<?php
// auth_check.php - Check if user is authenticated
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role']
]);
?>