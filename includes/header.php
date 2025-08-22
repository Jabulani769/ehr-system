<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include DB connection
include '../includes/db_connect.php';

// Determine current page
$current_page = basename($_SERVER['PHP_SELF']);

// Set dashboard redirect based on user role
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

// Default values
$username = '';
$department_name = '';
$initials = '';

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
    <title>MMH EHR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/design-system.css">
</head>
<body>
    <!-- Modern Header -->
    <header class="mmh-nav bg-white border-b border-gray-200">
        <div class="mmh-nav-container">
            <!-- Logo Section -->
            <div class="flex items-center">
                <h1 class="text-xl font-bold text-mmh-primary">
                    <i class="fas fa-hospital mr-2"></i>
                    MMH EHR
                </h1>
            </div>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Back Button -->
                    <?php if ($back_url && $current_page !== $dashboard_page && $current_page !== 'index.php'): ?>
                        <a href="<?php echo htmlspecialchars($back_url); ?>" class="mmh-button mmh-button-secondary">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back
                        </a>
                    <?php endif; ?>

                    <!-- Dashboard Button -->
                    <?php if ($current_page !== $dashboard_page && $dashboard_page): ?>
                        <a href="<?php echo htmlspecialchars($dashboard_page); ?>" class="mmh-button mmh-button-primary">
                            <i class="fas fa-home mr-2"></i>
                            Dashboard
                        </a>
                    <?php endif; ?>

                    <!-- User Profile Dropdown -->
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center space-x-3 text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-mmh-primary">
                            <div class="w-8 h-8 rounded-full border-2 border-mmh-primary flex items-center justify-center text-white font-semibold">
                                <?php echo $initials; ?>
                            </div>
                            <div class="hidden lg:block text-right">
                                <div class="font-medium text-gray-900"><?php echo $username; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $department_name; ?></div>
                            </div>
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="user-menu-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <div class="px-4 py-2 border-b">
                                <p class="text-sm font-medium text-gray-900"><?php echo $username; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $department_name; ?></p>
                            </div>
                            <a href="../index.php?action=logout" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Sign out
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="../index.php" class="mmh-button mmh-button-primary">
                        <i class="fas fa-sign-in mr-2"></i>
                        Login
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button id="mobile-menu-button" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-gray-200">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($back_url && $current_page !== $dashboard_page && $current_page !== 'index.php'): ?>
                        <a href="<?php echo htmlspecialchars($back_url); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back
                        </a>
                    <?php endif; ?>

                    <?php if ($current_page !== $dashboard_page && $dashboard_page): ?>
                        <a href="<?php echo htmlspecialchars($dashboard_page); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-mmh-primary hover:bg-gray-100">
                            <i class="fas fa-home mr-2"></i>
                            Dashboard
                        </a>
                    <?php endif; ?>

                    <div class="px-3 py-2 border-t border-gray-200">
                        <div class="flex items-center">
                            <div>
                                <div class="text-base font-medium text-gray-900"><?php echo $username; ?></div>
                                <div class="text-sm text-gray-500"><?php echo $department_name; ?></div>
                            </div>
                        </div>
                        <a href="../index.php?action=logout" class="mt-2 block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i>
                            Sign out
                        </a>
                    </div>
                <?php else: ?>
                    <a href="../index.php" class="block px-3 py-2 rounded-md text-base font-medium text-mmh-primary hover:bg-gray-100">
                        <i class="fas fa-sign-in mr-2"></i>
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <script>
        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }

        // User menu dropdown
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenuDropdown = document.getElementById('user-menu-dropdown');

        if (userMenuButton && userMenuDropdown) {
            userMenuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                userMenuDropdown.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!userMenuButton.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                    userMenuDropdown.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>
