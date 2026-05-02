<?php
/**
 * Tableau de Bord Analytique Avancé – Réservé Administrateur
 * Base : directaid — structure validée 01/05/2026
 * Enrichissements v2 : gratuit vs payant, bivarié, 12 mois, sexe, stock alertes
 */
requireRole('admin');
$pdo       = Database::getInstance();
$pageTitle = 'Analytique Avancée';

$filtreDebut = $_GET['filtre_debut'] ?? date('Y-m-01');
$filtreFin   = $_GET['filtre_fin']   ?? date('Y-m-d');

// ════════════════════════════════════════════════════════════════════════════
// 1. KPIs globaux enrichis
// ════════════════════════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT r.patient_id)                                              AS nb_patients,
        COUNT(*)                                                                   AS nb_recus,
        COALESCE(SUM(r.montant_encaisse), 0)                                      AS total_encaisse,
        COALESCE(SUM(r.montant_total), 0)                                         AS total_theorique,
        SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END) AS nb_gratuits,
        SUM(CASE WHEN r.type_patient = 'normal'       THEN 1 ELSE 0 END)         AS nb_payants,
        SUM(CASE WHEN r.type_patient = 'orphelin'     THEN 1 ELSE 0 END)         AS nb_orphelins,
        SUM(CASE WHEN r.type_patient = 'acte_gratuit' THEN 1 ELSE 0 END)         AS nb_actes_gratuits,
        COALESCE(SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit')
                     THEN r.montant_total ELSE 0 END), 0)                        AS cout_gratuits,
        COUNT(DISTINCT CASE WHEN p.sexe='M' THEN r.patient_id END)               AS nb_patients_M,
        COUNT(DISTINCT CASE WHEN p.sexe='F' THEN r.patient_id END)               AS nb_patients_F,
        CASE WHEN SUM(r.montant_total) > 0
             THEN ROUND(SUM(r.montant_encaisse) / SUM(r.montant_total) * 100, 1)
             ELSE 100 END                                                          AS taux_encaissement
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.isDeleted = 0
      AND DATE(r.whendone) BETWEEN :d AND :f
");
$stmt->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$kpi = $stmt->fetch();

