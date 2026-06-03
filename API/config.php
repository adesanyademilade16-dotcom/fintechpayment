<?php
/* =============================================
   config.php
   Central configuration for the Virtual Account API.
   ============================================= */

/* ── Environment ─────────────────────────────
   'production' → real Monnify API calls
   'development' → mock responses (no API needed)
──────────────────────────────────────────────── */
define('APP_ENV', 'production');

/* ── Monnify Sandbox Credentials ─────────────
   Log into sandbox.monnify.com → Settings →
   API Keys & Webhooks to confirm these values.
──────────────────────────────────────────────── */
define('MONNIFY_API_KEY',       'MK_TEST_7UDZSFXZ8T');
define('MONNIFY_SECRET_KEY',    'GRU4YXBNBHF34NQ0G7A4T5P334ZGACWV');
define('MONNIFY_CONTRACT_CODE', '4986576197'); // ← Replace with your exact contract code from Monnify dashboard

define('MONNIFY_BASE_URL', 'https://sandbox.monnify.com');

/* ── Security / CORS ──────────────────────── */
define('ALLOWED_ORIGINS', [
    'https://fintechpayment.freehosting.dev',
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
]);

/* ── Rate Limiting (future use) ───────────── */
define('RATE_LIMIT_MAX',    10);
define('RATE_LIMIT_WINDOW', 60);

/* ── Response Helpers ─────────────────────── */

function send_success(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'status' => 'success',
        'data'   => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function send_error(string $message, int $status = 500, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'status'  => 'error',
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function apply_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        // Fallback — allows same-origin requests from the hosting domain
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error('Method not allowed. Use POST.', 405);
    }
}

function get_json_body(): ?array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function log_event(string $level, string $message, array $context = []): void
{
    $logDir  = __DIR__ . '/../logs';
    $logFile = $logDir . '/api.log';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $line = sprintf(
        "[%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        empty($context) ? '' : json_encode($context)
    );

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
