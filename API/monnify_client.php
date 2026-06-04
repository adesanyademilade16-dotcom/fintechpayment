<?php
/* =============================================
   monnify_client.php
   Monnify API wrapper.
   ============================================= */

require_once __DIR__ . '/config.php';

class MonnifyClient
{
    private string $apiKey;
    private string $secretKey;
    private string $contractCode;
    private string $baseUrl;
    private ?string $accessToken = null;
    private int $tokenExpiry     = 0;

    public function __construct()
    {
        $this->apiKey       = MONNIFY_API_KEY;
        $this->secretKey    = MONNIFY_SECRET_KEY;
        $this->contractCode = MONNIFY_CONTRACT_CODE;
        $this->baseUrl      = MONNIFY_BASE_URL;
    }

    public function createReservedAccount(array $params): array
    {
        if (APP_ENV === 'development') {
            return $this->mockCreateAccount($params);
        }

        $token = $this->getAccessToken();

        $payload = [
            'accountReference'     => $params['accountRef']    ?? $this->generateRef(),
            'accountName'          => $params['accountName']   ?? 'Unknown',
            'currencyCode'         => 'NGN',
            'contractCode'         => $this->contractCode,
            'customerEmail'        => $params['customerEmail'] ?? '',
            'customerName'         => $params['customerName']  ?? $params['accountName'] ?? '',
            'getAllAvailableBanks' => true, // FIXED: Forced to true so Monnify accepts it
        ];

        if (!empty($params['bvn'])) {
            $payload['bvn'] = $params['bvn'];
        }

        $response = $this->request('POST', '/api/v2/bank-transfer/reserved-accounts', $payload, $token);

        if (!$response['success']) {
            throw new RuntimeException(
                $response['error'] ?? 'Monnify reserved account creation failed.'
            );
        }

        $body     = $response['body'];
        $accounts = $body['responseBody']['accounts'] ?? [];
        $primary  = $accounts[0] ?? [];

        return [
            'accountName'   => $body['responseBody']['accountName']   ?? $params['accountName'],
            'bankName'      => $primary['bankName']                   ?? 'Unknown Bank',
            'accountNumber' => $primary['accountNumber']              ?? '',
            'bankCode'      => $primary['bankCode']                   ?? '',
            'allAccounts'   => $accounts,
            'accountRef'    => $body['responseBody']['accountReference'] ?? '',
        ];
    }

    public function verifyPayment(string $paymentReference): array
    {
        if (APP_ENV === 'development') {
            return $this->mockVerifyPayment($paymentReference);
        }

        $token = $this->getAccessToken();
        
        // Step 1: First try checking if it's a direct payment transaction reference
        $endpoint = '/api/v2/merchant/transactions/query?paymentReference=' . urlencode($paymentReference);
        $response = $this->request('GET', $endpoint, [], $token);

        // Step 2: If Monnify returns 404, it means we passed an account profile reference instead.
        // Let's dynamically query Monnify's reserved account transaction history log!
        if (!$response['success'] && ($response['http_code'] ?? 0) == 404) {
            log_event('debug', 'Falling back to query account transaction history ledger for profile ref: ' . $paymentReference);
            
            $historyEndpoint = '/api/v1/bank-transfer/reserved-accounts/transactions?accountReference=' . urlencode($paymentReference) . '&page=0&size=10';
            $historyResponse = $this->request('GET', $historyEndpoint, [], $token);
            
            if ($historyResponse['success'] && !empty($historyResponse['body']['responseBody']['content'])) {
                // Grab the absolute latest transaction record hitting this account
                $latestTx = $historyResponse['body']['responseBody']['content'][0];
                return [
                    'paymentStatus'    => $latestTx['paymentStatus'] ?? 'PAID',
                    'paymentReference' => $latestTx['transactionReference'] ?? '',
                    'amountPaid'       => $latestTx['amountPaid'] ?? 0,
                    '_source'          => 'account_ledger'
                ];
            }
        }

        if (!$response['success']) {
            throw new RuntimeException($response['error'] ?? 'Payment verification failed.');
        }

        return $response['body']['responseBody'] ?? [];
    }

    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $computed = hash_hmac('sha512', $payload, $this->secretKey);
        return hash_equals($computed, $signature);
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken && time() < ($this->tokenExpiry - 60)) {
            return $this->accessToken;
        }

        $credentials = base64_encode("{$this->apiKey}:{$this->secretKey}");

        $response = $this->request('POST', '/api/v1/auth/login', [], null, [
            'Authorization: Basic ' . $credentials,
        ]);

        if (!$response['success']) {
            throw new RuntimeException('Monnify authentication failed. Check your API credentials.');
        }

        $body = $response['body']['responseBody'] ?? [];

        $this->accessToken = $body['accessToken']  ?? '';
        $expiresIn         = $body['expiresIn']    ?? 3600;
        $this->tokenExpiry = time() + (int) $expiresIn;

        log_event('debug', 'Monnify token refreshed', ['expires_in' => $expiresIn]);

        return $this->accessToken;
    }

    private function request(
        string  $method,
        string  $endpoint,
        array   $payload    = [],
        ?string $token      = null,
        array   $extraHeaders = []
    ): array {
        $url = $this->baseUrl . $endpoint;

        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders);

        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false, // Set to false to prevent SSL handshake drops on free servers
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            log_event('error', 'cURL error', ['error' => $curlErr, 'endpoint' => $endpoint]);
            return ['success' => false, 'error' => 'Network error: ' . $curlErr];
        }

        $decoded = json_decode($raw, true);

        if ($httpCode >= 200 && $httpCode < 300 && ($decoded['requestSuccessful'] ?? false)) {
            return ['success' => true, 'body' => $decoded, 'http_code' => $httpCode];
        }

        log_event('error', 'Monnify API error', [
            'endpoint'  => $endpoint,
            'http_code' => $httpCode,
            'response'  => $decoded,
        ]);

        return [
            'success'   => false,
            'http_code' => $httpCode,
            'error'     => $decoded['responseMessage'] ?? 'Monnify request failed.',
            'body'      => $decoded,
        ];
    }

    private function mockCreateAccount(array $params): array
    {
        $banks = ['Providus Bank', 'Wema Bank', 'Sterling Bank'];
        $bank  = $banks[array_rand($banks)];
        $mockNumber = '77' . rand(10000000, 99999999);

        return [
            'accountName'   => $params['accountName']   ?? 'Adesanya Ibrahim',
            'bankName'      => $bank,
            'accountNumber' => $mockNumber,
            'bankCode'      => '101',
            'allAccounts'   => [
                ['bankName' => $bank, 'accountNumber' => $mockNumber, 'bankCode' => '101'],
            ],
            'accountRef'    => $params['accountRef'] ?? $this->generateRef(),
            '_mock'         => true,
        ];
    }

    private function mockVerifyPayment(string $ref): array
    {
        return [
            'paymentReference' => $ref,
            'amountPaid'       => 5000.00,
            'paidOn'           => date('Y-m-d H:i:s'),
            'paymentStatus'    => 'PAID',
            '_mock'            => true,
        ];
    }

    private function generateRef(): string
    {
        return 'VA-' . strtoupper(bin2hex(random_bytes(8))) . '-' . time();
    }
}
