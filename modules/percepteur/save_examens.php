<?php
/**
 * API : Sauvegarde Examens
 * POST : recu_id (du reçu de consultation parent), examens (IDs séparés par virgule)
 *
 * Statut de règlement :
 *   - orphelin     => en_instance
 *   - normal       => regle
 *   - acte_gratuit => regle
 */
ob_start();
ini_set('display_errors', '0');
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
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

if (!$recuId || empty($exIds)) jsonError('Reçu et examens obligatoires.');

// ── Récupération du reçu parent (source de vérité) ──────────────────────────
$recu = $pdo->prepare("
    SELECT r.id, r.patient_id, r.type_patient, p.est_orphelin
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.id = :id AND r.isDeleted = 0
    LIMIT 1
");
$recu->execute([':id' => $recuId]);
$recuData = $recu->fetch();
if (!$recuData) jsonError('Reçu introuvable.');

$patientId   = (int)$recuData['patient_id'];
$typePatient = $recuData['type_patient'];
$estOrphelin = ($typePatient === 'orphelin' || (int)$recuData['est_orphelin'] === 1);

try {
    $pdo->beginTransaction();

    // ── Récupérer les examens sélectionnés ──────────────────────────────
    $placeholders = implode(',', array_fill(0, count($exIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, libelle, cout_total, pourcentage_labo
        FROM examens
        WHERE id IN ($placeholders) AND isDeleted = 0
    ");
    $stmt->execute($exIds);
    $examens = $stmt->fetchAll();

    if (empty($examens)) jsonError('Aucun examen valide trouvé.');

    // ── Insérer les lignes (rattachées au reçu de consultation parent) ──
    $montantTotal = 0;
    foreach ($examens as $e) {
        $montantLabo = $estOrphelin ? 0 : (int)round($e['cout_total'] * $e['pourcentage_labo'] / 100);

        $pdo->prepare("
            INSERT INTO lignes_examen
                (recu_id, examen_id, libelle, cout_total,
                 pourcentage_labo, montant_labo, whodone)
            VALUES (:rid, :eid, :lib, :ct, :pct, :ml, :who)
        ")->execute([
            ':rid' => $recuId,
            ':eid' => $e['id'],
            ':lib' => $e['libelle'],
            ':ct'  => $e['cout_total'],
            ':pct' => $e['pourcentage_labo'],
            ':ml'  => $montantLabo,
            ':who' => $userId,
        ]);

        $montantTotal += $e['cout_total'];
    }

    // ── Créer le reçu examen séparé ─────────────────────────────────────
    $montantEncaisse = $estOrphelin ? 0 : $montantTotal;
    $statutReglement = $estOrphelin ? 'en_instance' : 'regle';
    $dateReglement   = $estOrphelin ? null : date('Y-m-d H:i:s');

    $numRecu = getNextNumeroRecu($pdo);

    $pdo->prepare("
        INSERT INTO recus
            (numero_recu, patient_id, type_recu, type_patient,
             statut_reglement, date_reglement,
             montant_total, montant_encaisse, whodone)
        VALUES
            (:num, :pat, 'examen', :tp,
             :sr, :dr,
             :mt, :me, :who)
    ")->execute([
        ':num' => $numRecu,
        ':pat' => $patientId,
        ':tp'  => $typePatient,
        ':sr'  => $statutReglement,
        ':dr'  => $dateReglement,
        ':mt'  => $montantTotal,
        ':me'  => $montantEncaisse,
        ':who' => $userId,
    ]);
    $newRecuId = (int)$pdo->lastInsertId();

    // ── Déplacer les lignes vers le nouveau reçu examen ─────────────────
    $pdo->prepare("
        UPDATE lignes_examen
        SET recu_id = :nid
        WHERE recu_id = :oid AND whodone = :who
    ")->execute([
        ':nid' => $newRecuId,
        ':oid' => $recuId,
        ':who' => $userId,
    ]);

    $pdo->commit();

    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf     = new PdfGenerator($pdo);
    $pdfFile = $pdf->generateExamen($newRecuId);

    jsonSuccess(
        $estOrphelin ? 'Examens enregistrés (en instance – orphelin).' : 'Examens enregistrés.',
        [
            'recu_id'     => $newRecuId,
            'numero_recu' => $numRecu,
            'pdf_url'     => url('uploads/pdf/' . basename($pdfFile)),
        ]
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
}
