<?php
session_start();
// Secure session settings
ini_set('session.cookie_httponly', 1);
session_regenerate_id(true);

// Include database connection
include '../includes/db_connect.php';

// Check if database connection is valid
if (!$conn) {
    header("Location: ../index.php?error=" . urlencode("Database connection failed"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: ../index.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    // Sanitize inputs
    $employee_id = trim($_POST['employee_id'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (empty($employee_id) || empty($password)) {
        header("Location: ../index.php?error=" . urlencode("All fields are required"));
        exit();
    }

    // Query user by employee_id
    try {
        $stmt = $conn->prepare("SELECT user_id, employee_id, password, role, department_id FROM users WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        header("Location: ../index.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }

    // Verify credentials
    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID after successful login
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['department_id'] = $user['department_id'];

        // Redirect based on role
        switch ($user['role']) {
            case 'admin':
                header("Location: admin_dashboard.php");
                break;
            case 'doctor':
                header("Location: doctor_dashboard.php");
                break;
            case 'nurse':
                header("Location: nurse_dashboard.php");
                break;
            case 'pharmacist':
                header("Location: pharmacist_dashboard.php");
                break;
            case 'lab':
                header("Location: lab_dashboard.php");
                break;
            case 'radiology':
                header("Location: radiology_dashboard.php");
                break;
            default:
                header("Location: ../index.php?error=" . urlencode("Invalid role"));
                exit();
        }
    } else {
        header("Location: ../index.php?error=" . urlencode("Invalid credentials"));
        exit();
    }
} else {
    header("Location: ../index.php?error=" . urlencode("Invalid request method"));
    exit();
}
?>