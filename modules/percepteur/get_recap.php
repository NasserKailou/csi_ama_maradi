<?php
/**
 * API : Récapitulatif Patient (modal)
 * GET : recu_id
 */
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable');

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Session::validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['html' => '<p class="text-danger">Accès refusé.</p>']);
    exit;
}

$recuId = (int)($_GET['recu_id'] ?? 0);
if (!$recuId) {
    echo json_encode(['html' => '<p class="text-muted">ID invalide.</p>']);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Reçu principal
    $stmtR = $pdo->prepare("
        SELECT r.*, p.nom AS patient_nom, p.telephone, p.sexe, p.age, p.provenance
        FROM recus r JOIN patients p ON p.id = r.patient_id
        WHERE r.id = :id AND r.isDeleted = 0 LIMIT 1
    ");
    $stmtR->execute([':id' => $recuId]);
    $recu = $stmtR->fetch();

    if (!$recu) {
        echo json_encode(['html' => '<p class="text-muted">Reçu introuvable.</p>']);
        exit;
    }

    // Lignes selon le type
    $lignes = [];
    if ($recu['type_recu'] === 'consultation') {
        $stmt = $pdo->prepare("SELECT libelle, tarif, est_gratuit, avec_carnet, tarif_carnet FROM lignes_consultation WHERE recu_id=:id AND isDeleted=0");
        $stmt->execute([':id' => $recuId]);
        $lignes = $stmt->fetchAll();
    } elseif ($recu['type_recu'] === 'examen') {
        $stmt = $pdo->prepare("SELECT libelle, cout_total, montant_labo FROM lignes_examen WHERE recu_id=:id AND isDeleted=0");
        $stmt->execute([':id' => $recuId]);
        $lignes = $stmt->fetchAll();
    } elseif ($recu['type_recu'] === 'pharmacie') {
        $stmt = $pdo->prepare("SELECT nom, forme, quantite, prix_unitaire, total_ligne FROM lignes_pharmacie WHERE recu_id=:id AND isDeleted=0");
        $stmt->execute([':id' => $recuId]);
        $lignes = $stmt->fetchAll();
    }

    // Construire le HTML du récapitulatif
    $numFormate    = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
    $isOrphelin    = in_array($recu['type_patient'], ['orphelin','acte_gratuit']);
    $badgeColor    = match($recu['type_recu']) { 'consultation'=>'success', 'examen'=>'warning', 'pharmacie'=>'info', default=>'secondary' };
    $typeLabel     = ucfirst($recu['type_recu']);
    $montantAff    = $isOrphelin ? '<span class="text-danger fw-bold">0 F (GRATUIT)</span>'
                                : '<strong>' . number_format($recu['montant_encaisse'],0,',',' ') . ' F</strong>';

    $html = '<div class="p-2">';
    $html .= "<div class='d-flex justify-content-between align-items-center mb-3'>";
    $html .= "<h5 class='text-csi mb-0'>Reçu {$numFormate}</h5>";
    $html .= "<span class='badge bg-{$badgeColor}'>{$typeLabel}</span>";
    $html .= '</div>';

    // Info patient
    $html .= "<div class='mb-3 p-2 bg-light rounded'>";
    $html .= "<div><strong>Patient :</strong> " . htmlspecialchars($recu['patient_nom'], ENT_QUOTES) . "</div>";
    $html .= "<div><strong>Téléphone :</strong> " . htmlspecialchars($recu['telephone'], ENT_QUOTES) . "</div>";
    $html .= "<div><strong>Âge :</strong> " . (int)$recu['age'] . " ans &nbsp;&nbsp; <strong>Sexe :</strong> " . ($recu['sexe'] === 'M' ? 'Masculin' : 'Féminin') . "</div>";
    if ($recu['provenance']) {
        $html .= "<div><strong>Provenance :</strong> " . htmlspecialchars($recu['provenance'], ENT_QUOTES) . "</div>";
    }
    $html .= "<div class='text-muted small'>" . date('d/m/Y à H:i', strtotime($recu['whendone'])) . "</div>";
    $html .= '</div>';

    // Lignes détail
    if (!empty($lignes)) {
        $html .= '<table class="table table-sm table-bordered mb-2"><tbody>';
        foreach ($lignes as $l) {
            if ($recu['type_recu'] === 'consultation') {
                $html .= '<tr><td>' . htmlspecialchars($l['libelle'], ENT_QUOTES) . '</td>';
                $html .= '<td class="text-end fw-bold">' . number_format($l['tarif'],0,',',' ') . ' F</td></tr>';
                if ($l['avec_carnet'] && !$isOrphelin) {
                    $html .= '<tr><td>Carnet de Soins</td>';
                    $html .= '<td class="text-end">' . number_format($l['tarif_carnet'],0,',',' ') . ' F</td></tr>';
                }
            } elseif ($recu['type_recu'] === 'examen') {
                $html .= '<tr><td>' . htmlspecialchars($l['libelle'], ENT_QUOTES) . '</td>';
                $html .= '<td class="text-end fw-bold">' . number_format($l['cout_total'],0,',',' ') . ' F</td></tr>';
            } elseif ($recu['type_recu'] === 'pharmacie') {
                $html .= '<tr><td>' . htmlspecialchars($l['nom'], ENT_QUOTES) . ' <small class="text-muted">(' . htmlspecialchars($l['forme'], ENT_QUOTES) . ')</small></td>';
                $html .= '<td class="text-center">x' . $l['quantite'] . '</td>';
                $html .= '<td class="text-end fw-bold">' . number_format($l['total_ligne'],0,',',' ') . ' F</td></tr>';
            }
        }
        $html .= '</tbody></table>';
    }

    // Total
    $html .= "<div class='d-flex justify-content-between p-2 bg-success bg-opacity-10 rounded border-top border-success'>";
    $html .= "<strong>TOTAL ENCAISSÉ :</strong>{$montantAff}";
    $html .= '</div>';

    // Liens impression
    $html .= "<div class='mt-3 text-center'>";
    $html .= "<a href='/uploads/pdf/' class='btn btn-sm btn-outline-secondary' target='_blank'>";
    $html .= "<i class='bi bi-printer me-1'></i>Aller aux PDF</a>";
    $html .= '</div>';

    $html .= '</div>';

    echo json_encode(['html' => $html]);

} catch (PDOException $e) {
    echo json_encode(['html' => '<p class="text-danger">Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>']);
}
