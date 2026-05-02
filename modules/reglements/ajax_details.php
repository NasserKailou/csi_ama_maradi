<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole('admin', 'comptable');
$pdo = Database::getInstance();
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
            <td><?= h($r['numero_recu']) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($r['whendone'])) ?></td>
            <td><span class="badge bg-info"><?= h($r['type_recu']) ?></span></td>
            <td class="text-end"><strong><?= number_format((float)$r['montant_total'],0,',',' ') ?></strong></td>
            <td><small><?= h(($r['perc_nom']??'').' '.($r['perc_prenom']??'')) ?></small></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
