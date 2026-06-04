<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/monnify_client.php';

apply_headers();
require_post();

$body = get_json_body();

$accountReference = $body['accountReference'] ?? '';

if (!$accountReference) {
    send_error('Missing account reference.', 400);
}

try {

    log_event(
        'debug',
        'Verification Request',
        [
            'accountReference' => $accountReference
        ]
    );

    $monnify = new MonnifyClient();

    $result = $monnify->verifyPayment($accountReference);

    log_event(
        'debug',
        'Verification Result',
        $result
    );

    $status = strtoupper(
        $result['paymentStatus']
        ?? $result['status']
        ?? ''
    );

    if (
        $status === 'PAID'
        || $status === 'SUCCESS'
        || $status === 'SETTLED'
    ) {

        send_success([
            'message' => 'Payment confirmed.',
            'paymentReference' =>
                $result['paymentReference'] ?? '',

            'amountPaid' =>
                $result['amountPaid'] ?? 0,

            'status' => $status
        ]);
    }

    send_error(
        'No payment found yet.',
        200,
        [
            'status' => $status ?: 'PENDING',
            'debug' => $result
        ]
    );

} catch (Throwable $e) {

    log_event(
        'error',
        'Verification Crash',
        [
            'message' => $e->getMessage()
        ]
    );

    send_error(
        $e->getMessage(),
        500
    );
}
