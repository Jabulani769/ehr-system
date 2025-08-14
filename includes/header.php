<?php
// Include DB connection
include '../includes/db_connect.php';

// Determine current page
$current_page = basename($_SERVER['PHP_SELF']);

// Set dashboard redirect based on role
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
        case 'radiology':
            $dashboard_page = 'lab_radio_dashboard.php';
            break;
        default:
            $dashboard_page = '../index.php';
    }
}

// Back button support
$back_url = $_SERVER['HTTP_REFERER'] ?? '';
?>

<header class="bg-blue-600 text-white p-4 shadow-md">
    <div class="container mx-auto flex justify-between items-center">
        <!-- Hospital Name -->
        <h1 class="text-xl font-bold">Mulanje Mission Hospital EHR</h1>

        <!-- Navigation -->
        <nav class="flex items-center space-x-4">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                // Set default values
                $username = 'Unknown';
                $department_name = 'No Department';

                // Sanitize and set username if available
                if (!empty($_SESSION['username'])) {
                    $username = htmlspecialchars($_SESSION['username']);
                }

                // Fetch department name from DB if department_id is valid
                if (!empty($_SESSION['department_id']) && is_numeric($_SESSION['department_id'])) {
                    $stmt = $conn->prepare("SELECT name FROM departments WHERE department_id = ?");
                    $stmt->execute([$_SESSION['department_id']]);
                    $dept = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($dept && !empty($dept['name'])) {
                        $department_name = htmlspecialchars($dept['name']);
                    }
                }
                ?>
                <span class="text-sm"><?php echo "$username ($department_name)"; ?></span>

                <!-- Back Button -->
                <?php if ($back_url && $current_page !== $dashboard_page && $current_page !== 'index.php'): ?>
                    <a href="<?php echo htmlspecialchars($back_url); ?>"
                       class="bg-blue-500 hover:bg-blue-700 text-white px-3 py-2 rounded-md flex items-center">
                        <i class="fa-solid fa-arrow-left mr-2"></i> Back
                    </a>
                <?php endif; ?>

                <!-- Dashboard Button -->
                <?php if ($current_page !== $dashboard_page && $dashboard_page): ?>
                    <a href="<?php echo htmlspecialchars($dashboard_page); ?>"
                       class="bg-blue-500 hover:bg-blue-700 text-white px-3 py-2 rounded-md flex items-center">
                        <i class="fa-solid fa-home mr-2"></i> Dashboard
                    </a>
                <?php endif; ?>

                <!-- Logout -->
                <a href="../index.php?action=logout"
                   class="text-white p-1 border-2 border-white rounded-md">
                    Logout
                </a>
            <?php else: ?>
                <!-- If not logged in -->
                <a href="../index.php" class="text-white hover:underline">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
