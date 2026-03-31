<?php
/**
 * Tableau de Bord – Réservé Administrateur
 */
requireRole('admin');
$pdo       = Database::getInstance();
$pageTitle = 'Tableau de Bord';

// ── KPIs ───────────────────────────────────────────────────────────────────
// Patients du jour / semaine / mois
$patientsJour    = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND DATE(whendone)=CURDATE()")->fetchColumn();
$patientsLast7   = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND whendone >= DATE_SUB(CURDATE(),INTERVAL 7 DAY)")->fetchColumn();
$patientsMois    = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM recus WHERE isDeleted=0 AND MONTH(whendone)=MONTH(CURDATE()) AND YEAR(whendone)=YEAR(CURDATE())")->fetchColumn();

// Recettes du jour (total encaissé)
$recettesJour    = (int)$pdo->query("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone)=CURDATE()")->fetchColumn();

// Coût actes gratuits du jour (pour reporting bailleurs)
$coutGratuitJour = (int)$pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM recus WHERE isDeleted=0 AND type_patient IN ('orphelin','acte_gratuit') AND DATE(whendone)=CURDATE()")->fetchColumn();

// Total recettes sur filtre période (GET)
$filtreDebut  = $_GET['filtre_debut'] ?? date('Y-m-01');
$filtreFin    = $_GET['filtre_fin']   ?? date('Y-m-d');
$recettesPeriode = (int)$pdo->prepare("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f")
    ->execute([':d'=>$filtreDebut,':f'=>$filtreFin]) ? (int)$pdo->query("SELECT COALESCE(SUM(montant_encaisse),0) FROM recus WHERE isDeleted=0 AND DATE(whendone) BETWEEN '{$filtreDebut}' AND '{$filtreFin}'")->fetchColumn() : 0;

