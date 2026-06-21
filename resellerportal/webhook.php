<?php

// ==================== bKash Webhook - Raw PHP ====================

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bkash_webhook_error.log');

// ==================== Database Configuration ====================
define('DB_HOST', 'localhost');
define('DB_USER', 'tbotechn_user');
define('DB_PASS', 'tbotechn_user');
define('DB_NAME', 'tbotechn_gnet');

function getDbConnection()
{
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        bkash_log('Database Connection Failed', ['error' => $db->connect_error]);
        return null;
    }
    $db->set_charset("utf8mb4");
    return $db;
}

$db = getDbConnection();


function findCustomer($db, $transactionReference)
{
    $transactionReference = trim($transactionReference);

    // re_tbl_agent
    $stmt = $db->prepare("
        SELECT ag_id,'re_tbl_agent' as source
        FROM re_tbl_agent
        WHERE ip = ? OR ag_mobile_no = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $transactionReference, $transactionReference);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($customer) {
        return $customer;
    }

    // tbl_agent
    $stmt = $db->prepare("
        SELECT ag_id,'tbl_agent' as source
        FROM tbl_agent
        WHERE ip = ? OR ag_mobile_no = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $transactionReference, $transactionReference);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($customer) {
        return $customer;
    }

    // reseller username
    $stmt = $db->prepare("
        SELECT UserId,'reseller' as source
        FROM _reseller_createuser
        WHERE UserName = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $transactionReference);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($customer) {
        return $customer;
    }

    return null;
}


// $customer = findCustomer($db, '01999224934');


// Logging Function
function bkash_log($message, $data = [])
{
    $logFile = __DIR__ . '/bkash_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message} " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Get Request Body
$content = file_get_contents('php://input');
$payload = json_decode($content, true);

bkash_log('BKASH IPN Received', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
    'body' => $content
]);

if (!$payload) {
    bkash_log('Invalid JSON', ['content' => $content]);
    http_response_code(200);
    echo 'Invalid JSON';
    exit;
}

$messageType = $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'] ?? $payload['Type'] ?? null;

bkash_log('Message Type', ['type' => $messageType]);

// ==================== Configuration ====================
 $enableURL = 'https://gnet.tbotechno.xyz/view/pages/resellers/billcollection/bkash_partial_recharge_ajax.php';

// ==================== Database Connection Function ====================


