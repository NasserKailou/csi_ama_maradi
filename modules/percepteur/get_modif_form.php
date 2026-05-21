<?php
/**
 * modules/percepteur/get_modif_form.php
 * Endpoint AJAX — retourne JSON avec le HTML du formulaire de modification
 * Accès direct (pas via le routeur index.php)
 */

// ── Bootstrap : charger l'environnement sans layout ───────────────────────
define('AJAX_REQUEST', true);   // flag pour empêcher header.php de s'afficher si inclus

// Remonter jusqu'à la racine du projet pour charger config + autoload
$root = dirname(__DIR__, 2);    // modules/percepteur → racine

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $root);
}

// Charger config et autoload (adapter le chemin si différent dans ton projet)
require_once $root . '/config/config.php';
require_once $root . '/core/autoload.php';

// Toujours envoyer du JSON
header('Content-Type: application/json; charset=utf-8');

// ── Sécurité : session requise ─────────────────────────────────────────────
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['html' => null, 'error' => 'Non authentifié']);
    exit;
}

// Vérification du rôle
$allowedRoles = ['percepteur', 'admin', 'comptable'];
if (!in_array(Session::get('user_role'), $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['html' => null, 'error' => 'Accès refusé']);
    exit;
}

// ── Paramètres ─────────────────────────────────────────────────────────────
$pdo    = Database::getInstance();
$recuId = (int)($_GET['recu_id'] ?? 0);
$type   = trim($_GET['type'] ?? '');

if (!$recuId || !in_array($type, ['consultation', 'examen', 'pharmacie'], true)) {
    http_response_code(400);
    echo json_encode(['html' => null, 'error' => 'Paramètres invalides']);
    exit;
}