// ── Graphique 1 : Évolution consultations 7 derniers jours ─────────────────
$evolution = $pdo->query("
    SELECT DATE(whendone) AS jour, COUNT(*) AS nb
    FROM recus
    WHERE isDeleted=0 AND type_recu='consultation' AND whendone >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(whendone)
    ORDER BY jour ASC
")->fetchAll();
$labelsEvo  = array_map(fn($r) => date('d/m', strtotime($r['jour'])), $evolution);
$dataEvo    = array_column($evolution, 'nb');

// ── Graphique 2 : Répartition revenus par pôle ─────────────────────────────
$repartition = $pdo->query("
    SELECT type_recu,
           COALESCE(SUM(montant_encaisse),0) AS total
    FROM recus
    WHERE isDeleted=0 AND MONTH(whendone)=MONTH(CURDATE()) AND YEAR(whendone)=YEAR(CURDATE())
    GROUP BY type_recu
")->fetchAll();
$repLabels = [];
$repData   = [];
$repColors = ['consultation'=>'#2e7d32','examen'=>'#e65100','pharmacie'=>'#006064'];
foreach ($repartition as $r) {
    $repLabels[] = ucfirst($r['type_recu']);
    $repData[]   = (int)$r['total'];
}

// ── Alertes stock ──────────────────────────────────────────────────────────
$alertesStock = $pdo->query("
    SELECT nom, forme, stock_actuel, seuil_alerte, date_peremption,
           CASE
               WHEN stock_actuel = 0 THEN 'rupture'
               WHEN date_peremption IS NOT NULL AND date_peremption <= CURDATE() THEN 'perime'
               ELSE 'alerte'
           END AS type_alerte
    FROM produits_pharmacie
    WHERE isDeleted=0
      AND (stock_actuel <= seuil_alerte OR (date_peremption IS NOT NULL AND date_peremption <= CURDATE()))
    ORDER BY stock_actuel ASC
")->fetchAll();

// ── Activité par percepteur ────────────────────────────────────────────────
$activitePercep = $pdo->query("
    SELECT u.nom, u.prenom, u.login,
           COUNT(r.id) AS nb_recus,
           COALESCE(SUM(r.montant_encaisse),0) AS total_encaisse
    FROM utilisateurs u
    LEFT JOIN recus r ON r.whodone = u.id AND r.isDeleted=0 AND DATE(r.whendone)=CURDATE()
    WHERE u.role='percepteur' AND u.isDeleted=0
    GROUP BY u.id
    ORDER BY total_encaisse DESC
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
            <h4 class="mb-0 text-csi fw-bold">Tableau de Bord – Administration</h4>
            <small class="text-muted">Mis à jour le <?= date('d/m/Y à H:i') ?></small>
        </div>
    </div>

    <!-- ── KPI Cards ─────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="kpi-value"><?= $patientsJour ?></div>
                        <div class="kpi-label">Patients aujourd'hui</div>
                        <small class="text-muted">7j: <?= $patientsLast7 ?> · Mois: <?= $patientsMois ?></small>
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
                    <div>
                        <div class="kpi-value" style="color:#1565c0;"><?= number_format($recettesJour,0,',',' ') ?> F</div>
                        <div class="kpi-label">Recettes du jour</div>
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
                    <div>
                        <div class="kpi-value" style="color:#e65100;"><?= number_format($coutGratuitJour,0,',',' ') ?> F</div>
                        <div class="kpi-label">Coût actes gratuits (bailleur)</div>
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
                    <div>
                        <div class="kpi-value" style="color:#d32f2f;"><?= count($alertesStock) ?></div>
                        <div class="kpi-label">Alertes stock</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filtre période ────────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" action="/index.php" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="dashboard">
                <div class="col-auto"><label class="form-label mb-0 fw-semibold">Recettes sur période :</label></div>
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" name="filtre_debut"
                           value="<?= h($filtreDebut) ?>">
                </div>
                <div class="col-auto"><span class="text-muted">→</span></div>
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" name="filtre_fin"
                           value="<?= h($filtreFin) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--csi-green);">
                        <i class="bi bi-filter"></i> Filtrer
                    </button>
                </div>
                <div class="col-auto">
                    <span class="badge fs-6 bg-success px-3 py-2">
                        <?= number_format($recettesPeriode,0,',',' ') ?> F
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
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Évolution consultations – 7 derniers jours</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartEvolution" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Répartition revenus – Mois en cours</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartRepartition" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Alertes Stock & Productivité Percepteurs ───────────────────────── -->
    <div class="row g-3">
        <!-- Alertes Stock -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header" style="background:#ffebee;border-bottom:2px solid #d32f2f;">
                    <h6 class="mb-0 text-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Alertes Stock (<?= count($alertesStock) ?>)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($alertesStock): ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Produit</th><th>Forme</th><th>Stock</th><th>Seuil</th><th>Type</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($alertesStock as $a): ?>
                            <tr class="stock-alerte">
                                <td><strong><?= h($a['nom']) ?></strong></td>
                                <td><small><?= h($a['forme']) ?></small></td>
                                <td class="fw-bold text-danger"><?= $a['stock_actuel'] ?></td>
                                <td class="text-muted"><?= $a['seuil_alerte'] ?></td>
                                <td>
                                    <?php if ($a['type_alerte'] === 'rupture'): ?>
                                        <span class="badge bg-danger">Rupture</span>
                                    <?php elseif ($a['type_alerte'] === 'perime'): ?>
                                        <span class="badge bg-danger">Périmé (<?= date('d/m/Y',strtotime($a['date_peremption'])) ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">⚠ Alerte</span>
                                    <?php endif; ?>
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

        <!-- Productivité Percepteurs -->
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-csi-light">
                    <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Activité Percepteurs – Aujourd'hui</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Percepteur</th><th class="text-center">Reçus</th><th class="text-end">Encaissé</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($activitePercep as $p): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-person-circle text-csi me-1"></i>
                                    <?= h($p['nom'] . ' ' . $p['prenom']) ?>
                                    <div><small class="text-muted"><?= h($p['login']) ?></small></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $p['nb_recus'] > 0 ? 'success' : 'secondary' ?>">
                                        <?= $p['nb_recus'] ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold">
                                    <?= $p['nb_recus'] > 0 ? number_format($p['total_encaisse'],0,',',' ').' F' : '<span class="text-muted">0 F</span>' ?>
                                </td>
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
$labelsEvoJs  = json_encode($labelsEvo);
$dataEvoJs    = json_encode($dataEvo);
$repLabelsJs  = json_encode($repLabels);
$repDataJs    = json_encode($repData);
$repColorsJs  = json_encode(array_values(array_intersect_key($repColors, array_flip(array_map('strtolower', $repLabels)))));

$extraJs = <<<HEREDOC
<script>
// ── Chart Évolution ──────────────────────────────────────────────────────────
new Chart(document.getElementById('chartEvolution'), {
    type: 'bar',
    data: {
        labels: {$labelsEvoJs},
        datasets: [{
            label: 'Consultations',
            data: {$dataEvoJs},
            backgroundColor: 'rgba(46,125,50,.7)',
            borderColor: '#2e7d32',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// ── Chart Répartition ────────────────────────────────────────────────────────
const repLabels = {$repLabelsJs};
const repData   = {$repDataJs};
const repColors = ['#2e7d32','#e65100','#006064','#7b1fa2'];
if (repData.length > 0) {
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
                        label: ctx => ctx.label + ' : ' +
                            new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' F'
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
