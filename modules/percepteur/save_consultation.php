<?php
/**
 * API : Sauvegarde Consultation (Normal / Orphelin)
 * POST : type_patient, type_consultation, telephone (facultatif), nom, sexe, age, provenance, avec_carnet
 *
 * RÈGLES TARIFAIRES :
 *  - Consultation standard                   : 300 F (TARIF_CONSULTATION)
 *  - Carnet de soins (si avec_carnet=1)      : +100 F (TARIF_CARNET_SOINS)
 *  - Supplément âge > 5 ans (patient NORMAL UNIQUEMENT) : +100 F (TARIF_SUPPLEMENT_ADULTE)
 *  - Orphelin / Acte gratuit                : PAS de supplément âge (0 F)
 *  - Patient 0–5 ans : pas de supplément
 *  - Mise en observation                     : 1000 F fixe (TARIF_OBSERVATION)
 *      → PAS de carnet, PAS de supplément âge même si > 5 ans
 *
 * RÈGLES ORPHELIN :
 *  - sexe forcé à 'M', provenance forcée à 'Maradi', est_orphelin = 1
 *  - montant_encaisse = 0, statut_reglement = 'en_instance'
 *
 * RÈGLE TÉLÉPHONE :
 *  - Si vide → 99999999 (placeholder "non renseigné")
 *  - On ne fait pas d'upsert sur 99999999 (chaque cas crée une fiche distincte)
 */
ob_start();
ini_set('display_errors', '0');
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

require_once ROOT_PATH . '/core/CarnetsHelper.php';
CarnetsHelper::ensureConfig($pdo, $userId);

Session::start();
requireRole('percepteur', 'admin', 'comptable', 'major');
verifyCsrf();

header('Content-Type: application/json');

// ─────────────────────────────────────────────────────────────────────────────
//  Constantes locales (fallback si non définies dans config.php)
// ─────────────────────────────────────────────────────────────────────────────
if (!defined('TARIF_SUPPLEMENT_ADULTE')) {
    define('TARIF_SUPPLEMENT_ADULTE', 100);
}
if (!defined('AGE_LIMITE_SUPPLEMENT')) {
    define('AGE_LIMITE_SUPPLEMENT', 5);
}
if (!defined('TARIF_OBSERVATION')) {
    define('TARIF_OBSERVATION', 1000);
}
const TELEPHONE_PAR_DEFAUT_CONSULT = '99999999';

$pdo         = Database::getInstance();
$userId      = Session::getUserId();

$typePatient = in_array($_POST['type_patient'] ?? '', ['normal','orphelin','acte_gratuit'], true)
               ? $_POST['type_patient'] : 'normal';

// ── Type de consultation : standard | observation ───────────────────────────
$typeConsult = in_array($_POST['type_consultation'] ?? '', ['standard','observation'], true)
               ? $_POST['type_consultation'] : 'standard';

// ── Téléphone facultatif ────────────────────────────────────────────────────
$telephone   = preg_replace('/\D/', '', trim($_POST['telephone'] ?? ''));
if ($telephone === '') {
    $telephone = TELEPHONE_PAR_DEFAUT_CONSULT;
}

$nom         = trim($_POST['nom'] ?? '');
$age         = max(0, (int)($_POST['age'] ?? 0));
$avecCarnet  = (int)($_POST['avec_carnet'] ?? 1);

// ── Règles métier orphelin ──────────────────────────────────────────────────
if ($typePatient === 'orphelin') {
    $sexe        = 'M';
    $provenance  = 'Maradi';
    $estOrphelin = 1;
} else {
    $sexe        = in_array($_POST['sexe'] ?? 'M', ['M','F'], true) ? $_POST['sexe'] : 'M';
    $provenance  = trim($_POST['provenance'] ?? '');
    $estOrphelin = 0;
}

// ── Validations ─────────────────────────────────────────────────────────────
if (!$nom) {
    jsonError('Le nom du patient est obligatoire.');
}
if (strlen($telephone) !== 8 || !ctype_digit($telephone)) {
    jsonError('Le numéro de téléphone doit contenir exactement 8 chiffres (ou être laissé vide).');
}
if ($age < 0 || $age > 120) {
    jsonError('Âge invalide.');
}

