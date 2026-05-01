<?php
/**
 * modules/percepteur/ajax_get_modif_form.php
 * Endpoint AJAX – retourne le formulaire de modification d'un reçu (JSON)
 */
ob_start();

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__FILE__, 3));
}

set_error_handler(function(int $no, string $msg, string $file, int $line): bool {
    if (ob_get_level()) ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['html' => null, 'error' => "PHP [$no]: $msg (ligne $line)"]);
    exit;
});
set_exception_handler(function(Throwable $e): void {
    if (ob_get_level()) ob_end_clean();
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['html' => null, 'error' => get_class($e).': '.$e->getMessage().' (L.'.$e->getLine().')']);
    exit;
});

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['html' => null, 'error' => 'Non authentifié']);
    exit;
}
if (!in_array(Session::getRole(), ['percepteur','admin','comptable'], true)) {
    http_response_code(403);
    echo json_encode(['html' => null, 'error' => 'Accès refusé']);
    exit;
}

// ── Paramètres ────────────────────────────────────────────────────────────────
$recuId = (int)($_GET['recu_id'] ?? 0);
$type   = trim($_GET['type']    ?? '');

if ($recuId <= 0 || !in_array($type, ['consultation','examen','pharmacie'], true)) {
    http_response_code(400);
    echo json_encode(['html' => null, 'error' => 'Paramètres invalides']);
    exit;
}

// ── Chargement du reçu ────────────────────────────────────────────────────────
$pdo = Database::getInstance();

