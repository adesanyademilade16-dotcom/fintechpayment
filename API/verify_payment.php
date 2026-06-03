<?php
/* =============================================
   verify_payment.php — Secure Verification Switch
============================================= */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");

$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);
$accountReference = $data['accountReference'] ?? '';

if (empty($accountReference)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing core track identification token."
    ]);
    exit;
}

// Generate a mock payment clearance ticket safely
$mockPaymentRef = "PAY_" . strtoupper(bin2hex(random_bytes(6)));

// Simply return a successful payment clear confirmation to the frontend app
echo json_encode([
    "status" => "success",
    "data" => [
        "message" => "Settlement confirmed successfully.",
        "paymentReference" => $mockPaymentRef,
        "status" => "PAID"
    ]
]);
exit;
