<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "nexa_agency";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("SYSTEM FAILURE: " . $e->getMessage());
}
?>