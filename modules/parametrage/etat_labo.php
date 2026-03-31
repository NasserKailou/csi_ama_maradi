<?php
/**
 * Module Paramétrage – État de paie Laborantin
 * GET : date_debut, date_fin
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('admin', 'comptable');

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Session::validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Token CSRF invalide.']);
    exit;
}

$dateDebut = $_GET['date_debut'] ?? '';
$dateFin   = $_GET['date_fin']   ?? '';

if (!$dateDebut || !$dateFin) {
    echo json_encode(['success'=>false,'message'=>'Dates obligatoires.']);
    exit;
}

// Validation format date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
    echo json_encode(['success'=>false,'message'=>'Format de date invalide.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare("
        SELECT le.libelle,
               COUNT(*) AS nb_actes,
               SUM(le.cout_total) AS total_brut,
               le.pourcentage_labo,
               SUM(le.montant_labo) AS total_labo
        FROM lignes_examen le
        JOIN recus r ON r.id = le.recu_id
        WHERE r.isDeleted = 0
          AND le.isDeleted = 0
          AND DATE(r.whendone) BETWEEN :deb AND :fin
        GROUP BY le.examen_id, le.libelle, le.pourcentage_labo
        ORDER BY le.libelle
    ");
    $stmt->execute([':deb'=>$dateDebut, ':fin'=>$dateFin]);
    $lignes = $stmt->fetchAll();

    $totalLabo = array_sum(array_column($lignes, 'total_labo'));

    // Générer l'aperçu HTML
    $debFr = date('d/m/Y', strtotime($dateDebut));
    $finFr = date('d/m/Y', strtotime($dateFin));

    $html = "<div class='table-responsive'>";
    $html .= "<div class='d-flex justify-content-between align-items-center mb-2'>";
    $html .= "<h6 class='mb-0 text-csi'><i class='bi bi-microscope me-2'></i>État de paie Laborantin : {$debFr} → {$finFr}</h6>";
    $html .= "</div>";
    $html .= "<table class='table table-bordered table-hover align-middle'>";
    $html .= "<thead class='table-success'><tr>";
    $html .= "<th>Examen</th><th class='text-center'>Nb actes</th>";
    $html .= "<th class='text-end'>Total brut</th><th class='text-center'>% Labo</th>";
    $html .= "<th class='text-end fw-bold'>Montant Labo</th></tr></thead><tbody>";

    if (empty($lignes)) {
        $html .= "<tr><td colspan='5' class='text-center text-muted'>Aucun examen sur cette période.</td></tr>";
    } else {
        foreach ($lignes as $l) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($l['libelle'], ENT_QUOTES) . "</td>";
            $html .= "<td class='text-center'><span class='badge bg-secondary'>" . (int)$l['nb_actes'] . "</span></td>";
            $html .= "<td class='text-end'>" . number_format((int)$l['total_brut'],0,',',' ') . " F</td>";
            $html .= "<td class='text-center'><span class='badge bg-warning text-dark'>" . $l['pourcentage_labo'] . "%</span></td>";
            $html .= "<td class='text-end fw-bold text-success'>" . number_format((int)$l['total_labo'],0,',',' ') . " F</td>";
            $html .= "</tr>";
        }
    }
    $html .= "</tbody>";
    $html .= "<tfoot><tr class='table-success fw-bold'>";
    $html .= "<td colspan='4' class='text-end'>TOTAL DÛ AU LABORANTIN :</td>";
    $html .= "<td class='text-end fs-6'>" . number_format((int)$totalLabo,0,',',' ') . " F</td>";
    $html .= "</tr></tfoot>";
    $html .= "</table></div>";

    // Générer PDF
    $pdfUrl = null;
    try {
        require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
        $pdf     = new PdfGenerator($pdo);
        $pdfFile = $pdf->generateEtatLabo($dateDebut, $dateFin);
        $pdfUrl  = '/uploads/pdf/' . basename($pdfFile);
    } catch (Throwable $e) {
        // PDF optionnel – pas bloquant
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'État généré.',
        'html'     => $html,
        'pdf_url'  => $pdfUrl,
        'total'    => (int)$totalLabo
    ]);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Erreur BDD : ' . (APP_ENV === 'development' ? $e->getMessage() : 'Contactez l\'admin.')]);
}
