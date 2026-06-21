<?php
session_start();
require_once 'classes/model.php';

$model = new Model();

function savePayment($executeResponse, $model)
{
        $type = $_SESSION['type'];
        $dateEn = date('Y-m-d');
        $cusId      = $executeResponse['payerReference'] ?? '';
        $postAmount = $executeResponse['amount'] ?? 0;
        $total_days = date('t');
        
        if (empty($cusId) || empty($postAmount)) {
            error_log("Invalid data in savePayment: " . json_encode($executeResponse));
            return false;
        }
        
        if($type == "reseller"){
            $resellerSingle = $model->rowSql("select * from _reseller_createuser where UserId='$cusId'");
            $resellerNewbalance = $resellerSingle['balance'] + $postAmount;
            $model->updateSql("UPDATE `_reseller_createuser` SET `balance`='$resellerNewbalance' WHERE `UserId`='$cusId'");
            
            $model->insertGetId("INSERT INTO `re_tbl_reseller_account`(`admin_id`, `reseller_id`, `amount`, `type`, `description`, `entry_by`, `entry_date`) VALUES (100,'$cusId','$postAmount ',1,'Balance added from Bkash Payment',100,'$dateEn')");
            $lastracid = $model->insertGetId("INSERT INTO `re_tbl_reseller_account`(`admin_id`, `reseller_id`, `amount`, `type`, `description`, `entry_by`, `entry_date`) VALUES (100,'$cusId','$postAmount',3,'Payment receive from Bkash Payment',100,'$dateEn')");
                        // $lastracid = $connection->getPdo()->lastInsertId();
            $model->insertGetId("INSERT INTO `re_tbl_account`(`reseller_id`, `reseller_account_id`, `acc_amount`, `acc_description`, `acc_type`, `entry_by`, `entry_date`) VALUES ('$cusId','$lastracid','$postAmount','Payment receive from  Bkash Payment)',3,100,'$dateEn')");
            
            return [
                        'status' => true,
                        'message' => 'Reseller balance added successfully',
                        'customer_id' => $cusId,
                        'amount' => $postAmount
                    ];
       
        }else{
            $reCustomerData = $model->rowSql("SELECT * FROM `re_tbl_agent` WHERE ag_id='$cusId'");
            $resellerId = $reCustomerData['entry_by'];
            $resellerBill = $reCustomerData['re_taka'];
            $monthly_bill = $reCustomerData['taka'];
            $secretMdisconnect = $reCustomerData['mikrotik_disconnect'] ;
            $secretBalance = $reCustomerData['balance'] ;
            $secretName = $reCustomerData['ip'] ;
            
            
            $per_day_bill = round($monthly_bill / $total_days, 2);
            
            $paidbilldays = floor($postAmount / $per_day_bill); // paid bill day
            $paidbillrecharge = round(($resellerBill / $total_days) * $paidbilldays); // cut reseller amount
            
            $resellerSingle = $model->rowSql("select * from _reseller_createuser where UserId='$resellerId'");
            
            $resname = $resellerSingle['UserName'] ;
            
            // Calculate the difference in days
            $dateDifference = (strtotime(date('Y-m-d')) - strtotime($secretMdisconnect)) / (60 * 60 * 24);
            $disDate = ($dateDifference > $resellerSingle['freeday']) ? date('Y-m-d') : $secretMdisconnect;
            
            
            $resellerNewbalance = $resellerSingle['balance'] + ($postAmount - $paidbillrecharge);
            $model->updateSql("UPDATE `_reseller_createuser` SET `balance`='$resellerNewbalance' WHERE `UserId`='$resellerId'");
            
            
            if ($postAmount > 0) {
                            $lastracid = $model->insertGetId("INSERT INTO `re_tbl_reseller_account`(`admin_id`, `reseller_id`,  `agent_id`, `amount`, `type`, `description`, `entry_by`, `entry_date`) VALUES (100,'$resellerId','$cusId','$postAmount',3,'Payment receive from Customer $secretName by Bkash Paybill as Bill collection (Reseller $resname Payment)',100,'$dateEn')");
                            // $lastracid = $connection->getPdo()->lastInsertId();
                            $model->insertGetId("INSERT INTO `re_tbl_account`(`reseller_id`, `reseller_account_id`,`cus_id`, `agent_id`, `acc_amount`, `acc_description`, `acc_type`, `entry_by`, `entry_date`) VALUES ('$resellerId','$lastracid','$cusId','$cusId','$postAmount','Payment receive from Customer $secretName by Bkash Paybill as Bill collection (Reseller $resname Payment)',3,100,'$dateEn')");
                        }
            
            
            if ($paidbillrecharge > 0) {
                            $model->insertGetId("INSERT INTO `re_tbl_reseller_account`(`admin_id`, `reseller_id`, `amount`, `type`, `description`, `entry_by`, `entry_date`) VALUES (100,'$resellerId','$postAmount ',1,'Balance added ($resname) for Customer $secretName by Bkash Paybill as Bill collection (Recharge)',100,'$dateEn')");
                        }
            if ($paidbilldays > 0) {
                            $newdisconnect = date('Y-m-d', strtotime($disDate . " +$paidbilldays days"));
                            $form_data_tbl_agent['mikrotik_disconnect'] = $newdisconnect;
                            $form_data_tbl_agent['balance'] = $secretBalance + $postAmount;
                            $form_data_tbl_agent['ag_status'] = 1;
                            $form_data_tbl_agent['paid_amount'] += ($postAmount > 0) ? $postAmount : 0;
                            $form_data_tbl_agent['dueadvance'] -= ($postAmount > 0) ? $postAmount : 0;
                            $form_data_tbl_agent['bill_status'] = 1; //bill paid
                            $form_data_tbl_agent['pay_status'] = 0; //bill paid
    
                            if ($reCustomerData['billgenerate'] == 0) {
                                $form_data_tbl_agent['billgenerate'] = 1; //billgenerate
                                $form_data_tbl_agent['dueadvance'] += $postAmount;
                                $form_data_tbl_agent['generate_amount'] += $postAmount;
                            }
    
                            $model->insertGetId("INSERT INTO `re_tbl_reseller_account`(`reseller_id`, `agent_id`, `amount`, `type`, `description`, `entry_by`, `entry_date`) VALUES ('$resellerId','$cusId','$paidbillrecharge',5,'Balance reacharge for Customer $cusId for  $paidbilldays days disconnect date $newdisconnect  by Bkash (Admin)','$resellerId','$dateEn')");
    
                            $updatefields = '';
                            foreach ($form_data_tbl_agent as $column => $value) {
                                $updatefields .= "`$column` = '$value', ";
                            }
                            $updatefields = rtrim($updatefields, ', ');
                            $model->updateSql("UPDATE `re_tbl_agent` SET $updatefields WHERE `ag_id` = '$cusId'");
    
    
                            // Log::error('Error1: ' . $updatefields);
                            // sms 
                            // $smsNunmber = '88' . $secretPhone;
                            // $this->sendsms("N4t1lZ0dDc72APo8zryT", "Hilsha Net", $smsNunmber, "Dear $secretFullname  Your Internet Bill-$customerAm taka has been paid successfully. Thank you");
                            // $connection->insert("INSERT INTO `tbl_reseller_account`(`admin_id`,`reseller_id`, `amount`, `type`, `description`, `entry_by`, `entry_date`) VALUES (100,'$customerResellerId',2,2,'Balance used for Customer $customerCId ($resname) SMS Sent by new reacharge pay in Bkash Paybill(Admin)',$customerResellerId,'$dateEn')");
                        }
    
            $url = "https://gnet.tbotechno.xyz/re_enable_request_marchant.php?ag_id=" . urlencode($cusId);
            

            $response = @file_get_contents($url);
        
            return [
                'status' => true,
                'message' => 'Customer payment processed successfully',
                'customer_id' => $cusId,
                'amount' => $postAmount,
                're_enable_response' => $response
            ];
        }
    // return $result;
}

