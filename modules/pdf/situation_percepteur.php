<?php
/**
 * Génération Situation Percepteur – PDF (impression navigateur)
 * Paramètres GET :
 *   percepteur_id   : int  – ID utilisateur
 *   mode            : 'jour' | 'periode'
 *   date_debut      : Y-m-d  (pour mode=periode)
 *   date_fin        : Y-m-d  (pour mode=periode)
 */
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('admin');

$pdo           = Database::getInstance();
$percepteurId  = (int)($_GET['percepteur_id'] ?? 0);
$mode          = ($_GET['mode'] ?? 'jour') === 'periode' ? 'periode' : 'jour';

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
$stmtP = $pdo->prepare("SELECT nom, prenom, login FROM utilisateurs WHERE id=:id AND isDeleted=0 LIMIT 1");
$stmtP->execute([':id' => $percepteurId]);
$percepteur = $stmtP->fetch();
if (!$percepteur) { die('<p class="text-danger">Percepteur introuvable.</p>'); }

// ── Config centre ─────────────────────────────────────────────────────────────
$cfgRows = $pdo->query("SELECT cle, valeur FROM config_systeme WHERE isDeleted=0")->fetchAll();
$cfg = [];
foreach ($cfgRows as $r) { $cfg[$r['cle']] = $r['valeur']; }
$nomCentre  = $cfg['nom_centre']  ?? 'CSI AMA Maradi';
$adresse    = $cfg['adresse']     ?? 'Maradi – Niger';
$telephone  = $cfg['telephone']   ?? '';
$piedPage   = $cfg['pied_de_page'] ?? 'Merci de votre confiance.';

// Logo
$logoHtml = '';
$logoFile = $cfg['logo_filename'] ?? '';
if ($logoFile && file_exists(ROOT_PATH . '/uploads/logos/' . $logoFile)) {
    $logoHtml = '<img src="' . ROOT_PATH . '/uploads/logos/' . $logoFile . '" alt="Logo" style="max-height:60px;">';
} elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_csi.png')) {
    $logoHtml = '<img src="' . ROOT_PATH . '/uploads/logos/logo_csi.png" alt="Logo" style="max-height:60px;">';
}

// ── Recus du percepteur sur la période ───────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT r.id, r.numero_recu, r.type_recu, r.type_patient,
           r.montant_total, r.montant_encaisse,
           r.whendone,
           p.nom AS patient_nom, p.telephone AS patient_tel
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.whodone = :uid
      AND r.isDeleted = 0
      AND DATE(r.whendone) BETWEEN :deb AND :fin
    ORDER BY r.whendone ASC
");
$stmt->execute([':uid' => $percepteurId, ':deb' => $dateDebut, ':fin' => $dateFin]);
$recus = $stmt->fetchAll();

// ── Totaux ────────────────────────────────────────────────────────────────────
$totalEncaisse = 0;
$totalGratuit  = 0;
$nbRecus       = count($recus);
$byType        = [];
foreach ($recus as $r) {
    $totalEncaisse += $r['montant_encaisse'];
    if (in_array($r['type_patient'], ['orphelin', 'acte_gratuit'])) {
        $totalGratuit += $r['montant_total'];
    }
    $t = $r['type_recu'];
    if (!isset($byType[$t])) $byType[$t] = ['nb' => 0, 'total' => 0];
    $byType[$t]['nb']++;
    $byType[$t]['total'] += $r['montant_encaisse'];
}

// ── Labels ────────────────────────────────────────────────────────────────────
$labelMode = $mode === 'jour'
    ? 'Situation Journalière du ' . date('d/m/Y')
    : 'Situation du ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin));

$typeLabels = ['consultation' => 'Consultations', 'examen' => 'Examens', 'pharmacie' => 'Pharmacie'];

