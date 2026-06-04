<?php
/* =============================================
   API/config.php
============================================= */

// Safely pull from Render environment variables, or fallback to local strings
define('MONNIFY_API_KEY', getenv('MONNIFY_API_KEY') ?: 'YOUR_LOCAL_API_KEY_HERE');
define('MONNIFY_SECRET_KEY', getenv('MONNIFY_SECRET_KEY') ?: 'YOUR_LOCAL_SECRET_KEY_HERE');
define('MONNIFY_CONTRACT_CODE', getenv('MONNIFY_CONTRACT_CODE') ?: 'YOUR_LOCAL_CONTRACT_CODE_HERE');

// Determine environment mode (defaulting to sandbox for safety)
$envMode = getenv('MONNIFY_ENVIRONMENT') ?: 'sandbox';
define('MONNIFY_BASE_URL', ($envMode === 'production') 
    ? 'https://api.monnify.com' 
    : 'https://sandbox.monnify.com'
);
/* ── Security / CORS Origins ───────────────── */
define('ALLOWED_ORIGINS', [
    'https://fintech-payment-dashboard.onrender.com', // Added your live domain
    'https://fintechpayment.freehosting.dev',
    'http://localhost',
    'http://127.0.0.1'
]);

/* ── Response Helpers ─────────────────────── */

function send_success(array $data, int $status = 200): void
{
    ob_clean();
    http_response_code($status);
    echo json_encode([
        'status' => 'success',
        'data'   => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function send_error(string $message, int $status = 500, array $extra = []): void
{
    ob_clean();
    http_response_code($status);
    echo json_encode(array_merge([
        'status'  => 'error',
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function apply_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        header('Access-Control-Allow-Origin: https://fintech-payment-dashboard.onrender.com');
    }

    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

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

// In-memory logging to prevent Render disk permission write crashes
function log_event(string $level, string $message, array $context = []): void
{
    error_log(sprintf("[%s] %s: %s", strtoupper($level), $message, json_encode($context)));
}
