<?php
/**
 * Page Récapitulatif Patients
 * - Liste des patients avec recherche
 * - Détail par patient : reçus + produits + examens
 * - Badge ORPHELIN visible sur le nom dans la liste et dans la fiche détail
 */
requireRole('admin', 'comptable', 'percepteur');
$pdo       = Database::getInstance();
$pageTitle = 'Récapitulatif Patients';
$isAdmin   = in_array(Session::get('user_role'), ['admin','comptable'], true);

// ── Recherche patient ──────────────────────────────────────────────────────
$search    = trim($_GET['q'] ?? '');
$patientId = (int)($_GET['patient_id'] ?? 0);

// ── Liste patients — ajout de est_orphelin dans le SELECT ──────────────────
$sqlPatients = "
    SELECT p.id, p.telephone, p.nom, p.sexe, p.age, p.provenance,
           p.est_orphelin,
           COUNT(DISTINCT r.id)                        AS nb_recus,
           COALESCE(SUM(r.montant_encaisse),0)          AS total_paye,
           MAX(r.whendone)                              AS derniere_visite
    FROM patients p
    LEFT JOIN recus r ON r.patient_id=p.id AND r.isDeleted=0
    WHERE p.isDeleted=0
";
$params = [];
if ($search !== '') {
    $sqlPatients .= " AND (p.nom LIKE :q OR p.telephone LIKE :q2) ";
    $params[':q']  = '%'.$search.'%';
    $params[':q2'] = '%'.$search.'%';
}
$sqlPatients .= " GROUP BY p.id ORDER BY derniere_visite DESC, p.nom ASC LIMIT 60";
$stmtP = $pdo->prepare($sqlPatients);
$stmtP->execute($params);
$patients = $stmtP->fetchAll();

// ── Détail d'un patient sélectionné ───────────────────────────────────────
$patientDetail  = null;
$recusDetail    = [];
$produitsDetail = [];
$examensDetail  = [];

