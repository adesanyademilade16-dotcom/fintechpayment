<?php
/* =============================================
   webhook.php
   POST /api/webhook.php

   Receives payment notifications from Monnify.
   Monnify sends a POST request with a JSON body
   and an "monnify-signature" header whenever a
   payment hits your virtual account.

   REGISTER THIS URL in your Monnify dashboard:
   https://yourdomain.com/api/webhook.php

   ─────────────────────────────────────────────
   SECURITY: Always verify the signature before
   doing anything with the payload.
   ============================================= */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/monnify_client.php';

apply_headers();

// Webhooks are always POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── 1. Read raw body ──────────────────────────
$rawBody  = file_get_contents('php://input');
$payload  = json_decode($rawBody, true);

if (!$payload) {
    log_event('warn', 'Webhook: empty or invalid payload');
    http_response_code(400);
    exit;
}

// ── 2. Verify Monnify signature ───────────────
$signature = $_SERVER['HTTP_MONNIFY_SIGNATURE'] ?? '';

$monnify = new MonnifyClient();

if (APP_ENV === 'production' && !$monnify->validateWebhookSignature($rawBody, $signature)) {
    log_event('warn', 'Webhook: invalid signature', ['signature' => $signature]);
    http_response_code(401);
    exit;
}

// ── 3. Extract event data ─────────────────────
$eventType      = $payload['eventType']              ?? '';
$paymentRef     = $payload['eventData']['paymentReference'] ?? '';
$accountRef     = $payload['eventData']['product']['reference'] ?? '';
$amountPaid     = $payload['eventData']['amountPaid'] ?? 0;
$paymentStatus  = $payload['eventData']['paymentStatus'] ?? '';
$paidOn         = $payload['eventData']['paidOn']     ?? '';
$customerEmail  = $payload['eventData']['customer']['email'] ?? '';

log_event('info', 'Webhook received', [
    'event'      => $eventType,
    'ref'        => $paymentRef,
    'status'     => $paymentStatus,
    'amount'     => $amountPaid,
    'account'    => $accountRef,
]);

// ── 4. Handle event types ─────────────────────
switch ($eventType) {

    case 'SUCCESSFUL_TRANSACTION':
    // ↓ A payment was successfully made to a virtual account.
    //   TODO when you add a database:
    //   1. Find the user by $accountRef
    //   2. Credit their wallet with $amountPaid
    //   3. Record the transaction
    //   4. Send confirmation email/SMS

    if ($paymentStatus === 'PAID') {
        log_event('info', 'Payment confirmed', [
            'ref'    => $paymentRef,
            'amount' => $amountPaid,
            'email'  => $customerEmail,
        ]);

        // Example DB call (uncomment when DB is ready):
        // creditUserWallet($accountRef, $amountPaid, $paymentRef);
        // sendPaymentConfirmation($customerEmail, $amountPaid);
    }
    break;

    case 'FAILED_TRANSACTION':
        log_event('warn', 'Payment failed', ['ref' => $paymentRef]);
        // TODO: notify user if needed
        break;

    case 'REVERSED_TRANSACTION':
        log_event('warn', 'Payment reversed', ['ref' => $paymentRef, 'amount' => $amountPaid]);
        // TODO: debit wallet, notify user
        break;

    default:
        log_event('debug', 'Webhook: unhandled event type', ['event' => $eventType]);
        break;
}

// ── 5. Always return 200 to Monnify ──────────
// Monnify will retry if it doesn't get a 200.
http_response_code(200);
echo json_encode(['status' => 'received']);
exit;
