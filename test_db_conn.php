<?php
$host = '127.0.0.1';
$db   = 'coop_attaqwa';
$user = 'root';
$pass = '';
$port = '3306';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
    echo "Connected successfully\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
