<?php
/* =============================================
   verify_payment.php — Robust Monnify Verification
============================================= */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/monnify_client.php';

apply_headers();
require_post();

$body = get_json_body();
$accountReference = $body['accountReference'] ?? '';

if (empty($accountReference)) {
    send_error('Missing account reference token.', 400);
}

try {
    $monnify = new MonnifyClient();
    
    // Query the actual transaction history log or history ledger via our adaptive client wrapper
    $result = $monnify->verifyPayment($accountReference);
    
    $status = strtoupper($result['paymentStatus'] ?? $result['status'] ?? '');
    
    // Check if Monnify confirms it is cleared/paid
    if ($status === 'PAID' || $status === 'SETTLED' || $status === 'SUCCESS') {
        send_success([
            "message" => "Settlement confirmed successfully.",
            "paymentReference" => $result['paymentReference'] ?? '',
            "status" => "PAID",
            "amountPaid" => $result['amountPaid'] ?? 0
        ]);
    } else {
        send_error('No transaction detected yet or payment is still pending.', 200, [
            'status' => !empty($status) ? $status : 'PENDING'
        ]);
    }

} catch (Throwable $e) {
    log_event('error', 'Payment verification process crashed', ['message' => $e->getMessage()]);
    send_error('Verification node unreachable: ' . $e->getMessage(), 500);
}
