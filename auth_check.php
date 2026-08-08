<?php
// auth_check.php - Report whether the caller has a live session.
//
// The pages call this on load to decide whether to redirect to the login form,
// and to learn the caller's real role. The role shown in the UI must come from
// here rather than from sessionStorage, which the user can edit.
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'username'      => $_SESSION['username'] ?? '',
    'role'          => $_SESSION['role']
]);
