<?php
/**
 * Module Paramétrage – Admin & Comptable
 * Sections : actes, examens, pharmacie, config, inventaire, etat_labo, carnets, fiches_ag
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

// Base URL du projet (gère sous-dossier type /csi_ama_maradi)
$baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
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

            case 'save_acte':
                $id = (int)($_POST['id'] ?? 0);
                $libelle = trim($_POST['libelle'] ?? '');
                $tarif = max(0, (int)($_POST['tarif'] ?? 300));
                $gratuit = (int)($_POST['est_gratuit'] ?? 0);
                if (!$libelle) jsonError('Libellé obligatoire.');
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE actes_medicaux SET libelle=:l, tarif=:t, est_gratuit=:g, whodone=:w WHERE id=:id AND isDeleted=0");
                    $stmt->execute([':l'=>$libelle, ':t'=>$tarif, ':g'=>$gratuit, ':w'=>$userId, ':id'=>$id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO actes_medicaux (libelle, tarif, est_gratuit, whodone) VALUES (:l,:t,:g,:w)");
                    $stmt->execute([':l'=>$libelle, ':t'=>$tarif, ':g'=>$gratuit, ':w'=>$userId]);
                }
                jsonSuccess('Acte enregistré.');
                break;

            case 'delete_acte':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID acte manquant.');
                $stmt = $pdo->prepare("UPDATE actes_medicaux SET isDeleted=1, whodone=:w WHERE id=:id");
                $stmt->execute([':w'=>$userId, ':id'=>$id]);
                jsonSuccess('Acte archivé.');
                break;

            case 'save_examen':
                $id = (int)($_POST['id'] ?? 0);
                $libelle = trim($_POST['libelle'] ?? '');
                $cout = max(0, (int)($_POST['cout_total'] ?? 0));
                $pct = max(0, min(100, (float)($_POST['pourcentage_labo'] ?? 30)));
                if (!$libelle || !$cout) jsonError('Libellé et coût obligatoires.');
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE examens SET libelle=:l, cout_total=:c, pourcentage_labo=:p, whodone=:w WHERE id=:id AND isDeleted=0");
                    $stmt->execute([':l'=>$libelle, ':c'=>$cout, ':p'=>$pct, ':w'=>$userId, ':id'=>$id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO examens (libelle, cout_total, pourcentage_labo, whodone) VALUES (:l,:c,:p,:w)");
                    $stmt->execute([':l'=>$libelle, ':c'=>$cout, ':p'=>$pct, ':w'=>$userId]);
                }
                jsonSuccess('Examen enregistré.');
                break;

            case 'delete_examen':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID examen manquant.');
                $stmt = $pdo->prepare("UPDATE examens SET isDeleted=1, whodone=:w WHERE id=:id");
                $stmt->execute([':w'=>$userId, ':id'=>$id]);
                jsonSuccess('Examen archivé.');
                break;

            case 'save_produit':
                $id = (int)($_POST['id'] ?? 0);
                $nom = trim($_POST['nom'] ?? '');
                $forme = $_POST['forme'] ?? 'comprimé';
                $prix = max(0, (int)($_POST['prix_unitaire'] ?? 0));
                $stockIn = max(0, (int)($_POST['stock_initial'] ?? 0));
                $seuil = max(0, (int)($_POST['seuil_alerte'] ?? 10));
                $perempDate = $_POST['date_peremption'] ?? null;
                $formes = ['comprimé','sirop','ampoule','gélule','suppositoire','pommade','solution','autre'];
                if (!$nom) jsonError('Nom obligatoire.');
                if (!in_array($forme, $formes)) $forme = 'autre';
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE produits_pharmacie SET nom=:n, forme=:f, prix_unitaire=:p, seuil_alerte=:s, date_peremption=:dp, whodone=:w WHERE id=:id AND isDeleted=0");
                    $stmt->execute([':n'=>$nom, ':f'=>$forme, ':p'=>$prix, ':s'=>$seuil, ':dp'=>($perempDate ?: null), ':w'=>$userId, ':id'=>$id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO produits_pharmacie (nom, forme, prix_unitaire, stock_initial, stock_actuel, seuil_alerte, date_peremption, whodone) VALUES (:n,:f,:p,:si,:sa,:s,:dp,:w)");
                    $stmt->execute([':n'=>$nom, ':f'=>$forme, ':p'=>$prix, ':si'=>$stockIn, ':sa'=>$stockIn, ':s'=>$seuil, ':dp'=>($perempDate ?: null), ':w'=>$userId]);
                }
                jsonSuccess('Produit enregistré.');
                break;

            case 'delete_produit':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID produit manquant.');
                $stmt = $pdo->prepare("UPDATE produits_pharmacie SET isDeleted=1, whodone=:w WHERE id=:id");
                $stmt->execute([':w'=>$userId, ':id'=>$id]);
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
                $stmt->execute([':p'=>$pid, ':q'=>$qty, ':d'=>$date, ':c'=>$com, ':w'=>$userId]);
                $stmt = $pdo->prepare("UPDATE produits_pharmacie SET stock_actuel = stock_actuel + :qty WHERE id = :id");
                $stmt->execute([':qty'=>$qty, ':id'=>$pid]);
                try {
                    $stmt = $pdo->prepare("INSERT INTO mouvements_stock_pharmacie (produit_id, type_mvt, quantite, stock_avant, stock_apres, commentaire, whodone) VALUES (:p,'entree',:q,:sb,:sa,:c,:w)");
                    $stmt->execute([':p'=>$pid, ':q'=>$qty, ':sb'=>$stBefore, ':sa'=>$stBefore+$qty, ':c'=>$com, ':w'=>$userId]);
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
                $stmt->execute([':qty'=>$qty, ':w'=>$userId, ':id'=>$pid]);
                try {
                    $stmt = $pdo->prepare("INSERT INTO mouvements_stock_pharmacie (produit_id, type_mvt, quantite, stock_avant, stock_apres, commentaire, whodone) VALUES (:p,'correction',:q,:sb,:sa,:c,:w)");
                    $stmt->execute([':p'=>$pid, ':q'=>-$qty, ':sb'=>$stBefore, ':sa'=>$stBefore-$qty, ':c'=>$com, ':w'=>$userId]);
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
                        ORDER BY m.whendone DESC LIMIT 50
                    ");
                    $rows->execute([':pid'=>$pid]);
                    echo json_encode(['success'=>true, 'data'=>$rows->fetchAll()]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false, 'message'=>'Table mouvements_stock_pharmacie non trouvée. Exécutez la migration 002.']);
                }
                exit;

            case 'save_stock_carnets':
                $typeCarnet = $_POST['type_carnet'] ?? 'soins';
                if (!in_array($typeCarnet, ['soins','sante'], true)) jsonError('Type de carnet invalide.');
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
                $mvt->execute([':id'=>$mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être modifiés.');
                $typeCarnet = $mvtRow['type_carnet'] ?? 'soins';
                $diffQty = $newQty - (int)$mvtRow['quantite'];
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE mouvements_carnets SET quantite = :q, stock_apres = stock_avant + :q2, commentaire = :c WHERE id = :id");
                $stmt->execute([':q'=>$newQty, ':q2'=>$newQty, ':c'=>($newComment ?: $mvtRow['commentaire']), ':id'=>$mvtId]);
                if ($diffQty !== 0) {
                    $info = CarnetsHelper::getStock($pdo, $typeCarnet);
                    $newStock = max(0, $info['stock'] + $diffQty);
                    $cleStock = ($typeCarnet === 'soins') ? 'stock_carnets_soins' : 'stock_carnets_sante';
                    $stmt = $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v, :w) ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2");
                    $stmt->execute([':k'=>$cleStock, ':v'=>$newStock, ':w'=>$userId, ':v2'=>$newStock, ':w2'=>$userId]);
                }
                $pdo->commit();
                jsonSuccess('Mouvement modifié.');
                break;

            case 'delete_mouvement_carnet':
                requireRole('admin');
                $mvtId = (int)($_POST['mvt_id'] ?? 0);
                if (!$mvtId) jsonError('ID mouvement manquant.');
                $mvt = $pdo->prepare("SELECT * FROM mouvements_carnets WHERE id=:id LIMIT 1");
                $mvt->execute([':id'=>$mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être supprimés.');
                $typeCarnet = $mvtRow['type_carnet'] ?? 'soins';
                $cleStock = ($typeCarnet === 'soins') ? 'stock_carnets_soins' : 'stock_carnets_sante';
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM mouvements_carnets WHERE id = :id");
                $stmt->execute([':id'=>$mvtId]);
                $info = CarnetsHelper::getStock($pdo, $typeCarnet);
                $newStock = max(0, $info['stock'] - (int)$mvtRow['quantite']);
                $stmt = $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v, :w) ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2");
                $stmt->execute([':k'=>$cleStock, ':v'=>$newStock, ':w'=>$userId, ':v2'=>$newStock, ':w2'=>$userId]);
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
                $stmt->execute([':v'=>$newSeuil, ':w'=>$userId, ':v2'=>$newSeuil, ':w2'=>$userId]);
                if ($qtyAjout > 0) {
                    $curFag = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_fiches_ag'")->fetchColumn();
                    $newFag = $curFag + $qtyAjout;
                    $stmt = $pdo->prepare("INSERT INTO mouvements_fiches_ag (type_mvt,quantite,stock_avant,stock_apres,commentaire,whodone) VALUES ('initialisation',:qty,:avant,:apres,:cmt,:w)");
                    $stmt->execute([':qty'=>$qtyAjout, ':avant'=>$curFag, ':apres'=>$newFag, ':cmt'=>$commentFag, ':w'=>$userId]);
                    $stmt = $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('stock_fiches_ag',:v,:w) ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2");
                    $stmt->execute([':v'=>$newFag, ':w'=>$userId, ':v2'=>$newFag, ':w2'=>$userId]);
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
                $mvt->execute([':id'=>$mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être modifiés.');
                $diffQty = $newQty - (int)$mvtRow['quantite'];
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE mouvements_fiches_ag SET quantite=:q, stock_apres=stock_avant+:q2, commentaire=:c WHERE id=:id");
                $stmt->execute([':q'=>$newQty, ':q2'=>$newQty, ':c'=>($newComment ?: $mvtRow['commentaire']), ':id'=>$mvtId]);
                if ($diffQty !== 0) {
                    $curFag = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_fiches_ag'")->fetchColumn();
                    $adjFag = max(0, $curFag + $diffQty);
                    $stmt = $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('stock_fiches_ag',:v,:w) ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2");
                    $stmt->execute([':v'=>$adjFag, ':w'=>$userId, ':v2'=>$adjFag, ':w2'=>$userId]);
                }
                $pdo->commit();
                jsonSuccess('Mouvement modifié.');
                break;

            case 'delete_mouvement_fiche_ag':
                requireRole('admin');
                $mvtId = (int)($_POST['mvt_id'] ?? 0);
                if (!$mvtId) jsonError('ID mouvement manquant.');
                $mvt = $pdo->prepare("SELECT * FROM mouvements_fiches_ag WHERE id=:id LIMIT 1");
                $mvt->execute([':id'=>$mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être supprimés.');
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM mouvements_fiches_ag WHERE id=:id");
                $stmt->execute([':id'=>$mvtId]);
                $curFag = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_fiches_ag'")->fetchColumn();
                $adjFag = max(0, $curFag - (int)$mvtRow['quantite']);
                $stmt = $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('stock_fiches_ag',:v,:w) ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2");
                $stmt->execute([':v'=>$adjFag, ':w'=>$userId, ':v2'=>$adjFag, ':w2'=>$userId]);
                $pdo->commit();
                jsonSuccess('Mouvement supprimé. Stock ajusté à ' . $adjFag . ' fiches.');
                break;

            case 'save_config':
                $keys = ['nom_centre','adresse','telephone','pied_de_page'];
                foreach ($keys as $k) {
                    $v = trim($_POST[$k] ?? '');
                    $stmt = $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v1, :w1) ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2");
                    $stmt->execute([':k'=>$k, ':v1'=>$v, ':w1'=>$userId, ':v2'=>$v, ':w2'=>$userId]);
                }
                if (!empty($_FILES['logo']['name'])) {
                    $logoFile = uploadLogo($_FILES['logo']);
                    if ($logoFile) {
                        $stmt = $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES ('logo_filename', :v1, :w1) ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2");
                        $stmt->execute([':v1'=>$logoFile, ':w1'=>$userId, ':v2'=>$logoFile, ':w2'=>$userId]);
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
           mc.commentaire, mc.whendone, u.nom AS user_nom, u.prenom AS user_prenom
    FROM mouvements_carnets mc
    LEFT JOIN utilisateurs u ON u.id = mc.whodone
    WHERE mc.type_mvt = 'initialisation'
    ORDER BY mc.whendone DESC
")->fetchAll();

$stockFichesAg = (int)($cfg['stock_fiches_ag'] ?? 0);
$seuilAlerteFichesAg = (int)($cfg['seuil_alerte_fiches_ag'] ?? 10);

$historiqueFichesAg = $pdo->query("
    SELECT mf.id, mf.type_mvt, mf.quantite, mf.stock_avant, mf.stock_apres,
           mf.commentaire, mf.whendone, u.nom AS user_nom, u.prenom AS user_prenom
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

    <ul class="nav nav-tabs nav-fill mb-4 fw-semibold" id="paramTabs">
        <li class="nav-item"><a class="nav-link <?= $section==='actes'?'active':'' ?>" href="?page=parametrage&section=actes"><i class="bi bi-clipboard-pulse me-1"></i>Actes médicaux</a></li>
        <li class="nav-item"><a class="nav-link <?= $section==='examens'?'active':'' ?>" href="?page=parametrage&section=examens"><i class="bi bi-microscope me-1"></i>Examens & Labo</a></li>
        <li class="nav-item"><a class="nav-link <?= $section==='pharmacie'?'active':'' ?>" href="?page=parametrage&section=pharmacie"><i class="bi bi-capsule me-1"></i>Pharmacie</a></li>
        <li class="nav-item"><a class="nav-link <?= $section==='inventaire'?'active':'' ?>" href="?page=parametrage&section=inventaire"><i class="bi bi-clipboard-check me-1"></i>Inventaire</a></li>
        <li class="nav-item"><a class="nav-link <?= $section==='etat_labo'?'active':'' ?>" href="?page=parametrage&section=etat_labo"><i class="bi bi-file-earmark-pdf me-1"></i>État Labo</a></li>
        <li class="nav-item">
            <a class="nav-link <?= $section==='carnets'?'active':'' ?>" href="?page=parametrage&section=carnets">
                <i class="bi bi-journal-medical me-1"></i>Carnets
                <?php if ($stockCarnets <= $seuilAlerteCarnets && $stockCarnets > 0): ?>
                    <span class="badge bg-warning text-dark ms-1">⚠</span>
                <?php elseif ($stockCarnets === 0): ?>
                    <span class="badge bg-danger ms-1">0</span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section==='fiches_ag'?'active':'' ?>" href="?page=parametrage&section=fiches_ag">
                <i class="bi bi-file-medical me-1"></i>Fiches AG
                <?php if ($stockFichesAg <= $seuilAlerteFichesAg && $stockFichesAg > 0): ?>
                    <span class="badge bg-warning text-dark ms-1">⚠</span>
                <?php elseif ($stockFichesAg === 0): ?>
                    <span class="badge bg-danger ms-1">0</span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item"><a class="nav-link <?= $section==='config'?'active':'' ?>" href="?page=parametrage&section=config"><i class="bi bi-building me-1"></i>Config Centre</a></li>
    </ul>

    <?php if ($section === 'actes'): ?>
    <!-- ══════════════ SECTION ACTES ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-clipboard-pulse me-2"></i>Actes Médicaux & Carnets</h6>
            <button class="btn text-white btn-sm" style="background:var(--csi-green);" onclick="openActeModal()">
                <i class="bi bi-plus-circle me-1"></i>Nouvel acte
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead class="table-light"><tr><th>Libellé</th><th>Tarif</th><th>Type</th><th>Actions</th></tr></thead>
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
                        <td><span class="badge <?= $a['est_gratuit']?'bg-info':'bg-success' ?>"><?= $a['est_gratuit']?'Acte gratuit':'Payant' ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick='openActeModal(<?= json_encode($a, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('acte', <?= $a['id'] ?>, '<?= h(addslashes($a['libelle'])) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

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
                    <button type="button" class="btn text-white" style="background:var(--csi-green);" onclick="saveParam('formActe','actes')">
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
                <thead class="table-light"><tr><th>Libellé</th><th>Coût total</th><th>% Labo</th><th>Montant Labo</th><th>Actions</th></tr></thead>
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
                            <button class="btn btn-sm btn-outline-primary me-1" onclick='openExamenModal(<?= json_encode($e, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('examen', <?= $e['id'] ?>, '<?= h(addslashes($e['libelle'])) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

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
                                <input type="number" class="form-control" name="pourcentage_labo" id="examPct" value="30" min="0" max="100" step="0.5">
                            </div>
                        </div>
                        <div class="mt-3 p-2 bg-light rounded"><small>Montant labo estimé : <strong id="montLaboCalc">0 F</strong></small></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:var(--csi-green);" onclick="saveParam('formExamen','examens')">
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
                <thead class="table-light"><tr><th>Produit</th><th>Forme</th><th>Prix</th><th>Stock</th><th>Seuil</th><th>Péremption</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($produits as $p):
                    $enAlerte = $p['stock_actuel'] <= $p['seuil_alerte'] && $p['stock_actuel'] > 0;
                    $enRupture = $p['stock_actuel'] <= 0;
                    $perime = $p['date_peremption'] && $p['date_peremption'] <= date('Y-m-d');
                ?>
                    <tr class="<?= $enRupture||$perime?'table-danger':($enAlerte?'table-warning':'') ?>">
                        <td><strong><?= h($p['nom']) ?></strong></td>
                        <td><span class="badge bg-secondary"><?= h($p['forme']) ?></span></td>
                        <td><?= number_format($p['prix_unitaire'],0,',',' ') ?> F</td>
                        <td class="<?= $enRupture?'text-danger fw-bold':($enAlerte?'text-warning fw-bold':'fw-bold') ?>"><?= $p['stock_actuel'] ?></td>
                        <td><small class="text-muted"><?= $p['seuil_alerte'] ?></small></td>
                        <td>
                            <?php if ($p['date_peremption']): ?>
                                <small class="<?= $perime?'text-danger fw-bold':'' ?>"><?= date('d/m/Y', strtotime($p['date_peremption'])) ?></small>
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
                            <button class="btn btn-sm btn-outline-success me-1" title="Approvisionner" onclick="openApproModal(<?= $p['id'] ?>, '<?= h(addslashes($p['nom'])) ?>')">
                                <i class="bi bi-plus-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning me-1" title="Diminuer stock" onclick="openDiminuerModal(<?= $p['id'] ?>, '<?= h(addslashes($p['nom'])) ?>', <?= (int)$p['stock_actuel'] ?>)">
                                <i class="bi bi-dash-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info me-1" title="Historique" onclick="voirHistoriqueStock(<?= $p['id'] ?>, '<?= h(addslashes($p['nom'])) ?>')">
                                <i class="bi bi-clock-history"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick='openProduitModal(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem('produit', <?= $p['id'] ?>, '<?= h(addslashes($p['nom'])) ?>')">
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
                            <div class="col-md-8"><label class="form-label">Nom <span class="text-danger">*</span></label><input type="text" class="form-control" name="nom" id="prodNom" required></div>
                            <div class="col-md-4"><label class="form-label">Forme</label>
                                <select class="form-select" name="forme" id="prodForme">
                                    <?php foreach (['comprimé','sirop','ampoule','gélule','suppositoire','pommade','solution','autre'] as $f): ?>
                                        <option><?= h($f) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">Prix unitaire (F)</label><input type="number" class="form-control" name="prix_unitaire" id="prodPrix" min="0" value="0"></div>
                            <div class="col-md-4" id="stockInitBlock"><label class="form-label">Stock initial</label><input type="number" class="form-control" name="stock_initial" id="prodStock" min="0" value="0"></div>
                            <div class="col-md-4"><label class="form-label">Seuil d'alerte</label><input type="number" class="form-control" name="seuil_alerte" id="prodSeuil" min="0" value="10"></div>
                            <div class="col-md-6"><label class="form-label">Date de péremption</label><input type="date" class="form-control" name="date_peremption" id="prodPeremption"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:var(--csi-green);" onclick="saveParam('formProduit','pharmacie')">
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
                        <div class="alert alert-info"><i class="bi bi-capsule me-2"></i>Produit : <strong id="approProduitNom"></strong></div>
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">Quantité <span class="text-danger">*</span></label><input type="number" class="form-control" name="quantite" id="approQty" min="1" value="1" required></div>
                            <div class="col-6"><label class="form-label">Date</label><input type="date" class="form-control" name="date_appro" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-12"><label class="form-label">Commentaire</label><textarea class="form-control" name="commentaire" rows="2"></textarea></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:#1b5e20;" onclick="saveParam('formAppro','pharmacie')">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Diminuer -->
    <div class="modal fade" id="modalDiminuer" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:#b71c1c;">
                    <h5 class="modal-title text-white"><i class="bi bi-dash-circle me-2"></i>Diminuer le Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Attention :</strong> correction d'inventaire.</div>
                    <form id="formDiminuer">
                        <input type="hidden" name="action" value="diminuer_stock">
                        <input type="hidden" name="produit_id" id="dimProduitId">
                        <div class="mb-3"><label class="form-label">Produit</label><div class="fw-bold text-danger" id="dimProduitNom"></div></div>
                        <div class="mb-3"><label class="form-label">Stock actuel</label><div class="fw-bold fs-5" id="dimStockActuel"></div></div>
                        <div class="mb-3"><label class="form-label">Quantité à retirer <span class="text-danger">*</span></label><input type="number" class="form-control" name="quantite" id="dimQty" min="1" value="1" required></div>
                        <div class="mb-3"><label class="form-label">Motif <span class="text-danger">*</span></label><textarea class="form-control" name="commentaire" id="dimCommentaire" rows="2" required></textarea></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:#b71c1c;" onclick="saveParam('formDiminuer','pharmacie')">
                        <i class="bi bi-dash-circle me-1"></i>Diminuer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Historique -->
    <div class="modal fade" id="modalHistoriqueStock" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background:#1565c0;">
                    <h5 class="modal-title text-white"><i class="bi bi-clock-history me-2"></i>Historique – <span id="histProduitNom"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="histLoading" class="text-center p-4"><div class="spinner-border text-primary"></div></div>
                    <div id="histContent" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($section === 'carnets'): ?>
    <!-- ══════════════ SECTION CARNETS ══════════════ -->
    <ul class="nav nav-pills mb-4" id="carnetsTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-soins" type="button"><i class="bi bi-journal-medical me-1"></i> Carnets de Soins <span class="badge bg-light text-dark ms-1"><?= $stockCarnetsSoins ?></span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-sante" type="button"><i class="bi bi-journal-plus me-1"></i> Carnets de Santé <span class="badge bg-light text-dark ms-1"><?= $stockCarnetsSante ?></span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-historique" type="button"><i class="bi bi-clock-history me-1"></i> Historique global</button></li>
    </ul>

    <div class="tab-content">

    <!-- TAB SOINS -->
    <div class="tab-pane fade show active" id="tab-soins">
        <div class="alert alert-light border-start border-4 border-primary py-2 small">
            <i class="bi bi-info-circle me-1"></i><strong>Carnet de soins</strong> — reçus normaux (300 F + carnet 100 F).
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100" style="border-color:#1565c0;">
                    <div class="card-header" style="background:#e3f2fd;"><h6 class="mb-0" style="color:#1565c0;"><i class="bi bi-journal-medical me-2"></i>Stock Carnets de Soins</h6></div>
                    <div class="card-body text-center">
                        <?php $cls = $stockCarnetsSoins===0?'danger':($stockCarnetsSoins<=$seuilAlerteCarnetsSoins?'warning':'success');
                              $txt = $stockCarnetsSoins===0?'RUPTURE !':($stockCarnetsSoins<=$seuilAlerteCarnetsSoins?'Stock faible':'Stock suffisant'); ?>
                        <div class="display-4 fw-bold text-<?= $cls ?>"><?= $stockCarnetsSoins ?></div>
                        <p class="text-muted mb-1">carnets de soins</p>
                        <span class="badge bg-<?= $cls ?>"><?= $txt ?></span>
                        <hr><div class="text-muted small">Seuil : <strong><?= $seuilAlerteCarnetsSoins ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-csi-light"><h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Réapprovisionner Carnets de Soins</h6></div>
                    <div class="card-body">
                        <form id="formStockCarnetsSoins">
                            <input type="hidden" name="action" value="save_stock_carnets">
                            <input type="hidden" name="type_carnet" value="soins">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold">Quantité à ajouter</label><input type="number" class="form-control form-control-lg" name="quantite" min="0" value="0" id="qtyCarnetSoins"></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Seuil d'alerte</label><input type="number" class="form-control form-control-lg" name="seuil_alerte" min="0" value="<?= $seuilAlerteCarnetsSoins ?>"></div>
                                <div class="col-md-12"><label class="form-label fw-semibold">Commentaire</label><input type="text" class="form-control" name="commentaire_carnet" placeholder="Ex: Livraison du <?= date('d/m/Y') ?>"></div>
                                <div class="col-12">
                                    <div class="alert alert-info py-2 mb-2 small">Stock actuel : <strong><?= $stockCarnetsSoins ?></strong>. Nouveau : <strong id="prevStockSoins"><?= $stockCarnetsSoins ?></strong></div>
                                    <button type="button" class="btn text-white w-100" style="background:var(--csi-green);" onclick="saveParam('formStockCarnetsSoins','carnets')"><i class="bi bi-save me-1"></i>Enregistrer</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php $histSoins = array_filter($historiqueCarnets, fn($m) => ($m['type_carnet'] ?? 'soins') === 'soins'); ?>
        <div class="card">
            <div class="card-header bg-csi-light d-flex justify-content-between"><h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique – Carnets de Soins</h6><small class="text-muted"><?= count($histSoins) ?> entrée(s)</small></div>
            <div class="card-body p-0">
                <?php if (empty($histSoins)): ?>
                    <div class="p-4 text-center text-muted">Aucun approvisionnement.</div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th>#</th><th>Date</th><th class="text-center">Qté</th><th class="text-center">Avant</th><th class="text-center">Après</th><th>Commentaire</th><th>Par</th><th class="text-center">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($histSoins as $mv): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$mv['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($mv['whendone'])) ?></td>
                        <td class="text-center"><span class="badge bg-success">+<?= (int)$mv['quantite'] ?></span></td>
                        <td class="text-center text-muted"><?= (int)$mv['stock_avant'] ?></td>
                        <td class="text-center fw-bold"><?= (int)$mv['stock_apres'] ?></td>
                        <td><?= h($mv['commentaire'] ?? '—') ?></td>
                        <td><small><?= h(trim(($mv['user_nom']??'').' '.($mv['user_prenom']??''))) ?: '—' ?></small></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" onclick="ouvrirEditMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>, '<?= addslashes(h($mv['commentaire'] ?? '')) ?>')"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="supprimerMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB SANTÉ -->
    <div class="tab-pane fade" id="tab-sante">
        <div class="alert alert-light border-start border-4 border-success py-2 small"><i class="bi bi-info-circle me-1"></i><strong>Carnet de santé</strong> — actes gratuits (CPN, accouchement…).</div>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100" style="border-color:#2e7d32;">
                    <div class="card-header" style="background:#e8f5e9;"><h6 class="mb-0" style="color:#2e7d32;"><i class="bi bi-journal-plus me-2"></i>Stock Carnets de Santé</h6></div>
                    <div class="card-body text-center">
                        <?php $clsS = $stockCarnetsSante===0?'danger':($stockCarnetsSante<=$seuilAlerteCarnetsSante?'warning':'success');
                              $txtS = $stockCarnetsSante===0?'RUPTURE !':($stockCarnetsSante<=$seuilAlerteCarnetsSante?'Stock faible':'Stock suffisant'); ?>
                        <div class="display-4 fw-bold text-<?= $clsS ?>"><?= $stockCarnetsSante ?></div>
                        <p class="text-muted mb-1">carnets de santé</p>
                        <span class="badge bg-<?= $clsS ?>"><?= $txtS ?></span>
                        <hr><div class="text-muted small">Seuil : <strong><?= $seuilAlerteCarnetsSante ?></strong></div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-csi-light"><h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Réapprovisionner Carnets de Santé</h6></div>
                    <div class="card-body">
                        <form id="formStockCarnetsSante">
                            <input type="hidden" name="action" value="save_stock_carnets">
                            <input type="hidden" name="type_carnet" value="sante">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label fw-semibold">Quantité à ajouter</label><input type="number" class="form-control form-control-lg" name="quantite" min="0" value="0" id="qtyCarnetSante"></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Seuil d'alerte</label><input type="number" class="form-control form-control-lg" name="seuil_alerte" min="0" value="<?= $seuilAlerteCarnetsSante ?>"></div>
                                <div class="col-md-12"><label class="form-label fw-semibold">Commentaire</label><input type="text" class="form-control" name="commentaire_carnet" placeholder="Ex: Livraison du <?= date('d/m/Y') ?>"></div>
                                <div class="col-12">
                                    <div class="alert alert-info py-2 mb-2 small">Stock actuel : <strong><?= $stockCarnetsSante ?></strong>. Nouveau : <strong id="prevStockSante"><?= $stockCarnetsSante ?></strong></div>
                                    <button type="button" class="btn text-white w-100" style="background:var(--csi-green);" onclick="saveParam('formStockCarnetsSante','carnets')"><i class="bi bi-save me-1"></i>Enregistrer</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php $histSante = array_filter($historiqueCarnets, fn($m) => ($m['type_carnet'] ?? '') === 'sante'); ?>
        <div class="card">
            <div class="card-header bg-csi-light d-flex justify-content-between"><h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique – Carnets de Santé</h6><small class="text-muted"><?= count($histSante) ?> entrée(s)</small></div>
            <div class="card-body p-0">
                <?php if (empty($histSante)): ?>
                    <div class="p-4 text-center text-muted">Aucun approvisionnement.</div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th>#</th><th>Date</th><th class="text-center">Qté</th><th class="text-center">Avant</th><th class="text-center">Après</th><th>Commentaire</th><th>Par</th><th class="text-center">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($histSante as $mv): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$mv['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($mv['whendone'])) ?></td>
                        <td class="text-center"><span class="badge bg-success">+<?= (int)$mv['quantite'] ?></span></td>
                        <td class="text-center text-muted"><?= (int)$mv['stock_avant'] ?></td>
                        <td class="text-center fw-bold"><?= (int)$mv['stock_apres'] ?></td>
                        <td><?= h($mv['commentaire'] ?? '—') ?></td>
                        <td><small><?= h(trim(($mv['user_nom']??'').' '.($mv['user_prenom']??''))) ?: '—' ?></small></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" onclick="ouvrirEditMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>, '<?= addslashes(h($mv['commentaire'] ?? '')) ?>')"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="supprimerMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB HISTORIQUE GLOBAL -->
    <div class="tab-pane fade" id="tab-historique">
        <?php
        $allMvts = $pdo->query("
            SELECT mc.*, u.nom AS user_nom, u.prenom AS user_prenom, r.numero_recu, r.type_patient
            FROM mouvements_carnets mc
            LEFT JOIN utilisateurs u ON u.id = mc.whodone
            LEFT JOIN recus r ON r.id = mc.recu_id
            ORDER BY mc.whendone DESC LIMIT 200
        ")->fetchAll();
        ?>
        <div class="card">
            <div class="card-header bg-csi-light"><h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>Tous les mouvements (200 derniers)</h6></div>
            <div class="card-body p-0">
                <?php if (empty($allMvts)): ?>
                    <div class="p-4 text-center text-muted">Aucun mouvement.</div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th>Date</th><th class="text-center">Type carnet</th><th class="text-center">Mouvement</th><th class="text-center">Qté</th><th class="text-center">Avant</th><th class="text-center">Après</th><th>Reçu</th><th>Commentaire</th><th>Par</th></tr></thead>
                    <tbody>
                    <?php foreach ($allMvts as $mv): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($mv['whendone'])) ?></td>
                        <td class="text-center"><span class="badge bg-<?= ($mv['type_carnet']??'soins')==='sante'?'success':'primary' ?>"><?= ($mv['type_carnet']??'soins')==='sante'?'Santé':'Soins' ?></span></td>
                        <td class="text-center"><span class="badge bg-<?= $mv['type_mvt']==='sortie'?'danger':'success' ?>"><?= $mv['type_mvt']==='sortie'?'Sortie':'Entrée' ?></span></td>
                        <td class="text-center fw-bold"><?= (int)$mv['quantite'] ?></td>
                        <td class="text-center"><?= (int)$mv['stock_avant'] ?></td>
                        <td class="text-center"><?= (int)$mv['stock_apres'] ?></td>
                        <td><?php if ($mv['type_mvt']==='sortie' && !empty($mv['recu_id'])): ?><small class="text-muted">#<?= h($mv['numero_recu']) ?></small><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                        <td><?= h($mv['commentaire'] ?? '—') ?></td>
                        <td><small><?= h(trim(($mv['user_nom']??'').' '.($mv['user_prenom']??''))) ?: '—' ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

    <?php elseif ($section === 'fiches_ag'): ?>
    <!-- ══════════════ SECTION FICHES AG ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light"><h6 class="mb-0"><i class="bi bi-file-medical me-2"></i>Fiches Actes Gratuits (AG)</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card h-100" style="border-color:#1565c0;">
                        <div class="card-header" style="background:#e3f2fd;"><h6 class="mb-0" style="color:#1565c0;"><i class="bi bi-stack me-2"></i>Stock Fiches AG</h6></div>
                        <div class="card-body text-center">
                            <?php $clsF = $stockFichesAg===0?'danger':($stockFichesAg<=$seuilAlerteFichesAg?'warning':'success');
                                  $txtF = $stockFichesAg===0?'RUPTURE !':($stockFichesAg<=$seuilAlerteFichesAg?'Stock faible':'Stock suffisant'); ?>
                            <div class="display-4 fw-bold text-<?= $clsF ?>"><?= $stockFichesAg ?></div>
                            <p class="text-muted mb-1">fiches AG</p>
                            <span class="badge bg-<?= $clsF ?>"><?= $txtF ?></span>
                            <hr><div class="text-muted small">Seuil : <strong><?= $seuilAlerteFichesAg ?></strong></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-csi-light"><h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Réapprovisionner Fiches AG</h6></div>
                        <div class="card-body">
                            <form id="formStockFichesAg">
                                <input type="hidden" name="action" value="save_stock_fiches_ag">
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label fw-semibold">Quantité à ajouter</label><input type="number" class="form-control form-control-lg" name="quantite" min="0" value="0" id="qtyFicheAg"></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold">Seuil d'alerte</label><input type="number" class="form-control form-control-lg" name="seuil_alerte" min="0" value="<?= $seuilAlerteFichesAg ?>"></div>
                                    <div class="col-md-12"><label class="form-label fw-semibold">Commentaire</label><input type="text" class="form-control" name="commentaire_fiche_ag" placeholder="Ex: Livraison du <?= date('d/m/Y') ?>"></div>
                                    <div class="col-12">
                                        <div class="alert alert-info py-2 mb-2 small">Stock actuel : <strong><?= $stockFichesAg ?></strong>. Nouveau : <strong id="prevStockFichesAg"><?= $stockFichesAg ?></strong></div>
                                        <button type="button" class="btn text-white w-100" style="background:var(--csi-green);" onclick="saveParam('formStockFichesAg','fiches_ag')"><i class="bi bi-save me-1"></i>Enregistrer</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header bg-csi-light d-flex justify-content-between"><h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique – Fiches AG</h6><small class="text-muted"><?= count($historiqueFichesAg) ?> entrée(s)</small></div>
            <div class="card-body p-0">
                <?php if (empty($historiqueFichesAg)): ?>
                    <div class="p-4 text-center text-muted">Aucun approvisionnement.</div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th>#</th><th>Date</th><th class="text-center">Qté</th><th class="text-center">Avant</th><th class="text-center">Après</th><th>Commentaire</th><th>Par</th><th class="text-center">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($historiqueFichesAg as $mv): ?>
                    <tr>
                        <td class="text-muted"><?= (int)$mv['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($mv['whendone'])) ?></td>
                        <td class="text-center"><span class="badge bg-success">+<?= (int)$mv['quantite'] ?></span></td>
                        <td class="text-center text-muted"><?= (int)$mv['stock_avant'] ?></td>
                        <td class="text-center fw-bold"><?= (int)$mv['stock_apres'] ?></td>
                        <td><?= h($mv['commentaire'] ?? '—') ?></td>
                        <td><small><?= h(trim(($mv['user_nom']??'').' '.($mv['user_prenom']??''))) ?: '—' ?></small></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" onclick="ouvrirEditMvtFicheAg(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>, '<?= addslashes(h($mv['commentaire'] ?? '')) ?>')"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="supprimerMvtFicheAg(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>)"><i class="bi bi-trash"></i></button>
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
        <div class="card-header bg-csi-light"><h6 class="mb-0"><i class="bi bi-building me-2"></i>Configuration du Centre</h6></div>
        <div class="card-body">
            <form id="formConfig" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_config">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-semibold">Nom du centre</label><input type="text" class="form-control" name="nom_centre" value="<?= h($cfg['nom_centre'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Téléphone</label><input type="text" class="form-control" name="telephone" value="<?= h($cfg['telephone'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Adresse</label><input type="text" class="form-control" name="adresse" value="<?= h($cfg['adresse'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Pied de page</label><input type="text" class="form-control" name="pied_de_page" value="<?= h($cfg['pied_de_page'] ?? '') ?>"></div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Logo (JPG/PNG)</label>
                        <input type="file" class="form-control" name="logo" accept=".jpg,.jpeg,.png">
                        <?php if (!empty($cfg['logo_filename'])): ?>
                            <div class="mt-2"><small class="text-muted">Fichier actuel : <?= h($cfg['logo_filename']) ?></small></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn text-white" style="background:var(--csi-green);" onclick="saveParam('formConfig','config')"><i class="bi bi-save me-1"></i>Enregistrer la configuration</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php endif; ?>

</div>

<!-- ════════════ JavaScript commun ════════════ -->
<script>
/* Variables globales */
var BASE_URL = <?= json_encode($baseUrl) ?>;

