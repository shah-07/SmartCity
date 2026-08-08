<?php
// api/_guard.php - Shared session and authorisation guard for the JSON API.
//
// Every endpoint under api/ includes this first. It starts the session, sets
// the JSON content type, and exposes the checks the endpoints need.
//
// Authorisation is decided from $_SESSION only. A role sent in the request
// body or query string is never trusted: the browser is free to send anything,
// so a citizen could otherwise claim to be an admin just by editing the
// payload.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// The pages are served from the same origin as the API, so no cross-origin
// access is granted. A wildcard Access-Control-Allow-Origin would in any case
// be ignored by the browser for the cookie-carrying requests used here.

/** Ends the request with an error status and a JSON body. */
function json_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

/** Answers a CORS preflight and exits. Call before any other work. */
function handle_preflight()
{
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/** Ends the request with 401 unless a user is logged in. */
function require_auth()
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        json_fail(401, 'Unauthorized - please log in first');
    }
    return [
        'user_id'  => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'],
    ];
}

/** Ends the request with 401/403 unless the logged-in user is an admin. */
function require_admin()
{
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        json_fail(403, 'Forbidden - admin access required');
    }
    return $user;
}

/** The session role ('admin' | 'citizen'), or null when logged out. */
function current_role()
{
    return $_SESSION['role'] ?? null;
}

/**
 * Logs the real error and ends the request with a generic message.
 *
 * Raw PDO messages carry table and column names, so they go to the error log
 * rather than to the browser.
 */
function json_db_error(Throwable $e)
{
    error_log('API database error: ' . $e->getMessage());
    json_fail(500, 'A database error occurred. Please try again.');
}
