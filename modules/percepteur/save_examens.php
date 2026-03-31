<?php
/**
 * API : Sauvegarde Examens
 * POST : recu_id, examens (IDs séparés par virgule)
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable');
verifyCsrf();

header('Content-Type: application/json');

$pdo    = Database::getInstance();
$userId = Session::getUserId();
$recuId = (int)($_POST['recu_id'] ?? 0);
$exIds  = array_filter(array_map('intval', explode(',', $_POST['examens'] ?? '')));

if (!$recuId || empty($exIds)) {
    jsonError('Reçu et examens obligatoires.');
}

// Vérifier que le reçu appartient bien au percepteur connecté
$recu = $pdo->prepare("SELECT id, patient_id, whodone FROM recus WHERE id=:id AND isDeleted=0 LIMIT 1");
$recu->execute([':id' => $recuId]);
$recuData = $recu->fetch();
if (!$recuData) jsonError('Reçu introuvable.');

try {
    $pdo->beginTransaction();

    // Récupérer les examens sélectionnés
    $placeholders = implode(',', array_fill(0, count($exIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, libelle, cout_total, pourcentage_labo
        FROM examens
        WHERE id IN ($placeholders) AND isDeleted = 0
    ");
    $stmt->execute($exIds);
    $examens = $stmt->fetchAll();

    if (empty($examens)) jsonError('Aucun examen valide trouvé.');

    // Calculer le total examens
    $montantTotal = 0;
    foreach ($examens as $e) {
        $montantLabo = (int)round($e['cout_total'] * $e['pourcentage_labo'] / 100);
        // Insérer ligne examen
        $pdo->prepare("
            INSERT INTO lignes_examen
                (recu_id, examen_id, libelle, cout_total, pourcentage_labo, montant_labo, whodone)
            VALUES (:rid, :eid, :lib, :ct, :pct, :ml, :who)
        ")->execute([
            ':rid'=>$recuId, ':eid'=>$e['id'], ':lib'=>$e['libelle'],
            ':ct'=>$e['cout_total'], ':pct'=>$e['pourcentage_labo'],
            ':ml'=>$montantLabo, ':who'=>$userId
        ]);
        $montantTotal += $e['cout_total'];
    }

    // Créer un reçu examen séparé (lié au même patient)
    $numRecu = getNextNumeroRecu($pdo);
    $pdo->prepare("
        INSERT INTO recus (numero_recu, patient_id, type_recu, type_patient,
                           montant_total, montant_encaisse, whodone)
        VALUES (:num, :pat, 'examen', 'normal', :mt, :me, :who)
    ")->execute([
        ':num'=>$numRecu, ':pat'=>$recuData['patient_id'],
        ':mt'=>$montantTotal, ':me'=>$montantTotal, ':who'=>$userId
    ]);
    $newRecuId = (int)$pdo->lastInsertId();

    // Déplacer les lignes d'examen vers le nouveau reçu
    $pdo->prepare("UPDATE lignes_examen SET recu_id=:nid WHERE recu_id=:oid AND whodone=:who")
        ->execute([':nid'=>$newRecuId, ':oid'=>$recuId, ':who'=>$userId]);

    $pdo->commit();

    // Générer PDF
    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf     = new PdfGenerator($pdo);
    $pdfFile = $pdf->generateExamen($newRecuId);

    jsonSuccess('Examens enregistrés.', [
        'recu_id'     => $newRecuId,
        'numero_recu' => $numRecu,
        'pdf_url'     => '/uploads/pdf/' . basename($pdfFile)
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
}
