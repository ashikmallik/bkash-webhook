<?php
session_start();

class Bkash
{
    public function makePayment($amount, $ag_id)
    {
        $amount = intval($amount);
        $ag_id  = (string)$ag_id;

        unset($_SESSION['id_token']);

        $tokenData = $this->getToken();
        if (!$tokenData || !isset($tokenData['id_token'])) {
            $_SESSION['msg'] = 'Token generation failed!';
            header("Location: dashboard.php");
            exit();
        }

        $_SESSION['id_token'] = $tokenData['id_token'];
        $InvoiceNumber = 'GNET-' . rand(10000, 99999);

        $requestbody = [
            'mode'                  => '0011',
            'amount'                => $amount,
            'currency'              => 'BDT',
            'intent'                => 'sale',
            'payerReference'        => $ag_id,
            'merchantInvoiceNumber' => $InvoiceNumber,
            'callbackURL'           => "https://gnet.tbotechno.xyz/resellerportal/callback.php"
        ];

        $createurl = "https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/create";

        $headers = [
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization: " . $tokenData['id_token'],
            "X-APP-Key: 0vWQuCRGiUX7EPVjQDr0EUAYtc"
        ];

        $createResponse = $this->curlPost($createurl, $requestbody, $headers);
        if (!$createResponse || !isset($createResponse['bkashURL'])) {
            $_SESSION['msg'] = 'Payment initiation failed! ' . ($createResponse['statusMessage'] ?? 'Unknown error');
            header("Location: dashboard.php");
            exit();
        }

        header("Location: " . $createResponse['bkashURL']);
        exit();
    }

    private function getToken()
    {
        $url = "https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/token/grant";

        $data = [
            'app_key'    => "0vWQuCRGiUX7EPVjQDr0EUAYtc",
            'app_secret' => "jcUNPBgbcqEDedNKdvE4G1cAK7D3hCjmJccNPZZBq96QIxxwAMEx"
        ];
        $password = "D7DaC<*E*eG";

        $headers = [
            "Content-Type: application/json",
            "Accept: application/json",
            "username: 01770618567",
            "password: " . $password,
        ];

        return $this->curlPost($url, $data, $headers);
    }

    private function curlPost($url, $data, $headers = [])
    {
        $ch = curl_init($url);
        $payload = json_encode($data);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $responseData = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            echo "cURL Error: " . $curlError . " | HTTP: " . $httpCode;
            return false;
        }

        $decoded = json_decode($responseData, true);

        // ডিবাগ দেখানো
        if ($httpCode !== 200 && $httpCode !== 201) {
            echo "<h3 style='color:red'>HTTP Code: $httpCode</h3>";
        }

        return $decoded ?: $responseData;
    }
}
?>