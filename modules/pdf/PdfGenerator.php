<?php
/**
 * PdfGenerator – Génération des reçus A5 double exemplaire
 * Utilise TCPDF (installé via Composer ou inclus en vendor)
 * Format : A5 paysage (2 exemplaires côte à côte) OU A4 portrait (2 exemplaires empilés)
 */

// Charger TCPDF via Composer ou fallback manuel
if (file_exists(ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
} elseif (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf.php';
}

class PdfGenerator
{
    private PDO    $pdo;
    private array  $config = [];
    private string $logoPath = '';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadConfig();
    }

    // ── Charger config système ────────────────────────────────────────────
    private function loadConfig(): void
    {
        $rows = $this->pdo->query("SELECT cle, valeur FROM config_systeme WHERE isDeleted=0")->fetchAll();
        foreach ($rows as $r) {
            $this->config[$r['cle']] = $r['valeur'];
        }

        // Logo DirectAid (logo par défaut ou configuré)
        $logoFile = $this->config['logo_filename'] ?? '';
        if ($logoFile && file_exists(ROOT_PATH . '/uploads/logos/' . $logoFile)) {
            $this->logoPath = ROOT_PATH . '/uploads/logos/' . $logoFile;
        } elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_csi.png')) {
            $this->logoPath = ROOT_PATH . '/uploads/logos/logo_csi.png';
        }
    }

    private function cfg(string $key, string $default = ''): string
    {
        return $this->config[$key] ?? $default;
    }

    // ── Reçu Consultation ─────────────────────────────────────────────────
    public function generateConsultation(int $recuId): string
    {
        $recu = $this->getRecu($recuId);
        if (!$recu) throw new RuntimeException("Reçu {$recuId} introuvable.");

        $lignes = $this->pdo->prepare("
            SELECT libelle, tarif, est_gratuit, avec_carnet, tarif_carnet
            FROM lignes_consultation WHERE recu_id=:id AND isDeleted=0
        ");
        $lignes->execute([':id' => $recuId]);
        $items = $lignes->fetchAll();

        $isOrphelin   = in_array($recu['type_patient'], ['orphelin', 'acte_gratuit']);
        $avecCarnet   = !empty($items[0]['avec_carnet']);
        $tarifCarnet  = $avecCarnet ? ($items[0]['tarif_carnet'] ?? 0) : 0;
        $tarifConsult = $items[0]['tarif'] ?? TARIF_CONSULTATION;
        $acteLibelle  = $items[0]['libelle'] ?? 'Consultation';

        $content = $this->buildConsultationHtml(
            $recu, $acteLibelle, $tarifConsult, $avecCarnet, $tarifCarnet, $isOrphelin
        );

        return $this->renderPdf($content, 'recu_' . $recu['numero_recu']);
    }

    // ── Reçu Examen ───────────────────────────────────────────────────────
    public function generateExamen(int $recuId): string
    {
        $recu = $this->getRecu($recuId);
        if (!$recu) throw new RuntimeException("Reçu {$recuId} introuvable.");

        $stmtL = $this->pdo->prepare("
            SELECT libelle, cout_total FROM lignes_examen WHERE recu_id=:id AND isDeleted=0
        ");
        $stmtL->execute([':id' => $recuId]);
        $lignes = $stmtL->fetchAll();

        $content = $this->buildExamenHtml($recu, $lignes);
        return $this->renderPdf($content, 'recu_exam_' . $recu['numero_recu']);
    }

    // ── Reçu Pharmacie ────────────────────────────────────────────────────
    public function generatePharmacie(int $recuId): string
    {
        $recu = $this->getRecu($recuId);
        if (!$recu) throw new RuntimeException("Reçu {$recuId} introuvable.");

        $stmtL = $this->pdo->prepare("
            SELECT nom, forme, quantite, prix_unitaire, total_ligne
            FROM lignes_pharmacie WHERE recu_id=:id AND isDeleted=0
        ");
        $stmtL->execute([':id' => $recuId]);
        $lignes = $stmtL->fetchAll();

        $content = $this->buildPharmacieHtml($recu, $lignes);
        return $this->renderPdf($content, 'recu_pharma_' . $recu['numero_recu']);
    }

    // ── État de paie laborantin PDF ───────────────────────────────────────
    public function generateEtatLabo(string $dateDebut, string $dateFin): string
    {
        $stmt = $this->pdo->prepare("
            SELECT le.libelle, COUNT(*) AS nb_actes,
                   SUM(le.cout_total) AS total_brut,
                   le.pourcentage_labo,
                   SUM(le.montant_labo) AS total_labo
            FROM lignes_examen le
            JOIN recus r ON r.id = le.recu_id
            WHERE r.isDeleted = 0 AND le.isDeleted = 0
              AND DATE(r.whendone) BETWEEN :deb AND :fin
            GROUP BY le.examen_id, le.libelle, le.pourcentage_labo
            ORDER BY le.libelle
        ");
        $stmt->execute([':deb' => $dateDebut, ':fin' => $dateFin]);
        $lignes = $stmt->fetchAll();

        $content = $this->buildEtatLaboHtml($lignes, $dateDebut, $dateFin);
        return $this->renderPdf($content, 'etat_labo_' . str_replace('-', '', $dateDebut), false);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  BUILDERS HTML (converti en PDF via TCPDF writeHTML)
    // ═════════════════════════════════════════════════════════════════════

    private function buildConsultationHtml(array $recu, string $acteLibelle, int $tarif,
                                            bool $avecCarnet, int $tarifCarnet, bool $isOrphelin): string
    {
        $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
        $nomCentre  = $this->cfg('nom_centre', 'CSI AMA Maradi');
        $adresse    = $this->cfg('adresse', 'Maradi – Niger');
        $tel        = $this->cfg('telephone', '');
        $piedPage   = $this->cfg('pied_de_page', 'Merci de votre visite.');
        $totalAff   = $isOrphelin ? 0 : ($tarif + $tarifCarnet);
        $date       = date('d/m/Y H:i', strtotime($recu['whendone']));

        $gratuitLabel = $isOrphelin ? ' <font color="#d32f2f"><b>(GRATUIT)</b></font>' : '';
        $watermark    = $isOrphelin
            ? '<div style="text-align:center;font-size:36pt;color:#ffcccc;font-weight:bold;transform:rotate(-30deg);margin:8px 0;">GRATUIT</div>'
            : '';

        $carnetLine = '';
        if ($avecCarnet && !$isOrphelin) {
            $carnetLine = '<tr><td>Carnet de Soins</td><td style="text-align:right;">' . $tarifCarnet . ' F</td></tr>';
        }

        return $this->wrapDouble($this->receiptBlock($numFormate, $date, $recu, $nomCentre, $adresse, $tel,
            "<tr><td>{$acteLibelle}</td><td style='text-align:right;'>{$tarif} F{$gratuitLabel}</td></tr>
             {$carnetLine}",
            $totalAff, $piedPage, $watermark, $isOrphelin));
    }

    private function buildExamenHtml(array $recu, array $lignes): string
    {
        $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
        $nomCentre  = $this->cfg('nom_centre', 'CSI AMA Maradi');
        $adresse    = $this->cfg('adresse', 'Maradi – Niger');
        $tel        = $this->cfg('telephone', '');
        $piedPage   = $this->cfg('pied_de_page', 'Merci de votre visite.');
        $date       = date('d/m/Y H:i', strtotime($recu['whendone']));

        $rows = '';
        $sous = 0;
        foreach ($lignes as $l) {
            $rows .= "<tr><td>{$l['libelle']}</td><td style='text-align:right;'>{$l['cout_total']} F</td></tr>";
            $sous += $l['cout_total'];
        }

        // Zone vide pour le laborantin (observations + cachet)
        $zoneVide = '
        <tr><td colspan="2"><br/><b>Observations / Résultats du Laborantin :</b><br/>
        <br/>_____________________________________________<br/>
        <br/>_____________________________________________<br/>
        <br/>_____________________________________________<br/>
        <br/>_____________________________________________<br/>
        <br/><i>Cachet &amp; Signature Laborantin :</i>
        <br/><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        </td></tr>';

        $body  = $rows . '<tr><td colspan="2"><hr/></td></tr>' . $zoneVide;
        return $this->wrapDouble($this->receiptBlock($numFormate, $date, $recu, $nomCentre, $adresse, $tel,
            $body, $sous, $piedPage, '', false));
    }

    private function buildPharmacieHtml(array $recu, array $lignes): string
    {
        $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
        $nomCentre  = $this->cfg('nom_centre', 'CSI AMA Maradi');
        $adresse    = $this->cfg('adresse', 'Maradi – Niger');
        $tel        = $this->cfg('telephone', '');
        $piedPage   = $this->cfg('pied_de_page', 'Merci de votre visite.');
        $date       = date('d/m/Y H:i', strtotime($recu['whendone']));

        $rows  = '<tr style="background:#e8f5e9;font-weight:bold;">
                    <td>Désignation</td><td>Forme</td>
                    <td style="text-align:center;">Qté</td>
                    <td style="text-align:right;">P.U.</td>
                    <td style="text-align:right;">Total</td>
                  </tr>';
        $total = 0;
        foreach ($lignes as $l) {
            $rows .= "<tr>
                <td>{$l['nom']}</td>
                <td><small>{$l['forme']}</small></td>
                <td style='text-align:center;'>{$l['quantite']}</td>
                <td style='text-align:right;'>{$l['prix_unitaire']} F</td>
                <td style='text-align:right;'>{$l['total_ligne']} F</td>
              </tr>";
            $total += $l['total_ligne'];
        }

        return $this->wrapDouble($this->receiptBlock($numFormate, $date, $recu, $nomCentre, $adresse, $tel,
            $rows, $total, $piedPage, '', false));
    }

    private function buildEtatLaboHtml(array $lignes, string $debut, string $fin): string
    {
        $nomCentre = $this->cfg('nom_centre', 'CSI AMA Maradi');
        $totalLabo = array_sum(array_column($lignes, 'total_labo'));

        $rows = '';
        foreach ($lignes as $l) {
            $rows .= "<tr>
                <td>{$l['libelle']}</td>
                <td style='text-align:center;'>{$l['nb_actes']}</td>
                <td style='text-align:right;'>" . number_format($l['total_brut'],0,',',' ') . " F</td>
                <td style='text-align:center;'>{$l['pourcentage_labo']}%</td>
                <td style='text-align:right;font-weight:bold;color:#2e7d32;'>" . number_format($l['total_labo'],0,',',' ') . " F</td>
            </tr>";
        }

        $logoTag = $this->logoPath ? "<img src=\"{$this->logoPath}\" width=\"60\" style=\"float:right;\"/>" : '';

        return "
        <html><body style='font-family:Arial,sans-serif;font-size:10pt;'>
        <table width='100%'><tr>
            <td><h2 style='color:#2e7d32;margin:0;'>{$nomCentre}</h2>
                <p style='margin:2px 0;color:#666;'>État de paie Laborantin</p>
                <p style='margin:2px 0;'>Période : <b>" . date('d/m/Y',strtotime($debut)) . " → " . date('d/m/Y',strtotime($fin)) . "</b></p>
            </td>
            <td style='text-align:right;vertical-align:top;'>{$logoTag}</td>
        </tr></table>
        <hr style='border-color:#2e7d32;'/>
        <table border='1' cellpadding='5' cellspacing='0' width='100%' style='border-collapse:collapse;'>
            <thead style='background:#e8f5e9;font-weight:bold;'>
                <tr>
                    <th>Examen</th><th>Nb actes</th><th>Total brut</th><th>% Labo</th><th>Montant Labo</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
            <tfoot>
                <tr style='background:#2e7d32;color:#fff;font-weight:bold;'>
                    <td colspan='4' style='text-align:right;padding:6px;'>TOTAL DÛ AU LABORANTIN :</td>
                    <td style='text-align:right;padding:6px;'>" . number_format($totalLabo,0,',',' ') . " F</td>
                </tr>
            </tfoot>
        </table>
        <br/>
        <p style='text-align:right;margin-top:30px;'>Signature de l'Administrateur : ___________________________</p>
        </body></html>";
    }

    // ── Bloc reçu unique ──────────────────────────────────────────────────
    private function receiptBlock(string $numFormate, string $date, array $recu,
                                   string $nomCentre, string $adresse, string $tel,
                                   string $tableRows, int $total, string $piedPage,
                                   string $watermark, bool $isOrphelin = false): string
    {
        $logoTag  = $this->logoPath
            ? "<img src=\"{$this->logoPath}\" width=\"50\" style=\"float:right;margin-bottom:4px;\"/>"
            : '';
        $totalStr = $isOrphelin
            ? '<font color="#d32f2f"><b>0 F (GRATUIT)</b></font>'
            : "<b>" . number_format($total, 0, ',', ' ') . " F</b>";

        return "
        <table width='100%' style='border-bottom:2px solid #2e7d32;margin-bottom:4px;'>
            <tr>
                <td>
                    <b style='font-size:11pt;color:#2e7d32;'>{$nomCentre}</b><br/>
                    <small>{$adresse}</small><br/>
                    <small>Tél: {$tel}</small>
                </td>
                <td style='text-align:right;vertical-align:top;'>{$logoTag}</td>
            </tr>
        </table>
        <table width='100%' style='margin-bottom:4px;'>
            <tr>
                <td><b>Reçu {$numFormate}</b></td>
                <td style='text-align:right;color:#666;'><small>{$date}</small></td>
            </tr>
            <tr>
                <td><b>Patient:</b> {$recu['patient_nom']}</td>
                <td style='text-align:right;'><small>Tél: {$recu['telephone']}</small></td>
            </tr>
            " . ($recu['provenance'] ? "<tr><td colspan='2'><small>Provenance: {$recu['provenance']}</small></td></tr>" : "") . "
        </table>
        {$watermark}
        <table border='1' cellpadding='3' cellspacing='0' width='100%' style='border-collapse:collapse;font-size:9pt;'>
            {$tableRows}
            <tr style='background:#e8f5e9;'>
                <td style='text-align:right;padding:4px;'><b>TOTAL :</b></td>
                <td style='text-align:right;padding:4px;'>{$totalStr}</td>
            </tr>
        </table>
        <br/>
        <table width='100%'>
            <tr>
                <td><small>{$piedPage}</small></td>
                <td style='text-align:right;'><small>Signature Percepteur: ___________</small></td>
            </tr>
        </table>
        <hr style='border-color:#2e7d32;margin:6px 0;'/>
        ";
    }

    // ── Doubler le reçu (2 exemplaires sur même page A5) ──────────────────
    private function wrapDouble(string $block): string
    {
        return "
        <html><head><style>
            body { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; }
            table { border-color: #ccc; }
        </style></head>
        <body>
            <div style='padding:8px;border:1px dashed #999;margin-bottom:6px;'>
                <p style='text-align:center;color:#999;font-size:7pt;margin:0 0 4px;'>
                    ✂ Exemplaire Percepteur ✂
                </p>
                {$block}
            </div>
            <div style='padding:8px;border:1px dashed #999;'>
                <p style='text-align:center;color:#999;font-size:7pt;margin:0 0 4px;'>
                    ✂ Exemplaire Patient ✂
                </p>
                {$block}
            </div>
        </body></html>";
    }

    // ── Récupérer données reçu + patient ──────────────────────────────────
    private function getRecu(int $recuId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, p.nom AS patient_nom, p.telephone, p.provenance, p.sexe, p.age
            FROM recus r JOIN patients p ON p.id = r.patient_id
            WHERE r.id = :id AND r.isDeleted = 0
            LIMIT 1
        ");
        $stmt->execute([':id' => $recuId]);
        return $stmt->fetch() ?: null;
    }

    // ── Rendu PDF via TCPDF ───────────────────────────────────────────────
    private function renderPdf(string $html, string $filename, bool $doubleA5 = true): string
    {
        if (!class_exists('TCPDF')) {
            // Fallback : écrire le HTML dans un fichier .html temporaire
            return $this->fallbackHtml($html, $filename);
        }

        $pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);
        $pdf->SetCreator('CSI AMA Maradi');
        $pdf->SetAuthor('Système CSI');
        $pdf->SetTitle('Reçu ' . $filename);
        $pdf->SetAutoPageBreak(true, 5);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(5, 5, 5);
        $pdf->AddPage();

        $pdf->writeHTML($html, true, false, true, false, '');

        $dir  = ROOT_PATH . '/uploads/pdf/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . $filename . '_' . date('YmdHis') . '.pdf';
        $pdf->Output($file, 'F');
        return $file;
    }

    // ── Fallback HTML (si TCPDF absent) ──────────────────────────────────
    private function fallbackHtml(string $html, string $filename): string
    {
        $dir  = ROOT_PATH . '/uploads/pdf/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . $filename . '_' . date('YmdHis') . '.html';

        // Ajouter CSS print et auto-print
        $printHtml = str_replace(
            '<body>',
            '<body onload="window.print()">
            <style>
            @page { size: A5; margin: 8mm; }
            @media print { .no-print { display: none !important; } }
            body { font-family: Arial, sans-serif; font-size: 9pt; }
            </style>
            <div class="no-print" style="padding:10px;background:#e8f5e9;text-align:center;">
                <button onclick="window.print()" style="background:#2e7d32;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:14px;">
                    🖨️ Imprimer
                </button>
                <button onclick="window.close()" style="background:#999;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;margin-left:8px;">
                    Fermer
                </button>
            </div>',
            $html
        );

        file_put_contents($file, $printHtml);
        return $file;
    }
}
