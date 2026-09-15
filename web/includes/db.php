<?php
// web/includes/db.php

date_default_timezone_set('Asia/Kuala_Lumpur');

$host = '127.0.0.1';
$db   = 'synergy1_yuxuan_project9_whatsapp_bot';
$user = 'synergy1_yenping';
$pass = 'R.zb0ZwEuGZ}*fW2'; 
$charset = 'utf8mb4';


$dsn = "mysql:host=$host;port=3307;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    die("Database Connection Failure: " . $e->getMessage());
}