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
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}

// Include database connection
$db_connect_path = __DIR__ . '/../includes/db_connect.php';
if (!file_exists($db_connect_path)) {
    error_log("Database connection file not found at $db_connect_path at " . date('Y-m-d H:i:s'));
    die("Database connection file not found. Contact administrator.");
}
include $db_connect_path;

// Security: Check if $conn is set
if (!isset($conn)) {
    error_log("Database connection failed at " . date('Y-m-d H:i:s'));
    die("Database connection failed. Contact administrator.");
}

// Initialize variables to prevent undefined errors
$user_id = (int)$_SESSION['user_id'];
$department_id = (int)($_SESSION['department_id'] ?? 0);
$error = '';
$success = '';
$users = [];
$patients = [];
$departments = [];
$test_results = [];
$medications = [];
$deaths = [];
$export_logs = [];

// Fetch all users for User Management
try {
    $stmt = $conn->prepare("SELECT user_id, employee_id, username, password, role, department_id, created_at, status FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
    error_log("Database error in admin_dashboard.php (users): " . $e->getMessage());
}

// Fetch all patients
try {
    $stmt = $conn->prepare("SELECT patient_id, first_name, last_name, dob, gender, phone, admitted_at, discharged_at, department_id, bed_number, is_critical, discharge_notes FROM patients");
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching patients: " . $e->getMessage();
    error_log("Database error in admin_dashboard.php (patients): " . $e->getMessage());
}

// Fetch all departments
try {
    $stmt = $conn->prepare("SELECT department_id, name, created_at FROM departments");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching departments: " . $e->getMessage();
    error_log("Database error in admin_dashboard.php (departments): " . $e->getMessage());
}

// Fetch all test_results
try {
    $stmt = $conn->prepare("SELECT result_id, patient_id, test_type, request_status, result_value, image_path, requested_by, recorded_by, requested_at, recorded_at FROM test_results");
    $stmt->execute();
    $test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching test results: " . $e->getMessage();
    error_log("Database error in admin_dashboard.php (test_results): " . $e->getMessage());
}

// Fetch all medications
try {
    $stmt = $conn->prepare("SELECT medication_id, patient_id, medication_name, dosage, frequency, start_date FROM medications");
    $stmt->execute();
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching medications: " . $e->getMessage();
    error_log("Database error in admin_dashboard.php (medications): " . $e->getMessage());
}

// Fetch all deaths
try {
    $stmt = $conn->prepare("SELECT death_id, patient_id, date_of_death, cause_of_death, department_id, recorded_by, recorded_at FROM deaths");
    $stmt->execute();
    $deaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching deaths: " . $e->getMessage();
    error_log("Database error in admin_dashboard.php (deaths): " . $e->getMessage());
}

// Fetch all export_logs
try {
    $stmt = $conn->prepare("SELECT log_id, user_id, role, department_id, export_type, exported_at FROM export_logs");
    $stmt->execute();
    $export_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching export logs: " . $e->getMessage();
    error_log("Database error in admin_dashboard.php (export_logs): " . $e->getMessage());
}

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $status = htmlspecialchars(trim($_POST['status'] ?? ''), ENT_QUOTES, 'UTF-8');
    if ($user_id && in_array($status, ['active', 'inactive'])) {
        try {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->execute([$status, $user_id]);
            $success = "User status updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
            error_log("Database error in admin_dashboard.php (update_status): " . $e->getMessage());
        }
    } else {
        $error = "Invalid user ID or status.";
    }
}

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $employee_id = htmlspecialchars(trim($_POST['employee_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $role = htmlspecialchars(trim($_POST['role'] ?? ''), ENT_QUOTES, 'UTF-8');
    $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $status = 'active';

    if ($employee_id && $username && $password && $role && $department_id) {
        try {
            $stmt = $conn->prepare("INSERT INTO users (employee_id, username, password, role, department_id, created_at, status) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$employee_id, $username, $password, $role, $department_id, $status]);
            $success = "User added successfully.";
        } catch (PDOException $e) {
            $error = "Error adding user: " . $e->getMessage();
            error_log("Database error in admin_dashboard.php (add_user): " . $e->getMessage());
        }
    } else {
        $error = "Please fill all fields.";
    }
}
 
// Default values
$username = 'Guest';
$department_name = '';
$initials = 'G'; // For Guest

// Logged in?
if (isset($_SESSION['user_id'])) {
    // Use session-stored username or fallback
    $username = htmlspecialchars($_SESSION['username'] ?? 'User');

    // Generate initials (first letters of each word)
    $parts = explode(' ', $username);
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    // Fetch department name
    if (!empty($_SESSION['department_id']) && is_numeric($_SESSION['department_id'])) {
        $stmt = $conn->prepare("SELECT name FROM departments WHERE department_id = ?");
        $stmt->execute([$_SESSION['department_id']]);
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dept) {
            $department_name = htmlspecialchars($dept['name']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MMH EHR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .sidebar-item.active {
            border-left: 4px solid #3b82f6;
            background-color: #f8fafc;
            color: #3b82f6; /* Ensure active link text color is blue */
        }
        .sidebar-item.active i {
            color: #3b82f6; /* Ensure active icon color is blue */
        }
        /* Custom styles for modal and toggle functionality, adapted for Tailwind */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            display: flex; /* Use flexbox for centering */
            align-items: center; /* Center vertically */
            justify-content: center; /* Center horizontally */
        }
        .modal-content {
            background-color: #fefefe;
            padding: 20px;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            width: 90%;
            max-width: 600px;
            position: relative; /* For close button positioning */
        }
        .close {
            position: absolute;
            top: 10px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .toggle-content {
            display: none;
        }
        .toggle-content.active {
            display: block;
        }
        /* Ensure table responsiveness for smaller screens */
        @media (max-width: 640px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            th, td {
                min-width: 100px; /* Ensure columns have a minimum width */
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64 h-full border-r border-gray-200 bg-white">
                <div class="flex items-center h-16 px-4 border-b border-gray-200">
                    <h1 class="text-xl font-bold text-gray-800">MMH EHR</h1>
                </div>
                <div class="flex flex-col flex-grow overflow-y-auto">
                    <div class="px-4 py-4">
                        <div class="flex items-center pb-4 border-b border-gray-200">
                            <div class="w-8 h-8 rounded-full border-2 border-mmh-primary flex items-center justify-center text-white font-semibold">
                                <?php echo $initials; ?>
                            </div>
                            <div class="ml-3 text-[12px] text-gray-900">
                                <?php echo htmlspecialchars($username); ?> <br>
                                <?php echo htmlspecialchars($department_name); ?>
                            </div>
                            
                        </div>
                    </div>
                    
                    <nav class="flex-1 px-2 space-y-1">
                        <!-- Dashboard link (active by default, can be changed by JS if needed) -->
                        <a href="#" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50 sidebar-item active" data-target-section="dashboard">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            Dashboard
                        </a>
                        <a href="#" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50 sidebar-item" data-target-section="users">
                            <i class="fas fa-users mr-3"></i>
                            User Management
                        </a>
                        <a href="#" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50 sidebar-item" data-target-section="patients">
                            <i class="fas fa-user-injured mr-3"></i>
                            Patients
                        </a>
                        <a href="#" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50 sidebar-item" data-target-section="departments">
                            <i class="fas fa-hospital mr-3"></i>
                            Departments
                        </a>
                        <a href="#" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50 sidebar-item" data-target-section="test_results">
                            <i class="fas fa-vial mr-3"></i>
                            Test Results
                        </a>
                        <a href="#" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50 sidebar-item" data-target-section="medications">
                            <i class="fas fa-pills mr-3"></i>
                            Medications
                        </a>
                        <a href="#" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50 sidebar-item" data-target-section="deaths">
                            <i class="fas fa-skull-crossbones mr-3"></i>
                            Deaths
                        </a>
                        <a href="#" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50 sidebar-item" data-target-section="export_logs">
                            <i class="fas fa-file-export mr-3"></i>
                            Export Logs
                        </a>
                    </nav>
                    <div class="mt-auto px-4 py-4 border-t border-gray-200">
                        <a href="../index.php?action=logout" class="flex items-center px-3 py-2 text-sm font-medium text-gray-600 rounded-md hover:bg-gray-50">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top navigation -->
            <div class="flex items-center justify-between h-16 px-4 bg-white border-b border-gray-200">
                <div class="flex items-center md:hidden">
                    <button id="mobile-menu-button" class="text-gray-500 focus:outline-none">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                </div>
                <div class="flex items-center space-x-4">
                    <h1 class="text-xl font-bold text-gray-800 hidden md:block">Admin Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                         <div class="w-8 h-8 rounded-full border-2 border-mmh-primary flex items-center justify-center text-white font-semibold">
                            <?php echo $initials; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content area -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6 bg-gray-50">
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Section (New - to be active by default) -->
                <div id="dashboard-section" class="content-section active">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Dashboard Overview</h2>
                    <div class="grid grid-cols-1 gap-5 mb-6 md:grid-cols-2 lg:grid-cols-4">
                        <div class="p-5 bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 bg-blue-100 rounded-full">
                                    <i class="fas fa-user-injured text-blue-600 text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Patients</p>
                                    <p class="text-2xl font-semibold text-gray-800"><?php echo count($patients); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-5 bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 bg-green-100 rounded-full">
                                    <i class="fas fa-users text-green-600 text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Users</p>
                                    <p class="text-2xl font-semibold text-gray-800"><?php echo count($users); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-5 bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 bg-purple-100 rounded-full">
                                    <i class="fas fa-hospital text-purple-600 text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Departments</p>
                                    <p class="text-2xl font-semibold text-gray-800"><?php echo count($departments); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-5 bg-white rounded-lg shadow-sm border border-gray-200">
                            <div class="flex items-center">
                                <div class="p-3 bg-red-100 rounded-full">
                                    <i class="fas fa-skull-crossbones text-red-600 text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Deaths Recorded</p>
                                    <p class="text-2xl font-semibold text-gray-800"><?php echo count($deaths); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <button onclick="openModal()" class="flex items-center justify-center px-4 py-3 text-center text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition duration-150 ease-in-out">
                                <i class="fas fa-user-plus text-blue-600 mr-2"></i>
                                <span class="font-medium">Add New User</span>
                            </button>
                            <button onclick="showSection('patients')" class="flex items-center justify-center px-4 py-3 text-center text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition duration-150 ease-in-out">
                                <i class="fas fa-user-injured text-green-600 mr-2"></i>
                                <span class="font-medium">View Patients</span>
                            </button>
                            <button onclick="showSection('test_results')" class="flex items-center justify-center px-4 py-3 text-center text-purple-700 bg-purple-50 rounded-lg hover:bg-purple-100 transition duration-150 ease-in-out">
                                <i class="fas fa-vial text-purple-600 mr-2"></i>
                                <span class="font-medium">View Test Results</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- User Management -->
                <div id="users-section" class="content-section hidden">
                    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-users mr-2 text-gray-600"></i> User Management
                        </h2>
                        <div class="flex flex-wrap gap-3 mb-4">
                            <button onclick="exportToPDF('users-table', 'Users Report')" class="flex items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 transition duration-150 ease-in-out">
                                <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                            </button>
                            <button onclick="openModal()" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 transition duration-150 ease-in-out">
                                <i class="fas fa-user-plus mr-2"></i> Add User
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table id="users-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Password</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($users as $index => $user): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['user_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['employee_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">********</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['department_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo htmlspecialchars($user['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <form method="POST" class="flex items-center space-x-2">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <select name="status" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                    <button type="submit" name="update_status" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        Update
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Patients -->
                <div id="patients-section" class="content-section hidden">
                    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-user-injured mr-2 text-gray-600"></i> Patients
                        </h2>
                        <button onclick="exportToPDF('patients-table', 'Patients Report')" class="flex items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 mb-4 transition duration-150 ease-in-out">
                            <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                        </button>
                        <div class="overflow-x-auto">
                            <table id="patients-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fname</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lname</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DOB</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sex</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admitted</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department ID</th> 
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bed</th> 
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Critical</th>                          
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discharged</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($patients as $index => $patient): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['first_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['last_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['dob']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['gender']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['phone']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['department_id'] ?? ''); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['bed_number'] ?? ''); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['is_critical'] ? 'Yes' : 'No'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['discharged_at'] ?? 'N/A'); ?></td>                         
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($patient['discharge_notes'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Departments -->
                <div id="departments-section" class="content-section hidden">
                    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-hospital mr-2 text-gray-600"></i> Departments
                        </h2>
                        <button onclick="exportToPDF('departments-table', 'Departments Report')" class="flex items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 mb-4 transition duration-150 ease-in-out">
                            <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                        </button>
                        <div class="overflow-x-auto">
                            <table id="departments-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($departments as $index => $department): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($department['department_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($department['name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($department['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Test Results -->
                <div id="test_results-section" class="content-section hidden">
                    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-vial mr-2 text-gray-600"></i> Test Results
                        </h2>
                        <button onclick="exportToPDF('test-results-table', 'Test Results Report')" class="flex items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 mb-4 transition duration-150 ease-in-out">
                            <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                        </button>
                        <div class="overflow-x-auto">
                            <table id="test-results-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result Value</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image Path</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested By</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested At</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded At</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($test_results as $index => $test): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($test['result_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['patient_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['test_type']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                                    if ($test['request_status'] === 'completed') echo 'bg-green-100 text-green-800';
                                                    else if ($test['request_status'] === 'pending') echo 'bg-yellow-100 text-yellow-800';
                                                    else echo 'bg-gray-100 text-gray-800';
                                                ?>">
                                                    <?php echo htmlspecialchars($test['request_status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['result_value'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['image_path'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['requested_by']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['recorded_by'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['requested_at']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($test['recorded_at'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Medications -->
                <div id="medications-section" class="content-section hidden">
                    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-pills mr-2 text-gray-600"></i> Medications
                        </h2>
                        <button onclick="exportToPDF('medications-table', 'Medications Report')" class="flex items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 mb-4 transition duration-150 ease-in-out">
                            <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                        </button>
                        <div class="overflow-x-auto">
                            <table id="medications-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medication ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medication Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosage</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequency</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($medications as $index => $medication): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($medication['medication_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medication['patient_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medication['medication_name']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medication['dosage']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medication['frequency']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medication['start_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Deaths -->
                <div id="deaths-section" class="content-section hidden">
                    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-skull-crossbones mr-2 text-gray-600"></i> Deaths
                        </h2>
                        <button onclick="exportToPDF('deaths-table', 'Deaths Report')" class="flex items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 mb-4 transition duration-150 ease-in-out">
                            <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                        </button>
                        <div class="overflow-x-auto">
                            <table id="deaths-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Death ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date of Death</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cause of Death</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded At</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($deaths as $index => $death): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($death['death_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($death['patient_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($death['date_of_death']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($death['cause_of_death']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($death['department_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($death['recorded_by']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($death['recorded_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Export Logs -->
                <div id="export_logs-section" class="content-section hidden">
                    <div class="p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                        <h2 class="text-xl font-semibold mb-4 flex items-center">
                            <i class="fas fa-file-export mr-2 text-gray-600"></i> Export Logs
                        </h2>
                        <button onclick="exportToPDF('export-logs-table', 'Export Logs Report')" class="flex items-center px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 mb-4 transition duration-150 ease-in-out">
                            <i class="fas fa-file-pdf mr-2"></i> Export to PDF
                        </button>
                        <div class="overflow-x-auto">
                            <table id="export-logs-table" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Log ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Export Type</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exported At</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($export_logs as $index => $log): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['log_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['user_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['role']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['department_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['export_type']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['exported_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Add User Modal -->
                <div id="add-user-modal" class="modal hidden">
                    <div class="modal-content">
                        <span onclick="closeModal()" class="close">&times;</span>
                        <h2 class="text-xl font-semibold mb-4 text-gray-800">Add New User</h2>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label for="employee_id" class="block text-sm font-medium text-gray-700">Employee ID</label>
                                <input type="text" name="employee_id" id="employee_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                            </div>
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                                <input type="text" name="username" id="username" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                <input type="password" name="password" id="password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                            </div>
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                <select name="role" id="role" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" required>
                                    <option value="admin">Admin</option>
                                    <option value="doctor">Doctor</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="pharmacist">Pharmacist</option>
                                    <option value="lab">Lab</option>
                                    <option value="radiology">Radiology</option>
                                </select>
                            </div>
                            <div>
                                <label for="department_id" class="block text-sm font-medium text-gray-700">Department ID</label>
                                <input type="number" name="department_id" id="department_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                            </div>
                            <button type="submit" name="add_user" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Add User
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        'use strict';
        function exportToPDF(tableId, title) {
            try {
                // Validate inputs
                if (typeof tableId !== 'string' || !tableId) {
                    console.error('Invalid table ID provided');
                    alert('Error: Invalid table ID');
                    return;
                }
                if (typeof title !== 'string' || !title) {
                    title = 'MMH EHR Report';
                }

                // Get the table element
                const table = document.getElementById(tableId);
                if (!table || table.tagName !== 'TABLE') {
                    console.error('Table element not found or invalid:', tableId);
                    alert('Error: Table not found');
                    return;
                }

                // Log export to server
                const exportType = title.replace(' Report', '').toLowerCase().replace(/\s/g, '_') + '_report'; // Sanitize for URL
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '../includes/log_export.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    console.log('Export logged successfully:', response.success);
                                } else {
                                    console.error('Failed to log export:', response.error);
                                    // alert('Warning: Failed to log export - ' + response.error); // Optional: show alert
                                }
                            } catch (e) {
                                console.error('Invalid JSON response from log_export.php:', xhr.responseText);
                                // alert('Warning: Failed to log export - Invalid server response'); // Optional: show alert
                            }
                        } else {
                            console.error('HTTP error logging export:', xhr.status, xhr.responseText);
                            // alert('Warning: Failed to log export - HTTP ' + xhr.status + ' (' + xhr.statusText + ')'); // Optional: show alert
                        }
                    }
                };
                xhr.onerror = function() {
                    console.error('Network error logging export to ../includes/log_export.php');
                    // alert('Warning: Failed to log export - Network error. Please check if ../includes/log_export.php exists.'); // Optional: show alert
                };
                xhr.send(`export_type=${encodeURIComponent(exportType)}&department_id=${encodeURIComponent(<?php echo $department_id; ?>)}`);

                // Initialize jsPDF
                const { jsPDF } = window.jspdf;
                if (!jsPDF) {
                    console.error('jsPDF library not loaded');
                    alert('Error: PDF library not loaded');
                    return;
                }

                const doc = new jsPDF({
                    orientation: 'landscape',
                    unit: 'mm',
                    format: 'a4'
                });

                // Check if autoTable is available
                if (typeof doc.autoTable !== 'function') {
                    console.error('jsPDF autoTable plugin not loaded');
                    alert('Error: PDF table plugin not loaded. Please check your internet connection or CDN availability.');
                    return;
                }

                // Add title
                doc.setFontSize(16);
                doc.text(title, 14, 10);

                // Export table
                doc.autoTable({
                    html: '#' + tableId,
                    theme: 'grid',
                    headStyles: {
                        fillColor: [229, 231, 235],
                        textColor: [0, 0, 0],
                        fontStyle: 'bold'
                    },
                    bodyStyles: {
                        textColor: [0, 0, 0]
                    },
                    margin: { top: 20 },
                    styles: {
                        cellPadding: 1.5,
                        fontSize: 8,
                        overflow: 'linebreak'
                    },
                    columnStyles: {
                        0: { cellWidth: 'auto' }
                    }
                });

                // Save PDF
                const filename = tableId.replace('-table', '') + '.pdf';
                doc.save(filename);
            } catch (error) {
                console.error('Error exporting to PDF:', error);
                alert('Error generating PDF: ' + error.message);
            }
        }

        /**
         * Opens the add user modal
         */
        function openModal() {
            const modal = document.getElementById('add-user-modal');
            if (modal) {
                modal.classList.remove('hidden');
            } else {
                console.error('Modal element not found');
                alert('Error: Modal not found');
            }
        }

        /**
         * Closes the add user modal
         */
        function closeModal() {
            const modal = document.getElementById('add-user-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        /**
         * Closes modal when clicking outside
         */
        window.onclick = function(event) {
            const modal = document.getElementById('add-user-modal');
            if (event.target === modal) {
                closeModal();
            }
        };

        /**
         * Toggles visibility of table sections based on sidebar clicks
         */
        function showSection(sectionId) {
            // Hide all content sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.add('hidden');
            });

            // Show the selected section
            const targetSection = document.getElementById(sectionId + '-section');
            if (targetSection) {
                targetSection.classList.remove('hidden');
            }

            // Update active state in sidebar
            document.querySelectorAll('.sidebar-item').forEach(item => {
                item.classList.remove('active');
            });
            const activeSidebarItem = document.querySelector(`.sidebar-item[data-target-section="${sectionId}"]`);
            if (activeSidebarItem) {
                activeSidebarItem.classList.add('active');
            }
        }

        // Event listeners for sidebar items
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default link behavior
                const targetSectionId = this.getAttribute('data-target-section');
                showSection(targetSectionId);
            });
        });

        // Initial load: ensure dashboard is shown and active
        document.addEventListener('DOMContentLoaded', () => {
            showSection('dashboard'); // Show dashboard by default
        });

        // Mobile menu toggle (if you implement a mobile sidebar)
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', () => {
                const sidebar = document.querySelector('.md\\:flex-shrink-0'); // Adjust selector if needed
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('flex'); // Or toggle a class that makes it visible on mobile
            });
        }

    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn = null;
}
?>
