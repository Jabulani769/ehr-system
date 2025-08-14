<?php
// Security: Enable strict error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security: Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
session_start();

// Security: Validate session and role
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    error_log("Unauthorized access attempt at " . date('Y-m-d H:i:s') . ", role=" . ($_SESSION['role'] ?? 'none'));
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
$db_connect_path = __DIR__ . 'db_connect.php';
if (!file_exists($db_connect_path)) {
    error_log("Database connection file not found at $db_connect_path at " . date('Y-m-d H:i:s'));
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}
include $db_connect_path;

// Security: Check if $conn is set
if (!isset($conn)) {
    error_log("Database connection failed at " . date('Y-m-d H:i:s'));
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = htmlspecialchars($_SESSION['role'] ?? '', ENT_QUOTES, 'UTF-8');
$department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT) ?: 0;
$export_type = htmlspecialchars(trim($_POST['export_type'] ?? ''), ENT_QUOTES, 'UTF-8');

if (!$export_type) {
    http_response_code(400);
    echo json_encode(['error' => 'Export type is required']);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO export_logs (user_id, role, department_id, export_type, exported_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $role, $department_id, $export_type]);
    echo json_encode(['success' => 'Export logged successfully']);
} catch (PDOException $e) {
    error_log("Database error in log_export.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn = null;
?>