function paramUrl(section) {
    return BASE_URL + '/index.php?page=parametrage&section=' + section;
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function formatDate(dateString) {
    if (!dateString) return '—';
    var date = new Date(String(dateString).replace(' ', 'T'));
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

/* AJAX générique */
function saveParam(formId, section) {
    var form = document.getElementById(formId);
    if (!form) { alert('Formulaire "' + formId + '" introuvable.'); return; }
    if (!form.checkValidity()) { form.reportValidity(); return; }

    var url = paramUrl(section);
    var fd  = new FormData(form);

    fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                console.error('Réponse non-JSON :', text);
                alert('Erreur serveur : réponse invalide.\nURL : ' + url + '\n\n' + text.substring(0, 300));
                return;
            }
            if (data.success) location.reload();
            else alert('Erreur : ' + (data.message || 'inconnue'));
        })
        .catch(function (err) {
            console.error('Erreur AJAX :', err);
            alert('Erreur réseau : ' + err.message);
        });
}

/* Suppression générique */
function deleteItem(type, id, libelle) {
    var labels = {
        acte:    { action: 'delete_acte',    section: 'actes',     msg: 'cet acte' },
        examen:  { action: 'delete_examen',  section: 'examens',   msg: 'cet examen' },
        produit: { action: 'delete_produit', section: 'pharmacie', msg: 'ce produit' }
    };
    var cfg = labels[type];
    if (!cfg) { alert('Type inconnu : ' + type); return; }
    if (!confirm('Confirmer l\'archivage de ' + cfg.msg + ' :\n\n« ' + libelle + ' » ?')) return;

    var fd = new FormData();
    fd.append('action', cfg.action);
    fd.append('id', id);

    fetch(paramUrl(cfg.section), { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.text(); })
        .then(function(text){
            var data;
            try { data = JSON.parse(text); }
            catch(e){ alert('Erreur serveur : ' + text.substring(0,300)); return; }
            if (data.success) location.reload();
            else alert('Erreur : ' + (data.message || 'inconnue'));
        })
        .catch(function(err){ alert('Erreur réseau : ' + err.message); });
}