// Lignes HTML du tableau
$lignesHtml = '';
foreach ($recus as $i => $r) {
    $isGratuit = in_array($r['type_patient'], ['orphelin', 'acte_gratuit']);
    $numFmt    = '#' . str_pad($r['numero_recu'], 5, '0', STR_PAD_LEFT);
    $heure     = date('H:i', strtotime($r['whendone']));
    $dateAff   = date('d/m', strtotime($r['whendone']));
    $typeAff   = $typeLabels[$r['type_recu']] ?? ucfirst($r['type_recu']);
    $montant   = $isGratuit
        ? '<span style="color:#c62828;font-weight:bold;">0 F (GRATUIT)</span>'
        : '<strong>' . number_format($r['montant_encaisse'], 0, ',', ' ') . ' F</strong>';
    $lignesHtml .= "
    <tr style='border-bottom:1px solid #e0e0e0;" . ($i % 2 ? "background:#f9f9f9;" : "") . "'>
        <td style='padding:5px 8px;text-align:center;'>" . ($i + 1) . "</td>
        <td style='padding:5px 8px;'><b>{$numFmt}</b></td>
        <td style='padding:5px 8px;'>{$dateAff} {$heure}</td>
        <td style='padding:5px 8px;'>" . htmlspecialchars($r['patient_nom']) . "</td>
        <td style='padding:5px 8px;text-align:center;'><span style='background:#e8f5e9;color:#2e7d32;padding:2px 6px;border-radius:4px;font-size:9pt;'>{$typeAff}</span></td>
        <td style='padding:5px 8px;text-align:right;'>{$montant}</td>
    </tr>";
}
if (!$lignesHtml) {
    $lignesHtml = "<tr><td colspan='6' style='text-align:center;padding:20px;color:#888;'>Aucune opération sur cette période.</td></tr>";
}

// Récapitulatif par type
$recapHtml = '';
foreach ($byType as $type => $info) {
    $lb = $typeLabels[$type] ?? ucfirst($type);
    $recapHtml .= "<tr>
        <td style='padding:4px 8px;'>{$lb}</td>
        <td style='padding:4px 8px;text-align:center;'>{$info['nb']}</td>
        <td style='padding:4px 8px;text-align:right;font-weight:bold;'>" . number_format($info['total'], 0, ',', ' ') . " F</td>
    </tr>";
}

