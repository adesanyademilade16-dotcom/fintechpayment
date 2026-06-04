<?php
/**
 * Project: fintech-payment-dashboard
 * File: verify_payment.php
 * Upgraded Version: 1.0.2
 * Description: Fully robust verification for virtual account transfers.
 * Fixes input parsing fallback bugs and gracefully handles Monnify API 404 ledger states.
 */

// 1. Prevent random PHP warnings/notices from corrupting our JSON output channel
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// 2. Multi-Channel Input Parser: Extract paymentReference regardless of content-type format
$paymentReference = '';

$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (stripos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['paymentReference'])) {
        $paymentReference = $input['paymentReference'];
    }
}

// Fallback to standard form fields or URL query parameters if JSON extraction didn't populate it
if (empty($paymentReference)) {
    if (isset($_POST['paymentReference'])) {
        $paymentReference = $_POST['paymentReference'];
    } elseif (isset($_GET['paymentReference'])) {
        $paymentReference = $_GET['paymentReference'];
    }
}

// Validate that a payment reference was successfully resolved
if (empty($paymentReference)) {
    echo json_encode([
        "success" => false,
        "status" => "ERROR",
        "message" => "Missing dynamic transaction payment reference."
    ]);
    exit;
}

// 3. Environment Configurations (Update with your Monnify environment variables)
$isSandbox = true; // Set to false when moving to live production environment
$baseUrl = $isSandbox ? "https://sandbox.monnify.com" : "https://api.monnify.com";
$apiKey = getenv('MONNIFY_API_KEY') ?: "YOUR_API_KEY_HERE";
$secretKey = getenv('MONNIFY_SECRET_KEY') ?: "YOUR_SECRET_KEY_HERE";

// --- START VERIFICATION PROCESS ---
try {
    // 4. Authenticate and fetch authorization token session
    $accessToken = getMonnifyToken($baseUrl, $apiKey, $secretKey);
    if (!$accessToken) {
        throw new Exception("Unable to authenticate token refresh session with Monnify.");
    }

    // 5. TRY ENDPOINT A: Standard Monnify Transaction Query Gateway API
    $endpointA = $baseUrl . "/api/v2/merchant/transactions/query?paymentReference=" . urlencode($paymentReference);
    $responseA = makeCurlRequest($endpointA, $accessToken);
    
    error_log("[DEBUG] Primary verification response payload: HTTP " . $responseA['http_code']);

    // Check if Endpoint A successfully returned an active transaction record
    if ($responseA['http_code'] === 200 && isset($responseA['body']['requestSuccessful']) && $responseA['body']['requestSuccessful'] === true) {
        $txStatus = $responseA['body']['responseBody']['paymentStatus'] ?? '';
        
        if ($txStatus === 'PAID' || $txStatus === 'SUCCESSFUL') {
            echo json_encode([
                "success" => true,
                "status" => "SUCCESSFUL",
                "message" => "Payment verified successfully via gateway query.",
                "data" => $responseA['body']['responseBody']
            ]);
            exit;
        }
    }

    // 6. FALLBACK TO ENDPOINT B: Virtual Account Reserved Ledger Query History
    // (Executes if Endpoint A gives a 404 or transaction isn't marked as PAID yet)
    error_log("[DEBUG] Endpoint A did not match a cleared payment. Checking reserved account history ledger for: " . $paymentReference);
    
    $endpointB = $baseUrl . "/api/v1/bank-transfer/reserved-accounts/transactions?accountReference=" . urlencode($paymentReference) . "&page=0&size=10";
    $responseB = makeCurlRequest($endpointB, $accessToken);

    error_log("[DEBUG] Reserved ledger response payload: HTTP " . $responseB['http_code']);

    // Parse the transaction arrays safely without triggering index errors
    $ledgerTransactions = [];
    if ($responseB['http_code'] === 200 && isset($responseB['body']['responseBody']['content'])) {
        $ledgerTransactions = $responseB['body']['responseBody']['content'];
    }

    // 7. HANDLE PENDING STATUS SAFELY (Prevents application crashing on empty arrays)
    if (empty($ledgerTransactions)) {
        echo json_encode([
            "success" => true,
            "status" => "PENDING",
            "message" => "Awaiting your network transfer... No transactions have been posted to this ledger account yet."
        ]);
        exit;
    }

    // 8. EVALUATE TRANSFERS IN THE LEDGER
    foreach ($ledgerTransactions as $tx) {
        if (isset($tx['paymentStatus']) && ($tx['paymentStatus'] === 'PAID' || $tx['paymentStatus'] === 'SUCCESSFUL')) {
            echo json_encode([
                "success" => true,
                "status" => "SUCCESSFUL",
                "message" => "Direct bank transfer detected and verified successfully via ledger matching.",
                "amount" => $tx['amountPaid'] ?? $tx['amount'],
                "transaction_id" => $tx['transactionReference']
            ]);
            exit;
        }
    }

    // Default processing response if transaction exists but isn't settled
    echo json_encode([
        "success" => true,
        "status" => "PENDING",
        "message" => "Transaction history logged, but clear settlement funds are currently processing."
    ]);

} catch (Exception $e) {
    error_log("[ERROR] Payment verification process exception: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "status" => "ERROR",
        "message" => "Verification system exception: " . $e->getMessage()
    ]);
}

// --- CORE NETWORKING HELPER FUNCTIONS ---

function getMonnifyToken($baseUrl, $apiKey, $secretKey) {
    $url = $baseUrl . "/api/v1/auth/login";
    $ch = curl_init($url);
    
    $base64Credentials = base64_encode($apiKey . ":" . $secretKey);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic " . $base64Credentials,
        "Content-Length: 0"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['responseBody']['accessToken'])) {
            return $result['responseBody']['accessToken'];
        }
    }
    return null;
}

function makeCurlRequest($url, $accessToken) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $accessToken,
        "Accept: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}
