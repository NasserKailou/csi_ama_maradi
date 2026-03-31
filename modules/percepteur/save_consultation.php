<?php
/**
 * API : Sauvegarde Consultation (Normal / Orphelin)
 * POST : type_patient, telephone, nom, sexe, age, provenance, avec_carnet
 */
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable');
verifyCsrf();

header('Content-Type: application/json');

$pdo         = Database::getInstance();
$userId      = Session::getUserId();
$typePatient = in_array($_POST['type_patient'] ?? '', ['normal','orphelin','acte_gratuit']) ? $_POST['type_patient'] : 'normal';
$telephone   = preg_replace('/\D/', '', trim($_POST['telephone'] ?? ''));
$nom         = trim($_POST['nom'] ?? '');
$sexe        = in_array($_POST['sexe'] ?? 'M', ['M','F']) ? $_POST['sexe'] : 'M';
$age         = max(0, (int)($_POST['age'] ?? 0));
$provenance  = trim($_POST['provenance'] ?? '');
$avecCarnet  = (int)($_POST['avec_carnet'] ?? 1);

if (!$telephone || !$nom) jsonError('Téléphone et nom obligatoires.');
if (strlen($telephone) !== 8 || !ctype_digit($telephone)) jsonError('Le numéro de téléphone doit contenir exactement 8 chiffres.');

try {
    $pdo->beginTransaction();

    // ── 1. Upsert patient (déduplication par téléphone) ───────────────────
    $stmtP = $pdo->prepare("SELECT id FROM patients WHERE telephone=:tel AND isDeleted=0 LIMIT 1");
    $stmtP->execute([':tel' => $telephone]);
    $patientId = $stmtP->fetchColumn();

    if ($patientId) {
        // UPDATE existant
        $pdo->prepare("UPDATE patients SET nom=:nom, sexe=:sexe, age=:age, provenance=:prov, whodone=:who WHERE id=:id")
            ->execute([':nom'=>$nom,':sexe'=>$sexe,':age'=>$age,':prov'=>$provenance,':who'=>$userId,':id'=>$patientId]);
    } else {
        // INSERT nouveau
        $pdo->prepare("INSERT INTO patients (telephone, nom, sexe, age, provenance, whodone) VALUES (:tel,:nom,:sexe,:age,:prov,:who)")
            ->execute([':tel'=>$telephone,':nom'=>$nom,':sexe'=>$sexe,':age'=>$age,':prov'=>$provenance,':who'=>$userId]);
        $patientId = (int)$pdo->lastInsertId();
    }

    // ── 2. Numéro de reçu séquentiel global ───────────────────────────────
    $numRecu = getNextNumeroRecu($pdo);

    // ── 3. Calcul montants ─────────────────────────────────────────────────
    $tarifConsult = TARIF_CONSULTATION;
    $tarifCarnet  = ($avecCarnet && $typePatient === 'normal') ? TARIF_CARNET_SOINS : 0;
    $montantTotal    = $tarifConsult + $tarifCarnet;
    $montantEncaisse = ($typePatient === 'orphelin') ? 0 : $montantTotal;

    // ── 4. Insérer le reçu ────────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO recus (numero_recu, patient_id, type_recu, type_patient,
                           montant_total, montant_encaisse, whodone)
        VALUES (:num, :pat, 'consultation', :tp, :mt, :me, :who)
    ")->execute([
        ':num'=>$numRecu, ':pat'=>$patientId, ':tp'=>$typePatient,
        ':mt'=>$montantTotal, ':me'=>$montantEncaisse, ':who'=>$userId
    ]);
    $recuId = (int)$pdo->lastInsertId();

    // ── 5. Ligne consultation ─────────────────────────────────────────────
    // Acte de base (Consultation Générale id=1 ou premier acte disponible)
    $acteBase = $pdo->query("SELECT id, libelle FROM actes_medicaux WHERE est_gratuit=0 AND isDeleted=0 ORDER BY id LIMIT 1")->fetch();
    if ($acteBase) {
        $pdo->prepare("
            INSERT INTO lignes_consultation (recu_id, acte_id, libelle, tarif, est_gratuit, avec_carnet, tarif_carnet, whodone)
            VALUES (:rid, :aid, :lib, :tarif, :eg, :ac, :tc, :who)
        ")->execute([
            ':rid'=>$recuId, ':aid'=>$acteBase['id'], ':lib'=>$acteBase['libelle'],
            ':tarif'=>$tarifConsult, ':eg'=>($typePatient === 'orphelin' ? 1 : 0),
            ':ac'=>(int)($avecCarnet && $typePatient === 'normal'),
            ':tc'=>$tarifCarnet, ':who'=>$userId
        ]);
    }

    $pdo->commit();

    // ── 6. Générer le PDF ─────────────────────────────────────────────────
    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf = new PdfGenerator($pdo);
    $pdfFile = $pdf->generateConsultation($recuId);

    jsonSuccess('Reçu enregistré.', [
        'recu_id'    => $recuId,
        'numero_recu'=> $numRecu,
        'pdf_url'    => '/uploads/pdf/' . basename($pdfFile)
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
}
