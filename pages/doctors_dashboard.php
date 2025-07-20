<?php
ini_set('session.cookie_httponly', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
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
    header("Location: doctor_dashboard.php?error=" . urlencode("Invalid pagination parameters"));
    exit();
}

// Handle medication prescription
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'prescribe') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: doctor_dashboard.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }
    try {
        $patient_id = (int)$_POST['patient_id'];
        $medication_name = trim($_POST['medication_name'] ?? '');
        $dosage = trim($_POST['dosage'] ?? '');
        $frequency = trim($_POST['frequency'] ?? '');
        if (empty($patient_id) || empty($medication_name) || empty($dosage) || empty($frequency)) {
            header("Location: doctor_dashboard.php?action=prescribe&error=" . urlencode("All fields are required"));
            exit();
        }
        $stmt = $conn->prepare("INSERT INTO medications (patient_id, medication_name, dosage, frequency, prescribed_by, start_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$patient_id, $medication_name, $dosage, $frequency, $_SESSION['user_id']]);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: doctor_dashboard.php?success=" . urlencode("Medication prescribed successfully"));
        exit();
    } catch (PDOException $e) {
        header("Location: doctor_dashboard.php?action=prescribe&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}

// Fetch critical patients
$count_query = "SELECT COUNT(*) as total FROM patients WHERE is_critical = 1 AND discharged_at IS NULL";
$stmt = $conn->prepare($count_query);
$stmt->execute();
$total_results = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $results_per_page);

$query = "SELECT patient_id, first_name, last_name, bed_number, admitted_at FROM patients WHERE is_critical = 1 AND discharged_at IS NULL ORDER BY admitted_at DESC LIMIT $results_per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute();
$critical_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch patients for prescription dropdown
$stmt = $conn->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE discharged_at IS NULL");
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Doctor Dashboard - MMH EHR</title>
</head>
<body>
    <section class="self-center h-[10vh] bg-slate-400"></section>
    <section class="mt-10 w-full max-w-4xl m-auto p-6">
        <h1 class="text-[28px] font-semibold text-center">Doctor Dashboard</h1>
        <p class="text-center">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>

        <?php if ($success): ?>
            <p class="text-green-500 text-center"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="text-red-500 text-center"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($action === 'prescribe'): ?>
            <form action="doctor_dashboard.php?action=prescribe" method="POST" class="p-6 bg-white border border-gray-300 rounded-xl shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <h2 class="text-[24px] font-semibold mb-4">Prescribe Medication</h2>
                <div class="mb-4">
                    <select name="patient_id" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['patient_id']; ?>"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <input type="text" name="medication_name" placeholder="Medication Name" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <div class="mb-4">
                    <input type="text" name="dosage" placeholder="Dosage (e.g., 500mg)" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <div class="mb-4">
                    <input type="text" name="frequency" placeholder="Frequency (e.g., Twice daily)" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 rounded-md">Prescribe</button>
            </form>
            <div class="mt-4 text-center">
                <a href="doctor_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <div class="mb-4 flex justify-between">
                <a href="doctor_dashboard.php?action=prescribe" class="text-blue-600 hover:underline">Prescribe Medication</a>
                <a href="lab_results.php" class="text-blue-600 hover:underline">View Lab Results</a>
            </div>
            <h2 class="text-[24px] font-semibold mb-4">Critical Patients</h2>
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-300 p-2">Patient</th>
                        <th class="border border-gray-300 p-2">Bed Number</th>
                        <th class="border border-gray-300 p-2">Admitted At</th>
                        <th class="border border-gray-300 p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($critical_patients as $patient): ?>
                        <tr>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                            <td class="border border-gray-300 p-2">
                                <a href="vitals.php?action=history&patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:underline">Vitals</a> |
                                <a href="lab_results.php?filter=patient&patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:underline">Lab Results</a> |
                                <a href="messages.php?action=send&patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:underline">Message</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
                <div class="mt-4 flex justify-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="doctor_dashboard.php?page=<?php echo $page - 1; ?>" class="text-blue-600 hover:underline">Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="doctor_dashboard.php?page=<?php echo $i; ?>" class="text-blue-600 hover:underline <?php echo $i === $page ? 'font-bold' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="doctor_dashboard.php?page=<?php echo $page + 1; ?>" class="text-blue-600 hover:underline">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="mt-4 text-center">
                <a href="../index.php?action=logout" class="text-blue-600 hover:underline">Logout</a>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>