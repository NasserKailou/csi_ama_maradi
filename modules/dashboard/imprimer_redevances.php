<?php
if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('admin', 'comptable');

if (!defined('TARIF_SUPPLEMENT_ADULTE')) {
    define('TARIF_SUPPLEMENT_ADULTE', 100);
}
if (!defined('AGE_LIMITE_SUPPLEMENT')) {
    define('AGE_LIMITE_SUPPLEMENT', 5);
}
if (!defined('TARIF_OBSERVATION')) {
    define('TARIF_OBSERVATION', 1000);
}

$pdo = Database::getInstance();
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin']   ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
    die('Dates invalides.');
}

// ─────────────────────────────────────────────────────────────
// Récupération des données (LOGIQUE INCHANGÉE)
// ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT 
        DATE(r.whendone) AS jour,
        r.numero_recu,
        p.nom AS patient_nom,
        p.age,
        p.sexe,
        lc.tarif,
        u.nom AS percepteur_nom,
        u.prenom AS percepteur_prenom
    FROM lignes_consultation lc
    JOIN recus r ON r.id = lc.recu_id
    JOIN patients p ON p.id = r.patient_id
    LEFT JOIN utilisateurs u ON u.id = lc.whodone
    WHERE lc.type_ligne = 'redevance'
      AND lc.isDeleted = 0
      AND r.isDeleted = 0
      AND r.type_patient = 'normal'
      AND DATE(r.whendone) BETWEEN :d AND :f
    ORDER BY r.whendone ASC
");
$stmt->execute([':d' => $dateDebut, ':f' => $dateFin]);
$lignes = $stmt->fetchAll();

$nbTotal = count($lignes);
$montantTotal = array_sum(array_column($lignes, 'tarif'));

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
// Chargement TCPDF + TCPDF2DBarcode
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
// Génération du QR code (PNG temporaire)
// ─────────────────────────────────────────────────────────────
$qrFile    = '';
$qrCleanup = null;

if (class_exists('TCPDF2DBarcode') && function_exists('imagecreate')) {

    $userNom    = Session::get('user_nom') ?? '—';
    $dateGen    = date('d/m/Y H:i:s');
    $periodeDeb = date('d/m/Y', strtotime($dateDebut));
    $periodeFin = date('d/m/Y', strtotime($dateFin));

    $listeRecus = [];
    foreach ($lignes as $l) {
        $listeRecus[] = '#' . str_pad($l['numero_recu'], 5, '0', STR_PAD_LEFT);
    }
    $apercuRecus = implode(',', array_slice($listeRecus, 0, 30));
    if (count($listeRecus) > 30) {
        $apercuRecus .= ',... (+' . (count($listeRecus) - 30) . ')';
    }

    $contenuQr  = "=== CSI DIRECTAID MARADI ===\n";
    $contenuQr .= "DOCUMENT : SITUATION DES REDEVANCES\n";
    $contenuQr .= "A reverser au Ministere de la Sante\n";
    $contenuQr .= "-----------------------------\n";
    $contenuQr .= "Periode : du {$periodeDeb} au {$periodeFin}\n";
    $contenuQr .= "Nb patients concernes : {$nbTotal}\n";
    $contenuQr .= "Montant total : " . number_format($montantTotal, 0, ',', ' ') . " F CFA\n";
    $contenuQr .= "Tarif unitaire : " . TARIF_SUPPLEMENT_ADULTE . " F (age > " . AGE_LIMITE_SUPPLEMENT . " ans)\n";
    $contenuQr .= "-----------------------------\n";
    $contenuQr .= "Recus inclus : " . ($apercuRecus ?: 'aucun') . "\n";
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
                $qrFile = $qrDir . 'qr_redevance_' . uniqid('', true) . '.png';
                if (file_put_contents($qrFile, $pngData) === false) {
                    $qrFile = '';
                } else {
                    $qrCleanup = $qrFile;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[imprimer_redevance] Erreur QR : ' . $e->getMessage());
        $qrFile = '';
    }
}

// ─────────────────────────────────────────────────────────────
// Création du PDF avec footer personnalisé
// ─────────────────────────────────────────────────────────────
class RedevancePDF extends TCPDF {
    public string $footerText = '';

