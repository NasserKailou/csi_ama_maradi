<?php
/**
 * API : Sauvegarde Pharmacie
 * POST : recu_id, produits (JSON : [{id, qte, nom, forme, prix}])
 *
 * RÈGLE ORPHELIN :
 *  - Stock décrémenté normalement (les médicaments sont bien délivrés)
 *  - montant_encaisse = 0 sur le reçu pharmacie
 *  - total_ligne      conservé dans lignes_pharmacie pour reporting bailleur
 *  - Détection : lecture est_orphelin depuis la table patients (jamais via POST)
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

$pdo      = Database::getInstance();
$userId   = Session::getUserId();
$recuId   = (int)($_POST['recu_id'] ?? 0);
$produits = json_decode($_POST['produits'] ?? '[]', true);

if (!$recuId || empty($produits)) {
    jsonError('Reçu et produits obligatoires.');
}
if (count($produits) > PHARMACIE_MAX_LIGNES) {
    jsonError('Maximum ' . PHARMACIE_MAX_LIGNES . ' produits par reçu.');
}

// ── Vérifier que le reçu existe + récupérer est_orphelin depuis patients ──
// Lecture en source de vérité BDD, jamais via POST
$recu = $pdo->prepare("
    SELECT r.id, r.patient_id, r.type_patient,
           p.est_orphelin
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.id = :id AND r.isDeleted = 0
    LIMIT 1
");
$recu->execute([':id' => $recuId]);
$recuData = $recu->fetch();
if (!$recuData) jsonError('Reçu introuvable.');

// Double vérification : type_patient OU flag est_orphelin en base
$estOrphelin = ($recuData['type_patient'] === 'orphelin' || (int)$recuData['est_orphelin'] === 1);

try {
    $pdo->beginTransaction();

    $montantTotal = 0;

    foreach ($produits as $item) {
        $produitId = (int)($item['id']  ?? 0);
        $qte       = max(1, (int)($item['qte'] ?? 0));

        if (!$produitId || $qte < 1) continue;

        // ── Récupérer le produit avec vérifications stock/péremption ────
        $stmtProd = $pdo->prepare("
            SELECT id, nom, forme, prix_unitaire, stock_actuel, date_peremption
            FROM produits_pharmacie
            WHERE id = :id AND isDeleted = 0
            LIMIT 1
        ");
        $stmtProd->execute([':id' => $produitId]);
        $prod = $stmtProd->fetch();

        if (!$prod) continue;

        // Contrôle stock
        if ($prod['stock_actuel'] <= 0) {
            $pdo->rollBack();
            jsonError("Produit en rupture de stock : {$prod['nom']}");
        }

        // Contrôle péremption
        if ($prod['date_peremption'] && $prod['date_peremption'] <= date('Y-m-d')) {
            $pdo->rollBack();
            jsonError("Produit périmé : {$prod['nom']}");
        }

        // Contrôle quantité disponible
        if ($qte > $prod['stock_actuel']) {
            $pdo->rollBack();
            jsonError("Stock insuffisant pour {$prod['nom']} (dispo: {$prod['stock_actuel']})");
        }

        $totalLigne    = $qte * $prod['prix_unitaire'];
        $montantTotal += $totalLigne;

        // ── Insérer la ligne pharmacie ───────────────────────────────────
        // total_ligne conservé à sa valeur réelle même pour orphelin
        // (nécessaire pour le reporting bailleur)
        $pdo->prepare("
            INSERT INTO lignes_pharmacie
                (recu_id, produit_id, nom, forme,
                 quantite, prix_unitaire, total_ligne, whodone)
            VALUES
                (:rid, :pid, :nom, :forme, :qte, :pu, :tl, :who)
        ")->execute([
            ':rid'   => $recuId,
            ':pid'   => $produitId,
            ':nom'   => $prod['nom'],
            ':forme' => $prod['forme'],
            ':qte'   => $qte,
            ':pu'    => $prod['prix_unitaire'],
            ':tl'    => $totalLigne,    // valeur réelle, pas 0
            ':who'   => $userId,
        ]);

        // ── Décrémentation stock : TOUJOURS, même pour orphelin ─────────
        // (les médicaments sont physiquement délivrés)
        $pdo->prepare("
            UPDATE produits_pharmacie
            SET stock_actuel = stock_actuel - :qte1,
                whodone      = :who
            WHERE id = :id AND stock_actuel >= :qte2
        ")->execute([
            ':qte1' => $qte,
            ':qte2' => $qte,
            ':who'  => $userId,
            ':id'   => $produitId,
        ]);
    }

    if ($montantTotal === 0) {
        $pdo->rollBack();
        jsonError('Aucun produit valide sélectionné.');
    }

    // ── Créer le reçu pharmacie séparé ───────────────────────────────────
    // Orphelin : montant_encaisse = 0, montant_total réel conservé
    $montantEncaisse = $estOrphelin ? 0 : $montantTotal;

    $numRecu = getNextNumeroRecu($pdo);
    $pdo->prepare("
        INSERT INTO recus
            (numero_recu, patient_id, type_recu, type_patient,
             montant_total, montant_encaisse, whodone)
        VALUES
            (:num, :pat, 'pharmacie', :tp, :mt, :me, :who)
    ")->execute([
        ':num' => $numRecu,
        ':pat' => $recuData['patient_id'],
        ':tp'  => $recuData['type_patient'],    // conserve 'orphelin' dans le reçu
        ':mt'  => $montantTotal,
        ':me'  => $montantEncaisse,
        ':who' => $userId,
    ]);
    $newRecuId = (int)$pdo->lastInsertId();

    // ── Déplacer les lignes vers le nouveau reçu pharmacie ───────────────
    $pdo->prepare("
        UPDATE lignes_pharmacie
        SET recu_id = :nid
        WHERE recu_id = :oid AND whodone = :who AND isDeleted = 0
    ")->execute([
        ':nid' => $newRecuId,
        ':oid' => $recuId,
        ':who' => $userId,
    ]);

    $pdo->commit();

    // ── Générer PDF ──────────────────────────────────────────────────────
    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf     = new PdfGenerator($pdo);
    $pdfFile = $pdf->generatePharmacie($newRecuId);

    jsonSuccess(
        $estOrphelin
            ? 'Pharmacie enregistrée (gratuit – orphelin). Stock mis à jour.'
            : 'Pharmacie enregistrée.',
        [
            'recu_id'     => $newRecuId,
            'numero_recu' => $numRecu,
            'pdf_url'     => url('uploads/pdf/' . basename($pdfFile)),
        ]
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur BDD : ' . (APP_ENV === 'development'
        ? $e->getMessage()
        : 'Contactez l\'administrateur.'));
}