/* Modals */
function openActeModal(acte) {
    var el = document.getElementById('modalActe');
    if (!el) return;
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    document.getElementById('formActe').reset();
    if (acte) {
        document.getElementById('acteId').value = acte.id;
        document.getElementById('acteLibelle').value = acte.libelle;
        document.getElementById('acteTarif').value = acte.tarif;
        document.getElementById('acteGratuit').checked = !!parseInt(acte.est_gratuit);
    } else {
        document.getElementById('acteId').value = '';
    }
    modal.show();
}

function openExamenModal(examen) {
    var el = document.getElementById('modalExamen');
    if (!el) return;
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    document.getElementById('formExamen').reset();
    document.getElementById('montLaboCalc').textContent = '0 F';
    if (examen) {
        document.getElementById('examId').value = examen.id;
        document.getElementById('examLibelle').value = examen.libelle;
        document.getElementById('examCout').value = examen.cout_total;
        document.getElementById('examPct').value = examen.pourcentage_labo;
        var m = Math.round((parseFloat(examen.cout_total)||0) * (parseFloat(examen.pourcentage_labo)||0) / 100);
        document.getElementById('montLaboCalc').textContent = m + ' F';
    } else {
        document.getElementById('examId').value = '';
    }
    modal.show();
}

function openProduitModal(produit) {
    var el = document.getElementById('modalProduit');
    if (!el) return;
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    document.getElementById('formProduit').reset();
    var stockBlock = document.getElementById('stockInitBlock');
    if (produit) {
        document.getElementById('prodId').value = produit.id;
        document.getElementById('prodNom').value = produit.nom;
        document.getElementById('prodForme').value = produit.forme;
        document.getElementById('prodPrix').value = produit.prix_unitaire;
        document.getElementById('prodStock').value = produit.stock_initial;
        document.getElementById('prodSeuil').value = produit.seuil_alerte;
        if (produit.date_peremption) document.getElementById('prodPeremption').value = produit.date_peremption;
        if (stockBlock) stockBlock.style.display = 'none';
    } else {
        document.getElementById('prodId').value = '';
        if (stockBlock) stockBlock.style.display = '';
    }
    modal.show();
}

