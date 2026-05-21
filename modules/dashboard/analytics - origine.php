<?php
/**
 * Tableau de Bord Analytique Avancé – Réservé Administrateur
 */
requireRole('admin');
$pdo       = Database::getInstance();
$pageTitle = 'Analytique Avancée';

// ── Période de filtre (défaut : mois en cours) ─────────────────────────────
$filtreDebut = $_GET['filtre_debut'] ?? date('Y-m-01');
$filtreFin   = $_GET['filtre_fin']   ?? date('Y-m-d');

// ════════════════════════════════════════════════════════════════════════════
// 1. KPIs globaux sur la période
// ════════════════════════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT patient_id)           AS nb_patients,
        COUNT(*)                              AS nb_recus,
        COALESCE(SUM(montant_encaisse),0)     AS total_encaisse,
        COALESCE(SUM(montant_total),0)        AS total_theorique,
        SUM(CASE WHEN type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END) AS nb_gratuits
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
");
$stmt->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$kpi = $stmt->fetch();

// ════════════════════════════════════════════════════════════════════════════
// 2. Actes médicaux les plus utilisés (Top 10)
// ════════════════════════════════════════════════════════════════════════════
$topActes = $pdo->prepare("
    SELECT a.libelle, COUNT(lc.id) AS nb_utilisations, a.tarif,
           SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END) AS nb_orphelins
    FROM lignes_consultation lc
    JOIN actes_medicaux a ON a.id = lc.acte_id
    JOIN recus r ON r.id = lc.recu_id AND r.isDeleted=0
    WHERE lc.isDeleted=0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY a.id
    ORDER BY nb_utilisations DESC
    LIMIT 10
