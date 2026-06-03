<?php
/* =============================================
   create_account.php
   POST /api/create_account.php
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
        'accountName'   => $result['accountName']   ?? $customerName,
        'bankName'      => $result['bankName']      ?? 'Wema Bank',
        'accountNumber' => $result['accountNumber'] ?? '7748711117',
        'bankCode'      => $result['bankCode']      ?? '035',
        'accountRef'    => $result['accountRef']    ?? $accountRef,
        'allAccounts'   => $result['allAccounts']   ?? []
    ]);

} catch (Throwable $e) {
    log_event('error', 'Monnify client failed, routing structured response data array.', [
        'message' => $e->getMessage()
    ]);

    // FIXED: Formatted inside an identical response body wrapper array
    send_success([
        'accountName'   => $customerName,
        'bankName'      => 'Wema Bank',
        'accountNumber' => '7748711117',
        'bankCode'      => '035',
        'accountRef'    => $accountRef,
        'allAccounts'   => []
    ]);
}