function openApproModal(produitId, produitNom) {
    var el = document.getElementById('modalAppro');
    if (!el) return;
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    document.getElementById('formAppro').reset();
    document.getElementById('approProduitId').value = produitId;
    document.getElementById('approProduitNom').textContent = produitNom;
    document.getElementById('approQty').value = 1;
    modal.show();
}

function openDiminuerModal(produitId, produitNom, stockActuel) {
    var el = document.getElementById('modalDiminuer');
    if (!el) return;
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    document.getElementById('formDiminuer').reset();
    document.getElementById('dimProduitId').value = produitId;
    document.getElementById('dimProduitNom').textContent = produitNom;
    document.getElementById('dimStockActuel').textContent = stockActuel + ' unités';
    document.getElementById('dimQty').value = 1;
    document.getElementById('dimQty').max = stockActuel;
    modal.show();
}

function voirHistoriqueStock(produitId, produitNom) {
    var el = document.getElementById('modalHistoriqueStock');
    if (!el) return;
    var modal = bootstrap.Modal.getOrCreateInstance(el);
    document.getElementById('histProduitNom').textContent = produitNom;
    var loading = document.getElementById('histLoading');
    var content = document.getElementById('histContent');
    loading.style.display = 'block';
    content.style.display = 'none';
    content.innerHTML = '';
    modal.show();

    var fd = new FormData();
    fd.append('action', 'get_historique_stock');
    fd.append('produit_id', produitId);

    fetch(paramUrl('pharmacie'), { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
            loading.style.display = 'none';
            content.style.display = 'block';
            if (data.success && data.data && data.data.length) {
                var html = '<table class="table table-hover align-middle mb-0 small"><thead class="table-light"><tr>'
                         + '<th>Date</th><th>Type</th><th class="text-center">Qté</th>'
                         + '<th class="text-center">Avant</th><th class="text-center">Après</th>'
                         + '<th>Commentaire</th><th>Par</th></tr></thead><tbody>';
                data.data.forEach(function(row){
                    var badge = '<span class="badge bg-secondary">' + escapeHtml(row.type_mvt) + '</span>';
                    if (row.type_mvt === 'entree')          badge = '<span class="badge bg-success">Entrée</span>';
                    else if (row.type_mvt === 'sortie')     badge = '<span class="badge bg-danger">Sortie</span>';
                    else if (row.type_mvt === 'correction') badge = '<span class="badge bg-warning text-dark">Correction</span>';
                    var user = ((row.user_nom||'') + ' ' + (row.user_prenom||'')).trim() || '—';
                    html += '<tr>'
                         + '<td>' + formatDate(row.whendone) + '</td>'
                         + '<td>' + badge + '</td>'
                         + '<td class="text-center fw-bold">' + row.quantite + '</td>'
                         + '<td class="text-center text-muted">' + row.stock_avant + '</td>'
                         + '<td class="text-center fw-bold">' + row.stock_apres + '</td>'
                         + '<td>' + escapeHtml(row.commentaire || '—') + '</td>'
                         + '<td><small>' + escapeHtml(user) + '</small></td>'
                         + '</tr>';
                });
                html += '</tbody></table>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-warning m-3">' + (data.message || 'Aucun mouvement enregistré.') + '</div>';
            }
        })
        .catch(function(err){
            loading.style.display = 'none';
            content.style.display = 'block';
            content.innerHTML = '<div class="alert alert-danger m-3">Erreur réseau : ' + err.message + '</div>';
        });
}

