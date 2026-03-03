<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load dependencies
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Session.php';

// 1. Get the token from the cookie
$token = $_COOKIE['authToken'] ?? null;

// 2. Initialize database and model
$database = new Database();
$db = $database->getConnection();
$sessionModel = new Session($db);

// 3. Delete the session from the database (token invalidation)
if ($token) {
    $sessionModel->delete($token);
}

// 4. Clear the authentication cookie in the browser
setcookie('authToken', '', [
    'expires' => time() - 3600, // Set expiry to the past
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// 5. Destroy the PHP session
session_unset();
session_destroy();

// 6. Redirect to the login page
header("Location: login.php");
exit;
