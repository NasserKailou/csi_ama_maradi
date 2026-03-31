<?php http_response_code(403); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>403 – Accès refusé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/main.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center p-5">
        <div class="display-1 fw-bold text-danger">403</div>
        <h4 class="mb-3">Accès refusé</h4>
        <p class="text-muted mb-4">Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>
        <a href="/index.php" class="btn text-white px-4" style="background:var(--csi-green);">
            <i class="bi bi-house me-1"></i>Retour à l'accueil
        </a>
    </div>
</div>
</body>
</html>
