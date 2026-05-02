<?php
/**
 * Module Percepteur – Interface principale (Cœur de métier)
 * AJOUT : Modification de reçu avec traçabilité (motif + historique)
 */
requireRole('percepteur', 'admin', 'comptable');
$pdo       = Database::getInstance();
$userId    = Session::getUserId();
$pageTitle = 'Espace Percepteur';

// ── Récupérer les actes médicaux configurés ────────────────────────────────
$actes         = $pdo->query("SELECT id, libelle, tarif, est_gratuit FROM actes_medicaux WHERE isDeleted=0 ORDER BY libelle")->fetchAll();
$actesGratuits = array_filter($actes, fn($a) => $a['est_gratuit']);

// ── Récupérer les examens configurés ──────────────────────────────────────
$examens = $pdo->query("SELECT id, libelle, cout_total, pourcentage_labo FROM examens WHERE isDeleted=0 ORDER BY libelle")->fetchAll();

// ── Récupérer les produits pharmacie disponibles ──────────────────────────
$produits = $pdo->query("
    SELECT id, nom, forme, prix_unitaire, stock_actuel, seuil_alerte, date_peremption,
           CASE
               WHEN stock_actuel = 0 THEN 'rupture'
               WHEN date_peremption IS NOT NULL AND date_peremption <= CURDATE() THEN 'perime'
               ELSE 'ok'
           END AS statut
    FROM produits_pharmacie
    WHERE isDeleted = 0
    ORDER BY nom
")->fetchAll();

// ── Liste journalière du percepteur connecté ───────────────────────────────
$listeJour = $pdo->prepare("
    SELECT r.id, r.numero_recu, p.nom AS patient_nom, p.telephone,
           r.type_recu, r.type_patient, r.montant_total, r.montant_encaisse,
           r.whendone, p.est_orphelin,
           r.statut_reglement, r.date_reglement, r.reglement_id,
           (SELECT COUNT(*) FROM modifications_recus mr WHERE mr.recu_id = r.id) AS nb_modifs
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.isDeleted = 0
      AND r.whodone = :uid
      AND DATE(r.whendone) = CURDATE()
    ORDER BY r.whendone DESC
");
$listeJour->execute([':uid' => $userId]);
$recusJour = $listeJour->fetchAll();

// ── Filtre archives ────────────────────────────────────────────────────────
$recusArchives = [];
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin   = $_GET['date_fin']   ?? '';
if ($dateDebut && $dateFin) {
   $stmtArch = $pdo->prepare("
    SELECT r.id, r.numero_recu, p.nom AS patient_nom, p.telephone,
           r.type_recu, r.type_patient, r.montant_total, r.montant_encaisse,
           r.whendone, r.statut_reglement, r.date_reglement,
           (SELECT COUNT(*) FROM modifications_recus mr WHERE mr.recu_id = r.id) AS nb_modifs
    FROM recus r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.isDeleted = 0
      AND r.whodone = :uid
      AND DATE(r.whendone) BETWEEN :deb AND :fin
    ORDER BY r.whendone DESC
");
    $stmtArch->execute([':uid' => $userId, ':deb' => $dateDebut, ':fin' => $dateFin]);
    $recusArchives = $stmtArch->fetchAll();
}

include ROOT_PATH . '/templates/layouts/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     STYLES SPÉCIFIQUES PERCEPTEUR
═══════════════════════════════════════════════════════════════════════════ -->
<style>
.badge-modif {
    background: #ff6f00;
    color: #fff;
    font-size: .65rem;
    padding: 2px 5px;
    border-radius: 10px;
    vertical-align: middle;
    cursor: pointer;
}
.modif-row {
    background: #fff8e1 !important;
}
.modif-row td:first-child {
    border-left: 3px solid #ff6f00;
}
.historique-item {
    border-left: 3px solid #1565c0;
    padding-left: 10px;
    margin-bottom: 8px;
}
.historique-item .date-modif {
    font-size: .78rem;
    color: #888;
}
</style>

<!-- ═══════════════════════════════════════════════════════════════════════════
     INTERFACE PERCEPTEUR
═══════════════════════════════════════════════════════════════════════════ -->
<div class="mt-4">
    <div class="d-flex align-items-center mb-4">
        <div class="bg-csi rounded-circle d-flex align-items-center justify-content-center me-3"
             style="width:50px;height:50px;">
            <i class="bi bi-person-badge text-white fs-4"></i>
        </div>
        <div>
            <h4 class="mb-0 text-csi fw-bold">Espace Percepteur</h4>
            <small class="text-muted"><?= h(Session::get('user_nom')) ?> · <?= date('l d/m/Y', strtotime('today')) ?></small>
        </div>
    </div>

    <!-- ── 3 Grands Boutons d'Action ───────────────────────────────────────── -->
    <div class="row g-3 mb-5 justify-content-center">
        <div class="col-md-4 col-lg-3 text-center">
            <button class="btn btn-normal btn-percepteur w-100"
                    data-bs-toggle="modal" data-bs-target="#modalPatient"
                    onclick="setTypeRecu('normal')">
                <i class="bi bi-person-plus"></i>
                Reçu Patient Normal
                <div class="small fw-normal mt-1 opacity-75">Consultation 300F / 400F</div>
            </button>
        </div>
        <div class="col-md-4 col-lg-3 text-center">
            <button class="btn btn-orphelin btn-percepteur w-100"
                    data-bs-toggle="modal" data-bs-target="#modalPatient"
                    onclick="setTypeRecu('orphelin')">
                <i class="bi bi-heart"></i>
                Reçu Orphelin
                <div class="small fw-normal mt-1 opacity-75"></div>
            </button>
        </div>
        <div class="col-md-4 col-lg-3 text-center">
            <button class="btn btn-gratuit btn-percepteur w-100"
                    data-bs-toggle="modal" data-bs-target="#modalActeGratuit"
                    onclick="setTypeRecu('acte_gratuit')">
                <i class="bi bi-clipboard2-heart"></i>
                Reçu Actes Gratuits
                <div class="small fw-normal mt-1 opacity-75">CPN, Nourrissons, etc.</div>
            </button>
        </div>
    </div>

    <!-- ── Liste Journalière (DataTable) ──────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header bg-csi-light">
            <h6 class="mb-0">
                <i class="bi bi-list-ul me-2"></i>
                Liste du jour
                <span class="badge bg-csi ms-2"><?= count($recusJour) ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-datatable>
                    <thead class="table-light">
                        <tr>
                            <th>N° Reçu</th>
                            <th>Nom patient</th>
                            <th>Téléphone</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Heure</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recusJour as $r): ?>
                        <tr class="<?= $r['nb_modifs'] > 0 ? 'modif-row' : '' ?>">
                            <td>
                                <span class="fw-bold text-csi">#<?= str_pad($r['numero_recu'], 5, '0', STR_PAD_LEFT) ?></span>
                                <?php if ($r['nb_modifs'] > 0): ?>
                                    <span class="badge-modif ms-1"
                                          title="<?= $r['nb_modifs'] ?> modification(s)"
                                          onclick="voirHistorique(<?= (int)$r['id'] ?>)">
                                        ✏ <?= $r['nb_modifs'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['patient_nom']) ?></td>
                            <td><small><?= h($r['telephone']) ?></small></td>
                            <td>
                                <?php
                                $badgeTypes = [
                                    'consultation' => ['color' => '#2e7d32', 'label' => 'Consultation'],
                                    'examen'       => ['color' => '#e65100', 'label' => 'Examen'],
                                    'pharmacie'    => ['color' => '#006064', 'label' => 'Pharmacie'],
                                ];
                                $bt = $badgeTypes[$r['type_recu']] ?? ['color' => '#757575', 'label' => $r['type_recu']];
                                ?>
                                <span class="badge" style="background:<?= $bt['color'] ?>"><?= $bt['label'] ?></span>
                                        <?php if ($r['type_patient'] === 'orphelin'): ?>
                                            <span class="badge bg-secondary">DirectAid</span>
                                            <?php if (($r['statut_reglement'] ?? 'en_instance') === 'regle'): ?>
                                                <span class="badge bg-success" title="Réglé le <?= date('d/m/Y', strtotime($r['date_reglement'])) ?>">
                                                    <i class="bi bi-check-circle"></i> RÉGLÉ
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark" title="En attente de règlement DirectAid ">
                                                    <i class="bi bi-hourglass-split"></i> EN INSTANCE
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                            </td>
                            <td>
                                <?php if ($r['type_patient'] === 'orphelin'): ?>
                                    <span class="text-decoration-line-through text-muted"><?= formatMontant($r['montant_total']) ?></span>
                                    <span class="fw-bold text-danger">0 F</span>
                                <?php else: ?>
                                    <span class="fw-bold"><?= formatMontant($r['montant_encaisse']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= date('H:i', strtotime($r['whendone'])) ?></small></td>
                            <td>
                                <!-- Modifier le reçu -->
                                <?php $estVerrouille = ($r['type_patient'] === 'orphelin' && ($r['statut_reglement'] ?? '') === 'regle'); ?>
                                        <?php if ($estVerrouille): ?>
                                            <button class="btn btn-sm btn-outline-secondary me-1" 
                                                    title="Reçu déjà réglé par DirectAid AMA — modification interdite" disabled>
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-warning me-1" title="Modifier ce reçu"
                                                    onclick="ouvrirModification(<?= (int)$r['id'] ?>, '<?= h($r['type_recu']) ?>')">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        <?php endif; ?>

                                <!-- Examens -->
                                <button class="btn btn-sm btn-outline-warning me-1" title="Prescrire des examens"
                                        data-bs-toggle="modal" data-bs-target="#modalExamens"
                                        onclick="openExamensModal(
                                            <?= (int)$r['id'] ?>,
                                            '<?= h($r['patient_nom']) ?>',
                                            <?= (int)$r['numero_recu'] ?>,
                                            '<?= h($r['type_patient']) ?>'
                                        )">
                                    <i class="bi bi-microscope"></i>
                                </button>
                                <!-- Pharmacie -->
                                <button class="btn btn-sm btn-outline-info me-1" title="Pharmacie"
                                        data-bs-toggle="modal" data-bs-target="#modalPharmacie"
                                        onclick="openPharmacieModal(
                                            <?= (int)$r['id'] ?>,
                                            '<?= h($r['patient_nom']) ?>',
                                            <?= (int)$r['numero_recu'] ?>,
                                            '<?= h($r['type_patient']) ?>'
                                        )">
                                    <i class="bi bi-capsule"></i>
                                </button>
                                <!-- Récapitulatif -->
                                <button class="btn btn-sm btn-outline-secondary" title="Récapitulatif"
                                        data-bs-toggle="modal" data-bs-target="#modalRecap"
                                        onclick="openRecapModal(<?= (int)$r['id'] ?>)">
                                    <i class="bi bi-file-text"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Filtres Archives ────────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header bg-csi-light">
            <h6 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Archives – Recherche par période</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="<?= url('index.php') ?>" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="percepteur">
                <div class="col-md-4">
                    <label class="form-label">Date de début</label>
                    <input type="date" class="form-control" name="date_debut" value="<?= h($dateDebut) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de fin</label>
                    <input type="date" class="form-control" name="date_fin" value="<?= h($dateFin) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn text-white w-100" style="background:var(--csi-green);">
                        <i class="bi bi-search me-1"></i>Rechercher
                    </button>
                </div>
            </form>

            <?php if ($recusArchives): ?>
            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle" data-datatable>
                    <thead class="table-light">
                        <tr>
                            <th>N° Reçu</th>
                            <th>Patient</th>
                            <th>Tél.</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Modif.</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recusArchives as $r): ?>
                        <tr class="<?= $r['nb_modifs'] > 0 ? 'modif-row' : '' ?>">
                            <td>
                                <strong>#<?= str_pad($r['numero_recu'], 5, '0', STR_PAD_LEFT) ?></strong>
                                <?php if ($r['nb_modifs'] > 0): ?>
                                    <span class="badge-modif ms-1"
                                          onclick="voirHistorique(<?= (int)$r['id'] ?>)">
                                        ✏ <?= $r['nb_modifs'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['patient_nom']) ?></td>
                            <td><?= h($r['telephone']) ?></td>
                            <td><?= h(ucfirst($r['type_recu'])) ?></td>
                            <td><?= $r['type_patient'] === 'orphelin'
                                    ? '<span class="text-danger fw-bold">0 F (GRATUIT)</span>'
                                    : formatMontant($r['montant_encaisse']) ?></td>
                            <td><?= formatDate($r['whendone']) ?></td>
                            <td>
                                    <?php if ($r['type_patient'] === 'orphelin'): ?>
                                        <?php if (($r['statut_reglement'] ?? 'en_instance') === 'regle'): ?>
                                            <span class="badge bg-success">RÉGLÉ</span>
                                            <?php if ($r['date_reglement']): ?>
                                                <br><small class="text-muted"><?= date('d/m/Y', strtotime($r['date_reglement'])) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">EN INSTANCE</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">—</span>
                                    <?php endif; ?>
                                </td>

                            <td>
                                <?php if ($r['nb_modifs'] > 0): ?>
                                    <button class="btn btn-xs btn-outline-warning"
                                            onclick="voirHistorique(<?= (int)$r['id'] ?>)"
                                            title="Voir l'historique des modifications">
                                        <i class="bi bi-clock-history"></i> <?= $r['nb_modifs'] ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php elseif ($dateDebut && $dateFin): ?>
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle me-2"></i>Aucune opération trouvée pour cette période.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL : Formulaire Patient (Normal / Orphelin) — INCHANGÉ
═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPatient" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" id="modalPatientHeader">
                <h5 class="modal-title" id="modalPatientTitle"><i class="bi bi-person-plus me-2"></i>Nouveau patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formPatient" novalidate>
                    <input type="hidden" id="typeRecuHidden" name="type_patient" value="normal">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                Téléphone <span class="text-danger">*</span>
                                <small class="text-muted">(autocomplete dès 3 chiffres)</small>
                            </label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="fTelephone" name="telephone"
                                       placeholder="Ex: 90 00 00 00" required autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom et Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fNomPatient" name="nom"
                                   placeholder="Ex: Moussa Halima" required>
                        </div>
                        <div class="col-md-4" id="sexeBlock">
                            <label class="form-label">Sexe <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="sexe" id="sexeM" value="M" checked>
                                    <label class="form-check-label" for="sexeM">Masculin</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="sexe" id="sexeF" value="F">
                                    <label class="form-check-label" for="sexeF">Féminin</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Âge <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="fAge" name="age"
                                   min="0" max="120" placeholder="0" required>
                        </div>
                        <div class="col-md-4" id="provenanceBlock">
                            <label class="form-label">Provenance</label>
                            <input type="text" class="form-control" id="fProvenance" name="provenance"
                                   placeholder="Ville / Village">
                        </div>
                        <div class="col-12" id="typeConsultBlock">
                            <label class="form-label">Type de consultation <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check border rounded p-3 h-100 typeConsultOption" id="optAvecCarnet">
                                        <input class="form-check-input" type="radio" name="avec_carnet" id="consAvec" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="consAvec">
                                            Consultation + Carnet de Soins
                                            <div class="text-success">300 F + 100 F = <strong>400 F</strong></div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check border rounded p-3 h-100 typeConsultOption" id="optSansCarnet">
                                        <input class="form-check-input" type="radio" name="avec_carnet" id="consSans" value="0">
                                        <label class="form-check-label fw-semibold" for="consSans">
                                            Consultation sans Carnet
                                            <div class="text-success"><strong>300 F</strong></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="gratuitBanner">
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-gift me-2"></i>
                                <strong>ORPHELIN </strong>
                                Le montant encaissé sera <strong>0 F</strong>.
                                Les prix sont conservés pour le reporting bailleur.
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn text-white fw-bold" id="btnEnregistrerPatient"
                        style="background:var(--csi-green);">
                    <i class="bi bi-printer me-1"></i>Enregistrer & Imprimer Reçu
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL : Actes Gratuits — INCHANGÉ
═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalActeGratuit" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1565c0;">
                <h5 class="modal-title text-white"><i class="bi bi-clipboard2-heart me-2"></i>Reçu Actes Gratuits</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formActeGratuit" novalidate>
                    <input type="hidden" name="type_patient" value="acte_gratuit">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="fTelAG" name="telephone" required autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom et Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fNomAG" name="nom" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sexe</label>
                            <select class="form-select" name="sexe" id="fSexeAG">
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Âge <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="fAgeAG" name="age" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Provenance</label>
                            <input type="text" class="form-control" name="provenance">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Acte gratuit <span class="text-danger">*</span></label>
                            <select class="form-select" name="acte_id" required>
                                <option value="">-- Sélectionner un acte --</option>
                                <?php foreach ($actesGratuits as $a): ?>
                                <option value="<?= (int)$a['id'] ?>"><?= h($a['libelle']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn text-white fw-bold" style="background:#1565c0;"
                        onclick="saveActeGratuit()">
                    <i class="bi bi-printer me-1"></i>Enregistrer & Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL : Examens — INCHANGÉ
═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalExamens" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#e65100;">
                <h5 class="modal-title text-white"><i class="bi bi-microscope me-2"></i>Prescription d'examens</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    Patient : <strong id="examPatientNom"></strong>
                    · Reçu N° <span id="examNumeroRecu"></span>
                </div>
                <div class="alert alert-warning d-none mb-2" id="examGratuitBanner">
                    <i class="bi bi-gift me-2"></i>
                    <strong>ORPHELIN – GRATUITÉ TOTALE.</strong>
                    Les examens seront enregistrés à <strong>0 F</strong> encaissé.
                </div>
                <input type="hidden" id="examRecuId">
                <label class="form-label">Sélectionner les examens à prescrire</label>
                <div class="row g-2" id="examensCheckboxes">
                    <?php foreach ($examens as $e): ?>
                    <div class="col-md-6">
                        <div class="form-check border rounded p-2">
                            <input class="form-check-input examen-chk" type="checkbox"
                                   name="examens[]" value="<?= (int)$e['id'] ?>"
                                   id="ex<?= $e['id'] ?>"
                                   data-cout="<?= (int)$e['cout_total'] ?>"
                                   data-libelle="<?= h($e['libelle']) ?>">
                            <label class="form-check-label w-100" for="ex<?= $e['id'] ?>">
                                <strong><?= h($e['libelle']) ?></strong>
                                <span class="badge float-end examen-prix-badge"
                                      style="background:#e65100;"
                                      data-prix-original="<?= formatMontant($e['cout_total']) ?>">
                                    <?= formatMontant($e['cout_total']) ?>
                                </span>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 p-2 bg-light rounded d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Sous-total examens :</span>
                    <span class="fw-bold fs-5 text-csi" id="sousTotal">0 F</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn text-white fw-bold" style="background:#e65100;"
                        onclick="saveExamens()">
                    <i class="bi bi-printer me-1"></i>Valider & Imprimer Reçu Examens
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL : Pharmacie — INCHANGÉ
═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPharmacie" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:#006064;">
                <h5 class="modal-title text-white"><i class="bi bi-capsule me-2"></i>Délivrance Pharmacie</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    Patient : <strong id="pharmaPatientNom"></strong>
                    · Reçu N° <span id="pharmaNumeroRecu"></span>
                    <span class="float-end text-muted">Max 15 produits</span>
                </div>
                <div class="alert alert-warning d-none mb-2" id="pharmaGratuitBanner">
                    <i class="bi bi-gift me-2"></i>
                    <strong>ORPHELIN – GRATUITÉ TOTALE.</strong>
                    La pharmacie sera enregistrée à <strong>0 F</strong> encaissé.
                    Le stock sera quand même mis à jour.
                </div>
                <input type="hidden" id="pharmaRecuId" data-orphelin="0">
                <div class="row g-2 mb-3" id="produitsList">
                    <?php foreach ($produits as $p):
                        $disabled = ($p['statut'] !== 'ok');
                        $label    = match($p['statut']) {
                            'rupture' => '⚠ Rupture de stock',
                            'perime'  => '⛔ Périmé',
                            default   => ''
                        };
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 <?= $disabled ? 'opacity-50 border-danger' : '' ?> <?= $p['stock_actuel'] <= $p['seuil_alerte'] && !$disabled ? 'border-warning' : '' ?>">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="small"><?= h($p['nom']) ?></strong>
                                        <div class="text-muted" style="font-size:.78rem;"><?= h($p['forme']) ?></div>
                                        <div class="fw-bold text-csi small"><?= formatMontant($p['prix_unitaire']) ?>/unité</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= $disabled ? 'bg-danger' : ($p['stock_actuel'] <= $p['seuil_alerte'] ? 'bg-warning text-dark' : 'bg-success') ?>">
                                            <?= $disabled ? $label : 'Stock: ' . $p['stock_actuel'] ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!$disabled): ?>
                                <div class="mt-2 input-group input-group-sm">
                                    <span class="input-group-text">Qté</span>
                                    <input type="number" class="form-control produit-qte"
                                           min="0" max="<?= (int)$p['stock_actuel'] ?>" value="0"
                                           data-id="<?= (int)$p['id'] ?>"
                                           data-nom="<?= h($p['nom']) ?>"
                                           data-forme="<?= h($p['forme']) ?>"
                                           data-prix="<?= (int)$p['prix_unitaire'] ?>"
                                           oninput="updateTotalPharma()">
                                </div>
                                <?php else: ?>
                                <div class="mt-2 text-danger small"><i class="bi bi-x-circle me-1"></i><?= $label ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                    <span>
                        <strong>Produits sélectionnés : </strong>
                        <span id="nbProduitsSelec" class="badge bg-secondary">0</span> / 15 max
                    </span>
                    <span class="fw-bold fs-5 text-csi">
                        Total : <span id="totalPharma">0 F</span>
                    </span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn text-white fw-bold" style="background:#006064;"
                        onclick="savePharmacie()">
                    <i class="bi bi-printer me-1"></i>Valider & Imprimer Reçu Pharmacie
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL : Récapitulatif Patient — INCHANGÉ
═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalRecap" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-file-text me-2"></i>Récapitulatif Patient</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="recapContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-secondary"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL : MODIFICATION D'UN REÇU (NOUVEAU)
     - Chargement dynamique du formulaire selon type_recu (consultation/examen/pharmacie)
     - Motif de modification obligatoire
     - Affichage de l'historique des modifications
