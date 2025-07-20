<?php
session_start();
include '../includes/db_connect.php';

// Check if user is logged in and department set
if (!isset($_SESSION['user_id'], $_SESSION['department'])) {
    header('Location: ../index.php?error=' . urlencode('Please log in'));
    exit();
}

// Check POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: messages.php?action=compose&error=' . urlencode('Invalid request method.'));
    exit();
}

// CSRF token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: messages.php?action=compose&error=' . urlencode('Invalid CSRF token.'));
    exit();
}

// Validate inputs
$receiver_department = trim($_POST['receiver_department'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');

$valid_departments = ['nurse', 'doctor', 'admin', 'radiology/pharmacy'];

$errors = [];
if ($receiver_department === '' || !in_array($receiver_department, $valid_departments)) {
    $errors[] = "Please select a valid recipient department.";
}
if ($subject === '') {
    $errors[] = "Subject is required.";
}
if ($body === '') {
    $errors[] = "Message body cannot be empty.";
}

if (!empty($errors)) {
    $error_msg = implode(' ', $errors);
    header('Location: messages.php?action=compose&error=' . urlencode($error_msg));
    exit();
}

// Insert message into DB
try {
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_department, subject, body, sent_at, is_read) VALUES (?, ?, ?, ?, NOW(), FALSE)");
    $stmt->execute([$_SESSION['user_id'], $receiver_department, $subject, $body]);

    header('Location: messages.php?action=inbox&success=' . urlencode('Message sent successfully.'));
    exit();
} catch (PDOException $e) {
    // Log the error in production, do not show raw error to users
    header('Location: messages.php?action=compose&error=' . urlencode('Failed to send message. Please try again.'));
    exit();
}
