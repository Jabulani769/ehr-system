<?php
// Secure session settings
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

// Count critical vitals (only for active patients)
$stmt = $conn->prepare("
    SELECT COUNT(*) as critical_count 
    FROM vitals v
    JOIN patients p ON v.patient_id = p.patient_id
    WHERE v.is_critical = TRUE AND p.discharged_at IS NULL
");
$stmt->execute();
$critical_count = $stmt->fetch(PDO::FETCH_ASSOC)['critical_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MMH | Nurse Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>

    <!-- Critical Patient Section -->
    <section class="h-[10vh] pl-20 mt-6">
        <div class="border-l-2 border-red-700">
            <h1 class="pl-2 font-semibold text-red-700">
                <i class="fa-solid fa-triangle-exclamation"></i> Critical Patient (<?php echo $critical_count; ?>)
            </h1>
            <?php if ($critical_patient): ?>
                <p class="pl-2 font-semibold"><?php echo htmlspecialchars($critical_patient['first_name'] . ' ' . $critical_patient['last_name']); ?></p>
                <p class="pl-2">BP: <?php echo htmlspecialchars($critical_patient['blood_pressure']); ?>, HR: <?php echo $critical_patient['heart_rate']; ?>, Temp: <?php echo $critical_patient['temperature']; ?>°C</p>
                <?php if ($critical_count > 1): ?>
                    <a href="vitals.php?action=list" class="pl-2 text-blue-600 hover:underline">View All</a>
                <?php endif; ?>
            <?php else: ?>
                <p class="pl-2">No critical patients</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-14 px-20">
        <!-- Patient Vitals -->
        <div class="w-[30%] p-6 shadow-xl rounded-md bg-white">
            <div class="flex justify-between gap-10 items-center">
                <h1 class="text-[20px] text-blue-500"><i class="fa-solid fa-heart-pulse"></i> Patient Vitals</h1>
                <div class="bg-blue-200 text-red-600 font-semibold px-2 rounded-xl">
                    <p class="text-[14px]"><?php echo $critical_count; ?> Critical</p>
                </div>
            </div>
            <div class="mt-4">
                <a href="vitals.php?action=new" class="block w-full h-10 mt-2 text-white text-center leading-10 bg-blue-500 hover:bg-blue-600 transition rounded-md">
                    <i class="fa-solid fa-plus pr-2"></i> New Reading
                </a>
                <a href="vitals.php?action=history" class="block w-full h-10 mt-2 text-blue-400 border-2 border-blue-400 rounded-md leading-10 text-center">
                    View History
                </a>
            </div>
        </div>

        <!-- Medications -->
        <div class="w-[30%] p-6 shadow-xl rounded-md bg-white">
            <h1 class="text-[20px] text-blue-700"><i class="fa-solid fa-pills"></i> Medications</h1>
            <div class="mt-4">
                <a href="medications.php?view=form" class="block w-full h-10 mt-2 bg-blue-500 hover:bg-blue-700 text-white text-center leading-10 rounded-md">
                    <i class="fa-solid fa-plus pr-2"></i> Add Medication
                </a>
                <a href="medications.php?view=table" class="block w-full h-10 mt-2 text-blue-500 border-2 border-blue-400 text-center leading-10 rounded-md">
                    View Medications
                </a>
            </div>
        </div>

        <!-- Ward Management -->
        <div class="w-[30%] p-6 shadow-xl rounded-md bg-white">
            <h1 class="text-[20px] text-purple-600"><i class="fa-solid fa-bed-pulse"></i> Ward Management</h1>
            <div class="mt-4 flex gap-2">
                <a href="patient_management.php?action=list" class="w-full h-10 mt-2 bg-purple-500 hover:bg-purple-700 text-white text-center leading-10 rounded-md">
                    Patient List
                </a>
                <a href="patient_management.php?action=assign_bed" class="w-full h-10 mt-2 bg-purple-200 hover:bg-purple-300 text-purple-500 text-center leading-10 rounded-md">
                    Assign Bed
                </a>
            </div>
            <div class="mt-2 flex gap-2">
                <a href="patient_management.php?action=admit" class="w-full h-10 border-2 border-purple-500 text-purple-500 text-center leading-10 rounded-md">
                    New Admission
                </a>
                <a href="patient_management.php?action=discharge" class="w-full h-10 border-2 border-purple-500 text-purple-500 text-center leading-10 rounded-md">
                    Discharge
                </a>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="flex flex-wrap justify-center gap-10 self-center mt-14">
        <a href="messages.php" class="bg-green-200 px-2 w-[20%] text-green-900 rounded-md text-center py-2">
            <i class="fa-solid fa-message"></i>
            <p>Ward Messages</p>
        </a>
        <a href="reports.php" class="bg-blue-200 px-2 w-[20%] text-blue-500 rounded-md text-center py-2">
            <i class="fa-solid fa-file"></i>
            <p>Shift Report</p>
        </a>
        <a href="emergency.php" class="bg-red-200 px-2 w-[20%] text-red-500 rounded-md text-center py-2">
            <i class="fa-solid fa-truck-medical"></i>
            <p>Emergency</p>
        </a>
        <a href="lab_results.php" class="bg-gray-200 px-2 w-[20%] text-gray-500 rounded-md text-center py-2">
            <i class="fa-solid fa-flask-vial"></i>
            <p>Lab/Radio Results</p>
        </a>
    </section>
</body>
</html>
