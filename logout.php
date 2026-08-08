<?php
// logout.php - Handle logout
session_start();
header('Content-Type: application/json');

$_SESSION = [];

// session_destroy() leaves the cookie in place, so the browser keeps
// presenting a dead session id. Expire it explicitly.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
echo json_encode(['success' => true]);