if ($patientId > 0) {
    $stmtPat = $pdo->prepare("SELECT * FROM patients WHERE id=:id AND isDeleted=0 LIMIT 1");
    $stmtPat->execute([':id' => $patientId]);
    $patientDetail = $stmtPat->fetch();

    if ($patientDetail) {
        $stmtR = $pdo->prepare("
            SELECT r.id, r.numero_recu, r.type_recu, r.type_patient,
                   r.montant_total, r.montant_encaisse, r.whendone,
                   u.nom AS percepteur_nom, u.prenom AS percepteur_prenom
            FROM recus r
            LEFT JOIN utilisateurs u ON u.id = r.whodone
            WHERE r.patient_id=:pid AND r.isDeleted=0
            ORDER BY r.whendone DESC
        ");
        $stmtR->execute([':pid' => $patientId]);
        $recusDetail = $stmtR->fetchAll();

        $stmtProd = $pdo->prepare("
            SELECT lp.nom, lp.forme, lp.quantite, lp.prix_unitaire, lp.total_ligne,
                   r.numero_recu, r.whendone
            FROM lignes_pharmacie lp
            JOIN recus r ON r.id=lp.recu_id AND r.isDeleted=0
            WHERE r.patient_id=:pid AND lp.isDeleted=0
            ORDER BY r.whendone DESC
        ");
        $stmtProd->execute([':pid' => $patientId]);
        $produitsDetail = $stmtProd->fetchAll();

        $stmtExam = $pdo->prepare("
            SELECT e.libelle AS examen_nom, le.cout_total,
                   r.numero_recu, r.whendone
            FROM lignes_examen le
            JOIN examens e ON e.id=le.examen_id
            JOIN recus r ON r.id=le.recu_id AND r.isDeleted=0
            WHERE r.patient_id=:pid AND le.isDeleted=0
            ORDER BY r.whendone DESC
        ");
        $stmtExam->execute([':pid' => $patientId]);
        $examensDetail = $stmtExam->fetchAll();
    }
}

include ROOT_PATH . '/templates/layouts/header.php';
?>

<div class="mt-4">

    <!-- En-tête -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center me-3"
                 style="width:50px;height:50px;background:linear-gradient(135deg,#006064,#00838f);">
                <i class="bi bi-person-vcard text-white fs-4"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold" style="color:#006064;">Récapitulatif Patients</h4>
                <small class="text-muted">Historique des reçus, produits et examens par patient</small>
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <a href="<?= url('index.php?page=analytics') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-graph-up-arrow me-1"></i>Analytique avancée
        </a>
        <?php endif; ?>
    </div>

    <div class="row g-3">

        <!-- ── Colonne gauche : Liste patients ──────────────────────────── -->
        <div class="col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header border-0"
                     style="background:linear-gradient(90deg,#006064,#00838f);color:#fff;">
                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Patients</h6>
                </div>
                <div class="card-body p-2">
                    <!-- Recherche -->
                    <form method="GET" class="mb-2">
                        <input type="hidden" name="page" value="patients">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="q"
                                   placeholder="Nom ou téléphone…"
                                   value="<?= h($search) ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if ($search): ?>
                            <a href="?page=patients" class="btn btn-outline-danger" title="Effacer">
                                <i class="bi bi-x"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Liste -->
                    <div style="max-height:65vh;overflow-y:auto;">
                        <?php if (empty($patients)): ?>
                        <div class="text-center text-muted py-3 small">Aucun patient trouvé.</div>
                        <?php else: foreach ($patients as $pat):
                            $isSelected  = ($pat['id'] == $patientId);
                            $isOrph      = (int)$pat['est_orphelin'] === 1;
                            $url = url('index.php?page=patients&patient_id='.$pat['id']
                                       .($search ? '&q='.urlencode($search) : ''));
                        ?>
                        <a href="<?= $url ?>"
                           class="list-group-item list-group-item-action border-0 px-2 py-2 mb-1 rounded
                                  <?= $isSelected ? 'active' : '' ?>"
                           style="<?= $isSelected ? 'background:#e0f2f1;color:#006064;' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold small">
                                        <?= h($pat['nom']) ?>
                                        <?php if ($isOrph): ?>
                                        <!-- BADGE ORPHELIN dans la liste -->
                                        <span class="badge ms-1"
                                              style="background:#7b1fa2;font-size:0.6rem;
                                                     vertical-align:middle;letter-spacing:.5px;">
                                            <i class="bi bi-heart-fill me-1"
                                               style="font-size:0.55rem;"></i>ORPHELIN
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.75rem;">
                                        <i class="bi bi-phone me-1"></i><?= h($pat['telephone']) ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-secondary" style="font-size:0.65rem;">
                                        <?= $pat['nb_recus'] ?> reçu<?= $pat['nb_recus'] > 1 ? 's' : '' ?>
                                    </span>
                                    <?php if ($pat['derniere_visite']): ?>
                                    <div style="font-size:0.65rem;" class="text-muted">
                                        <?= date('d/m/Y', strtotime($pat['derniere_visite'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; endif; ?>
                    </div>

                    <?php if (count($patients) >= 60): ?>
                    <div class="text-center mt-2">
                        <small class="text-muted">60 résultats max. Affinez la recherche.</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Colonne droite : Détail patient ──────────────────────────── -->
        <div class="col-md-8 col-lg-9">
            <?php if (!$patientDetail): ?>
            <!-- Placeholder -->
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column align-items-center
                            justify-content-center py-5 text-muted">
                    <i class="bi bi-person-bounding-box fs-1 mb-3" style="color:#b2dfdb;"></i>
                    <h5 class="fw-normal">Sélectionnez un patient</h5>
                    <p class="small">Cliquez sur un patient à gauche pour voir son historique complet.</p>
                </div>
            </div>

            <?php else:
                $ficheOrphelin = (int)$patientDetail['est_orphelin'] === 1;
            ?>

            <!-- ── Fiche Patient ──────────────────────────────────────────── -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-3 d-flex flex-wrap align-items-center gap-4"
                     style="background:linear-gradient(135deg,#e0f2f1,#f1f8e9);">

                    <!-- Icône : violette pour orphelin, verte sinon -->
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:60px;height:60px;min-width:60px;color:#fff;font-size:1.6rem;
                                background:<?= $ficheOrphelin ? '#7b1fa2' : '#006064' ?>;">
                        <i class="bi bi-person<?= $ficheOrphelin ? '-heart' : '' ?>"></i>
                    </div>

                    <div class="flex-grow-1">
                        <!-- NOM + BADGE ORPHELIN dans la fiche -->
                        <h5 class="mb-1 fw-bold d-flex align-items-center flex-wrap gap-2"
                            style="color:#004d40;">
                            <?= h($patientDetail['nom']) ?>
                            <?php if ($ficheOrphelin): ?>
                            <span class="badge d-inline-flex align-items-center gap-1 px-2 py-1"
                                  style="background:#7b1fa2;font-size:0.75rem;letter-spacing:.5px;">
                                <i class="bi bi-heart-fill" style="font-size:0.7rem;"></i>
                                ORPHELIN – DIRECTAID AMA
                            </span>
                            <?php endif; ?>
                        </h5>

                        <div class="d-flex flex-wrap gap-3 text-muted small">
                            <span><i class="bi bi-phone me-1"></i><?= h($patientDetail['telephone']) ?></span>
                            <!-- Sexe : pour orphelin pas besoin d'afficher (toujours M) -->
                            <?php if (!$ficheOrphelin): ?>
                            <span><i class="bi bi-gender-ambiguous me-1"></i>
                                <?= $patientDetail['sexe'] === 'M' ? 'Homme' : 'Femme' ?>
                            </span>
                            <?php endif; ?>
                            <span><i class="bi bi-calendar me-1"></i><?= $patientDetail['age'] ?> ans</span>
                            <?php if ($patientDetail['provenance']): ?>
                            <span><i class="bi bi-geo-alt me-1"></i><?= h($patientDetail['provenance']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Compteurs -->
                    <div class="d-flex gap-2">
                        <div class="text-center px-3 py-2 rounded" style="background:#fff;min-width:80px;">
                            <div class="fw-bold fs-5" style="color:#006064;"><?= count($recusDetail) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;">Reçus</div>
                        </div>
                        <div class="text-center px-3 py-2 rounded" style="background:#fff;min-width:110px;">
                            <?php if ($ficheOrphelin): ?>
                            <!-- Orphelin : total toujours 0 F encaissé -->
                            <div class="fw-bold fs-6" style="color:#7b1fa2;">0 F</div>
                            <div class="text-muted" style="font-size:0.7rem;">Encaissé (gratuit)</div>
                            <?php else: ?>
                            <div class="fw-bold fs-6" style="color:#2e7d32;">
                                <?= number_format(
                                        array_sum(array_column($recusDetail, 'montant_encaisse')),
                                        0, ',', ' '
                                    ) ?> F
                            </div>
                            <div class="text-muted" style="font-size:0.7rem;">Total payé</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Onglets ──────────────────────────────────────────────── -->
            <ul class="nav nav-tabs mb-3" id="patientTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-semibold"
                            data-bs-toggle="tab" data-bs-target="#tabRecus">
                        <i class="bi bi-receipt me-1"></i>Reçus
                        <span class="badge bg-secondary ms-1"><?= count($recusDetail) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold"
                            data-bs-toggle="tab" data-bs-target="#tabProduits">
                        <i class="bi bi-capsule me-1"></i>Produits pris
                        <span class="badge bg-secondary ms-1"><?= count($produitsDetail) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold"
                            data-bs-toggle="tab" data-bs-target="#tabExamens">
                        <i class="bi bi-clipboard2-pulse me-1"></i>Examens
                        <span class="badge bg-secondary ms-1"><?= count($examensDetail) ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ── Tab Reçus ─────────────────────────────────────────── -->
                <div class="tab-pane fade show active" id="tabRecus">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <?php if ($recusDetail): ?>
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>N° Reçu</th>
                                        <th>Type</th>
                                        <th>Catégorie patient</th>
                                        <th class="text-end">Montant total</th>
                                        <th class="text-end">Encaissé</th>
                                        <th>Percepteur</th>
                                        <th>Date / Heure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recusDetail as $r):
                                    $typeBadges = [
                                        'consultation' => ['bg-success',          'Consultation'],
                                        'examen'       => ['bg-warning text-dark', 'Examen'],
                                        'pharmacie'    => ['bg-info text-dark',    'Pharmacie'],
                                    ];
                                    [$tbg, $tlabel] = $typeBadges[$r['type_recu']] ?? ['bg-secondary', $r['type_recu']];
                                    $isOrphelinRecu  = ($r['type_patient'] === 'orphelin');
                                    $isActeGratuit   = ($r['type_patient'] === 'acte_gratuit');
                                    $isGratuit       = $isOrphelinRecu || $isActeGratuit;
                                ?>
                                <tr>
                                    <td>
                                        <span class="font-monospace fw-bold" style="color:#006064;">
                                            #<?= str_pad($r['numero_recu'], 5, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $tbg ?>"><?= $tlabel ?></span>
                                    </td>
                                    <td>
                                        <?php if ($isOrphelinRecu): ?>
                                        <!-- BADGE ORPHELIN dans la colonne Catégorie patient -->
                                        <span class="badge d-inline-flex align-items-center gap-1"
                                              style="background:#7b1fa2;font-size:0.72rem;">
                                            <i class="bi bi-heart-fill" style="font-size:0.65rem;"></i>
                                            Orphelin
                                        </span>
                                        <?php elseif ($isActeGratuit): ?>
                                        <span class="badge"
                                              style="background:#1565c0;font-size:0.72rem;">
                                            <i class="bi bi-clipboard2-heart me-1"
                                               style="font-size:0.65rem;"></i>Acte gratuit
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-light text-dark border"
                                              style="font-size:0.72rem;">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($isGratuit): ?>
                                        <span class="text-muted">
                                            <s><?= number_format($r['montant_total'], 0, ',', ' ') ?> F</s>
                                        </span>
                                        <?php else: ?>
                                        <?= number_format($r['montant_total'], 0, ',', ' ') ?> F
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?php if ($isGratuit): ?>
                                        <span style="color:#7b1fa2;">0 F</span>
                                        <?php else: ?>
                                        <span style="color:#2e7d32;">
                                            <?= number_format($r['montant_encaisse'], 0, ',', ' ') ?> F
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= h($r['percepteur_nom'] . ' ' . $r['percepteur_prenom']) ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($r['whendone'])) ?>
                                            <br><?= date('H:i',  strtotime($r['whendone'])) ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="fw-bold">TOTAL</td>
                                        <td class="text-end fw-bold">
                                            <?= number_format(
                                                    array_sum(array_column($recusDetail, 'montant_total')),
                                                    0, ',', ' '
                                                ) ?> F
                                        </td>
                                        <td class="text-end fw-bold" style="color:#2e7d32;">
                                            <?= number_format(
                                                    array_sum(array_column($recusDetail, 'montant_encaisse')),
                                                    0, ',', ' '
                                                ) ?> F
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Aucun reçu pour ce patient.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ── Tab Produits ──────────────────────────────────────── -->
                <div class="tab-pane fade" id="tabProduits">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <?php if ($produitsDetail): ?>
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produit</th><th>Forme</th>
                                        <th class="text-center">Qté</th>
                                        <th class="text-end">Prix unit.</th>
                                        <th class="text-end">Total ligne</th>
                                        <th>Reçu</th><th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($produitsDetail as $pr): ?>
                                <tr>
                                    <td class="fw-semibold"><?= h($pr['nom']) ?></td>
                                    <td><small class="text-muted"><?= h($pr['forme']) ?></small></td>
                                    <td class="text-center">
                                        <span class="badge" style="background:#6a1b9a;">
                                            <?= $pr['quantite'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-muted small">
                                        <?php if ($ficheOrphelin): ?>
                                        <s><?= number_format($pr['prix_unitaire'], 0, ',', ' ') ?> F</s>
                                        <span style="color:#7b1fa2;font-weight:bold;"> 0 F</span>
                                        <?php else: ?>
                                        <?= number_format($pr['prix_unitaire'], 0, ',', ' ') ?> F
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?php if ($ficheOrphelin): ?>
                                        <span style="color:#7b1fa2;">0 F</span>
                                        <?php else: ?>
                                        <span style="color:#6a1b9a;">
                                            <?= number_format($pr['total_ligne'], 0, ',', ' ') ?> F
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="font-monospace small" style="color:#006064;">
                                            #<?= str_pad($pr['numero_recu'], 5, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($pr['whendone'])) ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="fw-bold">TOTAL PHARMACIE</td>
                                        <td class="text-end fw-bold" style="color:#6a1b9a;">
                                            <?php if ($ficheOrphelin): ?>
                                            <span style="color:#7b1fa2;">0 F (gratuit)</span>
                                            <?php else: ?>
                                            <?= number_format(
                                                    array_sum(array_column($produitsDetail, 'total_ligne')),
                                                    0, ',', ' '
                                                ) ?> F
                                            <?php endif; ?>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-capsule fs-2 d-block mb-2"></i>
                                Aucun produit pharmacie pour ce patient.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ── Tab Examens ───────────────────────────────────────── -->
                <div class="tab-pane fade" id="tabExamens">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <?php if ($examensDetail): ?>
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Examen</th>
                                        <th class="text-end">Coût</th>
                                        <th>Reçu</th><th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($examensDetail as $ex): ?>
                                <tr>
                                    <td class="fw-semibold"><?= h($ex['examen_nom']) ?></td>
                                    <td class="text-end fw-bold">
                                        <?php if ($ficheOrphelin): ?>
                                        <s class="text-muted">
                                            <?= number_format($ex['cout_total'], 0, ',', ' ') ?> F
                                        </s>
                                        <span style="color:#7b1fa2;"> 0 F</span>
                                        <?php else: ?>
                                        <span style="color:#e65100;">
                                            <?= number_format($ex['cout_total'], 0, ',', ' ') ?> F
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="font-monospace small" style="color:#006064;">
                                            #<?= str_pad($ex['numero_recu'], 5, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($ex['whendone'])) ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td class="fw-bold">TOTAL EXAMENS</td>
                                        <td class="text-end fw-bold">
                                            <?php if ($ficheOrphelin): ?>
                                            <span style="color:#7b1fa2;">0 F (gratuit)</span>
                                            <?php else: ?>
                                            <span style="color:#e65100;">
                                                <?= number_format(
                                                        array_sum(array_column($examensDetail, 'cout_total')),
                                                        0, ',', ' '
                                                    ) ?> F
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                            <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-clipboard2-x fs-2 d-block mb-2"></i>
                                Aucun examen pour ce patient.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /.tab-content -->
            <?php endif; // patientDetail ?>
        </div><!-- /.col -->

    </div><!-- /.row -->
</div><!-- /.mt-4 -->

<?php
$extraJs = <<<'HEREDOC'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector('[data-bs-target="' + hash + '"]');
        if (tab) new bootstrap.Tab(tab).show();
    }
});
</script>
HEREDOC;

include ROOT_PATH . '/templates/layouts/footer.php';
?>
