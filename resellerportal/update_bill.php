<?php
session_start();
require_once 'classes/model.php';
require_once 'classes/Bkash.php';
$model = new Model();
$bkash = new Bkash();

if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $customer_id = $_SESSION['customer_id'];
    $type = $_SESSION['type'];
    // echo $type;
    // exit;
    $monthly_bill = $_POST['monthly_bill'];

    if ($monthly_bill > 0) {

        $bkash->makePayment($monthly_bill , $customer_id);
    }
}

?>