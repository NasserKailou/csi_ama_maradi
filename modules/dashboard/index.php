<?php
/**
 * Tableau de Bord – Réservé Administrateur
 * Vue synthétique enrichie : KPIs, comparaisons, top du jour, alertes critiques.
 */
requireRole('admin', 'comptable');

$pdo       = Database::getInstance();
$pageTitle = 'Tableau de Bord';

// ── KPIs principaux ─────────────────────────────────────────────────────────
$patientsJour  = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND DATE(whendone)=CURDATE()")->fetchColumn();
$patientsLast7 = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND whendone >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)")->fetchColumn();
$patientsMois  = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND MONTH(whendone)=MONTH(CURDATE()) AND YEAR(whendone)=YEAR(CURDATE())")->fetchColumn();

// Recettes
$recettesJour  = (int)$pdo->query("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone)=CURDATE()")->fetchColumn();
$recettesHier  = (int)$pdo->query("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetchColumn();
$recettesMois  = (int)$pdo->query("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND MONTH(whendone)=MONTH(CURDATE()) AND YEAR(whendone)=YEAR(CURDATE())")->fetchColumn();
$recettesMoisPrec = (int)$pdo->query("
    SELECT COALESCE(SUM(montant_encaisse),0) FROM recus
    WHERE isDeleted=0
      AND MONTH(whendone)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
      AND YEAR(whendone)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
")->fetchColumn();

// Variation jour vs hier (%)
function _var($a,$p){ if($p==0) return $a>0?100:0; return round((($a-$p)/$p)*100,1); }
$varJour = _var($recettesJour, $recettesHier);
$varMois = _var($recettesMois, $recettesMoisPrec);

// Coût subventionné (orphelin + acte gratuit) – jour & mois
$coutGratuitJour = (int)$pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM recus WHERE isDeleted=0 AND type_patient IN ('orphelin','acte_gratuit') AND DATE(whendone)=CURDATE()")->fetchColumn();
$coutGratuitMois = (int)$pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM recus WHERE isDeleted=0 AND type_patient IN ('orphelin','acte_gratuit') AND MONTH(whendone)=MONTH(CURDATE()) AND YEAR(whendone)=YEAR(CURDATE())")->fetchColumn();

// Reçus du jour (volume)
$nbRecusJour     = (int)$pdo->query("SELECT COUNT(*) FROM recus WHERE isDeleted=0 AND DATE(whendone)=CURDATE()")->fetchColumn();
$nbModifsJour    = (int)$pdo->query("SELECT COUNT(*) FROM modifications_recus WHERE DATE(whendone)=CURDATE()")->fetchColumn();

// Filtre période (GET)
$filtreDebut = $_GET['filtre_debut'] ?? date('Y-m-01');
$filtreFin   = $_GET['filtre_fin']   ?? date('Y-m-d');
$stmtPeriode = $pdo->prepare("
    SELECT COALESCE(SUM(montant_encaisse),0)         AS encaisse,
           COALESCE(SUM(montant_total),0)            AS theorique,
           COUNT(*)                                  AS nb_recus,
           COUNT(DISTINCT patient_id)                AS nb_patients
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
");
$stmtPeriode->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$periode = $stmtPeriode->fetch();

// ── Graphique 1 : Évolution 7 jours – Consultations + Recettes ──────────────
$evolution = $pdo->query("
    SELECT DATE(whendone) AS jour,
           COUNT(*) AS nb_recus,
           COALESCE(SUM(montant_encaisse),0) AS recettes
    FROM recus
    WHERE isDeleted=0 AND whendone >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(whendone)
    ORDER BY jour ASC
")->fetchAll();
$labelsEvo  = array_map(fn($r) => date('d/m', strtotime($r['jour'])), $evolution);
$dataEvo    = array_column($evolution, 'nb_recus');
$dataEvoRec = array_column($evolution, 'recettes');

// ── Graphique 2 : Répartition revenus par pôle (mois en cours) ──────────────
$repartition = $pdo->query("
    SELECT type_recu,
           COUNT(*) AS nb,
           COALESCE(SUM(montant_encaisse),0) AS total
    FROM recus
    WHERE isDeleted=0 AND MONTH(whendone)=MONTH(CURDATE()) AND YEAR(whendone)=YEAR(CURDATE())
    GROUP BY type_recu
")->fetchAll();
$mapPole = ['consultation'=>'Consultations','examen'=>'Examens','pharmacie'=>'Pharmacie'];
$repLabels = []; $repData = [];
foreach ($repartition as $r) {
    $repLabels[] = $mapPole[$r['type_recu']] ?? ucfirst($r['type_recu']);
    $repData[]   = (int)$r['total'];
}

// ── Top 5 actes du jour ─────────────────────────────────────────────────────
$topActesJour = $pdo->query("
    SELECT a.libelle, COUNT(lc.id) AS nb,
           SUM(CASE WHEN lc.est_gratuit=1 THEN 0 ELSE (lc.tarif + lc.tarif_carnet) END) AS revenu
    FROM lignes_consultation lc
    JOIN actes_medicaux a ON a.id = lc.acte_id
    JOIN recus r ON r.id = lc.recu_id AND r.isDeleted=0
    WHERE lc.isDeleted=0 AND DATE(r.whendone)=CURDATE()
    GROUP BY a.id
    ORDER BY nb DESC
    LIMIT 5
")->fetchAll();

// ── Top 5 produits pharmacie du jour ────────────────────────────────────────
$topProduitsJour = $pdo->query("
    SELECT lp.nom, lp.forme, SUM(lp.quantite) AS qte, SUM(lp.total_ligne) AS revenu
    FROM lignes_pharmacie lp
    JOIN recus r ON r.id = lp.recu_id AND r.isDeleted=0
    WHERE lp.isDeleted=0 AND DATE(r.whendone)=CURDATE()
    GROUP BY lp.nom, lp.forme
    ORDER BY qte DESC
    LIMIT 5
")->fetchAll();

// ── Alertes stock (regroupées par sévérité) ─────────────────────────────────
$alertesStock = $pdo->query("
    SELECT nom, forme, stock_actuel, seuil_alerte, prix_unitaire, date_peremption,
           CASE
               WHEN stock_actuel = 0 THEN 'rupture'
               WHEN date_peremption IS NOT NULL AND date_peremption <= CURDATE() THEN 'perime'
               WHEN date_peremption IS NOT NULL AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 'peremption_proche'
               ELSE 'alerte'
           END AS type_alerte
    FROM produits_pharmacie
    WHERE isDeleted=0
      AND (
            stock_actuel <= seuil_alerte
            OR (date_peremption IS NOT NULL AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 60 DAY))
          )
    ORDER BY 
        CASE 
            WHEN stock_actuel = 0 THEN 1
            WHEN date_peremption IS NOT NULL AND date_peremption <= CURDATE() THEN 2
            WHEN date_peremption IS NOT NULL AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 3
            ELSE 4
        END,
        stock_actuel ASC
    LIMIT 15
")->fetchAll();

$nbRupture = $nbPerime = $nbPeremptionProche = $nbFaible = 0;
foreach ($alertesStock as $a) {
    if      ($a['type_alerte']==='rupture')           $nbRupture++;
    elseif  ($a['type_alerte']==='perime')            $nbPerime++;
    elseif  ($a['type_alerte']==='peremption_proche') $nbPeremptionProche++;
    else                                              $nbFaible++;
}

// ── Activité percepteurs du jour (avec détail par pôle) ─────────────────────
$activitePercep = $pdo->query("
    SELECT u.id AS user_id, u.nom, u.prenom, u.login,
           COUNT(r.id) AS nb_recus,
           COALESCE(SUM(r.montant_encaisse),0) AS total_encaisse,
           SUM(CASE WHEN r.type_recu='consultation' THEN 1 ELSE 0 END) AS nb_cons,
           SUM(CASE WHEN r.type_recu='examen'       THEN 1 ELSE 0 END) AS nb_exam,
           SUM(CASE WHEN r.type_recu='pharmacie'    THEN 1 ELSE 0 END) AS nb_pharma,
           SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END) AS nb_gratuits
    FROM utilisateurs u
    LEFT JOIN recus r ON r.whodone = u.id AND r.isDeleted=0 AND DATE(r.whendone)=CURDATE()
    WHERE u.role='percepteur' AND u.isDeleted=0
    GROUP BY u.id
    ORDER BY total_encaisse DESC
")->fetchAll();

// ── Derniers reçus émis (5 plus récents) ────────────────────────────────────
$derniersRecus = $pdo->query("
    SELECT r.numero_recu, r.type_recu, r.type_patient, r.montant_encaisse, r.whendone,
           p.nom AS nom_patient, u.nom AS nom_user, u.prenom AS prenom_user
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    LEFT JOIN utilisateurs u ON u.id = r.whodone
    WHERE r.isDeleted=0
    ORDER BY r.whendone DESC
    LIMIT 5
")->fetchAll();

include ROOT_PATH . '/templates/layouts/header.php';
?>

<div class="mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <div class="bg-csi rounded-circle d-flex align-items-center justify-content-center me-3"
                 style="width:50px;height:50px;">
                <i class="bi bi-speedometer2 text-white fs-4"></i>
            </div>
            <div>
                <h4 class="mb-0 text-csi fw-bold">Tableau de Bord – Administration</h4>
                <small class="text-muted">
                    Mis à jour le <?= date('d/m/Y à H:i') ?>
                    · <?= date('l d F Y') ?>
                </small>
            </div>
        </div>
        <a href="<?= url('index.php?page=analytics') ?>" class="btn btn-sm text-white" style="background:#1565c0;">
            <i class="bi bi-graph-up-arrow me-1"></i>Analytique avancée
        </a>
    </div>

    <!-- ⚠ Bandeau alertes critiques (compact) -->
    <?php if ($nbRupture>0 || $nbPerime>0 || $nbPeremptionProche>0): ?>
    <div class="alert alert-danger d-flex align-items-center py-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
        <div class="flex-grow-1 small">
            <strong>Action requise :</strong>
            <?php if ($nbRupture>0): ?>
                <span class="badge bg-danger ms-1"><?= $nbRupture ?> rupture<?= $nbRupture>1?'s':'' ?></span>
            <?php endif; ?>
            <?php if ($nbPerime>0): ?>
                <span class="badge ms-1" style="background:#880e4f;"><?= $nbPerime ?> périmé<?= $nbPerime>1?'s':'' ?></span>
            <?php endif; ?>
            <?php if ($nbPeremptionProche>0): ?>
                <span class="badge ms-1" style="background:#e65100;"><?= $nbPeremptionProche ?> péremption ≤ 60j</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── KPI Cards principaux (avec mini-tendance) ─────────────────────── -->
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="bi bi-people"></i></div>
                    <div class="flex-grow-1">
                        <div class="kpi-value"><?= $patientsJour ?></div>
                        <div class="kpi-label">Patients aujourd'hui</div>
                        <small class="text-muted">7j: <strong><?= $patientsLast7 ?></strong> · Mois: <strong><?= $patientsMois ?></strong></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card h-100" style="border-color:#1565c0;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#e3f2fd;color:#1565c0;">
                        <i class="bi bi-currency-exchange"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="kpi-value" style="color:#1565c0;"><?= number_format($recettesJour,0,',',' ') ?> F</div>
                        <div class="kpi-label">Recettes du jour</div>
                        <?php $vc = $varJour>0?'#2e7d32':($varJour<0?'#d32f2f':'#757575'); $vi = $varJour>0?'arrow-up-short':($varJour<0?'arrow-down-short':'dash'); ?>
                        <small style="color:<?= $vc ?>;font-weight:600;">
                            <i class="bi bi-<?= $vi ?>"></i><?= abs($varJour) ?>%
                            <span class="text-muted fw-normal">vs hier</span>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card h-100" style="border-color:#e65100;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#fbe9e7;color:#e65100;">
                        <i class="bi bi-heart"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="kpi-value" style="color:#e65100;"><?= number_format($coutGratuitJour,0,',',' ') ?> F</div>
                        <div class="kpi-label">Subventionné aujourd'hui</div>
                        <small class="text-muted">Mois : <strong><?= number_format($coutGratuitMois,0,',',' ') ?> F</strong></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card h-100" style="border-color:#d32f2f;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#ffebee;color:#d32f2f;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="kpi-value" style="color:#d32f2f;"><?= count($alertesStock) ?></div>
                        <div class="kpi-label">Alertes stock</div>
                        <small class="text-muted">
                            <?= $nbRupture ?> rupt. · <?= $nbPerime + $nbPeremptionProche ?> pér. · <?= $nbFaible ?> faib.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── KPI secondaires : volumes + cumul mensuel ─────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #00695c !important;">
                <div class="card-body py-2">
                    <small class="text-muted text-uppercase">Reçus émis aujourd'hui</small>
                    <div class="fw-bold fs-5" style="color:#00695c;"><?= $nbRecusJour ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6a1b9a !important;">
                <div class="card-body py-2">
                    <small class="text-muted text-uppercase">Recettes du mois</small>
                    <div class="fw-bold fs-5" style="color:#6a1b9a;"><?= number_format($recettesMois,0,',',' ') ?> F</div>
                    <?php $vcM = $varMois>0?'#2e7d32':($varMois<0?'#d32f2f':'#757575'); $viM = $varMois>0?'arrow-up-short':($varMois<0?'arrow-down-short':'dash'); ?>
                    <small style="color:<?= $vcM ?>;font-weight:600;">
                        <i class="bi bi-<?= $viM ?>"></i><?= abs($varMois) ?>%
                        <span class="text-muted fw-normal">vs mois préc.</span>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #f57f17 !important;">
                <div class="card-body py-2">
                    <small class="text-muted text-uppercase">Modifications du jour</small>
                    <div class="fw-bold fs-5" style="color:#f57f17;"><?= $nbModifsJour ?></div>
                    <small class="text-muted">audit qualité</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #1976d2 !important;">
                <div class="card-body py-2">
                    <small class="text-muted text-uppercase">Recettes d'hier</small>
                    <div class="fw-bold fs-5" style="color:#1976d2;"><?= number_format($recettesHier,0,',',' ') ?> F</div>
                    <small class="text-muted">référence J-1</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filtre période ────────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" action="<?= url('index.php') ?>" class="row g-2 align-items-end flex-wrap">
                <input type="hidden" name="page" value="dashboard">
                <div class="col-auto"><label class="form-label mb-0 fw-semibold">Synthèse sur période :</label></div>
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" name="filtre_debut" value="<?= h($filtreDebut) ?>">
                </div>
                <div class="col-auto"><span class="text-muted">→</span></div>
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" name="filtre_fin" value="<?= h($filtreFin) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--csi-green);">
                        <i class="bi bi-filter"></i> Filtrer
                    </button>
                </div>
                <div class="col-auto d-flex gap-2 flex-wrap">
                    <span class="badge bg-success px-3 py-2">
                        <i class="bi bi-cash"></i> <?= number_format($periode['encaisse'],0,',',' ') ?> F encaissés
                    </span>
                    <span class="badge bg-info text-dark px-3 py-2">
                        <i class="bi bi-receipt"></i> <?= (int)$periode['nb_recus'] ?> reçus
                    </span>
                    <span class="badge bg-primary px-3 py-2">
                        <i class="bi bi-people"></i> <?= (int)$periode['nb_patients'] ?> patients
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Graphiques ────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Évolution – 7 derniers jours (Reçus &amp; Recettes)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartEvolution" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Revenus par pôle – Mois en cours</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartRepartition" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Top du jour : actes & produits ────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-stethoscope me-2"></i>Top 5 Actes du jour</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Acte</th><th class="text-center">Nb</th><th class="text-end">Revenu</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($topActesJour): foreach ($topActesJour as $a): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($a['libelle']) ?></td>
                                <td class="text-center"><span class="badge bg-success"><?= $a['nb'] ?></span></td>
                                <td class="text-end fw-bold" style="color:#2e7d32;">
                                    <?= number_format($a['revenu'],0,',',' ') ?> F
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Aucun acte enregistré aujourd'hui</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0" style="color:#6a1b9a;"><i class="bi bi-capsule me-2"></i>Top 5 Produits Pharmacie du jour</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Produit</th><th>Forme</th><th class="text-center">Qté</th><th class="text-end">Revenu</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($topProduitsJour): foreach ($topProduitsJour as $p): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($p['nom']) ?></td>
                                <td><small class="text-muted"><?= h($p['forme']) ?></small></td>
                                <td class="text-center">
                                    <span class="badge" style="background:#6a1b9a;"><?= $p['qte'] ?></span>
                                </td>
                                <td class="text-end fw-bold" style="color:#2e7d32;">
                                    <?= number_format($p['revenu'],0,',',' ') ?> F
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Aucun produit vendu aujourd'hui</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Alertes Stock & Activité Percepteurs ───────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center"
                     style="background:#ffebee;border-bottom:2px solid #d32f2f;">
                    <h6 class="mb-0 text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Alertes Stock (<?= count($alertesStock) ?>)
                    </h6>
                    <a href="<?= url('index.php?page=analytics') ?>#section-stock" class="btn btn-sm btn-outline-danger">
                        Tout voir
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($alertesStock): ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Produit</th><th>Forme</th><th class="text-center">Stock</th><th class="text-center">Seuil</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($alertesStock as $a): ?>
                            <tr>
                                <td><strong><?= h($a['nom']) ?></strong></td>
                                <td><small class="text-muted"><?= h($a['forme']) ?></small></td>
                                <td class="text-center fw-bold <?= $a['stock_actuel']==0?'text-danger':'' ?>">
                                    <?= $a['stock_actuel'] ?>
                                </td>
                                <td class="text-center text-muted"><?= $a['seuil_alerte'] ?></td>
                                <td>
                                    <?php switch($a['type_alerte']):
                                        case 'rupture': ?>
                                            <span class="badge bg-danger">Rupture</span>
                                            <?php break; ?>
                                        <?php case 'perime': ?>
                                            <span class="badge" style="background:#880e4f;color:#fff;">
                                                Périmé (<?= date('d/m/Y',strtotime($a['date_peremption'])) ?>)
                                            </span>
                                            <?php break; ?>
                                        <?php case 'peremption_proche':
                                            $jrs = ceil((strtotime($a['date_peremption']) - time())/86400); ?>
                                            <span class="badge" style="background:#e65100;color:#fff;">
                                                Péremption ≤ <?= $jrs ?>j
                                            </span>
                                            <?php break; ?>
                                        <?php default: ?>
                                            <span class="badge bg-warning text-dark">Stock faible</span>
                                    <?php endswitch; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="p-3 text-success">
                        <i class="bi bi-check-circle me-2"></i>Aucune alerte de stock.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Activité Percepteurs – Aujourd'hui</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Percepteur</th>
                                <th class="text-center">Reçus</th>
                                <th class="text-end">Encaissé</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $maxEnc = max(1, ...array_column($activitePercep, 'total_encaisse'));
                        foreach ($activitePercep as $idx => $p):
                            $pct = round(($p['total_encaisse'] / $maxEnc) * 100);
                            $medal = $idx===0 && $p['total_encaisse']>0 ? '🥇' :
                                     ($idx===1 && $p['total_encaisse']>0 ? '🥈' :
                                     ($idx===2 && $p['total_encaisse']>0 ? '🥉' : ''));
                        ?>
                            <tr>
                                <td>
                                    <span class="me-1"><?= $medal ?></span>
                                    <i class="bi bi-person-circle text-csi me-1"></i>
                                    <strong><?= h($p['nom'].' '.$p['prenom']) ?></strong>
                                    <div class="d-flex gap-1 mt-1 flex-wrap">
                                        <?php if ($p['nb_cons']>0): ?>
                                            <span class="badge bg-light text-dark border" title="Consultations">C: <?= $p['nb_cons'] ?></span>
                                        <?php endif; ?>
                                        <?php if ($p['nb_exam']>0): ?>
                                            <span class="badge bg-light text-dark border" title="Examens">E: <?= $p['nb_exam'] ?></span>
                                        <?php endif; ?>
                                        <?php if ($p['nb_pharma']>0): ?>
                                            <span class="badge bg-light text-dark border" title="Pharmacie">P: <?= $p['nb_pharma'] ?></span>
                                        <?php endif; ?>
                                        <?php if ($p['nb_gratuits']>0): ?>
                                            <span class="badge" style="background:#7b1fa2;font-size:0.65em;" title="Gratuits/Orphelins">
                                                G: <?= $p['nb_gratuits'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $p['nb_recus'] > 0 ? 'success' : 'secondary' ?>">
                                        <?= $p['nb_recus'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php if ($p['nb_recus']>0): ?>
                                        <?= number_format($p['total_encaisse'],0,',',' ') ?> F
                                        <div class="progress mt-1" style="height:4px;">
                                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">0 F</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= url('modules/pdf/situation_percepteur.php') ?>?percepteur_id=<?= $p['user_id'] ?>&mode=jour"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-success me-1"
                                       title="Situation journalière – PDF">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Situation sur période"
                                            onclick="ouvrirModalPeriode(<?= $p['user_id'] ?>, '<?= h(addslashes($p['nom'].' '.$p['prenom'])) ?>')">
                                        <i class="bi bi-calendar-range"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Derniers reçus émis (flux temps réel) ──────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header bg-csi-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Derniers reçus émis</h6>
            <small class="text-muted">5 plus récents</small>
        </div>
        <div class="card-body p-0">
            <?php if ($derniersRecus): ?>
            <table class="table table-hover align-middle mb-0 table-sm">
                <thead class="table-light">
                    <tr>
                        <th>N°</th><th>Patient</th><th>Type</th><th>Statut</th>
                        <th class="text-end">Encaissé</th><th>Émis par</th><th>Heure</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $colorPole = ['consultation'=>'#2e7d32','examen'=>'#e65100','pharmacie'=>'#006064'];
                $iconPole  = ['consultation'=>'stethoscope','examen'=>'clipboard2-pulse','pharmacie'=>'capsule'];
                $colorTyp  = ['normal'=>'bg-success','orphelin'=>'background:#7b1fa2;color:#fff;','acte_gratuit'=>'background:#e65100;color:#fff;'];
                $labelTyp  = ['normal'=>'Payant','orphelin'=>'Orphelin','acte_gratuit'=>'Gratuit'];
                foreach ($derniersRecus as $rec):
                    $cP = $colorPole[$rec['type_recu']] ?? '#37474f';
                    $iP = $iconPole[$rec['type_recu']] ?? 'receipt';
                ?>
                    <tr>
                        <td><strong>#<?= $rec['numero_recu'] ?></strong></td>
                        <td><?= h($rec['nom_patient']) ?></td>
                        <td>
                            <i class="bi bi-<?= $iP ?> me-1" style="color:<?= $cP ?>"></i>
                            <small><?= h($mapPole[$rec['type_recu']] ?? $rec['type_recu']) ?></small>
                        </td>
                        <td>
                            <?php if ($rec['type_patient']==='normal'): ?>
                                <span class="badge bg-success"><?= $labelTyp['normal'] ?></span>
                            <?php else: ?>
                                <span class="badge" style="<?= $colorTyp[$rec['type_patient']] ?? '' ?>">
                                    <?= h($labelTyp[$rec['type_patient']] ?? $rec['type_patient']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold" style="color:#2e7d32;">
                            <?= number_format($rec['montant_encaisse'],0,',',' ') ?> F
                        </td>
                        <td>
                            <small><?= h($rec['nom_user'].' '.$rec['prenom_user']) ?></small>
                        </td>
                        <td>
                            <small class="text-muted"><?= date('H:i', strtotime($rec['whendone'])) ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-3 text-muted text-center">Aucun reçu émis.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ── Modal Situation par Période (inchangé) ──────────────────────────────── -->
<div class="modal fade" id="modalPeriodePercepteur" tabindex="-1" aria-labelledby="modalPeriodeLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#1b5e20;">
                <h5 class="modal-title text-white" id="modalPeriodeLabel">
                    <i class="bi bi-calendar-range me-2"></i>Situation Percepteur – Par Période
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    <i class="bi bi-person-circle me-1"></i>
                    Percepteur : <strong id="modalPercepNom"></strong>
                </div>
                <form id="formPeriodePercepteur">
                    <input type="hidden" id="modalPercepId" value="">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date de début</label>
                            <input type="date" class="form-control" id="periodeDateDebut"
                                   value="<?= date('Y-m-01') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date de fin</label>
                            <input type="date" class="form-control" id="periodeDateFin"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn text-white" style="background:#1b5e20;"
                        onclick="genererSituationPeriode()">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Voir &amp; Imprimer PDF
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$labelsEvoJs       = json_encode($labelsEvo);
$dataEvoJs         = json_encode($dataEvo);
$dataEvoRecJs      = json_encode($dataEvoRec);
$repLabelsJs       = json_encode($repLabels);
$repDataJs         = json_encode($repData);
$situationPercepUrl = url('modules/pdf/situation_percepteur.php');

$extraJs = <<<HEREDOC
<script>
const SITUATION_PERCEP_URL = '{$situationPercepUrl}';
const fmtFR = v => new Intl.NumberFormat('fr-FR').format(Math.round(v));

function ouvrirModalPeriode(percepId, percepNom) {
    document.getElementById('modalPercepId').value = percepId;
    document.getElementById('modalPercepNom').textContent = percepNom;
    new bootstrap.Modal(document.getElementById('modalPeriodePercepteur')).show();
}

function genererSituationPeriode() {
    const id  = document.getElementById('modalPercepId').value;
    const deb = document.getElementById('periodeDateDebut').value;
    const fin = document.getElementById('periodeDateFin').value;
    if (!deb || !fin) { alert('Veuillez sélectionner les deux dates.'); return; }
    if (deb > fin)    { alert('La date de début doit être antérieure à la date de fin.'); return; }
    const url = SITUATION_PERCEP_URL + '?percepteur_id=' + id + '&mode=periode&date_debut=' + deb + '&date_fin=' + fin;
    window.open(url, '_blank');
}

// ── Évolution dual axis (reçus + recettes) ───────────────────────────────────
new Chart(document.getElementById('chartEvolution'), {
    data: {
        labels: {$labelsEvoJs},
        datasets: [
            {
                type: 'bar',
                label: 'Reçus',
                data: {$dataEvoJs},
                backgroundColor: 'rgba(46,125,50,.7)',
                borderColor: '#2e7d32',
                borderRadius: 6,
                yAxisID: 'yRecus'
            },
            {
                type: 'line',
                label: 'Recettes (F)',
                data: {$dataEvoRecJs},
                borderColor: '#1565c0',
                backgroundColor: 'rgba(21,101,192,0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                yAxisID: 'yRecettes'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index' },
        plugins: {
            legend: { position: 'top' },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.yAxisID === 'yRecettes'
                        ? 'Recettes : ' + fmtFR(ctx.raw) + ' F'
                        : 'Reçus : ' + ctx.raw
                }
            }
        },
        scales: {
            yRecus:    { type:'linear', position:'left',  beginAtZero:true, ticks:{stepSize:1}, title:{display:true,text:'Reçus'} },
            yRecettes: { type:'linear', position:'right', beginAtZero:true, grid:{drawOnChartArea:false},
                         ticks:{callback:v=>fmtFR(v)+' F'}, title:{display:true,text:'Recettes'} }
        }
    }
});

// ── Répartition par pôle ─────────────────────────────────────────────────────
const repLabels = {$repLabelsJs};
const repData   = {$repDataJs};
const repColors = ['#2e7d32','#e65100','#006064','#7b1fa2'];
if (repData.length > 0 && repData.some(v => v > 0)) {
    new Chart(document.getElementById('chartRepartition'), {
        type: 'doughnut',
        data: {
            labels: repLabels,
            datasets: [{
                data: repData,
                backgroundColor: repColors.slice(0, repLabels.length),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.label + ' : ' + fmtFR(ctx.raw) + ' F'
                    }
                }
            }
        }
    });
} else {
    document.getElementById('chartRepartition').parentElement.innerHTML =
        '<p class="text-muted text-center py-4">Aucune donnée ce mois-ci.</p>';
}
</script>
HEREDOC;

include ROOT_PATH . '/templates/layouts/footer.php';
?>
