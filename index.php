<?php
/**
 * Point d'entrée unique – index.php
 */
define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

// ── Buffer de sortie pour éviter que les warnings/notices PHP
//    ne corrompent les réponses JSON AJAX ────────────────────────────────────
ob_start();

// Détection requête AJAX : désactiver display_errors pour ne pas polluer JSON
$isAjax = (
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json'
    || $_SERVER['REQUEST_METHOD'] === 'POST'
);
if ($isAjax) {
    ini_set('display_errors', '0');
}

Session::start();

// ── Router simple basé sur le paramètre GET 'page' ────────────────────────────
$page = $_GET['page'] ?? 'login';

// Pages publiques (sans authentification)
$publicPages = ['login', 'logout'];

if (!in_array($page, $publicPages, true) && !Session::isLoggedIn()) {
    ob_end_clean();
    redirect(url('index.php?page=login'));
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

    // Dashboard analytique avancé (Admin seulement)
    'analytics'        => ROOT_PATH . '/modules/dashboard/analytics.php',

    // Récapitulatif patients (Admin + Comptable + Percepteur)
    'patients'         => ROOT_PATH . '/modules/patients/index.php',

    // module reglement
    'reglements'         => ROOT_PATH . '/modules/reglements/index.php',
];

if (isset($routeMap[$page]) && file_exists($routeMap[$page])) {
    require $routeMap[$page];
} else {
    ob_end_clean();
    http_response_code(404);
    require ROOT_PATH . '/templates/errors/404.php';
}

// Pour les pages HTML normales, vider le buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
