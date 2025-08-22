<?php
ini_set('session.cookie_httponly', 1);
session_start();
include '../includes/db_connect.php';

// Check if user is a nurse
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}

// Check database connection
if (!$conn) {
    header("Location: ../index.php?error=" . urlencode("Database connection failed"));
    exit();
}

// Fetch critical patients (limit to 1 for display)
$stmt = $conn->prepare("
    SELECT p.patient_id, p.first_name, p.last_name, v.blood_pressure, v.heart_rate, v.temperature
    FROM patients p
    JOIN vitals v ON p.patient_id = v.patient_id
    WHERE v.is_critical = TRUE AND p.discharged_at IS NULL
    LIMIT 1
");
$stmt->execute();
$critical_patient = $stmt->fetch(PDO::FETCH_ASSOC);

// Count critical vitals
$stmt = $conn->prepare("
    SELECT COUNT(*) as critical_count 
    FROM vitals v
    JOIN patients p ON v.patient_id = p.patient_id
    WHERE v.is_critical = TRUE AND p.discharged_at IS NULL
");
$stmt->execute();
$critical_count = $stmt->fetch(PDO::FETCH_ASSOC)['critical_count'];

// Fetch users for recipient dropdown (exclude self)
$user_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'] ?? 1;
$stmt = $conn->prepare("SELECT user_id, username, role FROM users WHERE user_id != ? AND status = 'active'");
$stmt->execute([$user_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MMH | Nurse Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>
    
    <!-- Mobile Navigation Toggle -->
    <div class="md:hidden p-4">
        <button id="mobile-menu-toggle" class="text-gray-600">
            <i class="fas fa-bars text-xl"></i>
        </button>
    </div>

    <!-- Main Container -->
    <div class="container mx-auto px-4 py-6">
        <!-- Critical Patient Alert -->
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                <div>
                    <h3 class="text-red-700 font-semibold">Critical Patients (<?php echo $critical_count; ?>)</h3>
                    <?php if ($critical_patient): ?>
                        <p class="text-red-600"><?php echo htmlspecialchars($critical_patient['first_name'] . ' ' . $critical_patient['last_name']); ?></p>
                        <p class="text-sm">BP: <?php echo htmlspecialchars($critical_patient['blood_pressure']); ?>, HR: <?php echo $critical_patient['heart_rate']; ?></p>
                    <?php else: ?>
                        <p class="text-red-600">No critical patients</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Patient Vitals -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-blue-600">
                        <i class="fas fa-heart-pulse mr-2"></i>Patient Vitals
                    </h3>
                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-sm">
                        <?php echo $critical_count; ?> Critical
                    </span>
                </div>
                <div class="space-y-3">
                    <a href="vitals.php?action=new" class="block w-full bg-blue-500 hover:bg-blue-600 text-white text-center py-2 rounded-md transition">
                        <i class="fas fa-plus mr-2"></i>New Reading
                    </a>
                    <a href="vitals.php?action=history" class="block w-full border border-blue-500 text-blue-500 hover:bg-blue-50 text-center py-2 rounded-md transition">
                        View History
                    </a>
                </div>
            </div>

            <!-- Medications -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-blue-700 mb-4">
                    <i class="fas fa-pills mr-2"></i>Medications
                </h3>
                <div class="space-y-3">
                    <a href="medications.php?view=form" class="block w-full bg-blue-500 hover:bg-blue-600 text-white text-center py-2 rounded-md transition">
                        <i class="fas fa-plus mr-2"></i>Add Medication
                    </a>
                    <a href="medications.php?view=table" class="block w-full border border-blue-500 text-blue-500 hover:bg-blue-50 text-center py-2 rounded-md transition">
                        View Medications
                    </a>
                </div>
            </div>

            <!-- Ward Management -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-purple-600 mb-4">
                    <i class="fas fa-bed-pulse mr-2"></i>Ward Management
                </h3>
                <div class="space-y-3">
                    <a href="patient_management.php?action=list" class="block w-full bg-purple-500 hover:bg-purple-600 text-white text-center py-2 rounded-md transition">
                        Patient List
                    </a>
                    <a href="patient_management.php?action=assign_bed" class="block w-full bg-purple-200 hover:bg-purple-300 text-purple-700 text-center py-2 rounded-md transition">
                        Assign Bed
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <a href="reports.php" class="bg-blue-100 hover:bg-blue-200 text-blue-700 p-4 rounded-lg text-center transition">
                <i class="fas fa-file mr-2"></i>Shift Report
            </a>
            <a href="emergency.php" class="bg-red-100 hover:bg-red-200 text-red-700 p-4 rounded-lg text-center transition">
                <i class="fas fa-truck-medical mr-2"></i>Emergency
            </a>
            <a href="test_results.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 p-4 rounded-lg text-center transition">
                <i class="fas fa-flask-vial mr-2"></i>Lab Results
            </a>
        </div>

        <!-- Responsive Tables -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Recent Vitals</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BP</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HR</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Temp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <!-- Dynamic content would go here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200">
        <div class="flex justify-around py-2">
            <a href="vitals.php" class="flex flex-col items-center text-blue-600">
                <i class="fas fa-heart-pulse"></i>
                <span class="text-xs">Vitals</span>
            </a>
            <a href="medications.php" class="flex flex-col items-center text-blue-600">
                <i class="fas fa-pills"></i>
                <span class="text-xs">Meds</span>
            </a>
            <a href="patient_management.php" class="flex flex-col items-center text-purple-600">
                <i class="fas fa-bed"></i>
                <span class="text-xs">Patients</span>
            </a>
        </div>
    </nav>
</body>
</html>
