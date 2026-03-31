<?php
/**
 * API : Autocomplete patients par téléphone
 * GET ?q=XXX – retourne JSON [{id, nom, telephone, age, sexe, provenance}]
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable');
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) { echo '[]'; exit; }

$pdo  = Database::getInstance();
$stmt = $pdo->prepare("
    SELECT id, nom, telephone, age, sexe, provenance
    FROM patients
    WHERE telephone LIKE :q AND isDeleted = 0
    ORDER BY nom
    LIMIT 10
");
$stmt->execute([':q' => '%' . $q . '%']);
echo json_encode($stmt->fetchAll());
