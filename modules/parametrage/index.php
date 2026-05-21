<?php
/**
 * Module Paramétrage – Admin & Comptable
 * Sections : actes, examens, pharmacie, config, inventaire, etat_labo
 */
requireRole('admin', 'comptable', 'major');

// ---------- INITIALISATION ----------
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/Database.php';
require_once ROOT_PATH . '/core/Session.php';
require_once ROOT_PATH . '/core/CarnetsHelper.php';

$pdo = Database::getInstance();

$userId = Session::getUserId();
CarnetsHelper::ensureConfig($pdo, $userId);
// ---------- FIN INITIALISATION ----------

$section = $_GET['section'] ?? 'actes';
$allowed = ['actes', 'examens', 'pharmacie', 'config', 'inventaire', 'etat_labo', 'carnets', 'fiches_ag'];
if (!in_array($section, $allowed)) $section = 'actes';

// ── Actions POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            // ── Actes médicaux ────────────────────────────────────────────
            case 'save_acte':
                $id = (int)($_POST['id'] ?? 0);
                $libelle = trim($_POST['libelle'] ?? '');
                $tarif = max(0, (int)($_POST['tarif'] ?? 300));
                $gratuit = (int)($_POST['est_gratuit'] ?? 0);
                if (!$libelle) jsonError('Libellé obligatoire.');
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE actes_medicaux SET libelle=:l, tarif=:t, est_gratuit=:g, whodone=:w WHERE id=:id AND isDeleted=0");
                    $stmt->execute([':l' => $libelle, ':t' => $tarif, ':g' => $gratuit, ':w' => $userId, ':id' => $id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO actes_medicaux (libelle, tarif, est_gratuit, whodone) VALUES (:l,:t,:g,:w)");
                    $stmt->execute([':l' => $libelle, ':t' => $tarif, ':g' => $gratuit, ':w' => $userId]);
                }
                jsonSuccess('Acte enregistré.');
                break;

            case 'delete_acte':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID acte manquant.');
                $stmt = $pdo->prepare("UPDATE actes_medicaux SET isDeleted=1, whodone=:w WHERE id=:id");
                $stmt->execute([':w' => $userId, ':id' => $id]);
                jsonSuccess('Acte archivé.');
                break;

            // ── Examens ───────────────────────────────────────────────────
            case 'save_examen':
                $id = (int)($_POST['id'] ?? 0);
                $libelle = trim($_POST['libelle'] ?? '');
                $cout = max(0, (int)($_POST['cout_total'] ?? 0));
                $pct = max(0, min(100, (float)($_POST['pourcentage_labo'] ?? 30)));
                if (!$libelle || !$cout) jsonError('Libellé et coût obligatoires.');
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE examens SET libelle=:l, cout_total=:c, pourcentage_labo=:p, whodone=:w WHERE id=:id AND isDeleted=0");
                    $stmt->execute([':l' => $libelle, ':c' => $cout, ':p' => $pct, ':w' => $userId, ':id' => $id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO examens (libelle, cout_total, pourcentage_labo, whodone) VALUES (:l,:c,:p,:w)");
                    $stmt->execute([':l' => $libelle, ':c' => $cout, ':p' => $pct, ':w' => $userId]);
                }
                jsonSuccess('Examen enregistré.');
                break;

            case 'delete_examen':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID examen manquant.');
                $stmt = $pdo->prepare("UPDATE examens SET isDeleted=1, whodone=:w WHERE id=:id");
                $stmt->execute([':w' => $userId, ':id' => $id]);
                jsonSuccess('Examen archivé.');
                break;

            // ── Produits Pharmacie ─────────────────────────────────────────
            case 'save_produit':
                $id = (int)($_POST['id'] ?? 0);
                $nom = trim($_POST['nom'] ?? '');
                $forme = $_POST['forme'] ?? 'comprimé';
                $prix = max(0, (int)($_POST['prix_unitaire'] ?? 0));
                $stockIn = max(0, (int)($_POST['stock_initial'] ?? 0));
                $seuil = max(0, (int)($_POST['seuil_alerte'] ?? 10));
                $perempDate = $_POST['date_peremption'] ?? null;
                $formes = ['comprimé', 'sirop', 'ampoule', 'gélule', 'suppositoire', 'pommade', 'solution', 'autre'];
                if (!$nom) jsonError('Nom obligatoire.');
                if (!in_array($forme, $formes)) $forme = 'autre';
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE produits_pharmacie SET nom=:n, forme=:f, prix_unitaire=:p, seuil_alerte=:s, date_peremption=:dp, whodone=:w WHERE id=:id AND isDeleted=0");
                    $stmt->execute([':n' => $nom, ':f' => $forme, ':p' => $prix, ':s' => $seuil, ':dp' => ($perempDate ?: null), ':w' => $userId, ':id' => $id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO produits_pharmacie (nom, forme, prix_unitaire, stock_initial, stock_actuel, seuil_alerte, date_peremption, whodone) VALUES (:n,:f,:p,:si,:sa,:s,:dp,:w)");
                    $stmt->execute([':n' => $nom, ':f' => $forme, ':p' => $prix, ':si' => $stockIn, ':sa' => $stockIn, ':s' => $seuil, ':dp' => ($perempDate ?: null), ':w' => $userId]);
                }
                jsonSuccess('Produit enregistré.');
                break;

            case 'delete_produit':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID produit manquant.');
                $stmt = $pdo->prepare("UPDATE produits_pharmacie SET isDeleted=1, whodone=:w WHERE id=:id");
                $stmt->execute([':w' => $userId, ':id' => $id]);
                jsonSuccess('Produit archivé.');
                break;

            case 'approvisionner':
                $pid = (int)($_POST['produit_id'] ?? 0);
                $qty = max(1, (int)($_POST['quantite'] ?? 0));
                $date = $_POST['date_appro'] ?? date('Y-m-d');
                $com = trim($_POST['commentaire'] ?? '');
                if (!$pid) jsonError('Produit invalide.');
                $pdo->beginTransaction();
                $stBefore = (int)$pdo->query("SELECT stock_actuel FROM produits_pharmacie WHERE id={$pid}")->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO approvisionnements_pharmacie (produit_id, quantite, date_appro, commentaire, whodone) VALUES (:p,:q,:d,:c,:w)");
                $stmt->execute([':p' => $pid, ':q' => $qty, ':d' => $date, ':c' => $com, ':w' => $userId]);
                $stmt = $pdo->prepare("UPDATE produits_pharmacie SET stock_actuel = stock_actuel + :qty WHERE id = :id");
                $stmt->execute([':qty' => $qty, ':id' => $pid]);
                try {
                    $stmt = $pdo->prepare("INSERT INTO mouvements_stock_pharmacie (produit_id, type_mvt, quantite, stock_avant, stock_apres, commentaire, whodone) VALUES (:p,'entree',:q,:sb,:sa,:c,:w)");
                    $stmt->execute([':p' => $pid, ':q' => $qty, ':sb' => $stBefore, ':sa' => $stBefore + $qty, ':c' => $com, ':w' => $userId]);
                } catch (Exception $ignored) {}
                $pdo->commit();
                jsonSuccess('Stock approvisionné (+' . $qty . ' unités).');
                break;

            case 'diminuer_stock':
                $pid = (int)($_POST['produit_id'] ?? 0);
                $qty = max(1, (int)($_POST['quantite'] ?? 0));
                $com = trim($_POST['commentaire'] ?? 'Correction manuelle stock');
                if (!$pid) jsonError('Produit invalide.');
                $stBefore = (int)$pdo->query("SELECT stock_actuel FROM produits_pharmacie WHERE id={$pid}")->fetchColumn();
                if ($qty > $stBefore) jsonError("Impossible : stock actuel = {$stBefore}, quantité à retirer = {$qty}.");
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE produits_pharmacie SET stock_actuel = stock_actuel - :qty, whodone=:w WHERE id=:id");
                $stmt->execute([':qty' => $qty, ':w' => $userId, ':id' => $pid]);
                try {
                    $stmt = $pdo->prepare("INSERT INTO mouvements_stock_pharmacie (produit_id, type_mvt, quantite, stock_avant, stock_apres, commentaire, whodone) VALUES (:p,'correction',:q,:sb,:sa,:c,:w)");
                    $stmt->execute([':p' => $pid, ':q' => -$qty, ':sb' => $stBefore, ':sa' => $stBefore - $qty, ':c' => $com, ':w' => $userId]);
                } catch (Exception $ignored) {}
                $pdo->commit();
                jsonSuccess('Stock diminué de ' . $qty . ' unités. Nouveau stock : ' . ($stBefore - $qty) . '.');
                break;

            case 'get_historique_stock':
                $pid = (int)($_POST['produit_id'] ?? 0);
                if (!$pid) jsonError('Produit invalide.');
                try {
                    $rows = $pdo->prepare("
                        SELECT m.type_mvt, m.quantite, m.stock_avant, m.stock_apres,
                               m.commentaire, m.whendone,
                               u.nom AS user_nom, u.prenom AS user_prenom
                        FROM mouvements_stock_pharmacie m
                        LEFT JOIN utilisateurs u ON u.id = m.whodone
                        WHERE m.produit_id = :pid AND m.isDeleted=0
                        ORDER BY m.whendone DESC
                        LIMIT 50
                    ");
                    $rows->execute([':pid' => $pid]);
                    echo json_encode(['success' => true, 'data' => $rows->fetchAll()]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Table mouvements_stock_pharmacie non trouvée. Exécutez la migration 002.']);
                }
                exit;

            // ── Carnets ───────────────────────────────────────────────────
            case 'save_stock_carnets':
                $typeCarnet = $_POST['type_carnet'] ?? 'soins';
                if (!in_array($typeCarnet, ['soins', 'sante'], true)) jsonError('Type de carnet invalide.');
                $qtyAdd = max(0, (int)($_POST['quantite'] ?? 0));
                $seuilAlrt = max(0, (int)($_POST['seuil_alerte'] ?? 10));
                $commentaire = trim($_POST['commentaire_carnet'] ?? '');
                if ($commentaire === '') {
                    $commentaire = 'Réapprovisionnement ' . ($typeCarnet === 'soins' ? 'carnets de soins' : 'carnets de santé');
                }
                $pdo->beginTransaction();
                CarnetsHelper::updateSeuil($pdo, $typeCarnet, $seuilAlrt, $userId);
                if ($qtyAdd > 0) CarnetsHelper::increment($pdo, $typeCarnet, $qtyAdd, $commentaire, $userId);
                $pdo->commit();
                $info = CarnetsHelper::getStock($pdo, $typeCarnet);
                jsonSuccess('Stock ' . strtolower($info['label']) . ' mis à jour. Stock actuel : ' . $info['stock'] . '.');
                break;

            case 'edit_mouvement_carnet':
                requireRole('admin');
                $mvtId = (int)($_POST['mvt_id'] ?? 0);
                $newQty = (int)($_POST['quantite'] ?? 0);
                $newComment = trim($_POST['commentaire'] ?? '');
                if (!$mvtId) jsonError('ID mouvement manquant.');
                if ($newQty <= 0) jsonError('La quantité doit être > 0.');

                $mvt = $pdo->prepare("SELECT * FROM mouvements_carnets WHERE id=:id LIMIT 1");
                $mvt->execute([':id' => $mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être modifiés.');

                $typeCarnet = $mvtRow['type_carnet'] ?? 'soins';
                $diffQty = $newQty - (int)$mvtRow['quantite'];

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    UPDATE mouvements_carnets
                    SET quantite = :q, stock_apres = stock_avant + :q2, commentaire = :c
                    WHERE id = :id
                ");
                $stmt->execute([':q' => $newQty, ':q2' => $newQty, ':c' => ($newComment ?: $mvtRow['commentaire']), ':id' => $mvtId]);

                if ($diffQty !== 0) {
                    $info = CarnetsHelper::getStock($pdo, $typeCarnet);
                    $newStock = max(0, $info['stock'] + $diffQty);
                    $cleStock = ($typeCarnet === 'soins') ? 'stock_carnets_soins' : 'stock_carnets_sante';
                    $stmt = $pdo->prepare("
                        INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v, :w)
                        ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2
                    ");
                    $stmt->execute([':k' => $cleStock, ':v' => $newStock, ':w' => $userId, ':v2' => $newStock, ':w2' => $userId]);
                }
                $pdo->commit();
                jsonSuccess('Mouvement modifié.');
                break;

            case 'delete_mouvement_carnet':
                requireRole('admin');
                $mvtId = (int)($_POST['mvt_id'] ?? 0);
                if (!$mvtId) jsonError('ID mouvement manquant.');

                $mvt = $pdo->prepare("SELECT * FROM mouvements_carnets WHERE id=:id LIMIT 1");
                $mvt->execute([':id' => $mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être supprimés.');

                $typeCarnet = $mvtRow['type_carnet'] ?? 'soins';
                $cleStock = ($typeCarnet === 'soins') ? 'stock_carnets_soins' : 'stock_carnets_sante';

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM mouvements_carnets WHERE id = :id");
                $stmt->execute([':id' => $mvtId]);
                $info = CarnetsHelper::getStock($pdo, $typeCarnet);
                $newStock = max(0, $info['stock'] - (int)$mvtRow['quantite']);
                $stmt = $pdo->prepare("
                    INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v, :w)
                    ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2
                ");
                $stmt->execute([':k' => $cleStock, ':v' => $newStock, ':w' => $userId, ':v2' => $newStock, ':w2' => $userId]);
                $pdo->commit();
                jsonSuccess('Mouvement supprimé. Stock ajusté à ' . $newStock . '.');
                break;

            case 'save_stock_fiches_ag':
                $qtyAjout = (int)($_POST['quantite'] ?? 0);
                $newSeuil = max(0, (int)($_POST['seuil_alerte'] ?? 10));
                $commentFag = trim($_POST['commentaire_fiche_ag'] ?? '');
                if ($qtyAjout < 0) jsonError('La quantité ne peut pas être négative.');
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('seuil_alerte_fiches_ag',:v,:w) ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2");
                $stmt->execute([':v' => $newSeuil, ':w' => $userId, ':v2' => $newSeuil, ':w2' => $userId]);
                if ($qtyAjout > 0) {
                    $curFag = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_fiches_ag'")->fetchColumn();
                    $newFag = $curFag + $qtyAjout;
                    $stmt = $pdo->prepare("INSERT INTO mouvements_fiches_ag (type_mvt,quantite,stock_avant,stock_apres,commentaire,whodone) VALUES ('initialisation',:qty,:avant,:apres,:cmt,:w)");
                    $stmt->execute([':qty' => $qtyAjout, ':avant' => $curFag, ':apres' => $newFag, ':cmt' => $commentFag, ':w' => $userId]);
                    $stmt = $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('stock_fiches_ag',:v,:w) ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2");
                    $stmt->execute([':v' => $newFag, ':w' => $userId, ':v2' => $newFag, ':w2' => $userId]);
                } else {
                    $newFag = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_fiches_ag'")->fetchColumn();
                }
                $pdo->commit();
                jsonSuccess('Stock fiches AG mis à jour. Stock actuel : ' . $newFag . ' fiches.');
                break;

            case 'edit_mouvement_fiche_ag':
                requireRole('admin');
                $mvtId = (int)($_POST['mvt_id'] ?? 0);
                $newQty = (int)($_POST['quantite'] ?? 0);
                $newComment = trim($_POST['commentaire'] ?? '');
                if (!$mvtId) jsonError('ID mouvement manquant.');
                if ($newQty <= 0) jsonError('La quantité doit être > 0.');
                $mvt = $pdo->prepare("SELECT * FROM mouvements_fiches_ag WHERE id=:id LIMIT 1");
                $mvt->execute([':id' => $mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être modifiés.');
                $diffQty = $newQty - (int)$mvtRow['quantite'];
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE mouvements_fiches_ag SET quantite=:q, stock_apres=stock_avant+:q2, commentaire=:c WHERE id=:id");
                $stmt->execute([':q' => $newQty, ':q2' => $newQty, ':c' => ($newComment ?: $mvtRow['commentaire']), ':id' => $mvtId]);
                if ($diffQty !== 0) {
                    $curFag = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_fiches_ag'")->fetchColumn();
                    $adjFag = max(0, $curFag + $diffQty);
                    $stmt = $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('stock_fiches_ag',:v,:w) ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2");
                    $stmt->execute([':v' => $adjFag, ':w' => $userId, ':v2' => $adjFag, ':w2' => $userId]);
                }
                $pdo->commit();
                jsonSuccess('Mouvement modifié.');
                break;

            case 'delete_mouvement_fiche_ag':
                requireRole('admin');
                $mvtId = (int)($_POST['mvt_id'] ?? 0);
                if (!$mvtId) jsonError('ID mouvement manquant.');
                $mvt = $pdo->prepare("SELECT * FROM mouvements_fiches_ag WHERE id=:id LIMIT 1");
                $mvt->execute([':id' => $mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être supprimés.');
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM mouvements_fiches_ag WHERE id=:id");
                $stmt->execute([':id' => $mvtId]);
                $curFag = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_fiches_ag'")->fetchColumn();
                $adjFag = max(0, $curFag - (int)$mvtRow['quantite']);
                $stmt = $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('stock_fiches_ag',:v,:w) ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2");
                $stmt->execute([':v' => $adjFag, ':w' => $userId, ':v2' => $adjFag, ':w2' => $userId]);
                $pdo->commit();
                jsonSuccess('Mouvement supprimé. Stock ajusté à ' . $adjFag . ' fiches.');
                break;

            case 'save_config':
                $keys = ['nom_centre', 'adresse', 'telephone', 'pied_de_page'];
                foreach ($keys as $k) {
                    $v = trim($_POST[$k] ?? '');
                    $stmt = $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v1, :w1) ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2");
                    $stmt->execute([':k' => $k, ':v1' => $v, ':w1' => $userId, ':v2' => $v, ':w2' => $userId]);
                }
                if (!empty($_FILES['logo']['name'])) {
                    $logoFile = uploadLogo($_FILES['logo']);
                    if ($logoFile) {
                        $stmt = $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES ('logo_filename', :v1, :w1) ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2");
                        $stmt->execute([':v1' => $logoFile, ':w1' => $userId, ':v2' => $logoFile, ':w2' => $userId]);
                    }
                }
                jsonSuccess('Configuration sauvegardée.');
                break;

            default:
                jsonError('Action inconnue : ' . htmlspecialchars($action));
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonError(APP_ENV === 'development' ? $e->getMessage() : 'Erreur BDD.');
    }
}

// ── Chargement données ─────────────────────────────────────────────────────────
$actes = $pdo->query("SELECT * FROM actes_medicaux WHERE isDeleted=0 ORDER BY libelle")->fetchAll();
$examens = $pdo->query("SELECT * FROM examens WHERE isDeleted=0 ORDER BY libelle")->fetchAll();
$produits = $pdo->query("SELECT * FROM produits_pharmacie WHERE isDeleted=0 ORDER BY nom")->fetchAll();
$cfg = [];
foreach ($pdo->query("SELECT cle, valeur FROM config_systeme WHERE isDeleted=0")->fetchAll() as $r) {
    $cfg[$r['cle']] = $r['valeur'];
}
$infoSoins = CarnetsHelper::getStock($pdo, CarnetsHelper::TYPE_SOINS);
$infoSante = CarnetsHelper::getStock($pdo, CarnetsHelper::TYPE_SANTE);

$stockCarnetsSoins = $infoSoins['stock'];
$seuilAlerteCarnetsSoins = $infoSoins['seuil'];
$stockCarnetsSante = $infoSante['stock'];
$seuilAlerteCarnetsSante = $infoSante['seuil'];

$stockCarnets = $stockCarnetsSoins + $stockCarnetsSante;
$seuilAlerteCarnets = $seuilAlerteCarnetsSoins;

$historiqueCarnets = $pdo->query("
    SELECT mc.id, mc.type_mvt, mc.type_carnet, mc.quantite, mc.stock_avant, mc.stock_apres,
           mc.commentaire, mc.whendone,
           u.nom AS user_nom, u.prenom AS user_prenom
    FROM mouvements_carnets mc
    LEFT JOIN utilisateurs u ON u.id = mc.whodone
    WHERE mc.type_mvt = 'initialisation'
    ORDER BY mc.whendone DESC
")->fetchAll();

$stockFichesAg = (int)($cfg['stock_fiches_ag'] ?? 0);
$seuilAlerteFichesAg = (int)($cfg['seuil_alerte_fiches_ag'] ?? 10);

$historiqueFichesAg = $pdo->query("
    SELECT mf.id, mf.type_mvt, mf.quantite, mf.stock_avant, mf.stock_apres,
           mf.commentaire, mf.whendone,
           u.nom AS user_nom, u.prenom AS user_prenom
    FROM mouvements_fiches_ag mf
    LEFT JOIN utilisateurs u ON u.id = mf.whodone
    WHERE mf.type_mvt = 'initialisation'
    ORDER BY mf.whendone DESC
")->fetchAll();

$pageTitle = 'Paramétrage';
include ROOT_PATH . '/templates/layouts/header.php';
?>

<div class="mt-4">
    <h4 class="fw-bold text-csi mb-4"><i class="bi bi-gear me-2"></i>Module Paramétrage</h4>

    <!-- Tabs navigation -->
    <ul class="nav nav-tabs nav-fill mb-4 fw-semibold" id="paramTabs">
        <li class="nav-item">
            <a class="nav-link <?= $section === 'actes' ? 'active' : '' ?>" href="?page=parametrage&section=actes">
                <i class="bi bi-clipboard-pulse me-1"></i>Actes médicaux
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'examens' ? 'active' : '' ?>" href="?page=parametrage&section=examens">
                <i class="bi bi-microscope me-1"></i>Examens & Labo
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'pharmacie' ? 'active' : '' ?>" href="?page=parametrage&section=pharmacie">
                <i class="bi bi-capsule me-1"></i>Pharmacie
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'inventaire' ? 'active' : '' ?>" href="?page=parametrage&section=inventaire">
                <i class="bi bi-clipboard-check me-1"></i>Inventaire
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'etat_labo' ? 'active' : '' ?>" href="?page=parametrage&section=etat_labo">
                <i class="bi bi-file-earmark-pdf me-1"></i>État Labo
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'carnets' ? 'active' : '' ?>" href="?page=parametrage&section=carnets" title="Gestion stock des carnets de soins">
                <i class="bi bi-journal-medical me-1"></i>Carnets
                <?php if ($stockCarnets <= $seuilAlerteCarnets && $stockCarnets > 0): ?>
                    <span class="badge bg-warning text-dark ms-1">⚠</span>
                <?php elseif ($stockCarnets === 0): ?>
                    <span class="badge bg-danger ms-1">0</span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'fiches_ag' ? 'active' : '' ?>" href="?page=parametrage&section=fiches_ag" title="Gestion stock des fiches actes gratuits">
                <i class="bi bi-file-medical me-1"></i>Fiches AG
                <?php if ($stockFichesAg <= $seuilAlerteFichesAg && $stockFichesAg > 0): ?>
                    <span class="badge bg-warning text-dark ms-1">⚠</span>
                <?php elseif ($stockFichesAg === 0): ?>
                    <span class="badge bg-danger ms-1">0</span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'config' ? 'active' : '' ?>" href="?page=parametrage&section=config">
                <i class="bi bi-building me-1"></i>Config Centre
            </a>
        </li>
    </ul>

    <!-- ══════════════ SECTION ACTES ══════════════ -->
    <?php if ($section === 'actes'): ?>
    <div class="card">
        <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-clipboard-pulse me-2"></i>Actes Médicaux & Carnets</h6>
            <button class="btn text-white btn-sm" style="background:var(--csi-green);" onclick="openActeModal()">
                <i class="bi bi-plus-circle me-1"></i>Nouvel acte
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead class="table-light">
                    <tr><th>Libellé</th><th>Tarif</th><th>Type</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($actes as $a): ?>
                    <tr>
                        <td><?= h($a['libelle']) ?></td>
                        <td>
                            <?php if ($a['est_gratuit']): ?>
                                <span class="text-decoration-line-through text-muted"><?= $a['tarif'] ?> F</span>
                                <span class="badge bg-info ms-1">GRATUIT</span>
                            <?php else: ?>
                                <span class="fw-bold"><?= $a['tarif'] ?> F</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $a['est_gratuit'] ? 'bg-info' : 'bg-success' ?>">
                                <?= $a['est_gratuit'] ? 'Acte gratuit' : 'Payant' ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="openActeModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('acte', <?= $a['id'] ?>, '<?= h($a['libelle']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Acte -->
    <div class="modal fade" id="modalActe" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Acte médical</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formActe">
                        <input type="hidden" name="action" value="save_acte">
                        <input type="hidden" name="id" id="acteId" value="">
                        <div class="mb-3">
                            <label class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="libelle" id="acteLibelle" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tarif (F)</label>
                            <input type="number" class="form-control" name="tarif" id="acteTarif" value="300" min="0">
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="est_gratuit" id="acteGratuit" value="1">
                            <label class="form-check-label" for="acteGratuit">Acte gratuit (CPN, Accouchement...)</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:var(--csi-green);" onclick="saveParam('formActe', '/index.php?page=parametrage&section=actes')">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'examens'): ?>
    <!-- ══════════════ SECTION EXAMENS ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-microscope me-2"></i>Examens & Pourcentage Laborantin</h6>
            <button class="btn text-white btn-sm" style="background:var(--csi-green);" onclick="openExamenModal()">
                <i class="bi bi-plus-circle me-1"></i>Nouvel examen
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead class="table-light">
                    <tr><th>Libellé</th><th>Coût total</th><th>% Labo</th><th>Montant Labo</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($examens as $e): 
                    $montLabo = round($e['cout_total'] * $e['pourcentage_labo'] / 100);
                ?>
                    <tr>
                        <td><?= h($e['libelle']) ?></td>
                        <td class="fw-bold"><?= number_format($e['cout_total'],0,',',' ') ?> F</td>
                        <td><span class="badge bg-warning text-dark"><?= $e['pourcentage_labo'] ?>%</span></td>
                        <td class="text-success fw-bold"><?= number_format($montLabo,0,',',' ') ?> F</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="openExamenModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('examen', <?= $e['id'] ?>, '<?= h($e['libelle']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Examen -->
    <div class="modal fade" id="modalExamen" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Examen médical</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formExamen">
                        <input type="hidden" name="action" value="save_examen">
                        <input type="hidden" name="id" id="examId" value="">
                        <div class="mb-3">
                            <label class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="libelle" id="examLibelle" required>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Coût total (F) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="cout_total" id="examCout" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">% Laborantin</label>
                                <input type="number" class="form-control" name="pourcentage_labo" id="examPct"
                                       value="30" min="0" max="100" step="0.5">
                            </div>
                        </div>
                        <div class="mt-3 p-2 bg-light rounded">
                            <small>Montant labo estimé : <strong id="montLaboCalc">0 F</strong></small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:var(--csi-green);" onclick="saveParam('formExamen', '/index.php?page=parametrage&section=examens')">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'pharmacie'): ?>
    <!-- ══════════════ SECTION PHARMACIE ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-capsule me-2"></i>Gestion Stock Pharmaceutique</h6>
            <button class="btn text-white btn-sm" style="background:var(--csi-green);" onclick="openProduitModal()">
                <i class="bi bi-plus-circle me-1"></i>Nouveau produit
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead class="table-light">
                    <tr><th>Produit</th><th>Forme</th><th>Prix</th><th>Stock</th><th>Seuil</th><th>Péremption</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($produits as $p):
                    $enAlerte = $p['stock_actuel'] <= $p['seuil_alerte'] && $p['stock_actuel'] > 0;
                    $enRupture = $p['stock_actuel'] <= 0;
                    $perime = $p['date_peremption'] && $p['date_peremption'] <= date('Y-m-d');
                ?>
                    <tr class="<?= $enRupture || $perime ? 'table-danger' : ($enAlerte ? 'table-warning' : '') ?>">
                        <td><strong><?= h($p['nom']) ?></strong></td>
                        <td><span class="badge bg-secondary"><?= h($p['forme']) ?></span></td>
                        <td><?= number_format($p['prix_unitaire'],0,',',' ') ?> F</td>
                        <td class="<?= $enRupture ? 'text-danger fw-bold' : ($enAlerte ? 'text-warning fw-bold' : 'fw-bold') ?>">
                            <?= $p['stock_actuel'] ?>
                        </td>
                        <td><small class="text-muted"><?= $p['seuil_alerte'] ?></small></td>
                        <td>
                            <?php if ($p['date_peremption']): ?>
                                <small class="<?= $perime ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('d/m/Y', strtotime($p['date_peremption'])) ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($enRupture): ?>
                                <span class="badge bg-danger">Rupture</span>
                            <?php elseif ($perime): ?>
                                <span class="badge bg-danger">Périmé</span>
                            <?php elseif ($enAlerte): ?>
                                <span class="badge bg-warning text-dark">⚠ Alerte</span>
                            <?php else: ?>
                                <span class="badge bg-success">OK</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-success me-1" title="Approvisionner"
                                    onclick="openApproModal(<?= $p['id'] ?>, '<?= h($p['nom']) ?>')">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning me-1" title="Diminuer stock (correction)"
                                    onclick="openDiminuerModal(<?= $p['id'] ?>, '<?= h($p['nom']) ?>', <?= (int)$p['stock_actuel'] ?>')">
                                <i class="bi bi-dash-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info me-1" title="Historique mouvements"
                                    onclick="voirHistoriqueStock(<?= $p['id'] ?>, '<?= h($p['nom']) ?>')">
                                <i class="bi bi-clock-history"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="openProduitModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('produit', <?= $p['id'] ?>, '<?= h($p['nom']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Produit -->
    <div class="modal fade" id="modalProduit" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Produit pharmaceutique</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formProduit">
                        <input type="hidden" name="action" value="save_produit">
                        <input type="hidden" name="id" id="prodId" value="">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nom" id="prodNom" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Forme</label>
                                <select class="form-select" name="forme" id="prodForme">
                                    <?php foreach (['comprimé','sirop','ampoule','gélule','suppositoire','pommade','solution','autre'] as $f): ?>
                                        <option><?= h($f) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Prix unitaire (F)</label>
                                <input type="number" class="form-control" name="prix_unitaire" id="prodPrix" min="0" value="0">
                            </div>
                            <div class="col-md-4" id="stockInitBlock">
                                <label class="form-label">Stock initial</label>
                                <input type="number" class="form-control" name="stock_initial" id="prodStock" min="0" value="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Seuil d'alerte</label>
                                <input type="number" class="form-control" name="seuil_alerte" id="prodSeuil" min="0" value="10">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date de péremption</label>
                                <input type="date" class="form-control" name="date_peremption" id="prodPeremption">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:var(--csi-green);" onclick="saveParam('formProduit', '/index.php?page=parametrage&section=pharmacie')">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Appro -->
    <div class="modal fade" id="modalAppro" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:#1b5e20;">
                    <h5 class="modal-title text-white"><i class="bi bi-truck me-2"></i>Approvisionnement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formAppro">
                        <input type="hidden" name="action" value="approvisionner">
                        <input type="hidden" name="produit_id" id="approProduitId">
                        <div class="alert alert-info">
                            <i class="bi bi-capsule me-2"></i>Produit : <strong id="approProduitNom"></strong>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Quantité <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="quantite" id="approQty" min="1" value="1" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date_appro" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Commentaire</label>
                                <textarea class="form-control" name="commentaire" rows="2" placeholder="Source, fournisseur..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:#1b5e20;" onclick="saveParam('formAppro', '/index.php?page=parametrage&section=pharmacie')">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Diminuer Stock -->
    <div class="modal fade" id="modalDiminuer" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:#b71c1c;">
                    <h5 class="modal-title text-white"><i class="bi bi-dash-circle me-2"></i>Diminuer le Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Attention :</strong> cette opération diminue le stock actuel (correction d'inventaire).
                    </div>
                    <form id="formDiminuer">
                        <input type="hidden" name="action" value="diminuer_stock">
                        <input type="hidden" name="produit_id" id="dimProduitId">
                        <div class="mb-3">
                            <label class="form-label">Produit</label>
                            <div class="fw-bold text-danger" id="dimProduitNom"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock actuel</label>
                            <div class="fw-bold fs-5" id="dimStockActuel"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantité à retirer <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantite" id="dimQty" min="1" value="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motif / Commentaire <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="commentaire" id="dimCommentaire" rows="2"
                                      placeholder="Ex: Produits périmés retirés, décalage inventaire..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:#b71c1c;" onclick="saveParam('formDiminuer', '/index.php?page=parametrage&section=pharmacie')">
                        <i class="bi bi-dash-circle me-1"></i>Diminuer le stock
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Historique Mouvements Stock -->
    <div class="modal fade" id="modalHistoriqueStock" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background:#1565c0;">
                    <h5 class="modal-title text-white">
                        <i class="bi bi-clock-history me-2"></i>
                        Historique – <span id="histProduitNom"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="histLoading" class="text-center p-4">
                        <div class="spinner-border text-primary"></div>
                    </div>
                    <div id="histContent" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'carnets'): ?>
    <!-- ══════════════ SECTION CARNETS (2 TYPES) ══════════════ -->

    <!-- Onglets internes -->
    <ul class="nav nav-pills mb-4" id="carnetsTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-soins" type="button">
                <i class="bi bi-journal-medical me-1"></i> Carnets de Soins
                <span class="badge bg-light text-dark ms-1"><?= $stockCarnetsSoins ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-sante" type="button">
                <i class="bi bi-journal-plus me-1"></i> Carnets de Santé
                <span class="badge bg-light text-dark ms-1"><?= $stockCarnetsSante ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-historique" type="button">
                <i class="bi bi-clock-history me-1"></i> Historique global
            </button>
        </li>
    </ul>

    <div class="tab-content">

    <!-- ─── TAB CARNET DE SOINS ─── -->
    <div class="tab-pane fade show active" id="tab-soins">
        <div class="alert alert-light border-start border-4 border-primary py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Carnet de soins</strong> — utilisé pour les <strong>reçus normaux</strong> (consultation 300 F + carnet 100 F).
            Décompté à chaque consultation standard avec option carnet.
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100" style="border-color:#1565c0;">
                    <div class="card-header" style="background:#e3f2fd;">
                        <h6 class="mb-0" style="color:#1565c0;">
                            <i class="bi bi-journal-medical me-2"></i>Stock Carnets de Soins
                        </h6>
                    </div>
                    <div class="card-body text-center">
                        <?php
                        $cls = $stockCarnetsSoins === 0 ? 'danger' :
                              ($stockCarnetsSoins <= $seuilAlerteCarnetsSoins ? 'warning' : 'success');
                        $txt = $stockCarnetsSoins === 0 ? 'RUPTURE !' :
                              ($stockCarnetsSoins <= $seuilAlerteCarnetsSoins ? 'Stock faible' : 'Stock suffisant');
                        ?>
                        <div class="display-4 fw-bold text-<?= $cls ?>"><?= $stockCarnetsSoins ?></div>
                        <p class="text-muted mb-1">carnets de soins</p>
                        <span class="badge bg-<?= $cls ?>"><?= $txt ?></span>
                        <hr>
                        <div class="text-muted small">Seuil : <strong><?= $seuilAlerteCarnetsSoins ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-csi-light">
                        <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Réapprovisionner les Carnets de Soins</h6>
                    </div>
                    <div class="card-body">
                        <form id="formStockCarnetsSoins">
                            <input type="hidden" name="action" value="save_stock_carnets">
                            <input type="hidden" name="type_carnet" value="soins">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Quantité à ajouter</label>
                                    <input type="number" class="form-control form-control-lg" name="quantite"
                                           min="0" value="0" id="qtyCarnetSoins">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Seuil d'alerte</label>
                                    <input type="number" class="form-control form-control-lg" name="seuil_alerte"
                                           min="0" value="<?= $seuilAlerteCarnetsSoins ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Commentaire</label>
                                    <input type="text" class="form-control" name="commentaire_carnet"
                                           placeholder="Ex: Livraison du <?= date('d/m/Y') ?>">
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info py-2 mb-2 small">
                                        Stock actuel : <strong><?= $stockCarnetsSoins ?></strong>.
                                        Nouveau : <strong id="prevStockSoins"><?= $stockCarnetsSoins ?></strong>
                                    </div>
                                    <button type="button" class="btn text-white w-100" style="background:var(--csi-green);"
                                            onclick="saveParam('formStockCarnetsSoins','/index.php?page=parametrage&section=carnets')">
                                        <i class="bi bi-save me-1"></i>Enregistrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique soins -->
        <?php
        $histSoins = array_filter($historiqueCarnets, fn($m) => ($m['type_carnet'] ?? 'soins') === 'soins');
        ?>
        <div class="card">
            <div class="card-header bg-csi-light d-flex justify-content-between">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique – Carnets de Soins</h6>
                <small class="text-muted"><?= count($histSoins) ?> entrée(s)</small>
            </div>
            <div class="card-body p-0">
                <?php if (empty($histSoins)): ?>
                    <div class="p-4 text-center text-muted">Aucun approvisionnement.</div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Date</th><th class="text-center">Qté</th>
                            <th class="text-center">Avant</th><th class="text-center">Après</th>
                            <th>Commentaire</th><th>Par</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($histSoins as $mv): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$mv['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($mv['whendone'])) ?></td>
                        <td class="text-center"><span class="badge bg-success">+<?= (int)$mv['quantite'] ?></span></td>
                        <td class="text-center text-muted"><?= (int)$mv['stock_avant'] ?></td>
                        <td class="text-center fw-bold"><?= (int)$mv['stock_apres'] ?></td>
                        <td><?= h($mv['commentaire'] ?? '—') ?></td>
                        <td><small><?= h(trim(($mv['user_nom'] ?? '').' '.($mv['user_prenom'] ?? ''))) ?: '—' ?></small></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="ouvrirEditMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>, '<?= addslashes(h($mv['commentaire'] ?? '')) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="supprimerMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ─── TAB CARNET DE SANTÉ ─── -->
    <div class="tab-pane fade" id="tab-sante">
        <div class="alert alert-light border-start border-4 border-success py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Carnet de santé</strong> — utilisé pour les <strong>actes gratuits</strong> (CPN, accouchement, nourrissons…)
            avec option carnet (100 F) ou carnet + fiche (400 F).
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100" style="border-color:#2e7d32;">
                    <div class="card-header" style="background:#e8f5e9;">
                        <h6 class="mb-0" style="color:#2e7d32;">
                            <i class="bi bi-journal-plus me-2"></i>Stock Carnets de Santé
                        </h6>
                    </div>
                    <div class="card-body text-center">
                        <?php
                        $clsS = $stockCarnetsSante === 0 ? 'danger' :
                               ($stockCarnetsSante <= $seuilAlerteCarnetsSante ? 'warning' : 'success');
                        $txtS = $stockCarnetsSante === 0 ? 'RUPTURE !' :
                               ($stockCarnetsSante <= $seuilAlerteCarnetsSante ? 'Stock faible' : 'Stock suffisant');
                        ?>
                        <div class="display-4 fw-bold text-<?= $clsS ?>"><?= $stockCarnetsSante ?></div>
                        <p class="text-muted mb-1">carnets de santé</p>
                        <span class="badge bg-<?= $clsS ?>"><?= $txtS ?></span>
                        <hr>
                        <div class="text-muted small">Seuil : <strong><?= $seuilAlerteCarnetsSante ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-csi-light">
                        <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Réapprovisionner les Carnets de Santé</h6>
                    </div>
                    <div class="card-body">
                        <form id="formStockCarnetsSante">
                            <input type="hidden" name="action" value="save_stock_carnets">
                            <input type="hidden" name="type_carnet" value="sante">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Quantité à ajouter</label>
                                    <input type="number" class="form-control form-control-lg" name="quantite"
                                           min="0" value="0" id="qtyCarnetSante">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Seuil d'alerte</label>
                                    <input type="number" class="form-control form-control-lg" name="seuil_alerte"
                                           min="0" value="<?= $seuilAlerteCarnetsSante ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Commentaire</label>
                                    <input type="text" class="form-control" name="commentaire_carnet"
                                           placeholder="Ex: Livraison du <?= date('d/m/Y') ?>">
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info py-2 mb-2 small">
                                        Stock actuel : <strong><?= $stockCarnetsSante ?></strong>.
                                        Nouveau : <strong id="prevStockSante"><?= $stockCarnetsSante ?></strong>
                                    </div>
                                    <button type="button" class="btn text-white w-100" style="background:var(--csi-green);"
                                            onclick="saveParam('formStockCarnetsSante','/index.php?page=parametrage&section=carnets')">
                                        <i class="bi bi-save me-1"></i>Enregistrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique santé -->
        <?php
        $histSante = array_filter($historiqueCarnets, fn($m) => ($m['type_carnet'] ?? '') === 'sante');
        ?>
        <div class="card">
            <div class="card-header bg-csi-light d-flex justify-content-between">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique – Carnets de Santé</h6>
                <small class="text-muted"><?= count($histSante) ?> entrée(s)</small>
            </div>
            <div class="card-body p-0">
                <?php if (empty($histSante)): ?>
                    <div class="p-4 text-center text-muted">Aucun approvisionnement.</div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Date</th><th class="text-center">Qté</th>
                            <th class="text-center">Avant</th><th class="text-center">Après</th>
                            <th>Commentaire</th><th>Par</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($histSante as $mv): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$mv['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($mv['whendone'])) ?></td>
                        <td class="text-center"><span class="badge bg-success">+<?= (int)$mv['quantite'] ?></span></td>
                        <td class="text-center text-muted"><?= (int)$mv['stock_avant'] ?></td>
                        <td class="text-center fw-bold"><?= (int)$mv['stock_apres'] ?></td>
                        <td><?= h($mv['commentaire'] ?? '—') ?></td>
                        <td><small><?= h(trim(($mv['user_nom'] ?? '').' '.($mv['user_prenom'] ?? ''))) ?: '—' ?></small></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="ouvrirEditMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>, '<?= addslashes(h($mv['commentaire'] ?? '')) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="supprimerMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ─── TAB HISTORIQUE GLOBAL (tous mouvements, y compris sorties) ─── -->
    <div class="tab-pane fade" id="tab-historique">
        <?php
        $allMvts = $pdo->query("
            SELECT mc.*, u.nom AS user_nom, u.prenom AS user_prenom,
                   r.numero_recu, r.type_patient
            FROM mouvements_carnets mc
            LEFT JOIN utilisateurs u ON u.id = mc.whodone
            LEFT JOIN recus r ON r.id = mc.recu_id
            ORDER BY mc.whendone DESC
            LIMIT 200
        ")->fetchAll();
        ?>
        <div class="card">
            <div class="card-header bg-csi-light">
                <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Tous les mouvements (200 derniers)</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($allMvts)): ?>
                    <div class="p-4 text-center text-muted">Aucun mouvement.</div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th><th>Type carnet</th><th>Mouvement</th>
                            <th class="text-center">Qté</th>
                            <th class="text-center">Avant</th><th class="text-center">Après</th>
                            <th>Reçu</th><th>Commentaire</th><th>Par</th>
                        </tr>
                    </thead>
                   <tbody>
<?php foreach ($allMvts as $mv): ?>
    <?php
    $tc  = $mv['type_carnet'] ?? 'soins';
    $tm  = $mv['type_mvt']   ?? 'sortie';
    $tcLabel = ($tc === 'sante')
        ? '<span class="badge bg-success">Santé</span>'
        : '<span class="badge bg-primary">Soins</span>';
    $tmLabel = ($tm === 'sortie')
        ? '<span class="badge bg-danger">Sortie</span>'
        : '<span class="badge bg-info text-dark">Entrée</span>';
    ?>
    <tr>
        <td><?= date('d/m/Y H:i', strtotime($mv['whendone'] ?? 'now')) ?></td>
        <td class="text-center"><?= $tcLabel ?></td>
        <td class="text-center"><?= $tmLabel ?></td>
        <td class="text-center fw-bold"><?= abs((int)$mv['quantite']) ?></td>
        <td class="text-center text-muted"><?= (int)$mv['stock_avant'] ?></td>
        <td class="text-center fw-bold"><?= (int)$mv['stock_apres'] ?></td>
        <td>
            <?php if ($tm === 'sortie' && !empty($mv['recu_id'])): ?>
                <small class="text-muted">#<?= h($mv['numero_recu'] ?? '') ?></small>
            <?php else: ?>
                <span class="text-muted">—</span>
            <?php endif; ?>
        </td>
        <td><?= h($mv['commentaire'] ?? '—') ?></td>
        <td><small><?= h(trim(($mv['user_nom'] ?? '') . ' ' . ($mv['user_prenom'] ?? ''))) ?: '—' ?></small></td>
    </tr>
<?php endforeach; ?>
</tbody>

                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div> <!-- fin tab-content -->

<?php elseif ($section === 'fiches_ag'): ?>
    <!-- ══════════════ SECTION FICHES AG ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-file-medical me-2"></i>Fiches Actes Gratuits (AG)</h6>
            <button class="btn text-white btn-sm" style="background:var(--csi-green);" onclick="openFicheAgModal()">
                <i class="bi bi-plus-circle me-1"></i>Ajouter des fiches
            </button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card h-100" style="border-color:#1565c0;">
                        <div class="card-header" style="background:#e3f2fd;">
                            <h6 class="mb-0" style="color:#1565c0;">
                                <i class="bi bi-stack me-2"></i>Stock Fiches AG
                            </h6>
                        </div>
                        <div class="card-body text-center">
                            <?php
                            $clsF = $stockFichesAg === 0 ? 'danger' :
                                   ($stockFichesAg <= $seuilAlerteFichesAg ? 'warning' : 'success');
                            $txtF = $stockFichesAg === 0 ? 'RUPTURE !' :
                                   ($stockFichesAg <= $seuilAlerteFichesAg ? 'Stock faible' : 'Stock suffisant');
                            ?>
                            <div class="display-4 fw-bold text-<?= $clsF ?>"><?= $stockFichesAg ?></div>
                            <p class="text-muted mb-1">fiches AG</p>
                            <span class="badge bg-<?= $clsF ?>"><?= $txtF ?></span>
                            <hr>
                            <div class="text-muted small">Seuil : <strong><?= $seuilAlerteFichesAg ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-csi-light">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Réapprovisionner les Fiches AG</h6>
                        </div>
                        <div class="card-body">
                            <form id="formStockFichesAg">
                                <input type="hidden" name="action" value="save_stock_fiches_ag">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Quantité à ajouter</label>
                                        <input type="number" class="form-control form-control-lg" name="quantite"
                                               min="0" value="0" id="qtyFicheAg">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Seuil d'alerte</label>
                                        <input type="number" class="form-control form-control-lg" name="seuil_alerte"
                                               min="0" value="<?= $seuilAlerteFichesAg ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold">Commentaire</label>
                                        <input type="text" class="form-control" name="commentaire_fiche_ag"
                                               placeholder="Ex: Livraison du <?= date('d/m/Y') ?>">
                                    </div>
                                    <div class="col-12">
                                        <div class="alert alert-info py-2 mb-2 small">
                                            Stock actuel : <strong><?= $stockFichesAg ?></strong>.
                                            Nouveau : <strong id="prevStockFichesAg"><?= $stockFichesAg ?></strong>
                                        </div>
                                        <button type="button" class="btn text-white w-100" style="background:var(--csi-green);"
                                                onclick="saveParam('formStockFichesAg','/index.php?page=parametrage&section=fiches_ag')">
                                            <i class="bi bi-save me-1"></i>Enregistrer
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique fiches AG -->
        <div class="card mt-3">
            <div class="card-header bg-csi-light d-flex justify-content-between">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique – Fiches AG</h6>
                <small class="text-muted"><?= count($historiqueFichesAg) ?> entrée(s)</small>
            </div>
            <div class="card-body p-0">
                <?php if (empty($historiqueFichesAg)): ?>
                    <div class="p-4 text-center text-muted">Aucun approvisionnement.</div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Date</th><th class="text-center">Qté</th>
                            <th class="text-center">Avant</th><th class="text-center">Après</th>
                            <th>Commentaire</th><th>Par</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($historiqueFichesAg as $mv): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$mv['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($mv['whendone'])) ?></td>
                        <td class="text-center"><span class="badge bg-success">+<?= (int)$mv['quantite'] ?></span></td>
                        <td class="text-center text-muted"><?= (int)$mv['stock_avant'] ?></td>
                        <td class="text-center fw-bold"><?= (int)$mv['stock_apres'] ?></td>
                        <td><?= h($mv['commentaire'] ?? '—') ?></td>
                        <td><small><?= h(trim(($mv['user_nom'] ?? '').' '.($mv['user_prenom'] ?? ''))) ?: '—' ?></small></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="ouvrirEditMvtFicheAg(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>, '<?= addslashes(h($mv['commentaire'] ?? '')) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="supprimerMvtFicheAg(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($section === 'config'): ?>
    <!-- ══════════════ SECTION CONFIG ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light">
            <h6 class="mb-0"><i class="bi bi-building me-2"></i>Configuration du Centre</h6>
        </div>
        <div class="card-body">
            <form id="formConfig" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_config">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nom du centre</label>
                        <input type="text" class="form-control" name="nom_centre" value="<?= h($cfg['nom_centre'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Téléphone</label>
                        <input type="text" class="form-control" name="telephone" value="<?= h($cfg['telephone'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Adresse</label>
                        <input type="text" class="form-control" name="adresse" value="<?= h($cfg['adresse'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pied de page</label>
                        <input type="text" class="form-control" name="pied_de_page" value="<?= h($cfg['pied_de_page'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Logo (JPG/PNG)</label>
                        <input type="file" class="form-control" name="logo" accept=".jpg,.jpeg,.png">
                        <?php if (!empty($cfg['logo_filename'])): ?>
                            <div class="mt-2">
                                <small class="text-muted">Fichier actuel : <?= h($cfg['logo_filename']) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn text-white" style="background:var(--csi-green);"
                                onclick="saveParam('formConfig','/index.php?page=parametrage&section=config')">
                            <i class="bi bi-save me-1"></i>Enregistrer la configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

</div> <!-- Fin container principal -->

<!-- JavaScript commun -->
<script>
// ─── Helper CSRF — lit directement le meta-tag, sans dépendre de app.js ───────
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// ─── Helper encode HTML (sécurité XSS) ────────────────────────────────────────
function h(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ─── Formater date MySQL → format français ─────────────────────────────────────
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

// ─── Sauvegarde générique d'un formulaire via AJAX (GLOBAL) ───────────────────
function saveParam(formId, url) {
    const form = document.getElementById(formId);
    if (!form) {
        console.error('Formulaire non trouvé :', formId);
        alert('Erreur : Le formulaire n\'a pas été trouvé.');
        return;
    }
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);
    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Erreur réseau : ' + response.status);
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur : ' + data.message);
            }
        } catch (e) {
            console.error('Réponse non-JSON :', text);
            alert('Erreur : La réponse du serveur n\'est pas valide.\n\n' + text.substring(0, 200));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Erreur réseau. Vérifiez votre connexion.');
    });
}

// ─── deleteItem générique (soft-delete via AJAX) — GLOBAL ─────────────────────
function deleteItem(type, id, libelle) {
    if (!confirm('Supprimer « ' + libelle + ' » ?')) return;
    const sectionMap = { acte: 'actes', examen: 'examens', produit: 'pharmacie' };
    const section = sectionMap[type] || type;
    const fd = new FormData();
    fd.append('action', 'delete_' + type);
    fd.append('id', id);
    fetch('/index.php?page=parametrage&section=' + section, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
        body: fd
    })
    .then(r => r.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) location.reload();
            else alert('Erreur : ' + data.message);
        } catch(e) {
            console.error('Réponse non-JSON :', text);
            alert('Erreur serveur inattendue.');
        }
    })
    .catch(() => alert('Erreur réseau.'));
}

// ─── Modal Acte (GLOBAL) ───────────────────────────────────────────────────────
function openActeModal(acte) {
    acte = acte || null;
    const modalEl = document.getElementById('modalActe');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = document.getElementById('formActe');
    form.reset();
    document.getElementById('acteId').value = '';
    if (acte) {
        document.getElementById('acteId').value = acte.id;
        document.getElementById('acteLibelle').value = acte.libelle;
        document.getElementById('acteTarif').value = acte.tarif;
        document.getElementById('acteGratuit').checked = acte.est_gratuit == 1;
    }
    modal.show();
}

// ─── Modal Examen (GLOBAL) ─────────────────────────────────────────────────────
function openExamenModal(examen) {
    examen = examen || null;
    const modalEl = document.getElementById('modalExamen');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = document.getElementById('formExamen');
    form.reset();
    document.getElementById('examId').value = '';
    const montEl = document.getElementById('montLaboCalc');
    if (montEl) montEl.textContent = '0 F';
    if (examen) {
        document.getElementById('examId').value = examen.id;
        document.getElementById('examLibelle').value = examen.libelle;
        document.getElementById('examCout').value = examen.cout_total;
        document.getElementById('examPct').value = examen.pourcentage_labo;
        const cout = parseFloat(examen.cout_total) || 0;
        const pct  = parseFloat(examen.pourcentage_labo) || 0;
        if (montEl) montEl.textContent = Math.round(cout * pct / 100) + ' F';
    }
    modal.show();
}

// ─── Modal Produit (GLOBAL) ────────────────────────────────────────────────────
function openProduitModal(produit) {
    produit = produit || null;
    const modalEl = document.getElementById('modalProduit');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = document.getElementById('formProduit');
    form.reset();
    document.getElementById('prodId').value = '';
    if (produit) {
        document.getElementById('prodId').value = produit.id;
        document.getElementById('prodNom').value = produit.nom;
        document.getElementById('prodForme').value = produit.forme;
        document.getElementById('prodPrix').value = produit.prix_unitaire;
        document.getElementById('prodStock').value = produit.stock_initial;
        document.getElementById('prodSeuil').value = produit.seuil_alerte;
        const peremEl = document.getElementById('prodPeremption');
        if (peremEl && produit.date_peremption) peremEl.value = produit.date_peremption;
    }
    modal.show();
}

// ─── Modal Approvisionnement (GLOBAL) ─────────────────────────────────────────
function openApproModal(produitId, produitNom) {
    const modalEl = document.getElementById('modalAppro');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('approProduitId').value = produitId;
    const nomEl = document.getElementById('approProduitNom');
    if (nomEl) nomEl.textContent = produitNom;
    modal.show();
}

// ─── Modal Diminuer Stock (GLOBAL) ────────────────────────────────────────────
function openDiminuerModal(produitId, produitNom, stockActuel) {
    const modalEl = document.getElementById('modalDiminuer');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('dimProduitId').value = produitId;
    const nomEl = document.getElementById('dimProduitNom');
    if (nomEl) nomEl.textContent = produitNom;
    const stockEl = document.getElementById('dimStockActuel');
    if (stockEl) stockEl.textContent = stockActuel + ' unités';
    modal.show();
}

// ─── Modal Historique Stock (GLOBAL) ──────────────────────────────────────────
function voirHistoriqueStock(produitId, produitNom) {
    const modalEl = document.getElementById('modalHistoriqueStock');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const nomEl  = document.getElementById('histProduitNom');
    if (nomEl) nomEl.textContent = produitNom;
    const loading = document.getElementById('histLoading');
    const content = document.getElementById('histContent');
    if (loading) { loading.style.display = 'block'; }
    if (content) { content.style.display = 'none'; content.innerHTML = ''; }
    modal.show();
    fetch('/index.php?page=parametrage&section=pharmacie', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        body: JSON.stringify({ action: 'get_historique_stock', produit_id: produitId })
    })
    .then(r => r.json())
    .then(data => {
        if (loading) loading.style.display = 'none';
        if (!content) return;
        if (data.success && data.data && data.data.length > 0) {
            let rows = '';
            data.data.forEach(row => {
                const badge = row.type_mvt === 'entree'
                    ? '<span class="badge bg-success">Entr</span>'
                    : '<span class="badge bg-danger">Sort</span>';
                rows += `<tr>
                    <td>${formatDate(row.whendone)}</td>
                    <td>${badge}</td>
                    <td class="fw-bold">${row.quantite}</td>
                    <td class="text-muted">${row.stock_avant}</td>
                    <td class="fw-bold">${row.stock_apres}</td>
                    <td>${h(row.commentaire)}</td>
                    <td>${h(row.user_nom)} ${h(row.user_prenom)}</td>
                </tr>`;
            });
            content.innerHTML = `<table class="table table-hover align-middle mb-0 small">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Type</th><th>Qté</th>
                    <th>Avant</th><th>Après</th><th>Commentaire</th><th>Par</th>
                </tr></thead><tbody>${rows}</tbody></table>`;
        } else {
            content.innerHTML = '<div class="alert alert-warning">Aucun historique trouvé.</div>';
        }
        content.style.display = 'block';
    })
    .catch(() => {
        if (loading) loading.style.display = 'none';
        if (content) {
            content.innerHTML = '<div class="alert alert-danger">Erreur réseau.</div>';
            content.style.display = 'block';
        }
    });
}

// ─── Modal Fiche AG (GLOBAL) ──────────────────────────────────────────────────
function openFicheAgModal() {
    const modalEl = document.getElementById('modalFicheAg');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = document.getElementById('formStockFichesAg');
    if (form) form.reset();
    modal.show();
}

// ─── Éditer/Supprimer mouvement carnet (GLOBAL) ────────────────────────────────
function ouvrirEditMvtCarnet(mvtId, quantite, commentaire) {
    const newQty = prompt('Nouvelle quantité (actuelle : ' + quantite + ') :', quantite);
    if (newQty === null) return;
    const qty = parseInt(newQty, 10);
    if (isNaN(qty) || qty <= 0) { alert('Quantité invalide.'); return; }
    const newComment = prompt('Commentaire (optionnel) :', commentaire);
    if (newComment === null) return;
    const comment = encodeURIComponent(newComment);
    fetch('/index.php?page=parametrage&section=carnets', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': getCsrf() },
        body: 'action=edit_mouvement_carnet&mvt_id=' + mvtId + '&quantite=' + qty + '&commentaire=' + comment
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert('Erreur : ' + data.message); })
    .catch(() => alert('Erreur réseau.'));
}

function supprimerMvtCarnet(mvtId, quantite) {
    if (!confirm('Supprimer ce mouvement ? Cela ajustera le stock de ' + quantite + ' unités.')) return;
    fetch('/index.php?page=parametrage&section=carnets', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': getCsrf() },
        body: 'action=delete_mouvement_carnet&mvt_id=' + mvtId
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert('Erreur : ' + data.message); })
    .catch(() => alert('Erreur réseau.'));
}

// ─── Éditer/Supprimer mouvement fiche AG (GLOBAL) ─────────────────────────────
function ouvrirEditMvtFicheAg(mvtId, quantite, commentaire) {
    const newQty = prompt('Nouvelle quantité (actuelle : ' + quantite + ') :', quantite);
    if (newQty === null) return;
    const qty = parseInt(newQty, 10);
    if (isNaN(qty) || qty <= 0) { alert('Quantité invalide.'); return; }
    const newComment = prompt('Commentaire (optionnel) :', commentaire);
    if (newComment === null) return;
    const comment = encodeURIComponent(newComment);
    fetch('/index.php?page=parametrage&section=fiches_ag', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': getCsrf() },
        body: 'action=edit_mouvement_fiche_ag&mvt_id=' + mvtId + '&quantite=' + qty + '&commentaire=' + comment
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert('Erreur : ' + data.message); })
    .catch(() => alert('Erreur réseau.'));
}

function supprimerMvtFicheAg(mvtId, quantite) {
    if (!confirm('Supprimer ce mouvement ? Cela ajustera le stock de ' + quantite + ' fiches.')) return;
    fetch('/index.php?page=parametrage&section=fiches_ag', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': getCsrf() },
        body: 'action=delete_mouvement_fiche_ag&mvt_id=' + mvtId
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert('Erreur : ' + data.message); })
    .catch(() => alert('Erreur réseau.'));
}

// ─── DOMContentLoaded : uniquement bindings addEventListener + previews ─────────
document.addEventListener('DOMContentLoaded', function() {

    // ── Calcul montant labo (modal examen) ────────────────────────────────────
    const examCout = document.getElementById('examCout');
    const examPct  = document.getElementById('examPct');
    if (examCout && examPct) {
        function _updateMontLabo() {
            const cout = parseFloat(examCout.value) || 0;
            const pct  = parseFloat(examPct.value)  || 0;
            const el   = document.getElementById('montLaboCalc');
            if (el) el.textContent = Math.round(cout * pct / 100) + ' F';
        }
        examCout.addEventListener('input', _updateMontLabo);
        examPct.addEventListener('input', _updateMontLabo);
    }

    // ── Stock prévisionnel : carnets soins ────────────────────────────────────
    const qtyCarnetSoins = document.getElementById('qtyCarnetSoins');
    if (qtyCarnetSoins) {
        qtyCarnetSoins.addEventListener('input', function() {
            const stockActuel = <?= (int)($stockCarnetsSoins ?? 0) ?>;
            const ajout = parseInt(this.value, 10) || 0;
            const el = document.getElementById('prevStockSoins');
            if (el) el.textContent = stockActuel + ajout;
        });
    }

    // ── Stock prévisionnel : carnets santé ────────────────────────────────────
    const qtyCarnetSante = document.getElementById('qtyCarnetSante');
    if (qtyCarnetSante) {
        qtyCarnetSante.addEventListener('input', function() {
            const stockActuel = <?= (int)($stockCarnetsSante ?? 0) ?>;
            const ajout = parseInt(this.value, 10) || 0;
            const el = document.getElementById('prevStockSante');
            if (el) el.textContent = stockActuel + ajout;
        });
    }

    // ── Stock prévisionnel : fiches AG ────────────────────────────────────────
    const qtyFicheAg = document.getElementById('qtyFicheAg');
    if (qtyFicheAg) {
        qtyFicheAg.addEventListener('input', function() {
            const stockActuel = <?= (int)($stockFichesAg ?? 0) ?>;
            const ajout = parseInt(this.value, 10) || 0;
            const el = document.getElementById('prevStockFichesAg');
            if (el) el.textContent = stockActuel + ajout;
        });
    }

    // ── Submit via addEventListener (fallback pour formulaires sans onclick) ──
    const formBindings = [
        ['formStockCarnetsSoins', '/index.php?page=parametrage&section=carnets'],
        ['formStockCarnetsSante', '/index.php?page=parametrage&section=carnets'],
        ['formStockFichesAg',     '/index.php?page=parametrage&section=fiches_ag'],
        ['formActe',              '/index.php?page=parametrage&section=actes'],
        ['formExamen',            '/index.php?page=parametrage&section=examens'],
        ['formProduit',           '/index.php?page=parametrage&section=pharmacie'],
        ['formAppro',             '/index.php?page=parametrage&section=pharmacie'],
        ['formDiminuer',          '/index.php?page=parametrage&section=pharmacie'],
        ['formConfig',            '/index.php?page=parametrage&section=config'],
    ];
    formBindings.forEach(function([id, url]) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('submit', function(e) {
                e.preventDefault();
                saveParam(id, url);
            });
        }
    });
});
</script>

<?php
include ROOT_PATH . '/templates/layouts/footer.php';
?>
