<?php
/**
 * API : Sauvegarde Pharmacie – Transaction atomique + décrémentation stock
 * POST : recu_id, produits (JSON : [{id, qte, nom, forme, prix}, ...])
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
$produits = json_decode($_POST['produits'] ?? '[]', true);

if (!$recuId || !$produits || !is_array($produits)) jsonError('Données invalides.');
if (count($produits) > MAX_PRODUITS_RECU) jsonError('Maximum ' . MAX_PRODUITS_RECU . ' produits par reçu.');

// Vérifier appartenance reçu
$recu = $pdo->prepare("SELECT patient_id FROM recus WHERE id=:id AND whodone=:uid AND isDeleted=0 LIMIT 1");
$recu->execute([':id'=>$recuId, ':uid'=>$userId]);
$parent = $recu->fetch();
if (!$parent) jsonError('Reçu introuvable ou accès refusé.');

try {
    $pdo->beginTransaction();

    $numRecu      = getNextNumeroRecu($pdo);
    $montantTotal = 0;

    // Nouveau reçu pharmacie
    $pdo->prepare("
        INSERT INTO recus (numero_recu, patient_id, type_recu, type_patient,
                           montant_total, montant_encaisse, whodone)
        VALUES (:num, :pat, 'pharmacie', 'normal', 0, 0, :who)
    ")->execute([':num'=>$numRecu, ':pat'=>$parent['patient_id'], ':who'=>$userId]);
    $newRecuId = (int)$pdo->lastInsertId();

    $stmtProd = $pdo->prepare("
        SELECT id, nom, forme, prix_unitaire, stock_actuel, date_peremption
        FROM produits_pharmacie
        WHERE id=:id AND isDeleted=0
        FOR UPDATE
    ");
    $stmtDecrm = $pdo->prepare("UPDATE produits_pharmacie SET stock_actuel = stock_actuel - :qty WHERE id=:id");
    $stmtLigne = $pdo->prepare("
        INSERT INTO lignes_pharmacie (recu_id, produit_id, nom, forme, quantite, prix_unitaire, total_ligne, whodone)
        VALUES (:rid, :pid, :nom, :forme, :qty, :prix, :total, :who)
    ");

    foreach ($produits as $item) {
        $pid = (int)($item['id'] ?? 0);
        $qty = (int)($item['qte'] ?? 0);
        if (!$pid || $qty <= 0) continue;

        $stmtProd->execute([':id' => $pid]);
        $prod = $stmtProd->fetch();

        if (!$prod) {
            $pdo->rollBack();
            jsonError("Produit ID {$pid} introuvable.");
        }

        // ── Contrôles serveur (règle métier 6 & 10) ───────────────────────
        if ($prod['stock_actuel'] <= 0) {
            $pdo->rollBack();
            jsonError("Produit « {$prod['nom']} » en rupture de stock.");
        }
        if ($prod['date_peremption'] && $prod['date_peremption'] <= date('Y-m-d')) {
            $pdo->rollBack();
            jsonError("Produit « {$prod['nom']} » est périmé.");
        }
        if ($qty > $prod['stock_actuel']) {
            $pdo->rollBack();
            jsonError("Quantité demandée ({$qty}) dépasse le stock de « {$prod['nom']} » ({$prod['stock_actuel']}).");
        }

        $totalLigne = $qty * $prod['prix_unitaire'];
        $montantTotal += $totalLigne;

        // Décrémenter stock
        $stmtDecrm->execute([':qty' => $qty, ':id' => $pid]);

        // Insérer ligne
        $stmtLigne->execute([
            ':rid'=>$newRecuId, ':pid'=>$pid, ':nom'=>$prod['nom'],
            ':forme'=>$prod['forme'], ':qty'=>$qty,
            ':prix'=>$prod['prix_unitaire'], ':total'=>$totalLigne, ':who'=>$userId
        ]);
    }

    // Mettre à jour total reçu
    $pdo->prepare("UPDATE recus SET montant_total=:mt, montant_encaisse=:me WHERE id=:id")
        ->execute([':mt'=>$montantTotal, ':me'=>$montantTotal, ':id'=>$newRecuId]);

    $pdo->commit();

    // Générer PDF
    require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
    $pdf     = new PdfGenerator($pdo);
    $pdfFile = $pdf->generatePharmacie($newRecuId);

    jsonSuccess('Reçu pharmacie généré.', [
        'recu_id'    => $newRecuId,
        'numero_recu'=> $numRecu,
        'pdf_url'    => '/uploads/pdf/' . basename($pdfFile)
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Erreur : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Erreur serveur.'));
}
