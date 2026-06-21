<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'tbotechn_user');
define('DB_PASS', 'tbotechn_user');
define('DB_NAME', 'tbotechn_gnet');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
