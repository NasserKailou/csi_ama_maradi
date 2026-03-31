<?php
/**
 * État de paie laborantin – Génération PDF
 */
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/core/autoload.php';
require_once ROOT_PATH . '/core/helpers.php';

Session::start();
requireRole('admin', 'comptable');
header('Content-Type: application/json');

$dateDebut = $_GET['date_debut'] ?? '';
$dateFin   = $_GET['date_fin']   ?? '';

if (!$dateDebut || !$dateFin) jsonError('Dates obligatoires.');
if ($dateFin < $dateDebut) jsonError('Date fin doit être ≥ date début.');

$pdo  = Database::getInstance();
$stmt = $pdo->prepare("
    SELECT le.examen_id, le.libelle, COUNT(*) AS nb_actes,
           SUM(le.cout_total) AS total_brut,
           le.pourcentage_labo,
           SUM(le.montant_labo) AS total_labo
    FROM lignes_examen le
    JOIN recus r ON r.id = le.recu_id
    WHERE r.isDeleted = 0 AND le.isDeleted = 0
      AND DATE(r.whendone) BETWEEN :deb AND :fin
    GROUP BY le.examen_id, le.libelle, le.pourcentage_labo
    ORDER BY le.libelle
");
$stmt->execute([':deb' => $dateDebut, ':fin' => $dateFin]);
$lignes    = $stmt->fetchAll();
$totalLabo = array_sum(array_column($lignes, 'total_labo'));

if (!$lignes) {
    jsonSuccess('Aucun examen trouvé.', ['html' => '<div class="alert alert-info">Aucun examen sur cette période.</div>']);
}

// Générer HTML aperçu
ob_start();
?>
<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Examen</th>
                <th class="text-center">Nb actes</th>
                <th class="text-end">Total brut</th>
                <th class="text-center">% Labo</th>
                <th class="text-end text-success">Montant Labo</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
            <tr>
                <td><?= h($l['libelle']) ?></td>
                <td class="text-center"><?= $l['nb_actes'] ?></td>
                <td class="text-end"><?= number_format($l['total_brut'],0,',',' ') ?> F</td>
                <td class="text-center"><span class="badge bg-warning text-dark"><?= $l['pourcentage_labo'] ?>%</span></td>
                <td class="text-end fw-bold text-success"><?= number_format($l['total_labo'],0,',',' ') ?> F</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="table-success fw-bold">
            <tr>
                <td colspan="4" class="text-end">TOTAL DÛ AU LABORANTIN :</td>
                <td class="text-end fs-5 text-success"><?= number_format($totalLabo,0,',',' ') ?> F</td>
            </tr>
        </tfoot>
    </table>
</div>
<?php
$html = ob_get_clean();

// Générer PDF
require_once ROOT_PATH . '/modules/pdf/PdfGenerator.php';
$gen  = new PdfGenerator($pdo);
$file = $gen->generateEtatLabo($dateDebut, $dateFin);

jsonSuccess('État généré.', [
    'html'    => $html,
    'pdf_url' => '/uploads/pdf/' . basename($file)
]);