═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalModification" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#ff6f00;">
                <h5 class="modal-title text-white">
                    <i class="bi bi-pencil-square me-2"></i>
                    Modifier le reçu <span id="modifNumeroRecu"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Alerte info -->
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Toute modification est <strong>tracée</strong> avec votre identifiant,
                    l'heure et le motif. Cette action est <strong>irréversible</strong>.
                </div>

                <!-- Chargement dynamique du formulaire de modification -->
                <div id="modifFormContainer">
                    <div class="text-center py-4">
                        <div class="spinner-border text-warning"></div>
                    </div>
                </div>

                <!-- Motif de modification — OBLIGATOIRE -->
                <div class="mt-3">
                    <label class="form-label fw-bold text-danger">
                        Motif de la modification <span class="text-danger">*</span>
                    </label>
                    <select class="form-select mb-2" id="modifMotifSelect" onchange="toggleMotifAutre()">
                        <option value="">-- Sélectionner un motif --</option>
                        <option value="Erreur de saisie (montant)">Erreur de saisie (montant)</option>
                        <option value="Erreur de saisie (patient)">Erreur de saisie (patient)</option>
                        <option value="Erreur produit/examen sélectionné">Erreur produit/examen sélectionné</option>
                        <option value="Quantité incorrecte">Quantité incorrecte</option>
                        <option value="Doublon supprimé">Doublon supprimé</option>
                        <option value="Correction type patient">Correction type patient</option>
                        <option value="autre">Autre (préciser ci-dessous)</option>
                    </select>
                    <textarea class="form-control d-none" id="modifMotifAutre" rows="2"
                              placeholder="Précisez le motif…" maxlength="500"></textarea>
                    <div class="form-text text-muted">
                        Ce motif sera visible dans l'historique et les rapports.
                    </div>
                </div>

                <!-- Champs cachés -->
                <input type="hidden" id="modifRecuId">
                <input type="hidden" id="modifTypeRecu">

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn text-white fw-bold" style="background:#ff6f00;"
                        onclick="validerModification()">
                    <i class="bi bi-check-circle me-1"></i>Valider la modification
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL : HISTORIQUE DES MODIFICATIONS (NOUVEAU)
═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalHistorique" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1565c0;">
                <h5 class="modal-title text-white">
                    <i class="bi bi-clock-history me-2"></i>
                    Historique des modifications — Reçu <span id="histNumeroRecu"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historiqueContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php
