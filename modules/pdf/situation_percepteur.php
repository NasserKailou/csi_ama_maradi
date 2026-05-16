<?php
/**
 * Génération Situation Percepteur – PDF (impression navigateur)
 * AMÉLIORÉ : filtre type_rapport (tout / consultation / examen / pharmacie)
 *             + détail produits pharmacie
 */
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('admin');

$pdo          = Database::getInstance();
$percepteurId = (int)($_GET['percepteur_id'] ?? 0);
$mode         = ($_GET['mode'] ?? 'jour') === 'periode' ? 'periode' : 'jour';
$typeRapport  = $_GET['type_rapport'] ?? 'tout';
$allowedTypes = ['tout','consultation','examen','pharmacie'];
if (!in_array($typeRapport, $allowedTypes)) $typeRapport = 'tout';

if ($mode === 'jour') {
    $dateDebut = date('Y-m-d');
    $dateFin   = date('Y-m-d');
} else {
    $dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
    $dateFin   = $_GET['date_fin']   ?? date('Y-m-d');
}

if (!$percepteurId) {
    die('<p class="text-danger">ID percepteur manquant.</p>');
}

// ── Infos percepteur ──────────────────────────────────────────────────────────
$stmtP = $pdo->prepare("
    SELECT nom, prenom, login
    FROM   utilisateurs
    WHERE  id = :id AND isDeleted = 0
    LIMIT  1
");
$stmtP->execute([':id' => $percepteurId]);
$percepteur = $stmtP->fetch();
if (!$percepteur) { die('<p class="text-danger">Percepteur introuvable.</p>'); }

// ── Config centre ─────────────────────────────────────────────────────────────
$cfgRows = $pdo->query("SELECT cle, valeur FROM config_systeme WHERE isDeleted=0")->fetchAll();
$cfg = [];
foreach ($cfgRows as $r) { $cfg[$r['cle']] = $r['valeur']; }
$nomCentre = $cfg['nom_centre']   ?? 'CSI AMA Maradi';
$adresse   = $cfg['adresse']      ?? 'Maradi – Niger';
$telephone = $cfg['telephone']    ?? '';
$piedPage  = $cfg['pied_de_page'] ?? 'Merci de votre confiance.';

// Logo
$logoHtml = '';
$logoFile = $cfg['logo_filename'] ?? '';
if ($logoFile && file_exists(ROOT_PATH . '/uploads/logos/' . $logoFile)) {
    $logoHtml = '<img src="' . ROOT_PATH . '/uploads/logos/' . $logoFile
              . '" alt="Logo" style="max-height:60px;">';
} elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_csi.png')) {
    $logoHtml = '<img src="' . ROOT_PATH . '/uploads/logos/logo_csi.png"'
              . ' alt="Logo" style="max-height:60px;">';
}

// ── Clause filtre type_recu ───────────────────────────────────────────────────
$typeClause = $typeRapport === 'tout' ? '' : " AND r.type_recu = " . $pdo->quote($typeRapport);

// ── Reçus du percepteur sur la période ───────────────────────────────────────
$sql = "
    SELECT r.id,
           r.numero_recu,
           r.type_recu,
           r.type_patient,
           r.montant_total,
           r.montant_encaisse,
           r.whendone,
           p.nom         AS patient_nom,
           p.telephone   AS patient_tel,
           p.sexe        AS patient_sexe,
           p.est_orphelin
    FROM   recus r
    JOIN   patients p ON p.id = r.patient_id
    WHERE  r.whodone   = :uid
      AND  r.isDeleted = 0
      AND  DATE(r.whendone) BETWEEN :deb AND :fin
      {$typeClause}
    ORDER  BY r.whendone ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $percepteurId, ':deb' => $dateDebut, ':fin' => $dateFin]);
$recus = $stmt->fetchAll();