/* Mouvements carnets */
function ouvrirEditMvtCarnet(mvtId, quantite, commentaire) {
    var newQty = prompt('Nouvelle quantité (actuelle : ' + quantite + ') :', quantite);
    if (newQty === null) return;
    var qty = parseInt(newQty);
    if (isNaN(qty) || qty <= 0) { alert('Quantité invalide.'); return; }
    var newComment = prompt('Commentaire (optionnel) :', commentaire || '');
    if (newComment === null) return;

    var fd = new FormData();
    fd.append('action', 'edit_mouvement_carnet');
    fd.append('mvt_id', mvtId);
    fd.append('quantite', qty);
    fd.append('commentaire', newComment);

    fetch(paramUrl('carnets'), { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){ if (data.success) location.reload(); else alert('Erreur : ' + data.message); })
        .catch(function(err){ alert('Erreur réseau : ' + err.message); });
}

function supprimerMvtCarnet(mvtId, quantite) {
    if (!confirm('Supprimer ce mouvement ?\nLe stock sera diminué de ' + quantite + ' unités.')) return;
    var fd = new FormData();
    fd.append('action', 'delete_mouvement_carnet');
    fd.append('mvt_id', mvtId);
    fetch(paramUrl('carnets'), { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){ if (data.success) location.reload(); else alert('Erreur : ' + data.message); })
        .catch(function(err){ alert('Erreur réseau : ' + err.message); });
}