// ── Récupérer le reçu ──────────────────────────────────────────────────────
$stmtRecu = $pdo->prepare("
    SELECT r.*, p.nom AS patient_nom, p.telephone, p.est_orphelin
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.id = :id AND r.isDeleted = 0
    LIMIT 1
");
$stmtRecu->execute([':id' => $recuId]);
$recu = $stmtRecu->fetch(PDO::FETCH_ASSOC);

if (!$recu) {
    http_response_code(404);
    echo json_encode(['html' => null, 'error' => 'Reçu introuvable']);
    exit;
}

$numeroRecu = (int)$recu['numero_recu'];
$isOrphelin = (int)$recu['est_orphelin'] === 1;

// ── Helper échappement ─────────────────────────────────────────────────────
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function fmt(int $v): string {
    return number_format($v, 0, ',', ' ');
}

$html = '';

// ══════════════════════════════════════════════════════════════════════════
//  CONSULTATION
// ══════════════════════════════════════════════════════════════════════════
if ($type === 'consultation') {

    $avecCarnetActuel = (int)($recu['avec_carnet'] ?? 0);
    $montantActuel    = (int)$recu['montant_total'];

    $checkedAvec = $avecCarnetActuel  ? 'checked' : '';
    $checkedSans = !$avecCarnetActuel ? 'checked' : '';

    $html = <<<HTML
<div class="alert alert-light border mb-3">
    <div class="row">
        <div class="col-sm-6"><strong>Patient :</strong> {$recu['patient_nom']}</div>
        <div class="col-sm-6"><strong>Tél :</strong> {$recu['telephone']}</div>
        <div class="col-12 mt-1">
            <strong>Montant actuel :</strong>
            <span class="badge bg-secondary">{$montantActuel} F</span>
        </div>
    </div>
</div>
<label class="form-label fw-bold">Modifier le type de consultation</label>
<div class="row g-2">
    <div class="col-md-6">
        <div class="form-check border rounded p-3 h-100">
            <input class="form-check-input" type="radio"
                   name="modif_avec_carnet" id="modifAvec" value="1" {$checkedAvec}>
            <label class="form-check-label fw-semibold" for="modifAvec">
                Consultation + Carnet de Soins
                <div class="text-success">300 F + 100 F = <strong>400 F</strong></div>
            </label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check border rounded p-3 h-100">
            <input class="form-check-input" type="radio"
                   name="modif_avec_carnet" id="modifSans" value="0" {$checkedSans}>
            <label class="form-check-label fw-semibold" for="modifSans">
                Consultation sans Carnet
                <div class="text-success"><strong>300 F</strong></div>
            </label>
        </div>
    </div>
</div>
HTML;
}

// ══════════════════════════════════════════════════════════════════════════
//  EXAMEN
// ══════════════════════════════════════════════════════════════════════════
elseif ($type === 'examen') {

    $stmtEx = $pdo->prepare("
        SELECT examen_id FROM recu_examens
        WHERE recu_id = :rid AND isDeleted = 0
    ");
    $stmtEx->execute([':rid' => $recuId]);
    $dejaCoches = array_column($stmtEx->fetchAll(PDO::FETCH_ASSOC), 'examen_id');

    $tousExamens = $pdo->query("
        SELECT id, libelle, cout_total
        FROM examens
        WHERE isDeleted = 0
        ORDER BY libelle
    ")->fetchAll(PDO::FETCH_ASSOC);

    $numFormate  = str_pad($numeroRecu, 5, '0', STR_PAD_LEFT);
    $badgeOrph   = $isOrphelin
        ? '<span class="badge ms-2" style="background:#7b1fa2;">ORPHELIN</span>'
        : '';
    $bannerOrph  = $isOrphelin ? '
        <div class="alert alert-warning mb-2">
            <i class="bi bi-gift me-1"></i>
            <strong>Orphelin – Gratuité totale.</strong> Examens à 0 F encaissé.
        </div>' : '';

    $lignesExamens = '';
    foreach ($tousExamens as $e) {
        $checked   = in_array((int)$e['id'], array_map('intval', $dejaCoches)) ? 'checked' : '';
        $highlight = $checked ? 'border-success bg-light' : '';
        $prixBadge = $isOrphelin
            ? '<s>' . fmt((int)$e['cout_total']) . ' F</s> <strong>0 F</strong>'
            : fmt((int)$e['cout_total']) . ' F';
        $libelle   = e($e['libelle']);
        $id        = (int)$e['id'];
        $cout      = (int)$e['cout_total'];

        $lignesExamens .= <<<HTML
<div class="col-md-6">
    <div class="form-check border rounded p-2 {$highlight}">
        <input class="form-check-input modif-examen-chk" type="checkbox"
               value="{$id}" id="modifEx{$id}"
               data-cout="{$cout}" {$checked}>
        <label class="form-check-label w-100" for="modifEx{$id}">
            <strong>{$libelle}</strong>
            <span class="badge float-end" style="background:#e65100;">{$prixBadge}</span>
        </label>
    </div>
</div>
HTML;
    }

    $html = <<<HTML
<div class="alert alert-light border mb-3">
    <strong>Patient :</strong> {$recu['patient_nom']}
    &nbsp;|&nbsp;
    <strong>Reçu N° :</strong> #{$numFormate}
    {$badgeOrph}
</div>
{$bannerOrph}
<div class="row g-2">{$lignesExamens}</div>
<div class="mt-3 p-2 bg-light rounded d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Sous-total :</span>
    <span class="fw-bold fs-5" id="modifSousTotal">0 F</span>
</div>
<script>
(function(){
    function recalc(){
        var t=0;
        document.querySelectorAll('.modif-examen-chk:checked').forEach(function(c){
            t+=parseInt(c.dataset.cout)||0;
        });
        var el=document.getElementById('modifSousTotal');
        if(el) el.textContent=new Intl.NumberFormat('fr-FR').format(t)+' F';
    }
    document.querySelectorAll('.modif-examen-chk').forEach(function(c){
        c.addEventListener('change',recalc);
    });
    recalc();
})();
</script>
HTML;
}

// ══════════════════════════════════════════════════════════════════════════
//  PHARMACIE
// ══════════════════════════════════════════════════════════════════════════
elseif ($type === 'pharmacie') {

    $stmtLignes = $pdo->prepare("
        SELECT rl.produit_id, rl.quantite, rl.prix_unitaire,
               pp.nom, pp.forme, pp.stock_actuel
        FROM recu_lignes_pharmacie rl
        JOIN produits_pharmacie pp ON pp.id = rl.produit_id
        WHERE rl.recu_id = :rid AND rl.isDeleted = 0
    ");
    $stmtLignes->execute([':rid' => $recuId]);

    $qtesActuelles = [];
    foreach ($stmtLignes->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $qtesActuelles[(int)$l['produit_id']] = (int)$l['quantite'];
    }

    $tousProds = $pdo->query("
        SELECT id, nom, forme, prix_unitaire, stock_actuel,
               CASE
                   WHEN stock_actuel = 0 THEN 'rupture'
                   WHEN date_peremption IS NOT NULL
                        AND date_peremption <= CURDATE() THEN 'perime'
                   ELSE 'ok'
               END AS statut
        FROM produits_pharmacie
        WHERE isDeleted = 0
        ORDER BY nom
    ")->fetchAll(PDO::FETCH_ASSOC);

    $numFormate = str_pad($numeroRecu, 5, '0', STR_PAD_LEFT);
    $badgeOrph  = $isOrphelin
        ? '<span class="badge ms-2" style="background:#7b1fa2;">ORPHELIN</span>'
        : '';
    $bannerOrph = $isOrphelin ? '
        <div class="alert alert-warning mb-2">
            <i class="bi bi-gift me-1"></i>
            <strong>Orphelin – stock mis à jour, montant = 0 F.</strong>
        </div>' : '';

    $lignesProduits = '';
    foreach ($tousProds as $p) {
        if ($p['statut'] !== 'ok') continue;
        $qteActuelle = $qtesActuelles[(int)$p['id']] ?? 0;
        $totalLigne  = $qteActuelle * (int)$p['prix_unitaire'];
        $highlight   = $qteActuelle > 0 ? 'table-success' : '';
        $totalAff    = ($isOrphelin && $qteActuelle > 0)
            ? '<s>' . fmt($totalLigne) . ' F</s> <strong>0 F</strong>'
            : fmt($totalLigne) . ' F';
        $nom    = e($p['nom']);
        $forme  = e($p['forme']);
        $id     = (int)$p['id'];
        $prix   = (int)$p['prix_unitaire'];
        $stock  = (int)$p['stock_actuel'];
        $prixAff = fmt($prix);
        $stockBadge = $stock > 0 ? 'bg-success' : 'bg-danger';

        $lignesProduits .= <<<HTML
<tr class="{$highlight}">
    <td>
        <strong class="small">{$nom}</strong>
        <div class="text-muted" style="font-size:.75rem;">{$forme}</div>
    </td>
    <td class="text-center">
        <span class="badge {$stockBadge}">{$stock}</span>
    </td>
    <td class="text-end small">{$prixAff} F</td>
    <td class="text-center">
        <input type="number"
               class="form-control form-control-sm modif-produit-qte text-center"
               min="0" max="{$stock}" value="{$qteActuelle}"
               data-id="{$id}" data-prix="{$prix}" data-nom="{$nom}">
    </td>
    <td class="text-end modif-ligne-total">{$totalAff}</td>
</tr>
HTML;
    }

    $html = <<<HTML
<div class="alert alert-light border mb-2">
    <strong>Patient :</strong> {$recu['patient_nom']}
    &nbsp;|&nbsp;
    <strong>Reçu N° :</strong> #{$numFormate}
    {$badgeOrph}
</div>
{$bannerOrph}
<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead class="table-light">
        <tr>
            <th>Produit</th>
            <th class="text-center">Stock</th>
            <th class="text-end">P.U.</th>
            <th class="text-center" style="width:110px;">Qté</th>
            <th class="text-end">Total ligne</th>
        </tr>
    </thead>
    <tbody>{$lignesProduits}</tbody>
</table>
</div>
<div class="p-2 bg-light rounded d-flex justify-content-between align-items-center mt-2">
    <span>
        <strong>Produits :</strong>
        <span id="modifNbProduits" class="badge bg-secondary">0</span> / 15 max
    </span>
    <span class="fw-bold">Total : <span id="modifTotalPharma">0 F</span></span>
</div>
HTML;
}

// ── Réponse finale ─────────────────────────────────────────────────────────
echo json_encode([
    'html'        => $html,
    'numero_recu' => $numeroRecu,
    'error'       => null
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
