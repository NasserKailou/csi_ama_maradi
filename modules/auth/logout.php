<?php
/**
 * Déconnexion – Système CSI
 */
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
Session::destroy();

header('Location: /index.php?page=login');
exit;
