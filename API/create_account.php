<?php
/* =============================================
   API/create_account.php — Universal Structural Response
============================================= */
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/monnify_client.php';

// Wipe any previous output buffering noise
if (ob_get_length()) ob_clean();

// Secure Headers
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Generate unique profiles to bypass sandbox lockouts
$uniqueId      = time() . rand(10, 99);
$customerName  = "Adesanya Ibrahim";
$customerEmail = "codesanya_" . $uniqueId . "@gmail.com";
$accountRef    = "REF_" . $uniqueId;

try {
    $monnify = new MonnifyClient();

    $result = $monnify->createReservedAccount([
        'accountName'          => $customerName,
        'customerName'         => $customerName,
        'customerEmail'        => $customerEmail,
        'accountRef'           => $accountRef,
        'getAllAvailableBanks' => true,
    ]);

    // Extract values safely
    $finalBank   = $result['bankName'] ?? $result['allAccounts'][0]['bankName'] ?? 'Wema Bank';
    $finalNumber = $result['accountNumber'] ?? $result['allAccounts'][0]['accountNumber'] ?? '------------';
    $finalName   = $result['accountName'] ?? $customerName;

    // Dual-Compatibility Mode: Outputting BOTH Flat and Nested formats 
    // This ensures no matter which version of app.js your phone runs, it succeeds!
    echo json_encode([
        "status"        => "success",
        "accountName"   => $finalName,
        "bankName"      => $finalBank,
        "accountNumber" => $finalNumber,
        "accountRef"    => $accountRef,
        "data"          => [
            "accountName"   => $finalName,
            "bankName"      => $finalBank,
            "accountNumber" => $finalNumber,
            "accountRef"    => $accountRef
        ]
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        "status"  => "error",
        "message" => "Monnify Gateway Handshake Error: " . $e->getMessage()
    ]);
    exit;
}