");
$topActes->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$topActes = $topActes->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 3. Produits pharmacie les plus consommés (Top 10)
// ════════════════════════════════════════════════════════════════════════════
$topProduits = $pdo->prepare("
    SELECT lp.nom, lp.forme,
           SUM(lp.quantite)     AS total_qte,
           SUM(lp.total_ligne)  AS total_revenu
    FROM lignes_pharmacie lp
    JOIN recus r ON r.id = lp.recu_id AND r.isDeleted=0
    WHERE lp.isDeleted=0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY lp.nom, lp.forme
    ORDER BY total_qte DESC
    LIMIT 10
");
$topProduits->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$topProduits = $topProduits->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 4. Types de patients (répartition)
// ════════════════════════════════════════════════════════════════════════════
$typePatients = $pdo->prepare("
    SELECT type_patient, COUNT(*) AS nb,
           COALESCE(SUM(montant_encaisse),0) AS montant
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_patient
    ORDER BY nb DESC
");
$typePatients->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$typePatients = $typePatients->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 5. Évolution journalière sur la période (recettes + nb patients)
// ════════════════════════════════════════════════════════════════════════════
$evolution = $pdo->prepare("
    SELECT DATE(whendone) AS jour,
           COUNT(DISTINCT patient_id) AS nb_patients,
           COALESCE(SUM(montant_encaisse),0) AS recettes
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY DATE(whendone)
    ORDER BY jour ASC
");
$evolution->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$evolution = $evolution->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 6. Examens les plus prescrits (Top 10)
// ════════════════════════════════════════════════════════════════════════════
$topExamens = $pdo->prepare("
    SELECT e.libelle, COUNT(le.id) AS nb,
           COALESCE(SUM(le.cout_total),0) AS total_revenu,
           e.pourcentage_labo
    FROM lignes_examen le
    JOIN examens e ON e.id = le.examen_id
    JOIN recus r ON r.id = le.recu_id AND r.isDeleted=0
    WHERE le.isDeleted=0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY e.id
    ORDER BY nb DESC
    LIMIT 10
");
$topExamens->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$topExamens = $topExamens->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 7. Performance percepteurs sur la période
// ════════════════════════════════════════════════════════════════════════════
$perfPercep = $pdo->prepare("
    SELECT u.nom, u.prenom,
           COUNT(r.id)                        AS nb_recus,
           COUNT(DISTINCT r.patient_id)        AS nb_patients,
           COALESCE(SUM(r.montant_encaisse),0) AS total_encaisse
    FROM utilisateurs u
    LEFT JOIN recus r ON r.whodone=u.id AND r.isDeleted=0
        AND DATE(r.whendone) BETWEEN :d AND :f
    WHERE u.role='percepteur' AND u.isDeleted=0
    GROUP BY u.id
    ORDER BY total_encaisse DESC
");
$perfPercep->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$perfPercep = $perfPercep->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 8. Répartition revenus par pôle sur la période
// ════════════════════════════════════════════════════════════════════════════
$repartition = $pdo->prepare("
    SELECT type_recu, COALESCE(SUM(montant_encaisse),0) AS total
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_recu
");
$repartition->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$repartition = $repartition->fetchAll();

// ── Préparer les données JSON pour les graphiques ─────────────────────────
$labelsEvo    = array_map(fn($r) => date('d/m', strtotime($r['jour'])), $evolution);
$dataPatientsEvo = array_column($evolution, 'nb_patients');
$dataRecettesEvo = array_column($evolution, 'recettes');

$labelsActes  = array_column($topActes,   'libelle');
$dataActes    = array_column($topActes,   'nb_utilisations');

$labelsProd   = array_map(fn($p) => $p['nom'].($p['forme'] ? ' ('.$p['forme'].')' : ''), $topProduits);
$dataProdQte  = array_column($topProduits, 'total_qte');
$dataProdRev  = array_column($topProduits, 'total_revenu');

$labelsType   = array_map(fn($t) => [
    'normal'=>'Normal payant','orphelin'=>'Orphelin','acte_gratuit'=>'Acte gratuit',
    'nourrisson'=>'Nourrisson','cpn'=>'CPN'
][$t['type_patient']] ?? ucfirst($t['type_patient']), $typePatients);
$dataType     = array_column($typePatients, 'nb');

$labelsExam   = array_column($topExamens, 'libelle');
$dataExam     = array_column($topExamens, 'nb');

$labelsRep    = array_map(fn($r) => ucfirst($r['type_recu']), $repartition);
$dataRep      = array_column($repartition, 'total');

include ROOT_PATH . '/templates/layouts/header.php';
?>

<div class="mt-4">

    <!-- En-tête -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center me-3"
                 style="width:50px;height:50px;background:linear-gradient(135deg,#1565c0,#0d47a1);">
                <i class="bi bi-graph-up-arrow text-white fs-4"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold" style="color:#1565c0;">Analytique Avancée</h4>
                <small class="text-muted">Analyse détaillée de l'activité CSI DirectAid Maradi</small>
            </div>
        </div>
        <a href="<?= url('index.php?page=dashboard') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Tableau de bord principal
        </a>
    </div>

    <!-- ── Filtre Période ──────────────────────────────────────────────────── -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-2" style="background:linear-gradient(90deg,#e3f2fd,#f8f9fa);">
            <form method="GET" class="row g-2 align-items-end flex-wrap">
                <input type="hidden" name="page" value="analytics">
                <div class="col-auto">
                    <label class="form-label mb-0 fw-semibold text-primary">
                        <i class="bi bi-calendar-range me-1"></i>Période d'analyse :
                    </label>
                </div>
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" name="filtre_debut" value="<?= h($filtreDebut) ?>">
                </div>
                <div class="col-auto"><span class="text-muted">→</span></div>
                <div class="col-auto">
                    <input type="date" class="form-control form-control-sm" name="filtre_fin" value="<?= h($filtreFin) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm text-white" style="background:#1565c0;">
                        <i class="bi bi-search me-1"></i>Analyser
                    </button>
                </div>
                <!-- Raccourcis rapides -->
                <div class="col-auto d-flex gap-1">
                    <?php
                    $shortcuts = [
                        ['7j', date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), 'light'],
                        ['30j', date('Y-m-d', strtotime('-30 days')), date('Y-m-d'), 'light'],
                        ['Ce mois', date('Y-m-01'), date('Y-m-d'), 'light'],
                    ];
                    foreach ($shortcuts as [$label, $deb, $fin, $cls]):
                    ?>
                    <a href="?page=analytics&filtre_debut=<?= $deb ?>&filtre_fin=<?= $fin ?>"
                       class="btn btn-<?= $cls ?> btn-sm border"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ── KPI Cards ─────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <?php
        $kpiCards = [
            ['bi-people-fill',        '#1565c0', '#dbeafe', number_format($kpi['nb_patients'],0,',',' '),        'Patients uniques',        null],
            ['bi-receipt',            '#2e7d32', '#dcfce7', number_format($kpi['nb_recus'],0,',',' '),            'Reçus émis',              null],
            ['bi-cash-stack',         '#e65100', '#fef3e2', number_format($kpi['total_encaisse'],0,',',' ').' F', 'Total encaissé',          null],
            ['bi-heart-pulse-fill',   '#7b1fa2', '#f3e8ff', number_format($kpi['nb_gratuits'],0,',',' '),         'Actes gratuits/orphelins',null],
        ];
        foreach ($kpiCards as [$icon, $color, $bg, $val, $label, $sub]):
        ?>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid <?= $color ?> !important;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;background:<?= $bg ?>;color:<?= $color ?>;min-width:48px;">
                        <i class="bi <?= $icon ?> fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5" style="color:<?= $color ?>"><?= $val ?></div>
                        <div class="text-muted small"><?= $label ?></div>
                        <?php if ($sub): ?><small class="text-muted"><?= $sub ?></small><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Ligne 1 : Évolution + Répartition pôles ──────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#1565c0,#1976d2);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Évolution journalière – Patients &amp; Recettes</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartEvolution" height="160"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#2e7d32,#388e3c);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-pie-chart-fill me-2"></i>Revenus par pôle</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartRepartition" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Ligne 2 : Top Actes + Top Examens ──────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#e65100,#f4511e);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-stethoscope me-2"></i>Top 10 Actes Médicaux les plus utilisés</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartActes" height="220"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#006064,#00838f);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Top 10 Examens prescrits</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartExamens" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Ligne 3 : Top Produits + Types Patients ────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#6a1b9a,#8e24aa);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-capsule me-2"></i>Top 10 Produits Pharmacie les plus consommés</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartProduits" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#d32f2f,#e53935);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Répartition types de patients</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartTypePatients" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Ligne 4 : Tables détaillées ───────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <!-- Top Actes Table -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0 text-warning-emphasis"><i class="bi bi-list-ol me-2"></i>Détail Actes Médicaux</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Acte</th>
                                <th class="text-center">Utilisations</th>
                                <th class="text-center">Orphelins</th>
                                <th class="text-end">Tarif</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($topActes): foreach ($topActes as $i => $a): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= $i+1 ?></span></td>
                                <td class="fw-semibold"><?= h($a['libelle']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?= $a['nb_utilisations'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['nb_orphelins'] > 0): ?>
                                    <span class="badge bg-purple" style="background:#7b1fa2 !important;">
                                        <?= $a['nb_orphelins'] ?>
                                    </span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td class="text-end text-muted small"><?= number_format($a['tarif'],0,',',' ') ?> F</td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Aucune donnée</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Produits Table -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0" style="color:#6a1b9a;"><i class="bi bi-capsule me-2"></i>Détail Produits Pharmacie</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Produit</th><th>Forme</th>
                                <th class="text-center">Qté vendue</th>
                                <th class="text-end">Revenu</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($topProduits): foreach ($topProduits as $i => $p): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= $i+1 ?></span></td>
                                <td class="fw-semibold"><?= h($p['nom']) ?></td>
                                <td><small class="text-muted"><?= h($p['forme']) ?></small></td>
                                <td class="text-center">
                                    <span class="badge" style="background:#6a1b9a;"><?= $p['total_qte'] ?></span>
                                </td>
                                <td class="text-end fw-bold" style="color:#2e7d32;">
                                    <?= number_format($p['total_revenu'],0,',',' ') ?> F
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Aucune donnée</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Performance Percepteurs ────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-0" style="background:linear-gradient(90deg,#37474f,#546e7a);color:#fff;">
            <h6 class="mb-0"><i class="bi bi-people-fill me-2"></i>Performance Percepteurs sur la période</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Percepteur</th>
                        <th class="text-center">Reçus émis</th>
                        <th class="text-center">Patients uniques</th>
                        <th class="text-end">Encaissé</th>
                        <th>Barre de progression</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $maxEncaisse = max(1, ...array_column($perfPercep, 'total_encaisse'));
                foreach ($perfPercep as $p):
                    $pct = round(($p['total_encaisse'] / $maxEncaisse) * 100);
                ?>
                    <tr>
                        <td>
                            <i class="bi bi-person-badge me-1" style="color:#1565c0;"></i>
                            <strong><?= h($p['nom'].' '.$p['prenom']) ?></strong>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $p['nb_recus'] > 0 ? 'primary' : 'secondary' ?>">
                                <?= $p['nb_recus'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $p['nb_patients'] > 0 ? 'info' : 'secondary' ?> text-dark">
                                <?= $p['nb_patients'] ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold" style="color:#2e7d32;">
                            <?= number_format($p['total_encaisse'],0,',',' ') ?> F
                        </td>
                        <td style="min-width:140px;">
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar" role="progressbar"
                                     style="width:<?= $pct ?>%;background:linear-gradient(90deg,#1565c0,#42a5f5);"
                                     aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted"><?= $pct ?>%</small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.mt-4 -->

<?php
// ── JSON pour Chart.js ─────────────────────────────────────────────────────
$jsLabelsEvo       = json_encode($labelsEvo);
$jsDataPatientsEvo = json_encode($dataPatientsEvo);
$jsDataRecettesEvo = json_encode($dataRecettesEvo);
$jsLabelsActes     = json_encode($labelsActes);
$jsDataActes       = json_encode($dataActes);
$jsLabelsProd      = json_encode($labelsProd);
$jsDataProdQte     = json_encode($dataProdQte);
$jsDataProdRev     = json_encode($dataProdRev);
$jsLabelsType      = json_encode($labelsType);
$jsDataType        = json_encode($dataType);
$jsLabelsExam      = json_encode($labelsExam);
$jsDataExam        = json_encode($dataExam);
$jsLabelsRep       = json_encode($labelsRep);
$jsDataRep         = json_encode($dataRep);

$extraJs = <<<HEREDOC
<script>
// Palette couleurs
const PALETTE = ['#1565c0','#2e7d32','#e65100','#006064','#6a1b9a','#d32f2f','#f57f17','#00695c','#37474f','#880e4f'];

// ── 1. Évolution journalière (dual axis) ──────────────────────────────────────
new Chart(document.getElementById('chartEvolution'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsEvo},
        datasets: [
            {
                type: 'line',
                label: 'Recettes (F)',
                data: {$jsDataRecettesEvo},
                borderColor: '#1565c0',
                backgroundColor: 'rgba(21,101,192,0.1)',
                fill: true,
                tension: 0.4,
                yAxisID: 'yRevenu',
                pointBackgroundColor: '#1565c0',
                pointRadius: 4
            },
            {
                type: 'bar',
                label: 'Patients',
                data: {$jsDataPatientsEvo},
                backgroundColor: 'rgba(46,125,50,0.7)',
                borderColor: '#2e7d32',
                borderRadius: 5,
                yAxisID: 'yPatients'
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
                    label: ctx => {
                        if (ctx.dataset.yAxisID === 'yRevenu')
                            return 'Recettes : ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' F';
                        return 'Patients : ' + ctx.raw;
                    }
                }
            }
        },
        scales: {
            yPatients: { type:'linear', position:'left',  beginAtZero:true, ticks:{stepSize:1}, title:{display:true,text:'Patients'} },
            yRevenu:   { type:'linear', position:'right', beginAtZero:true, grid:{drawOnChartArea:false},
                         ticks:{callback:v=>new Intl.NumberFormat('fr-FR').format(v)+' F'}, title:{display:true,text:'Recettes'} }
        }
    }
});

// ── 2. Répartition revenus par pôle ──────────────────────────────────────────
(function(){
    const labels = {$jsLabelsRep};
    const data   = {$jsDataRep};
    if (!data.length) {
        document.getElementById('chartRepartition').closest('.card-body').innerHTML =
            '<p class="text-muted text-center py-4">Aucune donnée sur la période.</p>';
        return;
    }
    new Chart(document.getElementById('chartRepartition'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: PALETTE.slice(0,labels.length), borderWidth:2 }] },
        options: {
            responsive: true,
            plugins: {
                legend: { position:'bottom' },
                tooltip: { callbacks: { label: ctx => ctx.label+' : '+new Intl.NumberFormat('fr-FR').format(ctx.raw)+' F' } }
            }
        }
    });
})();

// ── 3. Top Actes (horizontal bar) ─────────────────────────────────────────────
new Chart(document.getElementById('chartActes'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsActes},
        datasets: [{ label:'Utilisations', data:{$jsDataActes}, backgroundColor:'rgba(230,81,0,0.75)', borderColor:'#e65100', borderRadius:5, borderWidth:1 }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend:{display:false} },
        scales: { x:{ beginAtZero:true, ticks:{stepSize:1} } }
    }
});

// ── 4. Top Examens (horizontal bar) ──────────────────────────────────────────
new Chart(document.getElementById('chartExamens'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsExam},
        datasets: [{ label:'Prescriptions', data:{$jsDataExam}, backgroundColor:'rgba(0,96,100,0.75)', borderColor:'#006064', borderRadius:5, borderWidth:1 }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend:{display:false} },
        scales: { x:{ beginAtZero:true, ticks:{stepSize:1} } }
    }
});

// ── 5. Top Produits (horizontal bar) ─────────────────────────────────────────
new Chart(document.getElementById('chartProduits'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsProd},
        datasets: [
            { label:'Quantité vendue', data:{$jsDataProdQte}, backgroundColor:'rgba(106,27,154,0.75)', borderColor:'#6a1b9a', borderRadius:5, yAxisID:'y' }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend:{display:false} },
        scales: { y:{ beginAtZero:true } }
    }
});

// ── 6. Types de patients (polar area) ────────────────────────────────────────
(function(){
    const labels = {$jsLabelsType};
    const data   = {$jsDataType};
    if (!data.length) {
        document.getElementById('chartTypePatients').closest('.card-body').innerHTML =
            '<p class="text-muted text-center py-4">Aucune donnée.</p>';
        return;
    }
    new Chart(document.getElementById('chartTypePatients'), {
        type: 'polarArea',
        data: { labels, datasets: [{ data, backgroundColor: PALETTE.map(c=>c+'cc'), borderWidth:1 }] },
        options: {
            responsive: true,
            plugins: { legend: { position:'bottom' } }
        }
    });
})();
</script>
HEREDOC;

include ROOT_PATH . '/templates/layouts/footer.php';
?>
