<?php
ini_set('session.cookie_httponly', 1);
session_start();
session_regenerate_id(true);
include '../includes/db_connect.php';

if (!$conn) {
    header("Location: ../index.php?error=" . urlencode("Database connection failed"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: ../index.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    $employee_id = trim($_POST['employee_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($employee_id) || empty($password)) {
        header("Location: ../index.php?error=" . urlencode("All fields are required"));
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT user_id, employee_id, username, password, role, department_id FROM users WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Output user data and password verification
        if (!$user) {
            error_log("No user found for employee_id: $employee_id");
            header("Location: ../index.php?error=" . urlencode("No user found for Employee ID: $employee_id"));
            exit();
        } else {
            error_log("User found: " . print_r($user, true));
            if (!password_verify($password, $user['password'])) {
                error_log("Password verification failed for employee_id: $employee_id");
                header("Location: ../index.php?error=" . urlencode("Incorrect password"));
                exit();
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: ../index.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['department_id'] = $user['department_id'];

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
    exit();
} else {
    header("Location: ../index.php?error=" . urlencode("Invalid request method"));
    exit();
}
?>