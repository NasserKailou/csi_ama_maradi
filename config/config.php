<?php
/**
 * Configuration centrale - Système de Gestion CSI
 * @version 1.0
 */

// ─── Environnement ───────────────────────────────────────────────────────────
define('APP_NAME', 'Système CSI');
define('APP_VERSION', '1.0');
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost');
define('ROOT_PATH', dirname(__DIR__));

// ─── Base de données ──────────────────────────────────────────────────────────
define('DB_HOST',     getenv('DB_HOST')     ?: '127.0.0.1');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'csi_ama');
define('DB_USER',     getenv('DB_USER')     ?: 'root');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_CHARSET',  'utf8mb4');

// ─── Session ─────────────────────────────────────────────────────────────────
define('SESSION_NAME',    'csi_session');
define('SESSION_LIFETIME', 3600 * 8); // 8 heures

// ─── Sécurité ─────────────────────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_ALGO',    PASSWORD_BCRYPT);
define('PASSWORD_COST',    12);

// ─── Upload ──────────────────────────────────────────────────────────────────
define('UPLOAD_PATH',      ROOT_PATH . '/uploads/logos/');
define('UPLOAD_MAX_SIZE',  2 * 1024 * 1024); // 2 Mo
define('UPLOAD_ALLOWED',   ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ─── PDF ──────────────────────────────────────────────────────────────────────
define('PDF_PATH', ROOT_PATH . '/uploads/pdf/');

// ─── Constantes métier ────────────────────────────────────────────────────────
define('TARIF_CONSULTATION',   300);
define('TARIF_CARNET_SOINS',   100);
define('TARIF_CARNET_SANTE',   0);   // gratuit pour femmes enceintes
define('MAX_PRODUITS_RECU',    15);

// ─── Profils ──────────────────────────────────────────────────────────────────
define('ROLE_ADMIN',      'admin');
define('ROLE_COMPTABLE',  'comptable');
define('ROLE_PERCEPTEUR', 'percepteur');

// ─── Charte graphique ────────────────────────────────────────────────────────
define('COLOR_PRIMARY', '#2e7d32');
define('COLOR_LIGHT',   '#e8f5e9');
define('COLOR_DARK',    '#1b5e20');