$saveConsultUrl   = url('modules/percepteur/save_consultation.php');
$saveActeGratUrl  = url('modules/percepteur/save_acte_gratuit.php');
$saveExamensUrl   = url('modules/percepteur/save_examens.php');
$savePharmUrl     = url('modules/percepteur/save_pharmacie.php');
$getRecapUrl      = url('modules/percepteur/get_recap.php');

// ── CORRECTION : même dossier que save_consultation.php qui fonctionne ──
$getModifFormUrl  = url('modules/percepteur/ajax_get_modif_form.php');
$saveModifUrl     = url('modules/percepteur/ajax_save_modification.php');
$getHistoriqueUrl = url('modules/percepteur/ajax_get_historique.php');

$jsUrls = [
    'SAVE_CONSULT_URL'   => $saveConsultUrl,
    'SAVE_ACTE_GRAT_URL' => $saveActeGratUrl,
    'SAVE_EXAMENS_URL'   => $saveExamensUrl,
    'SAVE_PHARMA_URL'    => $savePharmUrl,
    'GET_RECAP_URL'      => $getRecapUrl,
    'GET_MODIF_FORM_URL' => $getModifFormUrl,
    'SAVE_MODIF_URL'     => $saveModifUrl,
    'GET_HISTORIQUE_URL' => $getHistoriqueUrl,
];

