<?php
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('admin', 'comptable');

$pdo = Database::getInstance();
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin']   ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
    die('Dates invalides.');
}

// ─────────────────────────────────────────────────────────────
// Données : sorties pharmacie agrégées par produit
// ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        pp.nom,
        pp.forme,
        SUM(lp.quantite)      AS total_qte,
        AVG(lp.prix_unitaire) AS prix_unit,
        SUM(lp.total_ligne)   AS total_montant
    FROM lignes_pharmacie lp
    JOIN produits_pharmacie pp ON pp.id = lp.produit_id
    JOIN recus r ON r.id = lp.recu_id
    WHERE lp.isDeleted = 0
      AND r.isDeleted  = 0
      AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY pp.id, pp.nom, pp.forme
    ORDER BY total_qte DESC
");
$stmt->execute([':d' => $dateDebut, ':f' => $dateFin]);
$lignes = $stmt->fetchAll();

$nbProduits     = count($lignes);
$totalQte       = array_sum(array_column($lignes, 'total_qte'));
$totalMontant   = array_sum(array_column($lignes, 'total_montant'));

// Configuration centre
$cfgRows = $pdo->query("SELECT cle, valeur FROM config_systeme WHERE isDeleted=0")->fetchAll();
$cfg = array_column($cfgRows, 'valeur', 'cle');

// Logos
$logoMin = '';
if (!empty($cfg['logo_ministere']) && file_exists(ROOT_PATH . '/uploads/logos/' . $cfg['logo_ministere'])) {
    $logoMin = ROOT_PATH . '/uploads/logos/' . $cfg['logo_ministere'];
} elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_ministere.png')) {
    $logoMin = ROOT_PATH . '/uploads/logos/logo_ministere.png';
}
$logoDA = '';
if (!empty($cfg['logo_filename']) && file_exists(ROOT_PATH . '/uploads/logos/' . $cfg['logo_filename'])) {
    $logoDA = ROOT_PATH . '/uploads/logos/' . $cfg['logo_filename'];
}

