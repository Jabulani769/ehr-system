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

// Log session data for debugging
error_log("Session data on index.php access: " . json_encode($_SESSION) . " at " . date('Y-m-d H:i:s'));

// Include database connection
$db_connect_path = __DIR__ . '/includes/db_connect.php';
if (!file_exists($db_connect_path)) {
    error_log("Database connection file not found at $db_connect_path at " . date('Y-m-d H:i:s'));
    die("Database connection file not found. Contact administrator.");
}
include $db_connect_path;

// Initialize variables
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $employee_id = htmlspecialchars(trim($_POST['employee_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'] ?? '';

    if ($employee_id && $password) {
        try {
            $stmt = $conn->prepare("SELECT employee_id, employee_id, password, role, department_id FROM users WHERE employee_id = ? AND status = 'active'");
            $stmt->execute([$employee_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['employee_id'];
               // $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['department_id'] = $user['department_id'];
                error_log("Login successful for user=$employee_id, role={$user['role']}, employee_id={$user['employee_id']} at " . date('Y-m-d H:i:s'));

                // Redirect based on role
                switch (strtolower($user['role'])) {
                    case 'lab':
                    case 'radiology':
                        header("Location: pages/lab_radio_dashboard.php");
                        exit();
                    case 'admin':
                        header("Location: pages/admin_dashboard.php");
                        exit();
                    case 'doctor':
                        header("Location: pages/doctor_dashboard.php");
                        exit();
                    case 'nurse':
                        header("Location: pages/nurse_dashboard.php");
                        exit();
                    case 'pharmacist':
                        header("Location: pages/pharmacist_dashboard.php");
                        exit();
                    default:
                        header("Location: pages/other_dashboard.php");
                        exit();
                }
            } else {
                $error = "Invalid employee_id or password.";
                error_log("Login failed for user=$employee_id at " . date('Y-m-d H:i:s'));
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Database error in index.php: " . $e->getMessage());
        }
    } else {
        $error = "Please enter both id and password.";
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    $success = "Logged out successfully.";
    error_log("User logged out at " . date('Y-m-d H:i:s'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MMH EHR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { background-color: #ffffff; border: 2px solid #e5e7eb; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .btn-primary { background-color: #3b82f6; border: 2px solid #3b82f6; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #2563eb; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="card p-6 w-full max-w-md">
        <h1 class="text-2xl font-bold text-gray-800 mb-4 text-center">MMH EHR - Login</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label for="employee_id" class="block text-gray-700">MMH ID</label>
                <input type="text" name="employee_id" id="employee_id" class="w-full p-2 border border-gray-300 rounded" required>
            </div>
            <div>
                <label for="password" class="block text-gray-700">Password</label>
                <input type="password" name="password" id="password" class="w-full p-2 border border-gray-300 rounded" required>
            </div>
            <button type="submit" name="login" class="btn-primary text-white w-full p-2 rounded">Login</button>
        </form>
    </div>
</body>
</html>
<?php
if (isset($conn)) {
    $conn = null;
}
?>