<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$user = "root";
$pass = "";
$db = "barberia";
$port = 3307;

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset("utf8mb4"); 
} catch (mysqli_sql_exception $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>