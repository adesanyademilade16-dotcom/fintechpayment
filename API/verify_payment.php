<?php
/* =============================================
   verify_payment.php — Real Monnify Verification
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
    
    // Query the actual transaction history via Monnify's API wrapper
    $result = $monnify->verifyPayment($accountReference);
    
    // Check if Monnify confirms it is paid
    if (($result['paymentStatus'] ?? '') === 'PAID') {
        send_success([
            "message" => "Settlement confirmed successfully.",
            "paymentReference" => $result['paymentReference'] ?? '',
            "status" => "PAID",
            "amountPaid" => $result['amountPaid'] ?? 0
        ]);
    } else {
        send_error('Payment not found or still pending.', 404, [
            'status' => $result['paymentStatus'] ?? 'PENDING'
        ]);
    }

} catch (Throwable $e) {
    log_event('error', 'Payment verification process crashed', ['message' => $e->getMessage()]);
    send_error('Verification node unreachable: ' . $e->getMessage(), 500);
}
