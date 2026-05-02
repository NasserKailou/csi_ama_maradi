<?php
/**
 * Tableau de Bord Analytique Avancé – Réservé Administrateur
 * 
 * Vision 360° : Finance | Opérationnel | RH | Stock | Qualité | Démographie
 * 100% aligné sur le schéma réel directaid (vérifié dump SQL).
 */
requireRole('admin');
$pdo       = Database::getInstance();
$pageTitle = 'Analytique Avancée';

/**
 * Formate un nombre en sécurité (gère NULL, chaînes vides, etc.)
 */
function fmt($val, $decimales = 0) {
    return number_format((float)($val ?? 0), $decimales, ',', ' ');
}

// ── Période de filtre ─────────────────────────────────────────────────────
$filtreDebut = $_GET['filtre_debut'] ?? date('Y-m-01');
$filtreFin   = $_GET['filtre_fin']   ?? date('Y-m-d');

$nbJours        = (strtotime($filtreFin) - strtotime($filtreDebut)) / 86400 + 1;
$periodePrecDeb = date('Y-m-d', strtotime($filtreDebut.' -'.$nbJours.' days'));
$periodePrecFin = date('Y-m-d', strtotime($filtreDebut.' -1 day'));

// ════════════════════════════════════════════════════════════════════════════
// 1. KPIs globaux + comparaison période précédente
// ════════════════════════════════════════════════════════════════════════════
$sqlKpi = "
    SELECT
        COUNT(DISTINCT patient_id)                AS nb_patients,
        COUNT(*)                                  AS nb_recus,
        COALESCE(SUM(montant_encaisse),0)         AS total_encaisse,
        COALESCE(SUM(montant_total),0)            AS total_theorique,
        COALESCE(SUM(montant_total - montant_encaisse),0) AS total_subventionne,
        COALESCE(SUM(CASE WHEN type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END),0) AS nb_gratuits,
        COALESCE(AVG(NULLIF(montant_encaisse,0)),0) AS panier_moyen
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
";
$stmt = $pdo->prepare($sqlKpi);
$stmt->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$kpi = $stmt->fetch() ?: ['nb_patients'=>0,'nb_recus'=>0,'total_encaisse'=>0,'total_theorique'=>0,'total_subventionne'=>0,'nb_gratuits'=>0,'panier_moyen'=>0];

$stmt = $pdo->prepare($sqlKpi);
$stmt->execute([':d'=>$periodePrecDeb, ':f'=>$periodePrecFin]);
$kpiPrec = $stmt->fetch() ?: ['nb_patients'=>0,'nb_recus'=>0,'total_encaisse'=>0,'total_theorique'=>0,'total_subventionne'=>0,'nb_gratuits'=>0,'panier_moyen'=>0];

function variation($a, $p) {
    $a = (float)($a ?? 0);
    $p = (float)($p ?? 0);
    if ($p == 0) return $a > 0 ? 100 : 0;
    return round((($a - $p) / $p) * 100, 1);
}
$varPatients = variation($kpi['nb_patients'],    $kpiPrec['nb_patients']);
$varRecus    = variation($kpi['nb_recus'],       $kpiPrec['nb_recus']);
$varEncaisse = variation($kpi['total_encaisse'], $kpiPrec['total_encaisse']);
$varGratuits = variation($kpi['nb_gratuits'],    $kpiPrec['nb_gratuits']);

$tauxSubvention = $kpi['total_theorique'] > 0
    ? round(($kpi['total_subventionne'] / $kpi['total_theorique']) * 100, 1) : 0;