try {
    $pdo->beginTransaction();

    // ── 1. Upsert patient ─────────────────────────────────────────────────
    //    On NE recherche pas de patient existant si le téléphone est le
    //    placeholder 99999999 → chaque saisie crée une nouvelle fiche.
    $patientId = null;
    if ($telephone !== TELEPHONE_PAR_DEFAUT_CONSULT) {
        $stmtP = $pdo->prepare("SELECT id FROM patients WHERE telephone = :tel AND isDeleted = 0 LIMIT 1");
        $stmtP->execute([':tel' => $telephone]);
        $patientId = $stmtP->fetchColumn() ?: null;
    }

    if ($patientId) {
        $pdo->prepare("
            UPDATE patients
            SET nom=:nom, sexe=:sexe, age=:age, provenance=:prov, est_orphelin=:orp, whodone=:who
            WHERE id=:id
        ")->execute([
            ':nom'=>$nom, ':sexe'=>$sexe, ':age'=>$age, ':prov'=>$provenance,
            ':orp'=>$estOrphelin, ':who'=>$userId, ':id'=>$patientId,
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO patients (telephone, nom, sexe, age, provenance, est_orphelin, whodone)
            VALUES (:tel, :nom, :sexe, :age, :prov, :orp, :who)
        ")->execute([
            ':tel'=>$telephone, ':nom'=>$nom, ':sexe'=>$sexe, ':age'=>$age,
            ':prov'=>$provenance, ':orp'=>$estOrphelin, ':who'=>$userId,
        ]);
        $patientId = (int)$pdo->lastInsertId();
    }

    // ── 2. Numéro de reçu séquentiel global ───────────────────────────────
    $numRecu = getNextNumeroRecu($pdo);