$stmtRecu = $pdo->prepare("
    SELECT r.id,
           r.numero_recu,
           r.type_recu,
           r.type_patient,
           r.montant_total,
           r.montant_encaisse,
           p.nom        AS patient_nom,
           p.telephone  AS patient_tel,
           p.est_orphelin
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.id = :id
      AND r.isDeleted = 0
    LIMIT 1
");
$stmtRecu->execute([':id' => $recuId]);
$recu = $stmtRecu->fetch(PDO::FETCH_ASSOC);

if (!$recu) {
    http_response_code(404);
    echo json_encode(['html' => null, 'error' => 'Reçu introuvable']);
    exit;
}

// Orphelin = soit via patients.est_orphelin, soit via recus.type_patient
$isOrphelin = (int)($recu['est_orphelin'] ?? 0) === 1
           || $recu['type_patient'] === 'orphelin';

$numeroRecu = str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
$html       = '';

// ── CONSULTATION ──────────────────────────────────────────────────────────────
if ($type === 'consultation') {
    $montant    = (int)($recu['montant_total'] ?? 0);
    // Détecter le type actuel par le montant (400 F = avec carnet, 300 F = sans carnet)
    $avecCarnet = ($montant === 400) ? 1 : 0;

    $html = '
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label text-muted small">Patient</label>
        <p class="fw-bold mb-0">' . htmlspecialchars($recu['patient_nom'], ENT_QUOTES, 'UTF-8') . '</p>
      </div>
      <div class="col-12">
        <label class="form-label text-muted small">Montant actuel</label>
        <p class="fw-bold mb-0">' . number_format($montant, 0, ',', ' ') . ' F CFA</p>
      </div>
      <div class="col-12">
        <label class="form-label fw-bold">Modifier le type</label>
        <div class="d-flex gap-3 mt-1">
          <div class="form-check">
            <input class="form-check-input" type="radio"
                   name="modif_avec_carnet" id="modif_avc1" value="1"'
                   . ($avecCarnet ? ' checked' : '') . '>
            <label class="form-check-label" for="modif_avc1">
              Avec carnet &mdash; <strong>400 F</strong>
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio"
                   name="modif_avec_carnet" id="modif_avc0" value="0"'
                   . (!$avecCarnet ? ' checked' : '') . '>
            <label class="form-check-label" for="modif_avc0">
              Sans carnet &mdash; <strong>300 F</strong>
            </label>
          </div>
        </div>
      </div>
    </div>';
}

// ── EXAMEN ────────────────────────────────────────────────────────────────────
elseif ($type === 'examen') {

    // Examens actuellement liés à ce reçu — table : lignes_examen
    $stmtEx = $pdo->prepare("
        SELECT examen_id
        FROM lignes_examen
        WHERE recu_id = :rid
          AND isDeleted = 0
    ");
    $stmtEx->execute([':rid' => $recuId]);
    $existants = array_column($stmtEx->fetchAll(PDO::FETCH_ASSOC), 'examen_id');

    // Catalogue complet — colonne libelle (pas nom)
    $catalogue = $pdo->query("
        SELECT id, libelle, cout_total
        FROM examens
        WHERE isDeleted = 0
        ORDER BY libelle
    ")->fetchAll(PDO::FETCH_ASSOC);

    $rows = '';
    foreach ($catalogue as $ex) {
        $checked = in_array((int)$ex['id'], array_map('intval', $existants)) ? ' checked' : '';
        $prix    = $isOrphelin ? 0 : (int)$ex['cout_total'];
        $rows .= '
        <tr>
          <td class="text-center" style="width:40px">
            <input class="form-check-input modif-examen-chk" type="checkbox"
                   name="examens[]"
                   value="' . (int)$ex['id'] . '"
                   data-prix="' . $prix . '"'
                   . $checked . '>
          </td>
          <td>' . htmlspecialchars($ex['libelle'], ENT_QUOTES, 'UTF-8') . '</td>
          <td class="text-end text-nowrap">'
              . number_format((int)$ex['cout_total'], 0, ',', ' ') . ' F</td>
        </tr>';
    }

    $html = '
    <p class="text-muted small mb-2">Cochez les examens à facturer :</p>
    <div class="table-responsive" style="max-height:320px;overflow-y:auto">
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light sticky-top">
          <tr><th></th><th>Examen</th><th class="text-end">Prix</th></tr>
        </thead>
        <tbody>' . $rows . '</tbody>
      </table>
    </div>
    <div class="mt-2 fw-bold text-end fs-6" id="modifExamenTotal">Total : 0 F CFA</div>';
}

// ── PHARMACIE ─────────────────────────────────────────────────────────────────
elseif ($type === 'pharmacie') {

    // Produits actuellement liés — table : lignes_pharmacie
    $stmtProd = $pdo->prepare("
        SELECT produit_id, quantite
        FROM lignes_pharmacie
        WHERE recu_id = :rid
          AND isDeleted = 0
    ");
    $stmtProd->execute([':rid' => $recuId]);
    $existants = [];
    foreach ($stmtProd->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existants[(int)$row['produit_id']] = (int)$row['quantite'];
    }

    // Catalogue pharmacie — pas de colonne actif, filtrer isDeleted = 0
    $catalogue = $pdo->query("
        SELECT id, nom, forme, prix_unitaire, stock_actuel
        FROM produits_pharmacie
        WHERE isDeleted = 0
        ORDER BY nom
    ")->fetchAll(PDO::FETCH_ASSOC);

    $rows  = '';
    $count = 0;
    foreach ($catalogue as $prod) {
        if ($count >= 15) break;
        $qteActuelle = $existants[(int)$prod['id']] ?? 0;
        // Stock disponible = stock actuel + quantité déjà utilisée dans ce reçu
        $stockDispo  = (int)$prod['stock_actuel'] + $qteActuelle;
        $prix        = $isOrphelin ? 0 : (int)$prod['prix_unitaire'];

        $rows .= '
        <tr>
          <td>' . htmlspecialchars($prod['nom'], ENT_QUOTES, 'UTF-8')
              . ' <small class="text-muted">(' . htmlspecialchars($prod['forme'], ENT_QUOTES, 'UTF-8') . ')</small></td>
          <td class="text-end text-nowrap">'
              . number_format((int)$prod['prix_unitaire'], 0, ',', ' ') . ' F</td>
          <td>
            <input type="number"
                   class="form-control form-control-sm modif-produit-qte"
                   data-id="' . (int)$prod['id'] . '"
                   data-prix="' . $prix . '"
                   value="' . $qteActuelle . '"
                   min="0" max="' . $stockDispo . '"
                   style="width:75px">
          </td>
          <td class="text-end text-nowrap modif-ligne-total">0 F</td>
        </tr>';
        $count++;
    }

    $html = '
    <p class="text-muted small mb-2">Saisissez les quantités (0 = supprimer, max 15 produits) :</p>
    <div class="table-responsive" style="max-height:320px;overflow-y:auto">
      <table class="table table-sm align-middle">
        <thead class="table-light sticky-top">
          <tr>
            <th>Produit</th>
            <th class="text-end">PU</th>
            <th style="width:85px">Qté</th>
            <th class="text-end">Total ligne</th>
          </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
      </table>
    </div>
    <div class="mt-2 fw-bold text-end fs-6" id="modifPharmaTotal">Total : 0 F CFA</div>';
}

// ── Réponse JSON ──────────────────────────────────────────────────────────────
echo json_encode(
    ['html' => $html, 'numero_recu' => $numeroRecu, 'error' => null],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
exit;
