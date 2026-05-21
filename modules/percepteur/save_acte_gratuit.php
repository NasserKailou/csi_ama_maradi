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
 *                      2 = acte gratuit + carnet + fiche  → 400 F encaissés
 *                      3 = acte gratuit + fiche (300 F)   → 300 F encaissés)
 *
 * RÈGLES :
 *   - L'acte est toujours gratuit (tarif acte = 0 F sur le reçu).
 *   - Statut de règlement : toujours 'regle' (paiement direct au comptoir).
 *   - On stocke option_gratuite dans la colonne avec_carnet (0/1/2/3)
 *     et le montant total (carnet + éventuelle fiche) dans tarif_carnet.
 */
ob_start();
ini_set('display_errors', '0');
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

require_once ROOT_PATH . '/core/CarnetsHelper.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable', 'major');
verifyCsrf();

header('Content-Type: application/json');

// ─── Tarifs fixes ─────────────────────────────────────────────────────────
const TARIF_CARNET_SANTE_AG = 100;
const TARIF_FICHE_AG        = 300;
const TELEPHONE_PAR_DEFAUT  = '99999999';

$pdo        = Database::getInstance();
$userId     = Session::getUserId();
CarnetsHelper::ensureConfig($pdo, $userId);

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

// ✅ Choix d'option : 0, 1, 2 ou 3
$optionGratuite = (int)($_POST['option_gratuite'] ?? 0);
if (!in_array($optionGratuite, [0, 1, 2, 3], true)) {
    $optionGratuite = 0;
}

// ✅ Type de carnet choisi : 'soins' ou 'sante' (obligatoire si option 1 ou 2)
// Par défaut : 'soins' (sécurité — ne doit pas arriver si JS valide)
$carnetTypePost = strtolower(trim($_POST['carnet_type'] ?? 'soins'));
$carnetType     = ($carnetTypePost === 'sante') ? CarnetsHelper::TYPE_SANTE : CarnetsHelper::TYPE_SOINS;
$carnetTypeLib  = ($carnetType === CarnetsHelper::TYPE_SANTE) ? 'santé' : 'soins';

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
    //   3 → 300 F (fiche seule)
    switch ($optionGratuite) {
        case 1:
            $tarifCarnet = TARIF_CARNET_SANTE_AG;             // 100
            break;
        case 2:
            $tarifCarnet = TARIF_CARNET_SANTE_AG + TARIF_FICHE_AG; // 400
            break;
        case 3:
            $tarifCarnet = TARIF_FICHE_AG;                    // 300
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
    //    avec_carnet : 0 = aucun, 1 = carnet seul, 2 = carnet + fiche, 3 = fiche seule
    //    tarif_carnet : 0, 100, 300 ou 400 selon le choix
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

    // ── 6. Décrémentation stock carnets si carnet distribué (option 1 ou 2) ──
    //    On décrémente le TYPE choisi par le percepteur (soins OU santé)
    $stockCarnets   = 0;
    $alerteCarnets  = '';
    if ($optionGratuite === 1 || $optionGratuite === 2) {
        $commentaireMvt = ($optionGratuite === 2)
            ? "Carnet de {$carnetTypeLib} acte gratuit (+fiche) #{$numRecu}"
            : "Carnet de {$carnetTypeLib} acte gratuit #{$numRecu}";

        $res = CarnetsHelper::decrement(
            $pdo,
            $carnetType,          // ← TYPE_SOINS ou TYPE_SANTE selon le choix
            $recuId,
            $commentaireMvt,
            $userId
        );
        $stockCarnets  = $res['stock_apres'];
        $alerteCarnets = $res['alerte'];
    } else {
        // Pas de carnet : on renvoie les stocks des deux types pour info
        $infoSoins    = CarnetsHelper::getStock($pdo, CarnetsHelper::TYPE_SOINS);
        $stockCarnets = $infoSoins['stock']; // stock soins renvoyé par défaut
    }

    // ── 7. Décrémentation stock fiches AG si option 2 (carnet + fiche) ──────
    $stockFichesAg  = 0;
    $alerteFichesAg = '';
    if ($optionGratuite === 2) {
        $cfgFag = $pdo->query(
            "SELECT cle, valeur FROM config_systeme WHERE cle IN ('stock_fiches_ag','seuil_alerte_fiches_ag') AND isDeleted=0"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $stockFichesAg = (int)($cfgFag['stock_fiches_ag']         ?? 0);
        $seuilFichesAg = (int)($cfgFag['seuil_alerte_fiches_ag']  ?? 10);

        if ($stockFichesAg <= 0) {
            $alerteFichesAg = 'ATTENTION : Stock de fiches AG épuisé — fiche non décomptée.';
        } else {
            $newStockFag = max(0, $stockFichesAg - 1);
            $pdo->prepare(
                "INSERT INTO config_systeme (cle, valeur, whodone) VALUES ('stock_fiches_ag',:v,:w)
                 ON DUPLICATE KEY UPDATE valeur=:v2, whodone=:w2"
            )->execute([':v' => $newStockFag, ':w' => $userId, ':v2' => $newStockFag, ':w2' => $userId]);

            $pdo->prepare(
                "INSERT INTO mouvements_fiches_ag
                     (type_mvt, quantite, stock_avant, stock_apres, recu_id, commentaire, whodone)
                 VALUES ('sortie', -1, :sb, :sa, :rid, :cmt, :who)"
            )->execute([
                ':sb'  => $stockFichesAg,
                ':sa'  => $newStockFag,
                ':rid' => $recuId,
                ':cmt' => 'Fiche acte gratuit #' . $numRecu,
                ':who' => $userId,
            ]);
            $stockFichesAg = $newStockFag;

            if ($stockFichesAg === 0) {
                $alerteFichesAg = 'ATTENTION : Plus aucune fiche AG disponible !';
            } elseif ($stockFichesAg <= $seuilFichesAg) {
                $alerteFichesAg = 'Attention : Stock fiches AG bas – Reste ' . $stockFichesAg . ' fiche(s).';
            }
        }
    }

    $pdo->commit();

    // ── 8. Génération du PDF ─────────────────────────────────────────────
    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf     = new PdfGenerator($pdo);
    $pdfFile = $pdf->generateConsultation($recuId);

    // ── 9. Réponse JSON ──────────────────────────────────────────────────
    $carnetLibMsg = ucfirst($carnetTypeLib); // 'Soins' ou 'Santé'
    switch ($optionGratuite) {
        case 1:
            $message = "Acte gratuit + Carnet de {$carnetTypeLib} (100 F) enregistré.";
            break;
        case 2:
            $message = "Acte gratuit + Carnet de {$carnetTypeLib} + Fiche (400 F) enregistré.";
            break;
        case 3:
            $message = 'Acte gratuit + Fiche (300 F) enregistré.';
            break;
        default:
            $message = 'Acte gratuit enregistré (sans carnet ni fiche).';
    }

    jsonSuccess($message, [
        'recu_id'           => $recuId,
        'numero_recu'       => $numRecu,
        'option_gratuite'   => $optionGratuite,
        'carnet_type'       => ($optionGratuite === 1 || $optionGratuite === 2) ? $carnetType : null,
        'montant_total'     => $montantTotal,
        'montant_encaisse'  => $montantEncaisse,
        'stock_carnets'     => $stockCarnets,
        'alerte_carnets'    => $alerteCarnets,
        'stock_fiches_ag'   => $stockFichesAg,
        'alerte_fiches_ag'  => $alerteFichesAg,
        'pdf_url'           => url('uploads/pdf/' . basename($pdfFile)),
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
}
