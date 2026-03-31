<?php
/**
 * Helpers globaux – fonctions utilitaires
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

// ── Auth / Redirects ──────────────────────────────────────────────────────────
function requireLogin(): void
{
    if (!Session::isLoggedIn()) {
        header('Location: /index.php?page=login');
        exit;
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
        'whodone'  => Session::getUserId(),
        'isDeleted' => 0,
    ];
}
