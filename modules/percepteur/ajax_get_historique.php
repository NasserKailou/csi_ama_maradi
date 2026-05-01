<?php
/**
 * modules/percepteur/ajax_get_historique.php
 * Endpoint AJAX – retourne l'historique des modifications d'un reçu (JSON)
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
ob_start(); // AVANT tout require_once

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__FILE__, 3)); // ← 3 niveaux obligatoires
}

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ── Session ───────────────────────────────────────────────────────────────────
Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['html' => null, 'error' => 'Non authentifié']);
    exit;
}
$allowedRoles = ['percepteur', 'admin', 'comptable'];
if (!in_array(Session::getRole(), $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['html' => null, 'error' => 'Accès refusé']);
    exit;
}

// ── Paramètres ────────────────────────────────────────────────────────────────
$pdo    = Database::getInstance();
$recuId = (int)($_GET['recu_id'] ?? 0);

if ($recuId <= 0) {
    echo json_encode(['html' => null, 'error' => 'recu_id manquant']);
    exit;
}

// ── Requête ───────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT mr.type_recu,
       mr.motif,
       mr.detail_avant,
       mr.detail_apres,
       mr.whendone,
       CONCAT(u.prenom, ' ', u.nom) AS user_nom,   -- ← prenom + nom
       u.role                        AS user_role
FROM modifications_recus mr
JOIN utilisateurs u ON u.id = mr.user_id
WHERE mr.recu_id = :rid
ORDER BY mr.whendone DESC
");
$stmt->execute([':rid' => $recuId]);
$modifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($modifications)) {
    echo json_encode([
        'html'  => '<p class="text-muted text-center py-3">Aucune modification enregistrée.</p>',
        'error' => null,
    ]);
    exit;
}

// ── Construction HTML ─────────────────────────────────────────────────────────
$html = '';

foreach ($modifications as $mod) {
    $date  = date('d/m/Y à H:i', strtotime($mod['whendone']));
    $user  = htmlspecialchars($mod['user_nom'],  ENT_QUOTES, 'UTF-8');
    $role  = htmlspecialchars(ucfirst($mod['user_role'] ?? ''), ENT_QUOTES, 'UTF-8');
    $motif = htmlspecialchars($mod['motif'], ENT_QUOTES, 'UTF-8');

    $typeLabel = match($mod['type_recu']) {
        'consultation' => '<span class="badge bg-success">Consultation</span>',
        'examen'       => '<span class="badge" style="background:#e65100;color:#fff">Examen</span>',
        'pharmacie'    => '<span class="badge" style="background:#006064;color:#fff">Pharmacie</span>',
        default        => '<span class="badge bg-secondary">'
                          . htmlspecialchars($mod['type_recu'], ENT_QUOTES, 'UTF-8')
                          . '</span>',
    };

    $avant = json_decode($mod['detail_avant'] ?? '{}', true) ?? [];
    $apres = json_decode($mod['detail_apres'] ?? '{}', true) ?? [];

    // ── Résumé avant/après ────────────────────────────────────────────────
    $resume = '';

    if ($mod['type_recu'] === 'consultation') {
        $avC = isset($avant['avec_carnet'])
            ? ($avant['avec_carnet'] ? 'Avec carnet' : 'Sans carnet') : '—';
        $apC = isset($apres['avec_carnet'])
            ? ($apres['avec_carnet'] ? 'Avec carnet' : 'Sans carnet') : '—';
        $avM = isset($avant['montant_total'])
            ? number_format((int)$avant['montant_total'], 0, ',', ' ') . ' F' : '—';
        $apM = isset($apres['montant_total'])
            ? number_format((int)$apres['montant_total'], 0, ',', ' ') . ' F' : '—';

        $resume = "
        <div class='row g-2 mt-1'>
          <div class='col-md-6'>
            <div class='p-2 bg-danger bg-opacity-10 rounded border border-danger'>
              <small class='text-danger fw-bold'>AVANT</small><br>
              {$avC} — {$avM}
            </div>
          </div>
          <div class='col-md-6'>
            <div class='p-2 bg-success bg-opacity-10 rounded border border-success'>
              <small class='text-success fw-bold'>APRÈS</small><br>
              {$apC} — {$apM}
            </div>
          </div>
        </div>";

    } elseif ($mod['type_recu'] === 'examen') {
        $nbAv = isset($avant['examens']) ? count($avant['examens']) : '—';
        $nbAp = isset($apres['examens']) ? count($apres['examens']) : '—';
        $apM  = isset($apres['montant_total'])
            ? number_format((int)$apres['montant_total'], 0, ',', ' ') . ' F' : '—';

        // Noms des examens après modification
        $nomsAp = '';
        if (!empty($apres['examens'])) {
            $nomsAp = '<br><small class="text-muted">'
                . implode(', ', array_map(
                    fn($e) => htmlspecialchars($e['nom'] ?? '', ENT_QUOTES, 'UTF-8'),
                    $apres['examens']
                ))
                . '</small>';
        }

        $resume = "
        <div class='row g-2 mt-1'>
          <div class='col-md-6'>
            <div class='p-2 bg-danger bg-opacity-10 rounded border border-danger'>
              <small class='text-danger fw-bold'>AVANT</small><br>
              {$nbAv} examen(s)
            </div>
          </div>
          <div class='col-md-6'>
            <div class='p-2 bg-success bg-opacity-10 rounded border border-success'>
              <small class='text-success fw-bold'>APRÈS</small><br>
              {$nbAp} examen(s) — {$apM}{$nomsAp}
            </div>
          </div>
        </div>";

    } elseif ($mod['type_recu'] === 'pharmacie') {
        $nbAv = isset($avant['lignes']) ? count($avant['lignes']) : '—';
        $nbAp = isset($apres['lignes']) ? count($apres['lignes']) : '—';
        $apM  = isset($apres['montant_total'])
            ? number_format((int)$apres['montant_total'], 0, ',', ' ') . ' F' : '—';

        // Noms des produits après modification
        $nomsAp = '';
        if (!empty($apres['lignes'])) {
            $lignesStr = array_map(
                fn($l) => htmlspecialchars($l['nom'] ?? '', ENT_QUOTES, 'UTF-8')
                          . ' ×' . (int)($l['quantite'] ?? 0),
                $apres['lignes']
            );
            $nomsAp = '<br><small class="text-muted">' . implode(', ', $lignesStr) . '</small>';
        }

        $resume = "
        <div class='row g-2 mt-1'>
          <div class='col-md-6'>
            <div class='p-2 bg-danger bg-opacity-10 rounded border border-danger'>
              <small class='text-danger fw-bold'>AVANT</small><br>
              {$nbAv} produit(s)
            </div>
          </div>
          <div class='col-md-6'>
            <div class='p-2 bg-success bg-opacity-10 rounded border border-success'>
              <small class='text-success fw-bold'>APRÈS</small><br>
              {$nbAp} produit(s) — {$apM}{$nomsAp}
            </div>
          </div>
        </div>";
    }

    $html .= "
    <div class='border rounded p-3 mb-3 shadow-sm'>
      <div class='d-flex justify-content-between align-items-start flex-wrap gap-2'>
        <div>
          {$typeLabel}
          <span class='ms-2 fw-bold'>{$user}</span>
          <span class='text-muted small'>({$role})</span>
        </div>
        <small class='text-muted'>
          <i class='bi bi-clock me-1'></i>{$date}
        </small>
      </div>
      <div class='mt-2'>
        <i class='bi bi-chat-left-text me-1 text-primary'></i>
        <strong>Motif :</strong> {$motif}
      </div>
      {$resume}
    </div>";
}

// ── Réponse JSON ──────────────────────────────────────────────────────────────
echo json_encode(
    ['html' => $html, 'error' => null],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
exit;
