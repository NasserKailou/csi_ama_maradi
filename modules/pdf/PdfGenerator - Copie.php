<?php
/**
 * PdfGenerator – Génération des reçus A5 double exemplaire
 * Utilise TCPDF (installé via Composer ou inclus en vendor)
 *
 * LOGIQUE DOUBLE EXEMPLAIRE :
 *   - Consultation : toujours 2 exemplaires sur la même page A5 (peu de lignes)
 *   - Examen       : si >= SEUIL_DOUBLE_PAGE lignes → 2 pages TCPDF distinctes
 *                    sinon → 2 exemplaires sur la même page
 *   - Pharmacie    : idem Examen
 *
 * RÈGLE ORPHELIN (type_patient = 'orphelin') :
 *   – Tous les prix affichés = 0 F
 *   – Quantités conservées (médicaments bien délivrés)
 *   – Filigrane GRATUIT sur chaque exemplaire
 *   – Montant réel conservé en BDD pour reporting bailleur
 */

if (file_exists(ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
} elseif (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf.php';
}

class PdfGenerator
{
    private PDO    $pdo;
    private array  $config   = [];
    private string $logoPath = '';

    /**
     * Nombre de lignes à partir duquel on génère 2 pages séparées.
     * En dessous : les 2 exemplaires sur la même page A5.
     */
    private const SEUIL_DOUBLE_PAGE = 6;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadConfig();
    }

    // ── Charger config système ────────────────────────────────────────────
    private function loadConfig(): void
    {
        $rows = $this->pdo->query(
            "SELECT cle, valeur FROM config_systeme WHERE isDeleted=0"
        )->fetchAll();
        foreach ($rows as $r) {
            $this->config[$r['cle']] = $r['valeur'];
        }

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

    // ═════════════════════════════════════════════════════════════════════
    //  MÉTHODES PUBLIQUES
    // ═════════════════════════════════════════════════════════════════════

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

        $isOrphelin   = ($recu['type_patient'] === 'orphelin');
        $avecCarnet   = !empty($items[0]['avec_carnet']);
        $tarifCarnet  = $avecCarnet ? ($items[0]['tarif_carnet'] ?? 0) : 0;
        $tarifConsult = $items[0]['tarif'] ?? TARIF_CONSULTATION;
        $acteLibelle  = $items[0]['libelle'] ?? 'Consultation';

        // Consultation : max 2 lignes → toujours même page
        $block = $this->buildReceiptBlockConsultation(
            $recu, $acteLibelle, $tarifConsult, $avecCarnet, $tarifCarnet, $isOrphelin
        );

        return $this->renderPdfMemePage(
            $block,
            'recu_' . $recu['numero_recu']
        );
    }

    // ── Reçu Examen ───────────────────────────────────────────────────────
    public function generateExamen(int $recuId): string
    {
        $recu = $this->getRecu($recuId);
        if (!$recu) throw new RuntimeException("Reçu {$recuId} introuvable.");

        $stmtL = $this->pdo->prepare("
            SELECT libelle, cout_total
            FROM lignes_examen WHERE recu_id=:id AND isDeleted=0
        ");
        $stmtL->execute([':id' => $recuId]);
        $lignes = $stmtL->fetchAll();

        $isOrphelin = ($recu['type_patient'] === 'orphelin');
        $block      = $this->buildReceiptBlockExamen($recu, $lignes, $isOrphelin);

        // Décision : même page ou pages séparées
        if (count($lignes) >= self::SEUIL_DOUBLE_PAGE) {
            return $this->renderPdfDeuxPages(
                $block,
                'recu_exam_' . $recu['numero_recu']
            );
        }

        return $this->renderPdfMemePage(
            $block,
            'recu_exam_' . $recu['numero_recu']
        );
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

        $isOrphelin = ($recu['type_patient'] === 'orphelin');
        $block      = $this->buildReceiptBlockPharmacie($recu, $lignes, $isOrphelin);

        // Décision : même page ou pages séparées
        if (count($lignes) >= self::SEUIL_DOUBLE_PAGE) {
            return $this->renderPdfDeuxPages(
                $block,
                'recu_pharma_' . $recu['numero_recu']
            );
        }

        return $this->renderPdfMemePage(
            $block,
            'recu_pharma_' . $recu['numero_recu']
        );
    }

    // ── État de paie laborantin PDF (inchangé) ────────────────────────────
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
        return $this->renderPdfEtatLabo(
            $content,
            'etat_labo_' . str_replace('-', '', $dateDebut)
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    //  BUILDERS DE BLOCS (retournent le HTML du bloc seul, sans wrapper)
    // ═════════════════════════════════════════════════════════════════════

    // ── Bloc Consultation ─────────────────────────────────────────────────
    private function buildReceiptBlockConsultation(
        array  $recu,
        string $acteLibelle,
        int    $tarif,
        bool   $avecCarnet,
        int    $tarifCarnet,
        bool   $isOrphelin
    ): string {
        if ($isOrphelin) {
            $prixConsult = '0 F';
            $carnetLine  = '';
            $totalAff    = 0;
            $watermark   = $this->watermarkGratuit();
        } else {
            $prixConsult = $tarif . ' F';
            $carnetLine  = $avecCarnet
                ? '<tr>
                       <td>Carnet de Soins</td>
                       <td style="text-align:right;">' . $tarifCarnet . ' F</td>
                   </tr>'
                : '';
            $totalAff  = $tarif + $tarifCarnet;
            $watermark = '';
        }

        $tableRows = "
            <tr>
                <td>{$acteLibelle}</td>
                <td style='text-align:right;'>{$prixConsult}</td>
            </tr>
            {$carnetLine}
        ";

        return $this->receiptBlock(
            $recu, $tableRows, $totalAff, $watermark, $isOrphelin
        );
    }

    // ── Bloc Examen ───────────────────────────────────────────────────────
    private function buildReceiptBlockExamen(
        array $recu,
        array $lignes,
        bool  $isOrphelin
    ): string {
        $rows = '';
        $sous = 0;
        foreach ($lignes as $l) {
            $prixAff = $isOrphelin ? '0 F' : ($l['cout_total'] . ' F');
            $rows   .= "<tr>
                            <td>{$l['libelle']}</td>
                            <td style='text-align:right;'>{$prixAff}</td>
                        </tr>";
            $sous   += $l['cout_total'];
        }

        $totalPourAff = $isOrphelin ? 0 : $sous;
        $watermark    = $isOrphelin ? $this->watermarkGratuit() : '';

        $zoneVide = '
        <tr><td colspan="2">
            <br/><b>Observations / Résultats du Laborantin :</b><br/>
            <br/>_____________________________________________<br/>
            <br/>_____________________________________________<br/>
            <br/>_____________________________________________<br/>
            <br/>_____________________________________________<br/>
            <br/><i>Cachet &amp; Signature Laborantin :</i><br/><br/>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        </td></tr>';

        $tableRows = $rows . '<tr><td colspan="2"><hr/></td></tr>' . $zoneVide;

        return $this->receiptBlock(
            $recu, $tableRows, $totalPourAff, $watermark, $isOrphelin
        );
    }

    // ── Bloc Pharmacie ────────────────────────────────────────────────────
    private function buildReceiptBlockPharmacie(
        array $recu,
        array $lignes,
        bool  $isOrphelin
    ): string {
        $rows = '<tr style="background:#e8f5e9;font-weight:bold;">
                    <td>Désignation</td>
                    <td>Forme</td>
                    <td style="text-align:center;">Qté</td>
                    <td style="text-align:right;">P.U.</td>
                    <td style="text-align:right;">Total</td>
                 </tr>';

        $total = 0;
        foreach ($lignes as $l) {
            $puAff      = $isOrphelin ? '0 F' : ($l['prix_unitaire'] . ' F');
            $totalLigne = $isOrphelin ? '0 F' : ($l['total_ligne']   . ' F');

            $rows .= "<tr>
                          <td>{$l['nom']}</td>
                          <td><small>{$l['forme']}</small></td>
                          <td style='text-align:center;'>{$l['quantite']}</td>
                          <td style='text-align:right;'>{$puAff}</td>
                          <td style='text-align:right;'>{$totalLigne}</td>
                      </tr>";

            $total += $l['total_ligne'];
        }

        $totalPourAff = $isOrphelin ? 0 : $total;
        $watermark    = $isOrphelin ? $this->watermarkGratuit() : '';

        return $this->receiptBlock(
            $recu, $rows, $totalPourAff, $watermark, $isOrphelin
        );
    }

    // ── État Labo HTML (inchangé) ─────────────────────────────────────────
    private function buildEtatLaboHtml(array $lignes, string $debut, string $fin): string
    {
        $nomCentre = $this->cfg('nom_centre', 'CSI AMA Maradi');
        $totalLabo = array_sum(array_column($lignes, 'total_labo'));

        $rows = '';
        foreach ($lignes as $l) {
            $rows .= "<tr>
                <td>{$l['libelle']}</td>
                <td style='text-align:center;'>{$l['nb_actes']}</td>
                <td style='text-align:right;'>"
                    . number_format($l['total_brut'], 0, ',', ' ')
                . " F</td>
                <td style='text-align:center;'>{$l['pourcentage_labo']}%</td>
                <td style='text-align:right;font-weight:bold;color:#2e7d32;'>"
                    . number_format($l['total_labo'], 0, ',', ' ')
                . " F</td>
            </tr>";
        }

        $logoTag = $this->logoPath
            ? "<img src=\"{$this->logoPath}\" width=\"60\" style=\"float:right;\"/>"
            : '';

        return "
        <html><body style='font-family:Arial,sans-serif;font-size:10pt;'>
        <table width='100%'><tr>
            <td>
                <h2 style='color:#2e7d32;margin:0;'>{$nomCentre}</h2>
                <p style='margin:2px 0;color:#666;'>État de paie Laborantin</p>
                <p style='margin:2px 0;'>Période : <b>"
                    . date('d/m/Y', strtotime($debut))
                    . " → "
                    . date('d/m/Y', strtotime($fin))
                . "</b></p>
            </td>
            <td style='text-align:right;vertical-align:top;'>{$logoTag}</td>
        </tr></table>
        <hr style='border-color:#2e7d32;'/>
        <table border='1' cellpadding='5' cellspacing='0' width='100%'
               style='border-collapse:collapse;'>
            <thead style='background:#e8f5e9;font-weight:bold;'>
                <tr>
                    <th>Examen</th><th>Nb actes</th><th>Total brut</th>
                    <th>% Labo</th><th>Montant Labo</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
            <tfoot>
                <tr style='background:#2e7d32;color:#fff;font-weight:bold;'>
                    <td colspan='4' style='text-align:right;padding:6px;'>
                        TOTAL DÛ AU LABORANTIN :
                    </td>
                    <td style='text-align:right;padding:6px;'>"
                        . number_format($totalLabo, 0, ',', ' ')
                    . " F</td>
                </tr>
            </tfoot>
        </table>
        <br/>
        <p style='text-align:right;margin-top:30px;'>
            Signature de l'Administrateur : ___________________________
        </p>
        </body></html>";
    }

    // ═════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVÉS
    // ═════════════════════════════════════════════════════════════════════

    // ── Filigrane GRATUIT ─────────────────────────────────────────────────
    private function watermarkGratuit(): string
    {
        return '<div style="text-align:center;font-size:28pt;color:#ffcccc;'
             . 'font-weight:bold;letter-spacing:4px;margin:6px 0;'
             . 'border:2px dashed #ffcccc;padding:4px;">'
             . 'GRATUIT'
             . '</div>';
    }

    // ── Bloc reçu unique (HTML pur, sans <html><body>) ────────────────────
    // Retourne uniquement le contenu du reçu.
    // Le wrapper (<html><body> + mise en page) est appliqué par les méthodes
    // renderPdfMemePage() et renderPdfDeuxPages().
    private function receiptBlock(
        array  $recu,
        string $tableRows,
        int    $total,
        string $watermark,
        bool   $isOrphelin = false
    ): string {
        $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
        $nomCentre  = $this->cfg('nom_centre',  'CSI AMA Maradi');
        $adresse    = $this->cfg('adresse',      'Maradi – Niger');
        $tel        = $this->cfg('telephone',    '');
        $piedPage   = $this->cfg('pied_de_page', 'Merci de votre visite.');
        $date       = date('d/m/Y H:i', strtotime($recu['whendone']));

        $logoTag = $this->logoPath
            ? "<img src=\"{$this->logoPath}\" width=\"50\""
              . " style=\"float:right;margin-bottom:4px;\"/>"
            : '';

        $totalStr = $isOrphelin
            ? '<font color="#d32f2f"><b>0 F &nbsp;(GRATUIT)</b></font>'
            : '<b>' . number_format($total, 0, ',', ' ') . ' F</b>';

        $provenanceLine = $recu['provenance']
            ? "<tr><td colspan='2'><small>Provenance: {$recu['provenance']}</small></td></tr>"
            : '';

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
            {$provenanceLine}
        </table>
        {$watermark}
        <table border='1' cellpadding='3' cellspacing='0' width='100%'
               style='border-collapse:collapse;font-size:9pt;'>
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
                <td style='text-align:right;'>
                    <small>Signature Percepteur: ___________</small>
                </td>
            </tr>
        </table>
        <hr style='border-color:#2e7d32;margin:6px 0;'/>
        ";
    }

    // ── Récupérer données reçu + patient ──────────────────────────────────
    private function getRecu(int $recuId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   p.nom        AS patient_nom,
                   p.telephone,
                   p.provenance,
                   p.sexe,
                   p.age,
                   p.est_orphelin
            FROM recus r
            JOIN patients p ON p.id = r.patient_id
            WHERE r.id = :id AND r.isDeleted = 0
            LIMIT 1
        ");
        $stmt->execute([':id' => $recuId]);
        return $stmt->fetch() ?: null;
    }

    // ═════════════════════════════════════════════════════════════════════
    //  RENDUS PDF — 3 méthodes distinctes et claires
    // ═════════════════════════════════════════════════════════════════════

    // ── Mode 1 : 2 exemplaires sur la MÊME page A5 ────────────────────────
    private function renderPdfMemePage(string $block, string $filename): string
    {
        $html = $this->htmlMemePage($block);

        if (!class_exists('TCPDF')) {
            return $this->fallbackHtml($html, $filename);
        }

        $pdf = $this->newTcpdf();
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $this->savePdf($pdf, $filename);
    }

    // ── Mode 2 : 1 exemplaire par page A5 (2 pages au total) ─────────────
    // Chaque AddPage() crée une vraie nouvelle page TCPDF.
    // C'est la seule façon fiable d'obtenir 2 pages distinctes avec TCPDF.
    private function renderPdfDeuxPages(string $block, string $filename): string
    {
        if (!class_exists('TCPDF')) {
            return $this->fallbackHtmlDeuxPages($block, $filename);
        }

        $pdf = $this->newTcpdf();

        // ── Page 1 : Exemplaire Percepteur ──────────────────────────────
        $pdf->AddPage();
        $pdf->writeHTML($this->htmlUnExemplaire($block, 'Exemplaire Percepteur'),
                        true, false, true, false, '');

        // ── Page 2 : Exemplaire Patient ─────────────────────────────────
        $pdf->AddPage();
        $pdf->writeHTML($this->htmlUnExemplaire($block, 'Exemplaire Patient'),
                        true, false, true, false, '');

        return $this->savePdf($pdf, $filename);
    }

    // ── Mode 3 : État Labo (page unique, pas de double exemplaire) ────────
    private function renderPdfEtatLabo(string $html, string $filename): string
    {
        if (!class_exists('TCPDF')) {
            return $this->fallbackHtml($html, $filename);
        }

        $pdf = $this->newTcpdf();
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $this->savePdf($pdf, $filename);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  WRAPPERS HTML
    // ═════════════════════════════════════════════════════════════════════

    // ── HTML : 2 exemplaires sur la même page (séparateur pointillé) ──────
    private function htmlMemePage(string $block): string
    {
        return "
        <html><head><style>
            body  { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; }
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

    // ── HTML : 1 seul exemplaire (utilisé pour chaque page séparée) ───────
    private function htmlUnExemplaire(string $block, string $label): string
    {
        return "
        <html><head><style>
            body  { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; }
            table { border-color: #ccc; }
        </style></head>
        <body>
            <div style='padding:8px;border:1px dashed #999;'>
                <p style='text-align:center;color:#999;font-size:7pt;margin:0 0 4px;'>
                    ✂ {$label} ✂
                </p>
                {$block}
            </div>
        </body></html>";
    }

    // ═════════════════════════════════════════════════════════════════════
    //  HELPERS TCPDF
    // ═════════════════════════════════════════════════════════════════════

    // ── Créer une instance TCPDF configurée ───────────────────────────────
    private function newTcpdf(): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);
        $pdf->SetCreator('CSI AMA Maradi');
        $pdf->SetAuthor('Système CSI');
        $pdf->SetAutoPageBreak(true, 5);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(5, 5, 5);
        return $pdf;
    }

    // ── Sauvegarder le PDF et retourner le chemin ─────────────────────────
    private function savePdf(TCPDF $pdf, string $filename): string
    {
        $dir = ROOT_PATH . '/uploads/pdf/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . $filename . '_' . date('YmdHis') . '.pdf';
        $pdf->Output($file, 'F');
        return $file;
    }

    // ═════════════════════════════════════════════════════════════════════
    //  FALLBACKS HTML (si TCPDF absent)
    // ═════════════════════════════════════════════════════════════════════

    // ── Fallback : même page ──────────────────────────────────────────────
    private function fallbackHtml(string $html, string $filename): string
    {
        $dir  = ROOT_PATH . '/uploads/pdf/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . $filename . '_' . date('YmdHis') . '.html';

        $printHtml = str_replace(
            '<body>',
            '<body onload="window.print()">
            <style>
            @page  { size: A5; margin: 8mm; }
            @media print { .no-print { display: none !important; } }
            body   { font-family: Arial, sans-serif; font-size: 9pt; }
            </style>
            <div class="no-print"
                 style="padding:10px;background:#e8f5e9;text-align:center;">
                <button onclick="window.print()"
                        style="background:#2e7d32;color:#fff;border:none;
                               padding:8px 20px;border-radius:6px;
                               cursor:pointer;font-size:14px;">
                    🖨️ Imprimer
                </button>
                <button onclick="window.close()"
                        style="background:#999;color:#fff;border:none;
                               padding:8px 20px;border-radius:6px;
                               cursor:pointer;margin-left:8px;">
                    Fermer
                </button>
            </div>',
            $html
        );

        file_put_contents($file, $printHtml);
        return $file;
    }

    // ── Fallback : 2 pages séparées (via CSS print) ───────────────────────
    private function fallbackHtmlDeuxPages(string $block, string $filename): string
    {
        $dir  = ROOT_PATH . '/uploads/pdf/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . $filename . '_' . date('YmdHis') . '.html';

        $html = "
        <html><head>
        <style>
        @page  { size: A5; margin: 8mm; }
        @media print {
            .no-print  { display: none !important; }
            .new-page  { page-break-before: always; }
        }
        body   { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; }
        table  { border-color: #ccc; }
        </style>
        </head>
        <body onload='window.print()'>
            <div class='no-print' style='padding:10px;background:#e8f5e9;text-align:center;'>
                <button onclick='window.print()'
                        style='background:#2e7d32;color:#fff;border:none;
                               padding:8px 20px;border-radius:6px;
                               cursor:pointer;font-size:14px;'>
                    🖨️ Imprimer
                </button>
                <button onclick='window.close()'
                        style='background:#999;color:#fff;border:none;
                               padding:8px 20px;border-radius:6px;
                               cursor:pointer;margin-left:8px;'>
                    Fermer
                </button>
            </div>

            <!-- Page 1 : Exemplaire Percepteur -->
            <div style='padding:8px;border:1px dashed #999;'>
                <p style='text-align:center;color:#999;font-size:7pt;margin:0 0 4px;'>
                    ✂ Exemplaire Percepteur ✂
                </p>
                {$block}
            </div>

            <!-- Page 2 : Exemplaire Patient -->
            <div class='new-page' style='padding:8px;border:1px dashed #999;'>
                <p style='text-align:center;color:#999;font-size:7pt;margin:0 0 4px;'>
                    ✂ Exemplaire Patient ✂
                </p>
                {$block}
            </div>
        </body></html>";

        file_put_contents($file, $html);
        return $file;
    }
}
