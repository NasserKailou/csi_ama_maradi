<?php
/**
 * PdfGenerator – Génération des reçus A6 (CSI DirectAid Maradi)
 *
 * FORMAT : A6 paysage (148 × 105 mm) — équivalent A4/4
 *
 * RÈGLES MÉTIER (INCHANGÉES) :
 *  - Consultation NORMALE  : 300 F + 100 F carnet (opt.) + 100 F redevance (âge>5)
 *  - Mise en OBSERVATION   : 1000 F fixe
 *  - Consultation ACTE GRATUIT : 0 / 100 / 400 F selon avec_carnet
 *  - Examens / Pharmacie    : tarification dynamique
 *  - Orphelin : prix barrés + total 0 F en rouge
 *  - Téléphone '99999999' → "Non renseigné"
 *
 * QR CODE : PNG temporaire dans uploads/pdf/qr_tmp/
 */

// ── Chargement de TCPDF ──────────────────────────────────────────────────
if (file_exists(ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';
} elseif (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf.php')) {
    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf.php';
}

// ── Chargement de TCPDF2DBarcode ─────────────────────────────────────────
if (file_exists(ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php')) {
    require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
} elseif (file_exists(ROOT_PATH . '/vendor/tcpdf/tcpdf_barcodes_2d.php')) {
    require_once ROOT_PATH . '/vendor/tcpdf/tcpdf_barcodes_2d.php';
}


class PdfGenerator
{
    private PDO    $pdo;
    private array  $config        = [];
    private string $logoMinistere = '';
    private string $logoDirectAid = '';

    /** @var string[] PNG QR temporaires — purgés à la fin. */
    private array $qrTempFiles = [];

    private const SEUIL_DOUBLE_PAGE           = 8;
    private const SEUIL_EXAMEN_DOUBLE_PAGE    = 6;
    private const VALIDITE_CONSULTATION_JOURS = 3;

    // Mots pour montant en lettres
    private const UNITES = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf'
    ];
    private const DIZAINES = [
        '', '', 'vingt', 'trente', 'quarante', 'cinquante',
        'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'
    ];
    private const TARIF_CARNET_AG         = 100;
    private const TARIF_FICHE_AG          = 300;
    private const TARIF_OBSERVATION       = 1000;
    private const AGE_LIMITE_SUPPLEMENT   = 5;
    private const TARIF_SUPPLEMENT_ADULTE = 100;
    private const TELEPHONE_PAR_DEFAUT    = '99999999';

    // ── Couleurs et tailles centralisées (pour cohérence A6) ─────────────
    private const COLOR_PRIMARY    = '#2e7d32';   // vert ministère
    private const COLOR_ACCENT     = '#e65100';   // orange (observation)
    private const COLOR_BORDER     = '#444444';   // bordures tableau
    private const COLOR_HEADER_BG  = '#1b5e20';   // fond entête tableau
    private const COLOR_ROW_ALT    = '#f0f0f0';   // ligne alternée
    private const COLOR_TOTAL_BG   = '#c8e6c9';   // fond total
    private const FONT_BASE        = 8;           // taille de base A6
    private const FONT_SMALL       = 6.5;
    private const FONT_TITLE       = 9.5;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadConfig();
        $this->cleanupOldQrFiles();
    }

    public function __destruct()
    {
        foreach ($this->qrTempFiles as $f) {
            if (is_file($f)) @unlink($f);
        }
    }

    private function loadConfig(): void
    {
        $rows = $this->pdo->query("SELECT cle, valeur FROM config_systeme WHERE isDeleted=0")->fetchAll();
        foreach ($rows as $r) {
            $this->config[$r['cle']] = $r['valeur'];
        }

        $logoMin = $this->config['logo_ministere'] ?? '';
        if ($logoMin && file_exists(ROOT_PATH . '/uploads/logos/' . $logoMin)) {
            $this->logoMinistere = ROOT_PATH . '/uploads/logos/' . $logoMin;
        } elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_ministere.png')) {
            $this->logoMinistere = ROOT_PATH . '/uploads/logos/logo_ministere.png';
        }

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
    //  MONTANT EN LETTRES (INCHANGÉ)
    // ═════════════════════════════════════════════════════════════════════

    public function montantEnLettres(int $montant): string
    {
        if ($montant === 0) return 'Zéro franc CFA';
        if ($montant < 0)   return 'Moins ' . $this->montantEnLettres(-$montant);

        $lettres = rtrim($this->nombreEnLettres($montant));
        $franc   = ($montant > 1) ? 'francs' : 'franc';
        return ucfirst($lettres) . ' ' . $franc . ' CFA';
    }

    private function nombreEnLettres(int $n): string
    {
        if ($n < 20) return self::UNITES[$n];

        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;
            if ($d === 7 || $d === 9) {
                return self::DIZAINES[$d] . '-' . self::UNITES[10 + $u];
            }
            $lien  = ($u === 1 && $d !== 8) ? '-et-' : ($u > 0 ? '-' : '');
            $plurD = ($d === 8 && $u === 0) ? 's' : '';
            return self::DIZAINES[$d] . $plurD . ($u > 0 ? $lien . self::UNITES[$u] : '');
        }

        if ($n < 1000) {
            $c = intdiv($n, 100);
            $r = $n % 100;
            $centMot = ($c === 1) ? 'cent' : (self::UNITES[$c] . ' cent');
            $plurC   = ($r === 0 && $c > 1) ? 's' : '';
            return $centMot . $plurC . ($r > 0 ? ' ' . $this->nombreEnLettres($r) : '');
        }

        if ($n < 1_000_000) {
            $m = intdiv($n, 1000);
            $r = $n % 1000;
            $milleMot = ($m === 1) ? 'mille' : ($this->nombreEnLettres($m) . ' mille');
            return $milleMot . ($r > 0 ? ' ' . $this->nombreEnLettres($r) : '');
        }

        $mil = intdiv($n, 1_000_000);
        $r   = $n % 1_000_000;
        $milMot = $this->nombreEnLettres($mil) . ' million' . ($mil > 1 ? 's' : '');
        return $milMot . ($r > 0 ? ' ' . $this->nombreEnLettres($r) : '');
    }

    private function fmtTelephone(?string $tel): string
    {
        $tel = trim((string)$tel);
        if ($tel === '' || $tel === self::TELEPHONE_PAR_DEFAUT) {
            return '<i style="color:#999;">Non renseigné</i>';
        }
        return htmlspecialchars($tel, ENT_QUOTES, 'UTF-8');
    }

    private function estLigneSupplement(array $ligne): bool
    {
        if (!empty($ligne['type_ligne']) && $ligne['type_ligne'] === 'redevance') {
            return true;
        }
        $lib = mb_strtolower((string)($ligne['libelle'] ?? ''), 'UTF-8');
        return str_contains($lib, 'supplément')
            || str_contains($lib, 'supplement')
            || str_contains($lib, 'redevance');
    }

    private function estConsultationObservation(array $items): bool
    {
        foreach ($items as $it) {
            if (!empty($it['type_ligne']) && $it['type_ligne'] === 'observation') {
                return true;
            }
            $lib = mb_strtolower((string)($it['libelle'] ?? ''), 'UTF-8');
            if (str_contains($lib, 'observation')) return true;
        }
        return false;
    }

    // ═════════════════════════════════════════════════════════════════════
    //  MÉTHODES PUBLIQUES (LOGIQUE INCHANGÉE)
    // ═════════════════════════════════════════════════════════════════════

    public function generateConsultation(int $recuId): string
    {
        $recu = $this->getRecu($recuId);
        if (!$recu) throw new RuntimeException("Reçu {$recuId} introuvable.");

        $stmt = $this->pdo->prepare("
            SELECT id, type_ligne, libelle, tarif, est_gratuit, avec_carnet, tarif_carnet
            FROM lignes_consultation
            WHERE recu_id = :id AND isDeleted = 0
            ORDER BY id ASC
        ");
        $stmt->execute([':id' => $recuId]);
        $items = $stmt->fetchAll();

        $isOrphelin    = ($recu['type_patient'] === 'orphelin');
        $isActeGratuit = ($recu['type_patient'] === 'acte_gratuit');
        $isObservation = $this->estConsultationObservation($items);

        $ligneBase = null;
        foreach ($items as $it) {
            if (!$this->estLigneSupplement($it)) {
                $ligneBase = $it;
                break;
            }
        }
        if ($ligneBase === null) $ligneBase = $items[0] ?? [];

        $optionCarnet  = (int)($ligneBase['avec_carnet'] ?? 0);
        $tarifCarnetDb = (int)($ligneBase['tarif_carnet'] ?? 0);
        $estGratuit    = !empty($ligneBase['est_gratuit']) || $isActeGratuit;

        if ($estGratuit) {
            $tarifConsult = 0;
        } elseif ($isObservation) {
            $tarifConsult = (int)($ligneBase['tarif'] ?? 0) ?: self::TARIF_OBSERVATION;
        } else {
            $tarifConsult = (int)($ligneBase['tarif'] ?? 0);
            if ($tarifConsult === 0) {
                $tarifConsult = defined('TARIF_CONSULTATION') ? (int)TARIF_CONSULTATION : 300;
            }
        }

        if ($isObservation) {
            $acteLibelle = 'Mise en observation';
        } else {
            $acteLibelle = $ligneBase['libelle'] ?? 'Consultation';
        }

        $supplementAge = 0;
        $libelleSupplement = '';
        if (!$isObservation) {
            foreach ($items as $l) {
                if ($this->estLigneSupplement($l)) {
                    $supplementAge     = (int)$l['tarif'];
                    $libelleSupplement = $l['libelle'];
                    break;
                }
            }
        }

        $block = $this->buildBlocConsultation(
            $recu, $acteLibelle, $tarifConsult,
            $optionCarnet, $tarifCarnetDb,
            $isOrphelin, $isActeGratuit,
            $isObservation,
            $supplementAge, $libelleSupplement
        );

        return $this->renderExemplaire($block, 'recu_consult_' . $recu['numero_recu']);
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

        return $this->renderExemplaire($block, 'recu_exam_' . $recu['numero_recu']);
    }

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

        return $this->renderExemplaire($block, 'recu_pharma_' . $recu['numero_recu']);
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
    //  EN-TÊTE OFFICIEL (compact A6 paysage)
    // ═════════════════════════════════════════════════════════════════════

    private function buildEntete(): string
    {
        $logoSize = 16;

        $logoMinTag = $this->logoMinistere
            ? "<img src=\"{$this->logoMinistere}\" width=\"{$logoSize}\" height=\"{$logoSize}\"/>"
            : '';

        $logoDaTag = $this->logoDirectAid
            ? "<img src=\"{$this->logoDirectAid}\" width=\"{$logoSize}\" height=\"{$logoSize}\"/>"
            : '';

        $tel = trim($this->cfg('telephone', ''));
        $telSpan = $tel !== ''
            ? '<br/><span style="font-size:5.5pt;color:#666;">Tél : ' . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '</span>'
            : '';

        return '
        <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1pt solid ' . self::COLOR_PRIMARY . ';">
            <tr>
                <td width="14%" align="center" style="vertical-align:middle;">' . $logoMinTag . '</td>
                <td width="72%" align="center" style="vertical-align:middle;line-height:1.25;">
                    <span style="font-size:7pt;font-weight:bold;">RÉPUBLIQUE DU NIGER</span><br/>
                    <span style="font-size:5.5pt;color:#444;">Ministère de la Santé et de l\'Hygiène Publique</span><br/>
                    <span style="font-size:8pt;font-weight:bold;color:' . self::COLOR_PRIMARY . ';">CSI ZARIA I / Direct Aid – MARADI</span>'
                    . $telSpan . '
                </td>
                <td width="14%" align="center" style="vertical-align:middle;">' . $logoDaTag . '</td>
            </tr>
        </table>';
    }

    // ═════════════════════════════════════════════════════════════════════
    //  BUILDERS DE BLOCS
    // ═════════════════════════════════════════════════════════════════════

    private function fmtMontant(int $montant, bool $isOrphelin): string
    {
        if ($isOrphelin) {
            return '<span style="color:#999;text-decoration:line-through;">'
                . number_format($montant, 0, ',', ' ') . ' F</span>';
        }
        return number_format($montant, 0, ',', ' ') . ' F';
    }

    private function buildBlocValidite(array $recu): string
    {
        $dateEmission = strtotime($recu['whendone']);
        $dateFin      = strtotime('+' . self::VALIDITE_CONSULTATION_JOURS . ' days', $dateEmission);
        $emissionFmt  = date('d/m/Y', $dateEmission);
        $finFmt       = date('d/m/Y', $dateFin);

        return "
        <table width='100%' cellpadding='2' cellspacing='0' style='margin-top:2pt;background:#fff8e1;border:0.5pt solid #f9a825;'>
            <tr>
                <td style='font-size:6pt;color:#5d4037;'>
                    <b style='color:" . self::COLOR_ACCENT . ";">VALIDITÉ :</b>
                    <b>" . self::VALIDITE_CONSULTATION_JOURS . " jours</b> &nbsp;·&nbsp;
                    Émis le <b>{$emissionFmt}</b> &nbsp;·&nbsp;
                    Expire le <span style='color:#c62828;font-weight:bold;'>{$finFmt}</span>
                </td>
            </tr>
        </table>";
    }

    /**
     * Bloc consultation (LOGIQUE INCHANGÉE).
     */
    private function buildBlocConsultation(
        array  $recu,
        string $acteLibelle,
        int    $tarif,
        int    $optionCarnet,
        int    $tarifCarnetDb,
        bool   $isOrphelin,
        bool   $isActeGratuit = false,
        bool   $isObservation = false,
        int    $supplementAge = 0,
        string $libelleSupplement = ''
    ): string {
        error_log("[PdfGenerator] recu={$recu['numero_recu']} | isObservation=" . ($isObservation?'OUI':'NON') . " | tarif={$tarif} | libelle={$acteLibelle}");

        // ────────────────────────────────────────
        // Cas ACTE GRATUIT
        // ────────────────────────────────────────
        if ($isActeGratuit) {
            $rows = "
                <tr>
                    <td>{$acteLibelle}
                        <span style='color:#1565c0;font-size:6pt;font-weight:bold;'>(ACTE GRATUIT)</span>
                    </td>
                    <td align='right' style='color:" . self::COLOR_PRIMARY . ";font-weight:bold;'>Gratuit</td>
                </tr>";

            $totalAff = 0;

            if ($optionCarnet === 1) {
                $rows .= "
                <tr>
                    <td>Carnet de santé <span style='color:#666;font-size:6pt;'>(obligatoire)</span></td>
                    <td align='right' style='font-weight:bold;'>" . number_format(self::TARIF_CARNET_AG, 0, ',', ' ') . " F</td>
                </tr>";
                $totalAff = self::TARIF_CARNET_AG;
            } elseif ($optionCarnet === 2) {
                $rows .= "
                <tr>
                    <td>Carnet de santé <span style='color:#666;font-size:6pt;'>(obligatoire)</span></td>
                    <td align='right' style='font-weight:bold;'>" . number_format(self::TARIF_CARNET_AG, 0, ',', ' ') . " F</td>
                </tr>
                <tr>
                    <td>Fiche de consultation <span style='color:#666;font-size:6pt;'>(1er passage)</span></td>
                    <td align='right' style='font-weight:bold;'>" . number_format(self::TARIF_FICHE_AG, 0, ',', ' ') . " F</td>
                </tr>";
                $totalAff = self::TARIF_CARNET_AG + self::TARIF_FICHE_AG;
            }

            return $this->blocRecu($recu, 'CONSULTATION (ACTE GRATUIT)', $rows, $totalAff, false, false, '', 2, false);
        }

        // ────────────────────────────────────────
        // Cas MISE EN OBSERVATION
        // ────────────────────────────────────────
        if ($isObservation) {
            $libelleObs = htmlspecialchars($acteLibelle ?: 'Mise en observation', ENT_QUOTES, 'UTF-8');
            $prixObsAff = $this->fmtMontant($tarif, $isOrphelin);

            $rows = "
                <tr>
                    <td><b style='color:" . self::COLOR_ACCENT . ";'>{$libelleObs}</b>
                        <span style='color:#666;font-size:6pt;'>(tarif fixe)</span>
                    </td>
                    <td align='right'>{$prixObsAff}</td>
                </tr>";

            $totalAff = $isOrphelin ? 0 : $tarif;
            $afficherValidite = !$isOrphelin;

            return $this->blocRecu($recu, 'MISE EN OBSERVATION', $rows, $totalAff, $isOrphelin, false, '', 2, $afficherValidite);
        }

        // ────────────────────────────────────────
        // Cas STANDARD
        // ────────────────────────────────────────
        $prixConsultAff = $this->fmtMontant($tarif, $isOrphelin);
        $avecCarnet  = ($optionCarnet >= 1);
        $tarifCarnet = $avecCarnet ? ($tarifCarnetDb > 0 ? $tarifCarnetDb : 100) : 0;

        $carnetLine = '';
        if ($avecCarnet) {
            $prixCarnetAff = $this->fmtMontant($tarifCarnet, $isOrphelin);
            $carnetLine = "
                <tr>
                    <td>Carnet de Soins</td>
                    <td align='right'>{$prixCarnetAff}</td>
                </tr>";
        }

        $supplementLine = '';
        if ($supplementAge > 0) {
            $libSupp = $libelleSupplement !== ''
                ? htmlspecialchars($libelleSupplement, ENT_QUOTES, 'UTF-8')
                : 'Redevance (âge &gt; ' . self::AGE_LIMITE_SUPPLEMENT . ' ans)';

            $prixSuppAff = $this->fmtMontant($supplementAge, $isOrphelin);

            $supplementLine = "
                <tr>
                    <td style='font-style:italic;color:#5d4037;'>
                        {$libSupp}
                        <span style='color:#888;font-size:6pt;'>(reversée au ministère)</span>
                    </td>
                    <td align='right'>{$prixSuppAff}</td>
                </tr>";
        }

        $rows = "
            <tr>
                <td>{$acteLibelle}</td>
                <td align='right'>{$prixConsultAff}</td>
            </tr>
            {$carnetLine}
            {$supplementLine}";

        $totalAff = $isOrphelin ? 0 : ($tarif + $tarifCarnet + $supplementAge);
        $afficherValidite = !$isOrphelin;

        return $this->blocRecu($recu, 'CONSULTATION', $rows, $totalAff, $isOrphelin, false, '', 2, $afficherValidite);
    }

    private function buildBlocExamen(array $recu, array $lignes, bool $isOrphelin): string
    {
        // En-tête tableau (ligne avec fond sombre)
        $headerStyle = 'background:' . self::COLOR_HEADER_BG . ';color:#ffffff;font-weight:bold;font-size:7pt;';
        $rows = "<tr style='{$headerStyle}'>
            <th width='78%' align='left'>&nbsp;Examen prescrit</th>
            <th width='22%' align='right'>Coût&nbsp;</th>
        </tr>";

        $total = 0;
        $i = 0;
        foreach ($lignes as $l) {
            $i++;
            $bg = ($i % 2 === 0) ? "background:" . self::COLOR_ROW_ALT . ";" : "";
            $cout    = (int)$l['cout_total'];
            $coutAff = $this->fmtMontant($cout, $isOrphelin);
            $libelle = htmlspecialchars((string)$l['libelle'], ENT_QUOTES, 'UTF-8');
            $rows   .= "<tr style='{$bg}'>
                <td>&nbsp;{$libelle}</td>
                <td align='right'>{$coutAff}&nbsp;</td>
            </tr>";
            $total += $cout;
        }

        // Ligne total
        $totalAff   = $isOrphelin ? 0 : $total;
        $totalLigne = $isOrphelin
            ? '<span style="color:#d32f2f;font-weight:bold;">0 F</span>'
            : '<b>' . number_format($total, 0, ',', ' ') . ' F</b>';
        $rows .= "<tr style='background:" . self::COLOR_TOTAL_BG . ";'>
            <td align='right' style='font-weight:bold;'>TOTAL :&nbsp;</td>
            <td align='right' style='font-weight:bold;'>{$totalLigne}&nbsp;</td>
        </tr>";

        $lettres = '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2pt;">
            <tr><td style="font-size:6pt;color:#444;font-style:italic;">
                Arrêté à : <b>' . $this->montantEnLettres($totalAff) . '</b>
            </td></tr>
        </table>';

        return $this->blocRecu($recu, 'BON D\'EXAMEN', $rows, $totalAff, $isOrphelin, true, $lettres, 2, false);
    }

    private function buildBlocPharmacie(array $recu, array $lignes, bool $isOrphelin): string
    {
        $headerStyle = 'background:' . self::COLOR_HEADER_BG . ';color:#ffffff;font-weight:bold;font-size:6.5pt;';
        $rows = "<tr style='{$headerStyle}'>
            <th width='38%' align='left'>&nbsp;Désignation</th>
            <th width='18%' align='left'>Forme</th>
            <th width='10%' align='center'>Qté</th>
            <th width='17%' align='right'>P.U.</th>
            <th width='17%' align='right'>Total&nbsp;</th>
        </tr>";

        $total = 0;
        $i = 0;
        foreach ($lignes as $l) {
            $i++;
            $bg = ($i % 2 === 0) ? "background:" . self::COLOR_ROW_ALT . ";" : "";
            $pu       = (int)$l['prix_unitaire'];
            $totLigne = (int)$l['total_ligne'];
            $puAff    = $this->fmtMontant($pu, $isOrphelin);
            $totAff   = $this->fmtMontant($totLigne, $isOrphelin);
            $nom      = htmlspecialchars((string)$l['nom'], ENT_QUOTES, 'UTF-8');
            $forme    = htmlspecialchars((string)$l['forme'], ENT_QUOTES, 'UTF-8');

            $rows .= "<tr style='{$bg}'>
                <td>&nbsp;{$nom}</td>
                <td style='color:#555;'>{$forme}</td>
                <td align='center'>{$l['quantite']}</td>
                <td align='right'>{$puAff}</td>
                <td align='right' style='font-weight:bold;'>{$totAff}&nbsp;</td>
            </tr>";
            $total += $totLigne;
        }

        $totalAff   = $isOrphelin ? 0 : $total;
        $totalLigne = $isOrphelin
            ? '<span style="color:#d32f2f;font-weight:bold;">0 F</span>'
            : '<b>' . number_format($total, 0, ',', ' ') . ' F</b>';
        $rows .= "<tr style='background:" . self::COLOR_TOTAL_BG . ";'>
            <td colspan='4' align='right' style='font-weight:bold;'>TOTAL :&nbsp;</td>
            <td align='right' style='font-weight:bold;'>{$totalLigne}&nbsp;</td>
        </tr>";

        $lettres = '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2pt;">
            <tr><td style="font-size:6pt;color:#444;font-style:italic;">
                Arrêté à : <b>' . $this->montantEnLettres($totalAff) . '</b>
            </td></tr>
        </table>';

        return $this->blocRecu($recu, 'REÇU PHARMACIE', $rows, $totalAff, $isOrphelin, true, $lettres, 5, false);
    }

    private function buildEtatLaboHtml(array $lignes, string $debut, string $fin): string
    {
        $totalLabo = array_sum(array_column($lignes, 'total_labo'));
        $rows = '';
        foreach ($lignes as $l) {
            $rows .= "<tr>
                <td>" . htmlspecialchars($l['libelle'], ENT_QUOTES) . "</td>
                <td align='center'>{$l['nb_actes']}</td>
                <td align='right'>" . number_format($l['total_brut'], 0, ',', ' ') . " F</td>
                <td align='center'>{$l['pourcentage_labo']}%</td>
                <td align='right' style='font-weight:bold;color:" . self::COLOR_PRIMARY . ";'>"
                    . number_format($l['total_labo'], 0, ',', ' ') . " F</td>
            </tr>";
        }

        return "
        <html><body style='font-family:Arial,sans-serif;font-size:8pt;'>
        " . $this->buildEntete() . "
        <h3 style='text-align:center;color:" . self::COLOR_PRIMARY . ";margin:6pt 0;'>État de paie Laborantin</h3>
        <p style='text-align:center;font-size:7.5pt;'>Période : <b>"
            . date('d/m/Y', strtotime($debut)) . " → " . date('d/m/Y', strtotime($fin)) . "</b></p>
        <table border='1' cellpadding='3' cellspacing='0' width='100%' style='border-collapse:collapse;font-size:7.5pt;'>
            <tr style='background:" . self::COLOR_HEADER_BG . ";color:#fff;font-weight:bold;'>
                <th>Examen</th><th>Nb actes</th><th>Total brut</th><th>% Labo</th><th>Montant Labo</th>
            </tr>
            {$rows}
            <tr style='background:" . self::COLOR_PRIMARY . ";color:#fff;font-weight:bold;'>
                <td colspan='4' align='right'>TOTAL DÛ AU LABORANTIN :</td>
                <td align='right'>" . number_format($totalLabo, 0, ',', ' ') . " F</td>
            </tr>
        </table>
        <br/><br/>
        <p style='text-align:right;font-size:7.5pt;'>Signature de l'Administrateur : ___________________________</p>
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
        int    $nbColsTotal = 2,
        bool   $afficherValidite = false
    ): string {
        $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
        $date       = date('d/m/Y H:i', strtotime($recu['whendone']));
        $piedPage   = $this->cfg('pied_de_page', 'Merci de votre visite – Bonne santé.');

        // Ligne TOTAL pour consultation
        $totalStr = $isOrphelin
            ? '<span style="color:#d32f2f;font-weight:bold;">0 F</span>'
            : '<b>' . number_format($total, 0, ',', ' ') . ' F</b>';

        $totalRow = '';
        if (!$hideTotal) {
            $colspan  = $nbColsTotal - 1;
            $totalRow = "
                <tr style='background:" . self::COLOR_TOTAL_BG . ";'>
                    <td colspan='{$colspan}' align='right' style='font-weight:bold;'>TOTAL :&nbsp;</td>
                    <td align='right' style='font-weight:bold;'>{$totalStr}&nbsp;</td>
                </tr>";
        }

        // Badges
        $badges = [];
        if ($isOrphelin) {
            $badges[] = '<span style="background:#7b1fa2;color:#fff;padding:1pt 3pt;font-size:5.5pt;font-weight:bold;">PRIS EN CHARGE — DIRECTAID</span>';
        }
        if (($recu['type_patient'] ?? '') === 'acte_gratuit') {
            $badges[] = '<span style="background:#1565c0;color:#fff;padding:1pt 3pt;font-size:5.5pt;font-weight:bold;">ACTE GRATUIT</span>';
        }
        if (str_contains(strtoupper($titre), 'OBSERVATION')) {
            $badges[] = '<span style="background:#f9a825;color:#000;padding:1pt 3pt;font-size:5.5pt;font-weight:bold;">OBSERVATION</span>';
        }
        $badgesDroite = implode(' ', $badges);

        $provenanceCell = !empty($recu['provenance'])
            ? "<span style='color:#555;font-size:6pt;'>Prov. : " . htmlspecialchars((string)$recu['provenance'], ENT_QUOTES, 'UTF-8') . "</span>"
            : '';

        $infoPatient = '';
        if (!empty($recu['sexe']) || !empty($recu['age'])) {
            $infoPatient = "<span style='color:#555;font-size:6pt;'>"
                . ($recu['sexe'] ?? '') . ($recu['age'] ? ' · ' . $recu['age'] . ' ans' : '')
                . "</span>";
        }

        $blocValidite = $afficherValidite ? $this->buildBlocValidite($recu) : '';
        $qrCode       = $this->buildQrCode($recu, $total, $isOrphelin, $afficherValidite);

        $percNom = '';
        if (!empty($recu['whodone'])) {
            $stmt = $this->pdo->prepare("SELECT nom FROM utilisateurs WHERE id = ? LIMIT 1");
            $stmt->execute([$recu['whodone']]);
            $percNom = (string)$stmt->fetchColumn();
        }

        $telAffiche = $this->fmtTelephone($recu['telephone'] ?? '');
        $patientNom = htmlspecialchars((string)$recu['patient_nom'], ENT_QUOTES, 'UTF-8');

        $bgTitre = str_contains(strtoupper($titre), 'OBSERVATION') ? self::COLOR_ACCENT : self::COLOR_PRIMARY;

        // Montant en lettres pour consultation
        $ligneMontantLettres = '';
        if (!$hideTotal && !$isOrphelin && $total > 0) {
            $ligneMontantLettres = "<table width='100%' cellpadding='0' cellspacing='0' style='margin-top:2pt;'>
                <tr><td style='font-size:6pt;color:#444;font-style:italic;'>
                    Arrêté à : <b>" . $this->montantEnLettres($total) . "</b>
                </td></tr>
            </table>";
        }

        // ─── Construction du tableau principal ───
        // ⚠️ Pour TCPDF : border="1" sur la table + cellpadding="3" garantit
        // que TOUTES les bordures internes (lignes ET colonnes) sont tracées.
        $tableauPrincipal = "
        <table border='1' cellpadding='3' cellspacing='0' width='100%' style='border-collapse:collapse;font-size:7.5pt;'>
            {$tableRows}
            {$totalRow}
        </table>";

        return "
        " . $this->buildEntete() . "

        <table width='100%' cellpadding='0' cellspacing='0' style='margin-top:2pt;'>
            <tr>
                <td align='center' style='background:{$bgTitre};color:#ffffff;padding:2pt;font-weight:bold;font-size:8.5pt;letter-spacing:1pt;'>
                    {$titre} &nbsp;—&nbsp; N° {$numFormate}
                </td>
            </tr>
        </table>

        <table border='1' cellpadding='3' cellspacing='0' width='100%' style='border-collapse:collapse;margin-top:2pt;font-size:7pt;'>
            <tr>
                <td width='60%'>
                    <b>Patient :</b> {$patientNom}<br/>
                    {$infoPatient}
                    " . ($provenanceCell ? "<br/>{$provenanceCell}" : '') . "
                </td>
                <td width='40%' align='right'>
                    <span style='font-size:6.5pt;'>Date : <b>{$date}</b></span><br/>
                    <span style='font-size:6.5pt;'>Tél : {$telAffiche}</span>
                    " . ($badgesDroite ? "<br/>{$badgesDroite}" : '') . "
                </td>
            </tr>
        </table>

        {$tableauPrincipal}

        {$ligneMontantLettres}

        {$blocValidite}

        {$zoneSupplementaire}

        <table width='100%' cellpadding='2' cellspacing='0' style='margin-top:3pt;font-size:6pt;border-top:0.5pt solid #ccc;'>
            <tr>
                <td width='62%' style='vertical-align:middle;'>
                    <i style='color:#666;'>{$piedPage}</i><br/>
                    <span style='color:#888;font-size:5.5pt;'>
                        <b>Émis par :</b> " . ($percNom ?: '—') . " &nbsp;·&nbsp; <b>Le :</b> {$date}
                    </span>
                </td>
                <td width='38%' align='center' style='vertical-align:middle;'>
                    {$qrCode}<br/>
                    <span style='font-size:5pt;color:#888;font-style:italic;'>Scannez pour vérifier</span>
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
    //  RENDU PDF — A6 PAYSAGE (148 × 105 mm = A4/4)
    // ═════════════════════════════════════════════════════════════════════

    private function renderExemplaire(string $block, string $filename): string
    {
        $html = "
        <html><head><style>
            body { font-family: Arial, sans-serif; font-size: " . self::FONT_BASE . "pt; margin:0; padding:0; }
        </style></head>
        <body>
            {$block}
        </body></html>";

        return $this->renderPdf($html, $filename);
    }

    /** @deprecated */
    private function renderDoubleExemplaire(string $block, string $filename): string {
        return $this->renderExemplaire($block, $filename);
    }
    /** @deprecated */
    private function renderSimpleExemplaire(string $block, string $filename): string {
        return $this->renderExemplaire($block, $filename);
    }
    /** @deprecated */
    private function renderDeuxPages(string $block, string $filename): string {
        return $this->renderExemplaire($block, $filename);
    }

    private function renderEtatLabo(string $html, string $filename): string
    {
        if (!class_exists('TCPDF')) {
            return $this->fallbackHtml($html, $filename);
        }
        // L'état labo reste en A4 portrait (rapport interne)
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('CSI DirectAid Maradi');
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 10, 12);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        return $this->savePdf($pdf, $filename);
    }

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
        // ✅ A6 PAYSAGE : 148 × 105 mm (= A4 divisé en 4)
        $pdf = new TCPDF('L', 'mm', 'A6', true, 'UTF-8', false);
        $pdf->SetCreator('CSI DirectAid Maradi');
        $pdf->SetAuthor('CSI DirectAid Maradi');
        $pdf->SetAutoPageBreak(true, 4);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(4, 4, 4);
        $pdf->SetFont('helvetica', '', self::FONT_BASE);
        $pdf->setImageScale(1.25);
        // Réduit l'espacement vertical entre éléments pour densifier la mise en page
        $pdf->setCellHeightRatio(1.15);
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
    //  FALLBACK HTML
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
            @page { size: A6 landscape; margin: 4mm; }
            @media print { .no-print { display: none !important; } }
            body { font-family: Arial, sans-serif; font-size: 8pt; }
            table { border-collapse: collapse; }
            table[border="1"] td, table[border="1"] th { border: 0.5pt solid #444; }
            </style>
            <div class="no-print" style="padding:8px;background:#e8f5e9;text-align:center;">
                <button onclick="window.print()" style="background:#2e7d32;color:#fff;border:none;padding:6px 16px;border-radius:6px;cursor:pointer;font-size:13px;">🖨️ Imprimer</button>
                <button onclick="window.close()" style="background:#999;color:#fff;border:none;padding:6px 16px;border-radius:6px;cursor:pointer;margin-left:8px;">Fermer</button>
            </div>',
            $html
        );

        file_put_contents($file, $printHtml);
        return $file;
    }

    /** @deprecated */
    private function fallbackHtmlDeuxPages(string $block, string $filename): string
    {
        $html = "<html><head></head><body>{$block}</body></html>";
        return $this->fallbackHtml($html, $filename);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  QR CODE (LOGIQUE INCHANGÉE)
    // ═════════════════════════════════════════════════════════════════════

    private function buildQrCode(array $recu, int $totalAffiche, bool $isOrphelin, bool $afficherValidite = false): string
    {
        if (!class_exists('TCPDF2DBarcode')) {
            $candidates = [
                ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php',
                ROOT_PATH . '/vendor/tcpdf/tcpdf_barcodes_2d.php',
            ];
            foreach ($candidates as $c) {
                if (file_exists($c)) { require_once $c; break; }
            }
            if (!class_exists('TCPDF2DBarcode')) {
                error_log('[PdfGenerator] TCPDF2DBarcode introuvable');
                return '';
            }
        }

        $percNom = '';
        if (!empty($recu['whodone'])) {
            $stmt = $this->pdo->prepare("SELECT nom FROM utilisateurs WHERE id = ? LIMIT 1");
            $stmt->execute([$recu['whodone']]);
            $percNom = (string)$stmt->fetchColumn();
        }

        $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);

        if ($isOrphelin) {
            $statut = 'ORPHELIN-DirectAid';
        } elseif (($recu['type_patient'] ?? '') === 'acte_gratuit') {
            $statut = 'ACTE GRATUIT';
        } else {
            $statut = 'NORMAL';
        }

        $extraType = '';
        if (!$isOrphelin
            && ($recu['type_recu'] ?? '') === 'consultation'
            && (int)$totalAffiche === self::TARIF_OBSERVATION) {
            $extraType = ' (MISE EN OBSERVATION)';
        }

        $totalLib = $isOrphelin
            ? '0 F (pris en charge)'
            : number_format($totalAffiche, 0, ',', ' ') . ' F';

        $dateEmission = date('d/m/Y H:i:s', strtotime($recu['whendone']));
        $dateGen      = date('d/m/Y H:i:s');

        $blocValiditeQr = '';
        if ($afficherValidite) {
            $dateFin = date('d/m/Y', strtotime('+' . self::VALIDITE_CONSULTATION_JOURS . ' days', strtotime($recu['whendone'])));
            $blocValiditeQr = "Validite : " . self::VALIDITE_CONSULTATION_JOURS . " jours\n"
                . "Valable jusqu'au : {$dateFin}\n"
                . "-----------------\n";
        }

        $telPlain = trim((string)($recu['telephone'] ?? ''));
        if ($telPlain === '' || $telPlain === self::TELEPHONE_PAR_DEFAUT) {
            $telPlain = 'Non renseigne';
        }

        $contenuQr = "=== CSI DIRECTAID MARADI ===\n"
            . "Recu :