<?php
/**
 * Réimpression d'un reçu existant (en cas d'erreur d'impression)
 * Régénère le PDF à partir des données déjà enregistrées en base.
 */
require_once __DIR__ . '/../../core/bootstrap.php';

requireRole('percepteur', 'admin', 'comptable');

$recuId = (int)($_GET['recu_id'] ?? 0);
if ($recuId <= 0) {
    http_response_code(400);
    die('Reçu invalide.');
}

$pdo = Database::getInstance();

// Vérifier que le reçu existe et que l'utilisateur a le droit d'y accéder
$stmt = $pdo->prepare("
    SELECT r.*, p.nom AS patient_nom, p.telephone, p.age, p.sexe, p.provenance, p.est_orphelin
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.id = :id AND r.isDeleted = 0
");
$stmt->execute([':id' => $recuId]);
$recu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recu) {
    http_response_code(404);
    die('Reçu introuvable.');
}

// Sécurité : un percepteur ne peut réimprimer que ses propres reçus
// (admin et comptable peuvent tout réimprimer)
$role   = Session::getRole();
$userId = Session::getUserId();
if ($role === 'percepteur' && (int)$recu['whodone'] !== (int)$userId) {
    http_response_code(403);
    die('Accès refusé : ce reçu ne vous appartient pas.');
}

// Génération du PDF — on appelle la même fonction que les save_*.php
// Adapter le nom selon ta fonction réelle (genererPdfRecu, generatePdfRecu, etc.)
require_once ROOT_PATH . '/core/pdf_generator.php';

try {
    $pdfUrl = genererPdfRecu($pdo, $recuId);  // ← fonction existante du système
    // Redirection directe vers le PDF
    header('Location: ' . $pdfUrl);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    die('Erreur de génération PDF : ' . h($e->getMessage()));
}
