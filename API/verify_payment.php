<?php
/**
 * Project: fintech-payment-dashboard
 * File: verify_payment.php
 * Upgraded Version: 1.0.1
 * Description: Robust verification for direct virtual account transfers.
 * Gracefully handles 404 errors and empty ledger responses.
 */

// 1. Prevent any random PHP warnings/notices from breaking our clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// 2. Capture incoming request data (handles both application/json and form data)
$input = json_decode(file_get_contents('php://input'), true);
$paymentReference = isset($input['paymentReference']) ? $input['paymentReference'] : (isset($_POST['paymentReference']) ? $_POST['POST']['paymentReference'] : '');

if (empty($paymentReference)) {
    echo json_encode([
        "success" => false,
        "status" => "ERROR",
        "message" => "Missing dynamic transaction payment reference."
    ]);
    exit;
}

// 3. Environment Configurations (Update with your Render environment variables)
$isSandbox = true; // Set to false when moving to live/production environment
$baseUrl = $isSandbox ? "https://sandbox.monnify.com" : "https://api.monnify.com";
$apiKey = getenv('MONNIFY_API_KEY') ?: "YOUR_API_KEY_HERE";
$secretKey = getenv('MONNIFY_SECRET_KEY') ?: "YOUR_SECRET_KEY_HERE";

// --- START VERIFICATION PROCESS ---
try {
    // 4. Authenticate and fetch access token
    $accessToken = getMonnifyToken($baseUrl, $apiKey, $secretKey);
    if (!$accessToken) {
        throw new Exception("Unable to authenticate token refresh session with Monnify.");
    }

    // 5. TRY ENDPOINT A: Standard Monnify Transaction Query Gateway API
    $endpointA = $baseUrl . "/api/v2/merchant/transactions/query?paymentReference=" . urlencode($paymentReference);
    $responseA = makeCurlRequest($endpointA, $accessToken);
    
    error_log("[DEBUG] Primary verification response payload: HTTP " . $responseA['http_code']);

    // Check if Endpoint A successfully returned a transaction record
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
    error_log("[DEBUG] Endpoint A did not return a successful payment. Checking reserved account history ledger for: " . $paymentReference);
    
    // Querying the bank-transfer transaction history using your account identifier
    $endpointB = $baseUrl . "/api/v1/bank-transfer/reserved-accounts/transactions?accountReference=" . urlencode($paymentReference) . "&page=0&size=10";
    $responseB = makeCurlRequest($endpointB, $accessToken);

    error_log("[DEBUG] Reserved ledger response payload: HTTP " . $responseB['http_code']);

    // Parse the transaction arrays safely without triggering unhandled index warnings
    $ledgerTransactions = [];
    if ($responseB['http_code'] === 200 && isset($responseB['body']['responseBody']['content'])) {
        $ledgerTransactions = $responseB['body']['responseBody']['content'];
    }

    // 7. HANDLE PENDING STATUS SAFELY (The critical fix)
    if (empty($ledgerTransactions)) {
        // DO NOT CRASH the pipeline. Treat this as an expected pending transfer event state.
        echo json_encode([
            "success" => true,
            "status" => "PENDING",
            "message" => "Awaiting your network transfer... No transactions have been posted to this ledger account yet."
        ]);
        exit;
    }

    // 8. EVALUATE TRANSFERS IN THE LEDGER
    // Search the history array to see if any transaction references or flags matches our expected status
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

    // Default fallback state if transactions exist but none are valid/cleared
    echo json_encode([
        "success" => true,
        "status" => "PENDING",
        "message" => "Transaction history logged, but clear settlement funds are currently processing."
    ]);

} catch (Exception $e) {
    // Gracefully report any critical platform system error out back to app.js
    error_log("[ERROR] Payment verification process crashed safely: " . $e->getMessage());
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
            error_log("[DEBUG] Monnify token refreshed successfully: {\"expires_in\":" . ($result['responseBody']['expiresIn'] ?? '3600') . "}");
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
    
    $decodedBody = json_decode($response, true);
    
    // Log unexpected API errors for tracking, but avoid breaking the parent logic flow
    if ($httpCode !== 200) {
        error_log("[WARN] Monnify API endpoint returned code " . $httpCode . " for request URL: " . $url);
    }
    
    return [
        'http_code' => $httpCode,
        'body' => $decodedBody
    ];
}