    public function Footer() {
        $this->SetY(-12);
        $this->SetFont('helvetica', 'I', 7.5);
        $this->SetTextColor(110, 110, 110);
        // Ligne de séparation discrète
        $this->SetDrawColor(180, 180, 180);
        $this->Line($this->GetX(), $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->Ln(1);
        $this->Cell(0, 5, $this->footerText, 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new RedevancePDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->footerText = 'Document généré le ' . date('d/m/Y à H:i')
    . ' par ' . (Session::get('user_nom') ?? '—');

$pdf->SetCreator('CSI DirectAid Maradi');
$pdf->SetTitle('Situation des redevances ministère');
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
<table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1.5pt solid #2e7d32;">
    <tr>
        <td width="22%" align="center" style="vertical-align:middle;">' . $logoMinTag . '</td>
        <td width="56%" align="center" style="vertical-align:middle;line-height:1.4;">
            <span style="font-size:11.5pt;font-weight:bold;letter-spacing:0.5pt;">RÉPUBLIQUE DU NIGER</span><br/>
            <span style="font-size:8.5pt;">Ministère de la Santé et de l\'Hygiène Publique</span><br/>
            <span style="font-size:11.5pt;font-weight:bold;color:#2e7d32;">CSI ZARIA I / Direct Aid - MARADI</span>'
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
        <td align="center" style="background:#e65100;color:#ffffff;padding:6pt;font-size:13pt;font-weight:bold;letter-spacing:1.5pt;">
            SITUATION DES REDEVANCES
        </td>
    </tr>
    <tr>
        <td align="center" style="font-size:8.5pt;color:#555;padding-top:3pt;">
            À reverser au Ministère de la Santé &nbsp;·&nbsp; 
            Supplément ' . TARIF_SUPPLEMENT_ADULTE . ' F appliqué aux patients normaux de plus de ' . AGE_LIMITE_SUPPLEMENT . ' ans
        </td>
    </tr>
</table>

<table width="100%" cellpadding="5" cellspacing="0" style="background:#fff8e1;border:1pt solid #f57f17;margin-top:8pt;">
    <tr>
        <td width="50%" style="font-size:9pt;color:#5d4037;">
            <b style="color:#e65100;">PÉRIODE :</b><br/>
            Du <b>' . date('d/m/Y', strtotime($dateDebut)) . '</b> au <b>' . date('d/m/Y', strtotime($dateFin)) . '</b>
        </td>
        <td width="22%" style="font-size:9pt;color:#5d4037;text-align:center;">
            <b style="color:#e65100;">PATIENTS</b><br/>
            <span style="font-size:13pt;font-weight:bold;color:#2e7d32;">' . $nbTotal . '</span>
        </td>
        <td width="28%" style="font-size:9pt;color:#5d4037;text-align:center;">
            <b style="color:#e65100;">TOTAL À VERSER</b><br/>
            <span style="font-size:13pt;font-weight:bold;color:#c62828;">' . number_format($montantTotal, 0, ',', ' ') . ' F</span>
        </td>
    </tr>
</table>';

// ─────────────────────────────────────────────────────────────
// TABLEAU DÉTAILLÉ — bordures complètes
// ─────────────────────────────────────────────────────────────
$cellBorder = 'border:0.5pt solid #888;';

$rows = '';
$compteur = 0;
foreach ($lignes as $l) {
    $compteur++;
    $bg = $compteur % 2 ? '#ffffff' : '#f5f5f5';
    $rows .= "<tr style=\"background:{$bg};\">
        <td style=\"padding:4pt;text-align:center;color:#555;{$cellBorder}\">{$compteur}</td>
        <td style=\"padding:4pt;text-align:center;{$cellBorder}\">" . date('d/m/Y', strtotime($l['jour'])) . "</td>
        <td style=\"padding:4pt;text-align:center;font-family:courier;color:#1565c0;font-weight:bold;{$cellBorder}\">#" . str_pad($l['numero_recu'], 5, '0', STR_PAD_LEFT) . "</td>
        <td style=\"padding:4pt;{$cellBorder}\">" . htmlspecialchars($l['patient_nom'], ENT_QUOTES) . "</td>
        <td style=\"padding:4pt;text-align:center;{$cellBorder}\">{$l['sexe']} / {$l['age']} ans</td>
        <td style=\"padding:4pt;{$cellBorder}\">" . htmlspecialchars(($l['percepteur_nom'] ?? '—') . ' ' . ($l['percepteur_prenom'] ?? ''), ENT_QUOTES) . "</td>
        <td style=\"padding:4pt;text-align:right;font-weight:bold;color:#2e7d32;{$cellBorder}\">" . number_format($l['tarif'], 0, ',', ' ') . " F</td>
    </tr>";
}

if (empty($rows)) {
    $rows = '<tr><td colspan="7" style="padding:12pt;text-align:center;font-style:italic;color:#999;' . $cellBorder . '">Aucune redevance enregistrée sur cette période.</td></tr>';
}

$tableau = "
<table border=\"1\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"border-collapse:collapse;border:0.5pt solid #888;font-size:9pt;margin-top:10pt;\">
    <thead>
        <tr style=\"background:#f57f17;color:#ffffff;font-weight:bold;font-size:9pt;\">
            <th style=\"padding:5pt;width:6%;text-align:center;border:0.5pt solid #e65100;\">N°</th>
            <th style=\"padding:5pt;width:12%;text-align:center;border:0.5pt solid #e65100;\">Date</th>
            <th style=\"padding:5pt;width:11%;text-align:center;border:0.5pt solid #e65100;\">Reçu</th>
            <th style=\"padding:5pt;width:28%;text-align:left;border:0.5pt solid #e65100;\">Patient</th>
            <th style=\"padding:5pt;width:13%;text-align:center;border:0.5pt solid #e65100;\">Sexe / Âge</th>
            <th style=\"padding:5pt;width:18%;text-align:left;border:0.5pt solid #e65100;\">Percepteur</th>
            <th style=\"padding:5pt;width:12%;text-align:right;border:0.5pt solid #e65100;\">Montant</th>
        </tr>
    </thead>
    <tbody>{$rows}</tbody>
    <tfoot>
        <tr style=\"background:#2e7d32;color:#ffffff;font-weight:bold;font-size:10pt;\">
            <td colspan=\"6\" style=\"padding:6pt 8pt;text-align:right;border:0.5pt solid #1b5e20;\">TOTAL À VERSER AU MINISTÈRE :</td>
            <td style=\"padding:6pt 8pt;text-align:right;border:0.5pt solid #1b5e20;\">" . number_format($montantTotal, 0, ',', ' ') . " F</td>
        </tr>
    </tfoot>
</table>";

// Écriture du contenu principal
$pdf->writeHTML($enTete . $titre . $tableau, true, false, true, false, '');

// ─────────────────────────────────────────────────────────────
// QR CODE — juste sous le tableau, à droite (~2 lignes d'écart)
// ─────────────────────────────────────────────────────────────
if ($qrFile) {
    $qrSize = 45; // mm
    $pageW  = $pdf->getPageWidth();
    $marge  = 15;

    $yQr = $pdf->GetY() + 8; // ~2 lignes d'écart sous le tableau
    $xQr = $pageW - $qrSize - $marge;

    $pdf->Image($qrFile, $xQr, $yQr, $qrSize, $qrSize, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);

    // Légende discrète sous le QR
    $pdf->SetXY($xQr, $yQr + $qrSize + 1);
    $pdf->SetFont('helvetica', 'I', 6.5);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell($qrSize, 4, 'Scannez pour vérifier', 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

// ─────────────────────────────────────────────────────────────
// Sauvegarde
// ─────────────────────────────────────────────────────────────
$dir = ROOT_PATH . '/uploads/pdf/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$file = $dir . 'redevances_ministere_' . str_replace('-', '', $dateDebut) . '_' . str_replace('-', '', $dateFin) . '.pdf';
$pdf->Output($file, 'F');

if ($qrCleanup && is_file($qrCleanup)) {
    @unlink($qrCleanup);
}

header('Location: ' . url('uploads/pdf/' . basename($file)));
exit;