// ════════════════════════════════════════════════════════════════════════════
// 2. Top Actes médicaux
// ════════════════════════════════════════════════════════════════════════════
$topActes = $pdo->prepare("
    SELECT a.libelle, a.tarif,
           COUNT(lc.id) AS nb_utilisations,
           COALESCE(SUM(CASE WHEN lc.est_gratuit=1 THEN 0 ELSE (lc.tarif + lc.tarif_carnet) END),0) AS revenu_genere,
           COALESCE(SUM(CASE WHEN lc.est_gratuit=1 THEN 1 ELSE 0 END),0) AS nb_gratuits_acte,
           COALESCE(SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END),0) AS nb_orphelins,
           COALESCE(SUM(lc.avec_carnet),0) AS nb_avec_carnet
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
// 3. Top Produits pharmacie
// ════════════════════════════════════════════════════════════════════════════
$topProduits = $pdo->prepare("
    SELECT lp.nom, lp.forme,
           COALESCE(SUM(lp.quantite),0)     AS total_qte,
           COALESCE(SUM(lp.total_ligne),0)  AS total_revenu,
           COALESCE(AVG(lp.prix_unitaire),0) AS prix_moyen
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
// 4. Répartition par type de patient
// ════════════════════════════════════════════════════════════════════════════
$typePatients = $pdo->prepare("
    SELECT type_patient, COUNT(*) AS nb,
           COALESCE(SUM(montant_encaisse),0) AS montant,
           COALESCE(SUM(montant_total),0)    AS theorique
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_patient
    ORDER BY nb DESC
");
$typePatients->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$typePatients = $typePatients->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 5. Évolution journalière + cumul
// ════════════════════════════════════════════════════════════════════════════
$evolution = $pdo->prepare("
    SELECT DATE(whendone) AS jour,
           COUNT(DISTINCT patient_id) AS nb_patients,
           COUNT(*) AS nb_recus,
           COALESCE(SUM(montant_encaisse),0) AS recettes,
           COALESCE(SUM(montant_total - montant_encaisse),0) AS subventionne
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY DATE(whendone)
    ORDER BY jour ASC
");
$evolution->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$evolution = $evolution->fetchAll();

$cumul = 0;
foreach ($evolution as &$e) { $cumul += (float)$e['recettes']; $e['cumul'] = $cumul; }
unset($e);

// ════════════════════════════════════════════════════════════════════════════
// 6. Top Examens prescrits
// ════════════════════════════════════════════════════════════════════════════
$topExamens = $pdo->prepare("
    SELECT e.libelle, COUNT(le.id) AS nb,
           COALESCE(SUM(le.cout_total),0)  AS total_revenu,
           e.pourcentage_labo,
           COALESCE(SUM(le.montant_labo),0) AS part_labo
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

$totalLabo = 0;
foreach ($topExamens as $ex) $totalLabo += (float)($ex['part_labo'] ?? 0);

// ════════════════════════════════════════════════════════════════════════════
// 7. Performance percepteurs
// ════════════════════════════════════════════════════════════════════════════
$perfPercep = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, u.est_actif,
           COUNT(r.id)                         AS nb_recus,
           COUNT(DISTINCT r.patient_id)        AS nb_patients,
           COALESCE(SUM(r.montant_encaisse),0) AS total_encaisse,
           COALESCE(AVG(NULLIF(r.montant_encaisse,0)),0) AS panier_moyen,
           COALESCE(SUM(CASE WHEN r.type_patient IN ('orphelin','acte_gratuit') THEN 1 ELSE 0 END),0) AS nb_gratuits,
           COALESCE(SUM(CASE WHEN r.type_recu='consultation' THEN 1 ELSE 0 END),0) AS nb_consult,
           COALESCE(SUM(CASE WHEN r.type_recu='examen'       THEN 1 ELSE 0 END),0) AS nb_exam,
           COALESCE(SUM(CASE WHEN r.type_recu='pharmacie'    THEN 1 ELSE 0 END),0) AS nb_pharma
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
// 8. Répartition revenus par pôle
// ════════════════════════════════════════════════════════════════════════════
$repartition = $pdo->prepare("
    SELECT type_recu, COUNT(*) AS nb,
           COALESCE(SUM(montant_encaisse),0) AS total
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_recu
");
$repartition->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$repartition = $repartition->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 9. ⚠ ALERTES STOCK PHARMACIE
// ════════════════════════════════════════════════════════════════════════════
$stockAlertes = $pdo->query("
    SELECT id, nom, forme, stock_actuel, seuil_alerte, prix_unitaire, date_peremption,
           CASE
               WHEN stock_actuel <= 0 THEN 'RUPTURE'
               WHEN date_peremption IS NOT NULL AND date_peremption <= CURDATE() THEN 'PERIME'
               WHEN date_peremption IS NOT NULL AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 'PEREMPTION'
               WHEN stock_actuel <= seuil_alerte THEN 'FAIBLE'
               ELSE 'OK'
           END AS statut
    FROM produits_pharmacie
    WHERE isDeleted=0
      AND (
            stock_actuel <= seuil_alerte
            OR (date_peremption IS NOT NULL AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 60 DAY))
          )
    ORDER BY 
        CASE 
            WHEN stock_actuel <= 0 THEN 1
            WHEN date_peremption IS NOT NULL AND date_peremption <= CURDATE() THEN 2
            WHEN date_peremption IS NOT NULL AND date_peremption <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 3
            WHEN stock_actuel <= seuil_alerte THEN 4
            ELSE 5
        END,
        nom
    LIMIT 30
")->fetchAll();

$nbRupture=0; $nbFaible=0; $nbPeremption=0; $nbPerime=0;
foreach ($stockAlertes as $s) {
    if      ($s['statut']==='RUPTURE')    $nbRupture++;
    elseif  ($s['statut']==='PERIME')     $nbPerime++;
    elseif  ($s['statut']==='PEREMPTION') $nbPeremption++;
    elseif  ($s['statut']==='FAIBLE')     $nbFaible++;
}

$valeurStock = (float)$pdo->query("
    SELECT COALESCE(SUM(stock_actuel * prix_unitaire),0)
    FROM produits_pharmacie WHERE isDeleted=0
")->fetchColumn();

$stmtRot = $pdo->prepare("
    SELECT COALESCE(SUM(lp.quantite),0)
    FROM lignes_pharmacie lp
    JOIN recus r ON r.id=lp.recu_id AND r.isDeleted=0
    WHERE lp.isDeleted=0 AND DATE(r.whendone) BETWEEN ? AND ?
");
$stmtRot->execute([$filtreDebut, $filtreFin]);
$qteVenduePeriode = (int)$stmtRot->fetchColumn();

$stockTotalActuel = (int)$pdo->query("
    SELECT COALESCE(SUM(stock_actuel),0) FROM produits_pharmacie WHERE isDeleted=0
")->fetchColumn();

// ════════════════════════════════════════════════════════════════════════════
// 10. Heures de pic d'activité
// ════════════════════════════════════════════════════════════════════════════
$piesHeures = $pdo->prepare("
    SELECT HOUR(whendone) AS heure, COUNT(*) AS nb
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY HOUR(whendone)
    ORDER BY heure
");
$piesHeures->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$heuresFull = array_fill(0, 24, 0);
foreach ($piesHeures->fetchAll() as $h) $heuresFull[(int)$h['heure']] = (int)$h['nb'];

// ════════════════════════════════════════════════════════════════════════════
// 11. Activité par jour de la semaine
// ════════════════════════════════════════════════════════════════════════════
$jrSemaine = $pdo->prepare("
    SELECT DAYOFWEEK(whendone) AS jr, COUNT(*) AS nb,
           COALESCE(SUM(montant_encaisse),0) AS recette
    FROM recus
    WHERE isDeleted=0 AND DATE(whendone) BETWEEN :d AND :f
    GROUP BY DAYOFWEEK(whendone)
    ORDER BY jr
");
$jrSemaine->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$mapJrSem = [1=>'Dim',2=>'Lun',3=>'Mar',4=>'Mer',5=>'Jeu',6=>'Ven',7=>'Sam'];
$jrSemFull = array_fill_keys(array_values($mapJrSem), ['nb'=>0,'recette'=>0]);
foreach ($jrSemaine->fetchAll() as $j) {
    $jrSemFull[$mapJrSem[(int)$j['jr']]] = ['nb'=>(int)$j['nb'], 'recette'=>(float)$j['recette']];
}

// ════════════════════════════════════════════════════════════════════════════
// 12. Démographie patients
// ════════════════════════════════════════════════════════════════════════════
$demoSexe = $pdo->prepare("
    SELECT p.sexe, COUNT(DISTINCT r.patient_id) AS nb
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.isDeleted=0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY p.sexe
");
$demoSexe->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$demoSexe = $demoSexe->fetchAll();

$demoAge = $pdo->prepare("
    SELECT 
        CASE
            WHEN p.age < 1 THEN '0-1 an'
            WHEN p.age BETWEEN 1 AND 5 THEN '1-5 ans'
            WHEN p.age BETWEEN 6 AND 17 THEN '6-17 ans'
            WHEN p.age BETWEEN 18 AND 35 THEN '18-35 ans'
            WHEN p.age BETWEEN 36 AND 60 THEN '36-60 ans'
            ELSE '60+ ans'
        END AS tranche,
        COUNT(DISTINCT r.patient_id) AS nb
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.isDeleted=0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY tranche
    ORDER BY FIELD(tranche, '0-1 an','1-5 ans','6-17 ans','18-35 ans','36-60 ans','60+ ans')
");
$demoAge->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$demoAge = $demoAge->fetchAll();

$stmtOrph = $pdo->prepare("
    SELECT COUNT(DISTINCT p.id)
    FROM patients p
    JOIN recus r ON r.patient_id = p.id AND r.isDeleted=0
    WHERE p.est_orphelin=1 AND DATE(r.whendone) BETWEEN ? AND ?
");
$stmtOrph->execute([$filtreDebut, $filtreFin]);
$nbOrphelinsAma = (int)$stmtOrph->fetchColumn();

// ════════════════════════════════════════════════════════════════════════════
// 13. Top 10 provenances
// ════════════════════════════════════════════════════════════════════════════
$topProvenances = $pdo->prepare("
    SELECT COALESCE(NULLIF(TRIM(p.provenance),''), 'Non renseignée') AS provenance,
           COUNT(DISTINCT r.patient_id) AS nb_patients,
           COUNT(r.id) AS nb_recus,
           COALESCE(SUM(r.montant_encaisse),0) AS total
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.isDeleted=0 AND DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY provenance
    ORDER BY nb_patients DESC
    LIMIT 10
");
$topProvenances->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$topProvenances = $topProvenances->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 14. Audit qualité : modifications
// ════════════════════════════════════════════════════════════════════════════
$auditModifs = $pdo->prepare("
    SELECT COUNT(*) AS nb_modifs,
           COUNT(DISTINCT recu_id) AS nb_recus_modifies,
           COUNT(DISTINCT user_id) AS nb_users_modificateurs
    FROM modifications_recus
    WHERE DATE(whendone) BETWEEN :d AND :f
");
$auditModifs->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$auditModifs = $auditModifs->fetch() ?: ['nb_modifs'=>0,'nb_recus_modifies'=>0,'nb_users_modificateurs'=>0];

$tauxModif = $kpi['nb_recus'] > 0
    ? round(($auditModifs['nb_recus_modifies'] / $kpi['nb_recus']) * 100, 2) : 0;

$topMotifs = $pdo->prepare("
    SELECT motif, COUNT(*) AS nb
    FROM modifications_recus
    WHERE DATE(whendone) BETWEEN :d AND :f AND motif <> ''
    GROUP BY motif
    ORDER BY nb DESC
    LIMIT 5
");
$topMotifs->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$topMotifs = $topMotifs->fetchAll();

$modifsParType = $pdo->prepare("
    SELECT type_recu, COUNT(*) AS nb
    FROM modifications_recus
    WHERE DATE(whendone) BETWEEN :d AND :f
    GROUP BY type_recu
");
$modifsParType->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$modifsParType = $modifsParType->fetchAll();

$topModificateurs = $pdo->prepare("
    SELECT u.nom, u.prenom, u.role, COUNT(m.id) AS nb_modifs
    FROM modifications_recus m
    JOIN utilisateurs u ON u.id = m.user_id
    WHERE DATE(m.whendone) BETWEEN :d AND :f
    GROUP BY u.id
    ORDER BY nb_modifs DESC
    LIMIT 5
");
$topModificateurs->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$topModificateurs = $topModificateurs->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 15. Patients fidèles
// ════════════════════════════════════════════════════════════════════════════
$patientsFideles = $pdo->prepare("
    SELECT p.id, p.nom, p.telephone, p.age, p.sexe, p.est_orphelin,
           COUNT(r.id) AS nb_visites,
           COALESCE(SUM(r.montant_encaisse),0) AS total_paye,
           MAX(r.whendone) AS derniere_visite
    FROM patients p
    JOIN recus r ON r.patient_id = p.id AND r.isDeleted=0
    WHERE DATE(r.whendone) BETWEEN :d AND :f
    GROUP BY p.id
    HAVING nb_visites >= 2
    ORDER BY nb_visites DESC, total_paye DESC
    LIMIT 15
");
$patientsFideles->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$patientsFideles = $patientsFideles->fetchAll();

// ════════════════════════════════════════════════════════════════════════════
// 16. Approvisionnements pharmacie
// ════════════════════════════════════════════════════════════════════════════
$approvis = $pdo->prepare("
    SELECT COUNT(ap.id)                              AS nb_approvis,
           COALESCE(SUM(ap.quantite),0)              AS total_qte_entree,
           COALESCE(SUM(ap.quantite * pp.prix_unitaire),0) AS valeur_totale
    FROM approvisionnements_pharmacie ap
    JOIN produits_pharmacie pp ON pp.id = ap.produit_id
    WHERE ap.isDeleted=0 AND ap.date_appro BETWEEN :d AND :f
");
$approvis->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$approvis = $approvis->fetch() ?: ['nb_approvis'=>0,'total_qte_entree'=>0,'valeur_totale'=>0];

// ════════════════════════════════════════════════════════════════════════════
// 17. Taux d'utilisation des carnets
// ════════════════════════════════════════════════════════════════════════════
$statsCarnets = $pdo->prepare("
    SELECT
        COALESCE(SUM(lc.avec_carnet),0) AS nb_avec_carnet,
        COUNT(*)                         AS nb_total,
        COALESCE(SUM(lc.tarif_carnet),0) AS revenu_carnets
    FROM lignes_consultation lc
    JOIN recus r ON r.id = lc.recu_id AND r.isDeleted=0
    WHERE lc.isDeleted=0 AND DATE(r.whendone) BETWEEN :d AND :f
");
$statsCarnets->execute([':d'=>$filtreDebut, ':f'=>$filtreFin]);
$statsCarnets = $statsCarnets->fetch() ?: ['nb_avec_carnet'=>0,'nb_total'=>0,'revenu_carnets'=>0];
$tauxCarnet = $statsCarnets['nb_total'] > 0
    ? round(($statsCarnets['nb_avec_carnet'] / $statsCarnets['nb_total']) * 100, 1) : 0;

// ════════════════════════════════════════════════════════════════════════════
// 18. Records de la période
// ════════════════════════════════════════════════════════════════════════════
$record = ['jour'=>null,'patients'=>0,'recettes'=>0];
foreach ($evolution as $e) {
    if ((float)$e['recettes'] > $record['recettes']) {
        $record = ['jour'=>$e['jour'], 'patients'=>(int)$e['nb_patients'], 'recettes'=>(float)$e['recettes']];
    }
}

// ── Données JSON pour Chart.js ─────────────────────────────────────────────
$labelsEvo       = array_map(fn($r) => date('d/m', strtotime($r['jour'])), $evolution);
$dataPatientsEvo = array_column($evolution, 'nb_patients');
$dataRecettesEvo = array_column($evolution, 'recettes');
$dataCumulEvo    = array_column($evolution, 'cumul');

$labelsActes  = array_column($topActes, 'libelle');
$dataActes    = array_column($topActes, 'nb_utilisations');

$labelsProd   = array_map(fn($p) => $p['nom'].($p['forme'] ? ' ('.$p['forme'].')' : ''), $topProduits);
$dataProdQte  = array_column($topProduits, 'total_qte');

$mapType = [
    'normal'       => 'Normal payant',
    'orphelin'     => 'Orphelin DirectAid',
    'acte_gratuit' => 'Acte gratuit'
];
$labelsType = array_map(fn($t) => $mapType[$t['type_patient']] ?? ucfirst($t['type_patient']), $typePatients);
$dataType   = array_column($typePatients, 'nb');

$labelsExam = array_column($topExamens, 'libelle');
$dataExam   = array_column($topExamens, 'nb');

$mapPole = ['consultation'=>'Consultations','examen'=>'Examens','pharmacie'=>'Pharmacie'];
$labelsRep = array_map(fn($r) => $mapPole[$r['type_recu']] ?? ucfirst($r['type_recu']), $repartition);
$dataRep   = array_column($repartition, 'total');

$labelsHeures = array_map(fn($h) => sprintf('%02dh', $h), range(0,23));
$dataHeures   = array_values($heuresFull);

$labelsJrSem = array_keys($jrSemFull);
$dataJrSem   = array_map(fn($j) => $j['nb'], array_values($jrSemFull));

$labelsSexe = array_map(fn($s) => $s['sexe']==='M' ? 'Hommes' : 'Femmes', $demoSexe);
$dataSexe   = array_column($demoSexe, 'nb');

$labelsAge = array_column($demoAge, 'tranche');
$dataAge   = array_column($demoAge, 'nb');

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
                <h4 class="mb-0 fw-bold" style="color:#1565c0;">Analytique Avancée 360°</h4>
                <small class="text-muted">
                    Vision complète – Du <strong><?= date('d/m/Y', strtotime($filtreDebut)) ?></strong>
                    au <strong><?= date('d/m/Y', strtotime($filtreFin)) ?></strong>
                    (<?= $nbJours ?> jour<?= $nbJours>1?'s':'' ?>)
                </small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Imprimer
            </button>
            <a href="<?= url('index.php?page=dashboard') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Tableau de bord
            </a>
        </div>
    </div>

    <!-- ⚠ BANDEAU ALERTES CRITIQUES -->
    <?php if ($nbRupture>0 || $nbPerime>0 || $nbPeremption>0 || $nbFaible>0): ?>
    <div class="card border-0 shadow-sm mb-3" style="border-left:4px solid #d32f2f !important;">
        <div class="card-body py-2 d-flex align-items-center flex-wrap gap-2" style="background:#fff5f5;">
            <i class="bi bi-exclamation-triangle-fill fs-4" style="color:#d32f2f;"></i>
            <div class="flex-grow-1">
                <strong class="text-danger">Alertes Pharmacie :</strong>
                <?php if ($nbRupture>0): ?>
                    <span class="badge bg-danger ms-1"><?= $nbRupture ?> rupture<?= $nbRupture>1?'s':'' ?></span>
                <?php endif; ?>
                <?php if ($nbPerime>0): ?>
                    <span class="badge ms-1" style="background:#880e4f;color:#fff;"><?= $nbPerime ?> périmé<?= $nbPerime>1?'s':'' ?></span>
                <?php endif; ?>
                <?php if ($nbPeremption>0): ?>
                    <span class="badge ms-1" style="background:#e65100;color:#fff;"><?= $nbPeremption ?> péremption ≤ 60j</span>
                <?php endif; ?>
                <?php if ($nbFaible>0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?= $nbFaible ?> stock faible</span>
                <?php endif; ?>
            </div>
            <a href="#section-stock" class="btn btn-sm btn-outline-danger">Voir détails</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtre Période -->
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
                <div class="col-auto d-flex gap-1 flex-wrap">
                    <?php
                    $shortcuts = [
                        ["Auj.",       date('Y-m-d'), date('Y-m-d')],
                        ['7j',         date('Y-m-d', strtotime('-7 days')),  date('Y-m-d')],
                        ['30j',        date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
                        ['Ce mois',    date('Y-m-01'), date('Y-m-d')],
                        ['Mois préc.', date('Y-m-01', strtotime('first day of last month')),
                                       date('Y-m-t', strtotime('last day of last month'))],
                        ['Trim.',      date('Y-m-d', strtotime('-3 months')), date('Y-m-d')],
                        ['Année',      date('Y-01-01'), date('Y-m-d')],
                    ];
                    foreach ($shortcuts as [$label, $deb, $fin]):
                    ?>
                    <a href="?page=analytics&filtre_debut=<?= $deb ?>&filtre_fin=<?= $fin ?>"
                       class="btn btn-light btn-sm border"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI principaux avec variations -->
    <div class="row g-3 mb-3">
        <?php
        $kpiCards = [
            ['bi-people-fill',      '#1565c0', '#dbeafe', fmt($kpi['nb_patients']),         'Patients uniques',  $varPatients],
            ['bi-receipt',          '#2e7d32', '#dcfce7', fmt($kpi['nb_recus']),             'Reçus émis',        $varRecus],
            ['bi-cash-stack',       '#e65100', '#fef3e2', fmt($kpi['total_encaisse']).' F', 'Total encaissé',     $varEncaisse],
            ['bi-heart-pulse-fill', '#7b1fa2', '#f3e8ff', fmt($kpi['nb_gratuits']),          'Gratuits/Orphelins', $varGratuits],
        ];
        foreach ($kpiCards as [$icon, $color, $bg, $val, $label, $var]):
            $vc = $var > 0 ? '#2e7d32' : ($var < 0 ? '#d32f2f' : '#757575');
            $vi = $var > 0 ? 'arrow-up-short' : ($var < 0 ? 'arrow-down-short' : 'dash');
        ?>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid <?= $color ?> !important;">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;background:<?= $bg ?>;color:<?= $color ?>;min-width:48px;">
                        <i class="bi <?= $icon ?> fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-5" style="color:<?= $color ?>"><?= $val ?></div>
                        <div class="text-muted small"><?= $label ?></div>
                        <small style="color:<?= $vc ?>;font-weight:600;">
                            <i class="bi bi-<?= $vi ?>"></i><?= abs($var) ?>%
                            <span class="text-muted fw-normal">vs préc.</span>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- KPI secondaires -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #00695c !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Panier moyen</div>
                    <div class="fw-bold fs-5" style="color:#00695c;"><?= fmt($kpi['panier_moyen']) ?> F</div>
                    <small class="text-muted">par reçu payant</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #f57f17 !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Taux subvention sociale</div>
                    <div class="fw-bold fs-5" style="color:#f57f17;"><?= $tauxSubvention ?>%</div>
                    <small class="text-muted"><?= fmt($kpi['total_subventionne']) ?> F offerts</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #d32f2f !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Taux modifications</div>
                    <div class="fw-bold fs-5" style="color:#d32f2f;"><?= $tauxModif ?>%</div>
                    <small class="text-muted"><?= (int)$auditModifs['nb_recus_modifies'] ?> reçus modifiés</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6a1b9a !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Valeur stock pharmacie</div>
                    <div class="fw-bold fs-5" style="color:#6a1b9a;"><?= fmt($valeurStock) ?> F</div>
                    <small class="text-muted"><?= $stockTotalActuel ?> unités · <?= $qteVenduePeriode ?> vendues</small>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI tertiaires -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #1976d2 !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Taux d'utilisation des carnets</div>
                    <div class="fw-bold fs-5" style="color:#1976d2;"><?= $tauxCarnet ?>%</div>
                    <small class="text-muted">
                        <?= (int)$statsCarnets['nb_avec_carnet'] ?> consultations avec carnet ·
                        <?= fmt($statsCarnets['revenu_carnets']) ?> F générés
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #006064 !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Part Laboratoire (examens)</div>
                    <div class="fw-bold fs-5" style="color:#006064;"><?= fmt($totalLabo) ?> F</div>
                    <small class="text-muted">à reverser au labo sur la période</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #7b1fa2 !important;">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Orphelins DirectAid AMA</div>
                    <div class="fw-bold fs-5" style="color:#7b1fa2;"><?= $nbOrphelinsAma ?></div>
                    <small class="text-muted">enfants pris en charge sur la période</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Record de la période -->
    <?php if ($record['jour']): ?>
    <div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#fff8e1,#fff3e0);">
        <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <i class="bi bi-trophy-fill fs-2" style="color:#f57f17;"></i>
            <div class="flex-grow-1">
                <strong style="color:#e65100;">🏆 Meilleure journée :</strong>
                <span class="ms-2"><?= date('l d F Y', strtotime($record['jour'])) ?></span>
                <span class="badge bg-success ms-2"><?= $record['patients'] ?> patients</span>
                <span class="badge bg-warning text-dark ms-1"><?= fmt($record['recettes']) ?> F</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ligne 1 : Évolution + Pôles -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#1565c0,#1976d2);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Évolution journalière – Patients, Recettes &amp; Cumul</h6>
                </div>
                <div class="card-body"><canvas id="chartEvolution" height="160"></canvas></div>
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

    <!-- Ligne 2 : Heures + Jours -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#0277bd,#039be5);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Heures de pic d'activité (24h)</h6>
                </div>
                <div class="card-body"><canvas id="chartHeures" height="160"></canvas></div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#558b2f,#7cb342);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-calendar-week me-2"></i>Activité par jour de la semaine</h6>
                </div>
                <div class="card-body"><canvas id="chartJrSem" height="160"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Ligne 3 : Démographie -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#c2185b,#e91e63);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-gender-ambiguous me-2"></i>Répartition par sexe</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartSexe" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#5d4037,#795548);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-bar-chart-steps me-2"></i>Tranches d'âge</h6>
                </div>
                <div class="card-body"><canvas id="chartAge" height="200"></canvas></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#d32f2f,#e53935);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Types de patients</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartTypePatients" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Ligne 4 : Top Actes + Top Examens -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#e65100,#f4511e);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-stethoscope me-2"></i>Top 10 Actes Médicaux</h6>
                </div>
                <div class="card-body"><canvas id="chartActes" height="220"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#006064,#00838f);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>Top 10 Examens prescrits</h6>
                </div>
                <div class="card-body"><canvas id="chartExamens" height="220"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Ligne 5 : Top Produits -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#6a1b9a,#8e24aa);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-capsule me-2"></i>Top 10 Produits Pharmacie consommés</h6>
                </div>
                <div class="card-body"><canvas id="chartProduits" height="120"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Ligne 6 : Tables Actes & Produits -->
    <div class="row g-3 mb-4">
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
                                <th class="text-center">Util.</th>
                                <th class="text-center">Carnet</th>
                                <th class="text-center">Orph.</th>
                                <th class="text-end">Tarif</th>
                                <th class="text-end">Revenu</th>
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
                                    <?php if ($a['nb_avec_carnet']>0): ?>
                                    <span class="badge bg-info text-dark"><?= $a['nb_avec_carnet'] ?></span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['nb_orphelins'] > 0): ?>
                                    <span class="badge" style="background:#7b1fa2;"><?= $a['nb_orphelins'] ?></span>
                                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td class="text-end text-muted small"><?= fmt($a['tarif']) ?> F</td>
                                <td class="text-end fw-bold" style="color:#2e7d32;">
                                    <?= fmt($a['revenu_genere']) ?> F
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Aucune donnée</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

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
                                <th class="text-center">Qté</th>
                                <th class="text-end">Prix moy.</th>
                                <th class="text-end">Revenu</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($topProduits): foreach ($topProduits as $i => $prod): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= $i+1 ?></span></td>
                                <td class="fw-semibold"><?= h($prod['nom']) ?></td>
                                <td><small class="text-muted"><?= h($prod['forme']) ?></small></td>
                                <td class="text-center">
                                    <span class="badge" style="background:#6a1b9a;"><?= $prod['total_qte'] ?></span>
                                </td>
                                <td class="text-end text-muted small"><?= fmt($prod['prix_moyen']) ?> F</td>
                                <td class="text-end fw-bold" style="color:#2e7d32;">
                                    <?= fmt($prod['total_revenu']) ?> F
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
    </div>

    <!-- Performance Percepteurs -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-0" style="background:linear-gradient(90deg,#37474f,#546e7a);color:#fff;">
            <h6 class="mb-0"><i class="bi bi-people-fill me-2"></i>Performance Percepteurs</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Percepteur</th>
                        <th class="text-center">Reçus</th>
                        <th class="text-center">Patients</th>
                        <th class="text-center">Cons.</th>
                        <th class="text-center">Exam.</th>
                        <th class="text-center">Pharm.</th>
                        <th class="text-center">Gratuits</th>
                        <th class="text-end">Panier moy.</th>
                        <th class="text-end">Encaissé</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $encaisses = array_map(fn($x) => (float)($x['total_encaisse'] ?? 0), $perfPercep);
                $maxEncaisse = !empty($encaisses) ? max(1, max($encaisses)) : 1;
                foreach ($perfPercep as $idx => $p):
                    $totalEnc = (float)($p['total_encaisse'] ?? 0);
                    $pct = round(($totalEnc / $maxEncaisse) * 100);
                    $medal = $idx===0 && $totalEnc>0 ? '🥇' :
                             ($idx===1 && $totalEnc>0 ? '🥈' :
                             ($idx===2 && $totalEnc>0 ? '🥉' : ''));
                ?>
                    <tr>
                        <td>
                            <span class="me-1"><?= $medal ?></span>
                            <i class="bi bi-person-badge me-1" style="color:#1565c0;"></i>
                            <strong><?= h($p['nom'].' '.$p['prenom']) ?></strong>
                            <?php if (!$p['est_actif']): ?>
                                <span class="badge bg-secondary ms-1">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $p['nb_recus']>0?'primary':'secondary' ?>"><?= $p['nb_recus'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info text-dark"><?= $p['nb_patients'] ?></span>
                        </td>
                        <td class="text-center"><small><?= $p['nb_consult'] ?></small></td>
                        <td class="text-center"><small><?= $p['nb_exam'] ?></small></td>
                        <td class="text-center"><small><?= $p['nb_pharma'] ?></small></td>
                        <td class="text-center">
                            <?php if ($p['nb_gratuits']>0): ?>
                            <span class="badge" style="background:#7b1fa2;"><?= $p['nb_gratuits'] ?></span>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                        </td>
                        <td class="text-end text-muted small"><?= fmt($p['panier_moyen']) ?> F</td>
                        <td class="text-end fw-bold" style="color:#2e7d32;">
                            <?= fmt($p['total_encaisse']) ?> F
                        </td>
                        <td style="min-width:150px;">
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar" role="progressbar"
                                     style="width:<?= $pct ?>%;background:linear-gradient(90deg,#1565c0,#42a5f5);"></div>
                            </div>
                            <small class="text-muted"><?= $pct ?>%</small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- ⚠ Alertes Stock détaillées -->
    <div id="section-stock" class="card border-0 shadow-sm mb-4">
        <div class="card-header border-0 d-flex justify-content-between align-items-center"
             style="background:linear-gradient(90deg,#c62828,#e53935);color:#fff;">
            <h6 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Alertes Stock Pharmacie</h6>
            <span class="badge bg-light text-danger"><?= count($stockAlertes) ?> alertes</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Produit</th><th>Forme</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Seuil</th>
                        <th class="text-end">Valeur</th>
                        <th>Péremption</th>
                        <th class="text-center">Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($stockAlertes): foreach ($stockAlertes as $s):
                    $statusBadge = match($s['statut']) {
                        'RUPTURE'    => '<span class="badge bg-danger">RUPTURE</span>',
                        'PERIME'     => '<span class="badge" style="background:#880e4f;color:#fff;">PÉRIMÉ</span>',
                        'PEREMPTION' => '<span class="badge" style="background:#e65100;color:#fff;">PÉREMPTION ≤ 60j</span>',
                        'FAIBLE'     => '<span class="badge bg-warning text-dark">STOCK FAIBLE</span>',
                        default      => '<span class="badge bg-secondary">'.h($s['statut']).'</span>'
                    };
                    $valLigne = (float)$s['stock_actuel'] * (float)$s['prix_unitaire'];
                ?>
                    <tr>
                        <td class="fw-semibold"><?= h($s['nom']) ?></td>
                        <td><small class="text-muted"><?= h($s['forme']) ?></small></td>
                        <td class="text-center">
                            <strong class="<?= $s['stock_actuel']<=0?'text-danger':'' ?>"><?= $s['stock_actuel'] ?></strong>
                        </td>
                        <td class="text-center text-muted"><?= $s['seuil_alerte'] ?></td>
                        <td class="text-end text-muted small"><?= fmt($valLigne) ?> F</td>
                        <td>
                            <?php if ($s['date_peremption']): ?>
                                <?= date('d/m/Y', strtotime($s['date_peremption'])) ?>
                                <?php
                                $jrs = ceil((strtotime($s['date_peremption']) - time())/86400);
                                if ($jrs<=0): ?>
                                    <small class="text-danger fw-bold">(périmé)</small>
                                <?php elseif ($jrs<=60): ?>
                                    <small class="text-warning fw-bold">(<?= $jrs ?>j)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $statusBadge ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-center text-success py-3">
                        <i class="bi bi-check-circle me-1"></i>Aucune alerte – Stock sain
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- Provenances + Patients fidèles -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#00695c,#00897b);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Top 10 Provenances</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th><th>Provenance</th>
                                <th class="text-center">Patients</th>
                                <th class="text-center">Reçus</th>
                                <th class="text-end">Recettes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($topProvenances): foreach ($topProvenances as $i => $pv): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= $i+1 ?></span></td>
                                <td class="fw-semibold"><?= h($pv['provenance']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info text-dark"><?= $pv['nb_patients'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= $pv['nb_recus'] ?></span>
                                </td>
                                <td class="text-end fw-bold" style="color:#2e7d32;">
                                    <?= fmt($pv['total']) ?> F
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

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#1565c0,#1e88e5);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-star-fill me-2"></i>Patients fidèles (≥ 2 visites)</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th class="text-center">Visites</th>
                                <th class="text-end">Total payé</th>
                                <th>Dernière visite</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($patientsFideles): foreach ($patientsFideles as $f): ?>
                            <tr>
                                <td>
                                    <strong><?= h($f['nom']) ?></strong>
                                    <?php if ($f['est_orphelin']): ?>
                                        <span class="badge ms-1" style="background:#7b1fa2;font-size:0.65em;">Orphelin AMA</span>
                                    <?php endif; ?>
                                    <small class="text-muted d-block">
                                        <?= h($f['telephone']) ?>
                                        · <?= (int)$f['age'] ?> ans · <?= h($f['sexe']) ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark"><?= $f['nb_visites'] ?></span>
                                </td>
                                <td class="text-end fw-bold" style="color:#2e7d32;">
                                    <?= fmt($f['total_paye']) ?> F
                                </td>
                                <td><small><?= date('d/m/Y', strtotime($f['derniere_visite'])) ?></small></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Aucun patient récurrent</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Qualité + Approvisionnements -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#bf360c,#d84315);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Audit qualité – Modifications</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center mb-3">
                        <div class="col-4">
                            <div class="fs-4 fw-bold text-danger"><?= (int)$auditModifs['nb_modifs'] ?></div>
                            <small class="text-muted">Total modifs</small>
                        </div>
                        <div class="col-4">
                            <div class="fs-4 fw-bold" style="color:#e65100;"><?= (int)$auditModifs['nb_recus_modifies'] ?></div>
                            <small class="text-muted">Reçus impactés</small>
                        </div>
                        <div class="col-4">
                            <div class="fs-4 fw-bold" style="color:#1565c0;"><?= (int)$auditModifs['nb_users_modificateurs'] ?></div>
                            <small class="text-muted">Utilisateurs</small>
                        </div>
                    </div>

                    <?php if ($modifsParType): ?>
                    <h6 class="text-muted small text-uppercase mb-2">Par type de reçu</h6>
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <?php foreach ($modifsParType as $mt): ?>
                        <span class="badge bg-light text-dark border">
                            <?= ucfirst($mt['type_recu']) ?> : <strong><?= $mt['nb'] ?></strong>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($topMotifs): ?>
                    <h6 class="text-muted small text-uppercase mb-2">Top motifs</h6>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($topMotifs as $m): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                            <small><?= h(mb_strimwidth($m['motif'], 0, 60, '…')) ?></small>
                            <span class="badge bg-warning text-dark rounded-pill"><?= $m['nb'] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if ($topModificateurs): ?>
                    <h6 class="text-muted small text-uppercase mb-2">Top modificateurs</h6>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topModificateurs as $u): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                            <small>
                                <i class="bi bi-person me-1"></i>
                                <?= h($u['nom'].' '.$u['prenom']) ?>
                                <span class="text-muted">(<?= h($u['role']) ?>)</span>
                            </small>
                            <span class="badge bg-danger rounded-pill"><?= $u['nb_modifs'] ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if (!$auditModifs['nb_modifs']): ?>
                        <p class="text-muted text-center mb-0">Aucune modification sur la période</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0" style="background:linear-gradient(90deg,#1b5e20,#388e3c);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Approvisionnements pharmacie</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center mb-3">
                        <div class="col-4">
                            <div class="fs-4 fw-bold" style="color:#1b5e20;"><?= (int)$approvis['nb_approvis'] ?></div>
                            <small class="text-muted">Entrées</small>
                        </div>
                        <div class="col-4">
                            <div class="fs-4 fw-bold" style="color:#1b5e20;"><?= fmt($approvis['total_qte_entree']) ?></div>
                            <small class="text-muted">Quantité</small>
                        </div>
                        <div class="col-4">
                            <div class="fs-4 fw-bold" style="color:#1b5e20;">
                                <?= fmt($approvis['valeur_totale']) ?> F
                            </div>
                            <small class="text-muted">Valeur</small>
                        </div>
                    </div>

                    <h6 class="text-muted small text-uppercase mt-3 mb-2">Synthèse stock pharmacie</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span><i class="bi bi-archive me-2"></i>Stock total actuel</span>
                            <strong><?= fmt($stockTotalActuel) ?> unités</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span><i class="bi bi-cart-check me-2"></i>Quantité vendue (période)</span>
                            <strong><?= fmt($qteVenduePeriode) ?> unités</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span><i class="bi bi-cash-coin me-2"></i>Valeur immobilisée</span>
                            <strong style="color:#6a1b9a;"><?= fmt($valeurStock) ?> F</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.mt-4 -->

<?php
$jsLabelsEvo       = json_encode($labelsEvo);
$jsDataPatientsEvo = json_encode($dataPatientsEvo);
$jsDataRecettesEvo = json_encode($dataRecettesEvo);
$jsDataCumulEvo    = json_encode($dataCumulEvo);
$jsLabelsActes     = json_encode($labelsActes);
$jsDataActes       = json_encode($dataActes);
$jsLabelsProd      = json_encode($labelsProd);
$jsDataProdQte     = json_encode($dataProdQte);
$jsLabelsType      = json_encode($labelsType);
$jsDataType        = json_encode($dataType);
$jsLabelsExam      = json_encode($labelsExam);
$jsDataExam        = json_encode($dataExam);
$jsLabelsRep       = json_encode($labelsRep);
$jsDataRep         = json_encode($dataRep);
$jsLabelsHeures    = json_encode($labelsHeures);
$jsDataHeures      = json_encode($dataHeures);
$jsLabelsJrSem     = json_encode($labelsJrSem);
$jsDataJrSem       = json_encode($dataJrSem);
$jsLabelsSexe      = json_encode($labelsSexe);
$jsDataSexe        = json_encode($dataSexe);
$jsLabelsAge       = json_encode($labelsAge);
$jsDataAge         = json_encode($dataAge);

$extraJs = <<<HEREDOC
<script>
const PALETTE = ['#1565c0','#2e7d32','#e65100','#006064','#6a1b9a','#d32f2f','#f57f17','#00695c','#37474f','#880e4f'];
const fmtFR = v => new Intl.NumberFormat('fr-FR').format(Math.round(v));

new Chart(document.getElementById('chartEvolution'), {
    data: {
        labels: {$jsLabelsEvo},
        datasets: [
            {type:'line',label:'Cumul recettes (F)',data:{$jsDataCumulEvo},
             borderColor:'#7b1fa2',backgroundColor:'rgba(123,31,162,0.05)',
             borderDash:[6,3],fill:false,tension:0.3,yAxisID:'yRevenu',pointRadius:0},
            {type:'line',label:'Recettes du jour (F)',data:{$jsDataRecettesEvo},
             borderColor:'#1565c0',backgroundColor:'rgba(21,101,192,0.1)',
             fill:true,tension:0.4,yAxisID:'yRevenu',pointBackgroundColor:'#1565c0',pointRadius:3},
            {type:'bar',label:'Patients',data:{$jsDataPatientsEvo},
             backgroundColor:'rgba(46,125,50,0.7)',borderColor:'#2e7d32',
             borderRadius:5,yAxisID:'yPatients'}
        ]
    },
    options: {
        responsive:true,interaction:{mode:'index'},
        plugins:{legend:{position:'top'},
            tooltip:{callbacks:{label:ctx=>{
                if(ctx.dataset.yAxisID==='yRevenu')return ctx.dataset.label+' : '+fmtFR(ctx.raw)+' F';
                return 'Patients : '+ctx.raw;
            }}}},
        scales:{
            yPatients:{type:'linear',position:'left',beginAtZero:true,ticks:{stepSize:1},title:{display:true,text:'Patients'}},
            yRevenu:{type:'linear',position:'right',beginAtZero:true,grid:{drawOnChartArea:false},
                     ticks:{callback:v=>fmtFR(v)+' F'},title:{display:true,text:'Recettes'}}
        }
    }
});

(function(){
    const labels={$jsLabelsRep},data={$jsDataRep};
    if(!data.length){
        document.getElementById('chartRepartition').closest('.card-body').innerHTML='<p class="text-muted text-center py-4">Aucune donnée</p>';
        return;
    }
    new Chart(document.getElementById('chartRepartition'),{
        type:'doughnut',
        data:{labels,datasets:[{data,backgroundColor:PALETTE.slice(0,labels.length),borderWidth:2}]},
        options:{responsive:true,plugins:{legend:{position:'bottom'},
            tooltip:{callbacks:{label:ctx=>ctx.label+' : '+fmtFR(ctx.raw)+' F'}}}}
    });
})();

new Chart(document.getElementById('chartHeures'),{
    type:'line',
    data:{labels:{$jsLabelsHeures},datasets:[{label:'Reçus',data:{$jsDataHeures},
        borderColor:'#0277bd',backgroundColor:'rgba(2,119,189,0.2)',fill:true,tension:0.4,pointRadius:3}]},
    options:{responsive:true,plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
});

new Chart(document.getElementById('chartJrSem'),{
    type:'bar',
    data:{labels:{$jsLabelsJrSem},datasets:[{label:'Reçus',data:{$jsDataJrSem},
        backgroundColor:PALETTE,borderRadius:5}]},
    options:{responsive:true,plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
});

(function(){
    const labels={$jsLabelsSexe},data={$jsDataSexe};
    if(!data.length){
        document.getElementById('chartSexe').closest('.card-body').innerHTML='<p class="text-muted text-center py-4">Aucune donnée</p>';
        return;
    }
    new Chart(document.getElementById('chartSexe'),{
        type:'pie',
        data:{labels,datasets:[{data,backgroundColor:['#1565c0','#e91e63'],borderWidth:2}]},
        options:{responsive:true,plugins:{legend:{position:'bottom'}}}
    });
})();

new Chart(document.getElementById('chartAge'),{
    type:'bar',
    data:{labels:{$jsLabelsAge},datasets:[{label:'Patients',data:{$jsDataAge},
        backgroundColor:'rgba(93,64,55,0.75)',borderColor:'#5d4037',borderRadius:5}]},
    options:{responsive:true,plugins:{legend:{display:false}},
        scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
});

new Chart(document.getElementById('chartActes'),{
    type:'bar',
    data:{labels:{$jsLabelsActes},datasets:[{label:'Utilisations',data:{$jsDataActes},
        backgroundColor:'rgba(230,81,0,0.75)',borderColor:'#e65100',borderRadius:5}]},
    options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false}},
        scales:{x:{beginAtZero:true,ticks:{stepSize:1}}}}
});

new Chart(document.getElementById('chartExamens'),{
    type:'bar',
    data:{labels:{$jsLabelsExam},datasets:[{label:'Prescriptions',data:{$jsDataExam},
        backgroundColor:'rgba(0,96,100,0.75)',borderColor:'#006064',borderRadius:5}]},
    options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false}},
        scales:{x:{beginAtZero:true,ticks:{stepSize:1}}}}
});

new Chart(document.getElementById('chartProduits'),{
    type:'bar',
    data:{labels:{$jsLabelsProd},datasets:[
        {label:'Quantité',data:{$jsDataProdQte},backgroundColor:'rgba(106,27,154,0.75)',borderColor:'#6a1b9a',borderRadius:5}
    ]},
    options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false}},
        scales:{x:{beginAtZero:true}}}
});

(function(){
    const labels={$jsLabelsType},data={$jsDataType};
    if(!data.length){
        document.getElementById('chartTypePatients').closest('.card-body').innerHTML='<p class="text-muted text-center py-4">Aucune donnée</p>';
        return;
    }
    new Chart(document.getElementById('chartTypePatients'),{
        type:'polarArea',
        data:{labels,datasets:[{data,backgroundColor:PALETTE.map(c=>c+'cc'),borderWidth:1}]},
        options:{responsive:true,plugins:{legend:{position:'bottom'}}}
    });
})();
</script>
HEREDOC;

include ROOT_PATH . '/templates/layouts/footer.php';
?>

