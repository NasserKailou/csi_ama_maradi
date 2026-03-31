<?php
/**
 * Helpers globaux – fonctions utilitaires
 * Compatible XAMPP sous-dossier (APP_SUBDIR détecté automatiquement)
 */

// ── Sécurité ──────────────────────────────────────────────────────────────────
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrfInput(): string
{
    $token = Session::generateCsrfToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . h($token) . '">';
}

function csrfMeta(): string
{
    $token = Session::generateCsrfToken();
    return '<meta name="csrf-token" content="' . h($token) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Session::validateCsrfToken($token)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Token CSRF invalide.']));
    }
}

// ── URL helpers – compatibles sous-dossier XAMPP ─────────────────────────────
/**
 * Génère une URL absolue avec le sous-dossier XAMPP.
 * url('index.php?page=login')  → /csi_ama_maradi/index.php?page=login
 * url('index.php?page=login')  → /index.php?page=login  (à la racine)
 */
function url(string $path = ''): string
{
    $sub  = defined('APP_SUBDIR') ? APP_SUBDIR : '';
    $path = ltrim($path, '/');
    return $sub . '/' . $path;
}

/**
 * Génère le chemin vers un asset statique (CSS, JS, images).
 * asset('assets/css/main.css') → /csi_ama_maradi/assets/css/main.css
 */
function asset(string $path): string
{
    return url($path);
}

/**
 * Génère l'URL d'un fichier uploadé.
 * uploadUrl('logo_csi.png') → /csi_ama_maradi/uploads/logos/logo_csi.png
 */
function uploadUrl(string $filename, string $type = 'logos'): string
{
    return url("uploads/{$type}/{$filename}");
}

// ── Auth / Redirects ──────────────────────────────────────────────────────────
function requireLogin(): void
{
    if (!Session::isLoggedIn()) {
        redirect(url('index.php?page=login'));
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    if (!Session::hasRole(...$roles)) {
        http_response_code(403);
        include ROOT_PATH . '/templates/errors/403.php';
        exit;
    }
}

function redirect(string $url): void
{
    // Si chemin relatif sans http, ajouter le sous-dossier
    if (!str_starts_with($url, 'http') && !str_starts_with($url, APP_SUBDIR ?: '/')) {
        $url = url(ltrim($url, '/'));
    }
    header('Location: ' . $url);
    exit;
}

// ── JSON API ─────────────────────────────────────────────────────────────────
function jsonResponse(bool $success, string $message = '', array $data = [], int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

function jsonError(string $message, int $code = 400): void
{
    jsonResponse(false, $message, [], $code);
}

function jsonSuccess(string $message = 'OK', array $data = []): void
{
    jsonResponse(true, $message, $data);
}

// ── Formatage ─────────────────────────────────────────────────────────────────
function formatMontant(int $montant): string
{
    return number_format($montant, 0, ',', ' ') . ' F';
}

function formatDate(string $date, string $format = 'd/m/Y H:i'): string
{
    return (new DateTime($date))->format($format);
}

// ── Upload fichier ─────────────────────────────────────────────────────────────
function uploadLogo(array $file): string|false
{
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > UPLOAD_MAX_SIZE) return false;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED, true)) return false;

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = UPLOAD_PATH . $filename;

    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return $filename;
}

// ── Numéro de reçu ────────────────────────────────────────────────────────────
function getNextNumeroRecu(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT MAX(numero_recu) AS max_num FROM recus WHERE isDeleted = 0");
    $max  = (int)($stmt->fetchColumn() ?: 0);
    return $max + 1;
}

// ── Traçabilité ───────────────────────────────────────────────────────────────
function traceFields(): array
{
    return [
        'whodone'   => Session::getUserId(),
        'isDeleted' => 0,
    ];
}
