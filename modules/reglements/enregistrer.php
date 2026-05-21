<?php
require_once __DIR__ . '/../../core/bootstrap.php';
requireRole('admin', 'comptable');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/reglements/index.php');
}
// CSRF si tu as la fonction
if (function_exists('verifyCsrfToken') && !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    flash('error', 'Token de sécurité invalide.');
    redirect('/modules/reglements/index.php');
}

$pdo = Database::getInstance();
$recusIds = array_filter(array_map('intval', explode(',', $_POST['recus_ids'] ?? '')));
$dateReglement = $_POST['date_reglement'] ?? date('Y-m-d');
$montantSaisi  = (float)($_POST['montant_total'] ?? 0);
$mode          = $_POST['mode_paiement'] ?? 'especes';
$reference     = trim($_POST['reference_paiement'] ?? '');
$observations  = trim($_POST['observations'] ?? '');

if (empty($recusIds) || $montantSaisi <= 0) {
    flash('error', 'Sélection ou montant invalide.');
    redirect('/modules/reglements/index.php');
}

try {
    $pdo->beginTransaction();

    // Vérifier que les reçus sont bien en instance et orphelins
    $placeholders = implode(',', array_fill(0, count($recusIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, montant_total FROM recus
        WHERE id IN ($placeholders) AND isDeleted=0
          AND type_patient='orphelin' AND statut_reglement='en_instance'
        FOR UPDATE
    ");
    $stmt->execute($recusIds);
    $recusValides = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($recusValides) !== count($recusIds)) {
        throw new Exception('Certains reçus ne sont plus disponibles (déjà réglés ou modifiés).');
    }

    $totalDu = array_sum(array_column($recusValides, 'montant_total'));
    if ($montantSaisi > $totalDu) {
        throw new Exception('Le montant saisi dépasse le total dû ('.number_format($totalDu,0,',',' ').' FCFA).');
    }

    // Numéro unique : RGL-AAAAMMJJ-XXX
    $prefix = 'RGL-' . date('Ymd', strtotime($dateReglement)) . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*)+1 FROM reglements_orphelins WHERE numero_reglement LIKE ?");
    $stmt->execute([$prefix.'%']);
    $numero = $prefix . str_pad((int)$stmt->fetchColumn(), 3, '0', STR_PAD_LEFT);

    // Insertion du règlement
    $stmt = $pdo->prepare("
    INSERT INTO reglements_orphelins
    (numero_reglement, date_reglement, montant_total, nb_recus, mode_paiement, 
     reference_paiement, observations, whendone, whodone, isDeleted)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0)
        ");
        $stmt->execute([
            $numero, $dateReglement, $montantSaisi, count($recusValides),
            $mode, $reference ?: null, $observations ?: null, Session::get('user_id')
        ]);

    $reglementId = $pdo->lastInsertId();

    // Cas paiement total
    if (abs($montantSaisi - $totalDu) < 0.01) {
        $stmt = $pdo->prepare("
            UPDATE recus SET statut_reglement='regle', date_reglement=?, reglement_id=?
            WHERE id IN ($placeholders)
        ");
        $stmt->execute(array_merge([$dateReglement, $reglementId], $recusIds));
    } else {
        // Paiement partiel : on solde dans l'ordre les reçus jusqu'à épuisement du montant
        $reste = $montantSaisi;
        foreach ($recusValides as $r) {
            if ($reste >= $r['montant_total'] - 0.01) {
                $upd = $pdo->prepare("UPDATE recus SET statut_reglement='regle', date_reglement=?, reglement_id=? WHERE id=?");
                $upd->execute([$dateReglement, $reglementId, $r['id']]);
                $reste -= $r['montant_total'];
            } else {
                break; // les reçus restants demeurent en_instance
            }
        }
    }

    $pdo->commit();
    flash('success', "Règlement $numero enregistré avec succès.");
    redirect('/modules/reglements/facture.php?id=' . $reglementId);

} catch (Exception $e) {
    $pdo->rollBack();
    flash('error', 'Erreur : ' . $e->getMessage());
    redirect('/modules/reglements/index.php');
}
