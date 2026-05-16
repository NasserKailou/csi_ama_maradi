<?php
/**
 * Module Paramétrage – Admin & Comptable
 * Sections : actes, examens, pharmacie, config, inventaire, etat_labo
 */
requireRole('admin', 'comptable');

$pdo     = Database::getInstance();
$userId  = Session::getUserId();
$section = $_GET['section'] ?? 'actes';
$allowed = ['actes','examens','pharmacie','config','inventaire','etat_labo','carnets'];
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
                $id       = (int)($_POST['id'] ?? 0);
                $libelle  = trim($_POST['libelle'] ?? '');
                $tarif    = max(0, (int)($_POST['tarif'] ?? 300));
                $gratuit  = (int)($_POST['est_gratuit'] ?? 0);
                if (!$libelle) jsonError('Libellé obligatoire.');
                if ($id) {
                    $pdo->prepare("UPDATE actes_medicaux SET libelle=:l, tarif=:t, est_gratuit=:g, whodone=:w WHERE id=:id AND isDeleted=0")
                        ->execute([':l'=>$libelle,':t'=>$tarif,':g'=>$gratuit,':w'=>$userId,':id'=>$id]);
                } else {
                    $pdo->prepare("INSERT INTO actes_medicaux (libelle, tarif, est_gratuit, whodone) VALUES (:l,:t,:g,:w)")
                        ->execute([':l'=>$libelle,':t'=>$tarif,':g'=>$gratuit,':w'=>$userId]);
                }
                jsonSuccess('Acte enregistré.');
                break;

            case 'delete_acte':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID acte manquant.');
                $pdo->prepare("UPDATE actes_medicaux SET isDeleted=1, whodone=:w WHERE id=:id")
                    ->execute([':w'=>$userId, ':id'=>$id]);
                jsonSuccess('Acte archivé.');
                break;

            // ── Examens ───────────────────────────────────────────────────
            case 'save_examen':
                $id      = (int)($_POST['id'] ?? 0);
                $libelle = trim($_POST['libelle'] ?? '');
                $cout    = max(0, (int)($_POST['cout_total'] ?? 0));
                $pct     = max(0, min(100, (float)($_POST['pourcentage_labo'] ?? 30)));
                if (!$libelle || !$cout) jsonError('Libellé et coût obligatoires.');
                if ($id) {
                    $pdo->prepare("UPDATE examens SET libelle=:l, cout_total=:c, pourcentage_labo=:p, whodone=:w WHERE id=:id AND isDeleted=0")
                        ->execute([':l'=>$libelle,':c'=>$cout,':p'=>$pct,':w'=>$userId,':id'=>$id]);
                } else {
                    $pdo->prepare("INSERT INTO examens (libelle, cout_total, pourcentage_labo, whodone) VALUES (:l,:c,:p,:w)")
                        ->execute([':l'=>$libelle,':c'=>$cout,':p'=>$pct,':w'=>$userId]);
                }
                jsonSuccess('Examen enregistré.');
                break;

            case 'delete_examen':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID examen manquant.');
                $pdo->prepare("UPDATE examens SET isDeleted=1, whodone=:w WHERE id=:id")
                    ->execute([':w'=>$userId, ':id'=>$id]);
                jsonSuccess('Examen archivé.');
                break;

            // ── Produits Pharmacie ─────────────────────────────────────────
            case 'save_produit':
                $id       = (int)($_POST['id'] ?? 0);
                $nom      = trim($_POST['nom'] ?? '');
                $forme    = $_POST['forme'] ?? 'comprimé';
                $prix     = max(0, (int)($_POST['prix_unitaire'] ?? 0));
                $stockIn  = max(0, (int)($_POST['stock_initial'] ?? 0));
                $seuil    = max(0, (int)($_POST['seuil_alerte'] ?? 10));
                $perempDate = $_POST['date_peremption'] ?? null;
                $formes = ['comprimé','sirop','ampoule','gélule','suppositoire','pommade','solution','autre'];
                if (!$nom) jsonError('Nom obligatoire.');
                if (!in_array($forme, $formes)) $forme = 'autre';
                if ($id) {
                    $pdo->prepare("UPDATE produits_pharmacie SET nom=:n, forme=:f, prix_unitaire=:p,
                                   seuil_alerte=:s, date_peremption=:dp, whodone=:w WHERE id=:id AND isDeleted=0")
                        ->execute([':n'=>$nom,':f'=>$forme,':p'=>$prix,':s'=>$seuil,
                                   ':dp'=>($perempDate ?: null),':w'=>$userId,':id'=>$id]);
                } else {
                    $pdo->prepare("INSERT INTO produits_pharmacie
                                   (nom, forme, prix_unitaire, stock_initial, stock_actuel, seuil_alerte, date_peremption, whodone)
                                   VALUES (:n,:f,:p,:si,:sa,:s,:dp,:w)")
                        ->execute([':n'=>$nom,':f'=>$forme,':p'=>$prix,':si'=>$stockIn,':sa'=>$stockIn,
                                   ':s'=>$seuil,':dp'=>($perempDate ?: null),':w'=>$userId]);
                }
                jsonSuccess('Produit enregistré.');
                break;

            case 'delete_produit':
                $id = (int)($_POST['id'] ?? 0);
                if (!$id) jsonError('ID produit manquant.');
                $pdo->prepare("UPDATE produits_pharmacie SET isDeleted=1, whodone=:w WHERE id=:id")
                    ->execute([':w'=>$userId, ':id'=>$id]);
                jsonSuccess('Produit archivé.');
                break;

            case 'approvisionner':
                $pid   = (int)($_POST['produit_id'] ?? 0);
                $qty   = max(1, (int)($_POST['quantite'] ?? 0));
                $date  = $_POST['date_appro'] ?? date('Y-m-d');
                $com   = trim($_POST['commentaire'] ?? '');
                if (!$pid) jsonError('Produit invalide.');
                $pdo->beginTransaction();
                // Stock avant
                $stBefore = (int)$pdo->query("SELECT stock_actuel FROM produits_pharmacie WHERE id={$pid}")->fetchColumn();
                $pdo->prepare("INSERT INTO approvisionnements_pharmacie (produit_id, quantite, date_appro, commentaire, whodone) VALUES (:p,:q,:d,:c,:w)")
                    ->execute([':p'=>$pid,':q'=>$qty,':d'=>$date,':c'=>$com,':w'=>$userId]);
                $pdo->prepare("UPDATE produits_pharmacie SET stock_actuel = stock_actuel + :qty WHERE id = :id")
                    ->execute([':qty'=>$qty, ':id'=>$pid]);
                // Enregistrer mouvement si table existe
                try {
                    $pdo->prepare("INSERT INTO mouvements_stock_pharmacie
                        (produit_id, type_mvt, quantite, stock_avant, stock_apres, commentaire, whodone)
                        VALUES (:p,'entree',:q,:sb,:sa,:c,:w)")
                        ->execute([':p'=>$pid,':q'=>$qty,':sb'=>$stBefore,':sa'=>$stBefore+$qty,':c'=>$com,':w'=>$userId]);
                } catch (Exception $ignored) {}
                $pdo->commit();
                jsonSuccess('Stock approvisionné (+' . $qty . ' unités).');
                break;

            case 'diminuer_stock':
                // Correction/diminution manuelle stock
                $pid = (int)($_POST['produit_id'] ?? 0);
                $qty = max(1, (int)($_POST['quantite'] ?? 0));
                $com = trim($_POST['commentaire'] ?? 'Correction manuelle stock');
                if (!$pid) jsonError('Produit invalide.');
                $stBefore = (int)$pdo->query("SELECT stock_actuel FROM produits_pharmacie WHERE id={$pid}")->fetchColumn();
                if ($qty > $stBefore) jsonError("Impossible : stock actuel = {$stBefore}, quantité à retirer = {$qty}.");
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE produits_pharmacie SET stock_actuel = stock_actuel - :qty, whodone=:w WHERE id=:id")
                    ->execute([':qty'=>$qty, ':w'=>$userId, ':id'=>$pid]);
                try {
                    $pdo->prepare("INSERT INTO mouvements_stock_pharmacie
                        (produit_id, type_mvt, quantite, stock_avant, stock_apres, commentaire, whodone)
                        VALUES (:p,'correction',:q,:sb,:sa,:c,:w)")
                        ->execute([':p'=>$pid,':q'=>-$qty,':sb'=>$stBefore,':sa'=>$stBefore-$qty,':c'=>$com,':w'=>$userId]);
                } catch (Exception $ignored) {}
                $pdo->commit();
                jsonSuccess('Stock diminué de ' . $qty . ' unités. Nouveau stock : ' . ($stBefore - $qty) . '.');
                break;

            case 'get_historique_stock':
                // Retourne l'historique des mouvements d'un produit
                $pid = (int)($_POST['produit_id'] ?? 0);
                if (!$pid) jsonError('Produit invalide.');
                header('Content-Type: application/json');
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
                    $rows->execute([':pid'=>$pid]);
                    echo json_encode(['success'=>true,'data'=>$rows->fetchAll()]);
                } catch (Exception $e) {
                    echo json_encode(['success'=>false,'message'=>'Table mouvements_stock_pharmacie non trouvée. Exécutez la migration 002.']);
                }
                exit;

            case 'save_stock_carnets':
                // Initialisation / rechargement du stock carnets
                $qtyAdd    = max(0, (int)($_POST['quantite'] ?? 0));
                $seuilAlrt = max(0, (int)($_POST['seuil_alerte'] ?? 10));
                $commentaireCarnet = trim($_POST['commentaire_carnet'] ?? 'Réapprovisionnement carnets');
                if ($commentaireCarnet === '') $commentaireCarnet = 'Réapprovisionnement carnets';
                $pdo->beginTransaction();
                // Stock actuel
                $stCarnets = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_carnets'")->fetchColumn();
                $newStock  = $stCarnets + $qtyAdd;
                $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES ('stock_carnets',:v1,:w1)
                               ON DUPLICATE KEY UPDATE valeur=:v2, whodone=:w2")
                    ->execute([':v1'=>$newStock,':w1'=>$userId,':v2'=>$newStock,':w2'=>$userId]);
                $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES ('seuil_alerte_carnets',:v1,:w1)
                               ON DUPLICATE KEY UPDATE valeur=:v2, whodone=:w2")
                    ->execute([':v1'=>$seuilAlrt,':w1'=>$userId,':v2'=>$seuilAlrt,':w2'=>$userId]);
                // Enregistrer mouvement carnet (uniquement si on ajoute des carnets)
                if ($qtyAdd > 0) {
                    try {
                        $pdo->prepare("INSERT INTO mouvements_carnets
                            (type_mvt, quantite, stock_avant, stock_apres, commentaire, whodone)
                            VALUES ('initialisation',:q,:sb,:sa,:c,:w)")
                            ->execute([':q'=>$qtyAdd,':sb'=>$stCarnets,':sa'=>$newStock,':c'=>$commentaireCarnet,':w'=>$userId]);
                    } catch (Exception $ignored) {}
                }
                $pdo->commit();
                jsonSuccess('Stock carnets mis à jour. Stock actuel : ' . $newStock . ' carnets.');
                break;

            case 'edit_mouvement_carnet':
                // Modifier le commentaire et/ou la quantité d'un mouvement d'ajout (type initialisation)
                requireRole('admin');
                $mvtId      = (int)($_POST['mvt_id']    ?? 0);
                $newQty     = (int)($_POST['quantite']   ?? 0);
                $newComment = trim($_POST['commentaire'] ?? '');
                if (!$mvtId) jsonError('ID mouvement manquant.');
                if ($newQty <= 0) jsonError('La quantité doit être > 0.');
                // Récupérer le mouvement existant
                $mvt = $pdo->prepare("SELECT * FROM mouvements_carnets WHERE id=:id LIMIT 1");
                $mvt->execute([':id'=>$mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être modifiés.');
                // Recalcul : diff de quantité pour ajuster le stock
                $diffQty  = $newQty - (int)$mvtRow['quantite'];
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE mouvements_carnets SET quantite=:q, stock_apres=stock_avant+:q, commentaire=:c WHERE id=:id")
                    ->execute([':q'=>$newQty, ':c'=>($newComment ?: $mvtRow['commentaire']), ':id'=>$mvtId]);
                // Mettre à jour le stock actuel en appliquant la différence
                if ($diffQty !== 0) {
                    $curStock = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_carnets'")->fetchColumn();
                    $adjStock = max(0, $curStock + $diffQty);
                    $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('stock_carnets',:v,:w)
                                   ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2")
                        ->execute([':v'=>$adjStock,':w'=>$userId,':v2'=>$adjStock,':w2'=>$userId]);
                }
                $pdo->commit();
                jsonSuccess('Mouvement modifié.');
                break;

            case 'delete_mouvement_carnet':
                // Supprimer un mouvement d'ajout et soustraire la quantité du stock actuel
                requireRole('admin');
                $mvtId = (int)($_POST['mvt_id'] ?? 0);
                if (!$mvtId) jsonError('ID mouvement manquant.');
                $mvt = $pdo->prepare("SELECT * FROM mouvements_carnets WHERE id=:id LIMIT 1");
                $mvt->execute([':id'=>$mvtId]);
                $mvtRow = $mvt->fetch();
                if (!$mvtRow) jsonError('Mouvement introuvable.');
                if ($mvtRow['type_mvt'] !== 'initialisation') jsonError('Seuls les ajouts peuvent être supprimés.');
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM mouvements_carnets WHERE id=:id")->execute([':id'=>$mvtId]);
                // Soustraire la quantité du stock
                $curStock = (int)$pdo->query("SELECT valeur FROM config_systeme WHERE cle='stock_carnets'")->fetchColumn();
                $adjStock = max(0, $curStock - (int)$mvtRow['quantite']);
                $pdo->prepare("INSERT INTO config_systeme (cle,valeur,whodone) VALUES ('stock_carnets',:v,:w)
                               ON DUPLICATE KEY UPDATE valeur=:v2,whodone=:w2")
                    ->execute([':v'=>$adjStock,':w'=>$userId,':v2'=>$adjStock,':w2'=>$userId]);
                $pdo->commit();
                jsonSuccess('Mouvement supprimé. Stock ajusté à ' . $adjStock . ' carnets.');
                break;

            // ── Config système ─────────────────────────────────────────────
            case 'save_config':
                $keys = ['nom_centre','adresse','telephone','pied_de_page'];
                foreach ($keys as $k) {
                    $v = trim($_POST[$k] ?? '');
                    $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES (:k, :v1, :w1)
                                   ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2")
                        ->execute([':k'=>$k, ':v1'=>$v, ':w1'=>$userId, ':v2'=>$v, ':w2'=>$userId]);
                }
                // Upload logo
                if (!empty($_FILES['logo']['name'])) {
                    $logoFile = uploadLogo($_FILES['logo']);
                    if ($logoFile) {
                        $pdo->prepare("INSERT INTO config_systeme (cle, valeur, whodone) VALUES ('logo_filename', :v1, :w1)
                                       ON DUPLICATE KEY UPDATE valeur = :v2, whodone = :w2")
                            ->execute([':v1'=>$logoFile, ':w1'=>$userId, ':v2'=>$logoFile, ':w2'=>$userId]);
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
$actes    = $pdo->query("SELECT * FROM actes_medicaux WHERE isDeleted=0 ORDER BY libelle")->fetchAll();
$examens  = $pdo->query("SELECT * FROM examens WHERE isDeleted=0 ORDER BY libelle")->fetchAll();
$produits = $pdo->query("SELECT * FROM produits_pharmacie WHERE isDeleted=0 ORDER BY nom")->fetchAll();
$cfg      = [];
foreach ($pdo->query("SELECT cle, valeur FROM config_systeme WHERE isDeleted=0")->fetchAll() as $r) {
    $cfg[$r['cle']] = $r['valeur'];
}
$stockCarnets      = (int)($cfg['stock_carnets'] ?? 0);
$seuilAlerteCarnets = (int)($cfg['seuil_alerte_carnets'] ?? 10);

// Historique des ajouts de carnets (mouvements de type initialisation = réapprovisionnements)
$historiqueCarnets = $pdo->query("
    SELECT mc.id, mc.type_mvt, mc.quantite, mc.stock_avant, mc.stock_apres,
           mc.commentaire, mc.whendone,
           u.nom AS user_nom, u.prenom AS user_prenom
    FROM mouvements_carnets mc
    LEFT JOIN utilisateurs u ON u.id = mc.whodone
    WHERE mc.type_mvt = 'initialisation'
    ORDER BY mc.whendone DESC
")->fetchAll();

$pageTitle = 'Paramétrage';
include ROOT_PATH . '/templates/layouts/header.php';
?>

<div class="mt-4">
    <h4 class="fw-bold text-csi mb-4"><i class="bi bi-gear me-2"></i>Module Paramétrage</h4>

    <!-- Tabs navigation -->
    <ul class="nav nav-tabs nav-fill mb-4 fw-semibold" id="paramTabs">
        <li class="nav-item">
            <a class="nav-link <?= $section === 'actes' ? 'active' : '' ?>"
               href="?page=parametrage&section=actes">
                <i class="bi bi-clipboard-pulse me-1"></i>Actes médicaux
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'examens' ? 'active' : '' ?>"
               href="?page=parametrage&section=examens">
                <i class="bi bi-microscope me-1"></i>Examens & Labo
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'pharmacie' ? 'active' : '' ?>"
               href="?page=parametrage&section=pharmacie">
                <i class="bi bi-capsule me-1"></i>Pharmacie
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'inventaire' ? 'active' : '' ?>"
               href="?page=parametrage&section=inventaire">
                <i class="bi bi-clipboard-check me-1"></i>Inventaire
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'etat_labo' ? 'active' : '' ?>"
               href="?page=parametrage&section=etat_labo">
                <i class="bi bi-file-earmark-pdf me-1"></i>État Labo
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'carnets' ? 'active' : '' ?>"
               href="?page=parametrage&section=carnets"
               title="Gestion stock des carnets de soins">
                <i class="bi bi-journal-medical me-1"></i>Carnets
                <?php if ($stockCarnets <= $seuilAlerteCarnets && $stockCarnets > 0): ?>
                    <span class="badge bg-warning text-dark ms-1">⚠</span>
                <?php elseif ($stockCarnets === 0): ?>
                    <span class="badge bg-danger ms-1">0</span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $section === 'config' ? 'active' : '' ?>"
               href="?page=parametrage&section=config">
                <i class="bi bi-building me-1"></i>Config Centre
            </a>
        </li>
    </ul>

    <!-- ══════════════ SECTION ACTES ══════════════ -->
    <?php if ($section === 'actes'): ?>
    <div class="card">
        <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-clipboard-pulse me-2"></i>Actes Médicaux & Carnets</h6>
            <button class="btn text-white btn-sm" style="background:var(--csi-green);"
                    onclick="openActeModal()">
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
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openActeModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="deleteItem('acte', <?= $a['id'] ?>, '<?= h($a['libelle']) ?>')">
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
                    <button type="button" class="btn text-white" style="background:var(--csi-green);"
                            onclick="saveParam('formActe', '/index.php?page=parametrage&section=actes')">
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
            <button class="btn text-white btn-sm" style="background:var(--csi-green);"
                    onclick="openExamenModal()">
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
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openExamenModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="deleteItem('examen', <?= $e['id'] ?>, '<?= h($e['libelle']) ?>')">
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
                            <div class="col-6">
                                <label class="form-label">Coût total (F) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="cout_total" id="examCout" min="0" required>
                            </div>
                            <div class="col-6">
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
                    <button type="button" class="btn text-white" style="background:var(--csi-green);"
                            onclick="saveParam('formExamen', '/index.php?page=parametrage&section=examens')">
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
            <button class="btn text-white btn-sm" style="background:var(--csi-green);"
                    onclick="openProduitModal()">
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
                                    onclick="openDiminuerModal(<?= $p['id'] ?>, '<?= h($p['nom']) ?>', <?= (int)$p['stock_actuel'] ?>)">
                                <i class="bi bi-dash-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info me-1" title="Historique mouvements"
                                    onclick="voirHistoriqueStock(<?= $p['id'] ?>, '<?= h($p['nom']) ?>')">
                                <i class="bi bi-clock-history"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openProduitModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="deleteItem('produit', <?= $p['id'] ?>, '<?= h($p['nom']) ?>')">
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
                    <button type="button" class="btn text-white" style="background:var(--csi-green);"
                            onclick="saveParam('formProduit', '/index.php?page=parametrage&section=pharmacie')">
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
                    <button type="button" class="btn text-white" style="background:#1b5e20;"
                            onclick="saveParam('formAppro', '/index.php?page=parametrage&section=pharmacie')">
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
                    <button type="button" class="btn text-white" style="background:#b71c1c;"
                            onclick="saveParam('formDiminuer', '/index.php?page=parametrage&section=pharmacie')">
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
    <!-- ══════════════ SECTION CARNETS ══════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100" style="border-color:#1565c0;">
                <div class="card-header" style="background:#e3f2fd;">
                    <h6 class="mb-0" style="color:#1565c0;">
                        <i class="bi bi-journal-medical me-2"></i>Stock Carnets de Soins
                    </h6>
                </div>
                <div class="card-body text-center">
                    <?php
                    $alertCls = $stockCarnets === 0 ? 'danger' :
                               ($stockCarnets <= $seuilAlerteCarnets ? 'warning' : 'success');
                    $alertTxt = $stockCarnets === 0 ? 'RUPTURE – Plus de carnets !' :
                               ($stockCarnets <= $seuilAlerteCarnets ? 'Stock faible !' : 'Stock suffisant');
                    ?>
                    <div class="display-4 fw-bold text-<?= $alertCls ?>"><?= $stockCarnets ?></div>
                    <p class="text-muted mb-1">carnets disponibles</p>
                    <span class="badge bg-<?= $alertCls ?>"><?= $alertTxt ?></span>
                    <hr>
                    <div class="text-muted small">Seuil d'alerte : <strong><?= $seuilAlerteCarnets ?> carnets</strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Réapprovisionner / Configurer les Carnets</h6>
                </div>
                <div class="card-body">
                    <form id="formStockCarnets">
                        <input type="hidden" name="action" value="save_stock_carnets">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Quantité à ajouter
                                    <small class="text-muted">(s'ajoute au stock actuel)</small>
                                </label>
                                <input type="number" class="form-control form-control-lg" name="quantite"
                                       min="0" value="0" placeholder="Ex: 50" id="inputQtyCarnet">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Seuil d'alerte
                                    <small class="text-muted">(notification en cas de baisse)</small>
                                </label>
                                <input type="number" class="form-control form-control-lg" name="seuil_alerte"
                                       min="0" value="<?= $seuilAlerteCarnets ?>" placeholder="Ex: 10">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Commentaire / Note</label>
                                <input type="text" class="form-control" name="commentaire_carnet"
                                       placeholder="Ex: Livraison du 16/05/2026" id="inputCommentaireCarnet">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="alert alert-info py-2 mb-0 w-100 small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Stock actuel : <strong><?= $stockCarnets ?></strong>.
                                    Nouveau : <strong id="previewNewStock"><?= $stockCarnets ?></strong>
                                    (+<span id="previewQtyCarnet">0</span>)
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn text-white w-100"
                                        style="background:var(--csi-green);"
                                        onclick="saveParam('formStockCarnets', '/index.php?page=parametrage&section=carnets')">
                                    <i class="bi bi-save me-2"></i>Enregistrer l'approvisionnement
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Historique des ajouts ──────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique des ajouts de carnets</h6>
            <small class="text-muted"><?= count($historiqueCarnets) ?> entrée(s)</small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($historiqueCarnets)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                    Aucun approvisionnement enregistré.
                </div>
            <?php else: ?>
            <table class="table table-hover align-middle mb-0 small" id="tblHistoriqueCarnets">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:60px;">#</th>
                        <th>Date</th>
                        <th class="text-center">Qté ajoutée</th>
                        <th class="text-center">Stock avant</th>
                        <th class="text-center">Stock après</th>
                        <th>Commentaire</th>
                        <th>Par</th>
                        <th class="text-center" style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($historiqueCarnets as $mv): ?>
                <tr id="mvt-row-<?= (int)$mv['id'] ?>">
                    <td class="text-center text-muted"><?= (int)$mv['id'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($mv['whendone'])) ?></td>
                    <td class="text-center">
                        <span class="badge bg-success">+<?= (int)$mv['quantite'] ?></span>
                    </td>
                    <td class="text-center text-muted"><?= (int)$mv['stock_avant'] ?></td>
                    <td class="text-center fw-bold"><?= (int)$mv['stock_apres'] ?></td>
                    <td class="mvt-commentaire"><?= h($mv['commentaire'] ?? '—') ?></td>
                    <td>
                        <small class="text-muted">
                            <?= h(trim(($mv['user_nom'] ?? '') . ' ' . ($mv['user_prenom'] ?? ''))) ?: '—' ?>
                        </small>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary me-1" title="Modifier"
                                onclick="ouvrirEditMvtCarnet(<?= (int)$mv['id'] ?>, <?= (int)$mv['quantite'] ?>, '<?= addslashes(h($mv['commentaire'] ?? '')) ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" title="Supprimer"
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

    <!-- Modal édition mouvement carnet -->
    <div class="modal fade" id="modalEditMvtCarnet" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:#1565c0;">
                    <h5 class="modal-title text-white"><i class="bi bi-pencil me-2"></i>Modifier l'ajout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditMvtCarnet">
                        <input type="hidden" id="editMvtId" name="mvt_id">
                        <input type="hidden" name="action" value="edit_mouvement_carnet">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Quantité ajoutée <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="editMvtQty" name="quantite" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Commentaire</label>
                            <input type="text" class="form-control" id="editMvtComment" name="commentaire">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn text-white" style="background:var(--csi-green);"
                            onclick="enregistrerEditMvtCarnet()">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Preview stock
    document.getElementById('inputQtyCarnet')?.addEventListener('input', function() {
        const qty = parseInt(this.value) || 0;
        const current = <?= $stockCarnets ?>;
        document.getElementById('previewQtyCarnet').textContent = qty;
        document.getElementById('previewNewStock').textContent  = current + qty;
    });

    const EDIT_MVT_CARNET_URL   = '<?= url('index.php?page=parametrage') ?>';
    const DELETE_MVT_CARNET_URL = '<?= url('index.php?page=parametrage') ?>';

    window.ouvrirEditMvtCarnet = function(id, qty, commentaire) {
        document.getElementById('editMvtId').value      = id;
        document.getElementById('editMvtQty').value     = qty;
        document.getElementById('editMvtComment').value = commentaire;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditMvtCarnet')).show();
    };

    window.enregistrerEditMvtCarnet = function() {
        const id      = document.getElementById('editMvtId').value;
        const qty     = document.getElementById('editMvtQty').value;
        const comment = document.getElementById('editMvtComment').value;
        if (!qty || parseInt(qty) <= 0) { showToast('danger', 'La quantité doit être > 0.'); return; }
        ajaxPost(EDIT_MVT_CARNET_URL, {
            action: 'edit_mouvement_carnet',
            mvt_id: id,
            quantite: qty,
            commentaire: comment
        }, function(data) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditMvtCarnet')).hide();
            showToast('success', data.message || 'Mouvement modifié.');
            setTimeout(() => location.reload(), 800);
        });
    };

    window.supprimerMvtCarnet = function(id, qty) {
        if (!confirm('Supprimer cet ajout de ' + qty + ' carnet(s) ?\nLe stock sera réduit en conséquence.')) return;
        ajaxPost(DELETE_MVT_CARNET_URL, {
            action: 'delete_mouvement_carnet',
            mvt_id: id
        }, function(data) {
            showToast('success', data.message || 'Supprimé.');
            setTimeout(() => location.reload(), 800);
        });
    };
    </script>

    <?php elseif ($section === 'inventaire'): ?>
    <!-- ══════════════ SECTION INVENTAIRE ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light">
            <h6 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Inventaire Pharmaceutique</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0" data-datatable>
                <thead class="table-light">
                    <tr>
                        <th>Produit</th><th>Forme</th>
                        <th class="text-center">Stock initial</th>
                        <th class="text-center">Approvisionnements</th>
                        <th class="text-center">Ventes</th>
                        <th class="text-center">Stock théorique</th>
                        <th class="text-center">Stock actuel</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ($produits as $p) {
                    // Calcul approvisionnements
                    $appro = (int)$pdo->prepare("SELECT COALESCE(SUM(quantite),0) FROM approvisionnements_pharmacie WHERE produit_id=:id AND isDeleted=0")->execute([':id'=>$p['id']]) ? $pdo->query("SELECT COALESCE(SUM(quantite),0) FROM approvisionnements_pharmacie WHERE produit_id={$p['id']} AND isDeleted=0")->fetchColumn() : 0;
                    // Calcul ventes
                    $ventes = (int)$pdo->query("SELECT COALESCE(SUM(lp.quantite),0) FROM lignes_pharmacie lp JOIN recus r ON r.id=lp.recu_id WHERE lp.produit_id={$p['id']} AND lp.isDeleted=0 AND r.isDeleted=0")->fetchColumn();
                    $theorique = $p['stock_initial'] + $appro - $ventes;
                    $ecart = $p['stock_actuel'] - $theorique;
                ?>
                    <tr>
                        <td><strong><?= h($p['nom']) ?></strong></td>
                        <td><span class="badge bg-secondary"><?= h($p['forme']) ?></span></td>
                        <td class="text-center"><?= $p['stock_initial'] ?></td>
                        <td class="text-center text-success">+<?= $appro ?></td>
                        <td class="text-center text-danger">-<?= $ventes ?></td>
                        <td class="text-center fw-bold"><?= $theorique ?></td>
                        <td class="text-center fw-bold <?= $p['stock_actuel'] <= $p['seuil_alerte'] ? 'text-warning' : '' ?>">
                            <?= $p['stock_actuel'] ?>
                        </td>
                        <td>
                            <?php if ($ecart == 0): ?>
                                <span class="badge bg-success">Conforme</span>
                            <?php elseif ($ecart > 0): ?>
                                <span class="badge bg-info">+<?= $ecart ?> surplus</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><?= $ecart ?> écart</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($section === 'etat_labo'): ?>
    <!-- ══════════════ ÉTAT LABO ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light">
            <h6 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>État de Paie Laborantin</h6>
        </div>
        <div class="card-body">
            <form id="formEtatLabo" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Date de début <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="date_debut" id="laboDateDeb" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de fin <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="date_fin" id="laboDateFin" required>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn text-white w-100" style="background:var(--csi-green);"
                            onclick="genererEtatLabo()">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Générer PDF
                    </button>
                </div>
            </form>

            <!-- Aperçu tableau -->
            <div id="etatLaboPreview" class="mt-4"></div>
        </div>
    </div>

    <?php elseif ($section === 'config'): ?>
    <!-- ══════════════ CONFIG CENTRE ══════════════ -->
    <div class="card">
        <div class="card-header bg-csi-light">
            <h6 class="mb-0"><i class="bi bi-building me-2"></i>Configuration du Centre</h6>
        </div>
        <div class="card-body">
            <form id="formConfig" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_config">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Nom du Centre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nom_centre"
                               value="<?= h($cfg['nom_centre'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone(s)</label>
                        <input type="text" class="form-control" name="telephone"
                               value="<?= h($cfg['telephone'] ?? '') ?>" placeholder="+227 ...">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Adresse complète</label>
                        <textarea class="form-control" name="adresse" rows="2"><?= h($cfg['adresse'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Pied de page des reçus</label>
                        <input type="text" class="form-control" name="pied_de_page"
                               value="<?= h($cfg['pied_de_page'] ?? '') ?>"
                               placeholder="Ex: Merci de votre visite">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo du Centre (JPG/PNG, max 2Mo)</label>
                        <?php if (!empty($cfg['logo_filename']) && file_exists(ROOT_PATH.'/uploads/logos/'.$cfg['logo_filename'])): ?>
                        <div class="mb-2 p-2 border rounded">
                            <img src="<?= uploadUrl(h($cfg['logo_filename'])) ?>"
                                 alt="Logo actuel" style="max-height:80px;">
                            <span class="ms-2 text-muted small">Logo actuel (DirectAid)</span>
                        </div>
                        <?php elseif (file_exists(ROOT_PATH.'/uploads/logos/logo_csi.png')): ?>
                        <div class="mb-2 p-2 border rounded">
                            <img src="<?= uploadUrl('logo_csi.png') ?>" alt="Logo DirectAid" style="max-height:80px;">
                            <span class="ms-2 text-muted small">Logo DirectAid (par défaut)</span>
                        </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="logo" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Laisser vide pour conserver le logo actuel</div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="button" class="btn text-white px-4" style="background:var(--csi-green);"
                            onclick="saveConfig()">
                        <i class="bi bi-save me-1"></i>Sauvegarder la configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$paramBaseUrl = url('index.php?page=parametrage');
$etatLaboUrl  = url('modules/parametrage/etat_labo.php');
$extraJs = <<<HEREDOC
<script>
const PARAM_BASE_URL = '{$paramBaseUrl}';
const ETAT_LABO_URL  = '{$etatLaboUrl}';

// ── Fonctions génériques ────────────────────────────────────────────────────
function saveParam(formId, reloadUrl) {
    const form = document.getElementById(formId);
    // Object.fromEntries ne capture pas les checkboxes non cochées → on les ajoute à 0
    const fd   = new FormData(form);
    const data = {};
    fd.forEach((v, k) => { data[k] = v; });
    // Forcer est_gratuit=0 si la checkbox n'est pas cochée
    form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        if (!cb.checked) data[cb.name] = 0;
    });
    const section = new URLSearchParams(location.search).get('section') || 'actes';
    ajaxPost(PARAM_BASE_URL + '&section=' + section, data,
        () => setTimeout(() => location.reload(), 800));
}

function deleteItem(type, id, nom) {
    if (!confirm('Archiver "' + nom + '" ?')) return;
    const actions = { acte: 'delete_acte', examen: 'delete_examen', produit: 'delete_produit' };
    const section = new URLSearchParams(location.search).get('section') || 'actes';
    ajaxPost(PARAM_BASE_URL + '&section=' + section,
        { action: actions[type], id },
        () => setTimeout(() => location.reload(), 800));
}

// ── Actes ───────────────────────────────────────────────────────────────────
function openActeModal(a = null) {
    document.getElementById('acteId').value      = a?.id || '';
    document.getElementById('acteLibelle').value = a?.libelle || '';
    document.getElementById('acteTarif').value   = a?.tarif ?? 300;
    document.getElementById('acteGratuit').checked = !!a?.est_gratuit;
    new bootstrap.Modal(document.getElementById('modalActe')).show();
}

// ── Examens ─────────────────────────────────────────────────────────────────
function openExamenModal(e = null) {
    document.getElementById('examId').value      = e?.id || '';
    document.getElementById('examLibelle').value = e?.libelle || '';
    document.getElementById('examCout').value    = e?.cout_total || '';
    document.getElementById('examPct').value     = e?.pourcentage_labo ?? 30;
    updateMontLabo();
    new bootstrap.Modal(document.getElementById('modalExamen')).show();
}
function updateMontLabo() {
    const cout = parseFloat(document.getElementById('examCout').value) || 0;
    const pct  = parseFloat(document.getElementById('examPct').value)  || 0;
    document.getElementById('montLaboCalc').textContent =
        new Intl.NumberFormat('fr-FR').format(Math.round(cout * pct / 100)) + ' F';
}
document.getElementById('examCout')?.addEventListener('input', updateMontLabo);
document.getElementById('examPct')?.addEventListener('input', updateMontLabo);

// ── Produits ────────────────────────────────────────────────────────────────
function openProduitModal(p = null) {
    document.getElementById('prodId').value       = p?.id || '';
    document.getElementById('prodNom').value      = p?.nom || '';
    document.getElementById('prodForme').value    = p?.forme || 'comprimé';
    document.getElementById('prodPrix').value     = p?.prix_unitaire || 0;
    document.getElementById('prodSeuil').value    = p?.seuil_alerte || 10;
    document.getElementById('prodPeremption').value = p?.date_peremption || '';
    // Masquer stock initial si modification
    const stockBlock = document.getElementById('stockInitBlock');
    if (stockBlock) stockBlock.style.display = p ? 'none' : '';
    document.getElementById('prodStock').value = 0;
    new bootstrap.Modal(document.getElementById('modalProduit')).show();
}

// ── Approvisionnement ───────────────────────────────────────────────────────
function openApproModal(id, nom) {
    document.getElementById('approProduitId').value   = id;
    document.getElementById('approProduitNom').textContent = nom;
    document.getElementById('approQty').value = 1;
    new bootstrap.Modal(document.getElementById('modalAppro')).show();
}

// ── Diminuer Stock ───────────────────────────────────────────────────────────
function openDiminuerModal(id, nom, stockActuel) {
    document.getElementById('dimProduitId').value      = id;
    document.getElementById('dimProduitNom').textContent = nom;
    document.getElementById('dimStockActuel').textContent = stockActuel + ' unités';
    document.getElementById('dimQty').max         = stockActuel;
    document.getElementById('dimQty').value       = 1;
    document.getElementById('dimCommentaire').value = '';
    new bootstrap.Modal(document.getElementById('modalDiminuer')).show();
}

// ── Historique Mouvements Stock ──────────────────────────────────────────────
function voirHistoriqueStock(produitId, produitNom) {
    document.getElementById('histProduitNom').textContent = produitNom;
    document.getElementById('histLoading').style.display  = 'block';
    document.getElementById('histContent').style.display  = 'none';
    new bootstrap.Modal(document.getElementById('modalHistoriqueStock')).show();

    const fd = new FormData();
    fd.append('action', 'get_historique_stock');
    fd.append('produit_id', produitId);
    fd.append('csrf_token', CSRF_TOKEN);
    fetch(PARAM_BASE_URL + '&section=pharmacie', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        document.getElementById('histLoading').style.display = 'none';
        const cont = document.getElementById('histContent');
        if (!res.success) {
            cont.innerHTML = '<div class="alert alert-warning m-3">' + res.message + '</div>';
            cont.style.display = 'block';
            return;
        }
        const rows = res.data;
        const typeLabels = {'entree':'<span class="badge bg-success">Entrée</span>',
                            'sortie':'<span class="badge bg-danger">Sortie vente</span>',
                            'correction':'<span class="badge bg-warning text-dark">Correction</span>'};
        let html = '<table class="table table-sm table-hover align-middle mb-0">'
            + '<thead class="table-light"><tr><th>Date</th><th>Type</th><th>Quantité</th>'
            + '<th>Stock avant</th><th>Stock après</th><th>Commentaire</th><th>Par</th></tr></thead><tbody>';
        if (!rows.length) {
            html += '<tr><td colspan="7" class="text-center text-muted p-4">Aucun mouvement enregistré.</td></tr>';
        }
        rows.forEach(r => {
            const qSign = r.quantite > 0 ? '+' + r.quantite : r.quantite;
            const qColor = r.quantite > 0 ? 'color:#2e7d32;' : 'color:#d32f2f;';
            html += '<tr>'
                + '<td><small>' + r.whendone + '</small></td>'
                + '<td>' + (typeLabels[r.type_mvt] || r.type_mvt) + '</td>'
                + '<td style="font-weight:bold;' + qColor + '">' + qSign + '</td>'
                + '<td class="text-center">' + r.stock_avant + '</td>'
                + '<td class="text-center fw-bold">' + r.stock_apres + '</td>'
                + '<td><small class="text-muted">' + (r.commentaire || '—') + '</small></td>'
                + '<td><small>' + ((r.user_nom || '') + ' ' + (r.user_prenom || '')).trim() + '</small></td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        cont.innerHTML = html;
        cont.style.display = 'block';
    })
    .catch(() => {
        document.getElementById('histLoading').style.display = 'none';
        document.getElementById('histContent').innerHTML = '<div class="alert alert-danger m-3">Erreur réseau.</div>';
        document.getElementById('histContent').style.display = 'block';
    });
}

// ── Config ──────────────────────────────────────────────────────────────────
function saveConfig() {
    showLoader();
    const form = document.getElementById('formConfig');
    const fd   = new FormData(form);
    fd.append('csrf_token', CSRF_TOKEN);

    fetch(PARAM_BASE_URL + '&section=config', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        hideLoader();
        if (res.success) { showToast('success', res.message); setTimeout(() => location.reload(), 800); }
        else showToast('danger', res.message);
    })
    .catch(() => { hideLoader(); showToast('danger', 'Erreur réseau.'); });
}

// ── État Labo ───────────────────────────────────────────────────────────────
function genererEtatLabo() {
    const deb = document.getElementById('laboDateDeb').value;
    const fin = document.getElementById('laboDateFin').value;
    if (!deb || !fin) { showToast('warning', 'Sélectionnez les deux dates.'); return; }
    showLoader();
    fetch(ETAT_LABO_URL + '?date_debut=' + deb + '&date_fin=' + fin, {
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
    })
    .then(r => r.json())
    .then(res => {
        hideLoader();
        if (res.success) {
            if (res.pdf_url) window.open(res.pdf_url, '_blank');
            if (res.html)    document.getElementById('etatLaboPreview').innerHTML = res.html;
        } else showToast('danger', res.message);
    })
    .catch(() => { hideLoader(); showToast('danger', 'Erreur.'); });
}
</script>
HEREDOC;

include ROOT_PATH . '/templates/layouts/footer.php';
?>