// Générer le HTML complet
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Situation <?= htmlspecialchars($labelMode) ?> – <?= htmlspecialchars($percepteur['nom'] . ' ' . $percepteur['prenom']) ?></title>
    <style>
        @page { size: A4 portrait; margin: 15mm; }
        @media print { .no-print { display: none !important; } body { margin: 0; } }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #222; margin: 0; padding: 0; }
        h1, h2, h3 { margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .header-table td { vertical-align: top; padding: 4px; }
        .section-title {
            background: #2e7d32; color: #fff; padding: 6px 12px;
            font-size: 10pt; font-weight: bold; margin: 12px 0 6px;
        }
        .total-row td { background: #2e7d32; color: #fff; font-weight: bold; padding: 6px 8px; }
        .print-btn {
            background: #2e7d32; color: #fff; border: none;
            padding: 10px 24px; border-radius: 6px; cursor: pointer;
            font-size: 14px; margin-right: 8px;
        }
        .close-btn {
            background: #757575; color: #fff; border: none;
            padding: 10px 24px; border-radius: 6px; cursor: pointer; font-size: 14px;
        }
        .kpi-box {
            display: inline-block; border: 2px solid #2e7d32; border-radius: 8px;
            padding: 8px 16px; margin: 4px; text-align: center; min-width: 120px;
        }
        .kpi-box .val { font-size: 18pt; font-weight: bold; color: #2e7d32; }
        .kpi-box .lbl { font-size: 8pt; color: #555; }
        .watermark {
            position: fixed; top: 40%; left: 10%; opacity: 0.04;
            font-size: 80pt; font-weight: bold; color: #2e7d32;
            transform: rotate(-30deg); z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body onload="">
    <div class="watermark">CSI</div>

    <!-- Boutons impression (non imprimés) -->
    <div class="no-print" style="background:#e8f5e9;padding:12px 16px;border-bottom:2px solid #2e7d32;margin-bottom:16px;">
        <button class="print-btn" onclick="window.print()">🖨️ Imprimer / Enregistrer PDF</button>
        <button class="close-btn" onclick="window.close()">✕ Fermer</button>
        <span style="margin-left:16px;color:#555;font-size:12px;">Utilisez Ctrl+P ou le bouton ci-dessus pour imprimer.</span>
    </div>

    <!-- En-tête -->
    <table class="header-table" style="border-bottom:3px solid #2e7d32;padding-bottom:8px;margin-bottom:8px;">
        <tr>
            <td style="width:70%;">
                <h2 style="color:#2e7d32;"><?= htmlspecialchars($nomCentre) ?></h2>
                <div style="color:#555;font-size:9pt;"><?= htmlspecialchars($adresse) ?></div>
                <?php if ($telephone): ?>
                <div style="color:#555;font-size:9pt;">Tél: <?= htmlspecialchars($telephone) ?></div>
                <?php endif; ?>
            </td>
            <td style="width:30%;text-align:right;">
                <?= $logoHtml ?>
            </td>
        </tr>
    </table>

    <!-- Titre rapport -->
    <div style="text-align:center;margin:10px 0;">
        <h2 style="color:#2e7d32;font-size:14pt;margin:0;">SITUATION DU PERCEPTEUR</h2>
        <h3 style="font-size:12pt;margin:4px 0;"><?= htmlspecialchars(strtoupper($percepteur['nom'] . ' ' . $percepteur['prenom'])) ?></h3>
        <div style="font-size:10pt;color:#555;margin:2px 0;">(Login : <?= htmlspecialchars($percepteur['login']) ?>)</div>
        <div style="background:#f5f5f5;border:1px solid #ccc;border-radius:6px;padding:6px 12px;display:inline-block;margin-top:6px;">
            <strong><?= htmlspecialchars($labelMode) ?></strong>
        </div>
    </div>

    <!-- KPIs résumé -->
    <div style="text-align:center;margin:12px 0;">
        <div class="kpi-box">
            <div class="val"><?= $nbRecus ?></div>
            <div class="lbl">Reçus émis</div>
        </div>
        <div class="kpi-box">
            <div class="val"><?= number_format($totalEncaisse, 0, ',', ' ') ?> F</div>
            <div class="lbl">Total encaissé</div>
        </div>
        <?php if ($totalGratuit > 0): ?>
        <div class="kpi-box" style="border-color:#c62828;">
            <div class="val" style="color:#c62828;"><?= number_format($totalGratuit, 0, ',', ' ') ?> F</div>
            <div class="lbl">Coût actes gratuits</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Récapitulatif par type -->
    <?php if (!empty($byType)): ?>
    <div class="section-title">Récapitulatif par pôle</div>
    <table style="width:50%;margin-bottom:8px;">
        <thead>
            <tr style="background:#e8f5e9;">
                <th style="padding:4px 8px;text-align:left;">Pôle</th>
                <th style="padding:4px 8px;text-align:center;">Nb reçus</th>
                <th style="padding:4px 8px;text-align:right;">Montant encaissé</th>
            </tr>
        </thead>
        <tbody>
            <?= $recapHtml ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td>TOTAL</td>
                <td style="text-align:center;"><?= $nbRecus ?></td>
                <td style="text-align:right;"><?= number_format($totalEncaisse, 0, ',', ' ') ?> F</td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>

    <!-- Détail des reçus -->
    <div class="section-title">Détail des opérations (<?= $nbRecus ?> reçu<?= $nbRecus > 1 ? 's' : '' ?>)</div>
    <table>
        <thead>
            <tr style="background:#1b5e20;color:#fff;">
                <th style="padding:6px 8px;text-align:center;width:5%;">N°</th>
                <th style="padding:6px 8px;width:12%;">Reçu</th>
                <th style="padding:6px 8px;width:14%;">Date/Heure</th>
                <th style="padding:6px 8px;width:30%;">Patient</th>
                <th style="padding:6px 8px;text-align:center;width:14%;">Type</th>
                <th style="padding:6px 8px;text-align:right;width:15%;">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?= $lignesHtml ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" style="text-align:right;padding:6px 8px;">TOTAL ENCAISSÉ :</td>
                <td style="text-align:right;padding:6px 8px;"><?= number_format($totalEncaisse, 0, ',', ' ') ?> F</td>
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
                    <div style="margin-top:30px;border-top:1px solid #555;padding-top:4px;width:200px;float:right;">
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
