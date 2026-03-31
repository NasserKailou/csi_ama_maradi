<?php
/**
 * Configuration globale – Système CSI AMA Maradi
 * Chargement depuis variables d'environnement ou .env
 */

// ── Chargement .env si présent ────────────────────────────────────────────────
$envFile = ROOT_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME',    $_ENV['APP_NAME']    ?? 'Système CSI');
define('APP_VERSION', '1.0');
define('APP_ENV',     $_ENV['APP_ENV']     ?? 'development');
define('APP_URL',     rtrim($_ENV['APP_URL'] ?? 'http://localhost/csi_ama_maradi', '/'));

// ── Sous-répertoire web (détection automatique) ───────────────────────────────
// Ex : http://localhost/csi_ama_maradi → APP_SUBDIR = /csi_ama_maradi
// Ex : http://localhost               → APP_SUBDIR = (vide)
$_parsedUrl  = parse_url(APP_URL);
$_appPath    = rtrim($_parsedUrl['path'] ?? '', '/');
define('APP_SUBDIR', $_appPath);   // ex: "/csi_ama_maradi"  ou ""

// ── Base de données ────────────────────────────────────────────────────────────
define('DB_HOST',    $_ENV['DB_HOST']    ?? '127.0.0.1');
define('DB_PORT',    $_ENV['DB_PORT']    ?? '3306');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'csi_ama');
define('DB_USER',    $_ENV['DB_USER']    ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', 'utf8mb4');

// ── Session ────────────────────────────────────────────────────────────────────
define('SESSION_NAME',     'csi_session');
define('SESSION_LIFETIME', 8 * 3600);  // 8 heures

// ── Sécurité CSRF ─────────────────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', 'csrf_token');
define('BCRYPT_COST',     12);

// ── Uploads ───────────────────────────────────────────────────────────────────
define('UPLOAD_PATH',    ROOT_PATH . '/uploads/logos/');
define('UPLOAD_PDF',     ROOT_PATH . '/uploads/pdf/');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2 Mo
define('UPLOAD_ALLOWED',  ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ── Règles métier ─────────────────────────────────────────────────────────────
define('TARIF_CONSULTATION',  300);
define('TARIF_CARNET_SOINS',  100);
define('TARIF_CARNET_SANTE',    0);
define('STOCK_SEUIL_ALERTE',   10);
define('PHARMACIE_MAX_LIGNES', 15);

// ── Rôles ─────────────────────────────────────────────────────────────────────
define('ROLE_ADMIN',      'admin');
define('ROLE_COMPTABLE',  'comptable');
define('ROLE_PERCEPTEUR', 'percepteur');

// ── Palette couleurs ──────────────────────────────────────────────────────────
define('COLOR_PRIMARY', '#2e7d32');
define('COLOR_LIGHT',   '#e8f5e9');
define('COLOR_DARK',    '#1b5e20');

// ── Mode erreur PHP ───────────────────────────────────────────────────────────
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Africa/Niamey');