// ==================== Subscription Confirmation ====================
if ($messageType === 'SubscriptionConfirmation' || ($payload['Type'] ?? '') === 'SubscriptionConfirmation') {
    
    $subscribeUrl = $payload['SubscribeURL'] ?? null;
    
    if ($subscribeUrl) {
        $ch = curl_init($subscribeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        bkash_log('Subscription Confirmed', [
            'url' => $subscribeUrl,
            'status' => $httpCode
        ]);
    }
    
    http_response_code(200);
    echo 'OK';
    exit;
}

// ==================== Notification Handler ====================
if ($messageType === 'Notification' || ($payload['Type'] ?? '') === 'Notification') {
    
    // Signature Verification
    if (!verifySignature($payload)) {
        bkash_log('Invalid Signature', $payload);
        http_response_code(200);
        echo 'OK';
        exit;
    }

    $message = json_decode($payload['Message'] ?? '{}', true);

    if (($message['transactionStatus'] ?? '') !== 'Completed') {
        bkash_log('Transaction not Completed', ['status' => $message['transactionStatus'] ?? 'N/A']);
        http_response_code(200);
        echo 'OK';
        exit;
    }

    // Extract Data
    $invoice = $message['merchantInvoiceNumber'] ?? null;
    $trxID = $message['trxID'] ?? null;
    $amount = $message['amount'] ?? null;
    $transactionReference = $message['transactionReference'] ?? null;
    $transactionStatus = $message['transactionStatus'] ?? null;
    $paynumber = $message['debitMSISDN'] ?? null;
    $dateTime = $message['dateTime'] ?? date('Y-m-d H:i:s');
    $currency = $message['currency'] ?? 'BDT';

    bkash_log('Payment Successful', [
        'trxID' => $trxID,
        'invoice' => $invoice,
        'amount' => $amount
    ]);

    
    if (!$db) {
        http_response_code(200);
        echo 'OK';
        exit;
    }

    // Duplicate Check
    $stmt = $db->prepare("SELECT id FROM bkash_transactions WHERE trx_id = ? LIMIT 1");
    $stmt->bind_param("s", $trxID);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        bkash_log('Duplicate Transaction', ['trxID' => $trxID]);
        $stmt->close();
        $db->close();
        http_response_code(200);
        echo 'OK';
        exit;
    }
    $stmt->close();

    // Find Customer
    $customer = findCustomer($db, $transactionReference);

    if (!$customer) {
        bkash_log('Customer Not Found', [
            'reference' => $transactionReference,
            'trxID' => $trxID
        ]);
        $db->close();
        http_response_code(200);
        echo 'OK';
        exit;
    }

    // ==================== Process Payment by Source ====================
    $dateEn = date('Y-m-d H:i:s');

    if ($customer['source'] == 're_tbl_agent') {
        
        callEnableApi($enableURL, $customer['ag_id'], $amount);

    } elseif ($customer['source'] == 'tbl_agent') {
        
        $sql = "SELECT function_bill_update(
                    {$customer['ag_id']},
                    'billpay',
                    {$amount},
                    0,
                    '',
                    101,
                    2,
                    'paid by bkash'
                ) AS function_bill_update";
        $db->query($sql);
        
        $url = "https://gnet.tbotechno.xyz/enable_request_marchant.php?ag_id=" . urlencode($customer['ag_id']);

        file_get_contents($url);

    } elseif ($customer['source'] == 'reseller') {
        
        $userId = $customer['UserId'];

        // Get current balance
        $stmt = $db->prepare("SELECT balance FROM _reseller_createuser WHERE UserId = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $reseller = $result->fetch_assoc();
        $stmt->close();

        if ($reseller) {
            $newBalance = $reseller['balance'] + $amount;

            // Update balance
            $stmt = $db->prepare("UPDATE _reseller_createuser SET balance = ? WHERE UserId = ?");
            $stmt->bind_param("di", $newBalance, $userId);
            $stmt->execute();
            $stmt->close();

            // Insert into reseller account (Credit)
            $stmt = $db->prepare("INSERT INTO re_tbl_reseller_account 
                (admin_id, reseller_id, amount, type, description, entry_by, entry_date) 
                VALUES (100, ?, ?, 1, 'Balance added from Bkash Payment Webhook', 100, ?)");
            $stmt->bind_param("ids", $userId, $amount, $dateEn);
            $stmt->execute();
            $stmt->close();

            // Insert into reseller account (Payment Received)
            $stmt = $db->prepare("INSERT INTO re_tbl_reseller_account 
                (admin_id, reseller_id, amount, type, description, entry_by, entry_date) 
                VALUES (100, ?, ?, 3, 'Payment receive from Bkash Payment Webhook', 100, ?)");
            $stmt->bind_param("ids", $userId, $amount, $dateEn);
            $stmt->execute();
            $lastResellerAccountId = $stmt->insert_id;
            $stmt->close();

            // Insert into main account table
            $stmt = $db->prepare("INSERT INTO re_tbl_account 
                (reseller_id, reseller_account_id, acc_amount, acc_description, acc_type, entry_by, entry_date) 
                VALUES (?, ?, ?, 'Payment receive from Bkash Payment Webhook', 3, 100, ?)");
            $stmt->bind_param("iids", $userId, $lastResellerAccountId, $amount, $dateEn);
            $stmt->execute();
            $stmt->close();

            bkash_log('Reseller Balance Updated', [
                'userId' => $userId,
                'amount' => $amount,
                'new_balance' => $newBalance
            ]);
        }
    }
    
    
    
    

        // ==================== Save Transaction (Simple & Stable Version) ====================
    $payment_id     = !empty($trxID) ? $trxID : ($invoice ?? 'N/A');
    $customer_id    = ($customer['source'] !== 'reseller') ? ($customer['ag_id'] ?? 0) : 0;
    $reseller_id    = ($customer['source'] == 'reseller') ? $customer['UserId'] : 0;   // 0 instead of null

    $stmt = $db->prepare("INSERT INTO bkash_transactions 
        (payment_id, type, currency, amount, customer_id, reseller_id, status, 
         trx_id, merchant_invoice_number, payment_time, mobile, created_at)
        VALUES (?, 'webhook', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

    // All parameters as string to avoid type issues
    $stmt->bind_param("sssdssssss",
        $payment_id,           // s
        $currency,             // s
        $amount,               // s (changed to s)
        $customer_id,          // s (changed)
        $reseller_id,          // s (changed)
        $transactionStatus,    // s
        $trxID,                // s
        $invoice,              // s
        $dateTime,             // s
        $paynumber             // s
    );

    if ($stmt->execute()) {
        $insertId = $stmt->insert_id;
        bkash_log('✅ Transaction Saved Successfully', [
            'id'          => $insertId,
            'trxID'       => $trxID,
            'amount'      => $amount,
            'reseller_id' => $reseller_id,
            'customer_id' => $customer_id
        ]);
    } else {
        bkash_log('❌ Failed to Save Transaction', [
            'error' => $stmt->error,
            'errno' => $stmt->errno,
            'payment_id' => $payment_id,
            'trxID' => $trxID
        ]);
    }
    $stmt->close();

    $db->close();
    http_response_code(200);
    echo 'OK';
    exit;
}

// ==================== Helper Functions ====================


function callEnableApi($url, $token, $amount)
{
    if (empty($url)) {
        bkash_log('Enable URL not configured');
        return;
    }

    $query = http_build_query([
        'token' => $token,
        'amount' => $amount
    ]);

    $ch = curl_init($url . '?' . $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    bkash_log('Enable API Called', [
        'status' => $httpCode,
        'response' => $response ? substr($response, 0, 500) : 'No Response'
    ]);
}

function verifySignature($payload)
{
    try {
        $certUrl = $payload['SigningCertURL'] ?? '';

        if (empty($certUrl) || 
            !str_starts_with($certUrl, 'https://sns.') || 
            !str_ends_with($certUrl, '.pem')) {
            return false;
        }

        $ch = curl_init($certUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $cert = curl_exec($ch);
        curl_close($ch);

        if (empty($cert) || !str_contains($cert, '-----BEGIN CERTIFICATE-----')) {
            return false;
        }

        $signature = base64_decode($payload['Signature'] ?? '', true);
        if ($signature === false) return false;

        $stringToSign = buildStringToSign($payload);

        $publicKey = openssl_pkey_get_public($cert);
        if ($publicKey === false) return false;

        $result = openssl_verify($stringToSign, $signature, $publicKey, OPENSSL_ALGO_SHA1);
        openssl_free_key($publicKey);

        return $result === 1;

    } catch (Exception $e) {
        bkash_log('Signature Verification Error', ['error' => $e->getMessage()]);
        return false;
    }
}

function buildStringToSign($payload)
{
    $parts = [];

    $parts[] = "Message\n" . ($payload['Message'] ?? '') . "\n";
    $parts[] = "MessageId\n" . ($payload['MessageId'] ?? '') . "\n";

    if (!empty($payload['Subject'])) {
        $parts[] = "Subject\n" . $payload['Subject'] . "\n";
    }

    $parts[] = "Timestamp\n" . ($payload['Timestamp'] ?? '') . "\n";
    $parts[] = "TopicArn\n" . ($payload['TopicArn'] ?? '') . "\n";
    $parts[] = "Type\n" . ($payload['Type'] ?? '') . "\n";

    if (($payload['Type'] ?? '') === 'SubscriptionConfirmation') {
        $parts[] = "SubscribeURL\n" . ($payload['SubscribeURL'] ?? '') . "\n";
    }

    return implode('', $parts);
}

// Default Response
http_response_code(200);
echo 'OK';
