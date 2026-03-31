<?php
/**
 * Autoloader PSR-4 simplifié
 */
spl_autoload_register(function (string $class): void {
    $dirs = [
        ROOT_PATH . '/core/',
        ROOT_PATH . '/modules/auth/',
        ROOT_PATH . '/modules/percepteur/',
        ROOT_PATH . '/modules/parametrage/',
        ROOT_PATH . '/modules/dashboard/',
        ROOT_PATH . '/modules/pdf/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
