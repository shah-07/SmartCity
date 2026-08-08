<?php
// config.php - Database connection for the smart_city database.
$host = 'localhost';
$dbname = 'smart_city';
$username = 'admin';
$password = '3674';

try {
    // utf8mb4 so place names and descriptions round-trip unchanged.
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements: the server never sees interpolated
            // values, and INT/DECIMAL columns come back as numbers rather
            // than strings.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // The message names the host and user, so it goes to the log, not the page.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Database connection failed. Please try again later.']));
}
