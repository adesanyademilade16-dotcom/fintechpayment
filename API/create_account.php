 <?php
/* =============================================
   create_account.php — Dynamic Sandbox Bypass
============================================= */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/monnify_client.php';

apply_headers();
require_post();

$body = get_json_body();

// Create a completely unique string on every page reload to bypass Sandbox email lockouts
$uniqueId = time();
$customerName  = "Adesanya Ibrahim";
$customerEmail = "adesanya_" . $uniqueId . "@example.com"; 
$accountRef    = "REF_" . $uniqueId . "_" . rand(1000, 9999);

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
    send_error('Monnify Gateway Error: ' . $e->getMessage(), 500);
}