$jsUrlDeclarations = '';
foreach ($jsUrls as $constName => $urlValue) {
    $jsUrlDeclarations .= 'const ' . $constName . ' = ' . json_encode($urlValue) . ";\n";
}
?>


<script>
<?= $jsUrlDeclarations ?>

let currentTypeRecu = 'normal';

// ══════════════════════════════════════════════════════════════════════════
//  TOUT LE CODE EST ENCAPSULÉ DANS DOMContentLoaded
//  → garantit que app.js (initPhoneAutocomplete, ajaxPost, showToast…)
//    est chargé AVANT toute exécution
// ══════════════════════════════════════════════════════════════════════════
window.addEventListener('load', function () {

    // ── setTypeRecu ──────────────────────────────────────────────────────
    window.setTypeRecu = function(type) {
        currentTypeRecu = type;
        const header        = document.getElementById('modalPatientHeader');
        const title         = document.getElementById('modalPatientTitle');
        const block         = document.getElementById('typeConsultBlock');
        const banner        = document.getElementById('gratuitBanner');
        const sexeBlock     = document.getElementById('sexeBlock');
        const provenanceBlk = document.getElementById('provenanceBlock');

        document.getElementById('fTelephone').value  = '';
        document.getElementById('fNomPatient').value = '';
        document.getElementById('fAge').value        = '';
        document.getElementById('consAvec').checked  = true;

        if (type === 'orphelin') {
            header.style.background     = '#7b1fa2';
            title.innerHTML             = '<i class="bi bi-heart me-2"></i>Reçu Orphelin';
            block.style.display         = 'none';
            sexeBlock.style.display     = 'none';
            provenanceBlk.style.display = 'none';
            banner.classList.remove('d-none');
            document.getElementById('sexeM').checked     = true;
            document.getElementById('fProvenance').value = 'Maradi';
        } else {
            header.style.background     = 'var(--csi-green)';
            title.innerHTML             = '<i class="bi bi-person-plus me-2"></i>Nouveau Patient Normal';
            block.style.display         = '';
            sexeBlock.style.display     = '';
            provenanceBlk.style.display = '';
            banner.classList.add('d-none');
            document.getElementById('fProvenance').value = '';
        }
        document.getElementById('typeRecuHidden').value = type;
    };

    // ── Autocomplete téléphone (protégé) ─────────────────────────────────
    if (typeof initPhoneAutocomplete === 'function') {
        initPhoneAutocomplete('fTelephone', function(p) {
            document.getElementById('fNomPatient').value = p.nom;
            document.getElementById('fAge').value        = p.age;
            if (currentTypeRecu !== 'orphelin') {
                document.getElementById('fProvenance').value = p.provenance || '';
                const sexeRadio = document.querySelector('input[name="sexe"][value="' + p.sexe + '"]');
                if (sexeRadio) sexeRadio.checked = true;
            }
        });
        initPhoneAutocomplete('fTelAG', function(p) {
            document.getElementById('fNomAG').value = p.nom;
            document.getElementById('fAgeAG').value = p.age;
        });
    } else {
        console.warn('initPhoneAutocomplete non chargée (app.js absent ?)');
    }

    // ── Bouton Enregistrer Patient ───────────────────────────────────────
    document.getElementById('btnEnregistrerPatient').addEventListener('click', function() {
        const form = document.getElementById('formPatient');
        const data = Object.fromEntries(new FormData(form));
        if (!data.telephone || !data.nom || data.age === '' || data.age === undefined) {
            showToast('warning', 'Veuillez remplir tous les champs obligatoires (téléphone, nom, âge).');
            return;
        }
        if (data.telephone.replace(/\D/g, '').length !== 8) {
            showToast('warning', 'Le numéro de téléphone doit contenir exactement 8 chiffres.');
            return;
        }
        ajaxPost(SAVE_CONSULT_URL, data, function(res) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPatient')).hide();
            if (res.pdf_url) window.open(res.pdf_url, '_blank');
            setTimeout(() => location.reload(), 1000);
        });
    });

    // ── Acte gratuit ─────────────────────────────────────────────────────
    window.saveActeGratuit = function() {
        const form = document.getElementById('formActeGratuit');
        const data = Object.fromEntries(new FormData(form));
        if (!data.telephone || !data.nom || !data.acte_id) {
            showToast('warning', 'Champs obligatoires manquants (téléphone, nom, acte).');
            return;
        }
        if (data.telephone.replace(/\D/g, '').length !== 8) {
            showToast('warning', 'Le numéro de téléphone doit contenir exactement 8 chiffres.');
            return;
        }
        ajaxPost(SAVE_ACTE_GRAT_URL, data, function(res) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalActeGratuit')).hide();
            if (res.pdf_url) window.open(res.pdf_url, '_blank');
            setTimeout(() => location.reload(), 1000);
        });
    };

    // ── Examens ──────────────────────────────────────────────────────────
    window.openExamensModal = function(recuId, nom, num, typePatient) {
        document.getElementById('examRecuId').value           = recuId;
        document.getElementById('examPatientNom').textContent = nom;
        document.getElementById('examNumeroRecu').textContent = '#' + String(num).padStart(5, '0');
        document.querySelectorAll('.examen-chk').forEach(c => c.checked = false);

        const isOrphelin = (typePatient === 'orphelin');
        const banner     = document.getElementById('examGratuitBanner');

        if (isOrphelin) {
            banner.classList.remove('d-none');
            document.querySelectorAll('.examen-prix-badge').forEach(function(badge) {
                badge.innerHTML = '<s>' + badge.dataset.prixOriginal + '</s> <strong>0 F</strong>';
            });
        } else {
            banner.classList.add('d-none');
            document.querySelectorAll('.examen-prix-badge').forEach(function(badge) {
                badge.textContent = badge.dataset.prixOriginal;
            });
        }

        document.getElementById('examRecuId').dataset.orphelin = isOrphelin ? '1' : '0';
        updateSousTotal();
    };

    document.querySelectorAll('.examen-chk').forEach(chk => {
        chk.addEventListener('change', updateSousTotal);
    });

    function updateSousTotal() {
        const isOrphelin = document.getElementById('examRecuId').dataset.orphelin === '1';
        let total = 0;
        document.querySelectorAll('.examen-chk:checked').forEach(c => total += parseInt(c.dataset.cout));

        if (isOrphelin && total > 0) {
            document.getElementById('sousTotal').innerHTML =
                '<s>' + new Intl.NumberFormat('fr-FR').format(total) + ' F</s> ' +
                '<strong class="text-danger ms-1">0 F (GRATUIT)</strong>';
        } else {
            document.getElementById('sousTotal').textContent =
                new Intl.NumberFormat('fr-FR').format(total) + ' F';
        }
    }
    window.updateSousTotal = updateSousTotal;

    window.saveExamens = function() {
        const ids    = [...document.querySelectorAll('.examen-chk:checked')].map(c => c.value);
        const recuId = document.getElementById('examRecuId').value;
        if (!ids.length) { showToast('warning', 'Veuillez sélectionner au moins un examen.'); return; }
        if (!recuId)     { showToast('warning', 'Aucun reçu de consultation lié.');           return; }
        ajaxPost(SAVE_EXAMENS_URL, { recu_id: recuId, examens: ids.join(',') }, function(res) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalExamens')).hide();
            if (res.pdf_url) window.open(res.pdf_url, '_blank');
            setTimeout(() => location.reload(), 1000);
        });
    };

    // ── Pharmacie ────────────────────────────────────────────────────────
    window.openPharmacieModal = function(recuId, nom, num, typePatient) {
        const isOrphelin = (typePatient === 'orphelin');
        document.getElementById('pharmaRecuId').value            = recuId;
        document.getElementById('pharmaRecuId').dataset.orphelin = isOrphelin ? '1' : '0';
        document.getElementById('pharmaPatientNom').textContent  = nom;
        document.getElementById('pharmaNumeroRecu').textContent  = '#' + String(num).padStart(5, '0');
        document.querySelectorAll('.produit-qte').forEach(i => i.value = 0);

        const banner = document.getElementById('pharmaGratuitBanner');
        if (isOrphelin) banner.classList.remove('d-none');
        else            banner.classList.add('d-none');
        updateTotalPharma();
    };

    window.updateTotalPharma = function() {
        const isOrphelin = document.getElementById('pharmaRecuId').dataset.orphelin === '1';
        let total = 0, count = 0;
        document.querySelectorAll('.produit-qte').forEach(inp => {
            const qty = parseInt(inp.value) || 0;
            if (qty > 0) { total += qty * parseInt(inp.dataset.prix); count++; }
        });

        if (isOrphelin && total > 0) {
            document.getElementById('totalPharma').innerHTML =
                '<s>' + new Intl.NumberFormat('fr-FR').format(total) + ' F</s> ' +
                '<strong class="text-danger ms-1">0 F (GRATUIT)</strong>';
        } else {
            document.getElementById('totalPharma').textContent =
                new Intl.NumberFormat('fr-FR').format(total) + ' F';
        }
        document.getElementById('nbProduitsSelec').textContent = count;
        document.getElementById('nbProduitsSelec').className   =
            'badge ' + (count > 15 ? 'bg-danger' : (count > 0 ? 'bg-success' : 'bg-secondary'));
    };

    window.savePharmacie = function() {
        const items  = [];
        const recuId = document.getElementById('pharmaRecuId').value;
        document.querySelectorAll('.produit-qte').forEach(inp => {
            const qty = parseInt(inp.value) || 0;
            if (qty > 0) items.push({
                id: inp.dataset.id, qte: qty,
                nom: inp.dataset.nom, forme: inp.dataset.forme, prix: inp.dataset.prix
            });
        });
        if (!items.length)     { showToast('warning', 'Aucun produit sélectionné.');      return; }
        if (items.length > 15) { showToast('danger',  'Maximum 15 produits par reçu.');   return; }
        if (!recuId)           { showToast('warning', 'Aucun reçu de consultation lié.'); return; }
        ajaxPost(SAVE_PHARMA_URL, { recu_id: recuId, produits: JSON.stringify(items) }, function(res) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPharmacie')).hide();
            if (res.pdf_url) window.open(res.pdf_url, '_blank');
            setTimeout(() => location.reload(), 1000);
        });
    };

    // ── Récap ────────────────────────────────────────────────────────────
    window.openRecapModal = function(recuId) {
        document.getElementById('recapContent').innerHTML =
            '<div class="text-center py-4"><div class="spinner-border text-secondary"></div></div>';
        fetch(GET_RECAP_URL + '?recu_id=' + recuId, {
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
        })
        .then(r => r.json())
        .then(data => {
            if (data.html) document.getElementById('recapContent').innerHTML = data.html;
            else           document.getElementById('recapContent').innerHTML = '<p class="text-muted">Aucune donnée.</p>';
        });
    };

    // ══════════════════════════════════════════════════════════════════════
    //  MODIFICATION & HISTORIQUE
    // ══════════════════════════════════════════════════════════════════════

    window.ouvrirModification = function(recuId, typeRecu) {
        document.getElementById('modifRecuId').value      = recuId;
        document.getElementById('modifTypeRecu').value    = typeRecu;
        document.getElementById('modifMotifSelect').value = '';
        document.getElementById('modifMotifAutre').value  = '';
        document.getElementById('modifMotifAutre').classList.add('d-none');

        document.getElementById('modifFormContainer').innerHTML =
            '<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>';

        const url = GET_MODIF_FORM_URL
            + '?recu_id=' + encodeURIComponent(recuId)
            + '&type='    + encodeURIComponent(typeRecu);

        fetch(url, { credentials: 'same-origin' })
        .then(function(r) {
            const ct = r.headers.get('Content-Type') || '';
            if (!r.ok) throw new Error('HTTP ' + r.status + ' — ' + r.statusText);
            if (!ct.includes('application/json')) {
                return r.text().then(function(t) {
                    throw new Error('Réponse non-JSON : ' + t.substring(0, 300));
                });
            }
            return r.json();
        })
        .then(function(data) {
            if (data.html) {
                document.getElementById('modifFormContainer').innerHTML = data.html;
                document.getElementById('modifNumeroRecu').textContent =
                    '#' + String(data.numero_recu || recuId).padStart(5, '0');
                if (typeRecu === 'pharmacie') initModifPharmacieEvents();
            } else {
                document.getElementById('modifFormContainer').innerHTML =
                    '<div class="alert alert-danger">Erreur : ' + (data.error || '?') + '</div>';
            }
        })
        .catch(function(err) {
            document.getElementById('modifFormContainer').innerHTML =
                '<div class="alert alert-danger"><strong>Erreur :</strong> ' + err.message + '</div>';
        });

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalModification')).show();
    };

    window.toggleMotifAutre = function() {
        const sel = document.getElementById('modifMotifSelect');
        const txt = document.getElementById('modifMotifAutre');
        if (sel.value === 'autre') {
            txt.classList.remove('d-none');
            txt.focus();
        } else {
            txt.classList.add('d-none');
        }
    };

    function initModifPharmacieEvents() {
        document.querySelectorAll('.modif-produit-qte').forEach(function(inp) {
            inp.addEventListener('input', updateModifTotalPharma);
        });
        updateModifTotalPharma();
    }

    function updateModifTotalPharma() {
        let total = 0, count = 0;
        document.querySelectorAll('.modif-produit-qte').forEach(function(inp) {
            const qty = parseInt(inp.value) || 0;
            const td  = inp.closest('tr') ? inp.closest('tr').querySelector('.modif-ligne-total') : null;
            if (qty > 0) {
                const ligneTot = qty * (parseInt(inp.dataset.prix) || 0);
                total += ligneTot;
                count++;
                if (td) td.textContent = new Intl.NumberFormat('fr-FR').format(ligneTot) + ' F';
            } else {
                if (td) td.textContent = '0 F';
            }
        });
        const totalEl = document.getElementById('modifTotalPharma');
        if (totalEl) totalEl.textContent = new Intl.NumberFormat('fr-FR').format(total) + ' F';
        const countEl = document.getElementById('modifNbProduits');
        if (countEl) {
            countEl.textContent = count;
            countEl.className   = 'badge ' + (count > 15 ? 'bg-danger' : count > 0 ? 'bg-success' : 'bg-secondary');
        }
    }

    window.validerModification = function() {
        const recuId   = document.getElementById('modifRecuId').value;
        const typeRecu = document.getElementById('modifTypeRecu').value;
        const motifSel = document.getElementById('modifMotifSelect').value;
        const motifTxt = document.getElementById('modifMotifAutre').value.trim();

        if (!motifSel) {
            showToast('warning', 'Veuillez sélectionner un motif de modification.');
            return;
        }
        const motifFinal = (motifSel === 'autre') ? motifTxt : motifSel;
        if (!motifFinal) {
            showToast('warning', 'Veuillez préciser le motif de modification.');
            return;
        }

        const container = document.getElementById('modifFormContainer');
        if (container.querySelector('.spinner-border')) {
            showToast('warning', 'Le formulaire est encore en cours de chargement, patientez.');
            return;
        }

        let payload = { recu_id: recuId, type_recu: typeRecu, motif: motifFinal };

        if (typeRecu === 'consultation') {
            const avecCarnet = container.querySelector('input[name="modif_avec_carnet"]:checked');
            if (!avecCarnet) {
                showToast('warning', 'Veuillez sélectionner le type de consultation.');
                return;
            }
            payload.avec_carnet = avecCarnet.value;

        } else if (typeRecu === 'examen') {
            const chks = container.querySelectorAll('.modif-examen-chk:checked');
            if (chks.length === 0) {
                showToast('warning', 'Veuillez sélectionner au moins un examen.');
                return;
            }
            payload.examens = [...chks].map(c => c.value).join(',');

        } else if (typeRecu === 'pharmacie') {
            const items = [];
            container.querySelectorAll('.modif-produit-qte').forEach(function(inp) {
                const qty = parseInt(inp.value) || 0;
                if (qty > 0) items.push({ id: inp.dataset.id, qte: qty });
            });
            if (items.length === 0) {
                showToast('warning', 'Veuillez saisir au moins une quantité.');
                return;
            }
            if (items.length > 15) {
                showToast('danger', 'Maximum 15 produits par reçu.');
                return;
            }
            payload.produits = JSON.stringify(items);
        }

        if (!confirm('Confirmer la modification ?\nMotif : ' + motifFinal + '\n\nCette action sera enregistrée dans l\'historique.')) {
            return;
        }

        ajaxPost(SAVE_MODIF_URL, payload, function(res) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalModification')).hide();
            showToast('success', 'Modification enregistrée avec succès.');
            if (res.pdf_url) window.open(res.pdf_url, '_blank');
            setTimeout(() => location.reload(), 1200);
        });
    };

    window.voirHistorique = function(recuId) {
        document.getElementById('historiqueContent').innerHTML =
            '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        document.getElementById('histNumeroRecu').textContent =
            '#' + String(recuId).padStart(5, '0');

        const url = GET_HISTORIQUE_URL + '?recu_id=' + encodeURIComponent(recuId);

        fetch(url, { credentials: 'same-origin' })
        .then(function(r) {
            const ct = r.headers.get('Content-Type') || '';
            if (!r.ok) throw new Error('HTTP ' + r.status);
            if (!ct.includes('application/json')) {
                return r.text().then(function(t) {
                    throw new Error('Non-JSON : ' + t.substring(0, 300));
                });
            }
            return r.json();
        })
        .then(function(data) {
            if (data.html) {
                document.getElementById('historiqueContent').innerHTML = data.html;
            } else {
                document.getElementById('historiqueContent').innerHTML =
                    '<p class="text-muted text-center py-3">Aucune modification trouvée.</p>';
            }
        })
        .catch(function(err) {
            document.getElementById('historiqueContent').innerHTML =
                '<div class="alert alert-danger"><strong>Erreur :</strong> ' + err.message + '</div>';
        });

        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHistorique')).show();
    };

}); // fin DOMContentLoaded
</script>
<?php require __DIR__ . '/../../templates/layouts/footer.php'; ?>
