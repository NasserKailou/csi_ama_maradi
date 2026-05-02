<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole('admin', 'comptable');
header('Content-Type: application/json');
$pdo = Database::getInstance();
$patientId = (int)($_GET['patient_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id FROM recus WHERE isDeleted=0 AND type_patient='orphelin' AND statut_reglement='en_instance' AND patient_id=?");
$stmt->execute([$patientId]);
echo json_encode(['ids' => array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))]);
