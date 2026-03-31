<?php
// Charger les helpers si ROOT_PATH est défini (pour la fonction url())
if (defined('ROOT_PATH') && function_exists('url')) {
    $backUrl = url('index.php');
} else {
    // Fallback : détecter le sous-dossier depuis REQUEST_URI
    $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $backUrl = $baseDir . '/index.php';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 – Page introuvable | CSI AMA Maradi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --csi: #2e7d32; }
        body { background: #f5f7fa; }
        .error-card { max-width: 500px; margin: 10vh auto; border-radius: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card error-card shadow-lg border-0 text-center p-5">
        <div class="mb-4">
            <span class="display-1">🏥</span>
        </div>
        <h1 class="fw-bold" style="color:var(--csi);">Page introuvable</h1>
        <p class="text-muted mb-4">La page que vous recherchez n'existe pas ou a été déplacée.</p>
        <a href="<?= $backUrl ?>" class="btn text-white px-4" style="background:var(--csi);">
            <i class="bi bi-house me-2"></i>Retour à l'accueil
        </a>
    </div>
</div>
</body>
</html>
