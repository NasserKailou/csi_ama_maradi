<?php
/**
 * modules/percepteur/ajax_save_modification.php
 * Endpoint AJAX – enregistre une modification de reçu (JSON)
 */
ob_start();

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__FILE__, 3));
}

set_error_handler(function(int $no, string $msg, string $file, int $line): bool {
    if (ob_get_level()) ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => "PHP [$no]: $msg (ligne $line)"]);
    exit;
});
set_exception_handler(function(Throwable $e): void {
    if (ob_get_level()) ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => get_class($e).': '.$e->getMessage().' (L.'.$e->getLine().')']);
    exit;
});

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ── Session ───────────────────────────────────────────────────────────────────
Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}
if (!in_array(Session::getRole(), ['percepteur','admin','comptable'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

// ── Paramètres POST ───────────────────────────────────────────────────────────
$pdo    = Database::getInstance();
$userId = Session::getUserId();

$recuId   = (int)($_POST['recu_id']  ?? 0);
$typeRecu = trim($_POST['type_recu'] ?? '');
$motif    = trim($_POST['motif']     ?? '');

if (!$recuId || !$typeRecu || !$motif) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes (recu_id/type_recu/motif)']);
    exit;
}
if (!in_array($typeRecu, ['consultation','examen','pharmacie'], true)) {
    echo json_encode(['success' => false, 'message' => 'Type invalide']);
    exit;
}

// ── Chargement du reçu ────────────────────────────────────────────────────────
// On utilise UNIQUEMENT recus.type_patient comme source de vérité
// (figé au moment de la création, jamais altéré par un changement de statut patient)
$stmtRecu = $pdo->prepare("
    SELECT r.id,
           r.numero_recu,
           r.type_patient,
           r.statut_reglement,
           r.montant_total,
           r.montant_encaisse
    FROM recus r
    WHERE r.id = :id
      AND r.isDeleted = 0
    LIMIT 1
");
$stmtRecu->execute([':id' => $recuId]);
$recu = $stmtRecu->fetch(PDO::FETCH_ASSOC);

if (!$recu) {
    echo json_encode(['success' => false, 'message' => 'Reçu introuvable']);
    exit;
}

// Protection : interdire la modification d'un reçu orphelin déjà réglé par DirectAid AMA
if ($recu['type_patient'] === 'orphelin' && $recu['statut_reglement'] === 'regle') {
    echo json_encode([
        'success' => false,
        'message' => 'Ce reçu a déjà été réglé par DirectAid AMA. Modification interdite.'
    ]);
    exit;
}



// Source de vérité unique : recus.type_patient
$isOrphelin  = ($recu['type_patient'] === 'orphelin');
$detailAvant = [];
$detailApres = [];

try {
    $pdo->beginTransaction();

    // ── CONSULTATION ──────────────────────────────────────────────────────────
    if ($typeRecu === 'consultation') {

        $avecCarnet     = (int)($_POST['avec_carnet'] ?? 0);
        $nouveauMontant = $isOrphelin ? 0 : ($avecCarnet ? 400 : 300);

        $detailAvant = [
            'montant_total'    => (int)($recu['montant_total']    ?? 0),
            'montant_encaisse' => (int)($recu['montant_encaisse'] ?? 0),
        ];
        $detailApres = [
            'avec_carnet'      => $avecCarnet,
            'montant_total'    => $nouveauMontant,
            'montant_encaisse' => $nouveauMontant,
        ];

        // Mettre à jour lignes_consultation
        $pdo->prepare("
            UPDATE lignes_consultation
               SET avec_carnet  = :ac,
                   tarif_carnet = :tc,
                   lastUpdate   = NOW()
             WHERE recu_id  = :rid
               AND isDeleted = 0
        ")->execute([
            ':ac'  => $avecCarnet,
            ':tc'  => $avecCarnet ? 100 : 0,
            ':rid' => $recuId,
        ]);

        // Mettre à jour recus
        $pdo->prepare("
            UPDATE recus
               SET montant_total    = :mt,
                   montant_encaisse = :me,
                   lastUpdate       = NOW()
             WHERE id = :id
        ")->execute([
            ':mt' => $nouveauMontant,
            ':me' => $nouveauMontant,
            ':id' => $recuId,
        ]);
    }

    // ── EXAMEN ────────────────────────────────────────────────────────────────
    elseif ($typeRecu === 'examen') {

        $examensStr  = trim($_POST['examens'] ?? '');
        $nouveauxIds = array_values(
            array_filter(array_map('intval', explode(',', $examensStr)))
        );

        if (empty($nouveauxIds)) {
            throw new Exception('Aucun examen sélectionné');
        }

        // Ancien état
        $stmtAnc = $pdo->prepare("
            SELECT le.examen_id,
                   e.libelle,
                   le.cout_total
            FROM lignes_examen le
            JOIN examens e ON e.id = le.examen_id
            WHERE le.recu_id  = :rid
              AND le.isDeleted = 0
        ");
        $stmtAnc->execute([':rid' => $recuId]);
        $detailAvant = [
            'examens'       => $stmtAnc->fetchAll(PDO::FETCH_ASSOC),
            'montant_total' => (int)$recu['montant_total'],
        ];

        // Infos nouveaux examens
        $ph     = implode(',', array_fill(0, count($nouveauxIds), '?'));
        $stmtEx = $pdo->prepare("
            SELECT id, libelle, cout_total, pourcentage_labo
            FROM examens
            WHERE id IN ($ph)
              AND isDeleted = 0
        ");
        $stmtEx->execute($nouveauxIds);
        $examensData = $stmtEx->fetchAll(PDO::FETCH_ASSOC);

        if (empty($examensData)) {
            throw new Exception('Examens sélectionnés introuvables en base');
        }

        $nouveauTotal = $isOrphelin
            ? 0
            : array_sum(array_column($examensData, 'cout_total'));

        $detailApres = [
            'examens'       => $examensData,
            'montant_total' => $nouveauTotal,
        ];

        // Soft-delete anciens
        $pdo->prepare("
            UPDATE lignes_examen
               SET isDeleted  = 1,
                   lastUpdate = NOW()
             WHERE recu_id = :rid
        ")->execute([':rid' => $recuId]);

        // Insertion nouveaux
        $stmtIns = $pdo->prepare("
            INSERT INTO lignes_examen
                (recu_id, examen_id, libelle, cout_total,
                 pourcentage_labo, montant_labo, whodone, isDeleted)
            VALUES
                (:rid, :eid, :lib, :ct, :pct, :ml, :who, 0)
        ");
        foreach ($examensData as $ex) {
            $ct  = $isOrphelin ? 0 : (int)$ex['cout_total'];
            $pct = (float)$ex['pourcentage_labo'];
            $ml  = $isOrphelin ? 0 : (int)round($ct * $pct / 100);
            $stmtIns->execute([
                ':rid' => $recuId,
                ':eid' => (int)$ex['id'],
                ':lib' => $ex['libelle'],
                ':ct'  => $ct,
                ':pct' => $pct,
                ':ml'  => $ml,
                ':who' => $userId,
            ]);
        }

        // Mise à jour recus
        $pdo->prepare("
            UPDATE recus
               SET montant_total    = :mt,
                   montant_encaisse = :me,
                   lastUpdate       = NOW()
             WHERE id = :id
        ")->execute([
            ':mt' => $nouveauTotal,
            ':me' => $nouveauTotal,
            ':id' => $recuId,
        ]);
    }

    // ── PHARMACIE ─────────────────────────────────────────────────────────────
    elseif ($typeRecu === 'pharmacie') {

        $rawProduits = trim($_POST['produits'] ?? '');
        $items       = json_decode($rawProduits, true);

        if (!is_array($items) || empty($items)) {
            throw new Exception('Format produits invalide');
        }
        if (count($items) > 15) {
            throw new Exception('Maximum 15 produits autorisés');
        }

        // Ancien état
        $stmtAnc = $pdo->prepare("
            SELECT lp.produit_id,
                   lp.quantite,
                   lp.nom,
                   lp.prix_unitaire,
                   lp.total_ligne
            FROM lignes_pharmacie lp
            WHERE lp.recu_id  = :rid
              AND lp.isDeleted = 0
        ");
        $stmtAnc->execute([':rid' => $recuId]);
        $anciennesLignes = $stmtAnc->fetchAll(PDO::FETCH_ASSOC);
        $detailAvant     = [
            'lignes'        => $anciennesLignes,
            'montant_total' => (int)$recu['montant_total'],
        ];

        // Restaurer stock ancien
        foreach ($anciennesLignes as $old) {
            $pdo->prepare("
                UPDATE produits_pharmacie
                   SET stock_actuel = stock_actuel + :q
                 WHERE id = :id
            ")->execute([
                ':q'  => (int)$old['quantite'],
                ':id' => (int)$old['produit_id'],
            ]);
        }

        // Soft-delete anciennes lignes
        $pdo->prepare("
            UPDATE lignes_pharmacie
               SET isDeleted  = 1,
                   lastUpdate = NOW()
             WHERE recu_id = :rid
        ")->execute([':rid' => $recuId]);

        // Nouvelles lignes
        $nouveauTotal = 0;
        $lignesApres  = [];

        $stmtIns = $pdo->prepare("
            INSERT INTO lignes_pharmacie
                (recu_id, produit_id, nom, forme,
                 quantite, prix_unitaire, total_ligne, whodone, isDeleted)
            VALUES
                (:rid, :pid, :nom, :forme,
                 :qte, :pu, :tl, :who, 0)
        ");

        foreach ($items as $item) {
            $prodId = (int)($item['id']  ?? 0);
            $qte    = (int)($item['qte'] ?? 0);
            if ($prodId <= 0 || $qte <= 0) continue;

            $stmtProd = $pdo->prepare("
                SELECT id, nom, forme, prix_unitaire, stock_actuel
                FROM produits_pharmacie
                WHERE id        = :id
                  AND isDeleted = 0
                LIMIT 1
            ");
            $stmtProd->execute([':id' => $prodId]);
            $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("Produit #$prodId introuvable");
            }
            if ((int)$prod['stock_actuel'] < $qte) {
                throw new Exception(
                    "Stock insuffisant : {$prod['nom']} (disponible : {$prod['stock_actuel']})"
                );
            }

            $pu         = (int)$prod['prix_unitaire'];
            $totalLigne = $isOrphelin ? 0 : ($qte * $pu);
            $nouveauTotal += $totalLigne;

            $stmtIns->execute([
                ':rid'   => $recuId,
                ':pid'   => $prodId,
                ':nom'   => $prod['nom'],
                ':forme' => $prod['forme'],
                ':qte'   => $qte,
                ':pu'    => $pu,
                ':tl'    => $totalLigne,
                ':who'   => $userId,
            ]);

            // Déduire du stock
            $pdo->prepare("
                UPDATE produits_pharmacie
                   SET stock_actuel = stock_actuel - :q
                 WHERE id = :id
            ")->execute([':q' => $qte, ':id' => $prodId]);

            $lignesApres[] = [
                'produit_id' => $prodId,
                'nom'        => $prod['nom'],
                'forme'      => $prod['forme'],
                'quantite'   => $qte,
                'prix'       => $pu,
                'montant'    => $totalLigne,
            ];
        }

        if (empty($lignesApres)) {
            throw new Exception('Aucun produit valide dans la liste');
        }

        $detailApres = [
            'lignes'        => $lignesApres,
            'montant_total' => $nouveauTotal,
        ];

        // Mise à jour recus
        $pdo->prepare("
            UPDATE recus
               SET montant_total    = :mt,
                   montant_encaisse = :me,
                   lastUpdate       = NOW()
             WHERE id = :id
        ")->execute([
            ':mt' => $nouveauTotal,
            ':me' => $nouveauTotal,
            ':id' => $recuId,
        ]);
    }

    // ── Traçabilité ───────────────────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO modifications_recus
            (recu_id, user_id, type_recu, motif,
             detail_avant, detail_apres, whendone)
        VALUES
            (:rid, :uid, :type, :motif,
             :avant, :apres, NOW())
    ")->execute([
        ':rid'   => $recuId,
        ':uid'   => $userId,
        ':type'  => $typeRecu,
        ':motif' => $motif,
        ':avant' => json_encode($detailAvant, JSON_UNESCAPED_UNICODE),
        ':apres' => json_encode($detailApres, JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Modification enregistrée avec succès',
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
exit;
