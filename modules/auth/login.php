<?php
/**
 * Page de connexion – Système CSI
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();

// Déjà connecté → rediriger
if (Session::isLoggedIn()) {
    $role = Session::getRole();
    redirect(url(match($role) {
        'admin'      => 'index.php?page=dashboard',
        'comptable'  => 'index.php?page=parametrage',
        default      => 'index.php?page=percepteur',
    }));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $login    = trim($_POST['login']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($login && $password) {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare("
                SELECT id, nom, prenom, login, password, role, est_actif
                FROM utilisateurs
                WHERE login = :login AND isDeleted = 0
                LIMIT 1
            ");
            $stmt->execute([':login' => $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['est_actif']) {
                    $error = 'Votre compte est suspendu. Contactez l\'administrateur.';
                } else {
                    // Régénérer l'ID de session pour prévenir la fixation
                    session_regenerate_id(true);
                    Session::set('user_id',  (int)$user['id']);
                    Session::set('user_nom', $user['nom'] . ' ' . $user['prenom']);
                    Session::set('role',     $user['role']);
                    Session::regenerateCsrfToken();

                    redirect(url(match($user['role']) {
                        'admin'     => 'index.php?page=dashboard',
                        'comptable' => 'index.php?page=parametrage',
                        default     => 'index.php?page=percepteur',
                    }));
                }
            } else {
                $error = 'Identifiants incorrects. Veuillez réessayer.';
            }
        } catch (PDOException $e) {
            $error = 'Erreur système. Veuillez contacter l\'administrateur.';
        }
    } else {
        $error = 'Tous les champs sont obligatoires.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrfMeta() ?>
    <title>Connexion – CSI AMA Maradi</title>
    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- CSS personnalisé -->
    <link href="<?= asset('assets/css/main.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card bg-white">
        <!-- Brand -->
        <div class="text-center mb-4">
            <div class="bg-csi d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                 style="width:70px;height:70px;">
                <i class="bi bi-hospital text-white" style="font-size:2rem;"></i>
            </div>
            <h1 class="login-brand">CSI AMA Maradi</h1>
            <p class="text-muted small">Système de Gestion du Centre de Santé</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('index.php?page=login') ?>" novalidate>
            <?= csrfInput() ?>

            <div class="mb-3">
                <label for="login" class="form-label">
                    <i class="bi bi-person me-1 text-csi"></i>Identifiant
                </label>
                <input type="text" class="form-control form-control-lg" id="login" name="login"
                       value="<?= h($_POST['login'] ?? '') ?>"
                       placeholder="Votre identifiant" required autofocus autocomplete="username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">
                    <i class="bi bi-lock me-1 text-csi"></i>Mot de passe
                </label>
                <div class="input-group">
                    <input type="password" class="form-control form-control-lg" id="password"
                           name="password" placeholder="••••••••" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-lg text-white fw-bold" style="background:var(--csi-green);">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                </button>
            </div>
        </form>

        <hr class="my-4">
        <p class="text-center text-muted small mb-0">
            <i class="bi bi-shield-lock me-1"></i>
            Accès réservé au personnel autorisé du CSI
        </p>
    </div>
</div>

<!-- Bootstrap 5 JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePwd').addEventListener('click', function () {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
});
</script>
</body>
</html>
