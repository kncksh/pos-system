<?php
// Idagdag itong 3 lines sa pinakataas para lumabas ang tunay na error
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pos_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4");
    // Alisin muna natin ang 'die' at palitan ng simple echo para ma-test
    // echo "Connected successfully!"; 
} catch (Exception $e) {
    // I-display ang mismong error message ni PHP para malaman natin ang exact issue
    die("Connection failed: " . $e->getMessage());
}
?>