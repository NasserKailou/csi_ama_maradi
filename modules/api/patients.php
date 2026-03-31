<?php
/**
 * API Patients – Autocomplete téléphone (dès 3 chiffres)
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable');

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

// Sécurité CSRF pour les requêtes GET depuis AJAX
$token = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Session::validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT id, telephone, nom, sexe, age, provenance
        FROM patients
        WHERE telephone LIKE :q
          AND isDeleted = 0
        ORDER BY nom ASC
        LIMIT 10
    ");
    $stmt->execute([':q' => '%' . $q . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(array_map(fn($p) => [
        'id'         => (int)$p['id'],
        'telephone'  => $p['telephone'],
        'nom'        => $p['nom'],
        'sexe'       => $p['sexe'],
        'age'        => (int)$p['age'],
        'provenance' => $p['provenance'] ?? '',
    ], $results));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([]);
}