// ── 3. Calcul des montants ────────────────────────────────────────────
     // Vérification stock CARNET DE SOINS si demandé
    if ($avecCarnet && $typePatient === 'normal' && $typeConsult === 'standard') {
        $checkSoins = CarnetsHelper::getStock($pdo, CarnetsHelper::TYPE_SOINS);
        if ($checkSoins['stock'] <= 0) {
            $pdo->rollBack();
            jsonError('Stock de carnets de soins épuisé. Veuillez réapprovisionner (Paramétrage → Carnets de soins).');
        }
    }

    if ($typeConsult === 'observation') {
        // MISE EN OBSERVATION : 1000 F fixe, pas de carnet, pas de supplément âge
        $tarifConsult   = (int)TARIF_OBSERVATION;
        $libelleConsult = 'Mise en observation';
        $tarifCarnet    = 0;
        $supplementAge  = 0;
        $avecCarnet     = 0;
    } else {
        // CONSULTATION STANDARD
        $tarifConsult   = (int)TARIF_CONSULTATION;
        $libelleConsult = null; // sera repris depuis actes_medicaux
        $tarifCarnet    = ($avecCarnet && $typePatient === 'normal')
                          ? (int)TARIF_CARNET_SOINS
                          : 0;

        // Bloquer si carnet demandé et stock = 0 (déjà vérifié avant la transaction, mais doublon de sécurité)
        // Note : $checkSoins est déjà lu juste avant la transaction, on réutilise sa valeur
        if ($avecCarnet && $typePatient === 'normal' && isset($checkSoins) && $checkSoins['stock'] <= 0) {
            $pdo->rollBack();
            jsonError('Stock de carnets de soins épuisé. Veuillez réapprovisionner (Paramétrage → Carnets de soins).');
        }

        $supplementAge = ($age > AGE_LIMITE_SUPPLEMENT) ? (int)TARIF_SUPPLEMENT_ADULTE : 0;
        // Pas de redevance pour les actes gratuits NI pour les orphelins
        // (la redevance ministère ne s'applique qu'aux patients normaux payants)
        if ($typePatient !== 'normal') {
            $supplementAge = 0;
        }
    }

    //$montantTotal    = $tarifConsult + $tarifCarnet + $supplementAge;
    $mtl = $tarifConsult + $tarifCarnet + $supplementAge;
    $montantTotal    = ($typePatient === 'orphelin') ? 0 :  $mtl;
    $montantEncaisse = ($typePatient === 'orphelin') ? 0 : $montantTotal;

    // ── 4. Statut de règlement ────────────────────────────────────────────
    $statutReglement = ($typePatient === 'orphelin') ? 'en_instance' : 'regle';
    $dateReglement   = ($typePatient === 'orphelin') ? null : date('Y-m-d H:i:s');

    // ── 5. Insertion du reçu ──────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO recus
            (numero_recu, patient_id, type_recu, type_patient,
             statut_reglement, date_reglement,
             montant_total, montant_encaisse, whodone)
        VALUES
            (:num, :pat, 'consultation', :tp,
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
    $recuId = (int)$pdo->lastInsertId();

    // ── 6. Ligne consultation principale ──────────────────────────────────
$acteBase = null;
$acteId   = null;

// ── 6. Préparation de la requête d'insertion (factorisée) ─────────────
$stmtLigne = $pdo->prepare("
    INSERT INTO lignes_consultation
        (recu_id, acte_id, type_ligne, libelle, tarif, est_gratuit,
         avec_carnet, tarif_carnet, whodone)
    VALUES
        (:rid, :aid, :tl, :lib, :tarif, :eg, :ac, :tc, :who)
");

// ── 6a. Ligne principale (consultation OU observation) ────────────────
if ($typeConsult === 'observation') {
    $stmtLigne->execute([
        ':rid'   => $recuId,
        ':aid'   => null,
        ':tl'    => 'observation',
        ':lib'   => 'Mise en observation',
        ':tarif' => $tarifConsult,           // 1000 F
        ':eg'    => $estOrphelin,
        ':ac'    => 0,
        ':tc'    => 0,
        ':who'   => $userId,
    ]);
} else {
    $stmtLigne->execute([
        ':rid'   => $recuId,
        ':aid'   => null,
        ':tl'    => 'consultation',
        ':lib'   => 'Consultation',
        ':tarif' => $tarifConsult,           // 300 F
        ':eg'    => $estOrphelin,
        ':ac'    => (int)($avecCarnet && $typePatient === 'normal'),
        ':tc'    => $tarifCarnet,
        ':who'   => $userId,
    ]);
}

// ── 6b. Ligne supplément âge (redevance ministère) ────────────────────
//       Jamais en observation, jamais orphelin, jamais acte gratuit, jamais ≤ 5 ans
//       ($supplementAge est déjà mis à 0 pour orphelin/acte_gratuit dans le calcul)
if ($supplementAge > 0 && $typeConsult === 'standard') {
    $stmtLigne->execute([
        ':rid'   => $recuId,
        ':aid'   => null,
        ':tl'    => 'redevance',
        ':lib'   => 'Redevance ministère (âge > ' . AGE_LIMITE_SUPPLEMENT . ' ans)',
        ':tarif' => $supplementAge,          // 100 F
        ':eg'    => $estOrphelin,
        ':ac'    => 0,
        ':tc'    => 0,
        ':who'   => $userId,
    ]);
}


    // ── 6 bis. Ligne supplément âge > 5 ans (si applicable) ───────────────
    //          Jamais ajouté pour la mise en observation.
    

    $pdo->commit();

    // ── 7. Décrémentation stock carnets ──────────────────────────────────
    // Si carnet utilisé (consultation normale avec carnet)
    $stockSoinsInfo = ['stock' => 0, 'seuil' => 10, 'alerte' => ''];
    if ($avecCarnet && $typePatient === 'normal' && $typeConsult === 'standard' && $tarifCarnet > 0) {
        $res = CarnetsHelper::decrement(
            $pdo,
            CarnetsHelper::TYPE_SOINS,
            $recuId,
            'Carnet de soins consultation #' . $numRecu,
            $userId
        );
        $stockSoinsInfo = [
            'stock'  => $res['stock_apres'],
            'seuil'  => $res['seuil'],
            'alerte' => $res['alerte'],
        ];
    } else {
        $infoSoins = CarnetsHelper::getStock($pdo, CarnetsHelper::TYPE_SOINS);
        $stockSoinsInfo['stock'] = $infoSoins['stock'];
        $stockSoinsInfo['seuil'] = $infoSoins['seuil'];
    }

    // ── 8. Génération du PDF ──────────────────────────────────────────────
    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf     = new PdfGenerator($pdo);
    $pdfFile = $pdf->generateConsultation($recuId);

    // ── 8. Construction du message de réponse ─────────────────────────────
    $details = [];
    if ($typeConsult === 'observation') {
        $details[] = $tarifConsult . ' F (mise en observation)';
    } else {
        $details[] = $tarifConsult . ' F (consultation)';
        if ($tarifCarnet > 0)   $details[] = $tarifCarnet . ' F (carnet)';
        if ($supplementAge > 0) $details[] = $supplementAge . ' F (supplément âge > ' . AGE_LIMITE_SUPPLEMENT . ' ans)';
    }

    $message = ($typePatient === 'orphelin')
        ? 'Reçu orphelin enregistré (gratuité totale — montant théorique : ' . $montantTotal . ' F).'
        : 'Reçu enregistré : ' . $montantTotal . ' F = ' . implode(' + ', $details);

    // Alerte carnets bas — basée sur $stockSoinsInfo qui est déjà rempli
    $alertCarnets = $stockSoinsInfo['alerte'] ?? '';

    jsonSuccess($message, [
        'recu_id'           => $recuId,
        'numero_recu'       => $numRecu,
        'type_consultation' => $typeConsult,
        'montant_total'     => $montantTotal,
        'montant_encaisse'  => $montantEncaisse,
        'tarif_consult'     => $tarifConsult,
        'tarif_carnet'      => $tarifCarnet,
        'supplement_age'    => $supplementAge,
        'avec_carnet'       => (int)($avecCarnet && $typePatient === 'normal' && $typeConsult === 'standard'),
        'pdf_url'           => url('uploads/pdf/' . basename($pdfFile)),
        'stock_carnets_soins' => $stockSoinsInfo['stock'],
        'alerte_carnets'      => $stockSoinsInfo['alerte']
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
}
