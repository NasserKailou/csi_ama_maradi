<?php
/**
 * Facture de règlement DirectAid AMA — orphelins
 * Affiché via : index.php?page=reglements&action=facture&id=XXX
 *
 * Note : ce fichier est utilisé par le dispatcher dans modules/reglements/index.php.
 * Si tu l'appelles via le dispatcher, le bootstrap est déjà chargé.
 * Si tu l'appelles en direct, décommente la ligne de bootstrap ci-dessous.
 */

// require_once __DIR__ . '/../../index.php'; // optionnel si appelé en direct

requireRole('admin', 'comptable');
$pdo = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);

// ── Récupération du règlement ──────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT ro.*, u.nom AS regleur_nom, u.prenom AS regleur_prenom
    FROM reglements_orphelins ro
    LEFT JOIN utilisateurs u ON u.id = ro.whodone
    WHERE ro.id = ? AND ro.isDeleted = 0
");
$stmt->execute([$id]);
$reg = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$reg) { http_response_code(404); exit('Règlement introuvable'); }

// ── Récupération des reçus liés ────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT r.*, p.nom AS pat_nom, p.age, p.sexe, p.provenance, p.telephone
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.reglement_id = ? AND r.isDeleted = 0
    ORDER BY p.nom, r.whendone
");
$stmt->execute([$id]);
$recus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Détails par reçu ───────────────────────────────────────────────────────
$detailsParRecu = [];
if (!empty($recus)) {
    $ids = array_column($recus, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    // Consultations
    $stmt = $pdo->prepare("SELECT lc.recu_id, lc.libelle, lc.tarif, lc.tarif_carnet, lc.avec_carnet
                           FROM lignes_consultation lc
                           WHERE lc.isDeleted=0 AND lc.recu_id IN ($ph)");
    $stmt->execute($ids);
    foreach ($stmt as $l) {
        $mt = $l['avec_carnet'] ? ($l['tarif'] + $l['tarif_carnet']) : $l['tarif'];
        $detailsParRecu[$l['recu_id']][] = ['lib' => $l['libelle'], 'mt' => $mt, 'qte' => 1];
    }

    // Examens
    $stmt = $pdo->prepare("SELECT le.recu_id, le.libelle, le.cout_total
                           FROM lignes_examen le
                           WHERE le.isDeleted=0 AND le.recu_id IN ($ph)");
    $stmt->execute($ids);
    foreach ($stmt as $l) {
        $detailsParRecu[$l['recu_id']][] = ['lib' => $l['libelle'], 'mt' => $l['cout_total'], 'qte' => 1];
    }

    // Pharmacie
    $stmt = $pdo->prepare("SELECT lp.recu_id, lp.nom, lp.quantite, lp.prix_unitaire, lp.total_ligne
                           FROM lignes_pharmacie lp
                           WHERE lp.isDeleted=0 AND lp.recu_id IN ($ph)");
    $stmt->execute($ids);
    foreach ($stmt as $l) {
        $detailsParRecu[$l['recu_id']][] = ['lib' => $l['nom'], 'mt' => $l['total_ligne'], 'qte' => $l['quantite']];
    }
}

// ── Configuration centre ───────────────────────────────────────────────────
$nomCentre     = $pdo->query("SELECT valeur FROM config_systeme WHERE cle='nom_centre' AND isDeleted=0 LIMIT 1")->fetchColumn() ?: 'CSI AMA MARADI';
$adresseCentre = $pdo->query("SELECT valeur FROM config_systeme WHERE cle='adresse'    AND isDeleted=0 LIMIT 1")->fetchColumn() ?: '';
$telCentre     = $pdo->query("SELECT valeur FROM config_systeme WHERE cle='telephone'  AND isDeleted=0 LIMIT 1")->fetchColumn() ?: '';
$logoFile      = $pdo->query("SELECT valeur FROM config_systeme WHERE cle='logo_filename' AND isDeleted=0 LIMIT 1")->fetchColumn();
$logoUrl       = ($logoFile && file_exists(ROOT_PATH . '/uploads/logos/' . $logoFile))
    ? url('uploads/logos/' . $logoFile)
    : null;

// ── Statistiques ───────────────────────────────────────────────────────────
$nbOrphelins      = count(array_unique(array_column($recus, 'patient_id')));
$nbRecus          = count($recus);
$repartitionTypes = ['consultation' => 0, 'examen' => 0, 'pharmacie' => 0];
foreach ($recus as $r) {
    $repartitionTypes[$r['type_recu']] = ($repartitionTypes[$r['type_recu']] ?? 0) + (float)$r['montant_total'];
}

// ── Montant en lettres ─────────────────────────────────────────────────────
$montantLettres = function_exists('montantEnLettres')
    ? montantEnLettres((float)$reg['montant_total'])
    : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Facture <?= h($reg['numero_reglement']) ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 11px;
        color: #2c3e50;
        background: #ecf0f1;
        padding: 20px;
    }
    .page {
        max-width: 900px;
        margin: 0 auto;
        background: #fff;
        box-shadow: 0 0 30px rgba(0,0,0,0.15);
        position: relative;
    }

    /* ====== TOOLBAR ====== */
    .toolbar {
        background: #2c3e50;
        padding: 12px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #fff;
    }
    .toolbar .title { font-size: 13px; font-weight: 600; }
    .toolbar .actions a, .toolbar .actions button {
        display: inline-block;
        padding: 8px 16px;
        margin-left: 8px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: opacity 0.2s;
    }
    .toolbar .actions .btn-print { background: #27ae60; color: #fff; }
    .toolbar .actions .btn-back  { background: #95a5a6; color: #fff; }
    .toolbar .actions a:hover, .toolbar .actions button:hover { opacity: 0.85; }

    /* ====== HEADER FACTURE ====== */
    .invoice-header {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%);
        color: #fff;
        padding: 30px 40px;
        position: relative;
        overflow: hidden;
    }
    .invoice-header::before {
        content: ''; position: absolute; top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05); border-radius: 50%;
    }
    .invoice-header::after {
        content: ''; position: absolute; bottom: -30%; left: -5%;
        width: 250px; height: 250px;
        background: rgba(255,255,255,0.04); border-radius: 50%;
    }
    .header-flex {
        display: flex; justify-content: space-between; align-items: center;
        position: relative; z-index: 1;
    }
    .header-left .logo {
        max-height: 70px; background: #fff;
        padding: 6px; border-radius: 6px; margin-bottom: 10px;
    }
    .header-left h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
    .header-left .subtitle { font-size: 11px; opacity: 0.9; line-height: 1.5; }
    .header-right { text-align: right; }
    .header-right .doc-type {
        font-size: 10px; text-transform: uppercase; letter-spacing: 2px;
        opacity: 0.85; margin-bottom: 4px;
    }
    .header-right .doc-num { font-size: 24px; font-weight: 800; letter-spacing: 1px; }
    .header-right .doc-date { font-size: 11px; margin-top: 4px; opacity: 0.9; }

    /* ====== BANDEAU INFO ====== */
    .info-bar {
        background: #f8f9fa;
        padding: 18px 40px;
        border-bottom: 2px solid #198754;
        display: flex;
        justify-content: space-around;
        text-align: center;
    }
    .info-item { flex: 1; }
    .info-item .label {
        font-size: 9px; text-transform: uppercase; letter-spacing: 1px;
        color: #7f8c8d; margin-bottom: 4px;
    }
    .info-item .value { font-size: 14px; font-weight: 700; color: #198754; }
    .info-item.total .value { color: #c0392b; font-size: 18px; }

    /* ====== SECTIONS ====== */
    .content { padding: 25px 40px; position: relative; z-index: 1; }
    .section-title {
        font-size: 13px; font-weight: 700; color: #198754;
        text-transform: uppercase; letter-spacing: 1px;
        margin: 20px 0 12px; padding-bottom: 6px;
        border-bottom: 2px solid #198754;
        display: flex; justify-content: space-between; align-items: center;
    }
    .section-title .count {
        font-size: 11px; background: #198754; color: #fff;
        padding: 3px 10px; border-radius: 12px; font-weight: 600;
    }

    /* ====== INFO GRID ====== */
    .info-grid {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 15px; margin-bottom: 25px;
    }
    .info-card {
        background: #f8f9fa;
        border-left: 4px solid #198754;
        padding: 15px 18px;
        border-radius: 0 6px 6px 0;
    }
    .info-card .card-title {
        font-size: 10px; text-transform: uppercase; letter-spacing: 1px;
        color: #7f8c8d; margin-bottom: 8px; font-weight: 600;
    }
    .info-card .card-content { font-size: 12px; line-height: 1.6; }
    .info-card .card-content strong {
        color: #2c3e50; display: inline-block; min-width: 110px;
    }

    /* ====== TABLE ====== */
    table.invoice-table {
        width: 100%; border-collapse: collapse;
        margin: 12px 0 20px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        border-radius: 6px; overflow: hidden;
    }
    table.invoice-table th {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%);
        color: #fff; text-align: left; padding: 12px 10px;
        font-size: 10px; text-transform: uppercase;
        letter-spacing: 0.5px; font-weight: 600;
    }
    table.invoice-table td {
        padding: 10px; border-bottom: 1px solid #ecf0f1;
        font-size: 11px; vertical-align: top;
    }
    table.invoice-table tbody tr:nth-child(even) { background: #fafbfc; }
    table.invoice-table tbody tr:hover { background: #f1f8e9; }
    table.invoice-table .num-recu { font-weight: 700; color: #198754; }
    table.invoice-table .patient-name { font-weight: 600; color: #2c3e50; }
    table.invoice-table .patient-meta { font-size: 9px; color: #7f8c8d; }
    .type-badge {
        display: inline-block; padding: 3px 8px; border-radius: 10px;
        font-size: 9px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .type-consultation { background: #d4edda; color: #155724; }
    .type-examen       { background: #fff3cd; color: #856404; }
    .type-pharmacie    { background: #cce5ff; color: #004085; }
    .text-right  { text-align: right; }
    .text-center { text-align: center; }
    .montant {
        font-weight: 700; color: #2c3e50;
        font-family: 'Courier New', monospace;
    }
    .details-list { font-size: 10px; color: #555; }
    .details-list .det-item { padding: 1px 0; }

    /* ====== TOTAL ====== */
    .total-section {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%);
        color: #fff; padding: 20px 25px; border-radius: 8px;
        margin: 15px 0;
        display: flex; justify-content: space-between; align-items: center;
    }
    .total-section .label {
        font-size: 12px; text-transform: uppercase;
        letter-spacing: 2px; opacity: 0.9;
    }
    .total-section .amount {
        font-size: 28px; font-weight: 800;
        font-family: 'Courier New', monospace;
    }

    .montant-lettres {
        font-style: italic; background: #fff8e1;
        padding: 10px 14px; border-left: 3px solid #f39c12;
        border-radius: 0 4px 4px 0; margin: 12px 0 18px;
        font-size: 11px;
    }
    .montant-lettres strong {
        color: #e67e22; text-transform: uppercase;
        font-size: 9px; letter-spacing: 1px;
        display: block; margin-bottom: 2px;
    }

    /* ====== RÉPARTITION ====== */
    .repartition {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 12px; margin-bottom: 20px;
    }
    .repart-card {
        text-align: center; padding: 14px 10px;
        border-radius: 6px; border: 2px solid;
    }
    .repart-card.cons  { border-color: #28a745; background: #e8f5e9; }
    .repart-card.exam  { border-color: #ffc107; background: #fffaeb; }
    .repart-card.pharm { border-color: #007bff; background: #e3f2fd; }
    .repart-card .repart-label {
        font-size: 10px; text-transform: uppercase; letter-spacing: 1px;
        font-weight: 700; margin-bottom: 5px;
    }
    .repart-card .repart-value {
        font-size: 16px; font-weight: 800;
        font-family: 'Courier New', monospace;
    }
    .repart-card.cons  .repart-label, .repart-card.cons  .repart-value { color: #155724; }
    .repart-card.exam  .repart-label, .repart-card.exam  .repart-value { color: #856404; }
    .repart-card.pharm .repart-label, .repart-card.pharm .repart-value { color: #004085; }

    /* ====== OBSERVATIONS ====== */
    .observations {
        background: #fffbea;
        border: 1px dashed #f39c12;
        padding: 12px 16px; border-radius: 6px;
        margin: 15px 0; font-size: 11px;
    }
    .observations strong {
        color: #d35400; display: block; margin-bottom: 4px;
        font-size: 10px; text-transform: uppercase; letter-spacing: 1px;
    }

    /* ====== SIGNATURES ====== */
    .signatures {
        margin: 35px 0 20px;
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 25px;
    }
    .sig-box { text-align: center; }
    .sig-title {
        font-size: 10px; text-transform: uppercase; letter-spacing: 1px;
        color: #7f8c8d; margin-bottom: 50px; font-weight: 600;
    }
    .sig-line {
        border-top: 1.5px solid #2c3e50; padding-top: 6px;
        font-size: 11px; font-weight: 700; color: #2c3e50;
    }
    .sig-line .role {
        font-size: 9px; color: #7f8c8d;
        font-weight: 400; margin-top: 2px;
    }

    /* ====== FOOTER ====== */
    .invoice-footer {
        background: #2c3e50; color: #ecf0f1;
        padding: 12px 40px; text-align: center;
        font-size: 9px; line-height: 1.6;
    }
    .invoice-footer .corp { font-weight: 700; color: #fff; }
    .invoice-footer .meta { opacity: 0.8; }

    /* ====== WATERMARK ====== */
    .watermark {
        position: absolute; top: 50%; left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 130px; color: rgba(40, 167, 69, 0.06);
        font-weight: 900; pointer-events: none;
        white-space: nowrap; z-index: 0;
    }

    /* ====== IMPRESSION ====== */
    @media print {
        body { background: #fff; padding: 0; }
        .page { box-shadow: none; max-width: 100%; }
        .toolbar, .no-print { display: none !important; }
        .invoice-header, .info-bar, .total-section, table.invoice-table th {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        @page { margin: 12mm; size: A4; }
    }
</style>
</head>
<body>

<div class="page">
    <!-- TOOLBAR -->
    <div class="toolbar no-print">
        <div class="title">📄 Facture de règlement <?= h($reg['numero_reglement']) ?></div>
        <div class="actions">
            <a href="<?= url('index.php?page=reglements') ?>" class="btn-back">← Retour</a>
            <button onclick="window.print()" class="btn-print">🖨 Imprimer</button>
        </div>
    </div>

    <!-- WATERMARK -->
    <div class="watermark">RÉGLÉ</div>

    <!-- HEADER FACTURE -->
    <div class="invoice-header">
        <div class="header-flex">
            <div class="header-left">
                <?php if ($logoUrl): ?>
                    <img src="<?= h($logoUrl) ?>" alt="Logo" class="logo">
                <?php endif; ?>
                <h1><?= h($nomCentre) ?></h1>
                <div class="subtitle">
                    <?php if ($adresseCentre): ?><?= h($adresseCentre) ?><br><?php endif; ?>
                    <?php if ($telCentre): ?>📞 <?= h($telCentre) ?><?php endif; ?>
                </div>
            </div>
            <div class="header-right">
                <div class="doc-type">Facture de Règlement</div>
                <div class="doc-num"><?= h($reg['numero_reglement']) ?></div>
                <div class="doc-date">Émise le <?= date('d/m/Y', strtotime($reg['date_reglement'])) ?></div>
            </div>
        </div>
    </div>

    <!-- BANDEAU INFOS RAPIDES -->
    <div class="info-bar">
        <div class="info-item">
            <div class="label">Orphelins</div>
            <div class="value"><?= $nbOrphelins ?></div>
        </div>
        <div class="info-item">
            <div class="label">Reçus pris en charge</div>
            <div class="value"><?= $nbRecus ?></div>
        </div>
        <div class="info-item">
            <div class="label">Mode de paiement</div>
            <div class="value"><?= h(ucfirst(str_replace('_', ' ', $reg['mode_paiement']))) ?></div>
        </div>
        <div class="info-item total">
            <div class="label">Total réglé</div>
            <div class="value"><?= number_format((float)$reg['montant_total'],0,',',' ') ?> F</div>
        </div>
    </div>

    <div class="content">

        <!-- INFOS RÈGLEMENT -->
        <div class="section-title">Informations sur le règlement</div>
        <div class="info-grid">
            <div class="info-card">
                <div class="card-title">Programme bénéficiaire</div>
                <div class="card-content">
                    <strong>Nom :</strong> DirectAid AMA<br>
                    <strong>Type :</strong> Soins aux orphelins<br>
                    <strong>Localité :</strong> Maradi
                </div>
            </div>
            <div class="info-card">
                <div class="card-title">Détails du paiement</div>
                <div class="card-content">
                    <strong>Date :</strong> <?= date('d/m/Y', strtotime($reg['date_reglement'])) ?><br>
                    <strong>Mode :</strong> <?= h(strtoupper(str_replace('_', ' ', $reg['mode_paiement']))) ?><br>
                    <strong>Référence :</strong> <?= h($reg['reference_paiement'] ?: '—') ?><br>
                    <strong>Réglé par :</strong> <?= h(trim(($reg['regleur_nom']??'').' '.($reg['regleur_prenom']??''))) ?: '—' ?>
                </div>
            </div>
        </div>

        <!-- RÉPARTITION PAR PÔLE -->
        <?php if (array_sum($repartitionTypes) > 0): ?>
        <div class="section-title">Répartition par type de prestation</div>
        <div class="repartition">
            <div class="repart-card cons">
                <div class="repart-label">Consultations</div>
                <div class="repart-value"><?= number_format($repartitionTypes['consultation'],0,',',' ') ?> F</div>
            </div>
            <div class="repart-card exam">
                <div class="repart-label">Examens</div>
                <div class="repart-value"><?= number_format($repartitionTypes['examen'],0,',',' ') ?> F</div>
            </div>
            <div class="repart-card pharm">
                <div class="repart-label">Pharmacie</div>
                <div class="repart-value"><?= number_format($repartitionTypes['pharmacie'],0,',',' ') ?> F</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- DÉTAIL DES REÇUS -->
        <div class="section-title">
            Détail des reçus pris en charge
            <span class="count"><?= $nbRecus ?> reçu(s)</span>
        </div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th style="width:11%">N° Reçu</th>
                    <th style="width:10%">Date</th>
                    <th style="width:24%">Orphelin</th>
                    <th style="width:11%">Type</th>
                    <th style="width:30%">Détails des prestations</th>
                    <th style="width:14%" class="text-right">Montant</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recus as $r):
                $typeClass = 'type-' . $r['type_recu'];
            ?>
                <tr>
                    <td class="num-recu">#<?= str_pad($r['numero_recu'], 5, '0', STR_PAD_LEFT) ?></td>
                    <td><?= date('d/m/Y', strtotime($r['whendone'])) ?></td>
                    <td>
                        <div class="patient-name"><?= h($r['pat_nom']) ?></div>
                        <div class="patient-meta">
                            <?= $r['sexe'] === 'F' ? '♀' : '♂' ?> <?= $r['age'] ?> ans
                            <?php if ($r['telephone']): ?> · <?= h($r['telephone']) ?><?php endif; ?>
                        </div>
                    </td>
                    <td><span class="type-badge <?= $typeClass ?>"><?= h($r['type_recu']) ?></span></td>
                    <td class="details-list">
                        <?php foreach (($detailsParRecu[$r['id']] ?? []) as $d): ?>
                            <div class="det-item">
                                ▸ <?= h($d['lib']) ?>
                                <?php if (!empty($d['qte']) && $d['qte'] > 1): ?> × <?= $d['qte'] ?><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td class="text-right montant"><?= number_format((float)$r['montant_total'],0,',',' ') ?> F</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOTAL -->
        <div class="total-section">
            <div class="label">Montant Total à Régler</div>
            <div class="amount"><?= number_format((float)$reg['montant_total'],0,',',' ') ?> FCFA</div>
        </div>

        <?php if ($montantLettres): ?>
        <div class="montant-lettres">
            <strong>Arrêté en lettres</strong>
            <?= h($montantLettres) ?> francs CFA
        </div>
        <?php endif; ?>

        <!-- OBSERVATIONS -->
        <?php if ($reg['observations']): ?>
        <div class="observations">
            <strong>📝 Observations</strong>
            <?= nl2br(h($reg['observations'])) ?>
        </div>
        <?php endif; ?>

        <!-- SIGNATURES -->
        <div class="signatures">
            <div class="sig-box">
                <div class="sig-title">Établi par</div>
                <div class="sig-line">
                    <?= h(trim(($reg['regleur_nom']??'').' '.($reg['regleur_prenom']??''))) ?: 'Le Comptable' ?>
                    <div class="role">Comptable</div>
                </div>
            </div>
            <div class="sig-box">
                <div class="sig-title">Approuvé par</div>
                <div class="sig-line">
                    Représentant DirectAid AMA
                    <div class="role">Bailleur</div>
                </div>
            </div>
            <div class="sig-box">
                <div class="sig-title">Validé par</div>
                <div class="sig-line">
                    Directeur du CSI
                    <div class="role">Direction</div>
                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <div class="invoice-footer">
        <div class="corp"><?= h($nomCentre) ?> · Programme DirectAid AMA</div>
        <div class="meta">
            Document généré le <?= date('d/m/Y à H:i') ?> ·
            Facture n° <?= h($reg['numero_reglement']) ?> ·
            Page 1/1
        </div>
    </div>

</div>

</body>
</html>