// ── Pour pharmacie : récupérer détail produits par reçu ─────────────────────
$detailPharmacieByRecu = [];
if ($typeRapport === 'pharmacie' || $typeRapport === 'tout') {
    $recusIds = array_filter(array_column($recus, 'id'), fn($v) => $v > 0);
    if ($recusIds) {
        $placeholders = implode(',', array_map('intval', $recusIds));
        $stmtLP = $pdo->query("
            SELECT lp.recu_id, lp.nom, lp.forme, lp.quantite, lp.prix_unitaire, lp.total_ligne
            FROM lignes_pharmacie lp
            WHERE lp.recu_id IN ({$placeholders}) AND lp.isDeleted=0
            ORDER BY lp.nom
        ");
        foreach ($stmtLP->fetchAll() as $lp) {
            $detailPharmacieByRecu[$lp['recu_id']][] = $lp;
        }
    }
}

// ── Totaux globaux ────────────────────────────────────────────────────────────
$totalEncaisse = 0;
$totalGratuit  = 0;
$nbRecus       = count($recus);

$byType        = [];
$bySexe        = ['M' => ['nb'=>0,'encaisse'=>0,'label'=>'Masculin'], 'F' => ['nb'=>0,'encaisse'=>0,'label'=>'Féminin']];
$byTypePatient = [
    'normal'       => ['nb'=>0,'encaisse'=>0,'label'=>'Normal'],
    'orphelin'     => ['nb'=>0,'encaisse'=>0,'label'=>'Orphelin'],
    'acte_gratuit' => ['nb'=>0,'encaisse'=>0,'label'=>'Acte Gratuit'],
];

foreach ($recus as $r) {
    $totalEncaisse += (int)$r['montant_encaisse'];
    if (in_array($r['type_patient'], ['orphelin','acte_gratuit'])) {
        $totalGratuit += (int)$r['montant_total'];
    }
    $t = $r['type_recu'];
    if (!isset($byType[$t])) $byType[$t] = ['nb'=>0,'total'=>0];
    $byType[$t]['nb']++;
    $byType[$t]['total'] += (int)$r['montant_encaisse'];

    $sexe = $r['patient_sexe'] === 'F' ? 'F' : 'M';
    $bySexe[$sexe]['nb']++;
    $bySexe[$sexe]['encaisse'] += (int)$r['montant_encaisse'];

    $tp = $r['type_patient'];
    if (isset($byTypePatient[$tp])) {
        $byTypePatient[$tp]['nb']++;
        $byTypePatient[$tp]['encaisse'] += (int)$r['montant_encaisse'];
    }
}

// ── Récap produits pharmacie (global, pour tout ou pharmacie) ────────────────
$recapPharmacieProduits = [];
if ($typeRapport === 'pharmacie' || $typeRapport === 'tout') {
    foreach ($detailPharmacieByRecu as $rid => $lignes) {
        foreach ($lignes as $lp) {
            $key = $lp['nom'] . '||' . $lp['forme'];
            if (!isset($recapPharmacieProduits[$key])) {
                $recapPharmacieProduits[$key] = [
                    'nom'    => $lp['nom'],
                    'forme'  => $lp['forme'],
                    'qte'    => 0,
                    'total'  => 0,
                ];
            }
            $recapPharmacieProduits[$key]['qte']   += (int)$lp['quantite'];
            $recapPharmacieProduits[$key]['total']  += (int)$lp['total_ligne'];
        }
    }
    usort($recapPharmacieProduits, fn($a,$b) => $b['qte'] - $a['qte']);
}

// ── Labels ────────────────────────────────────────────────────────────────────
$modeLabel = $mode === 'jour'
    ? 'Situation Journalière du ' . date('d/m/Y')
    : 'Situation du ' . date('d/m/Y', strtotime($dateDebut))
      . ' au ' . date('d/m/Y', strtotime($dateFin));

$typeRapportLabels = [
    'tout'         => 'Toutes opérations',
    'consultation' => 'Consultations uniquement',
    'examen'       => 'Examens uniquement',
    'pharmacie'    => 'Pharmacie uniquement',
];

$typeLabels       = ['consultation'=>'Consultation','examen'=>'Examen','pharmacie'=>'Pharmacie'];
$typePatientLabels = [
    'normal'       => ['label'=>'Normal',      'color'=>'#1565c0','bg'=>'#e3f2fd'],
    'orphelin'     => ['label'=>'Orphelin',     'color'=>'#6a1b9a','bg'=>'#f3e5f5'],
    'acte_gratuit' => ['label'=>'Acte Gratuit', 'color'=>'#c62828','bg'=>'#ffebee'],
];
$sexeLabels = ['M'=>['label'=>'M','color'=>'#1565c0'],'F'=>['label'=>'F','color'=>'#c62828']];

// ── HTML lignes du tableau détail ─────────────────────────────────────────────
$lignesHtml = '';
foreach ($recus as $i => $r) {
    $isGratuit = in_array($r['type_patient'], ['orphelin','acte_gratuit']);
    $numFmt    = '#' . str_pad($r['numero_recu'], 5, '0', STR_PAD_LEFT);
    $heure     = date('H:i', strtotime($r['whendone']));
    $dateAff   = date('d/m', strtotime($r['whendone']));
    $typeAff   = $typeLabels[$r['type_recu']] ?? ucfirst($r['type_recu']);

    $montant = $isGratuit
        ? '<span style="color:#c62828;font-weight:bold;">0 F <em style="font-size:8pt;">(gratuit)</em></span>'
        : '<strong>' . number_format((int)$r['montant_encaisse'], 0, ',', ' ') . ' F</strong>';

    $tpInfo  = $typePatientLabels[$r['type_patient']] ?? ['label'=>$r['type_patient'],'color'=>'#555','bg'=>'#eee'];
    $tpBadge = "<span style='background:{$tpInfo['bg']};color:{$tpInfo['color']};padding:1px 5px;"
             . "border-radius:3px;font-size:8pt;font-weight:bold;'>{$tpInfo['label']}</span>";

    $sexe      = $r['patient_sexe'] === 'F' ? 'F' : 'M';
    $sexeColor = $sexe === 'F' ? '#c62828' : '#1565c0';
    $sexeBadge = "<span style='color:{$sexeColor};font-weight:bold;font-size:9pt;'>{$sexe}</span>";

    $rowBg = $i % 2 ? 'background:#f9f9f9;' : '';

    $lignesHtml .= "
    <tr style='border-bottom:1px solid #e0e0e0;{$rowBg}'>
        <td style='padding:5px 6px;text-align:center;color:#888;font-size:9pt;'>" . ($i + 1) . "</td>
        <td style='padding:5px 6px;'><b>{$numFmt}</b></td>
        <td style='padding:5px 6px;font-size:9pt;'>{$dateAff} {$heure}</td>
        <td style='padding:5px 6px;'>" . htmlspecialchars($r['patient_nom'])
        . "<br><span style='font-size:8pt;color:#666;'>{$r['patient_tel']}</span></td>
        <td style='padding:5px 6px;text-align:center;'>{$sexeBadge}</td>
        <td style='padding:5px 6px;text-align:center;'>
            <span style='background:#e8f5e9;color:#2e7d32;padding:2px 5px;
                         border-radius:3px;font-size:8pt;'>{$typeAff}</span>
        </td>
        <td style='padding:5px 6px;text-align:center;'>{$tpBadge}</td>
        <td style='padding:5px 6px;text-align:right;'>{$montant}</td>
    </tr>";

    // Détail pharmacie sous le reçu si disponible
    if (($typeRapport === 'pharmacie' || $typeRapport === 'tout') && !empty($detailPharmacieByRecu[$r['id']])) {
        $lignesHtml .= "<tr style='background:#e0f7fa;{$rowBg}'>
            <td colspan='8' style='padding:3px 20px;font-size:8pt;'>
                <em style='color:#006064;font-weight:bold;'>Produits pharmacie de ce reçu :</em>
                <table style='width:100%;border-collapse:collapse;margin-top:3px;'>
                    <tr style='background:#b2ebf2;'>
                        <th style='padding:2px 6px;text-align:left;font-size:8pt;'>Produit</th>
                        <th style='padding:2px 6px;text-align:left;font-size:8pt;'>Forme</th>
                        <th style='padding:2px 6px;text-align:center;font-size:8pt;'>Qté</th>
                        <th style='padding:2px 6px;text-align:right;font-size:8pt;'>P.U.</th>
                        <th style='padding:2px 6px;text-align:right;font-size:8pt;'>Total</th>
                    </tr>";
        foreach ($detailPharmacieByRecu[$r['id']] as $lp) {
            $lignesHtml .= "<tr>
                <td style='padding:2px 6px;font-size:8pt;'>" . htmlspecialchars($lp['nom']) . "</td>
                <td style='padding:2px 6px;font-size:8pt;color:#888;'>" . htmlspecialchars($lp['forme']) . "</td>
                <td style='padding:2px 6px;text-align:center;font-size:8pt;font-weight:bold;'>" . (int)$lp['quantite'] . "</td>
                <td style='padding:2px 6px;text-align:right;font-size:8pt;'>" . number_format((int)$lp['prix_unitaire'],0,',',' ') . " F</td>
                <td style='padding:2px 6px;text-align:right;font-size:8pt;font-weight:bold;'>" . number_format((int)$lp['total_ligne'],0,',',' ') . " F</td>
            </tr>";
        }
        $lignesHtml .= "</table></td></tr>";
    }
}

if (!$lignesHtml) {
    $lignesHtml = "<tr><td colspan='8' style='text-align:center;padding:20px;color:#888;'>"
                . "Aucune opération sur cette période.</td></tr>";
}

// ── Récap par type de reçu ───────────────────────────────────────────────────
$recapHtml = '';
foreach ($byType as $type => $info) {
    $lb = $typeLabels[$type] ?? ucfirst($type);
    $recapHtml .= "<tr>
        <td style='padding:4px 8px;'>{$lb}</td>
        <td style='padding:4px 8px;text-align:center;'>{$info['nb']}</td>
        <td style='padding:4px 8px;text-align:right;font-weight:bold;'>"
        . number_format($info['total'], 0, ',', ' ') . " F</td>
    </tr>";
}

// ── Récap sexe ────────────────────────────────────────────────────────────────
$recapSexeHtml = '';
foreach ($bySexe as $sexeKey => $info) {
    if ($info['nb'] === 0) continue;
    $color = $sexeKey === 'F' ? '#c62828' : '#1565c0';
    $recapSexeHtml .= "<tr>
        <td style='padding:4px 8px;'>
            <span style='color:{$color};font-weight:bold;font-size:10pt;'>{$sexeKey}</span>
            &nbsp;{$info['label']}
        </td>
        <td style='padding:4px 8px;text-align:center;'>{$info['nb']}</td>
        <td style='padding:4px 8px;text-align:right;font-weight:bold;color:{$color};'>"
        . number_format($info['encaisse'], 0, ',', ' ') . " F</td>
    </tr>";
}

// ── Récap type patient ────────────────────────────────────────────────────────
$recapTypePatientHtml = '';
foreach ($byTypePatient as $tp => $info) {
    if ($info['nb'] === 0) continue;
    $tpInfo = $typePatientLabels[$tp];
    $recapTypePatientHtml .= "<tr>
        <td style='padding:4px 8px;'>
            <span style='background:{$tpInfo['bg']};color:{$tpInfo['color']};
                         padding:1px 6px;border-radius:3px;font-size:9pt;font-weight:bold;'>"
            . $tpInfo['label'] . "</span>
        </td>
        <td style='padding:4px 8px;text-align:center;'>{$info['nb']}</td>
        <td style='padding:4px 8px;text-align:right;font-weight:bold;'>"
        . number_format($info['encaisse'], 0, ',', ' ') . " F</td>
    </tr>";
}

// ── Récap produits pharmacie HTML ─────────────────────────────────────────────
$recapPharmaHtml = '';
if ($recapPharmacieProduits) {
    $totalQtePharma    = 0;
    $totalMontPharma   = 0;
    foreach ($recapPharmacieProduits as $rp) {
        $totalQtePharma  += (int)$rp['qte'];
        $totalMontPharma += (int)$rp['total'];
        $recapPharmaHtml .= "<tr>
            <td style='padding:4px 8px;'>" . htmlspecialchars($rp['nom']) . "</td>
            <td style='padding:4px 8px;color:#888;'>" . htmlspecialchars($rp['forme']) . "</td>
            <td style='padding:4px 8px;text-align:center;font-weight:bold;'>" . (int)$rp['qte'] . "</td>
            <td style='padding:4px 8px;text-align:right;font-weight:bold;color:#006064;'>"
            . number_format((int)$rp['total'], 0, ',', ' ') . " F</td>
        </tr>";
    }
    $recapPharmaHtml .= "<tr style='background:#006064;color:#fff;'>
        <td colspan='2' style='padding:4px 8px;font-weight:bold;'>TOTAL</td>
        <td style='padding:4px 8px;text-align:center;font-weight:bold;'>{$totalQtePharma}</td>
        <td style='padding:4px 8px;text-align:right;font-weight:bold;'>"
        . number_format($totalMontPharma, 0, ',', ' ') . " F</td>
    </tr>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Situation <?= htmlspecialchars($modeLabel) ?> –
        <?= htmlspecialchars($percepteur['nom'] . ' ' . $percepteur['prenom']) ?>
    </title>
    <style>
        @page  { size: A4 portrait; margin: 12mm; }
        @media print { .no-print { display:none !important; } body { margin:0; } }
        body   { font-family: Arial, sans-serif; font-size: 10pt; color: #222; margin:0; padding:0; }
        h1,h2,h3 { margin:0; }
        table  { border-collapse: collapse; width: 100%; }
        .header-table td { vertical-align:top; padding:4px; }
        .section-title {
            background:#2e7d32; color:#fff; padding:5px 12px;
            font-size:10pt; font-weight:bold; margin:10px 0 5px;
        }
        .section-title-pharma { background:#006064; }
        .total-row td { background:#2e7d32; color:#fff; font-weight:bold; padding:5px 8px; }
        .print-btn {
            background:#2e7d32; color:#fff; border:none;
            padding:10px 24px; border-radius:6px; cursor:pointer; font-size:14px; margin-right:8px;
        }
        .close-btn {
            background:#757575; color:#fff; border:none;
            padding:10px 24px; border-radius:6px; cursor:pointer; font-size:14px;
        }
        .kpi-box {
            display:inline-block; border:2px solid #2e7d32; border-radius:8px;
            padding:8px 14px; margin:4px; text-align:center; min-width:110px;
        }
        .kpi-box .val { font-size:16pt; font-weight:bold; color:#2e7d32; }
        .kpi-box .lbl { font-size:8pt; color:#555; }
        .kpi-sexe-M { border-color:#1565c0; } .kpi-sexe-M .val { color:#1565c0; }
        .kpi-sexe-F { border-color:#c62828; } .kpi-sexe-F .val { color:#c62828; }
        .kpi-gratuit { border-color:#c62828; } .kpi-gratuit .val { color:#c62828; }
        .kpi-pharma  { border-color:#006064; } .kpi-pharma  .val { color:#006064; }
        .watermark {
            position:fixed; top:40%; left:10%; opacity:0.04;
            font-size:80pt; font-weight:bold; color:#2e7d32;
            transform:rotate(-30deg); z-index:-1; pointer-events:none;
        }
        th { font-weight:bold; }
    </style>
</head>
<body>
<div class="watermark">CSI</div>

<!-- Boutons impression -->
<div class="no-print" style="background:#e8f5e9;padding:12px 16px;
     border-bottom:2px solid #2e7d32;margin-bottom:16px;">
    <button class="print-btn" onclick="window.print()">🖨️ Imprimer / Enregistrer PDF</button>
    <button class="close-btn" onclick="window.close()">✕ Fermer</button>
    <span style="margin-left:16px;color:#555;font-size:12px;">
        Utilisez Ctrl+P ou le bouton ci-dessus pour imprimer.
    </span>
</div>

<!-- En-tête -->
<table class="header-table"
       style="border-bottom:3px solid #2e7d32;padding-bottom:8px;margin-bottom:8px;">
    <tr>
        <td style="width:70%;">
            <h2 style="color:#2e7d32;"><?= htmlspecialchars($nomCentre) ?></h2>
            <div style="color:#555;font-size:9pt;"><?= htmlspecialchars($adresse) ?></div>
            <?php if ($telephone): ?>
            <div style="color:#555;font-size:9pt;">Tél : <?= htmlspecialchars($telephone) ?></div>
            <?php endif; ?>
        </td>
        <td style="width:30%;text-align:right;"><?= $logoHtml ?></td>
    </tr>
</table>

<!-- Titre -->
<div style="text-align:center;margin:10px 0;">
    <h2 style="color:#2e7d32;font-size:14pt;margin:0;">SITUATION DU PERCEPTEUR</h2>
    <h3 style="font-size:12pt;margin:4px 0;">
        <?= htmlspecialchars(strtoupper($percepteur['nom'] . ' ' . $percepteur['prenom'])) ?>
    </h3>
    <div style="font-size:9pt;color:#555;">(Login : <?= htmlspecialchars($percepteur['login']) ?>)</div>
    <div style="display:inline-flex;gap:8px;margin-top:6px;flex-wrap:wrap;justify-content:center;">
        <span style="background:#f5f5f5;border:1px solid #ccc;border-radius:6px;padding:5px 12px;">
            <strong><?= htmlspecialchars($modeLabel) ?></strong>
        </span>
        <span style="background:<?= $typeRapport === 'tout' ? '#263238' : ($typeRapport === 'consultation' ? '#e8f5e9' : ($typeRapport === 'examen' ? '#fff3e0' : '#e0f7fa')) ?>;
                     color:<?= $typeRapport === 'tout' ? '#fff' : ($typeRapport === 'consultation' ? '#2e7d32' : ($typeRapport === 'examen' ? '#e65100' : '#006064')) ?>;
                     border-radius:6px;padding:5px 12px;font-weight:bold;font-size:9pt;">
            <?= htmlspecialchars($typeRapportLabels[$typeRapport] ?? 'Tout') ?>
        </span>
    </div>
</div>

<!-- KPIs – ligne 1 -->
<div style="text-align:center;margin:10px 0 4px;">
    <div class="kpi-box">
        <div class="val"><?= $nbRecus ?></div>
        <div class="lbl">Reçus émis</div>
    </div>
    <div class="kpi-box">
        <div class="val"><?= number_format($totalEncaisse, 0, ',', ' ') ?> F</div>
        <div class="lbl">Total encaissé</div>
    </div>
    <?php if ($totalGratuit > 0): ?>
    <div class="kpi-box kpi-gratuit">
        <div class="val"><?= number_format($totalGratuit, 0, ',', ' ') ?> F</div>
        <div class="lbl">Coût actes gratuits</div>
    </div>
    <?php endif; ?>
    <?php if ($recapPharmacieProduits): ?>
    <div class="kpi-box kpi-pharma">
        <div class="val"><?= count($recapPharmacieProduits) ?></div>
        <div class="lbl">Produits vendus</div>
    </div>
    <?php endif; ?>
</div>

<!-- KPIs – ligne 2 : par sexe -->
<div style="text-align:center;margin:4px 0 10px;">
    <?php foreach ($bySexe as $sexeKey => $sInfo): ?>
    <?php if ($sInfo['nb'] === 0) continue; ?>
    <div class="kpi-box kpi-sexe-<?= $sexeKey ?>">
        <div class="val"><?= $sInfo['nb'] ?></div>
        <div class="lbl">
            Reçus <?= $sInfo['label'] ?><br>
            <strong><?= number_format($sInfo['encaisse'], 0, ',', ' ') ?> F</strong>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Récapitulatifs côte à côte -->
<?php if (!empty($byType)): ?>
<div class="section-title">Récapitulatifs</div>
<table style="width:100%;margin-bottom:10px;border:none;">
    <tr style="vertical-align:top;">
        <!-- Récap par pôle -->
        <td style="width:33%;padding-right:8px;">
            <table style="width:100%;">
                <thead>
                    <tr style="background:#e8f5e9;">
                        <th style="padding:4px 8px;text-align:left;font-size:9pt;">Pôle</th>
                        <th style="padding:4px 8px;text-align:center;font-size:9pt;">Nb</th>
                        <th style="padding:4px 8px;text-align:right;font-size:9pt;">Encaissé</th>
                    </tr>
                </thead>
                <tbody><?= $recapHtml ?></tbody>
                <tfoot>
                    <tr class="total-row">
                        <td style="font-size:9pt;">TOTAL</td>
                        <td style="text-align:center;font-size:9pt;"><?= $nbRecus ?></td>
                        <td style="text-align:right;font-size:9pt;"><?= number_format($totalEncaisse, 0, ',', ' ') ?> F</td>
                    </tr>
                </tfoot>
            </table>
        </td>
        <!-- Récap par type patient -->
        <td style="width:34%;padding:0 4px;">
            <table style="width:100%;">
                <thead>
                    <tr style="background:#e8f5e9;">
                        <th style="padding:4px 8px;text-align:left;font-size:9pt;">Catégorie</th>
                        <th style="padding:4px 8px;text-align:center;font-size:9pt;">Nb</th>
                        <th style="padding:4px 8px;text-align:right;font-size:9pt;">Encaissé</th>
                    </tr>
                </thead>
                <tbody><?= $recapTypePatientHtml ?></tbody>
                <tfoot>
                    <tr class="total-row">
                        <td style="font-size:9pt;">TOTAL</td>
                        <td style="text-align:center;font-size:9pt;"><?= $nbRecus ?></td>
                        <td style="text-align:right;font-size:9pt;"><?= number_format($totalEncaisse, 0, ',', ' ') ?> F</td>
                    </tr>
                </tfoot>
            </table>
        </td>
        <!-- Récap par sexe -->
        <td style="width:33%;padding-left:8px;">
            <table style="width:100%;">
                <thead>
                    <tr style="background:#e8f5e9;">
                        <th style="padding:4px 8px;text-align:left;font-size:9pt;">Sexe</th>
                        <th style="padding:4px 8px;text-align:center;font-size:9pt;">Nb reçus</th>
                        <th style="padding:4px 8px;text-align:right;font-size:9pt;">Encaissé</th>
                    </tr>
                </thead>
                <tbody><?= $recapSexeHtml ?></tbody>
                <tfoot>
                    <tr class="total-row">
                        <td style="font-size:9pt;">TOTAL</td>
                        <td style="text-align:center;font-size:9pt;"><?= $nbRecus ?></td>
                        <td style="text-align:right;font-size:9pt;"><?= number_format($totalEncaisse, 0, ',', ' ') ?> F</td>
                    </tr>
                </tfoot>
            </table>
        </td>
    </tr>
</table>
<?php endif; ?>

<!-- Récap produits pharmacie -->
<?php if ($recapPharmacieProduits): ?>
<div class="section-title section-title-pharma">Récapitulatif Produits Pharmaceutiques vendus</div>
<table style="width:60%;margin-bottom:10px;">
    <thead>
        <tr style="background:#b2ebf2;">
            <th style="padding:5px 8px;text-align:left;font-size:9pt;">Produit</th>
            <th style="padding:5px 8px;text-align:left;font-size:9pt;">Forme</th>
            <th style="padding:5px 8px;text-align:center;font-size:9pt;">Qté totale</th>
            <th style="padding:5px 8px;text-align:right;font-size:9pt;">Montant total</th>
        </tr>
    </thead>
    <tbody><?= $recapPharmaHtml ?></tbody>
</table>
<?php endif; ?>

<!-- Détail des opérations -->
<div class="section-title">
    Détail des opérations (<?= $nbRecus ?> reçu<?= $nbRecus > 1 ? 's' : '' ?>)
</div>
<table>
    <thead>
        <tr style="background:#1b5e20;color:#fff;font-size:9pt;">
            <th style="padding:5px 6px;text-align:center;width:4%;">N°</th>
            <th style="padding:5px 6px;width:9%;">Reçu</th>
            <th style="padding:5px 6px;width:11%;">Date/Heure</th>
            <th style="padding:5px 6px;width:24%;">Patient</th>
            <th style="padding:5px 6px;text-align:center;width:5%;">Sexe</th>
            <th style="padding:5px 6px;text-align:center;width:12%;">Type reçu</th>
            <th style="padding:5px 6px;text-align:center;width:14%;">Catégorie</th>
            <th style="padding:5px 6px;text-align:right;width:12%;">Montant</th>
        </tr>
    </thead>
    <tbody><?= $lignesHtml ?></tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="7" style="text-align:right;padding:5px 8px;font-size:9pt;">TOTAL ENCAISSÉ :</td>
            <td style="text-align:right;padding:5px 8px;font-size:9pt;">
                <?= number_format($totalEncaisse, 0, ',', ' ') ?> F
            </td>
        </tr>
    </tfoot>
</table>

<!-- Pied de page -->
<div style="margin-top:20px;border-top:1px solid #ccc;padding-top:8px;">
    <table>
        <tr>
            <td style="width:60%;font-size:9pt;color:#555;"><?= htmlspecialchars($piedPage) ?></td>
            <td style="text-align:right;font-size:9pt;">
                <div>Signature Percepteur :</div>
                <div style="margin-top:28px;border-top:1px solid #555;
                            padding-top:4px;width:200px;float:right;">
                    <?= htmlspecialchars($percepteur['nom'] . ' ' . $percepteur['prenom']) ?>
                </div>
            </td>
        </tr>
    </table>
    <p style="text-align:center;color:#888;font-size:8pt;margin-top:8px;">
        Document généré le <?= date('d/m/Y à H:i') ?> — <?= htmlspecialchars($nomCentre) ?>
    </p>
</div>
</body>
</html>