// ════════════════════════════════════════════════════════════════════════════
// 2. Gratuit vs Payant — détail par type_patient
// ════════════════════════════════════════════════════════════════════════════
$stmtGP = $pdo->prepare("
    SELECT type_patient,
           COUNT(*)                              AS nb,
           COALESCE(SUM(montant_total), 0)       AS cout_theorique,
           COALESCE(SUM(montant_encaisse), 0)    AS montant_encaisse,
           COALESCE(SUM(montant_total) - SUM(montant_encaisse), 0) AS manque_a_gagner
    FROM recus
    WHERE isDeleted = 0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_patient
    ORDER BY FIELD(type_patient,'normal','acte_gratuit','orphelin')
");
$stmtGP->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$gratPayData = $stmtGP->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 3. Évolution journalière enrichie (payant / gratuit séparés)
// ════════════════════════════════════════════════════════════════════════════
$evolution = $pdo->prepare("
    SELECT DATE(whendone)                                                          AS jour,
           COUNT(DISTINCT patient_id)                                              AS nb_patients,
           COALESCE(SUM(montant_encaisse), 0)                                     AS recettes,
           COALESCE(SUM(montant_total), 0)                                        AS theorique,
           SUM(CASE WHEN type_patient = 'normal' THEN montant_encaisse ELSE 0 END) AS recettes_payant,
           SUM(CASE WHEN type_patient IN ('orphelin','acte_gratuit')
               THEN montant_total ELSE 0 END)                                     AS cout_gratuit,
           SUM(CASE WHEN type_patient = 'normal' THEN 1 ELSE 0 END)              AS nb_payants,
           SUM(CASE WHEN type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END) AS nb_gratuits
    FROM recus
    WHERE isDeleted = 0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY DATE(whendone)
    ORDER BY jour ASC
");
$evolution->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$evolution = $evolution->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 4. Tendance 12 mois glissants
// ════════════════════════════════════════════════════════════════════════════
$evo12m = $pdo->query("
    SELECT DATE_FORMAT(whendone,'%Y-%m')                  AS mois,
           COUNT(*)                                        AS nb_recus,
           COUNT(DISTINCT patient_id)                     AS nb_patients,
           COALESCE(SUM(montant_encaisse), 0)             AS recettes,
           SUM(CASE WHEN type_patient IN ('orphelin','acte_gratuit')
               THEN montant_total ELSE 0 END)             AS cout_gratuit
    FROM recus
    WHERE isDeleted = 0
      AND whendone >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(whendone,'%Y-%m')
    ORDER BY mois ASC
")->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 5. Bivarié : pôle × type_patient
// ════════════════════════════════════════════════════════════════════════════
$bivarieRecuPat = $pdo->prepare("
    SELECT type_recu, type_patient,
           COUNT(*)                              AS nb,
           COALESCE(SUM(montant_encaisse), 0)   AS encaisse,
           COALESCE(SUM(montant_total), 0)      AS theorique
    FROM recus
    WHERE isDeleted = 0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_recu, type_patient
    ORDER BY type_recu, type_patient
");
$bivarieRecuPat->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$bivarieData = $bivarieRecuPat->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 6. Bivarié : sexe × type_patient
// ════════════════════════════════════════════════════════════════════════════
$bivarieSexePat = $pdo->prepare("
    SELECT p.sexe, r.type_patient,
           COUNT(*)                              AS nb,
           COALESCE(SUM(r.montant_encaisse), 0) AS encaisse
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.isDeleted = 0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY p.sexe, r.type_patient
    ORDER BY p.sexe, r.type_patient
");
$bivarieSexePat->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$bivarieSexeData = $bivarieSexePat->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 7. Répartition par sexe
// ════════════════════════════════════════════════════════════════════════════
$sexeStmt = $pdo->prepare("
    SELECT p.sexe,
           COUNT(r.id)                           AS nb_recus,
           COUNT(DISTINCT r.patient_id)          AS nb_patients,
           COALESCE(SUM(r.montant_encaisse), 0) AS encaisse
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.isDeleted = 0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY p.sexe
");
$sexeStmt->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$sexeRows = [];
foreach ($sexeStmt->fetchAll() as $row) { $sexeRows[$row['sexe']] = $row; }

// ════════════════════════════════════════════════════════════════════════════
// 8. Top actes médicaux — avec ventilation payant/gratuit
// ════════════════════════════════════════════════════════════════════════════
$topActes = $pdo->prepare("
    SELECT a.libelle, a.tarif,
           COUNT(lc.id)                                                            AS nb_utilisations,
           SUM(CASE WHEN r.type_patient = 'normal' THEN 1 ELSE 0 END)            AS nb_payants,
           SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END) AS nb_gratuits,
           SUM(CASE WHEN r.type_patient = 'orphelin' THEN 1 ELSE 0 END)          AS nb_orphelins
    FROM lignes_consultation lc
    JOIN actes_medicaux a ON a.id = lc.acte_id
    JOIN recus r ON r.id = lc.recu_id AND r.isDeleted = 0
    WHERE lc.isDeleted = 0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY a.id, a.libelle, a.tarif
    ORDER BY nb_utilisations DESC
    LIMIT 10
");
$topActes->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$topActes = $topActes->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 9. Top examens — avec revenu labo (lignes_examen.montant_labo)
// ════════════════════════════════════════════════════════════════════════════
$topExamens = $pdo->prepare("
    SELECT e.libelle, e.cout_total AS tarif_catalogue, e.pourcentage_labo,
           COUNT(le.id)                                                            AS nb,
           COALESCE(SUM(le.cout_total), 0)                                        AS total_revenu,
           COALESCE(SUM(le.montant_labo), 0)                                      AS total_labo,
           SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END) AS nb_gratuits
    FROM lignes_examen le
    JOIN examens e ON e.id = le.examen_id
    JOIN recus r ON r.id = le.recu_id AND r.isDeleted = 0
    WHERE le.isDeleted = 0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY e.id, e.libelle, e.cout_total, e.pourcentage_labo
    ORDER BY nb DESC
    LIMIT 10
");
$topExamens->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$topExamens = $topExamens->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 10. Top produits pharmacie — payant vs gratuit
//     lignes_pharmacie : nom, forme, quantite, total_ligne
// ════════════════════════════════════════════════════════════════════════════
$topProduits = $pdo->prepare("
    SELECT lp.nom, lp.forme,
           SUM(lp.quantite)                                                        AS total_qte,
           SUM(lp.total_ligne)                                                     AS total_revenu,
           SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit')
               THEN lp.quantite ELSE 0 END)                                       AS qte_gratuit,
           SUM(CASE WHEN r.type_patient = 'normal' THEN lp.quantite ELSE 0 END)  AS qte_payant
    FROM lignes_pharmacie lp
    JOIN recus r ON r.id = lp.recu_id AND r.isDeleted = 0
    WHERE lp.isDeleted = 0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY lp.nom, lp.forme
    ORDER BY total_qte DESC
    LIMIT 10
");
$topProduits->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$topProduits = $topProduits->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 11. Types de patients
// ════════════════════════════════════════════════════════════════════════════
$typePatients = $pdo->prepare("
    SELECT type_patient,
           COUNT(*)                              AS nb,
           COALESCE(SUM(montant_encaisse), 0)   AS montant,
           COALESCE(SUM(montant_total), 0)      AS theorique
    FROM recus
    WHERE isDeleted = 0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_patient
    ORDER BY nb DESC
");
$typePatients->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$typePatients = $typePatients->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 12. Répartition revenus par pôle
// ════════════════════════════════════════════════════════════════════════════
$repartition = $pdo->prepare("
    SELECT type_recu, COALESCE(SUM(montant_encaisse), 0) AS total
    FROM recus
    WHERE isDeleted = 0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_recu
");
$repartition->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$repartition = $repartition->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 13. Alertes stock pharmacie
//     produits_pharmacie : stock_actuel, seuil_alerte, date_peremption
//     Pas de colonne actif → filtrer isDeleted = 0
// ════════════════════════════════════════════════════════════════════════════
$alertesStock = $pdo->query("
    SELECT nom, forme, stock_actuel, seuil_alerte, date_peremption,
           CASE
               WHEN stock_actuel = 0             THEN 'rupture'
               WHEN stock_actuel <= seuil_alerte THEN 'alerte'
               WHEN date_peremption < CURDATE()  THEN 'perime'
               ELSE 'ok'
           END AS statut
    FROM produits_pharmacie
    WHERE isDeleted = 0
      AND (stock_actuel <= seuil_alerte OR date_peremption < CURDATE())
    ORDER BY stock_actuel ASC
    LIMIT 15
")->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 14. Performance percepteurs enrichie
// ════════════════════════════════════════════════════════════════════════════
$perfPercep = $pdo->prepare("
    SELECT u.nom, u.prenom,
           COUNT(r.id)                                                             AS nb_recus,
           COUNT(DISTINCT r.patient_id)                                            AS nb_patients,
           COALESCE(SUM(r.montant_encaisse), 0)                                   AS total_encaisse,
           SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END) AS nb_gratuits,
           SUM(CASE WHEN r.type_patient = 'normal' THEN r.montant_encaisse ELSE 0 END)   AS encaisse_payant
    FROM utilisateurs u
    LEFT JOIN recus r ON r.whodone = u.id AND r.isDeleted = 0
        AND DATE(r.whendone) BETWEEN :d AND :f
    WHERE u.role = 'percepteur' AND u.isDeleted = 0
    GROUP BY u.id, u.nom, u.prenom
    ORDER BY total_encaisse DESC
");
$perfPercep->execute([':d' => $filtreDebut, ':f' => $filtreFin]);
$perfPercep = $perfPercep->fetchAll();

// ── Matrices bivariées pour Chart.js ─────────────────────────────────────
$polesRef   = ['consultation', 'examen', 'pharmacie'];
$typesPatRef = ['normal', 'orphelin', 'acte_gratuit'];
$matrice = [];
foreach ($polesRef as $pole) {
    foreach ($typesPatRef as $tp) { $matrice[$pole][$tp] = 0; }
}
foreach ($bivarieData as $row) {
    if (isset($matrice[$row['type_recu']][$row['type_patient']])) {
        $matrice[$row['type_recu']][$row['type_patient']] = (int)$row['nb'];
    }
}

$matriceSexe = [];
foreach (['M','F'] as $s) {
    foreach ($typesPatRef as $tp) { $matriceSexe[$s][$tp] = 0; }
}
foreach ($bivarieSexeData as $row) {
    if (isset($matriceSexe[$row['sexe']][$row['type_patient']])) {
        $matriceSexe[$row['sexe']][$row['type_patient']] = (int)$row['nb'];
    }
}

// ── Labels ────────────────────────────────────────────────────────────────
$labelsTypePatient = [
    'normal'       => 'Normal payant',
    'orphelin'     => 'Orphelin',
    'acte_gratuit' => 'Acte gratuit',
];
$typeLabels = [
    'consultation' => 'Consultation',
    'examen'       => 'Examen',
    'pharmacie'    => 'Pharmacie',
];

// ── Arrays pour Chart.js ─────────────────────────────────────────────────
$labelsEvo          = array_map(fn($r) => date('d/m', strtotime($r['jour'])), $evolution);
$dataRecettesPayant = array_column($evolution, 'recettes_payant');
$dataCoutGratuit    = array_column($evolution, 'cout_gratuit');
$dataPatientsEvo    = array_column($evolution, 'nb_patients');
$dataRecettesEvo    = array_column($evolution, 'recettes');

$labels12m          = array_map(fn($r) => date('M y', strtotime($r['mois'].'-01')), $evo12m);
$data12mRecettes    = array_column($evo12m, 'recettes');
$data12mPatients    = array_column($evo12m, 'nb_patients');
$data12mGratuit     = array_column($evo12m, 'cout_gratuit');
$data12mRecus       = array_column($evo12m, 'nb_recus');

$labelsActes        = array_column($topActes,   'libelle');
$dataActesPay       = array_column($topActes,   'nb_payants');
$dataActesGrat      = array_column($topActes,   'nb_gratuits');

$labelsExam         = array_column($topExamens, 'libelle');
$dataExam           = array_column($topExamens, 'nb');
$dataExamGrat       = array_column($topExamens, 'nb_gratuits');
$dataExamLabo       = array_column($topExamens, 'total_labo');

$labelsProd         = array_map(fn($p) => $p['nom'].' ('.$p['forme'].')', $topProduits);
$dataProdPay        = array_column($topProduits, 'qte_payant');
$dataProdGrat       = array_column($topProduits, 'qte_gratuit');

$labelsType         = array_map(fn($t) => $labelsTypePatient[$t['type_patient']] ?? $t['type_patient'], $typePatients);
$dataType           = array_column($typePatients, 'nb');
$dataTypeTheori     = array_column($typePatients, 'theorique');
$dataTypeEnc        = array_column($typePatients, 'montant');

$labelsRep          = array_map(fn($r) => $typeLabels[$r['type_recu']] ?? ucfirst($r['type_recu']), $repartition);
$dataRep            = array_column($repartition, 'total');

$jsMNormal   = json_encode(array_map(fn($p) => $matrice[$p]['normal'],       $polesRef));
$jsMOrphelin = json_encode(array_map(fn($p) => $matrice[$p]['orphelin'],     $polesRef));
$jsMGratuit  = json_encode(array_map(fn($p) => $matrice[$p]['acte_gratuit'], $polesRef));
$jsPolesRef  = json_encode(['Consultation','Examen','Pharmacie']);

$jsSexeMNormal  = json_encode([$matriceSexe['M']['normal'],       $matriceSexe['F']['normal']]);
$jsSexeMOrph    = json_encode([$matriceSexe['M']['orphelin'],     $matriceSexe['F']['orphelin']]);
$jsSexeMGrat    = json_encode([$matriceSexe['M']['acte_gratuit'], $matriceSexe['F']['acte_gratuit']]);

// JSON encode tous les arrays
$jsLabelsEvo        = json_encode($labelsEvo);
$jsDataRecPay       = json_encode($dataRecettesPayant);
$jsDataCoutGrat     = json_encode($dataCoutGratuit);
$jsDataPatientsEvo  = json_encode($dataPatientsEvo);
$jsDataRecettesEvo  = json_encode($dataRecettesEvo);
$jsLabels12m        = json_encode($labels12m);
$jsData12mRec       = json_encode($data12mRecettes);
$jsData12mPat       = json_encode($data12mPatients);
$jsData12mGrat      = json_encode($data12mGratuit);
$jsData12mRecus     = json_encode($data12mRecus);
$jsLabelsActes      = json_encode($labelsActes);
$jsDataActesPay     = json_encode($dataActesPay);
$jsDataActesGrat    = json_encode($dataActesGrat);
$jsLabelsExam       = json_encode($labelsExam);
$jsDataExam         = json_encode($dataExam);
$jsDataExamGrat     = json_encode($dataExamGrat);
$jsDataExamLabo     = json_encode($dataExamLabo);
$jsLabelsProd       = json_encode($labelsProd);
$jsDataProdPay      = json_encode($dataProdPay);
$jsDataProdGrat     = json_encode($dataProdGrat);
$jsLabelsType       = json_encode($labelsType);
$jsDataType         = json_encode($dataType);
$jsDataTypeTheori   = json_encode($dataTypeTheori);
$jsDataTypeEnc      = json_encode($dataTypeEnc);
$jsLabelsRep        = json_encode($labelsRep);
$jsDataRep          = json_encode($dataRep);

include ROOT_PATH . '/templates/layouts/header.php';
?>

<div class="mt-4">

<!-- ── En-tête page ──────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center">
        <div class="rounded-circle d-flex align-items-center justify-content-center me-3"
             style="width:50px;height:50px;background:linear-gradient(135deg,#1565c0,#0d47a1);">
            <i class="bi bi-graph-up-arrow text-white fs-4"></i>
        </div>
        <div>
            <h4 class="mb-0 fw-bold" style="color:#1565c0;">Analytique Avancée</h4>
            <small class="text-muted">
                Analyse complète &middot;
                <?= date('d/m/Y', strtotime($filtreDebut)) ?> →
                <?= date('d/m/Y', strtotime($filtreFin)) ?>
            </small>
        </div>
    </div>
    <a href="<?= url('index.php?page=dashboard') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Tableau de bord
    </a>
</div>

<!-- ── Filtre période ─────────────────────────────────────────────────────── -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body py-2" style="background:linear-gradient(90deg,#e3f2fd,#f8f9fa);">
        <form method="GET" class="row g-2 align-items-end flex-wrap">
            <input type="hidden" name="page" value="analytics">
            <div class="col-auto">
                <label class="form-label mb-0 fw-semibold text-primary">
                    <i class="bi bi-calendar-range me-1"></i>Période :
                </label>
            </div>
            <div class="col-auto">
                <input type="date" class="form-control form-control-sm"
                       name="filtre_debut" value="<?= h($filtreDebut) ?>">
            </div>
            <div class="col-auto"><span class="text-muted">→</span></div>
            <div class="col-auto">
                <input type="date" class="form-control form-control-sm"
                       name="filtre_fin" value="<?= h($filtreFin) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm text-white" style="background:#1565c0;">
                    <i class="bi bi-search me-1"></i>Analyser
                </button>
            </div>
            <div class="col-auto d-flex gap-1">
                <?php foreach ([
                    ['Auj.',  date('Y-m-d'), date('Y-m-d')],
                    ['7j',    date('Y-m-d', strtotime('-7 days')), date('Y-m-d')],
                    ['30j',   date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
                    ['Mois',  date('Y-m-01'), date('Y-m-d')],
                    ['Année', date('Y-01-01'), date('Y-m-d')],
                ] as [$lbl,$deb,$fin]): ?>
                <a href="?page=analytics&filtre_debut=<?= $deb ?>&filtre_fin=<?= $fin ?>"
                   class="btn btn-light btn-sm border"><?= $lbl ?></a>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     KPI — Ligne 1 : globaux
     ════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-3">
    <?php foreach ([
        ['bi-people-fill',    '#1565c0','#dbeafe', number_format($kpi['nb_patients'],0,',',' '),        'Patients uniques'],
        ['bi-receipt',        '#2e7d32','#dcfce7', number_format($kpi['nb_recus'],0,',',' '),            'Reçus émis'],
        ['bi-cash-stack',     '#e65100','#fef3e2', number_format($kpi['total_encaisse'],0,',',' ').' F', 'Total encaissé'],
        ['bi-percent',        '#006064','#e0f7fa', $kpi['taux_encaissement'].' %',                      'Taux encaissement'],
    ] as [$ic,$col,$bg,$val,$lbl]): ?>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100"
             style="border-left:4px solid <?= $col ?> !important;">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:48px;height:48px;background:<?= $bg ?>;color:<?= $col ?>;min-width:48px;">
                    <i class="bi <?= $ic ?> fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5" style="color:<?= $col ?>"><?= $val ?></div>
                    <div class="text-muted small"><?= $lbl ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- KPI — Ligne 2 : payant / gratuit / sexe -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #2e7d32 !important;">
            <div class="card-body">
                <div class="small text-muted mb-1">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>Actes payants
                </div>
                <div class="fw-bold fs-5 text-success">
                    <?= number_format($kpi['nb_payants'],0,',',' ') ?> reçus
                </div>
                <div class="small text-muted">
                    <?= number_format($kpi['total_encaisse'],0,',',' ') ?> F encaissés
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #c62828 !important;">
            <div class="card-body">
                <div class="small text-muted mb-1">
                    <i class="bi bi-heart-fill text-danger me-1"></i>Gratuits + Orphelins
                </div>
                <div class="fw-bold fs-5 text-danger">
                    <?= number_format($kpi['nb_gratuits'],0,',',' ') ?> reçus
                </div>
                <div class="small text-muted">
                    Coût supporté : <?= number_format($kpi['cout_gratuits'],0,',',' ') ?> F
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #1565c0 !important;">
            <div class="card-body">
                <div class="small text-muted mb-1">
                    <i class="bi bi-gender-male me-1"></i>Patients Masculins
                </div>
                <div class="fw-bold fs-5" style="color:#1565c0">
                    <?= number_format($kpi['nb_patients_M'],0,',',' ') ?>
                </div>
                <div class="small text-muted">
                    <?= number_format($sexeRows['M']['encaisse'] ?? 0,0,',',' ') ?> F encaissés
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #c2185b !important;">
            <div class="card-body">
                <div class="small text-muted mb-1">
                    <i class="bi bi-gender-female me-1"></i>Patients Féminins
                </div>
                <div class="fw-bold fs-5" style="color:#c2185b">
                    <?= number_format($kpi['nb_patients_F'],0,',',' ') ?>
                </div>
                <div class="small text-muted">
                    <?= number_format($sexeRows['F']['encaisse'] ?? 0,0,',',' ') ?> F encaissés
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     Tableau synthèse Gratuit vs Payant
     ════════════════════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header border-0"
         style="background:linear-gradient(90deg,#37474f,#546e7a);color:#fff;">
        <h6 class="mb-0">
            <i class="bi bi-table me-2"></i>Synthèse Gratuit vs Payant — Coûts &amp; Manque à gagner
        </h6>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Catégorie</th>
                    <th class="text-center">Nb reçus</th>
                    <th class="text-end">Coût théorique</th>
                    <th class="text-end">Encaissé</th>
                    <th class="text-end">Manque à gagner</th>
                    <th style="min-width:120px;">Répartition</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $totalNb   = array_sum(array_column($gratPayData, 'nb'));
            $gpColors  = ['normal'=>'#2e7d32','acte_gratuit'=>'#e65100','orphelin'=>'#7b1fa2'];
            $gpBg      = ['normal'=>'#dcfce7','acte_gratuit'=>'#fef3e2','orphelin'=>'#f3e8ff'];
            foreach ($gratPayData as $gp):
                $pct  = $totalNb > 0 ? round($gp['nb'] / $totalNb * 100) : 0;
                $col  = $gpColors[$gp['type_patient']] ?? '#555';
                $bg   = $gpBg[$gp['type_patient']]    ?? '#eee';
                $lbl  = $labelsTypePatient[$gp['type_patient']] ?? $gp['type_patient'];
            ?>
            <tr>
                <td>
                    <span style="background:<?= $bg ?>;color:<?= $col ?>;
                                 padding:3px 10px;border-radius:4px;font-weight:bold;">
                        <?= $lbl ?>
                    </span>
                </td>
                <td class="text-center fw-bold"><?= number_format($gp['nb'],0,',',' ') ?></td>
                <td class="text-end"><?= number_format($gp['cout_theorique'],0,',',' ') ?> F</td>
                <td class="text-end fw-bold" style="color:#2e7d32;">
                    <?= number_format($gp['montant_encaisse'],0,',',' ') ?> F
                </td>
                <td class="text-end" style="color:<?= $gp['manque_a_gagner']>0?'#c62828':'#2e7d32' ?>">
                    <?= $gp['manque_a_gagner'] > 0
                        ? '-'.number_format($gp['manque_a_gagner'],0,',',' ').' F'
                        : '—' ?>
                </td>
                <td>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $col ?>"></div>
                    </div>
                    <small class="text-muted"><?= $pct ?>%</small>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-dark">
                <tr>
                    <td><strong>TOTAL</strong></td>
                    <td class="text-center"><strong><?= number_format($kpi['nb_recus'],0,',',' ') ?></strong></td>
                    <td class="text-end"><strong><?= number_format($kpi['total_theorique'],0,',',' ') ?> F</strong></td>
                    <td class="text-end"><strong><?= number_format($kpi['total_encaisse'],0,',',' ') ?> F</strong></td>
                    <td class="text-end text-danger">
                        <strong>-<?= number_format($kpi['total_theorique']-$kpi['total_encaisse'],0,',',' ') ?> F</strong>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     LIGNE A : Évolution journalière payant/gratuit + Répartition pôles
     ════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0"
                 style="background:linear-gradient(90deg,#1565c0,#1976d2);color:#fff;">
                <h6 class="mb-0">
                    <i class="bi bi-bar-chart-line me-2"></i>
                    Évolution journalière — Encaissé Payant vs Coût Gratuits
                </h6>
            </div>
            <div class="card-body"><canvas id="chartEvoPayGrat" height="170"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0"
                 style="background:linear-gradient(90deg,#2e7d32,#388e3c);color:#fff;">
                <h6 class="mb-0"><i class="bi bi-pie-chart-fill me-2"></i>Revenus par pôle</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartRepartition" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     LIGNE B : Tendance 12 mois glissants
     ════════════════════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header border-0"
         style="background:linear-gradient(90deg,#006064,#00838f);color:#fff;">
        <h6 class="mb-0">
            <i class="bi bi-graph-up me-2"></i>
            Tendance 12 mois — Recettes · Patients · Coût gratuité
        </h6>
    </div>
    <div class="card-body"><canvas id="chart12mois" height="110"></canvas></div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     LIGNE C : Analyses bivariées
     ════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0"
                 style="background:linear-gradient(90deg,#e65100,#f4511e);color:#fff;">
                <h6 class="mb-0">
                    <i class="bi bi-grid-3x3-gap me-2"></i>
                    Bivarié — Pôle × Catégorie patient
                </h6>
            </div>
            <div class="card-body"><canvas id="chartBivariePole" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0"
                 style="background:linear-gradient(90deg,#6a1b9a,#8e24aa);color:#fff;">
                <h6 class="mb-0">
                    <i class="bi bi-gender-ambiguous me-2"></i>
                    Bivarié — Sexe × Catégorie patient
                </h6>
            </div>
            <div class="card-body"><canvas id="chartBivarieSexe" height="200"></canvas></div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     LIGNE D : Top Actes empilé + Top Examens avec labo
     ════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0"
                 style="background:linear-gradient(90deg,#e65100,#f4511e);color:#fff;">
                <h6 class="mb-0">
                    <i class="bi bi-stethoscope me-2"></i>
                    Top Actes — Payants vs Gratuits/Orphelins
                </h6>
            </div>
            <div class="card-body"><canvas id="chartActes" height="240"></canvas></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0"
                 style="background:linear-gradient(90deg,#006064,#00838f);color:#fff;">
                <h6 class="mb-0">
                    <i class="bi bi-clipboard2-pulse me-2"></i>
                    Top Examens — Prescriptions &amp; Revenu Labo
                </h6>
            </div>
            <div class="card-body"><canvas id="chartExamens" height="240"></canvas></div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     LIGNE E : Top Produits empilé + Types patients (donut + bar comparatif)
     ════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0"
                 style="background:linear-gradient(90deg,#6a1b9a,#8e24aa);color:#fff;">
                <h6 class="mb-0">
                    <i class="bi bi-capsule me-2"></i>
                    Top Produits — Qté payant vs gratuit
                </h6>
            </div>
            <div class="card-body"><canvas id="chartProduits" height="210"></canvas></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0"
                 style="background:linear-gradient(90deg,#d32f2f,#e53935);color:#fff;">
                <h6 class="mb-0">
                    <i class="bi bi-person-lines-fill me-2"></i>
                    Types patients — Nb &amp; Coûts comparés
                </h6>
            </div>
            <div class="card-body"><canvas id="chartTypePatients" height="210"></canvas></div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     LIGNE F : Tables détaillées
     ════════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <!-- Détail Actes -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0 text-warning-emphasis">
                    <i class="bi bi-list-ol me-2"></i>Détail Actes Médicaux
                </h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Acte</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Payants</th>
                            <th class="text-center">Gratuits</th>
                            <th class="text-end">Tarif</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($topActes): foreach ($topActes as $i => $a): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= $i+1 ?></span></td>
                            <td class="fw-semibold small"><?= h($a['libelle']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?= $a['nb_utilisations'] ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success"><?= $a['nb_payants'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($a['nb_gratuits'] > 0): ?>
                                <span class="badge" style="background:#c62828;">
                                    <?= $a['nb_gratuits'] ?>
                                </span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="text-end text-muted small">
                                <?= number_format($a['tarif'],0,',',' ') ?> F
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Aucune donnée</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Détail Examens -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0" style="color:#006064;">
                    <i class="bi bi-clipboard2-pulse me-2"></i>Détail Examens
                </h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Examen</th>
                            <th class="text-center">Nb</th>
                            <th class="text-end">Revenu total</th>
                            <th class="text-end">Part labo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($topExamens): foreach ($topExamens as $i => $e): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= $i+1 ?></span></td>
                            <td class="fw-semibold small"><?= h($e['libelle']) ?></td>
                            <td class="text-center">
                                <span class="badge" style="background:#006064;">
                                    <?= $e['nb'] ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold" style="color:#2e7d32;">
                                <?= number_format($e['total_revenu'],0,',',' ') ?> F
                            </td>
                            <td class="text-end text-muted small">
                                <?= number_format($e['total_labo'],0,',',' ') ?> F
                                <span class="text-muted">(<?= $e['pourcentage_labo'] ?>%)</span>
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

<!-- Détail Produits -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0" style="color:#6a1b9a;">
            <i class="bi bi-capsule me-2"></i>Détail Produits Pharmacie
        </h6>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>Produit</th><th>Forme</th>
                    <th class="text-center">Qté totale</th>
                    <th class="text-center">Qté payant</th>
                    <th class="text-center">Qté gratuit</th>
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
                        <span class="badge bg-primary"><?= $p['total_qte'] ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success"><?= $p['qte_payant'] ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($p['qte_gratuit'] > 0): ?>
                        <span class="badge" style="background:#c62828;">
                            <?= $p['qte_gratuit'] ?>
                        </span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-end fw-bold" style="color:#2e7d32;">
                        <?= number_format($p['total_revenu'],0,',',' ') ?> F
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-center text-muted py-3">Aucune donnée</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     LIGNE G : Alertes stock pharmacie
     ════════════════════════════════════════════════════════════════════════ -->
<?php if (!empty($alertesStock)): ?>
<div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #c62828 !important;">
    <div class="card-header border-0"
         style="background:linear-gradient(90deg,#b71c1c,#c62828);color:#fff;">
        <h6 class="mb-0">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Alertes Stock Pharmacie (<?= count($alertesStock) ?> produit<?= count($alertesStock)>1?'s':'' ?>)
        </h6>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="table-light">
                <tr>
                    <th>Produit</th><th>Forme</th>
                    <th class="text-center">Stock actuel</th>
                    <th class="text-center">Seuil alerte</th>
                    <th>Péremption</th>
                    <th class="text-center">Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alertesStock as $al):
                $sBadge = match($al['statut']) {
                    'rupture' => ['Rupture totale', '#fff', '#b71c1c'],
                    'alerte'  => ['Stock bas',      '#fff', '#e65100'],
                    'perime'  => ['Périmé',         '#fff', '#6a1b9a'],
                    default   => ['OK',              '#fff', '#2e7d32'],
                };
                $perempColor = ($al['date_peremption'] && $al['date_peremption'] < date('Y-m-d'))
                    ? 'color:#b71c1c;font-weight:bold;' : '';
            ?>
            <tr>
                <td class="fw-semibold"><?= h($al['nom']) ?></td>
                <td><small class="text-muted"><?= h($al['forme']) ?></small></td>
                <td class="text-center">
                    <span class="fw-bold" style="color:<?= $al['stock_actuel']==0?'#b71c1c':'#e65100' ?>">
                        <?= $al['stock_actuel'] ?>
                    </span>
                </td>
                <td class="text-center text-muted"><?= $al['seuil_alerte'] ?></td>
                <td style="<?= $perempColor ?>font-size:0.85rem;">
                    <?= $al['date_peremption']
                        ? date('d/m/Y', strtotime($al['date_peremption']))
                        : '—' ?>
                </td>
                <td class="text-center">
                    <span style="background:<?= $sBadge[2] ?>;color:<?= $sBadge[1] ?>;
                                 padding:2px 8px;border-radius:4px;font-size:0.8rem;font-weight:bold;">
                        <?= $sBadge[0] ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════════════════
     Performance Percepteurs enrichie
     ════════════════════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header border-0"
         style="background:linear-gradient(90deg,#37474f,#546e7a);color:#fff;">
        <h6 class="mb-0">
            <i class="bi bi-people-fill me-2"></i>Performance Percepteurs sur la période
        </h6>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Percepteur</th>
                    <th class="text-center">Reçus émis</th>
                    <th class="text-center">Patients</th>
                    <th class="text-center">Gratuits</th>
                    <th class="text-end">Encaissé payant</th>
                    <th class="text-end">Total encaissé</th>
                    <th style="min-width:120px;">Performance</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $maxEnc = max(1, ...array_column($perfPercep, 'total_encaisse'));
            foreach ($perfPercep as $p):
                $pct = round($p['total_encaisse'] / $maxEnc * 100);
            ?>
            <tr>
                <td>
                    <i class="bi bi-person-badge me-1" style="color:#1565c0;"></i>
                    <strong><?= h($p['nom'].' '.$p['prenom']) ?></strong>
                </td>
                <td class="text-center">
                    <span class="badge bg-<?= $p['nb_recus']>0?'primary':'secondary' ?>">
                        <?= $p['nb_recus'] ?>
                    </span>
                </td>
                <td class="text-center">
                    <span class="badge bg-<?= $p['nb_patients']>0?'info':'secondary' ?> text-dark">
                        <?= $p['nb_patients'] ?>
                    </span>
                </td>
                <td class="text-center">
                    <?php if ($p['nb_gratuits'] > 0): ?>
                    <span class="badge" style="background:#c62828;">
                        <?= $p['nb_gratuits'] ?>
                    </span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="text-end" style="color:#006064;">
                    <?= number_format($p['encaisse_payant'],0,',',' ') ?> F
                </td>
                <td class="text-end fw-bold" style="color:#2e7d32;">
                    <?= number_format($p['total_encaisse'],0,',',' ') ?> F
                </td>
                <td>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar" role="progressbar"
                             style="width:<?= $pct ?>%;background:linear-gradient(90deg,#1565c0,#42a5f5);"
                             aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
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
$extraJs = <<<HEREDOC
<script>
const FMT = v => new Intl.NumberFormat('fr-FR').format(v) + ' F';
const PALETTE  = ['#1565c0','#2e7d32','#e65100','#006064','#6a1b9a','#d32f2f','#f57f17','#00695c','#37474f','#880e4f'];
const C_PAY    = 'rgba(46,125,50,0.8)';
const C_GRAT   = 'rgba(198,40,40,0.75)';
const C_ORPH   = 'rgba(106,27,154,0.75)';
const C_LABO   = 'rgba(0,96,100,0.6)';
const C_12M    = '#1565c0';

// ── 1. Évolution journalière : Encaissé payant (bar) + Coût gratuit (line)
new Chart(document.getElementById('chartEvoPayGrat'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsEvo},
        datasets: [
            {
                type: 'bar',
                label: 'Encaissé payant (F)',
                data: {$jsDataRecPay},
                backgroundColor: C_PAY,
                borderColor: '#2e7d32',
                borderRadius: 5,
                yAxisID: 'yRev'
            },
            {
                type: 'line',
                label: 'Coût gratuité (F)',
                data: {$jsDataCoutGrat},
                borderColor: '#c62828',
                backgroundColor: 'rgba(198,40,40,0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#c62828',
                yAxisID: 'yRev'
            },
            {
                type: 'line',
                label: 'Patients',
                data: {$jsDataPatientsEvo},
                borderColor: '#1565c0',
                backgroundColor: 'transparent',
                tension: 0.3,
                pointRadius: 3,
                borderDash: [5,3],
                yAxisID: 'yPat'
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
                        if (ctx.dataset.yAxisID === 'yRev') return ctx.dataset.label + ' : ' + FMT(ctx.raw);
                        return 'Patients : ' + ctx.raw;
                    }
                }
            }
        },
        scales: {
            yRev: { type:'linear', position:'left',  beginAtZero:true,
                    ticks:{ callback: v => FMT(v) }, title:{display:true,text:'Montants (F)'} },
            yPat: { type:'linear', position:'right', beginAtZero:true,
                    grid:{drawOnChartArea:false}, ticks:{stepSize:1}, title:{display:true,text:'Patients'} }
        }
    }
});

// ── 2. Répartition revenus par pôle (doughnut)
(function(){
    const labels = {$jsLabelsRep};
    const data   = {$jsDataRep};
    if (!data.length) {
        document.getElementById('chartRepartition').closest('.card-body').innerHTML =
            '<p class="text-muted text-center py-4">Aucune donnée.</p>';
        return;
    }
    new Chart(document.getElementById('chartRepartition'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: PALETTE.slice(0,labels.length), borderWidth:2 }] },
        options: {
            responsive: true,
            plugins: {
                legend: { position:'bottom' },
                tooltip: { callbacks: { label: ctx => ctx.label + ' : ' + FMT(ctx.raw) } }
            }
        }
    });
})();

// ── 3. Tendance 12 mois (ligne multi-axes)
new Chart(document.getElementById('chart12mois'), {
    type: 'line',
    data: {
        labels: {$jsLabels12m},
        datasets: [
            {
                label: 'Recettes (F)',
                data: {$jsData12mRec},
                borderColor: C_12M,
                backgroundColor: 'rgba(21,101,192,0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: C_12M,
                yAxisID: 'yRev'
            },
            {
                label: 'Coût gratuité (F)',
                data: {$jsData12mGrat},
                borderColor: '#c62828',
                backgroundColor: 'transparent',
                tension: 0.4,
                borderDash: [6,3],
                pointRadius: 4,
                pointBackgroundColor: '#c62828',
                yAxisID: 'yRev'
            },
            {
                label: 'Patients',
                data: {$jsData12mPat},
                borderColor: '#2e7d32',
                backgroundColor: 'transparent',
                tension: 0.4,
                borderDash: [3,3],
                pointRadius: 4,
                pointBackgroundColor: '#2e7d32',
                yAxisID: 'yPat'
            },
            {
                label: 'Nb reçus',
                data: {$jsData12mRecus},
                borderColor: '#e65100',
                backgroundColor: 'transparent',
                tension: 0.3,
                pointRadius: 3,
                pointBackgroundColor: '#e65100',
                yAxisID: 'yPat'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode:'index' },
        plugins: { legend:{ position:'top' } },
        scales: {
            yRev: { type:'linear', position:'left',  beginAtZero:true,
                    ticks:{ callback: v => FMT(v) }, title:{display:true,text:'Montants (F)'} },
            yPat: { type:'linear', position:'right', beginAtZero:true,
                    grid:{drawOnChartArea:false}, title:{display:true,text:'Patients / Reçus'} }
        }
    }
});

// ── 4. Bivarié pôle × type_patient (grouped bar)
new Chart(document.getElementById('chartBivariePole'), {
    type: 'bar',
    data: {
        labels: {$jsPolesRef},
        datasets: [
            { label:'Normal payant', data: {$jsMNormal},   backgroundColor: C_PAY  },
            { label:'Orphelin',      data: {$jsMOrphelin}, backgroundColor: C_ORPH },
            { label:'Acte gratuit',  data: {$jsMGratuit},  backgroundColor: C_GRAT }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend:{ position:'top' } },
        scales: {
            x: { stacked:false },
            y: { beginAtZero:true, ticks:{ stepSize:1 }, title:{display:true,text:'Nombre de reçus'} }
        }
    }
});

// ── 5. Bivarié sexe × type_patient (grouped bar)
new Chart(document.getElementById('chartBivarieSexe'), {
    type: 'bar',
    data: {
        labels: ['Masculin (M)', 'Féminin (F)'],
        datasets: [
            { label:'Normal payant', data: {$jsSexeMNormal}, backgroundColor: C_PAY  },
            { label:'Orphelin',      data: {$jsSexeMOrph},   backgroundColor: C_ORPH },
            { label:'Acte gratuit',  data: {$jsSexeMGrat},   backgroundColor: C_GRAT }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend:{ position:'top' } },
        scales: {
            y: { beginAtZero:true, ticks:{ stepSize:1 }, title:{display:true,text:'Nombre de reçus'} }
        }
    }
});

// ── 6. Top Actes empilé payant/gratuit (horizontal stacked bar)
new Chart(document.getElementById('chartActes'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsActes},
        datasets: [
            { label:'Payants',        data:{$jsDataActesPay},  backgroundColor: C_PAY,  borderRadius:4 },
            { label:'Gratuits/Orph.', data:{$jsDataActesGrat}, backgroundColor: C_GRAT, borderRadius:4 }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend:{ position:'top' } },
        scales: {
            x: { stacked:true, beginAtZero:true, ticks:{ stepSize:1 } },
            y: { stacked:true }
        }
    }
});

// ── 7. Top Examens avec part labo (grouped horizontal)
new Chart(document.getElementById('chartExamens'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsExam},
        datasets: [
            { label:'Prescriptions', data:{$jsDataExam},     backgroundColor:'rgba(0,96,100,0.75)', borderRadius:4 },
            { label:'Dont gratuits', data:{$jsDataExamGrat}, backgroundColor: C_GRAT,               borderRadius:4 }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend:{ position:'top' } },
        scales: {
            x: { beginAtZero:true, ticks:{ stepSize:1 } }
        }
    }
});

// ── 8. Top Produits empilé payant/gratuit (horizontal stacked)
new Chart(document.getElementById('chartProduits'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsProd},
        datasets: [
            { label:'Qté payant',  data:{$jsDataProdPay},  backgroundColor: C_PAY,  borderRadius:4 },
            { label:'Qté gratuit', data:{$jsDataProdGrat}, backgroundColor: C_GRAT, borderRadius:4 }
        ]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend:{ position:'top' } },
        scales: {
            x: { stacked:true, beginAtZero:true },
            y: { stacked:true }
        }
    }
});

// ── 9. Types patients — double bar (nb + coût théorique)
new Chart(document.getElementById('chartTypePatients'), {
    type: 'bar',
    data: {
        labels: {$jsLabelsType},
        datasets: [
            {
                label: 'Nb reçus',
                data: {$jsDataType},
                backgroundColor: PALETTE.slice(0,3).map(c=>c+'bb'),
                yAxisID: 'yNb',
                borderRadius: 5
            },
            {
                type: 'line',
                label: 'Coût théorique (F)',
                data: {$jsDataTypeTheori},
                borderColor: '#e65100',
                backgroundColor: 'transparent',
                pointRadius: 6,
                pointBackgroundColor: '#e65100',
                yAxisID: 'yMont'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend:{ position:'top' } },
        scales: {
            yNb:   { type:'linear', position:'left',  beginAtZero:true,
                     ticks:{stepSize:1}, title:{display:true,text:'Nb reçus'} },
            yMont: { type:'linear', position:'right', beginAtZero:true,
                     grid:{drawOnChartArea:false},
                     ticks:{ callback: v => FMT(v) }, title:{display:true,text:'Coût (F)'} }
        }
    }
});
</script>
HEREDOC;

include ROOT_PATH . '/templates/layouts/footer.php';
?>