// ─────────────────────────────────────────────────────────────
// Chargement TCPDF
// ─────────────────────────────────────────────────────────────
if (file_exists(ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
} elseif (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf.php';
}
if (file_exists(ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php')) {
    require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
} elseif (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf_barcodes_2d.php')) {
    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf_barcodes_2d.php';
}

// ─────────────────────────────────────────────────────────────
// QR Code
// ─────────────────────────────────────────────────────────────
$qrFile    = '';
$qrCleanup = null;

if (class_exists('TCPDF2DBarcode') && function_exists('imagecreate')) {
    $userNom    = Session::get('user_nom') ?? '—';
    $dateGen    = date('d/m/Y H:i:s');
    $periodeDeb = date('d/m/Y', strtotime($dateDebut));
    $periodeFin = date('d/m/Y', strtotime($dateFin));

    $contenuQr  = "=== CSI DIRECTAID MARADI ===\n";
    $contenuQr .= "DOCUMENT : SITUATION SORTIES PHARMACIE\n";
    $contenuQr .= "-----------------------------\n";
    $contenuQr .= "Periode : du {$periodeDeb} au {$periodeFin}\n";
    $contenuQr .= "Nb produits : {$nbProduits}\n";
    $contenuQr .= "Total unites sorties : {$totalQte}\n";
    $contenuQr .= "Montant total : " . number_format($totalMontant, 0, ',', ' ') . " F CFA\n";
    $contenuQr .= "-----------------------------\n";
    $contenuQr .= "Genere le : {$dateGen}\n";
    $contenuQr .= "Par : " . $userNom . "\n";
    $contenuQr .= "Tel centre : " . ($cfg['telephone'] ?? '-') . "\n";
    $contenuQr .= "=============================";

    try {
        $qr = new TCPDF2DBarcode($contenuQr, 'QRCODE,M');
        $pngData = $qr->getBarcodePngData(12, 12, [0, 0, 0]);

        if ($pngData !== false && strlen($pngData) > 100) {
            $qrDir = ROOT_PATH . '/uploads/pdf/qr_tmp/';
            if (!is_dir($qrDir)) @mkdir($qrDir, 0755, true);
            if (is_dir($qrDir) && is_writable($qrDir)) {
                $qrFile = $qrDir . 'qr_pharmacie_' . uniqid('', true) . '.png';
                if (file_put_contents($qrFile, $pngData) === false) {
                    $qrFile = '';
                } else {
                    $qrCleanup = $qrFile;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[imprimer_pharmacie] Erreur QR : ' . $e->getMessage());
        $qrFile = '';
    }
}

// ─────────────────────────────────────────────────────────────
// PDF
// ─────────────────────────────────────────────────────────────
if (!class_exists('TCPDF')) {
    // Fallback HTML si TCPDF absent
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>Sorties Pharmacie</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        h2 { color: #006064; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #006064; color: #fff; padding: 6px 8px; text-align: center; }
        td { padding: 5px 8px; border-bottom: 1px solid #ddd; }
        tfoot td { background: #e0f7fa; font-weight: bold; }
        .info { background: #e0f7fa; padding: 10px; border-left: 4px solid #006064; margin-bottom: 15px; }
    </style></head><body>';
    echo '<h2><i>CSI DirectAid Maradi — Situation Sorties Pharmacie</i></h2>';
    echo '<div class="info">
        <strong>Période :</strong> ' . date('d/m/Y', strtotime($dateDebut)) . ' → ' . date('d/m/Y', strtotime($dateFin)) . ' &nbsp;·&nbsp;
        <strong>Produits :</strong> ' . $nbProduits . ' &nbsp;·&nbsp;
        <strong>Unités sorties :</strong> ' . number_format($totalQte, 0, ',', ' ') . ' &nbsp;·&nbsp;
        <strong>Total :</strong> ' . number_format($totalMontant, 0, ',', ' ') . ' F CFA
    </div>';
    echo '<table><thead><tr>
        <th>#</th><th>Produit</th><th>Forme</th>
        <th>Qté sortie</th><th>Prix unit. moy.</th><th>Total</th>
    </tr></thead><tbody>';
    $i = 0;
    foreach ($lignes as $l) {
        $i++;
        $bg = $i % 2 ? '#fff' : '#f5f5f5';
        echo '<tr style="background:' . $bg . '">
            <td style="text-align:center;color:#999;">' . $i . '</td>
            <td>' . htmlspecialchars($l['nom'], ENT_QUOTES) . '</td>
            <td style="text-align:center;">' . htmlspecialchars($l['forme'] ?? '—', ENT_QUOTES) . '</td>
            <td style="text-align:center;font-weight:bold;color:#006064;">' . (int)$l['total_qte'] . '</td>
            <td style="text-align:right;">' . number_format($l['prix_unit'], 0, ',', ' ') . ' F</td>
            <td style="text-align:right;font-weight:bold;">' . number_format($l['total_montant'], 0, ',', ' ') . ' F</td>
        </tr>';
    }
    if (empty($lignes)) {
        echo '<tr><td colspan="6" style="text-align:center;color:#999;padding:15px;">Aucune sortie pharmacie sur cette période.</td></tr>';
    }
    echo '</tbody><tfoot><tr>
        <td colspan="3" style="text-align:right;">TOTAL</td>
        <td style="text-align:center;">' . number_format($totalQte, 0, ',', ' ') . '</td>
        <td></td>
        <td style="text-align:right;">' . number_format($totalMontant, 0, ',', ' ') . ' F</td>
    </tr></tfoot></table>';
    echo '<p style="margin-top:20px;color:#777;font-size:10px;">Document généré le ' . date('d/m/Y à H:i') . ' par ' . htmlspecialchars(Session::get('user_nom') ?? '—', ENT_QUOTES) . '</p>';
    echo '</body></html>';
    exit;
}

class PharmaciePDF extends TCPDF {
    public string $footerText = '';

    public function Footer() {
        $this->SetY(-12);
        $this->SetFont('helvetica', 'I', 7.5);
        $this->SetTextColor(110, 110, 110);
        $this->SetDrawColor(180, 180, 180);
        $this->Line($this->GetX(), $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->Ln(1);
        $this->Cell(0, 5, $this->footerText, 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new PharmaciePDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->footerText = 'Document généré le ' . date('d/m/Y à H:i')
    . ' par ' . (Session::get('user_nom') ?? '—');

$pdf->SetCreator('CSI DirectAid Maradi');
$pdf->SetTitle('Situation Sorties Pharmacie');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 12, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetFont('helvetica', '', 9.5);
$pdf->setImageScale(1.25);
$pdf->AddPage();

// ─────────────────────────────────────────────────────────────
// EN-TÊTE OFFICIEL
// ─────────────────────────────────────────────────────────────
$logoMinTag = $logoMin ? "<img src=\"{$logoMin}\" width=\"32\" height=\"32\"/>" : '';
$logoDaTag  = $logoDA  ? "<img src=\"{$logoDA}\" width=\"32\" height=\"32\"/>"  : '';

$adresse = trim($cfg['adresse'] ?? '');
$tel     = trim($cfg['telephone'] ?? '');
$coord   = [];
if ($adresse !== '') $coord[] = htmlspecialchars($adresse, ENT_QUOTES);
if ($tel !== '')     $coord[] = 'Tél : ' . htmlspecialchars($tel, ENT_QUOTES);
$ligneCoord = !empty($coord)
    ? '<br/><span style="font-size:7.5pt;color:#555;">' . implode(' &nbsp;·&nbsp; ', $coord) . '</span>'
    : '';

$enTete = '
<table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1.5pt solid #006064;">
    <tr>
        <td width="22%" align="center" style="vertical-align:middle;">' . $logoMinTag . '</td>
        <td width="56%" align="center" style="vertical-align:middle;line-height:1.4;">
            <span style="font-size:11.5pt;font-weight:bold;letter-spacing:0.5pt;">RÉPUBLIQUE DU NIGER</span><br/>
            <span style="font-size:8.5pt;">Ministère de la Santé et de l\'Hygiène Publique</span><br/>
            <span style="font-size:11.5pt;font-weight:bold;color:#006064;">CSI ZARIA I / Direct Aid - MARADI</span>'
            . $ligneCoord . '
        </td>
        <td width="22%" align="center" style="vertical-align:middle;">' . $logoDaTag . '</td>
    </tr>
</table>';

// ─────────────────────────────────────────────────────────────
// TITRE + BANDEAU INFOS
// ─────────────────────────────────────────────────────────────
$titre = '
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:8pt;">
    <tr>
        <td align="center" style="background:#006064;color:#ffffff;padding:6pt;font-size:13pt;font-weight:bold;letter-spacing:1.5pt;">
            SITUATION SORTIES PHARMACIE
        </td>
    </tr>
    <tr>
        <td align="center" style="font-size:8.5pt;color:#555;padding-top:3pt;">
            Médicaments et produits pharmaceutiques délivrés sur la période
        </td>
    </tr>
</table>

<table width="100%" cellpadding="5" cellspacing="0" style="background:#e0f7fa;border:1pt solid #006064;margin-top:8pt;">
    <tr>
        <td width="40%" style="font-size:9pt;color:#004d40;">
            <b style="color:#006064;">PÉRIODE :</b><br/>
            Du <b>' . date('d/m/Y', strtotime($dateDebut)) . '</b> au <b>' . date('d/m/Y', strtotime($dateFin)) . '</b>
        </td>
        <td width="20%" style="font-size:9pt;color:#004d40;text-align:center;">
            <b style="color:#006064;">PRODUITS</b><br/>
            <span style="font-size:13pt;font-weight:bold;color:#006064;">' . $nbProduits . '</span>
        </td>
        <td width="20%" style="font-size:9pt;color:#004d40;text-align:center;">
            <b style="color:#006064;">UNITÉS</b><br/>
            <span style="font-size:13pt;font-weight:bold;color:#006064;">' . number_format($totalQte, 0, ',', ' ') . '</span>
        </td>
        <td width="20%" style="font-size:9pt;color:#004d40;text-align:center;">
            <b style="color:#006064;">TOTAL</b><br/>
            <span style="font-size:13pt;font-weight:bold;color:#c62828;">' . number_format($totalMontant, 0, ',', ' ') . ' F</span>
        </td>
    </tr>
</table>';

// ─────────────────────────────────────────────────────────────
// TABLEAU DÉTAILLÉ
// ─────────────────────────────────────────────────────────────
$cellBorder = 'border-top:0.5pt solid #888;border-bottom:0.5pt solid #888;border-left:0.5pt solid #888;border-right:0.5pt solid #888;';

$rows = '';
$compteur = 0;
foreach ($lignes as $l) {
    $compteur++;
    $bg = $compteur % 2 ? '#ffffff' : '#f0fdfd';
    $rows .= "<tr style=\"background:{$bg};\">
        <td style=\"padding:4pt;text-align:center;color:#555;{$cellBorder}\">{$compteur}</td>
        <td style=\"padding:4pt;font-weight:bold;{$cellBorder}\">" . htmlspecialchars($l['nom'], ENT_QUOTES) . "</td>
        <td style=\"padding:4pt;text-align:center;{$cellBorder}\">" . htmlspecialchars($l['forme'] ?? '—', ENT_QUOTES) . "</td>
        <td style=\"padding:4pt;text-align:center;font-weight:bold;color:#006064;{$cellBorder}\">" . (int)$l['total_qte'] . "</td>
        <td style=\"padding:4pt;text-align:right;color:#555;{$cellBorder}\">" . number_format((float)$l['prix_unit'], 0, ',', ' ') . " F</td>
        <td style=\"padding:4pt;text-align:right;font-weight:bold;color:#2e7d32;{$cellBorder}\">" . number_format((float)$l['total_montant'], 0, ',', ' ') . " F</td>
    </tr>";
}

if (empty($rows)) {
    $rows = '<tr><td colspan="6" style="padding:12pt;text-align:center;font-style:italic;color:#999;' . $cellBorder . '">Aucune sortie pharmacie enregistrée sur cette période.</td></tr>';
}

$thBorder = 'border-top:0.5pt solid #004d40;border-bottom:0.5pt solid #004d40;border-left:0.5pt solid #004d40;border-right:0.5pt solid #004d40;';

$tableau = "
<table border=\"1\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"border-collapse:collapse;border:0.5pt solid #888;font-size:9pt;margin-top:10pt;\">
    <tr style=\"background:#006064;color:#ffffff;font-weight:bold;font-size:9pt;\">
        <th style=\"padding:5pt;width:6%;text-align:center;{$thBorder}\">N°</th>
        <th style=\"padding:5pt;width:30%;text-align:left;{$thBorder}\">Produit</th>
        <th style=\"padding:5pt;width:14%;text-align:center;{$thBorder}\">Forme</th>
        <th style=\"padding:5pt;width:14%;text-align:center;{$thBorder}\">Qté sortie</th>
        <th style=\"padding:5pt;width:18%;text-align:right;{$thBorder}\">Prix unit. moy.</th>
        <th style=\"padding:5pt;width:18%;text-align:right;{$thBorder}\">Total</th>
    </tr>
    {$rows}
    <tr style=\"background:#006064;color:#ffffff;font-weight:bold;font-size:10pt;\">
        <td colspan=\"3\" style=\"padding:6pt 8pt;text-align:right;{$thBorder}\">TOTAL GÉNÉRAL :</td>
        <td style=\"padding:6pt 8pt;text-align:center;{$thBorder}\">" . number_format($totalQte, 0, ',', ' ') . "</td>
        <td style=\"{$thBorder}\"></td>
        <td style=\"padding:6pt 8pt;text-align:right;{$thBorder}\">" . number_format($totalMontant, 0, ',', ' ') . " F</td>
    </tr>
</table>";

// Écriture PDF
$pdf->writeHTML($enTete . $titre . $tableau, true, false, true, false, '');

// QR Code
if ($qrFile) {
    $qrSize = 45;
    $pageW  = $pdf->getPageWidth();
    $marge  = 15;
    $yQr    = $pdf->GetY() + 8;
    $xQr    = $pageW - $qrSize - $marge;
    $pdf->Image($qrFile, $xQr, $yQr, $qrSize, $qrSize, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
    $pdf->SetXY($xQr, $yQr + $qrSize + 1);
    $pdf->SetFont('helvetica', 'I', 6.5);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell($qrSize, 4, 'Scannez pour vérifier', 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

// Sauvegarde
$dir = ROOT_PATH . '/uploads/pdf/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$file = $dir . 'pharmacie_sorties_' . str_replace('-', '', $dateDebut) . '_' . str_replace('-', '', $dateFin) . '.pdf';
$pdf->Output($file, 'F');

if ($qrCleanup && is_file($qrCleanup)) {
    @unlink($qrCleanup);
}

header('Location: ' . url('uploads/pdf/' . basename($file)));
exit;
