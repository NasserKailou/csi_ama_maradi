<?php
/**
 * PdfGenerator – Génération des reçus A5 (CSI DirectAid Maradi)
 *
 * RÈGLES :
 *  - Consultation NORMALE (standard)  : 2 exemplaires + validité 3 jours
 *      • 300 F consultation
 *      • + 100 F carnet (optionnel)
 *      • + 100 F redevance/supplément si âge > 5 ans
 *  - Consultation MISE EN OBSERVATION : 2 exemplaires + validité 3 jours
 *      • 1000 F fixe (pas de carnet, pas de redevance)
 *  - Consultation ACTE GRATUIT  : 2 exemplaires, sans validité
 *      avec_carnet=0 → 0 F
 *      avec_carnet=1 → Carnet 100 F (total 100 F)
 *      avec_carnet=2 → Carnet 100 F + Fiche 300 F (total 400 F)
 *  - Examen     : 2 exemplaires (1 page A5 si court, 2 pages sinon), sans validité
 *  - Pharmacie  : 2 exemplaires (1 page A5 si court, 2 pages sinon), sans validité
 *
 * RÈGLE ORPHELIN : prix barrés, total 0 F en rouge.
 * RÈGLE TÉLÉPHONE : '99999999' affiché comme "Non renseigné".
 *
 * QR CODE : génération via TCPDF2DBarcode → fichier PNG temporaire
 *           dans uploads/pdf/qr_tmp/ (compatible XAMPP & production).
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

    /** @var string[] Liste des PNG QR temporaires créés par cette instance — purgés à la fin. */
    private array $qrTempFiles = [];

    private const SEUIL_DOUBLE_PAGE          = 8;
    private const SEUIL_EXAMEN_DOUBLE_PAGE   = 6;
    private const VALIDITE_CONSULTATION_JOURS = 3;

    // ── Mots pour montant en lettres ───────────────────────────────────
    private const UNITES = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf'
    ];
    private const DIZAINES = [
        '', '', 'vingt', 'trente', 'quarante', 'cinquante',
        'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'
    ];
    private const TARIF_CARNET_AG            = 100;
    private const TARIF_FICHE_AG             = 300;
    private const TARIF_OBSERVATION          = 1000;   // ✅ Mise en observation
    private const AGE_LIMITE_SUPPLEMENT      = 5;      // ✅ Redevance si âge > 5
    private const TARIF_SUPPLEMENT_ADULTE    = 100;
    private const TELEPHONE_PAR_DEFAUT       = '99999999';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadConfig();
        $this->cleanupOldQrFiles();
    }

    public function __destruct()
    {
        // Suppression des QR PNG temporaires créés pendant la requête courante.
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

        // Logo Ministère
        $logoMin = $this->config['logo_ministere'] ?? '';
        if ($logoMin && file_exists(ROOT_PATH . '/uploads/logos/' . $logoMin)) {
            $this->logoMinistere = ROOT_PATH . '/uploads/logos/' . $logoMin;
        } elseif (file_exists(ROOT_PATH . '/uploads/logos/logo_ministere.png')) {
            $this->logoMinistere = ROOT_PATH . '/uploads/logos/logo_ministere.png';
        }

        // Logo DirectAid
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
    //  MONTANT EN LETTRES (F CFA)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Convertit un entier en lettres françaises suivi de "F CFA".
     * Ex: 1500 → "Mille cinq cents francs CFA"
     */
    public function montantEnLettres(int $montant): string
    {
        if ($montant === 0) return 'Zéro franc CFA';
        if ($montant < 0)  return 'Moins ' . $this->montantEnLettres(-$montant);

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

    /**
     * ✅ Détecte si une ligne correspond à la redevance/supplément âge.
     * On regarde le libellé (insensible à la casse) car le type_ligne
     * peut ne pas exister dans la table actuelle.
     */
  private function estLigneSupplement(array $ligne): bool
{
    if (!empty($ligne['type_ligne']) && $ligne['type_ligne'] === 'redevance') {
        return true;
    }
    // Fallback pour anciennes lignes sans type_ligne
    $lib = mb_strtolower((string)($ligne['libelle'] ?? ''), 'UTF-8');
    return str_contains($lib, 'supplément')
        || str_contains($lib, 'supplement')
        || str_contains($lib, 'redevance');
}

    /**
     * ✅ Détecte si la consultation est une "mise en observation".
     * Le libellé de la ligne principale contient "observation".
     */
  /**
 * ✅ Détecte si la consultation est une "mise en observation".
 * Triple critère : libellé contient "observation" OU tarif = 1000 F sur la ligne principale.
 */
private function estConsultationObservation(array $items): bool
{
    foreach ($items as $it) {
        if (!empty($it['type_ligne']) && $it['type_ligne'] === 'observation') {
            return true;
        }
        // Fallback : libellé contient "observation"
        $lib = mb_strtolower((string)($it['libelle'] ?? ''), 'UTF-8');
        if (str_contains($lib, 'observation')) return true;
    }
    return false;
}


    // ═════════════════════════════════════════════════════════════════════
    //  MÉTHODES PUBLIQUES
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

    // ── Identifier la ligne principale (PAS la ligne de supplément) ──
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

    // ── Calcul du tarif principal ──
    if ($estGratuit) {
        $tarifConsult = 0;
    } elseif ($isObservation) {
        // Toujours 1000 F pour une mise en observation
        $tarifConsult = (int)($ligneBase['tarif'] ?? 0) ?: self::TARIF_OBSERVATION;
    } else {
        $tarifConsult = (int)($ligneBase['tarif'] ?? 0);
        if ($tarifConsult === 0) {
            $tarifConsult = defined('TARIF_CONSULTATION') ? (int)TARIF_CONSULTATION : 300;
        }
    }

    // ── Libellé affiché ──
    if ($isObservation) {
        $acteLibelle = 'Mise en observation';
    } else {
        $acteLibelle = $ligneBase['libelle'] ?? 'Consultation';
    }

    // ── Ligne supplément âge (si présente, hors observation) ──
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
    //  EN-TÊTE OFFICIEL
    // ═════════════════════════════════════════════════════════════════════

    private function buildEntete(): string
    {
        // A6 compact : logos réduits, texte serré
        $logoSize = 22;

        $logoMinTag = $this->logoMinistere
            ? "<img src=\"{$this->logoMinistere}\" width=\"{$logoSize}\" height=\"{$logoSize}\"/>"
            : '';

        $logoDaTag = $this->logoDirectAid
            ? "<img src=\"{$this->logoDirectAid}\" width=\"{$logoSize}\" height=\"{$logoSize}\"/>"
            : '';

        $tel = trim($this->cfg('telephone', ''));
        $telSpan = $tel !== ''
            ? '<br/><span style="font-size:6pt;color:#555;">Tél : ' . htmlspecialchars($tel, ENT_QUOTES, 'UTF-8') . '</span>'
            : '';

        return '
        <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1.5pt solid #2e7d32;padding-bottom:2pt;">
            <tr>
                <td width="15%" style="text-align:center;vertical-align:middle;">' . $logoMinTag . '</td>
                <td width="70%" style="text-align:center;vertical-align:middle;line-height:1.3;">
                    <span style="font-size:7pt;font-weight:bold;">RÉPUBLIQUE DU NIGER</span><br/>
                    <span style="font-size:6pt;">Ministère de la Santé et de l\'Hygiène Publique</span><br/>
                    <span style="font-size:7.5pt;font-weight:bold;color:#2e7d32;">CSI ZARIA I / Direct Aid – MARADI</span>'
                    . $telSpan . '
                </td>
                <td width="15%" style="text-align:center;vertical-align:middle;">' . $logoDaTag . '</td>
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
        <table width='100%' cellpadding='2' cellspacing='0' style='margin-top:2pt;background:#fff8e1;border:1pt solid #f9a825;'>
            <tr>
                <td style='padding:2pt 3pt;font-size:6.5pt;color:#5d4037;'>
                    <b style='color:#e65100;'>VALIDITÉ :</b>
                    Valable <b>" . self::VALIDITE_CONSULTATION_JOURS . " jours</b> —
                    Émis le <b>{$emissionFmt}</b> —
                    Exp. : <span style='color:#c62828;font-weight:bold;'>{$finFmt}</span>
                </td>
            </tr>
        </table>";
    }

    /**
     * ✅ Bloc consultation enrichi :
     *  - Cas ACTE GRATUIT (inchangé)
     *  - Cas MISE EN OBSERVATION (1000 F, pas de carnet, pas de redevance)
     *  - Cas STANDARD (consultation + carnet optionnel + redevance âge optionnelle)
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
        // ──────────────────────────────────────────────────────────────────
        // Cas ACTE GRATUIT
        // ──────────────────────────────────────────────────────────────────
        error_log("[PdfGenerator] recu={$recu['numero_recu']} | isObservation=" . ($isObservation?'OUI':'NON') . " | tarif={$tarif} | libelle={$acteLibelle}");

        $bdrC = 'border-top:1px solid #999;border-bottom:1px solid #999;border-left:1px solid #999;border-right:1px solid #999;';

        if ($isActeGratuit) {
            $rows = "
                <tr>
                    <td style='padding:3pt 4pt;{$bdrC}'>
                        {$acteLibelle}
                        <span style='color:#1565c0;font-size:7pt;font-weight:bold;'> (ACTE GRATUIT)</span>
                    </td>
                    <td style='padding:3pt 4pt;text-align:right;color:#2e7d32;font-weight:bold;{$bdrC}'>Gratuit</td>
                </tr>";

            $totalAff = 0;

            if ($optionCarnet === 1) {
                $rows .= "
                <tr>
                    <td style='padding:3pt 4pt;{$bdrC}'>
                        Carnet de santé
                        <span style='color:#666;font-size:7pt;'> (obligatoire)</span>
                    </td>
                    <td style='padding:3pt 4pt;text-align:right;font-weight:bold;{$bdrC}'>"
                    . number_format(self::TARIF_CARNET_AG, 0, ',', ' ') . " F</td>
                </tr>";
                $totalAff = self::TARIF_CARNET_AG;
            } elseif ($optionCarnet === 2) {
                $rows .= "
                <tr>
                    <td style='padding:3pt 4pt;{$bdrC}'>
                        Carnet de santé
                        <span style='color:#666;font-size:7pt;'> (obligatoire)</span>
                    </td>
                    <td style='padding:3pt 4pt;text-align:right;font-weight:bold;{$bdrC}'>"
                    . number_format(self::TARIF_CARNET_AG, 0, ',', ' ') . " F</td>
                </tr>
                <tr>
                    <td style='padding:3pt 4pt;{$bdrC}'>
                        Fiche de consultation
                        <span style='color:#666;font-size:7pt;'> (premier passage)</span>
                    </td>
                    <td style='padding:3pt 4pt;text-align:right;font-weight:bold;{$bdrC}'>"
                    . number_format(self::TARIF_FICHE_AG, 0, ',', ' ') . " F</td>
                </tr>";
                $totalAff = self::TARIF_CARNET_AG + self::TARIF_FICHE_AG;
            }

            return $this->blocRecu($recu, 'CONSULTATION (ACTE GRATUIT)', $rows, $totalAff, false, false, '', 2, false);
        }

        // ──────────────────────────────────────────────────────────────────
        // ✅ Cas MISE EN OBSERVATION (1000 F, pas de carnet, pas de redevance)
        // ──────────────────────────────────────────────────────────────────
        if ($isObservation) {
            $libelleObs = htmlspecialchars($acteLibelle ?: 'Mise en observation', ENT_QUOTES, 'UTF-8');
            $prixObsAff = $this->fmtMontant($tarif, $isOrphelin);

            $rows = "
                <tr>
                    <td style=\"padding:3pt 4pt;{$bdrC}\">
                        <b style='color:#e65100;'>{$libelleObs}</b>
                        <span style='color:#666;font-size:7pt;'> (tarif fixe)</span>
                    </td>
                    <td style=\"padding:3pt 4pt;text-align:right;{$bdrC}\">{$prixObsAff}</td>
                </tr>";

            $totalAff = $isOrphelin ? 0 : $tarif;
            $afficherValidite = !$isOrphelin;

            return $this->blocRecu(
                $recu,
                'MISE EN OBSERVATION',
                $rows,
                $totalAff,
                $isOrphelin,
                false, '',
                2,
                $afficherValidite
            );
        }

        // ──────────────────────────────────────────────────────────────────
        // Cas STANDARD (NORMAL ou ORPHELIN)
        // ──────────────────────────────────────────────────────────────────
        $prixConsultAff = $this->fmtMontant($tarif, $isOrphelin);
        $avecCarnet  = ($optionCarnet >= 1);
        $tarifCarnet = $avecCarnet ? ($tarifCarnetDb > 0 ? $tarifCarnetDb : 100) : 0;

        $carnetLine = '';
        if ($avecCarnet) {
            $prixCarnetAff = $this->fmtMontant($tarifCarnet, $isOrphelin);
            $carnetLine = "
                <tr>
                    <td style=\"padding:3pt 4pt;{$bdrC}\">Carnet de Soins</td>
                    <td style=\"padding:3pt 4pt;text-align:right;{$bdrC}\">{$prixCarnetAff}</td>
                </tr>";
        }

        // ✅ Ligne supplément âge > 5 ans (redevance reversée au ministère)
        $supplementLine = '';
        if ($supplementAge > 0) {
            $libSupp = $libelleSupplement !== ''
                ? htmlspecialchars($libelleSupplement, ENT_QUOTES, 'UTF-8')
                : 'Redevance (âge &gt; ' . self::AGE_LIMITE_SUPPLEMENT . ' ans)';

            $prixSuppAff = $this->fmtMontant($supplementAge, $isOrphelin);

            $supplementLine = "
                <tr>
                    <td style=\"padding:3pt 4pt;font-style:italic;color:#5d4037;{$bdrC}\">
                        {$libSupp}
                        <span style='color:#888;font-size:7pt;'> (reversée au ministère)</span>
                    </td>
                    <td style=\"padding:3pt 4pt;text-align:right;{$bdrC}\">{$prixSuppAff}</td>
                </tr>";
        }

        $rows = "
            <tr>
                <td style=\"padding:3pt 4pt;{$bdrC}\">{$acteLibelle}</td>
                <td style=\"padding:3pt 4pt;text-align:right;{$bdrC}\">{$prixConsultAff}</td>
            </tr>
            {$carnetLine}
            {$supplementLine}";

        $totalAff = $isOrphelin ? 0 : ($tarif + $tarifCarnet + $supplementAge);
        $afficherValidite = !$isOrphelin;

        return $this->blocRecu($recu, 'CONSULTATION', $rows, $totalAff, $isOrphelin, false, '', 2, $afficherValidite);
    }

    private function buildBlocExamen(array $recu, array $lignes, bool $isOrphelin): string
    {
        // TCPDF: seule façon fiable d'avoir des bordures = style inline complet sur chaque <td>
        // On n'utilise PAS <thead>/<tbody> — TCPDF les gère mal pour les bordures.
        // On passe des <tr> simples avec style de fond sur la 1re ligne (en-tête manuelle).
        $bdr  = 'border-top:1px solid #999;border-bottom:1px solid #999;border-left:1px solid #999;border-right:1px solid #999;';
        $rows = '';

        // Ligne d'en-tête (simulée avec style de fond)
        $rows .= "<tr style='background:#fff3e0;font-weight:bold;font-size:7pt;'>
            <td style='padding:2pt 3pt;width:76%;{$bdr}'>Examen prescrit</td>
            <td style='padding:2pt 3pt;text-align:right;{$bdr}'>Coût</td>
        </tr>";

        $total = 0;
        foreach ($lignes as $l) {
            $cout    = (int)$l['cout_total'];
            $coutAff = $this->fmtMontant($cout, $isOrphelin);
            $rows   .= "<tr>
                <td style='padding:2pt 3pt;font-size:7.5pt;{$bdr}'>{$l['libelle']}</td>
                <td style='padding:2pt 3pt;text-align:right;font-size:7.5pt;{$bdr}'>{$coutAff}</td>
            </tr>";
            $total += $cout;
        }

        // Ligne total
        $totalAff   = $isOrphelin ? 0 : $total;
        $totalLigne = $isOrphelin
            ? '<span style="color:#d32f2f;font-weight:bold;">0 F</span>'
            : '<b>' . number_format($total, 0, ',', ' ') . ' F</b>';
        $rows .= "<tr style='background:#e8f5e9;'>
            <td style='padding:3pt;text-align:right;font-weight:bold;font-size:7.5pt;{$bdr}'>TOTAL :</td>
            <td style='padding:3pt;text-align:right;{$bdr}'>{$totalLigne}</td>
        </tr>";

        // Montant en lettres
        $lettres = '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2pt;">
            <tr><td style="font-size:6.5pt;color:#444;font-style:italic;padding:2pt 0;">
                Arrêté à : <b>' . $this->montantEnLettres($totalAff) . '</b>
            </td></tr>
        </table>';

        return $this->blocRecu($recu, 'BON D\'EXAMEN', $rows, $totalAff, $isOrphelin, true, $lettres, 2, false);
    }

    private function buildBlocPharmacie(array $recu, array $lignes, bool $isOrphelin): string
    {
        // TCPDF: seule façon fiable d'avoir des bordures = style inline complet sur chaque <td>
        // On n'utilise PAS <thead>/<tbody> — TCPDF les gère mal pour les bordures.
        $bdr  = 'border-top:1px solid #999;border-bottom:1px solid #999;border-left:1px solid #999;border-right:1px solid #999;';
        $rows = '';

        // Ligne d'en-tête (simulée avec style de fond)
        $rows .= "<tr style='background:#e0f2f1;font-weight:bold;font-size:6.5pt;'>
            <td style='padding:2pt 3pt;{$bdr}'>Désignation</td>
            <td style='padding:2pt 3pt;{$bdr}'>Forme</td>
            <td style='padding:2pt 3pt;text-align:center;{$bdr}'>Qté</td>
            <td style='padding:2pt 3pt;text-align:right;{$bdr}'>P.U.</td>
            <td style='padding:2pt 3pt;text-align:right;{$bdr}'>Total</td>
        </tr>";

        $total = 0;
        foreach ($lignes as $l) {
            $pu       = (int)$l['prix_unitaire'];
            $totLigne = (int)$l['total_ligne'];
            $puAff    = $this->fmtMontant($pu, $isOrphelin);
            $totAff   = $this->fmtMontant($totLigne, $isOrphelin);

            $rows .= "<tr>
                <td style='padding:2pt 3pt;font-size:7pt;{$bdr}'>{$l['nom']}</td>
                <td style='padding:2pt 3pt;font-size:6.5pt;color:#555;{$bdr}'>{$l['forme']}</td>
                <td style='padding:2pt 3pt;text-align:center;font-size:7pt;{$bdr}'>{$l['quantite']}</td>
                <td style='padding:2pt 3pt;text-align:right;font-size:7pt;{$bdr}'>{$puAff}</td>
                <td style='padding:2pt 3pt;text-align:right;font-size:7pt;font-weight:bold;{$bdr}'>{$totAff}</td>
            </tr>";
            $total += $totLigne;
        }

        // Ligne total
        $totalAff   = $isOrphelin ? 0 : $total;
        $totalLigne = $isOrphelin
            ? '<span style="color:#d32f2f;font-weight:bold;">0 F</span>'
            : '<b>' . number_format($total, 0, ',', ' ') . ' F</b>';
        $rows .= "<tr style='background:#e8f5e9;'>
            <td colspan='4' style='padding:3pt;text-align:right;font-weight:bold;font-size:7pt;{$bdr}'>TOTAL :</td>
            <td style='padding:3pt;text-align:right;{$bdr}'>{$totalLigne}</td>
        </tr>";

        // Montant en lettres
        $lettres = '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:2pt;">
            <tr><td style="font-size:6pt;color:#444;font-style:italic;padding:1pt 0;">
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
        int    $nbColsTotal = 2,
        bool   $afficherValidite = false
    ): string {
        $numFormate = '#' . str_pad($recu['numero_recu'], 5, '0', STR_PAD_LEFT);
        $date       = date('d/m/Y H:i', strtotime($recu['whendone']));
        $piedPage   = $this->cfg('pied_de_page', 'Merci de votre visite – Bonne santé.');

        // Ligne TOTAL (pour consultation — les autres blocs gèrent la leur en interne)
        $totalStr = $isOrphelin
            ? '<span style="color:#d32f2f;font-weight:bold;font-size:9pt;">0 F</span>'
            : '<b style="font-size:9pt;">' . number_format($total, 0, ',', ' ') . ' F</b>';

        $totalRow = '';
        if (!$hideTotal) {
            $colspan  = $nbColsTotal - 1;
            $totalRow = "
                <tr style='background:#e8f5e9;'>
                    <td colspan='{$colspan}' style='padding:3pt 4pt;text-align:right;font-weight:bold;border-top:1px solid #999;border-bottom:1px solid #999;border-left:1px solid #999;border-right:1px solid #999;font-size:7.5pt;'>TOTAL :</td>
                    <td style='padding:3pt 4pt;text-align:right;border-top:1px solid #999;border-bottom:1px solid #999;border-left:1px solid #999;border-right:1px solid #999;'>{$totalStr}</td>
                </tr>";
        }

        // Badges
        $badgeOrphelin = $isOrphelin
            ? '<span style="background:#7b1fa2;color:#fff;padding:1pt 3pt;font-size:6pt;font-weight:bold;border-radius:2pt;">PRIS EN CHARGE — DIRECTAID AMA</span>'
            : '';

        $badgeActeGratuit = '';
        if (($recu['type_patient'] ?? '') === 'acte_gratuit') {
            $badgeActeGratuit = '<span style="background:#1565c0;color:#fff;padding:1pt 3pt;font-size:6pt;font-weight:bold;border-radius:2pt;">ACTE GRATUIT</span>';
        }

        $badgeObservation = '';
        if (str_contains(strtoupper($titre), 'OBSERVATION')) {
            $badgeObservation = '<span style="background:#f9a825;color:#000;padding:1pt 3pt;font-size:6pt;font-weight:bold;border-radius:2pt;">MISE EN OBSERVATION</span>';
        }

        $provenanceCell = !empty($recu['provenance'])
            ? "<small style='color:#555;font-size:6.5pt;'>Prov. : " . htmlspecialchars((string)$recu['provenance'], ENT_QUOTES, 'UTF-8') . "</small>"
            : '';

        $infoPatient = '';
        if (!empty($recu['sexe']) || !empty($recu['age'])) {
            $infoPatient = "<small style='color:#555;font-size:6.5pt;'>"
                . ($recu['sexe'] ?? '') . ($recu['age'] ? ' · ' . $recu['age'] . ' ans' : '')
                . "</small>";
        }

        $blocValidite = $afficherValidite ? $this->buildBlocValidite($recu) : '';
        $qrCode       = $this->buildQrCode($recu, $total, $isOrphelin, $afficherValidite);

        $percNom = '';
        if (!empty($recu['whodone'])) {
            $stmt = $this->pdo->prepare("SELECT nom FROM utilisateurs WHERE id = ? LIMIT 1");
            $stmt->execute([$recu['whodone']]);
            $percNom = (string)$stmt->fetchColumn();
        }

        $badgesDroite = trim($badgeOrphelin . ' ' . $badgeActeGratuit . ' ' . $badgeObservation);
        $telAffiche   = $this->fmtTelephone($recu['telephone'] ?? '');
        $patientNom   = htmlspecialchars((string)$recu['patient_nom'], ENT_QUOTES, 'UTF-8');

        // Bandeau titre : orangé pour observation, vert sinon
        $bgTitre = str_contains(strtoupper($titre), 'OBSERVATION') ? '#e65100' : '#2e7d32';

        // Montant en lettres pour consultation (hideTotal=false = la consultation gère le total)
        $ligneMontantLettres = '';
        if (!$hideTotal && !$isOrphelin && $total > 0) {
            $ligneMontantLettres = "<table width='100%' cellpadding='0' cellspacing='0' style='margin-top:2pt;'>
                <tr><td style='font-size:6pt;color:#444;font-style:italic;padding:1pt 0;'>
                    Arrêté à : <b>" . $this->montantEnLettres($total) . "</b>
                </td></tr>
            </table>";
        }

        return "
        " . $this->buildEntete() . "

        <table width='100%' cellpadding='0' cellspacing='0' style='margin-top:2pt;'>
            <tr>
                <td style='text-align:center;background:{$bgTitre};color:#fff;padding:2pt 3pt;font-weight:bold;font-size:8pt;letter-spacing:0.5pt;'>
                    {$titre} &nbsp;—&nbsp; N° {$numFormate}
                </td>
            </tr>
        </table>

        <table width='100%' cellpadding='1' cellspacing='0' style='margin-top:2pt;font-size:7.5pt;border:1pt solid #ccc;'>
            <tr>
                <td width='60%' style='padding:2pt 3pt;'>
                    <b>Patient :</b> {$patientNom}<br/>
                    {$infoPatient}
                    " . ($provenanceCell ? "<br/>{$provenanceCell}" : '') . "
                </td>
                <td width='40%' style='text-align:right;padding:2pt 3pt;border-left:1pt solid #ccc;'>
                    <small style='font-size:6.5pt;'>Date : <b>{$date}</b></small><br/>
                    <small style='font-size:6.5pt;'>Tél : {$telAffiche}</small>
                    " . ($badgesDroite ? "<br/>{$badgesDroite}" : '') . "
                </td>
            </tr>
        </table>

        <table border='1' cellpadding='2' cellspacing='0' width='100%' style='border-collapse:collapse;border:1px solid #555;font-size:7.5pt;margin-top:2pt;'>
            <tbody>" . $tableRows . $totalRow . "</tbody>
        </table>

        {$ligneMontantLettres}

        {$blocValidite}

        {$zoneSupplementaire}

        <table width='100%' cellpadding='1' cellspacing='0' style='margin-top:3pt;font-size:6.5pt;border-top:0.5pt solid #ccc;'>
            <tr>
                <td width='60%' style='vertical-align:middle;padding-right:4pt;'>
                    <i style='color:#666;'>{$piedPage}</i><br/>
                    <small style='color:#888;font-size:6pt;'>
                        <b>Émis par :</b> " . ($percNom ?: '—') . " &nbsp;·&nbsp; <b>Le :</b> {$date}
                    </small>
                </td>
                <td width='40%' style='text-align:center;vertical-align:middle;'>
                    {$qrCode}
                    <br/><span style='font-size:5.5pt;color:#888;font-style:italic;'>Scannez pour vérifier</span>
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
    //  RENDUS PDF — A6 copie unique
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Rendu A6 portrait copie unique — méthode principale.
     */
    private function renderExemplaire(string $block, string $filename): string
    {
        $html = "
        <html><head><style>
            body { font-family: Arial, sans-serif; font-size: 7.5pt; margin: 0; padding: 0; }
            table { border-color: #555; }
        </style></head>
        <body>
            {$block}
        </body></html>";

        return $this->renderPdf($html, $filename);
    }

    /** @deprecated — routes to renderExemplaire */
    private function renderDoubleExemplaire(string $block, string $filename): string
    {
        return $this->renderExemplaire($block, $filename);
    }

    /** @deprecated — routes to renderExemplaire */
    private function renderSimpleExemplaire(string $block, string $filename): string
    {
        return $this->renderExemplaire($block, $filename);
    }

    /** @deprecated — routes to renderExemplaire */
    private function renderDeuxPages(string $block, string $filename): string
    {
        return $this->renderExemplaire($block, $filename);
    }

    private function renderEtatLabo(string $html, string $filename): string
    {
        return $this->renderPdf($html, $filename);
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
        // A6 portrait : 105 × 148 mm — copie unique
        $pdf = new TCPDF('P', 'mm', 'A6', true, 'UTF-8', false);
        $pdf->SetCreator('CSI DirectAid Maradi');
        $pdf->SetAuthor('CSI DirectAid Maradi');
        $pdf->SetAutoPageBreak(true, 5);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(5, 5, 5);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->setImageScale(1.25);
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
    //  FALLBACKS HTML (A6)
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
            @page { size: A6 portrait; margin: 5mm; }
            @media print { .no-print { display: none !important; } }
            body { font-family: Arial, sans-serif; font-size: 7.5pt; }
            table { border-color: #555; }
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

    /** @deprecated — routes to fallbackHtml for backward compatibility */
    private function fallbackHtmlDeuxPages(string $block, string $filename): string
    {
        $html = "<html><head><style>
        @page { size: A6 portrait; margin: 5mm; }
        @media print { .no-print { display: none !important; } }
        body { font-family: Arial, sans-serif; font-size: 7.5pt; margin: 0; }
        table { border-color: #555; }
        </style></head>
        <body>{$block}</body></html>";

        return $this->fallbackHtml($html, $filename);
    }

    // ═════════════════════════════════════════════════════════════════════
    //  QR CODE — version robuste (PNG sur disque)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Génère le QR code et retourne un tag <img> qui pointe vers
     * un fichier PNG temporaire. Compatible XAMPP & production.
     */
    private function buildQrCode(array $recu, int $totalAffiche, bool $isOrphelin, bool $afficherValidite = false): string
    {
        // ── 1. Vérifier la disponibilité de TCPDF2DBarcode ─────────────────
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

        // ── 2. Construire le contenu textuel du QR ─────────────────────────
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

        // ✅ Marqueur "OBSERVATION" dans le QR si total = 1000 sur consultation
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

        // ⚠️ Caractères ASCII uniquement dans le QR pour compatibilité maximale des scanners
        $contenuQr = "=== CSI DIRECTAID MARADI ===\n"
            . "Recu : {$numFormate}\n"
            . "Type : " . strtoupper($recu['type_recu']) . $extraType . "\n"
            . "Date emission : {$dateEmission}\n"
            . "-----------------\n"
            . $blocValiditeQr
            . "Patient : " . $recu['patient_nom'] . "\n"
            . "Tel : " . $telPlain . "\n"
            . ($recu['sexe'] ? "Sexe/Age : {$recu['sexe']} / " . ($recu['age'] ?? '?') . " ans\n" : '')
            . ($recu['provenance'] ? "Provenance : {$recu['provenance']}\n" : '')
            . "-----------------\n"
            . "Statut : {$statut}\n"
            . "Montant : {$totalLib}\n"
            . "Percepteur : " . ($percNom ?: '-') . "\n"
            . "-----------------\n"
            . "Genere le : {$dateGen}";

        // ── 3. Générer le PNG et le sauvegarder sur disque ─────────────────
        try {
            $qr = new TCPDF2DBarcode($contenuQr, 'QRCODE,M');

            // Vérifier la disponibilité de GD (présent par défaut sur XAMPP)
            if (!function_exists('imagecreate')) {
                error_log('[PdfGenerator] Extension GD non disponible');
                return '';
            }

            // Génère le PNG : 8 px par module, marge 8 px, noir
            $pngData = $qr->getBarcodePngData(8, 8, [0, 0, 0]);

            if ($pngData === false || $pngData === '' || strlen($pngData) < 100) {
                error_log('[PdfGenerator] QR PNG vide ou invalide (taille=' . strlen((string)$pngData) . ')');
                return '';
            }

            // Préparer le dossier temporaire
            $qrDir = ROOT_PATH . '/uploads/pdf/qr_tmp/';
            if (!is_dir($qrDir)) {
                if (!@mkdir($qrDir, 0755, true) && !is_dir($qrDir)) {
                    error_log('[PdfGenerator] Impossible de créer le dossier ' . $qrDir);
                    return '';
                }
            }
            if (!is_writable($qrDir)) {
                error_log('[PdfGenerator] Dossier QR non inscriptible : ' . $qrDir);
                return '';
            }

            // Nom unique (id reçu + uniqid + microtime)
            $qrFile = $qrDir . 'qr_' . (int)$recu['id'] . '_' . uniqid('', true) . '.png';
            if (file_put_contents($qrFile, $pngData) === false) {
                error_log('[PdfGenerator] Echec ecriture QR : ' . $qrFile);
                return '';
            }

            // Mémoriser pour suppression à la fin de l'instance
            $this->qrTempFiles[] = $qrFile;

            // ✅ TCPDF accepte un chemin absolu local — fonctionne en local & prod
            return '<img src="' . $qrFile . '" width="42" height="42"/>';

        } catch (Throwable $e) {
            error_log('[PdfGenerator] Erreur QR : ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Nettoie les QR temporaires de plus d'une heure.
     * Appelée 1 fois sur 20 environ pour ne pas alourdir les requêtes.
     */
    private function cleanupOldQrFiles(): void
    {
        if (mt_rand(1, 20) !== 1) return;

        $qrDir = ROOT_PATH . '/uploads/pdf/qr_tmp/';
        if (!is_dir($qrDir)) return;

        $now = time();
        foreach (glob($qrDir . 'qr_*.png') ?: [] as $f) {
            if (is_file($f) && ($now - filemtime($f)) > 3600) {
                @unlink($f);
            }
        }
    }
}
