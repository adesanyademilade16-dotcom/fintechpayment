<?php
/* =============================================
   API/create_account.php — Streamlined Sandbox
============================================= */

// Clear any accidental white spaces or errors ahead of time
ob_clean();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/monnify_client.php';

// Force clean JSON headers
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST requests allowed"]);
    exit;
}

// Generate an ultra-unique profile for every single click/refresh
$uniqueId = time() . rand(10, 99);
$customerName  = "Adesanya Ibrahim";
$customerEmail = "codesanya_" . $uniqueId . "@gmail.com"; 
$accountRef    = "REF_" . $uniqueId;

try {
    $monnify = new MonnifyClient();

    // Call Monnify directly
    $result = $monnify->createReservedAccount([
        'accountName'          => $customerName,
        'customerName'         => $customerName,
        'customerEmail'        => $customerEmail,
        'accountRef'           => $accountRef,
        'getAllAvailableBanks' => true,
    ]);

    // Send clean success packet back to app.js
    echo json_encode([
        "status" => "success",
        "data" => [
            "accountName"   => $result['accountName'] ?? $customerName,
            "bankName"      => $result['bankName'] ?? (isset($result['allAccounts'][0]['bankName']) ? $result['allAccounts'][0]['bankName'] : 'Wema Bank'),
            "accountNumber" => $result['accountNumber'] ?? (isset($result['allAccounts'][0]['accountNumber']) ? $result['allAccounts'][0]['accountNumber'] : '------------'),
            "accountRef"    => $accountRef
        ]
    ]);
    exit;

} catch (Throwable $e) {
    // Return the exact error text directly to the screen for clear diagnostics
    echo json_encode([
        "status" => "error",
        "message" => "Monnify Gateway Handshake Error: " . $e->getMessage()
    ]);
    exit;
}
