<?php
// config.php - Database configuration for your existing smart_city database
$host = 'localhost';
$dbname = 'smart_city';  // Your existing database name
$username = 'admin';      // Changed from 'root' to 'admin'
$password = '3674';       // Added quotes around password (was missing)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>