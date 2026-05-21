<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

requireRole('admin', 'comptable','percepteur');

// ✅ Récupérer la connexion PDO
$pdo = Database::getInstance();

$action = $_GET['action'] ?? 'liste';

// ============================================================
//  DISPATCHER UNIQUE — ⚠️ NE PAS DUPLIQUER
// ============================================================
switch ($action) {
    case 'ajax_details':
        ajaxDetails($pdo);
        exit;
    case 'ajax_ids':
        ajaxIds($pdo);
        exit;
    case 'enregistrer':
        enregistrerReglement($pdo);
        exit;  // par sécurité même si redirect() fait déjà exit
    case 'facture':
        afficherFacture($pdo);
        exit;
    case 'pdf_instance':
        imprimerInstancePdf($pdo);
        exit;
    case 'liste':
    default:
        afficherListe($pdo);
        break;
}



// =====================================================================
// AJAX : détails des reçus en instance d'un orphelin
// =====================================================================
function ajaxDetails(PDO $pdo) {
    $patientId = (int)($_GET['patient_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT r.id, r.numero_recu, r.type_recu, r.montant_total, r.whendone,
               u.nom AS perc_nom, u.prenom AS perc_prenom
        FROM recus r
        LEFT JOIN utilisateurs u ON u.id = r.whodone
        WHERE r.isDeleted = 0 AND r.type_patient = 'orphelin'
          AND r.statut_reglement = 'en_instance' AND r.patient_id = :p
        ORDER BY r.whendone DESC
    ");
    $stmt->execute([':p' => $patientId]);
    $recus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <table class="table table-sm table-hover">
        <thead class="table-light">
            <tr>
                <th><input type="checkbox" id="cbAll" checked></th>
                <th>N° Reçu</th><th>Date</th><th>Type</th>
                <th class="text-end">Montant</th><th>Percepteur</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recus as $r): ?>
            <tr>
                <td><input type="checkbox" class="cb-recu" value="<?= $r['id'] ?>" data-montant="<?= $r['montant_total'] ?>" checked></td>
                <td><strong><?= h($r['numero_recu']) ?></strong></td>
                <td><?= date('d/m/Y H:i', strtotime($r['whendone'])) ?></td>
                <td><span class="badge bg-info"><?= h($r['type_recu']) ?></span></td>
                <td class="text-end"><strong><?= number_format((float)$r['montant_total'],0,',',' ') ?></strong></td>
                <td><small><?= h(trim(($r['perc_nom']??'').' '.($r['perc_prenom']??''))) ?: '—' ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// =====================================================================
// AJAX : récupère tous les IDs en instance pour un orphelin (ou TOUS)
// =====================================================================
function ajaxIds(PDO $pdo) {
    header('Content-Type: application/json');
    $patientId = (int)($_GET['patient_id'] ?? 0);

    if ($patientId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM recus WHERE isDeleted=0 AND type_patient='orphelin' AND statut_reglement='en_instance' AND patient_id=?");
        $stmt->execute([$patientId]);
    } else {
        // Tous les orphelins en instance
        $stmt = $pdo->query("SELECT id FROM recus WHERE isDeleted=0 AND type_patient='orphelin' AND statut_reglement='en_instance'");
    }
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    // Total
    if (!empty($ids)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(montant_total),0) FROM recus WHERE id IN ($ph)");
        $stmt2->execute($ids);
        $total = (float)$stmt2->fetchColumn();
    } else {
        $total = 0;
    }

    echo json_encode(['ids' => $ids, 'total' => $total, 'nb' => count($ids)]);
}

// =====================================================================
// ENREGISTRER UN RÈGLEMENT (POST)
// =====================================================================
function enregistrerReglement(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        flash('error', 'Méthode invalide.');
        redirect('index.php?page=reglements');
    }
    if (function_exists('verifyCsrfToken') && !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token de sécurité invalide.');
        redirect('index.php?page=reglements');
    }

    $recusIds      = array_filter(array_map('intval', explode(',', $_POST['recus_ids'] ?? '')));
    $dateReglement = $_POST['date_reglement'] ?? date('Y-m-d');
    $montantSaisi  = (float)($_POST['montant_total'] ?? 0);
    $mode          = $_POST['mode_paiement'] ?? 'especes';
    $reference     = trim($_POST['reference_paiement'] ?? '');
    $observations  = trim($_POST['observations'] ?? '');

    if (empty($recusIds) || $montantSaisi <= 0) {
        flash('error', 'Sélection ou montant invalide.');
        redirect('index.php?page=reglements');
    }

    try {
        $pdo->beginTransaction();
        $ph = implode(',', array_fill(0, count($recusIds), '?'));
        $stmt = $pdo->prepare("
            SELECT id, montant_total FROM recus
            WHERE id IN ($ph) AND isDeleted=0
              AND type_patient='orphelin' AND statut_reglement='en_instance'
            ORDER BY whendone ASC
            FOR UPDATE
        ");
        $stmt->execute($recusIds);
        $recusValides = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($recusValides) !== count($recusIds)) {
            throw new Exception('Certains reçus ne sont plus disponibles (déjà réglés).');
        }

        $totalDu = array_sum(array_column($recusValides, 'montant_total'));
        if ($montantSaisi > $totalDu + 0.01) {
            throw new Exception('Le montant saisi ('.number_format($montantSaisi,0,',',' ').' FCFA) dépasse le total dû ('.number_format($totalDu,0,',',' ').' FCFA).');
        }

        $prefix = 'RGL-' . date('Ymd', strtotime($dateReglement)) . '-';
        $stmt = $pdo->prepare("SELECT COUNT(*)+1 FROM reglements_orphelins WHERE numero_reglement LIKE ?");
        $stmt->execute([$prefix.'%']);
        $numero = $prefix . str_pad((int)$stmt->fetchColumn(), 3, '0', STR_PAD_LEFT);

        $userId = Session::getUserId() ?? 0;
        $stmt = $pdo->prepare("
            INSERT INTO reglements_orphelins
            (numero_reglement, date_reglement, montant_total, nb_recus, mode_paiement,
             reference_paiement, observations, whendone, whodone, isDeleted)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0)
        ");
        $stmt->execute([
            $numero, $dateReglement, $montantSaisi, count($recusValides),
            $mode, $reference ?: null, $observations ?: null, $userId
        ]);
        $reglementId = $pdo->lastInsertId();

        if (abs($montantSaisi - $totalDu) < 0.01) {
            $stmt = $pdo->prepare("
                UPDATE recus SET statut_reglement='regle', date_reglement=?, reglement_id=?, montant_encaisse=montant_total
                WHERE id IN ($ph)
            ");
            $stmt->execute(array_merge([$dateReglement, $reglementId], $recusIds));
        } else {
            $reste = $montantSaisi;
            foreach ($recusValides as $r) {
                if ($reste >= $r['montant_total'] - 0.01) {
                    $upd = $pdo->prepare("UPDATE recus SET statut_reglement='regle', date_reglement=?, reglement_id=?, montant_encaisse=montant_total WHERE id=?");
                    $upd->execute([$dateReglement, $reglementId, $r['id']]);
                    $reste -= $r['montant_total'];
                } else {
                    break;
                }
            }
        }

        $pdo->commit();
        flash('success', "Règlement $numero enregistré avec succès (".number_format($montantSaisi,0,',',' ')." FCFA).");
        redirect('index.php?page=reglements&action=facture&id=' . $reglementId);

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Erreur : ' . $e->getMessage());
        redirect('index.php?page=reglements');
    }
}

// =====================================================================
// FACTURE IMPRIMABLE
// =====================================================================
function afficherFacture(PDO $pdo) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT ro.*, u.nom AS regleur_nom, u.prenom AS regleur_prenom
        FROM reglements_orphelins ro
        LEFT JOIN utilisateurs u ON u.id = ro.whodone
        WHERE ro.id = ? AND ro.isDeleted = 0
    ");
    $stmt->execute([$id]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reg) { http_response_code(404); exit('Règlement introuvable'); }

    $stmt = $pdo->prepare("
        SELECT r.*, p.nom AS pat_nom, p.age, p.sexe, p.provenance
        FROM recus r
        JOIN patients p ON p.id = r.patient_id
        WHERE r.reglement_id = ? AND r.isDeleted = 0
        ORDER BY p.nom, r.whendone
    ");
    $stmt->execute([$id]);
    $recus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Détails par reçu
    $detailsParRecu = [];
    if (!empty($recus)) {
        $ids = array_column($recus, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare("SELECT lc.recu_id, lc.libelle, lc.tarif, lc.tarif_carnet, lc.avec_carnet
                               FROM lignes_consultation lc
                               WHERE lc.isDeleted=0 AND lc.recu_id IN ($ph)");
        $stmt->execute($ids);
        foreach ($stmt as $l) {
            $mt = $l['avec_carnet'] ? ($l['tarif'] + $l['tarif_carnet']) : $l['tarif'];
            $detailsParRecu[$l['recu_id']][] = ['lib'=>$l['libelle'], 'mt'=>$mt];
        }

        $stmt = $pdo->prepare("SELECT le.recu_id, le.libelle, le.cout_total
                               FROM lignes_examen le
                               WHERE le.isDeleted=0 AND le.recu_id IN ($ph)");
        $stmt->execute($ids);
        foreach ($stmt as $l) $detailsParRecu[$l['recu_id']][] = ['lib'=>$l['libelle'], 'mt'=>$l['cout_total']];

        $stmt = $pdo->prepare("SELECT lp.recu_id, lp.nom, lp.quantite, lp.prix_unitaire, lp.total_ligne
                               FROM lignes_pharmacie lp
                               WHERE lp.isDeleted=0 AND lp.recu_id IN ($ph)");
        $stmt->execute($ids);
        foreach ($stmt as $l) $detailsParRecu[$l['recu_id']][] = ['lib'=>$l['nom'].' x'.$l['quantite'], 'mt'=>$l['total_ligne']];
    }

    // Récupération nom centre
    $nomCentre = $pdo->query("SELECT valeur FROM config_systeme WHERE cle='nom_centre' AND isDeleted=0 LIMIT 1")->fetchColumn() ?: 'CSI AMA MARADI';
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <title>Facture <?= h($reg['numero_reglement']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; color:#333; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 22px; }
        .info-box { background: #f5f5f5; padding: 10px; border-left: 4px solid #198754; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #198754; color: #fff; text-align: left; }
        .total-row { background: #fff3cd; font-weight: bold; font-size: 14px; }
        .text-right { text-align: right; }
        .signatures { margin-top: 50px; display: flex; justify-content: space-around; }
        .sig-box { text-align: center; width: 30%; }
        .sig-line { border-top: 1px solid #000; margin-top: 60px; padding-top: 4px; }
        @media print { .no-print { display: none; } body { padding: 10px; } }
        .btn { padding: 8px 16px; background: #198754; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration:none; }
    </style>
    </head>
    <body>

    <div class="no-print" style="margin-bottom:15px;">
        <button onclick="window.print()" class="btn">🖨 Imprimer</button>
        <a href="<?= url('index.php?page=reglements') ?>" style="margin-left:10px;">← Retour</a>
    </div>

    <div class="header">
        <h1><?= h($nomCentre) ?></h1>
        <div>Programme DirectAid AMA</div>
        <h2 style="margin-top:15px; color:#198754;">FACTURE DE RÈGLEMENT — ORPHELINS</h2>
    </div>

    <div class="info-box">
        <table style="border:none;">
            <tr><td style="border:none;"><strong>N° Règlement :</strong> <?= h($reg['numero_reglement']) ?></td>
                <td style="border:none;"><strong>Date :</strong> <?= date('d/m/Y', strtotime($reg['date_reglement'])) ?></td></tr>
            <tr><td style="border:none;"><strong>Mode :</strong> <?= h(strtoupper($reg['mode_paiement'])) ?></td>
                <td style="border:none;"><strong>Référence :</strong> <?= h($reg['reference_paiement'] ?? '—') ?></td></tr>
            <tr><td style="border:none;" colspan="2"><strong>Réglé par :</strong> <?= h(trim(($reg['regleur_nom']??'').' '.($reg['regleur_prenom']??''))) ?></td></tr>
        </table>
    </div>

    <h3>Détail des reçus pris en charge</h3>
    <table>
        <thead>
            <tr><th>N° Reçu</th><th>Date</th><th>Orphelin</th><th>Type</th><th>Détails</th><th class="text-right">Montant (FCFA)</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recus as $r): ?>
            <tr>
                <td><?= h($r['numero_recu']) ?></td>
                <td><?= date('d/m/Y', strtotime($r['whendone'])) ?></td>
                <td><?= h($r['pat_nom']) ?> (<?= $r['sexe'] ?>, <?= $r['age'] ?>a)</td>
                <td><?= h($r['type_recu']) ?></td>
                <td>
                    <?php foreach (($detailsParRecu[$r['id']] ?? []) as $d): ?>
                        • <?= h($d['lib']) ?><br>
                    <?php endforeach; ?>
                </td>
                <td class="text-right"><?= number_format((float)$r['montant_total'],0,',',' ') ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL RÈGLEMENT :</td>
                <td class="text-right"><?= number_format((float)$reg['montant_total'],0,',',' ') ?> FCFA</td>
            </tr>
        </tbody>
    </table>

    <?php if ($reg['observations']): ?>
    <div class="info-box"><strong>Observations :</strong> <?= nl2br(h($reg['observations'])) ?></div>
    <?php endif; ?>

    <div class="signatures">
        <div class="sig-box"><div class="sig-line">Le Comptable</div></div>
        <div class="sig-box"><div class="sig-line">Le Représentant DirectAid AMA</div></div>
        <div class="sig-box"><div class="sig-line">Le Directeur du CSI</div></div>
    </div>

    </body>
    </html>
    <?php
}


// =====================================================================
// PDF : LISTE DES REGLEMENTS ORPHELINS EN INSTANCE
// Pharmacie + Examens uniquement (consultations gratuites non incluses)
// =====================================================================
function imprimerInstancePdf(PDO $pdo) {

    // Nom du centre
    $nomCentre = $pdo->query("SELECT valeur FROM config_systeme WHERE cle='nom_centre' AND isDeleted=0 LIMIT 1")->fetchColumn() ?: 'CSI AMA MARADI';

    // Stats globaux (pharmacie + examens uniquement)
    $statsGlobal = $pdo->query("
        SELECT COUNT(DISTINCT id) AS nb_recus,
               COUNT(DISTINCT patient_id) AS nb_orphelins,
               COALESCE(SUM(montant_total), 0) AS grand_total
        FROM recus
        WHERE isDeleted = 0 AND type_patient = 'orphelin'
          AND statut_reglement = 'en_instance'
          AND type_recu IN ('examen', 'pharmacie')
    ")->fetch(PDO::FETCH_ASSOC) ?: ['nb_recus' => 0, 'nb_orphelins' => 0, 'grand_total' => 0];

    // Orphelins avec leurs recus pharmacie+examen en instance
    $orphelins = $pdo->query("
        SELECT p.id AS patient_id, p.nom, p.sexe, p.age, p.provenance,
               COUNT(r.id) AS nb_recus,
               COALESCE(SUM(r.montant_total), 0) AS total_du,
               MIN(r.whendone) AS premiere_visite,
               MAX(r.whendone) AS derniere_visite
        FROM patients p
        JOIN recus r ON r.patient_id = p.id AND r.isDeleted = 0
                    AND r.type_patient = 'orphelin'
                    AND r.statut_reglement = 'en_instance'
                    AND r.type_recu IN ('examen', 'pharmacie')
        WHERE p.isDeleted = 0
        GROUP BY p.id
        ORDER BY p.nom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Pour chaque orphelin, charger ses recus + lignes details
    $recusParOrphelin = [];
    $detailsParRecu   = [];
    if (!empty($orphelins)) {
        $patientIds = array_column($orphelins, 'patient_id');
        $phP = implode(',', array_fill(0, count($patientIds), '?'));

        $stmtR = $pdo->prepare("
            SELECT r.id, r.numero_recu, r.type_recu, r.montant_total, r.whendone, r.patient_id
            FROM recus r
            WHERE r.isDeleted = 0 AND r.type_patient = 'orphelin'
              AND r.statut_reglement = 'en_instance'
              AND r.type_recu IN ('examen', 'pharmacie')
              AND r.patient_id IN ($phP)
            ORDER BY r.patient_id, r.whendone ASC
        ");
        $stmtR->execute($patientIds);
        $allRecus = $stmtR->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allRecus as $r) {
            $recusParOrphelin[$r['patient_id']][] = $r;
        }

        if (!empty($allRecus)) {
            $recuIds = array_column($allRecus, 'id');
            $phR = implode(',', array_fill(0, count($recuIds), '?'));

            // Lignes examen
            $stmtE = $pdo->prepare("SELECT recu_id, libelle, cout_total FROM lignes_examen WHERE isDeleted=0 AND recu_id IN ($phR)");
            $stmtE->execute($recuIds);
            foreach ($stmtE as $l) {
                $detailsParRecu[$l['recu_id']][] = ['lib' => $l['libelle'], 'mt' => $l['cout_total']];
            }

            // Lignes pharmacie
            $stmtP = $pdo->prepare("SELECT recu_id, nom, quantite, total_ligne FROM lignes_pharmacie WHERE isDeleted=0 AND recu_id IN ($phR)");
            $stmtP->execute($recuIds);
            foreach ($stmtP as $l) {
                $detailsParRecu[$l['recu_id']][] = ['lib' => $l['nom'] . ' x' . $l['quantite'], 'mt' => $l['total_ligne']];
            }
        }
    }

    // Reference document + QR code (via api.qrserver.com)
    $docRef  = 'INST-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    $dateDoc = date('d/m/Y');
    $qrData  = 'Ref:' . $docRef . '|Date:' . $dateDoc . '|Orphelins:' . $statsGlobal['nb_orphelins'] . '|Total:' . number_format((float)$statsGlobal['grand_total'], 0, ',', ' ') . ' FCFA';
    $qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=' . urlencode($qrData);

    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <title>Reglements en instance &mdash; <?= h($docRef) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #222; padding: 18px 22px; }

        /* ===== EN-TETE ===== */
        .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px double #1a5276; padding-bottom: 10px; margin-bottom: 14px; }
        .doc-header-left { flex: 1; }
        .doc-header-left h1 { font-size: 17px; color: #1a5276; margin-bottom: 2px; }
        .doc-header-left .subtitle { font-size: 12px; color: #555; }
        .doc-header-left .doc-title { font-size: 14px; font-weight: bold; color: #922b21; margin-top: 6px; text-transform: uppercase; letter-spacing: .5px; }
        .doc-header-right { text-align: right; }
        .doc-header-right img { display: block; margin-left: auto; margin-bottom: 4px; }
        .doc-ref { font-size: 10px; color: #555; }

        /* ===== BANDEAU STATS ===== */
        .stats-band { display: flex; gap: 10px; margin-bottom: 14px; }
        .stat-box { flex: 1; border: 1px solid #d5d8dc; border-radius: 5px; padding: 8px 10px; text-align: center; background: #f8f9fa; }
        .stat-box .val { font-size: 18px; font-weight: bold; color: #1a5276; }
        .stat-box .lbl { font-size: 10px; color: #666; }
        .notice-box { background: #fef9e7; border-left: 4px solid #f39c12; padding: 7px 10px; margin-bottom: 14px; font-size: 10.5px; color: #6d4c0a; border-radius: 3px; }

        /* ===== ORPHELINS ===== */
        .orphelin-block { margin-bottom: 16px; page-break-inside: avoid; border: 1px solid #d0d3d4; border-radius: 5px; overflow: hidden; }
        .orphelin-header { display: flex; justify-content: space-between; align-items: center; background: #1a5276; color: #fff; padding: 6px 10px; }
        .orphelin-header .oname { font-weight: bold; font-size: 12px; }
        .orphelin-header .ometa { font-size: 10px; opacity: .85; }
        .orphelin-header .ototal { font-weight: bold; font-size: 13px; background: #d6eaf8; color: #1a5276; padding: 2px 8px; border-radius: 4px; }

        table.recus-table { width: 100%; border-collapse: collapse; }
        table.recus-table thead tr { background: #eaf2ff; }
        table.recus-table th { padding: 5px 7px; text-align: left; font-size: 10.5px; border-bottom: 1px solid #ccc; }
        table.recus-table td { padding: 4px 7px; border-bottom: 1px solid #e8e8e8; vertical-align: top; font-size: 10.5px; }
        table.recus-table tr:last-child td { border-bottom: none; }
        .badge-type { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 9.5px; font-weight: bold; }
        .badge-examen    { background: #d6eaf8; color: #1a5276; }
        .badge-pharmacie { background: #d5f5e3; color: #1e8449; }
        table.recus-table .text-right { text-align: right; }
        .subtotal-row td { background: #eaf2ff; font-weight: bold; text-align: right; border-top: 1px solid #aed6f1; }

        /* ===== GRAND TOTAL ===== */
        .grand-total-box { border: 2px solid #1a5276; border-radius: 5px; padding: 10px 16px; margin-top: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; background: #eaf2ff; page-break-inside: avoid; }
        .grand-total-box .gt-label { font-size: 13px; font-weight: bold; color: #1a5276; }
        .grand-total-box .gt-value { font-size: 20px; font-weight: bold; color: #922b21; }

        /* ===== SIGNATURES ===== */
        .signatures { display: flex; justify-content: space-around; margin-top: 40px; page-break-inside: avoid; }
        .sig-box { text-align: center; width: 28%; }
        .sig-line { border-top: 1px solid #555; margin-top: 55px; padding-top: 4px; font-size: 10.5px; }

        /* ===== PRINT ===== */
        .no-print { margin-bottom: 14px; display: flex; gap: 10px; }
        .btn-print { padding: 7px 16px; background: #1a5276; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-back  { padding: 7px 16px; background: #6c757d; color: #fff; border: none; border-radius: 4px; text-decoration: none; font-size: 13px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 8px 12px; }
            .orphelin-block { page-break-inside: avoid; }
        }
    </style>
    </head>
    <body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">&#128438; Imprimer / Enregistrer PDF</button>
        <a class="btn-back" href="<?= url('index.php?page=reglements') ?>">&#8592; Retour</a>
    </div>

    <!-- En-tete document -->
    <div class="doc-header">
        <div class="doc-header-left">
            <h1><?= h($nomCentre) ?></h1>
            <div class="subtitle">Programme DirectAid AMA &mdash; Maradi, Niger</div>
            <div class="doc-title">Situation des reglements orphelins en instance</div>
            <div class="doc-ref" style="margin-top:6px;">
                <strong>Ref. document :</strong> <?= h($docRef) ?> &nbsp;|&nbsp;
                <strong>Edite le :</strong> <?= $dateDoc ?> a <?= date('H:i') ?> &nbsp;|&nbsp;
                <strong>Objet :</strong> Pharmacie &amp; Examens (consultations gratuites non comprises)
            </div>
        </div>
        <div class="doc-header-right">
            <img src="<?= $qrUrl ?>" width="110" height="110" alt="QR Code" title="QR Code de verification">
            <div class="doc-ref" style="font-size:9px; text-align:center;"><?= h($docRef) ?></div>
        </div>
    </div>

    <!-- Bandeau stats -->
    <div class="stats-band">
        <div class="stat-box">
            <div class="val"><?= $statsGlobal['nb_orphelins'] ?></div>
            <div class="lbl">Orphelins en instance</div>
        </div>
        <div class="stat-box">
            <div class="val"><?= $statsGlobal['nb_recus'] ?></div>
            <div class="lbl">Recus (exam. + pharm.)</div>
        </div>
        <div class="stat-box" style="background:#fef5e7; border-color:#f39c12;">
            <div class="val" style="color:#922b21;"><?= number_format((float)$statsGlobal['grand_total'], 0, ',', ' ') ?></div>
            <div class="lbl">Total du (FCFA)</div>
        </div>
    </div>

    <!-- Note metier -->
    <div class="notice-box">
        <strong>Note :</strong> Conformement aux regles du programme DirectAid AMA, les consultations sont gratuites pour les orphelins et ne figurent pas dans ce document. Seules les depenses <strong>pharmacie</strong> et <strong>examens medicaux</strong> sont prises en charge.
    </div>

    <?php if (empty($orphelins)): ?>
    <div style="text-align:center; padding:40px; color:#555; border:1px dashed #bbb; border-radius:6px;">
        <strong>Aucune depense en instance</strong> &mdash; Toutes les situations sont a jour.
    </div>
    <?php else: ?>

    <?php $rowNum = 1; foreach ($orphelins as $o): ?>
    <div class="orphelin-block">
        <div class="orphelin-header">
            <div>
                <span class="oname"><?= $rowNum++ ?>. <?= h($o['nom']) ?></span>
                <span class="ometa">&nbsp;&mdash;&nbsp;<?= $o['sexe'] === 'F' ? 'Fille' : 'Garcon' ?>, <?= $o['age'] ?> ans<?= $o['provenance'] ? ' &mdash; ' . h($o['provenance']) : '' ?></span>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span class="ometa">
                    <?= $o['nb_recus'] ?> recu<?= (int)$o['nb_recus'] > 1 ? 's' : '' ?> &nbsp;|&nbsp;
                    <?= date('d/m/Y', strtotime($o['premiere_visite'])) ?>
                    <?= $o['premiere_visite'] !== $o['derniere_visite'] ? ' > ' . date('d/m/Y', strtotime($o['derniere_visite'])) : '' ?>
                </span>
                <span class="ototal"><?= number_format((float)$o['total_du'], 0, ',', ' ') ?> FCFA</span>
            </div>
        </div>
        <table class="recus-table">
            <thead>
                <tr>
                    <th style="width:130px;">N Recu</th>
                    <th style="width:85px;">Date</th>
                    <th style="width:80px;">Type</th>
                    <th>Details des prestations</th>
                    <th style="width:110px;" class="text-right">Montant (FCFA)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($recusParOrphelin[$o['patient_id']] ?? []) as $r):
                $typeLabel = $r['type_recu'] === 'examen' ? 'Examen' : 'Pharmacie';
                $typeCls   = $r['type_recu'] === 'examen' ? 'badge-examen' : 'badge-pharmacie';
                $lignes    = $detailsParRecu[$r['id']] ?? [];
            ?>
                <tr>
                    <td><strong><?= h($r['numero_recu']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($r['whendone'])) ?></td>
                    <td><span class="badge-type <?= $typeCls ?>"><?= $typeLabel ?></span></td>
                    <td>
                        <?php foreach ($lignes as $l): ?>
                            &bull; <?= h($l['lib']) ?><br>
                        <?php endforeach; ?>
                        <?php if (empty($lignes)): ?><em style="color:#999;">—</em><?php endif; ?>
                    </td>
                    <td class="text-right"><?= number_format((float)$r['montant_total'], 0, ',', ' ') ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="subtotal-row">
                    <td colspan="4">Sous-total <?= h($o['nom']) ?> :</td>
                    <td><?= number_format((float)$o['total_du'], 0, ',', ' ') ?> FCFA</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <!-- Grand total -->
    <div class="grand-total-box">
        <div class="gt-label">GRAND TOTAL A REGLER &mdash; <?= (int)$statsGlobal['nb_orphelins'] ?> orphelin<?= (int)$statsGlobal['nb_orphelins'] > 1 ? 's' : '' ?> (<?= (int)$statsGlobal['nb_recus'] ?> recus pharmacie &amp; examens)</div>
        <div class="gt-value"><?= number_format((float)$statsGlobal['grand_total'], 0, ',', ' ') ?> FCFA</div>
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div class="sig-box"><div class="sig-line">Le Comptable</div></div>
        <div class="sig-box"><div class="sig-line">Le Representant DirectAid AMA</div></div>
        <div class="sig-box"><div class="sig-line">Le Directeur du CSI</div></div>
    </div>

    <?php endif; ?>

    </body>
    </html>
    <?php
}


// =====================================================================
// LISTE PRINCIPALE
// =====================================================================
function afficherListe(PDO $pdo) {
    $filtreDebut = $_GET['filtre_debut'] ?? date('Y-m-01');
    $filtreFin   = $_GET['filtre_fin']   ?? date('Y-m-d');

    $statsInstance = $pdo->query("
        SELECT COUNT(DISTINCT r.id) AS nb_recus,
               COUNT(DISTINCT r.patient_id) AS nb_orphelins,
               COALESCE(SUM(r.montant_total),0) AS total_du
        FROM recus r
        WHERE r.isDeleted=0 AND r.type_patient='orphelin' AND r.statut_reglement='en_instance'
    ")->fetch(PDO::FETCH_ASSOC) ?: ['nb_recus'=>0,'nb_orphelins'=>0,'total_du'=>0];

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ro.id) AS nb_reglements,
               COALESCE(SUM(ro.montant_total),0) AS total_regle
        FROM reglements_orphelins ro
        WHERE ro.isDeleted=0 AND ro.date_reglement BETWEEN :d AND :f
    ");
    $stmt->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
    $statsRegle = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['nb_reglements'=>0,'total_regle'=>0];

    $stmt = $pdo->prepare("
        SELECT p.id AS patient_id, p.nom, p.sexe, p.age, p.provenance, p.telephone, p.est_orphelin,
               COUNT(r.id) AS nb_recus,
               COALESCE(SUM(r.montant_total),0) AS total_du,
               MIN(r.whendone) AS premiere_visite,
               MAX(r.whendone) AS derniere_visite
        FROM patients p
        JOIN recus r ON r.patient_id=p.id AND r.isDeleted=0
                    AND r.type_patient='orphelin' AND r.statut_reglement='en_instance'
        WHERE p.isDeleted=0
        GROUP BY p.id
        ORDER BY total_du DESC
    ");
    $stmt->execute();
    $orphelinsInstance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT ro.*, u.nom AS regleur_nom, u.prenom AS regleur_prenom
        FROM reglements_orphelins ro
        LEFT JOIN utilisateurs u ON u.id=ro.whodone
        WHERE ro.isDeleted=0 AND ro.date_reglement BETWEEN :d AND :f
        ORDER BY ro.date_reglement DESC, ro.id DESC
    ");
    $stmt->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
    $reglements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pageTitle = 'Règlements Orphelins';
    include ROOT_PATH . '/templates/layouts/header.php';
    ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-cash-stack text-success"></i> Règlements CSI DirectAid </h2>
                <p class="text-muted mb-0">Gestion des dépenses pour les orphelins</p>
            </div>
            <a href="<?= url('index.php?page=dashboard') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Tableau de bord
            </a>
        </div>

        <!-- KPI -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-warning shadow-sm">
                    <div class="card-body">
                        <div class="text-warning small fw-bold">EN INSTANCE</div>
                        <div class="h3 mb-0"><?= number_format((float)$statsInstance['total_du'],0,',',' ') ?> <small>FCFA</small></div>
                        <div class="text-muted small"><?= $statsInstance['nb_recus'] ?> reçus · <?= $statsInstance['nb_orphelins'] ?> orphelins</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success shadow-sm">
                    <div class="card-body">
                        <div class="text-success small fw-bold">RÉGLÉ (PÉRIODE)</div>
                        <div class="h3 mb-0"><?= number_format((float)$statsRegle['total_regle'],0,',',' ') ?> <small>FCFA</small></div>
                        <div class="text-muted small"><?= $statsRegle['nb_reglements'] ?> règlements</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="get" class="row g-2 align-items-end">
                            <input type="hidden" name="page" value="reglements">
                            <div class="col-md-5">
                                <label class="form-label small mb-1">Du</label>
                                <input type="date" name="filtre_debut" value="<?= h($filtreDebut) ?>" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-1">Au</label>
                                <input type="date" name="filtre_fin" value="<?= h($filtreFin) ?>" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglets -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-instance">
                    <i class="bi bi-hourglass-split text-warning"></i> En instance
                    <span class="badge bg-warning text-dark ms-1"><?= count($orphelinsInstance) ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-regle">
                    <i class="bi bi-check-circle text-success"></i> Réglés
                    <span class="badge bg-success ms-1"><?= count($reglements) ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- ========== EN INSTANCE ========== -->
            <div class="tab-pane fade show active" id="tab-instance">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (empty($orphelinsInstance)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle h1 text-success"></i>
                                <p>Aucune dépense en instance. Toutes les situations sont à jour.</p>
                            </div>
                        <?php else: ?>
                            <!-- Barre d'actions globales -->
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                                <div>
                                    <input type="checkbox" id="cbAllOrphelins" class="form-check-input me-2">
                                    <label for="cbAllOrphelins" class="fw-bold">Tout sélectionner</label>
                                    <span class="ms-3">Sélection : <span class="badge bg-primary" id="badgeNbOrph">0</span> orphelin(s) — <strong class="text-success"><span id="badgeMontantOrph">0</span> FCFA</strong></span>
                                </div>
                                <div>
                                    <button class="btn btn-success" id="btnReglerSelection" disabled>
                                        <i class="bi bi-cash-coin"></i> Régler la sélection
                                    </button>
                                    <button class="btn btn-warning" onclick="reglerTout()">
                                        <i class="bi bi-check2-all"></i> TOUT régler (<?= number_format((float)$statsInstance['total_du'],0,',',' ') ?> FCFA)
                                    </button>
                                    <a href="<?= url('index.php?page=reglements&action=pdf_instance') ?>" target="_blank" class="btn btn-outline-danger">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF en instance
                                    </a>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;"></th>
                                            <th>Orphelin</th>
                                            <th class="text-center">Sexe / Âge</th>
                                            <th>Provenance</th>
                                            <th class="text-center">Nb reçus</th>
                                            <th class="text-end">Montant dû</th>
                                            <th>Période</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orphelinsInstance as $o): 
                                            $nomEsc = htmlspecialchars($o['nom'], ENT_QUOTES);
                                        ?>
                                        <tr>
                                            <td><input type="checkbox" class="form-check-input cb-orph" 
                                                       data-patient-id="<?= $o['patient_id'] ?>"
                                                       data-nom="<?= $nomEsc ?>"
                                                       data-montant="<?= $o['total_du'] ?>"></td>
                                            <td>
                                                <strong><?= h($o['nom']) ?></strong>
                                                <?php if ($o['est_orphelin']): ?>
                                                    <span class="badge bg-warning text-dark ms-1" title="Orphelin DirectAid AMA">★ AMA</span>
                                                <?php endif; ?>
                                                <?php if ($o['telephone']): ?>
                                                    <br><small class="text-muted"><i class="bi bi-telephone"></i> <?= h($o['telephone']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $o['sexe']==='F'?'danger':'info' ?>"><?= $o['sexe'] ?></span>
                                                <?= $o['age'] ?> ans
                                            </td>
                                            <td><?= h($o['provenance'] ?? '—') ?></td>
                                            <td class="text-center"><span class="badge bg-secondary"><?= $o['nb_recus'] ?></span></td>
                                            <td class="text-end"><strong class="text-danger"><?= number_format((float)$o['total_du'],0,',',' ') ?></strong></td>
                                            <td>
                                                <small><?= date('d/m/Y', strtotime($o['premiere_visite'])) ?>
                                                → <?= date('d/m/Y', strtotime($o['derniere_visite'])) ?></small>
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <button class="btn btn-sm btn-outline-primary"
                                                        onclick="ouvrirDetailsOrphelin(<?= $o['patient_id'] ?>, '<?= $nomEsc ?>')">
                                                    <i class="bi bi-eye"></i> Détails
                                                </button>
                                                <button class="btn btn-sm btn-success"
                                                        onclick="reglerOrphelin(<?= $o['patient_id'] ?>, '<?= $nomEsc ?>', <?= $o['total_du'] ?>)">
                                                    <i class="bi bi-cash-coin"></i> Régler
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-warning">
                                        <tr>
                                            <th colspan="5" class="text-end">TOTAL EN INSTANCE :</th>
                                            <th class="text-end h5"><?= number_format((float)$statsInstance['total_du'],0,',',' ') ?> FCFA</th>
                                            <th colspan="2"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ========== RÉGLÉS ========== -->
            <div class="tab-pane fade" id="tab-regle">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (empty($reglements)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox h1"></i>
                                <p>Aucun règlement sur cette période.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>N° Règlement</th><th>Date</th>
                                            <th class="text-center">Nb reçus</th><th class="text-end">Montant</th>
                                            <th>Mode</th><th>Référence</th><th>Réglé par</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reglements as $r): ?>
                                        <tr>
                                            <td><strong><?= h($r['numero_reglement']) ?></strong></td>
                                            <td><?= date('d/m/Y', strtotime($r['date_reglement'])) ?></td>
                                            <td class="text-center"><span class="badge bg-info"><?= $r['nb_recus'] ?></span></td>
                                            <td class="text-end"><strong class="text-success"><?= number_format((float)$r['montant_total'],0,',',' ') ?></strong></td>
                                            <td><span class="badge bg-secondary"><?= h(strtoupper($r['mode_paiement'])) ?></span></td>
                                            <td><small><?= h($r['reference_paiement'] ?? '—') ?></small></td>
                                            <td><small><?= h(trim(($r['regleur_nom']??'').' '.($r['regleur_prenom']??''))) ?: '—' ?></small></td>
                                            <td class="text-center">
                                                <a href="<?= url('index.php?page=reglements&action=facture&id='.$r['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-printer"></i> Facture
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-success">
                                        <tr>
                                            <th colspan="3" class="text-end">TOTAL RÉGLÉ :</th>
                                            <th class="text-end h5"><?= number_format((float)$statsRegle['total_regle'],0,',',' ') ?> FCFA</th>
                                            <th colspan="4"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== MODAL DÉTAILS ========== -->
    <div class="modal fade" id="modalDetails" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-list-check"></i> Détails — <span id="detailsOrphelinNom"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsBody">
                    <div class="text-center py-4"><div class="spinner-border"></div></div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto">
                        <strong>Sélection : </strong>
                        <span class="badge bg-primary" id="badgeNbSel">0</span> reçus —
                        <strong class="text-success"><span id="badgeMontantSel">0</span> FCFA</strong>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-success" id="btnReglerSelectionRecus" disabled>
                        <i class="bi bi-cash-coin"></i> Régler la sélection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== MODAL RÈGLEMENT ========== -->
    <div class="modal fade" id="modalReglement" tabindex="-1">
        <div class="modal-dialog">
            <form method="post" action="<?= url('index.php?page=reglements&action=enregistrer') ?>" class="modal-content">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-cash-coin"></i> Enregistrer un règlement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="recus_ids" id="reglementRecusIds">
                    <div class="mb-3">
                        <label class="form-label">Bénéficiaire(s)</label>
                        <input type="text" id="reglementBeneficiaire" class="form-control" readonly>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date du règlement *</label>
                            <input type="date" name="date_reglement" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Montant (FCFA) *</label>
                            <input type="number" name="montant_total" id="reglementMontant" class="form-control" required min="0" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mode de paiement *</label>
                            <select name="mode_paiement" class="form-select" required>
                                <option value="especes">Espèces</option>
                                <option value="cheque">Chèque</option>
                                <option value="virement">Virement</option>
                                <option value="mobile_money">Mobile Money</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Référence (n° chèque, transaction…)</label>
                            <input type="text" name="reference_paiement" class="form-control" maxlength="100">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observations</label>
                            <textarea name="observations" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0 small">
                        <i class="bi bi-info-circle"></i> Le montant proposé correspond au total dû.
                        Vous pouvez l'ajuster en cas de paiement partiel (le reste restera en instance).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Valider</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const REGL_AJAX_DETAILS = APP_BASE_URL + 'index.php?page=reglements&action=ajax_details';
    const REGL_AJAX_IDS     = APP_BASE_URL + 'index.php?page=reglements&action=ajax_ids';

    // ========== Détails par orphelin (popup avec checkboxes des reçus) ==========
    function ouvrirDetailsOrphelin(patientId, nom) {
        document.getElementById('detailsOrphelinNom').textContent = nom;
        document.getElementById('detailsBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';
        const btn = document.getElementById('btnReglerSelectionRecus');
        btn.disabled = true;
        btn.dataset.nom = nom;

        new bootstrap.Modal(document.getElementById('modalDetails')).show();

        fetch(REGL_AJAX_DETAILS + '&patient_id=' + patientId)
            .then(r => r.text())
            .then(html => {
                document.getElementById('detailsBody').innerHTML = html;
                attachCheckboxListeners();
            })
            .catch(err => {
                document.getElementById('detailsBody').innerHTML = '<div class="alert alert-danger">Erreur de chargement : ' + err + '</div>';
            });
    }

    function attachCheckboxListeners() {
        const cbAll = document.getElementById('cbAll');
        const cbs = document.querySelectorAll('.cb-recu');
        if (cbAll) cbAll.addEventListener('change', e => {
            cbs.forEach(cb => cb.checked = e.target.checked);
            majSelectionRecus();
        });
        cbs.forEach(cb => cb.addEventListener('change', majSelectionRecus));
        majSelectionRecus();
    }

    function majSelectionRecus() {
        const selected = document.querySelectorAll('.cb-recu:checked');
        let total = 0;
        selected.forEach(cb => total += parseFloat(cb.dataset.montant) || 0);
        document.getElementById('badgeNbSel').textContent = selected.length;
        document.getElementById('badgeMontantSel').textContent = total.toLocaleString('fr-FR');
        const btn = document.getElementById('btnReglerSelectionRecus');
        btn.disabled = selected.length === 0;
        btn.dataset.montant = total;
        btn.dataset.ids = Array.from(selected).map(cb => cb.value).join(',');
    }

    document.getElementById('btnReglerSelectionRecus').addEventListener('click', function() {
        const nom = this.dataset.nom;
        const montant = this.dataset.montant;
        const ids = this.dataset.ids;
        bootstrap.Modal.getInstance(document.getElementById('modalDetails')).hide();
        setTimeout(() => ouvrirModalReglement(nom, montant, ids), 300);
    });

    // ========== Régler tout un orphelin (raccourci) ==========
    function reglerOrphelin(patientId, nom, total) {
        fetch(REGL_AJAX_IDS + '&patient_id=' + patientId)
            .then(r => r.json())
            .then(data => {
                if (!data.ids || data.ids.length === 0) {
                    alert('Aucun reçu en instance pour cet orphelin.');
                    return;
                }
                ouvrirModalReglement(nom, total, data.ids.join(','));
            })
            .catch(err => alert('Erreur : ' + err));
    }

    // ========== Régler TOUS les orphelins en instance ==========
    function reglerTout() {
        if (!confirm('Voulez-vous vraiment régler la TOTALITÉ des dépenses en instance pour tous les orphelins ?')) return;
        fetch(REGL_AJAX_IDS)
            .then(r => r.json())
            .then(data => {
                if (!data.ids || data.ids.length === 0) {
                    alert('Aucun reçu en instance.');
                    return;
                }
                ouvrirModalReglement('Règlement global ('+data.nb+' reçus)', data.total, data.ids.join(','));
            })
            .catch(err => alert('Erreur : ' + err));
    }

    // ========== Sélection multiple sur la liste des orphelins ==========
    function majSelectionOrphelins() {
        const sel = document.querySelectorAll('.cb-orph:checked');
        let total = 0;
        sel.forEach(cb => total += parseFloat(cb.dataset.montant) || 0);
        document.getElementById('badgeNbOrph').textContent = sel.length;
        document.getElementById('badgeMontantOrph').textContent = total.toLocaleString('fr-FR');
        document.getElementById('btnReglerSelection').disabled = sel.length === 0;
    }

    document.querySelectorAll('.cb-orph').forEach(cb => cb.addEventListener('change', majSelectionOrphelins));
    const cbAllOrph = document.getElementById('cbAllOrphelins');
    if (cbAllOrph) cbAllOrph.addEventListener('change', e => {
        document.querySelectorAll('.cb-orph').forEach(cb => cb.checked = e.target.checked);
        majSelectionOrphelins();
    });

    document.getElementById('btnReglerSelection')?.addEventListener('click', function() {
        const sel = document.querySelectorAll('.cb-orph:checked');
        if (sel.length === 0) return;
        const patientIds = Array.from(sel).map(cb => cb.dataset.patientId);
        const noms = Array.from(sel).map(cb => cb.dataset.nom);

        // Récupérer tous les IDs de reçus pour ces orphelins
        Promise.all(patientIds.map(pid =>
            fetch(REGL_AJAX_IDS + '&patient_id=' + pid).then(r => r.json())
        )).then(results => {
            const allIds = results.flatMap(r => r.ids);
            const total = results.reduce((s, r) => s + parseFloat(r.total || 0), 0);
            const libelle = noms.length > 1 ? noms.length + ' orphelins (' + noms.slice(0,3).join(', ') + (noms.length>3?'…':'') + ')' : noms[0];
            ouvrirModalReglement(libelle, total, allIds.join(','));
        });
    });

    // ========== Ouverture du modal de règlement ==========
    function ouvrirModalReglement(beneficiaire, montant, ids) {
        if (!ids || ids.length === 0) { alert('Aucun reçu sélectionné.'); return; }
        document.getElementById('reglementBeneficiaire').value = beneficiaire;
        document.getElementById('reglementMontant').value = Math.round(montant);
        document.getElementById('reglementRecusIds').value = ids;
        new bootstrap.Modal(document.getElementById('modalReglement')).show();
    }
    </script>

    <?php include ROOT_PATH . '/templates/layouts/footer.php'; ?>
    <?php
}
