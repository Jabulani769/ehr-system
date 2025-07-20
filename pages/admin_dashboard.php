<?php
ini_set('session.cookie_httponly', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include '../includes/db_connect.php';

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$action = $_GET['action'] ?? 'list';

// Pagination settings
$results_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;
$results_per_page = (int)$results_per_page;
$offset = (int)$offset;
if ($results_per_page <= 0 || $offset < 0) {
    header("Location: admin_dashboard.php?error=" . urlencode("Invalid pagination parameters"));
    exit();
}

// Handle user management
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: admin_dashboard.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }
    try {
        if ($action === 'add_user') {
            $employee_id = trim($_POST['employee_id'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = password_hash(trim($_POST['password'] ?? ''), PASSWORD_DEFAULT);
            $role = trim($_POST['role'] ?? '');
            $department_id = (int)$_POST['department_id'];
            if (empty($employee_id) || empty($username) || empty($password) || empty($role) || empty($department_id)) {
                header("Location: admin_dashboard.php?action=add_user&error=" . urlencode("All fields are required"));
                exit();
            }
            $stmt = $conn->prepare("INSERT INTO users (employee_id, username, password, role, department_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$employee_id, $username, $password, $role, $department_id]);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: admin_dashboard.php?success=" . urlencode("User added successfully"));
            exit();
        } elseif ($action === 'edit_user') {
            $user_id = (int)$_POST['user_id'];
            $status = trim($_POST['status'] ?? '');
            if (empty($user_id) || !in_array($status, ['active', 'inactive'])) {
                header("Location: admin_dashboard.php?action=edit_user&user_id=$user_id&error=" . urlencode("Invalid input"));
                exit();
            }
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->execute([$status, $user_id]);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: admin_dashboard.php?success=" . urlencode("User status updated successfully"));
            exit();
        } elseif ($action === 'reset_password') {
            $user_id = (int)$_POST['user_id'];
            $new_password = trim($_POST['new_password'] ?? '');
            if (empty($user_id) || empty($new_password)) {
                header("Location: admin_dashboard.php?action=edit_user&user_id=$user_id&error=" . urlencode("New password is required"));
                exit();
            }
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: admin_dashboard.php?success=" . urlencode("Password reset successfully"));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: admin_dashboard.php?action=$action&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}

// Fetch users
$count_query = "SELECT COUNT(*) as total FROM users";
$stmt = $conn->prepare($count_query);
$stmt->execute();
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_user_pages = ceil($total_users / $results_per_page);

$query = "SELECT user_id, employee_id, username, role, status FROM users ORDER BY user_id DESC LIMIT $results_per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch critical patients
$count_query = "SELECT COUNT(*) as total FROM patients WHERE is_critical = 1 AND discharged_at IS NULL";
$stmt = $conn->prepare($count_query);
$stmt->execute();
$total_critical = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_critical_pages = ceil($total_critical / $results_per_page);

$query = "SELECT patient_id, first_name, last_name, bed_number, admitted_at FROM patients WHERE is_critical = 1 AND discharged_at IS NULL ORDER BY admitted_at DESC LIMIT $results_per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute();
$critical_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments (with error handling)
$departments = [];
try {
    $stmt = $conn->prepare("SELECT department_id, name FROM departments");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to fetch departments: " . $e->getMessage();
    $departments = [
        ['department_id' => 1, 'name' => 'General Medicine'],
        ['department_id' => 2, 'name' => 'Radiology'],
        ['department_id' => 3, 'name' => 'Administration']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Admin Dashboard - MMH EHR</title>
    <style>
        /* Custom styles to match existing theme and enhance UI */
        body {
            font-family: 'Inter', sans-serif;
        }
        .navbar {
            background-color: #f1f5f9; /* bg-slate-100 */
        }
        .card {
            background-color: #ffffff;
            border: 2px solid #e5e7eb; /* border-gray-200 */
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .input-field {
            border-bottom: 2px solid #9ca3af; /* border-gray-400 */
            transition: border-color 0.3s;
        }
        .input-field:focus {
            border-color: #3b82f6; /* border-blue-500 */
            outline: none;
        }
        .btn-primary {
            background-color: #3b82f6; /* bg-blue-500 */
            border: 2px solid #3b82f6;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #2563eb; /* hover:bg-blue-600 */
        }
        .table-header {
            background-color: #e5e7eb; /* bg-gray-200 */
        }
        .pagination a {
            color: #1d4ed8; /* text-blue-600 */
        }
        .pagination a:hover {
            text-decoration: underline;
        }
        @media (max-width: 640px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include '../includes/header.php'; ?> 
    <!-- Navigation Bar -->
    <nav class="navbar p-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800"></h1>
        <div class="space-x-4">
            <a href="admin_dashboard.php?action=add_user" class="text-blue-600 hover:underline">Add User</a>
            <a href="reports.php" class="text-blue-600 hover:underline">View Reports</a>
        </div>
    </nav>

    <section class="container mx-auto p-6">
        <div class="text-center mb-6">
            <p class="text-lg text-gray-700">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'add_user'): ?>
            <div class="card p-6 max-w-lg mx-auto">
                <h2 class="text-xl font-semibold mb-4">Add New User</h2>
                <form action="admin_dashboard.php?action=add_user" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-4">
                        <input type="text" name="employee_id" placeholder="Employee ID" class="input-field w-full p-2" required>
                    </div>
                    <div class="mb-4">
                        <input type="text" name="username" placeholder="Username" class="input-field w-full p-2" required>
                    </div>
                    <div class="mb-4">
                        <input type="password" name="password" placeholder="Password" class="input-field w-full p-2" required>
                    </div>
                    <div class="mb-4">
                        <select name="role" class="input-field w-full p-2" required>
                            <option value="">Select Role</option>
                            <option value="nurse">Nurse</option>
                            <option value="doctor">Doctor</option>
                            <option value="lab">Lab/Radiology</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <select name="department_id" class="input-field w-full p-2" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary w-full p-2 rounded-md text-white">Add User</button>
                </form>
                <div class="mt-4 text-center">
                    <a href="admin_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
                </div>
            </div>
        <?php elseif ($action === 'edit_user' && isset($_GET['user_id'])): ?>
            <?php
            $user_id = (int)$_GET['user_id'];
            $stmt = $conn->prepare("SELECT user_id, employee_id, username, role, status FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user):
            ?>
            <div class="card p-6 max-w-lg mx-auto">
                <h2 class="text-xl font-semibold mb-4">Edit User: <?php echo htmlspecialchars($user['username']); ?></h2>
                <form action="admin_dashboard.php?action=edit_user" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                    <div class="mb-4">
                        <label class="block text-gray-700">Employee ID: <?php echo htmlspecialchars($user['employee_id']); ?></label>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700">Username: <?php echo htmlspecialchars($user['username']); ?></label>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700">Role: <?php echo htmlspecialchars($user['role']); ?></label>
                    </div>
                    <div class="mb-4">
                        <select name="status" class="input-field w-full p-2" required>
                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary w-full p-2 rounded-md text-white">Update Status</button>
                </form>
                <h3 class="text-lg font-semibold mt-6 mb-4">Reset Password</h3>
                <form action="admin_dashboard.php?action=reset_password" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                    <div class="mb-4">
                        <input type="password" name="new_password" placeholder="New Password" class="input-field w-full p-2" required>
                    </div>
                    <button type="submit" class="btn-primary w-full p-2 rounded-md text-white">Reset Password</button>
                </form>
                <div class="mt-4 text-center">
                    <a href="admin_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
                </div>
            </div>
            <?php else: ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    User not found
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card p-6">
                <h2 class="text-xl font-semibold mb-4">Manage Users</h2>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Employee ID</th>
                            <th class="border border-gray-300 p-2">Username</th>
                            <th class="border border-gray-300 p-2">Role</th>
                            <th class="border border-gray-300 p-2">Status</th>
                            <th class="border border-gray-300 p-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['employee_id']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['role']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['status']); ?></td>
                                <td class="border border-gray-300 text-center p-2">
                                    <a href="admin_dashboard.php?action=edit_user&user_id=<?php echo $user['user_id']; ?>" class="text-blue-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($total_user_pages > 1): ?>
                    <div class="pagination mt-4 flex justify-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="admin_dashboard.php?page=<?php echo $page - 1; ?>" class="px-2 py-1">Previous</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_user_pages; $i++): ?>
                            <a href="admin_dashboard.php?page=<?php echo $i; ?>" class="px-2 py-1 <?php echo $i === $page ? 'font-bold' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_user_pages): ?>
                            <a href="admin_dashboard.php?page=<?php echo $page + 1; ?>" class="px-2 py-1">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card p-6 mt-6">
                <h2 class="text-xl font-semibold mb-4">Critical Patients</h2>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Patient</th>
                            <th class="border border-gray-300 p-2">Bed Number</th>
                            <th class="border border-gray-300 p-2">Admitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($critical_patients as $patient): ?>
                            <tr>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($total_critical_pages > 1): ?>
                    <div class="pagination mt-4 flex justify-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="admin_dashboard.php?page=<?php echo $page - 1; ?>" class="px-2 py-1">Previous</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_critical_pages; $i++): ?>
                            <a href="admin_dashboard.php?page=<?php echo $i; ?>" class="px-2 py-1 <?php echo $i === $page ? 'font-bold' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_critical_pages): ?>
                            <a href="admin_dashboard.php?page=<?php echo $page + 1; ?>" class="px-2 py-1">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>