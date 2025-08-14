<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session cookie (if used)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page (adjust path if needed)
header("Location: ../index.php?success=" . urlencode("Logged out successfully"));
exit();
