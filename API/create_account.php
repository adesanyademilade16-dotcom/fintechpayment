<?php
/* =============================================
   create_account.php — Production Gateway
============================================= */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/monnify_client.php';

apply_headers();
require_post();

$body = get_json_body();

if ($body === null) {
    send_error('Invalid JSON in request body.', 400);
}

$customerName  = isset($body['customerName']) ? trim(htmlspecialchars($body['customerName'], ENT_QUOTES, 'UTF-8')) : 'Adesanya Ibrahim';
$customerEmail = isset($body['customerEmail']) ? trim(filter_var($body['customerEmail'], FILTER_SANITIZE_EMAIL)) : 'adesanya@example.com';
$accountRef    = "REF_" . time() . "_" . rand(1000, 9999);

try {
    $monnify = new MonnifyClient();

    $result = $monnify->createReservedAccount([
        'accountName'          => $customerName,
        'customerName'         => $customerName,
        'customerEmail'        => $customerEmail,
        'accountRef'           => $accountRef,
        'getAllAvailableBanks' => true,
    ]);

    log_event('info', 'Virtual account created successfully', [
        'account_ref' => $result['accountRef'] ?? 'n/a',
        'bank'        => $result['bankName']   ?? 'n/a'
    ]);

    send_success([
        'accountName'   => $result['accountName'],
        'bankName'      => $result['bankName'],
        'accountNumber' => $result['accountNumber'],
        'bankCode'      => $result['bankCode'],
        'accountRef'    => $result['accountRef'],
        'allAccounts'   => $result['allAccounts'] ?? []
    ]);

} catch (Throwable $e) {
    log_event('error', 'Monnify account creation failed', ['message' => $e->getMessage()]);
    
    // Stop concealing errors. Return the error details so we can diagnose the issue.
    send_error('Monnify Gateway Error: ' . $e->getMessage(), 500);
}
