<?php
/**
 * API : Annulation d'un reçu (Admin uniquement)
 *
 * POST : recu_id, motif (optionnel)
 *
 * Actions :
 *  - Marque recus.isDeleted = 1 (soft-delete)
 *  - Insère dans annulations_recus (audit trail)
 *  - Restaure le stock pharmacie si type_recu = 'pharmacie'
 *  - Restaure le stock carnet si type_recu = 'consultation' avec carnet
 *
 * Réponse JSON : { success, message }
 */
ob_start();
ini_set('display_errors', '0');
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('admin');          // Seul l'admin peut annuler
verifyCsrf();

header('Content-Type: application/json');

$pdo    = Database::getInstance();
$userId = Session::getUserId();

$recuId = (int)($_POST['recu_id'] ?? 0);
$motif  = trim($_POST['motif'] ?? '');

if ($recuId <= 0) {
    jsonError('Identifiant de reçu invalide.');
}

// ── 1. Récupérer le reçu ─────────────────────────────────────────────────
$stmtRecu = $pdo->prepare("
    SELECT r.id, r.numero_recu, r.type_recu, r.type_patient,
           r.montant_encaisse, r.isDeleted, r.whodone
    FROM recus r
    WHERE r.id = :id
    LIMIT 1
");
$stmtRecu->execute([':id' => $recuId]);
$recu = $stmtRecu->fetch();

if (!$recu) {
    jsonError('Reçu introuvable.');
}
if ((int)$recu['isDeleted'] === 1) {
    jsonError('Ce reçu est déjà annulé.');
}

try {
    $pdo->beginTransaction();

    // ── 2. Soft-delete du reçu ───────────────────────────────────────────
    $pdo->prepare("UPDATE recus SET isDeleted = 1, lastUpdate = NOW() WHERE id = :id")
        ->execute([':id' => $recuId]);

    // ── 3. Audit trail ───────────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO annulations_recus
            (recu_id, type_recu, motif, montant_annule, whendone, whodone)
        VALUES
            (:rid, :type, :motif, :montant, NOW(), :who)
    ")->execute([
        ':rid'     => $recuId,
        ':type'    => $recu['type_recu'],
        ':motif'   => $motif ?: 'Annulation administrative',
        ':montant' => (int)$recu['montant_encaisse'],
        ':who'     => $userId,
    ]);

    // ── 4. Restauration stock pharmacie ──────────────────────────────────
    if ($recu['type_recu'] === 'pharmacie') {
        $stmtLignes = $pdo->prepare("
            SELECT lp.produit_id, lp.quantite,
                   pp.stock_actuel
            FROM lignes_pharmacie lp
            JOIN produits_pharmacie pp ON pp.id = lp.produit_id
            WHERE lp.recu_id = :rid AND lp.isDeleted = 0
        ");
        $stmtLignes->execute([':rid' => $recuId]);
        $lignesPharm = $stmtLignes->fetchAll();

        foreach ($lignesPharm as $lp) {
            $produitId   = (int)$lp['produit_id'];
            $qteRestorer = (int)$lp['quantite'];
            $stockAvant  = (int)$lp['stock_actuel'];
            $stockApres  = $stockAvant + $qteRestorer;

            // Remettre le stock
            $pdo->prepare("
                UPDATE produits_pharmacie
                SET stock_actuel = stock_actuel + :qty, lastUpdate = NOW()
                WHERE id = :id
            ")->execute([':qty' => $qteRestorer, ':id' => $produitId]);

            // Mouvement d'annulation dans l'historique
            try {
                $pdo->prepare("
                    INSERT INTO mouvements_stock_pharmacie
                        (produit_id, type_mvt, quantite, stock_avant, stock_apres,
                         recu_id, commentaire, whodone)
                    VALUES
                        (:pid, 'correction', :qty, :sb, :sa, :rid, :comm, :who)
                ")->execute([
                    ':pid'  => $produitId,
                    ':qty'  => $qteRestorer,
                    ':sb'   => $stockAvant,
                    ':sa'   => $stockApres,
                    ':rid'  => $recuId,
                    ':comm' => 'Annulation reçu #' . $recu['numero_recu'],
                    ':who'  => $userId,
                ]);
            } catch (Exception $ignored) {
                // La table peut ne pas encore exister — ne pas bloquer
            }
        }
    }

    // ── 5. Restauration carnet si consultation avec carnet ───────────────
    if ($recu['type_recu'] === 'consultation') {
        // Vérifier si un carnet a été utilisé pour ce reçu
        $stmtCarnet = $pdo->prepare("
            SELECT COUNT(*) FROM lignes_consultation
            WHERE recu_id = :rid AND avec_carnet >= 1 AND isDeleted = 0
        ");
        $stmtCarnet->execute([':rid' => $recuId]);
        $aCarnet = (int)$stmtCarnet->fetchColumn();

        if ($aCarnet > 0) {
            try {
                // Lire le stock actuel
                $cfgRows = $pdo->query("
                    SELECT cle, valeur FROM config_systeme
                    WHERE cle IN ('stock_carnets') AND isDeleted = 0
                ")->fetchAll(PDO::FETCH_KEY_PAIR);
                $stockActuel = (int)($cfgRows['stock_carnets'] ?? 0);
                $nouveauStock = $stockActuel + 1;

                $pdo->prepare("
                    INSERT INTO config_systeme (cle, valeur, whodone)
                    VALUES ('stock_carnets', :v, :w)
                    ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2
                ")->execute([
                    ':v'  => $nouveauStock,
                    ':w'  => $userId,
                    ':v2' => $nouveauStock,
                    ':w2' => $userId,
                ]);

                // Mouvement carnet
                $pdo->prepare("
                    INSERT INTO mouvements_carnets
                        (type_mvt, quantite, stock_avant, stock_apres, recu_id, commentaire, whodone)
                    VALUES
                        ('correction', 1, :sb, :sa, :rid, :comm, :who)
                ")->execute([
                    ':sb'   => $stockActuel,
                    ':sa'   => $nouveauStock,
                    ':rid'  => $recuId,
                    ':comm' => 'Annulation reçu #' . $recu['numero_recu'],
                    ':who'  => $userId,
                ]);
            } catch (Exception $ignored) {
                // Ne pas bloquer si la table n'existe pas encore
            }
        }
    }

    $pdo->commit();

    $numF = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
    jsonSuccess("Reçu {$numF} annulé avec succès.", [
        'recu_id'    => $recuId,
        'numero_recu' => $recu['numero_recu'],
        'type_recu'  => $recu['type_recu'],
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'administrateur.'));
}
