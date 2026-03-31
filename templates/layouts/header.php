<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrfMeta() ?>
    <title><?= h($pageTitle ?? 'Système CSI') ?> – CSI AMA Maradi</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- CSS personnalisé -->
    <link href="<?= asset('assets/css/main.css') ?>" rel="stylesheet">
    <?php if (isset($extraCss)) echo $extraCss; ?>
    <!-- Variables globales JS (sous-dossier XAMPP) -->
    <script>
        const APP_BASE_URL      = '<?= url('') ?>';
        const PATIENTS_API_URL  = '<?= url('modules/api/patients.php') ?>';
        const INDEX_URL         = '<?= url('index.php') ?>';
    </script>
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark bg-csi sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= url('index.php') ?>">
            <?php
            $logo = '';
            try {
                $pdo = Database::getInstance();
                $logoFile = $pdo->query("SELECT valeur FROM config_systeme WHERE cle='logo_filename' AND isDeleted=0 LIMIT 1")->fetchColumn();
                if ($logoFile && file_exists(ROOT_PATH . '/uploads/logos/' . $logoFile)) {
                    $logo = '<img src="' . uploadUrl($logoFile) . '" alt="Logo CSI" height="38" class="rounded">';
                }
            } catch (Exception $e) {}
            ?>
            <?= $logo ?: '<i class="bi bi-hospital fs-4"></i>' ?>
            <span class="d-none d-md-inline">CSI AMA Maradi</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <!-- Nav gauche -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (Session::hasRole('admin')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($page === 'dashboard') ? 'active' : '' ?>" href="<?= url('index.php?page=dashboard') ?>">
                        <i class="bi bi-speedometer2"></i> Tableau de bord
                    </a>
                </li>
                <?php endif; ?>

                <?php if (Session::hasRole('percepteur')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($page === 'percepteur') ? 'active' : '' ?>" href="<?= url('index.php?page=percepteur') ?>">
                        <i class="bi bi-person-badge"></i> Espace Percepteur
                    </a>
                </li>
                <?php endif; ?>

                <?php if (Session::hasRole('admin', 'comptable')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($page, ['parametrage']) ? 'active' : '' ?>" 
                       href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-gear"></i> Paramétrage
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= url('index.php?page=parametrage&section=actes') ?>"><i class="bi bi-clipboard-pulse me-2"></i>Actes médicaux</a></li>
                        <li><a class="dropdown-item" href="<?= url('index.php?page=parametrage&section=examens') ?>"><i class="bi bi-microscope me-2"></i>Examens &amp; Labo</a></li>
                        <li><a class="dropdown-item" href="<?= url('index.php?page=parametrage&section=pharmacie') ?>"><i class="bi bi-capsule me-2"></i>Pharmacie / Stock</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('index.php?page=parametrage&section=config') ?>"><i class="bi bi-building me-2"></i>Config. centre</a></li>
                        <li><a class="dropdown-item" href="<?= url('index.php?page=parametrage&section=inventaire') ?>"><i class="bi bi-clipboard-check me-2"></i>Inventaire</a></li>
                        <li><a class="dropdown-item" href="<?= url('index.php?page=parametrage&section=etat_labo') ?>"><i class="bi bi-file-earmark-pdf me-2"></i>État de paie labo</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (Session::hasRole('admin')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($page === 'utilisateurs') ? 'active' : '' ?>" href="<?= url('index.php?page=utilisateurs') ?>">
                        <i class="bi bi-people"></i> Utilisateurs
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Nav droite : infos utilisateur -->
            <ul class="navbar-nav align-items-center gap-2">
                <?php
                $role = Session::getRole();
                $badgeColor = match($role) { 'admin' => 'danger', 'comptable' => 'warning', default => 'info' };
                $roleLabel  = match($role) { 'admin' => 'Administrateur', 'comptable' => 'Comptable', default => 'Percepteur' };
                ?>
                <li class="nav-item">
                    <span class="badge bg-<?= $badgeColor ?> px-3 py-2">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= h(Session::get('user_nom', 'Inconnu')) ?> · <?= $roleLabel ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-light btn-sm" href="<?= url('index.php?page=logout') ?>">
                        <i class="bi bi-box-arrow-right"></i> Déconnexion
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ── Flash messages ────────────────────────────────────────────────────────── -->
<div class="container-fluid px-4 mt-3">
    <?php foreach (Session::getFlash() as $f): ?>
    <div class="alert alert-<?= h($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= h($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Contenu principal ─────────────────────────────────────────────────────── -->
<main class="container-fluid px-4 pb-5">
