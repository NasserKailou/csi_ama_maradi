<?php
/**
 * API : Sauvegarde Acte Gratuit (CPN, Nourrissons, etc.)
 *
 * POST :
 *   - type_patient   = acte_gratuit
 *   - telephone (facultatif → 99999999 par défaut), nom, sexe, age, provenance
 *   - acte_id        (id de l'acte gratuit choisi)
 *   - option_gratuite (0 = acte gratuit seul              → 0 F encaissés
 *                      1 = acte gratuit + carnet (100 F)  → 100 F encaissés
 *                      2 = acte gratuit + carnet + fiche  → 400 F encaissés)
 *
 * RÈGLES :
 *   - L'acte est toujours gratuit (tarif acte = 0 F sur le reçu).
 *   - Statut de règlement : toujours 'regle' (paiement direct au comptoir).
 *   - On stocke option_gratuite dans la colonne avec_carnet (0/1/2)
 *     et le montant total (carnet + éventuelle fiche) dans tarif_carnet.
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

// ─── Tarifs fixes ─────────────────────────────────────────────────────────
const TARIF_CARNET_SANTE_AG = 100;
const TARIF_FICHE_AG        = 300;
const TELEPHONE_PAR_DEFAUT  = '99999999';

$pdo        = Database::getInstance();
$userId     = Session::getUserId();

// ─── Téléphone facultatif : vide → 99999999 ──────────────────────────────
$telephone  = preg_replace('/\D/', '', trim($_POST['telephone'] ?? ''));
if ($telephone === '') {
    $telephone = TELEPHONE_PAR_DEFAUT;
}

$nom        = trim($_POST['nom'] ?? '');
$sexe       = in_array($_POST['sexe'] ?? 'F', ['M', 'F']) ? $_POST['sexe'] : 'F';
$age        = max(0, (int)($_POST['age'] ?? 0));
$provenance = trim($_POST['provenance'] ?? '');
$acteId     = (int)($_POST['acte_id'] ?? 0);

// ✅ Choix d'option : 0, 1 ou 2
$optionGratuite = (int)($_POST['option_gratuite'] ?? 0);
if (!in_array($optionGratuite, [0, 1, 2], true)) {
    $optionGratuite = 0;
}

// ─── Validations ──────────────────────────────────────────────────────────
if (!$nom || !$acteId) {
    jsonError('Nom et acte gratuit obligatoires.');
}
if (strlen($telephone) !== 8 || !ctype_digit($telephone)) {
    jsonError('Le numéro de téléphone doit contenir exactement 8 chiffres (ou être laissé vide).');
}

try {
    // Vérifier que l'acte existe bien et est marqué gratuit
    $acte = $pdo->prepare("
        SELECT id, libelle, tarif
        FROM actes_medicaux
        WHERE id = :id AND est_gratuit = 1 AND isDeleted = 0
        LIMIT 1
    ");
    $acte->execute([':id' => $acteId]);
    $acteData = $acte->fetch();
    if (!$acteData) {
        jsonError('Acte gratuit introuvable ou non autorisé.');
    }

    $pdo->beginTransaction();

    // ── 1. Upsert patient ────────────────────────────────────────────────
    // ⚠ Pour le numéro par défaut 99999999, on ne fait JAMAIS d'upsert
    //    (chaque patient sans téléphone doit créer une nouvelle fiche).
    $patientId = null;
    if ($telephone !== TELEPHONE_PAR_DEFAUT) {
        $stmtP = $pdo->prepare("SELECT id FROM patients WHERE telephone = :tel AND isDeleted = 0 LIMIT 1");
        $stmtP->execute([':tel' => $telephone]);
        $patientId = $stmtP->fetchColumn() ?: null;
    }

    if ($patientId) {
        $pdo->prepare("
            UPDATE patients
               SET nom = :nom, sexe = :sexe, age = :age,
                   provenance = :prov, whodone = :who
             WHERE id = :id
        ")->execute([
            ':nom'  => $nom,
            ':sexe' => $sexe,
            ':age'  => $age,
            ':prov' => $provenance,
            ':who'  => $userId,
            ':id'   => $patientId,
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO patients (telephone, nom, sexe, age, provenance, whodone)
            VALUES (:tel, :nom, :sexe, :age, :prov, :who)
        ")->execute([
            ':tel'  => $telephone,
            ':nom'  => $nom,
            ':sexe' => $sexe,
            ':age'  => $age,
            ':prov' => $provenance,
            ':who'  => $userId,
        ]);
        $patientId = (int)$pdo->lastInsertId();
    }

    // ── 2. Numéro de reçu séquentiel ─────────────────────────────────────
    $numRecu = getNextNumeroRecu($pdo);

    // ── 3. Calcul des montants selon option_gratuite ─────────────────────
    //   0 → 0 F   (acte gratuit seul)
    //   1 → 100 F (carnet seul)
    //   2 → 400 F (carnet + fiche)
    switch ($optionGratuite) {
        case 1:
            $tarifCarnet = TARIF_CARNET_SANTE_AG;             // 100
            break;
        case 2:
            $tarifCarnet = TARIF_CARNET_SANTE_AG + TARIF_FICHE_AG; // 400
            break;
        default:
            $tarifCarnet = 0;
    }
    $montantTotal    = $tarifCarnet;
    $montantEncaisse = $tarifCarnet;

    // ── 4. Insertion du reçu ─────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO recus
            (numero_recu, patient_id, type_recu, type_patient,
             statut_reglement, date_reglement,
             montant_total, montant_encaisse, whodone)
        VALUES
            (:num, :pat, 'consultation', 'acte_gratuit',
             'regle', NOW(),
             :mt, :me, :who)
    ")->execute([
        ':num' => $numRecu,
        ':pat' => $patientId,
        ':mt'  => $montantTotal,
        ':me'  => $montantEncaisse,
        ':who' => $userId,
    ]);
    $recuId = (int)$pdo->lastInsertId();

    // ── 5. Ligne consultation (acte gratuit + éventuel carnet/fiche) ─────
    //    avec_carnet : 0 = aucun, 1 = carnet seul, 2 = carnet + fiche
    //    tarif_carnet : 0, 100 ou 400 selon le choix
    $pdo->prepare("
        INSERT INTO lignes_consultation
            (recu_id, acte_id, libelle, tarif, est_gratuit,
             avec_carnet, tarif_carnet, whodone)
        VALUES
            (:rid, :aid, :lib, 0, 1,
             :avec, :tc, :who)
    ")->execute([
        ':rid'  => $recuId,
        ':aid'  => $acteData['id'],
        ':lib'  => $acteData['libelle'],
        ':avec' => $optionGratuite,
        ':tc'   => $tarifCarnet,
        ':who'  => $userId,
    ]);

    $pdo->commit();

    // ── 6. Génération du PDF ─────────────────────────────────────────────
    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf     = new PdfGenerator($pdo);
    $pdfFile = $pdf->generateConsultation($recuId);

    // ── 7. Réponse JSON ──────────────────────────────────────────────────
    switch ($optionGratuite) {
        case 1:
            $message = 'Acte gratuit + Carnet (100 F) enregistré.';
            break;
        case 2:
            $message = 'Acte gratuit + Carnet + Fiche (400 F) enregistré.';
            break;
        default:
            $message = 'Acte gratuit enregistré (sans carnet ni fiche).';
    }

    jsonSuccess($message, [
        'recu_id'          => $recuId,
        'numero_recu'      => $numRecu,
        'option_gratuite'  => $optionGratuite,
        'montant_total'    => $montantTotal,
        'montant_encaisse' => $montantEncaisse,
        'pdf_url'          => url('uploads/pdf/' . basename($pdfFile)),
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
}
