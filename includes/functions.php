<?php
// Sanitize input to prevent XSS
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Generate a CSRF token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate a CSRF token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Format datetime to readable format
function format_datetime($datetime) {
    return date("M d, Y H:i", strtotime($datetime));
}

// Simple flash message
function flash($name, $message = '', $class = 'info') {
    if (!empty($message)) {
        $_SESSION[$name] = ['message' => $message, 'class' => $class];
    } elseif (!empty($_SESSION[$name])) {
        $msg = $_SESSION[$name];
        unset($_SESSION[$name]);
        return "<div class='alert alert-{$msg['class']}'>{$msg['message']}</div>";
    }
    return '';
}
?>