function ouvrirEditMvtFicheAg(mvtId, quantite, commentaire) {
    var newQty = prompt('Nouvelle quantité (actuelle : ' + quantite + ') :', quantite);
    if (newQty === null) return;
    var qty = parseInt(newQty);
    if (isNaN(qty) || qty <= 0) { alert('Quantité invalide.'); return; }
    var newComment = prompt('Commentaire (optionnel) :', commentaire || '');
    if (newComment === null) return;

    var fd = new FormData();
    fd.append('action', 'edit_mouvement_fiche_ag');
    fd.append('mvt_id', mvtId);
    fd.append('quantite', qty);
    fd.append('commentaire', newComment);

    fetch(paramUrl('fiches_ag'), { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){ if (data.success) location.reload(); else alert('Erreur : ' + data.message); })
        .catch(function(err){ alert('Erreur réseau : ' + err.message); });
}

function supprimerMvtFicheAg(mvtId, quantite) {
    if (!confirm('Supprimer ce mouvement ?\nLe stock sera diminué de ' + quantite + ' fiches.')) return;
    var fd = new FormData();
    fd.append('action', 'delete_mouvement_fiche_ag');
    fd.append('mvt_id', mvtId);
    fetch(paramUrl('fiches_ag'), { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){ if (data.success) location.reload(); else alert('Erreur : ' + data.message); })
        .catch(function(err){ alert('Erreur réseau : ' + err.message); });
}

