<?php
/**
 * API : Sauvegarde Examens
 * POST : recu_id, examens (CSV d'IDs)
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable');
verifyCsrf();

header('Content-Type: application/json');

$pdo      = Database::getInstance();
$userId   = Session::getUserId();
$recuId   = (int)($_POST['recu_id'] ?? 0);
$examIds  = array_filter(array_map('intval', explode(',', $_POST['examens'] ?? '')));

if (!$recuId || !$examIds) jsonError('Données invalides.');

// Vérifier que le reçu appartient au percepteur connecté
$recu = $pdo->prepare("SELECT id FROM recus WHERE id=:id AND whodone=:uid AND isDeleted=0 LIMIT 1");
$recu->execute([':id'=>$recuId, ':uid'=>$userId]);
if (!$recu->fetch()) jsonError('Reçu introuvable ou accès refusé.');

try {
    $pdo->beginTransaction();

    // Numéro de reçu examens (nouveau reçu lié au même patient)
    $parentRecu = $pdo->prepare("SELECT patient_id, numero_recu FROM recus WHERE id=:id");
    $parentRecu->execute([':id' => $recuId]);
    $parent = $parentRecu->fetch();

    $numRecu      = getNextNumeroRecu($pdo);
    $montantTotal = 0;

    // Nouveau reçu type 'examen'
    $pdo->prepare("
        INSERT INTO recus (numero_recu, patient_id, type_recu, type_patient,
                           montant_total, montant_encaisse, whodone)
        VALUES (:num, :pat, 'examen', 'normal', 0, 0, :who)
    ")->execute([':num'=>$numRecu, ':pat'=>$parent['patient_id'], ':who'=>$userId]);
    $newRecuId = (int)$pdo->lastInsertId();

    // Récupérer infos examens et insérer lignes
    $stmtEx = $pdo->prepare("SELECT id, libelle, cout_total, pourcentage_labo, montant_labo FROM examens WHERE id=:id AND isDeleted=0");
    foreach ($examIds as $eid) {
        $stmtEx->execute([':id' => $eid]);
        $ex = $stmtEx->fetch();
        if (!$ex) continue;

        $montantLabo = (int)round($ex['cout_total'] * $ex['pourcentage_labo'] / 100);
        $pdo->prepare("
            INSERT INTO lignes_examen (recu_id, examen_id, libelle, cout_total, pourcentage_labo, montant_labo, whodone)
            VALUES (:rid, :eid, :lib, :cout, :pct, :labo, :who)
        ")->execute([
            ':rid'=>$newRecuId, ':eid'=>$eid, ':lib'=>$ex['libelle'],
            ':cout'=>$ex['cout_total'], ':pct'=>$ex['pourcentage_labo'],
            ':labo'=>$montantLabo, ':who'=>$userId
        ]);
        $montantTotal += $ex['cout_total'];
    }

    // Mettre à jour les montants du reçu examens
    $pdo->prepare("UPDATE recus SET montant_total=:mt, montant_encaisse=:me WHERE id=:id")
        ->execute([':mt'=>$montantTotal, ':me'=>$montantTotal, ':id'=>$newRecuId]);

    $pdo->commit();

    // Générer PDF
    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf    = new PdfGenerator($pdo);
    $pdfFile = $pdf->generateExamen($newRecuId);

    jsonSuccess('Reçu examens généré.', [
        'recu_id'     => $newRecuId,
        'numero_recu' => $numRecu,
        'pdf_url'     => '/uploads/pdf/' . basename($pdfFile)
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Erreur serveur.'));
}
