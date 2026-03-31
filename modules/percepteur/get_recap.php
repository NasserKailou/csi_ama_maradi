<?php
/**
 * API : Récapitulatif patient (toutes opérations du jour)
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('percepteur', 'admin', 'comptable');
header('Content-Type: application/json');

$pdo    = Database::getInstance();
$userId = Session::getUserId();
$recuId = (int)($_GET['recu_id'] ?? 0);

if (!$recuId) jsonError('ID reçu manquant.');

// Récupérer le patient lié à ce reçu (isolation stricte)
$stmtR = $pdo->prepare("
    SELECT r.patient_id, p.nom, p.telephone, p.provenance
    FROM recus r JOIN patients p ON p.id = r.patient_id
    WHERE r.id = :id AND r.whodone = :uid AND r.isDeleted = 0
    LIMIT 1
");
$stmtR->execute([':id' => $recuId, ':uid' => $userId]);
$info = $stmtR->fetch();
if (!$info) jsonError('Accès refusé.');

// Toutes les opérations du jour pour ce patient, par ce percepteur
$stmtAll = $pdo->prepare("
    SELECT r.numero_recu, r.type_recu, r.type_patient, r.montant_total, r.montant_encaisse, r.whendone
    FROM recus r
    WHERE r.patient_id = :pid AND r.whodone = :uid AND r.isDeleted = 0
      AND DATE(r.whendone) = CURDATE()
    ORDER BY r.whendone ASC
");
$stmtAll->execute([':pid' => $info['patient_id'], ':uid' => $userId]);
$ops = $stmtAll->fetchAll();

$totalJour    = array_sum(array_column($ops, 'montant_encaisse'));

// Construire le HTML de récapitulatif
ob_start();
?>
<div class="p-3">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h6 class="fw-bold mb-1"><?= h($info['nom']) ?></h6>
            <div class="text-muted small">
                <i class="bi bi-telephone me-1"></i><?= h($info['telephone']) ?>
                <?php if ($info['provenance']): ?>
                · <i class="bi bi-geo-alt me-1"></i><?= h($info['provenance']) ?>
                <?php endif; ?>
            </div>
        </div>
        <span class="badge bg-success"><?= date('d/m/Y') ?></span>
    </div>

    <table class="table table-sm table-bordered mb-3">
        <thead class="table-light">
            <tr>
                <th>N° Reçu</th>
                <th>Type</th>
                <th>Heure</th>
                <th class="text-end">Montant dû</th>
                <th class="text-end">Encaissé</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ops as $op):
            $typeLbl = match($op['type_recu']) {
                'consultation' => '<span class="badge" style="background:#2e7d32">Consultation</span>',
                'examen'       => '<span class="badge" style="background:#e65100">Examen</span>',
                'pharmacie'    => '<span class="badge" style="background:#006064">Pharmacie</span>',
                default        => h($op['type_recu'])
            };
        ?>
        <tr>
            <td><strong>#<?= str_pad($op['numero_recu'], 5, '0', STR_PAD_LEFT) ?></strong></td>
            <td>
                <?= $typeLbl ?>
                <?php if ($op['type_patient'] === 'orphelin'): ?>
                    <span class="badge bg-secondary ms-1">GRATUIT</span>
                <?php endif; ?>
            </td>
            <td class="text-muted small"><?= date('H:i', strtotime($op['whendone'])) ?></td>
            <td class="text-end"><?= formatMontant($op['montant_total']) ?></td>
            <td class="text-end fw-bold">
                <?php if ($op['type_patient'] === 'orphelin'): ?>
                    <span class="text-danger">0 F</span>
                <?php else: ?>
                    <?= formatMontant($op['montant_encaisse']) ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="4" class="text-end">TOTAL ENCAISSÉ :</td>
                <td class="text-end text-success fs-5"><?= formatMontant($totalJour) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php
$html = ob_get_clean();
jsonSuccess('OK', ['html' => $html]);
