<?php
// Avoid redundant session_start() as it's called in the main page
include '../includes/db_connect.php';

// Determine the current page
$current_page = basename($_SERVER['PHP_SELF']);

// Determine the dashboard page based on role
$dashboard_page = '';
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            $dashboard_page = 'admin_dashboard.php';
            break;
        case 'doctor':
            $dashboard_page = 'doctor_dashboard.php';
            break;
        case 'nurse':
            $dashboard_page = 'nurse_dashboard.php';
            break;
        case 'pharmacist':
            $dashboard_page = 'pharmacist_dashboard.php';
            break;
        case 'lab':
            $dashboard_page = 'lab_dashboard.php';
            break;
        case 'radiology':
            $dashboard_page = 'radiology_dashboard.php';
            break;
        default:
            $dashboard_page = '../index.php';
    }
}

// Check if there's a referrer for the Back button
$back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
?>

<header class="bg-blue-600 text-white p-4 shadow-md">
    <div class="container mx-auto flex justify-between items-center">
        <!-- Hospital Name -->
        <h1 class="text-xl font-bold">Mulanje Mission Hospital EHR</h1>
        
        <!-- Navigation -->
        <nav class="flex items-center space-x-4">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- User Info -->
                <?php
                $department_name = 'No Department';
                if (isset($_SESSION['department_id']) && !empty($_SESSION['department_id'])) {
                    $stmt = $conn->prepare("SELECT name FROM departments WHERE department_id = ?");
                    $stmt->execute([$_SESSION['department_id']]);
                    $dept = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($dept) {
                        $department_name = htmlspecialchars($dept['name']);
                    }
                }
                $username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Unknown';
                ?>
                <span class="text-sm"><?php echo "Welcome, $username ($department_name)"; ?></span>
                
                <!-- Navigation Buttons -->
                <?php if ($back_url && $current_page !== $dashboard_page && $current_page !== 'index.php'): ?>
                    <a href="<?php echo htmlspecialchars($back_url); ?>" 
                       class="bg-blue-500 hover:bg-blue-700 text-white px-3 py-2 rounded-md flex items-center">
                        <i class="fa-solid fa-arrow-left mr-2"></i> Back
                    </a>
                <?php endif; ?>
                <?php if ($current_page !== $dashboard_page && $dashboard_page): ?>
                    <a href="<?php echo $dashboard_page; ?>" 
                       class="bg-blue-500 hover:bg-blue-700 text-white px-3 py-2 rounded-md flex items-center">
                        <i class="fa-solid fa-home mr-2"></i> Dashboard
                    </a>
                <?php endif; ?>
                
                <!-- Logout -->
                <a href="logout.php" class="text-white hover:underline">Logout</a>
            <?php else: ?>
                <!-- Login Link -->
                <a href="../index.php" class="text-white hover:underline">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>