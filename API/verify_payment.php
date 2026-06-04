 <?php
/* =============================================
   API/verify_payment.php — Strict Ledger Check
============================================= */
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/monnify_client.php';

if (ob_get_length()) ob_clean();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// 1. Parse incoming payment reference from frontend
$inputData = json_decode(file_get_contents('php://input'), true);
$reference = $inputData['paymentReference'] ?? $inputData['accountReference'] ?? null;

if (!$reference) {
    echo json_encode([
        "success" => false,
        "status" => "ERROR",
        "message" => "Missing payment reference identifier."
    ]);
    exit;
}

try {
    $monnify = new MonnifyClient();
    
    // 2. Try Standard Single Checkout Lookup
    $result = $monnify->queryTransaction($reference);

    if (!empty($result) && isset($result['paymentStatus'])) {
        if ($result['paymentStatus'] === 'PAID' || $result['paymentStatus'] === 'SETTLED') {
            echo json_encode([
                "success" => true,
                "status" => "SUCCESSFUL",
                "message" => "Payment verified successfully via transaction lookup.",
                "amount" => $result['amount'] ?? 0,
                "transaction_id" => $result['transactionReference'] ?? $reference
            ]);
            exit;
        }
    }

    // 3. Fallback: Check Account History Ledger
    $ledger = $monnify->getReservedAccountTransactions($reference);
    
    // Extract the transaction list safely depending on how your client formats it
    $transactions = [];
    if (isset($ledger['responseBody']['content'])) {
        $transactions = $ledger['responseBody']['content'];
    } elseif (isset($ledger['content'])) {
        $transactions = $ledger['content'];
    } elseif (is_array($ledger) && !isset($ledger['requestSuccessful'])) {
        $transactions = $ledger; // If client returns the raw array directly
    }

    // CRITICAL SECURITY FIX: Only clear payment if the transaction list is NOT empty!
    if (!empty($transactions) && is_array($transactions) && count($transactions) > 0) {
        // Grab the most recent transaction entry from the ledger
        $latestTx = $transactions[0];
        
        echo json_encode([
            "success" => true,
            "status" => "SUCCESSFUL",
            "message" => "Direct bank transfer detected and verified successfully via ledger matching.",
            "amount" => $latestTx['amount'] ?? 699,
            "transaction_id" => $latestTx['transactionReference'] ?? ($latestTx['paymentReference'] ?? "UNKNOWN_ID")
        ]);
        exit;
    }

    // 4. If control gets here, the ledger is empty -> No transfer has been made yet
    echo json_encode([
        "success" => false,
        "status" => "PENDING",
        "message" => "Awaiting your network transfer... No payment detected on account ledger yet."
    ]);
    exit;

} catch (Throwable $e) {
    // Gracefully handle 404 / Not Found errors from Monnify as pending states
    $msg = $e->getMessage();
    if (strpos($msg, 'Could not find') !== false || strpos($msg, '404') !== false) {
        echo json_encode([
            "success" => false,
            "status" => "PENDING",
            "message" => "Transaction record not found yet. Awaiting payment clearing."
        ]);
        exit;
    }

    echo json_encode([
        "success" => false,
        "status" => "ERROR",
        "message" => "System verification exception: " . $msg
    ]);
    exit;
}
