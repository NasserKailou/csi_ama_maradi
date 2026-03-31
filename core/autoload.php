<?php
/**
 * Autoloader PSR-0 simplifié – Charge les classes PHP du projet
 * et intègre l'autoloader Composer si disponible
 */

// ── 1. Autoloader Composer (TCPDF, etc.) ──────────────────────────────────────
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// ── 2. Chargement manuel des classes core ────────────────────────────────────
$coreFiles = [
    ROOT_PATH . '/core/Database.php',
    ROOT_PATH . '/core/Session.php',
];

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// ── 3. Autoloader générique pour les nouvelles classes ───────────────────────
spl_autoload_register(function (string $class): void {
    $searchPaths = [
        ROOT_PATH . '/core/',
        ROOT_PATH . '/modules/pdf/',
        ROOT_PATH . '/modules/',
    ];

    $file = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

    foreach ($searchPaths as $path) {
        $fullPath = $path . $file;
        if (file_exists($fullPath)) {
            require_once $fullPath;
            return;
        }
    }
});
