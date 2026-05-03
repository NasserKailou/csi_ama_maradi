<?php
/**
 * PdfGenerator – Génération des reçus A5 (CSI DirectAid Maradi)
 *
 * RÈGLES :
 *  - Consultation : 2 exemplaires (Percepteur + Patient) sur la même page A5
 *  - Examen       : UN SEUL exemplaire (bon de prescription pour le laborantin)
 *  - Pharmacie    : 2 exemplaires (Percepteur + Patient)
 *                   → si trop de lignes, 2 pages séparées
 *
 * RÈGLE ORPHELIN (type_patient = 'orphelin') :
 *  – Tous les prix unitaires affichés sont BARRÉS (rayés)
 *  – Le total affiché est 0 F en rouge
 *  – Pas de filigrane "GRATUIT"
 *  – Montants réels conservés en BDD pour reporting bailleur
 *
 * QR CODE : utilise TCPDF2DBarcode (inclus nativement dans TCPDF)
 */

// ── Chargement de TCPDF (classe principale) ───────────────────────────────
if (file_exists(ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
} elseif (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf.php';
}

// ── Chargement de TCPDF2DBarcode (générateur de QR codes) ─────────────────
// Cette classe est livrée nativement avec TCPDF, dans le même dossier.
if (file_exists(ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php')) {
    require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
} elseif (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf_barcodes_2d.php')) {
    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf_barcodes_2d.php';
}



class PdfGenerator
{
    private PDO    $pdo;
    private array  $config           = [];
    private string $logoMinistere    = '';
    private string $logoDirectAid    = '';

    /** Au-delà de ce nombre de lignes, on bascule en 2 pages séparées. */
    private const SEUIL_DOUBLE_PAGE = 8;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $rows = $this->pdo->query("SELECT cle, valeur FROM config_systeme WHERE isDeleted=0")->fetchAll();
        foreach ($rows as $r) {
            $this->config[$r['cle']] = $r['valeur'];
        }

        // Logo Ministère de la Santé (gauche)
        $logoMin = $this->config['logo_ministere'] ?? '';
        if ($logoMin && file_exists(ROOT_PATH . '/uploads/logos/' . $logoMin)) {
            $this->logoMinistere = ROOT_PATH . '/uploads/logos/' . $logoMin;
        } elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_ministere.png')) {
            $this->logoMinistere = ROOT_PATH . '/uploads/logos/logo_ministere.png';
        }

        // Logo DirectAid (droite) — utilise la config existante "logo_filename"
        $logoDA = $this->config['logo_filename'] ?? '';
        if ($logoDA && file_exists(ROOT_PATH . '/uploads/logos/' . $logoDA)) {
            $this->logoDirectAid = ROOT_PATH . '/uploads/logos/' . $logoDA;
        } elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_directaid.png')) {
            $this->logoDirectAid = ROOT_PATH . '/uploads/logos/logo_directaid.png';
        } elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_csi.png')) {
            $this->logoDirectAid = ROOT_PATH . '/uploads/logos/logo_csi.png';
        }
    }

    private function cfg(string $key, string $default = ''): string
    {
        return $this->config[$key] ?? $default;
    }

    // ═════════════════════════════════════════════════════════════════════
    //  MÉTHODES PUBLIQUES
    // ═════════════════════════════════════════════════════════════════════

    public function generateConsultation(int $recuId): string
    {
        $recu = $this->getRecu($recuId);
        if (!$recu) throw new RuntimeException("Reçu {$recuId} introuvable.");

        $stmt = $this->pdo->prepare("
            SELECT libelle, tarif, est_gratuit, avec_carnet, tarif_carnet
            FROM lignes_consultation WHERE recu_id=:id AND isDeleted=0
        ");
        $stmt->execute([':id' => $recuId]);
        $items = $stmt->fetchAll();

        $isOrphelin   = ($recu['type_patient'] === 'orphelin');
        $avecCarnet   = !empty($items[0]['avec_carnet']);
        $tarifCarnet  = $avecCarnet ? (int)($items[0]['tarif_carnet'] ?? 0) : 0;
        $tarifConsult = (int)($items[0]['tarif'] ?? (defined('TARIF_CONSULTATION') ? TARIF_CONSULTATION : 300));
        $acteLibelle  = $items[0]['libelle'] ?? 'Consultation';

        $block = $this->buildBlocConsultation(
            $recu, $acteLibelle, $tarifConsult, $avecCarnet, $tarifCarnet, $isOrphelin
        );

        return $this->renderDoubleExemplaire($block, 'recu_consult_' . $recu['numero_recu']);
    }

    public function generateExamens(int $recuId): string
    {
        $recu = $this->getRecu($recuId);
        if (!$recu) throw new RuntimeException("Reçu {$recuId} introuvable.");

        $stmt = $this->pdo->prepare("
            SELECT libelle, cout_total
            FROM lignes_examen WHERE recu_id=:id AND isDeleted=0
        ");
        $stmt->execute([':id' => $recuId]);
        $lignes = $stmt->fetchAll();

        $isOrphelin = ($recu['type_patient'] === 'orphelin');
        $block = $this->buildBlocExamen($recu, $lignes, $isOrphelin);

        // ⚠️ Examens = UN SEUL exemplaire (bon laborantin)
        return $this->renderSimpleExemplaire($block, 'recu_exam_' . $recu['numero_recu']);
    }

    /** Alias pour rétrocompatibilité éventuelle. */
    public function generateExamen(int $recuId): string
    {
        return $this->generateExamens($recuId);
    }

    public function generatePharmacie(int $recuId): string
    {
        $recu = $this->getRecu($recuId);
        if (!$recu) throw new RuntimeException("Reçu {$recuId} introuvable.");

        $stmt = $this->pdo->prepare("
            SELECT nom, forme, quantite, prix_unitaire, total_ligne
            FROM lignes_pharmacie WHERE recu_id=:id AND isDeleted=0
        ");
        $stmt->execute([':id' => $recuId]);
        $lignes = $stmt->fetchAll();

        $isOrphelin = ($recu['type_patient'] === 'orphelin');
        $block = $this->buildBlocPharmacie($recu, $lignes, $isOrphelin);

        if (count($lignes) >= self::SEUIL_DOUBLE_PAGE) {
            return $this->renderDeuxPages($block, 'recu_pharma_' . $recu['numero_recu']);
        }
        return $this->renderDoubleExemplaire($block, 'recu_pharma_' . $recu['numero_recu']);
    }

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
        return $this->renderEtatLabo($content, 'etat_labo_' . str_replace('-', '', $dateDebut));
    }

    // ═════════════════════════════════════════════════════════════════════
    //  EN-TÊTE OFFICIEL (République du Niger)
    // ═════════════════════════════════════════════════════════════════════

   private function buildEntete(): string
{
    // Logos très visibles
    $logoSize = 35; // mm — bien visible (était 28)
    
    $logoMinTag = $this->logoMinistere
        ? "<img src=\"{$this->logoMinistere}\" width=\"{$logoSize}\" height=\"{$logoSize}\"/>"
        : "<div style=\"width:{$logoSize}mm;height:{$logoSize}mm;\"></div>";

    $logoDaTag = $this->logoDirectAid
        ? "<img src=\"{$this->logoDirectAid}\" width=\"{$logoSize}\" height=\"{$logoSize}\"/>"
        : "<div style=\"width:{$logoSize}mm;height:{$logoSize}mm;\"></div>";

    return '
    <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1.5pt solid #2e7d32;padding-bottom:4pt;">
        <tr>
            <td width="26%" style="text-align:center;vertical-align:middle;">' . $logoMinTag . '</td>
            <td width="48%" style="text-align:center;vertical-align:middle;line-height:1.4;">
                <span style="font-size:9.5pt;font-weight:bold;">RÉPUBLIQUE DU NIGER</span><br/>
                <span style="font-size:7pt;font-style:italic;color:#555;">Fraternité – Travail – Progrès</span><br/>
                <span style="font-size:8pt;">Ministère de la Santé Publique</span><br/>
                <span style="font-size:8pt;">District Sanitaire de Maradi</span><br/>
                <span style="font-size:10pt;font-weight:bold;color:#2e7d32;">CSI DIRECTAID DE MARADI</span>
            </td>
            <td width="26%" style="text-align:center;vertical-align:middle;">' . $logoDaTag . '</td>
        </tr>
    </table>';
}


    // ═════════════════════════════════════════════════════════════════════
    //  BUILDERS DE BLOCS
    // ═════════════════════════════════════════════════════════════════════

    /** Helper : montant barré pour orphelin / normal pour les autres */
    private function fmtMontant(int $montant, bool $isOrphelin): string
    {
        if ($isOrphelin) {
            return '<span style="color:#999;text-decoration:line-through;">'
                . number_format($montant, 0, ',', ' ') . ' F</span>';
        }
        return number_format($montant, 0, ',', ' ') . ' F';
    }

    private function buildBlocConsultation(
        array  $recu,
        string $acteLibelle,
        int    $tarif,
        bool   $avecCarnet,
        int    $tarifCarnet,
        bool   $isOrphelin
    ): string {
        $prixConsultAff = $this->fmtMontant($tarif, $isOrphelin);

        $carnetLine = '';
        if ($avecCarnet) {
            $prixCarnetAff = $this->fmtMontant($tarifCarnet, $isOrphelin);
            $carnetLine = "
                <tr>
                    <td style=\"padding:3pt 4pt;\">Carnet de Soins</td>
                    <td style=\"padding:3pt 4pt;text-align:right;\">{$prixCarnetAff}</td>
                </tr>";
        }

        $rows = "
            <tr>
                <td style=\"padding:3pt 4pt;\">{$acteLibelle}</td>
                <td style=\"padding:3pt 4pt;text-align:right;\">{$prixConsultAff}</td>
            </tr>
            {$carnetLine}";

        $totalAff = $isOrphelin ? 0 : ($tarif + $tarifCarnet);

        return $this->blocRecu($recu, 'CONSULTATION', $rows, $totalAff, $isOrphelin, false);
    }

    private function buildBlocExamen(array $recu, array $lignes, bool $isOrphelin): string
    {
        $rows = '
            <tr style="background:#fff3e0;font-weight:bold;">
                <td style="padding:3pt 4pt;width:75%;">Examen prescrit</td>
                <td style="padding:3pt 4pt;text-align:right;">Coût</td>
            </tr>';
        $total = 0;
        foreach ($lignes as $l) {
            $cout    = (int)$l['cout_total'];
            $coutAff = $this->fmtMontant($cout, $isOrphelin);
            $rows   .= "<tr>
                <td style='padding:3pt 4pt;'>{$l['libelle']}</td>
                <td style='padding:3pt 4pt;text-align:right;'>{$coutAff}</td>
            </tr>";
            $total += $cout;
        }

        // Zone d'observations pour le laborantin
        $zoneObs = '
        <br/>
        <table width="100%" cellpadding="2" cellspacing="0" style="border:1pt solid #999;">
            <tr><td style="padding:4pt;background:#fff8e1;font-weight:bold;font-size:8pt;">
                Observations / Résultats du Laborantin :
            </td></tr>
            <tr><td style="padding:4pt;height:16mm;font-size:7.5pt;">
                _______________________________________________________________<br/><br/>
                _______________________________________________________________<br/><br/>
                _______________________________________________________________
            </td></tr>
        </table>
        <br/>
        <table width="100%">
            <tr>
                <td width="50%" style="font-size:7.5pt;"><b>Date :</b> _______________</td>
                <td width="50%" style="text-align:right;font-size:7.5pt;"><b>Cachet &amp; Signature Laborantin</b></td>
            </tr>
        </table>';

        $totalAff = $isOrphelin ? 0 : $total;
        return $this->blocRecu($recu, 'BON D\'EXAMEN', $rows, $totalAff, $isOrphelin, true, $zoneObs);
    }

    private function buildBlocPharmacie(array $recu, array $lignes, bool $isOrphelin): string
    {
        $rows = '
            <tr style="background:#e0f2f1;font-weight:bold;font-size:7.5pt;">
                <td style="padding:3pt;">Désignation</td>
                <td style="padding:3pt;">Forme</td>
                <td style="padding:3pt;text-align:center;">Qté</td>
                <td style="padding:3pt;text-align:right;">P.U.</td>
                <td style="padding:3pt;text-align:right;">Total</td>
            </tr>';
        $total = 0;
        foreach ($lignes as $l) {
            $pu        = (int)$l['prix_unitaire'];
            $totLigne  = (int)$l['total_ligne'];
            $puAff     = $this->fmtMontant($pu, $isOrphelin);
            $totAff    = $this->fmtMontant($totLigne, $isOrphelin);

            $rows .= "<tr>
                <td style='padding:2pt 3pt;'>{$l['nom']}</td>
                <td style='padding:2pt 3pt;font-size:7pt;color:#666;'>{$l['forme']}</td>
                <td style='padding:2pt 3pt;text-align:center;'>{$l['quantite']}</td>
                <td style='padding:2pt 3pt;text-align:right;'>{$puAff}</td>
                <td style='padding:2pt 3pt;text-align:right;'>{$totAff}</td>
            </tr>";
            $total += $totLigne;
        }

        $totalAff = $isOrphelin ? 0 : $total;
        return $this->blocRecu($recu, 'REÇU PHARMACIE', $rows, $totalAff, $isOrphelin, false, '', 5);
    }

    private function buildEtatLaboHtml(array $lignes, string $debut, string $fin): string
    {
        $totalLabo = array_sum(array_column($lignes, 'total_labo'));
        $rows = '';
        foreach ($lignes as $l) {
            $rows .= "<tr>
                <td>{$l['libelle']}</td>
                <td style='text-align:center;'>{$l['nb_actes']}</td>
                <td style='text-align:right;'>" . number_format($l['total_brut'], 0, ',', ' ') . " F</td>
                <td style='text-align:center;'>{$l['pourcentage_labo']}%</td>
                <td style='text-align:right;font-weight:bold;color:#2e7d32;'>"
                    . number_format($l['total_labo'], 0, ',', ' ') . " F</td>
            </tr>";
        }

        return "
        <html><body style='font-family:Arial,sans-serif;font-size:9pt;'>
        " . $this->buildEntete() . "
        <h3 style='text-align:center;color:#2e7d32;margin:8pt 0;'>État de paie Laborantin</h3>
        <p style='text-align:center;'>Période : <b>"
            . date('d/m/Y', strtotime($debut)) . " → " . date('d/m/Y', strtotime($fin)) . "</b></p>
        <table border='1' cellpadding='4' cellspacing='0' width='100%' style='border-collapse:collapse;'>
            <thead style='background:#e8f5e9;font-weight:bold;'>
                <tr>
                    <th>Examen</th><th>Nb actes</th><th>Total brut</th>
                    <th>% Labo</th><th>Montant Labo</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
            <tfoot>
                <tr style='background:#2e7d32;color:#fff;font-weight:bold;'>
                    <td colspan='4' style='text-align:right;padding:5pt;'>TOTAL DÛ AU LABORANTIN :</td>
                    <td style='text-align:right;padding:5pt;'>" . number_format($totalLabo, 0, ',', ' ') . " F</td>
                </tr>
            </tfoot>
        </table>
        <br/><br/>
        <p style='text-align:right;'>Signature de l'Administrateur : ___________________________</p>
        </body></html>";
    }

    // ═════════════════════════════════════════════════════════════════════
    //  BLOC RECU UNIVERSEL
    // ═════════════════════════════════════════════════════════════════════

 private function blocRecu(
    array  $recu,
    string $titre,
    string $tableRows,
    int    $total,
    bool   $isOrphelin,
    bool   $hideTotal = false,
    string $zoneSupplementaire = '',
    int    $nbColsTotal = 2
): string {
    $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
    $date       = date('d/m/Y H:i', strtotime($recu['whendone']));
    $piedPage   = $this->cfg('pied_de_page', 'Merci de votre visite – Bonne santé.');

    $totalStr = $isOrphelin
        ? '<span style="color:#d32f2f;font-weight:bold;font-size:11pt;">0 F</span>'
        : '<b style="font-size:11pt;">' . number_format($total, 0, ',', ' ') . ' F</b>';

    $totalRow = '';
    if (!$hideTotal) {
        $colspan = $nbColsTotal - 1;
        $totalRow = "
            <tr style='background:#e8f5e9;'>
                <td colspan='{$colspan}' style='padding:5pt 4pt;text-align:right;font-weight:bold;'>TOTAL :</td>
                <td style='padding:5pt 4pt;text-align:right;'>{$totalStr}</td>
            </tr>";
    }

    $badgeOrphelin = $isOrphelin
        ? '<span style="background:#7b1fa2;color:#fff;padding:1pt 4pt;font-size:7pt;font-weight:bold;border-radius:2pt;">PRIS EN CHARGE — DIRECTAID AMA</span>'
        : '';

    $provenanceCell = !empty($recu['provenance'])
        ? "<small style='color:#666;'>Provenance : {$recu['provenance']}</small>"
        : '';

    $infoPatient = '';
    if (!empty($recu['sexe']) || !empty($recu['age'])) {
        $infoPatient = "<small style='color:#666;'>"
            . ($recu['sexe'] ?? '') . ($recu['age'] ? ' · ' . $recu['age'] . ' ans' : '')
            . "</small>";
    }

    // ✅ Génération du QR code
    $qrCode = $this->buildQrCode($recu, $total, $isOrphelin);

    // Récupérer nom du percepteur pour l'afficher à côté du QR
    $percNom = '';
    if (!empty($recu['whodone'])) {
        $stmt = $this->pdo->prepare("SELECT nom FROM utilisateurs WHERE id = ? LIMIT 1");
        $stmt->execute([$recu['whodone']]);
        $percNom = (string)$stmt->fetchColumn();
    }

    return "
    " . $this->buildEntete() . "

    <table width='100%' cellpadding='0' cellspacing='0' style='margin-top:3pt;'>
        <tr>
            <td style='text-align:center;background:#2e7d32;color:#fff;padding:3pt;font-weight:bold;font-size:9pt;letter-spacing:1pt;'>
                {$titre} — N° {$numFormate}
            </td>
        </tr>
    </table>

    <table width='100%' cellpadding='2' cellspacing='0' style='margin-top:3pt;font-size:8pt;'>
        <tr>
            <td width='60%'>
                <b>Patient :</b> {$recu['patient_nom']}<br/>
                {$infoPatient}
            </td>
            <td width='40%' style='text-align:right;'>
                <small>Date : <b>{$date}</b></small><br/>
                <small>Tél : {$recu['telephone']}</small>
            </td>
        </tr>
        " . ($provenanceCell || $badgeOrphelin ? "
        <tr>
            <td>{$provenanceCell}</td>
            <td style='text-align:right;'>{$badgeOrphelin}</td>
        </tr>" : "") . "
    </table>

    <table border='1' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse;border-color:#bbb;font-size:8.5pt;margin-top:3pt;'>
        {$tableRows}
        {$totalRow}
    </table>

    {$zoneSupplementaire}

            <table width='100%' cellpadding='2' cellspacing='0' style='margin-top:5pt;font-size:7.5pt;'>
        <tr>
            <td width='62%' style='vertical-align:middle;padding-right:6pt;'>
                <i style='color:#666;'>{$piedPage}</i>
                <br/><br/>
                <small style='color:#888;'>
                    <b>Émis par :</b> " . ($percNom ?: '—') . "<br/>
                    <b>Le :</b> {$date}
                </small>
            </td>
            <td width='38%' style='text-align:center;vertical-align:middle;'>
                {$qrCode}
                <br/>
                <span style='font-size:6.5pt;color:#666;font-style:italic;'>
                    Scannez pour vérifier<br/>l'authenticité du reçu
                </span>
            </td>
        </tr>
    </table>";


}


    // ═════════════════════════════════════════════════════════════════════
    //  RÉCUPÉRATION DONNÉES
    // ═════════════════════════════════════════════════════════════════════

    private function getRecu(int $recuId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   p.nom AS patient_nom,
                   p.telephone, p.provenance, p.sexe, p.age, p.est_orphelin
            FROM recus r
            JOIN patients p ON p.id = r.patient_id
            WHERE r.id = :id AND r.isDeleted = 0
            LIMIT 1
        ");
        $stmt->execute([':id' => $recuId]);
        return $stmt->fetch() ?: null;
    }

    // ═════════════════════════════════════════════════════════════════════
    //  RENDUS PDF
    // ═════════════════════════════════════════════════════════════════════

    /** 2 exemplaires (Percepteur + Patient) sur une même page A5 */
    private function renderDoubleExemplaire(string $block, string $filename): string
    {
        $html = "
        <html><head><style>
            body { font-family: Arial, sans-serif; font-size: 8.5pt; margin: 0; padding: 0; }
            table { border-color: #bbb; }
        </style></head>
        <body>
            <div>
                <p style='text-align:center;color:#999;font-size:6.5pt;margin:0 0 1pt;letter-spacing:2pt;'>
                    ✂ — — — — — — — EXEMPLAIRE PERCEPTEUR — — — — — — — ✂
                </p>
                {$block}
            </div>
            <div style='border-top:1px dashed #999;margin:5pt 0 3pt;'></div>
            <div>
                <p style='text-align:center;color:#999;font-size:6.5pt;margin:0 0 1pt;letter-spacing:2pt;'>
                    ✂ — — — — — — — — EXEMPLAIRE PATIENT — — — — — — — — ✂
                </p>
                {$block}
            </div>
        </body></html>";

        return $this->renderPdf($html, $filename);
    }

    /** UN SEUL exemplaire (utilisé pour les bons d'examen) */
    private function renderSimpleExemplaire(string $block, string $filename): string
    {
        $html = "
        <html><head><style>
            body { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; padding: 0; }
            table { border-color: #bbb; }
        </style></head>
        <body>
            {$block}
        </body></html>";

        return $this->renderPdf($html, $filename);
    }

    /** 2 pages A5 séparées (cas pharmacie avec beaucoup de lignes) */
    private function renderDeuxPages(string $block, string $filename): string
    {
        if (!class_exists('TCPDF')) {
            return $this->fallbackHtmlDeuxPages($block, $filename);
        }

        $pdf = $this->newTcpdf();

        $page1 = "<html><body style='font-family:Arial,sans-serif;font-size:9pt;'>
            <p style='text-align:center;color:#999;font-size:6.5pt;margin:0 0 2pt;letter-spacing:2pt;'>
                ✂ — — — EXEMPLAIRE PERCEPTEUR — — — ✂
            </p>
            {$block}
        </body></html>";

        $page2 = "<html><body style='font-family:Arial,sans-serif;font-size:9pt;'>
            <p style='text-align:center;color:#999;font-size:6.5pt;margin:0 0 2pt;letter-spacing:2pt;'>
                ✂ — — — EXEMPLAIRE PATIENT — — — ✂
            </p>
            {$block}
        </body></html>";

        $pdf->AddPage();
        $pdf->writeHTML($page1, true, false, true, false, '');
        $pdf->AddPage();
        $pdf->writeHTML($page2, true, false, true, false, '');

        return $this->savePdf($pdf, $filename);
    }

    private function renderEtatLabo(string $html, string $filename): string
    {
        return $this->renderPdf($html, $filename);
    }

    /** Rendu PDF générique (1 page) */
    private function renderPdf(string $html, string $filename): string
    {
        if (!class_exists('TCPDF')) {
            return $this->fallbackHtml($html, $filename);
        }

        $pdf = $this->newTcpdf();
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $this->savePdf($pdf, $filename);
    }

    private function newTcpdf(): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);
        $pdf->SetCreator('CSI DirectAid Maradi');
        $pdf->SetAuthor('CSI DirectAid Maradi');
        $pdf->SetAutoPageBreak(true, 6);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(7, 6, 7);
        $pdf->SetFont('helvetica', '', 9);
        return $pdf;
    }

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

    private function fallbackHtml(string $html, string $filename): string
    {
        $dir = ROOT_PATH . '/uploads/pdf/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . $filename . '_' . date('YmdHis') . '.html';

        $printHtml = str_replace(
            '<body>',
            '<body onload="window.print()">
            <style>
            @page { size: A5; margin: 7mm; }
            @media print { .no-print { display: none !important; } }
            body { font-family: Arial, sans-serif; font-size: 9pt; }
            </style>
            <div class="no-print" style="padding:10px;background:#e8f5e9;text-align:center;">
                <button onclick="window.print()" style="background:#2e7d32;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:14px;">🖨️ Imprimer</button>
                <button onclick="window.close()" style="background:#999;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;margin-left:8px;">Fermer</button>
            </div>',
            $html
        );

        file_put_contents($file, $printHtml);
        return $file;
    }

    private function fallbackHtmlDeuxPages(string $block, string $filename): string
    {
        $dir = ROOT_PATH . '/uploads/pdf/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = $dir . $filename . '_' . date('YmdHis') . '.html';

        $html = "
        <html><head><style>
        @page { size: A5; margin: 7mm; }
        @media print {
            .no-print { display: none !important; }
            .new-page { page-break-before: always; }
        }
        body { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; }
        </style></head>
        <body onload='window.print()'>
            <div class='no-print' style='padding:10px;background:#e8f5e9;text-align:center;'>
                <button onclick='window.print()' style='background:#2e7d32;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:14px;'>🖨️ Imprimer</button>
                <button onclick='window.close()' style='background:#999;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;margin-left:8px;'>Fermer</button>
            </div>
            <div>
                <p style='text-align:center;color:#999;font-size:7pt;margin:0 0 2pt;'>✂ EXEMPLAIRE PERCEPTEUR ✂</p>
                {$block}
            </div>
            <div class='new-page'>
                <p style='text-align:center;color:#999;font-size:7pt;margin:0 0 2pt;'>✂ EXEMPLAIRE PATIENT ✂</p>
                {$block}
            </div>
        </body></html>";

        file_put_contents($file, $html);
        return $file;
    }

    /**
 * Génère un QR code (PNG base64) contenant les infos du reçu.
 * Retourne une balise <img> prête à intégrer dans le HTML.
 */private function buildQrCode(array $recu, int $totalAffiche, bool $isOrphelin): string
{
    if (!class_exists('TCPDF2DBarcode')) {
        return '';
    }

    // Récupérer le nom du percepteur
    $percNom = '';
    if (!empty($recu['whodone'])) {
        $stmt = $this->pdo->prepare("SELECT nom FROM utilisateurs WHERE id = ? LIMIT 1");
        $stmt->execute([$recu['whodone']]);
        $percNom = (string)$stmt->fetchColumn();
    }

    $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
    $statut     = $isOrphelin ? 'ORPHELIN-DirectAid' : 'NORMAL';
    $totalLib   = $isOrphelin ? '0 F (pris en charge)' : number_format($totalAffiche, 0, ',', ' ') . ' F';

    // Date complète d'émission
    $dateEmission = date('d/m/Y H:i:s', strtotime($recu['whendone']));
    $dateGen      = date('d/m/Y H:i:s'); // date de génération du PDF

    // Contenu structuré du QR
    $contenuQr = "═══ CSI DIRECTAID MARADI ═══\n"
        . "Reçu : {$numFormate}\n"
        . "Type : " . strtoupper($recu['type_recu']) . "\n"
        . "Date émission : {$dateEmission}\n"
        . "─────────────────\n"
        . "Patient : " . $recu['patient_nom'] . "\n"
        . "Tél : " . ($recu['telephone'] ?? '—') . "\n"
        . ($recu['sexe'] ? "Sexe/Âge : {$recu['sexe']} / " . ($recu['age'] ?? '?') . " ans\n" : '')
        . ($recu['provenance'] ? "Provenance : {$recu['provenance']}\n" : '')
        . "─────────────────\n"
        . "Statut : {$statut}\n"
        . "Montant : {$totalLib}\n"
        . "Percepteur : " . ($percNom ?: '—') . "\n"
        . "─────────────────\n"
        . "Généré le : {$dateGen}";

    try {
        $qr = new TCPDF2DBarcode($contenuQr, 'QRCODE,M');
        // Augmentation de la résolution : 6px par module au lieu de 3
                $pngData = $qr->getBarcodePngData(8, 8, [0, 0, 0]);
        $base64  = base64_encode($pngData);
        // QR bien visible : 42mm × 42mm
        return '<img src="@' . $base64 . '" width="42" height="42"/>';

    } catch (Exception $e) {
        return '';
    }
}


}
