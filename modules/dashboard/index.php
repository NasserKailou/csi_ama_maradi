<?php
/**
 * Dashboard Admin – Tableau de Bord Global
 */
requireRole('admin');
$pdo       = Database::getInstance();
$pageTitle = 'Tableau de Bord';

// ── KPIs ──────────────────────────────────────────────────────────────────
// Patients du jour
$patientsJour = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND DATE(whendone)=CURDATE()")->fetchColumn();
// Patients semaine
$patientsSemaine = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND YEARWEEK(whendone,1)=YEARWEEK(CURDATE(),1)")->fetchColumn();
// Patients mois
$patientsMois = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND MONTH(whendone)=MONTH(CURDATE()) AND YEAR(whendone)=YEAR(CURDATE())")->fetchColumn();

// Recettes du jour (encaissées)
$recettesJour = (int)$pdo->query("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone)=CURDATE()")->fetchColumn();

// Coût actes gratuits du jour
$coutsGratuits = (int)$pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM recus WHERE isDeleted=0 AND type_patient IN('orphelin','acte_gratuit') AND DATE(whendone)=CURDATE()")->fetchColumn();

// Filtre période (recettes)
$filtreDebut = $_GET['filter_debut'] ?? date('Y-m-01');
$filtreFin   = $_GET['filter_fin']   ?? date('Y-m-d');
$recettesPeriode = (int)$pdo->prepare("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f");
$recettesPeriode->execute([':d'=>$filtreDebut,':f'=>$filtreFin]);
$recettesPeriode = (int)$pdo->prepare("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f")->execute([':d'=>$filtreDebut,':f'=>$filtreFin]) ? 0 : 0;
// Re-query properly
$stmtRp = $pdo->prepare("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f");
$stmtRp->execute([':d'=>$filtreDebut,':f'=>$filtreFin]);
$recettesPeriode = (int)$stmtRp->fetchColumn();

// ── Graphique 7 jours ─────────────────────────────────────────────────────
$labels7j = [];
$data7j   = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels7j[] = date('d/m', strtotime($d));
    $cnt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND DATE(whendone)=:d");
    $cnt->execute([':d' => $d]);
    $data7j[] = (int)$cnt->fetchColumn();
}

