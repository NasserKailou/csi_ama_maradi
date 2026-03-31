<?php
/**
 * Point d'entrée unique – index.php
 */
define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();

// ── Router simple basé sur le paramètre GET 'page' ────────────────────────────
$page = $_GET['page'] ?? 'login';

// Pages publiques (sans authentification)
$publicPages = ['login', 'logout'];

if (!in_array($page, $publicPages, true) && !Session::isLoggedIn()) {
    redirect('/index.php?page=login');
}

// Dispatch
$routeMap = [
    // Auth
    'login'            => ROOT_PATH . '/modules/auth/login.php',
    'logout'           => ROOT_PATH . '/modules/auth/logout.php',

    // Dashboard (Admin seulement)
    'dashboard'        => ROOT_PATH . '/modules/dashboard/index.php',

    // Percepteur
    'percepteur'       => ROOT_PATH . '/modules/percepteur/index.php',

    // Paramétrage (Admin + Comptable)
    'parametrage'      => ROOT_PATH . '/modules/parametrage/index.php',

    // Gestion utilisateurs (Admin seulement)
    'utilisateurs'     => ROOT_PATH . '/modules/auth/utilisateurs.php',
];

if (isset($routeMap[$page]) && file_exists($routeMap[$page])) {
    require $routeMap[$page];
} else {
    http_response_code(404);
    require ROOT_PATH . '/templates/errors/404.php';
}
