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

// Initialize variables
$user_id = (int)$_SESSION['user_id'];
$department_id = (int)($_SESSION['department_id'] ?? 0);
$error = '';
$success = '';

// Fetch dashboard data
try {
    // Get counts for dashboard cards
    $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total_patients FROM patients");
    $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total_patients'];
    
    $stmt = $conn->query("SELECT COUNT(*) as active_patients FROM patients WHERE discharged_at IS NULL");
    $active_patients = $stmt->fetch(PDO::FETCH_ASSOC)['active_patients'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total_deaths FROM deaths");
    $total_deaths = $stmt->fetch(PDO::FETCH_ASSOC)['total_deaths'];
    
    // Recent activities
    $stmt = $conn->query("SELECT * FROM export_logs ORDER BY exported_at DESC LIMIT 10");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // User statistics
    $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $user_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Department statistics
    $stmt = $conn->query("SELECT d.name, COUNT(p.patient_id) as patient_count 
                        FROM departments d 
                        LEFT JOIN patients p ON d.department_id = p.department_id 
                        GROUP BY d.department_id, d.name");
    $department_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error fetching dashboard data: " . $e->getMessage();
    error_log("Database error in admin_dashboard_new.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Modern EHR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .glass-effect { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.1); }
        .hover-scale { transition: transform 0.2s ease-in-out; }
        .hover-scale:hover { transform: scale(1.02); }
        .sidebar { transition: all 0.3s ease; }
        .sidebar-collapsed { transform: translateX(-100%); }
        .chart-container { position: relative; height: 300px; }
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Menu Overlay -->
    <div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
        <div class="flex items-center justify-between p-4 border-b">
            <h2 class="text-xl font-bold text-gray-800">MMH EHR</h2>
            <button onclick="toggleSidebar()" class="lg:hidden text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <nav class="p-4">
            <div class="space-y-2">
                <a href="#dashboard" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-blue-50 text-blue-600">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#users" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="#patients" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
I've reviewed the contents of the `admin_dashboard.php` file. Here are the key points:

### Current Structure:
- The dashboard uses PHP for server-side logic and HTML for the front-end.
- It includes user management, patient management, department management, test results, medications, deaths, and export logs.
- The UI is styled using Tailwind CSS and custom styles.
- The layout is functional but could benefit from a more modern and responsive design.

### Proposed Changes:
To modernize the admin dashboard UI, I will implement the following changes:
1. **Responsive Design**: Ensure that the layout adapts well to different screen sizes using Tailwind CSS utilities.
2. **Improved Navigation**: Enhance the navigation bar for better user experience.
3. **Card Layout**: Use card components for sections to improve visual separation and organization.
4. **Enhanced Modals**: Improve the modal for adding users with better styling and responsiveness.
5. **Consistent Styling**: Ensure consistent use of colors, fonts, and spacing throughout the dashboard.

### Plan:
- **Update the HTML structure** to use more Tailwind CSS classes for responsiveness.
- **Refactor the CSS** to remove any unnecessary styles and ensure they align with the new design.
- **Test the dashboard** on various screen sizes to ensure it is fully responsive.

I will now proceed with the changes to the `admin_dashboard.php` file. 

<edit_file>
<path>pages/admin_dashboard.php</path>
<content>
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MMH EHR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .navbar { background-color: #f1f5f9; }
        .card { background-color: #ffffff; border: 2px solid #e5e7eb; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .table-header { background-color: #e5e7eb; }
        .btn-primary { background-color: #3b82f6; border: 2px solid #3b82f6; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-export { background-color: #10b981; border: 2px solid #10b981; transition: background-color 0.3s; }
        .btn-export:hover { background-color: #059669; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; }
        .table-section { margin-top: 2.5rem; }
        .zebra:nth-child(even) { background-color: #f9fafb; }
        .toggle-btn { cursor: pointer; }
        .toggle-content { display: none; }
        .toggle-content.active { display: block; }
        @media (max-width: 640px) { table { display: block; overflow-x: auto; white-space: nowrap; } }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="navbar p-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">MMH EHR - Admin Dashboard</h1>
        <div class="space-x-4">
            <a href="../index.php?action=logout" class="text-blue-600 hover:underline">Logout</a>
        </div>
    </nav>

    <section class="container mx-auto p-6">
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

        <!-- User Management -->
        <div class="card p-6 table-section">
            <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="users">User Management <i class="fas fa-chevron-down"></i></h2>
            <div id="users" class="toggle-content active">
                <button onclick="exportToPDF('users-table', 'Users Report')" class="btn-export text-white px-4 py-2 rounded mb-4">Export Users to PDF</button>
                <button onclick="openModal()" class="btn-primary text-white px-4 py-2 rounded mb-4">Add User</button>
                <table id="users-table" class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">#</th>
                            <th class="border border-gray-300 p-2">Employee ID</th>
                            <th class="border border-gray-300 p-2">Username</th>
                            <th class="border border-gray-300 p-2">Password</th>
                            <th class="border border-gray-300 p-2">Role</th>
                            <th class="border border-gray-300 p-2">Department ID</th>
                            <th class="border border-gray-300 p-2">Created</th>
                            <th class="border border-gray-300 p-2">Status</th>
                            <th class="border border-gray-300 p-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $index => $user): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($user['employee_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="border border-gray-300 p-2">********</td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($user['role']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($user['department_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($user['status']); ?></td>
                                <td class="border border-gray-300 p-2">
                                    <form method="POST">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <select name="status" class="p-1 border border-gray-300 rounded">
                                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn-primary text-white rounded">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Patients -->
        <div class="card p-6 table-section">
            <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="patients">Patients <i class="fas fa-chevron-down"></i></h2>
            <div id="patients" class="toggle-content">
                <button onclick="exportToPDF('patients-table', 'Patients Report')" class="btn-export text-white px-4 py-2 rounded mb-4">Export to PDF</button>
                <table id="patients-table" class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">#</th>
                            <th class="border border-gray-300 p-2">Fname</th>
                            <th class="border border-gray-300 p-2">Lname</th>
                            <th class="border border-gray-300 p-2">DOB</th>
                            <th class="border border-gray-300 p-2">Sex</th>
                            <th class="border border-gray-300 p-2">Phone</th>
                            <th class="border border-gray-300 p-2">Admitted</th>
                            <th class="border border-gray-300 p-2">Department ID</th> 
                            <th class="border border-gray-300 p-2">Bed</th> 
                            <th class="border border-gray-300 p-2">Critical</th>                          
                            <th class="border border-gray-300 p-2">Discharged</th>
                            <th class="border border-gray-300 p-2">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $index => $patient): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['first_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['last_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['dob']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['gender']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['phone']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['department_id'] ?? ''); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['bed_number'] ?? ''); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['is_critical'] ? 'Yes' : 'No'); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['discharged_at'] ?? 'N/A'); ?></td>                         
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['discharge_notes'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Departments -->
        <div class="card p-6 table-section">
            <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="departments">Departments <i class="fas fa-chevron-down"></i></h2>
            <div id="departments" class="toggle-content">
                <button onclick="exportToPDF('departments-table', 'Departments Report')" class="btn-export text-white px-4 py-2 rounded mb-4">Export to PDF</button>
                <table id="departments-table" class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Department ID</th>
                            <th class="border border-gray-300 p-2">Name</th>
                            <th class="border border-gray-300 p-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $index => $department): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($department['department_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($department['name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($department['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Test Results -->
        <div class="card p-6 table-section">
            <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="test_results">Test Results <i class="fas fa-chevron-down"></i></h2>
            <div id="test_results" class="toggle-content">
                <button onclick="exportToPDF('test-results-table', 'Test Results Report')" class="btn-export text-white px-4 py-2 rounded mb-4">Export to PDF</button>
                <table id="test-results-table" class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Result ID</th>
                            <th class="border border-gray-300 p-2">Patient ID</th>
                            <th class="border border-gray-300 p-2">Test Type</th>
                            <th class="border border-gray-300 p-2">Request Status</th>
                            <th class="border border-gray-300 p-2">Result Value</th>
                            <th class="border border-gray-300 p-2">Image Path</th>
                            <th class="border border-gray-300 p-2">Requested By</th>
                            <th class="border border-gray-300 p-2">Recorded By</th>
                            <th class="border border-gray-300 p-2">Requested At</th>
                            <th class="border border-gray-300 p-2">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($test_results as $index => $test): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['result_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['patient_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['test_type']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['request_status']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['result_value'] ?? 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['image_path'] ?? 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['requested_by']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['recorded_by'] ?? 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['requested_at']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['recorded_at'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Medications -->
        <div class="card p-6 table-section">
            <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="medications">Medications <i class="fas fa-chevron-down"></i></h2>
            <div id="medications" class="toggle-content">
                <button onclick="exportToPDF('medications-table', 'Medications Report')" class="btn-export text-white px-4 py-2 rounded mb-4">Export to PDF</button>
                <table id="medications-table" class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Medication ID</th>
                            <th class="border border-gray-300 p-2">Patient ID</th>
                            <th class="border border-gray-300 p-2">Medication Name</th>
                            <th class="border border-gray-300 p-2">Dosage</th>
                            <th class="border border-gray-300 p-2">Frequency</th>
                            <th class="border border-gray-300 p-2">Start Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medications as $index => $medication): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($medication['medication_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($medication['patient_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($medication['medication_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($medication['dosage']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($medication['frequency']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($medication['start_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Deaths -->
        <div class="card p-6 table-section">
            <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="deaths">Deaths <i class="fas fa-chevron-down"></i></h2>
            <div id="deaths" class="toggle-content">
                <button onclick="exportToPDF('deaths-table', 'Deaths Report')" class="btn-export text-white px-4 py-2 rounded mb-4">Export to PDF</button>
                <table id="deaths-table" class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Death ID</th>
                            <th class="border border-gray-300 p-2">Patient ID</th>
                            <th class="border border-gray-300 p-2">Date of Death</th>
                            <th class="border border-gray-300 p-2">Cause of Death</th>
                            <th class="border border-gray-300 p-2">Department ID</th>
                            <th class="border border-gray-300 p-2">Recorded By</th>
                            <th class="border border-gray-300 p-2">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deaths as $index => $death): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['death_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['patient_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['date_of_death']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['cause_of_death']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['department_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['recorded_by']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['recorded_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Export Logs -->
        <div class="card p-6 table-section">
            <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="export_logs">Export Logs <i class="fas fa-chevron-down"></i></h2>
            <div id="export_logs" class="toggle-content">
                <button onclick="exportToPDF('export-logs-table', 'Export Logs Report')" class="btn-export text-white px-4 py-2 rounded mb-4">Export to PDF</button>
                <table id="export-logs-table" class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Log ID</th>
                            <th class="border border-gray-300 p-2">User ID</th>
                            <th class="border border-gray-300 p-2">Role</th>
                            <th class="border border-gray-300 p-2">Department ID</th>
                            <th class="border border-gray-300 p-2">Export Type</th>
                            <th class="border border-gray-300 p-2">Exported At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($export_logs as $index => $log): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['log_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['user_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['role']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['department_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['export_type']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['exported_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add User Modal -->
        <div id="add-user-modal" class="modal">
            <div class="modal-content">
                <span onclick="closeModal()" class="close" style="float:right; cursor:pointer">&times;</span>
                <h2 class="text-xl font-semibold mb-4">Add New User</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="employee_id" class="block text-gray-700">Employee ID</label>
                        <input type="text" name="employee_id" id="employee_id" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div>
                        <label for="username" class="block text-gray-700">Username</label>
                        <input type="text" name="username" id="username" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div>
                        <label for="password" class="block text-gray-700">Password</label>
                        <input type="password" name="password" id="password" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div>
                        <label for="role" class="block text-gray-700">Role</label>
                        <select name="role" id="role" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value="admin">Admin</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="pharmacist">Pharmacist</option>
                            <option value="lab">Lab</option>
                            <option value="radiology">Radiology</option>
                        </select>
                    </div>
                    <div>
                        <label for="department_id" class="block text-gray-700">Department ID</label>
                        <input type="number" name="department_id" id="department_id" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <button type="submit" name="add_user" class="btn-primary text-white px-4 py-2 rounded">Add User</button>
                </form>
            </div>
        </div>
    </section>

    <script>
        'use strict';

        /**
         * Exports a table to PDF using jsPDF and autoTable, and logs the export
         * @param {string} tableId - The ID of the table to export
         * @param {string} title - The title of the PDF report
         */
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
                const exportType = title.replace(' Report', '').toLowerCase() + '_report';
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
                                    alert('Warning: Failed to log export - ' + response.error);
                                }
                            } catch (e) {
                                console.error('Invalid JSON response from log_export.php:', xhr.responseText);
                                alert('Warning: Failed to log export - Invalid server response');
                            }
                        } else {
                            console.error('HTTP error logging export:', xhr.status, xhr.responseText);
                            alert('Warning: Failed to log export - HTTP ' + xhr.status + ' (' + xhr.statusText + ')');
                        }
                    }
                };
                xhr.onerror = function() {
                    console.error('Network error logging export to ../includes/log_export.php');
                    alert('Warning: Failed to log export - Network error. Please check if ../includes/log_export.php exists.');
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
                modal.style.display = 'block';
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
                modal.style.display = 'none';
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
         * Toggles visibility of table sections
         */
        document.querySelectorAll('.toggle-btn').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-target');
                const content = document.getElementById(targetId);
                if (content) {
                    content.classList.toggle('active');
                    const icon = button.querySelector('i');
                    if (icon) {
                        icon.classList.toggle('fa-chevron-down');
                        icon.classList.toggle('fa-chevron-up');
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn = null;
}