// =============== Main Logic ===============
if (isset($_GET['paymentID']) && isset($_GET['status'])) {
    
    $paymentID = $_GET['paymentID'];
    $status    = $_GET['status'];

    if ($status !== 'success') {
        $_SESSION['msg'] = ($status === 'failure') ? 'Payment Failed!' : 'Payment Cancelled!';
        header("Location: dashboard.php");
        exit();
    }

    $data = ['paymentID' => $paymentID];
    $executeUrl = "https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout/execute";
    
    $auth = $_SESSION['id_token'] ?? '';

    if (empty($auth)) {
        $_SESSION['msg'] = 'Session expired. Please try again.';
        header("Location: dashboard.php");
        exit();
    }

    $headers = [
        "Content-Type: application/json",
        "Accept: application/json",
        "Authorization: " . $auth,
        "X-APP-Key: 0vWQuCRGiUX7EPVjQDr0EUAYtc"
    ];

    $ch = curl_init($executeUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $responseData = curl_exec($ch);
    $curlError    = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $_SESSION['msg'] = 'cURL Error: ' . $curlError;
        header("Location: dashboard.php");
        exit();
    }

    $executeResponse = json_decode($responseData, true);

    if (isset($executeResponse['transactionStatus']) && 
        $executeResponse['transactionStatus'] === 'Completed') {
        savePayment($executeResponse, $model);
        $_SESSION['successMsg'] = "✅ Payment Successful! Transaction ID: " . ($executeResponse['trxID'] ?? '');
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['msg'] = $executeResponse['statusMessage'] 
                        ?? $executeResponse['message'] 
                        ?? 'Payment execution failed!';
        header("Location: dashboard.php");
        exit();
    }
} else {
    $_SESSION['msg'] = 'Invalid callback request!';
    header("Location: dashboard.php");
    exit();
}
?>