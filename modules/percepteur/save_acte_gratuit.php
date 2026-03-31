<?php
/**
 * API : Acte Gratuit (CPN, Accouchement, etc.)
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable');
verifyCsrf();
header('Content-Type: application/json');

$pdo       = Database::getInstance();
$userId    = Session::getUserId();
$telephone = trim($_POST['telephone'] ?? '');
$nom       = trim($_POST['nom'] ?? '');
$sexe      = in_array($_POST['sexe'] ?? 'F', ['M','F']) ? $_POST['sexe'] : 'F';
$age       = max(0, (int)($_POST['age'] ?? 0));
$provenance = trim($_POST['provenance'] ?? '');
$acteId    = (int)($_POST['acte_id'] ?? 0);

if (!$telephone || !$nom || !$acteId) jsonError('Données obligatoires manquantes.');

$stmtActe = $pdo->prepare("SELECT id, libelle, tarif FROM actes_medicaux WHERE id=:id AND est_gratuit=1 AND isDeleted=0");
$stmtActe->execute([':id' => $acteId]);
$acteData = $stmtActe->fetch();
if (!$acteData) jsonError('Acte gratuit invalide.');

try {
    $pdo->beginTransaction();

    // Upsert patient
    $stmtP = $pdo->prepare("SELECT id FROM patients WHERE telephone=:tel AND isDeleted=0");
    $stmtP->execute([':tel' => $telephone]);
    $patientId = $stmtP->fetchColumn();

    if ($patientId) {
        $pdo->prepare("UPDATE patients SET nom=:nom, sexe=:sexe, age=:age, provenance=:prov, whodone=:who WHERE id=:id")
            ->execute([':nom'=>$nom,':sexe'=>$sexe,':age'=>$age,':prov'=>$provenance,':who'=>$userId,':id'=>$patientId]);
    } else {
        $pdo->prepare("INSERT INTO patients (telephone, nom, sexe, age, provenance, whodone) VALUES (:tel,:nom,:sexe,:age,:prov,:who)")
            ->execute([':tel'=>$telephone,':nom'=>$nom,':sexe'=>$sexe,':age'=>$age,':prov'=>$provenance,':who'=>$userId]);
        $patientId = (int)$pdo->lastInsertId();
    }

    $numRecu = getNextNumeroRecu($pdo);

    $pdo->prepare("
        INSERT INTO recus (numero_recu, patient_id, type_recu, type_patient, montant_total, montant_encaisse, whodone)
        VALUES (:num, :pat, 'consultation', 'acte_gratuit', :mt, 0, :who)
    ")->execute([':num'=>$numRecu, ':pat'=>$patientId, ':mt'=>$acteData['tarif'], ':who'=>$userId]);
    $recuId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO lignes_consultation (recu_id, acte_id, libelle, tarif, est_gratuit, avec_carnet, tarif_carnet, whodone)
        VALUES (:rid, :aid, :lib, :tarif, 1, 0, 0, :who)
    ")->execute([':rid'=>$recuId,':aid'=>$acteId,':lib'=>$acteData['libelle'],':tarif'=>$acteData['tarif'],':who'=>$userId]);

    $pdo->commit();

    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf     = new PdfGenerator($pdo);
    $pdfFile = $pdf->generateConsultation($recuId);

    jsonSuccess('Reçu acte gratuit généré.', [
        'recu_id'     => $recuId,
        'numero_recu' => $numRecu,
        'pdf_url'     => '/uploads/pdf/' . basename($pdfFile)
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError(APP_ENV === 'development' ? $e->getMessage() : 'Erreur serveur.');
}