// ── Répartition revenus ────────────────────────────────────────────────────
$repartition = $pdo->query("
    SELECT type_recu,
           COALESCE(SUM(montant_encaisse),0) AS total
    FROM recus
    WHERE isDeleted=0
      AND MONTH(whendone)=MONTH(CURDATE())
      AND YEAR(whendone)=YEAR(CURDATE())
    GROUP BY type_recu
")->fetchAll();
$revenusParType = ['consultation'=>0,'examen'=>0,'pharmacie'=>0];
foreach ($repartition as $r) {
    $revenusParType[$r['type_recu']] = (int)$r['total'];
}

// ── Alertes stock ──────────────────────────────────────────────────────────
$alertesStock = $pdo->query("
    SELECT nom, forme, stock_actuel, seuil_alerte, date_peremption,
           CASE WHEN stock_actuel=0 THEN 'Rupture de stock'
                WHEN date_peremption IS NOT NULL AND date_peremption<=CURDATE() THEN 'Périmé'
                ELSE 'Stock bas' END AS raison
    FROM produits_pharmacie
    WHERE isDeleted=0
      AND (stock_actuel <= seuil_alerte OR (date_peremption IS NOT NULL AND date_peremption<=CURDATE()))
    ORDER BY stock_actuel ASC
    LIMIT 10
")->fetchAll();

// ── Activité par percepteur ────────────────────────────────────────────────
$activitePercepteurs = $pdo->query("
    SELECT u.nom, u.prenom,
           COUNT(r.id) AS nb_recus,
           COALESCE(SUM(r.montant_encaisse),0) AS total_encaisse
    FROM utilisateurs u
    LEFT JOIN recus r ON r.whodone=u.id AND r.isDeleted=0 AND DATE(r.whendone)=CURDATE()
    WHERE u.role='percepteur' AND u.isDeleted=0
    GROUP BY u.id
    ORDER BY nb_recus DESC
")->fetchAll();

include ROOT_PATH . '/templates/layouts/header.php';
?>

<div class="mt-4">
    <div class="d-flex align-items-center mb-4">
        <div class="bg-csi rounded-circle d-flex align-items-center justify-content-center me-3"
             style="width:50px;height:50px;">
            <i class="bi bi-speedometer2 text-white fs-4"></i>
        </div>
        <div>
            <h4 class="mb-0 fw-bold text-csi">Tableau de Bord Global</h4>
            <small class="text-muted"><?= date('l d F Y', strtotime('today')) ?></small>
        </div>
    </div>

    <!-- ── KPI Widgets ──────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="kpi-value"><?= number_format($patientsJour) ?></div>
                        <div class="kpi-label">Patients aujourd'hui</div>
                        <small class="text-muted"><?= number_format($patientsSemaine) ?> / sem · <?= number_format($patientsMois) ?> / mois</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card" style="border-left-color:#1565c0;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#e3f2fd;color:#1565c0;"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#1565c0;"><?= number_format($recettesJour) ?> F</div>
                        <div class="kpi-label">Recettes du jour</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card" style="border-left-color:#e65100;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#fff3e0;color:#e65100;"><i class="bi bi-heart"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#e65100;"><?= number_format($coutsGratuits) ?> F</div>
                        <div class="kpi-label">Coût actes gratuits (jour)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card" style="border-left-color:#6a1b9a;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#f3e5f5;color:#6a1b9a;"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <div class="kpi-value" style="color:#6a1b9a;"><?= count($alertesStock) ?></div>
                        <div class="kpi-label">Alertes Stock</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filtre période ────────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="GET" action="/index.php">
                <input type="hidden" name="page" value="dashboard">
                <div class="col-md-4">
                    <label class="form-label small">Recettes sur période</label>
                    <input type="date" class="form-control form-control-sm" name="filter_debut" value="<?= h($filtreDebut) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">au</label>
                    <input type="date" class="form-control form-control-sm" name="filter_fin" value="<?= h($filtreFin) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-sm text-white w-100" style="background:var(--csi-green);">
                        <i class="bi bi-search"></i> Calculer
                    </button>
                </div>
            </form>
            <div class="mt-2 p-2 bg-light rounded">
                Recettes <strong><?= date('d/m/Y', strtotime($filtreDebut)) ?></strong>
                → <strong><?= date('d/m/Y', strtotime($filtreFin)) ?></strong> :
                <span class="fw-bold fs-5 text-csi"><?= number_format($recettesPeriode) ?> F</span>
            </div>
        </div>
    </div>

    <!-- ── Graphiques ─────────────────────────────────────────────────────── -->
    <div class="row g-4 mb-4">
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Consultations – 7 derniers jours</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartConsultations" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Revenus par pôle (mois en cours)</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartRepartition" height="180" style="max-height:200px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Alertes stock + Activité percepteurs ──────────────────────────── -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header" style="background:#ffebee;border-bottom:2px solid #d32f2f;">
                    <h6 class="mb-0 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Alertes Stock</h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($alertesStock): ?>
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Produit</th><th>Stock</th><th>Raison</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($alertesStock as $a): ?>
                        <tr class="<?= $a['stock_actuel']==0||$a['raison']==='Périmé'?'table-danger':'table-warning' ?>">
                            <td><strong><?= h($a['nom']) ?></strong><br><small><?= h($a['forme']) ?></small></td>
                            <td class="fw-bold"><?= (int)$a['stock_actuel'] ?></td>
                            <td><span class="badge <?= $a['raison']==='Rupture de stock'?'bg-danger':($a['raison']==='Périmé'?'bg-dark':'bg-warning text-dark') ?>"><?= $a['raison'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle-fill text-success fs-2"></i>
                        <p class="mt-2 mb-0">Aucune alerte de stock</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Activité Percepteurs (aujourd'hui)</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Percepteur</th><th>Nb Reçus</th><th>Total encaissé</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($activitePercepteurs as $ap): ?>
                        <tr>
                            <td><?= h($ap['nom'] . ' ' . $ap['prenom']) ?></td>
                            <td><span class="badge bg-csi"><?= (int)$ap['nb_recus'] ?></span></td>
                            <td class="fw-bold text-csi"><?= number_format($ap['total_encaisse']) ?> F</td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$labels = json_encode($labels7j);
$vals   = json_encode($data7j);
$rcons  = (int)$revenusParType['consultation'];
$rexam  = (int)$revenusParType['examen'];
$rphar  = (int)$revenusParType['pharmacie'];

$extraJs = <<<JS
<script>
// Graphique barres – 7 jours
new Chart(document.getElementById('chartConsultations'), {
    type: 'bar',
    data: {
        labels: {$labels},
        datasets: [{
            label: 'Patients',
            data: {$vals},
            backgroundColor: 'rgba(46,125,50,0.6)',
            borderColor: '#2e7d32',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

// Graphique camembert – Répartition revenus
new Chart(document.getElementById('chartRepartition'), {
    type: 'doughnut',
    data: {
        labels: ['Consultation', 'Examens', 'Pharmacie'],
        datasets: [{
            data: [{$rcons}, {$rexam}, {$rphar}],
            backgroundColor: ['#2e7d32','#e65100','#006064'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.label + ' : ' + new Intl.NumberFormat('fr-FR').format(ctx.parsed) + ' F';
                    }
                }
            }
        }
    }
});
</script>
JS;

include ROOT_PATH . '/templates/layouts/footer.php';
?>