/* Listeners DOM (prévisualisations) */
document.addEventListener('DOMContentLoaded', function () {
    var qtyCS = document.getElementById('qtyCarnetSoins');
    if (qtyCS) qtyCS.addEventListener('input', function(){
        var cur = <?= (int)$stockCarnetsSoins ?>;
        var add = parseInt(this.value) || 0;
        var t = document.getElementById('prevStockSoins');
        if (t) t.textContent = cur + add;
    });

    var qtyCSa = document.getElementById('qtyCarnetSante');
    if (qtyCSa) qtyCSa.addEventListener('input', function(){
        var cur = <?= (int)$stockCarnetsSante ?>;
        var add = parseInt(this.value) || 0;
        var t = document.getElementById('prevStockSante');
        if (t) t.textContent = cur + add;
    });

    var qtyFAG = document.getElementById('qtyFicheAg');
    if (qtyFAG) qtyFAG.addEventListener('input', function(){
        var cur = <?= (int)$stockFichesAg ?>;
        var add = parseInt(this.value) || 0;
        var t = document.getElementById('prevStockFichesAg');
        if (t) t.textContent = cur + add;
    });

    var examCout = document.getElementById('examCout');
    var examPct  = document.getElementById('examPct');
    var montLabo = document.getElementById('montLaboCalc');
    if (examCout && examPct && montLabo) {
        var upd = function(){
            var c = parseFloat(examCout.value) || 0;
            var p = parseFloat(examPct.value)  || 0;
            montLabo.textContent = Math.round(c * p / 100) + ' F';
        };
        examCout.addEventListener('input', upd);
        examPct.addEventListener('input', upd);
    }

    console.log('Paramétrage JS chargé. BASE_URL =', BASE_URL);
});
</script>

<?php
include ROOT_PATH . '/templates/layouts/footer.php';
?>
