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
error_log("Session data on doctor_dashboard.php access: " . json_encode($_SESSION) . " at " . date('Y-m-d H:i:s'));

// Security: Validate session and role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'doctor') {
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

// Initialize variables
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

// Fetch patient test results
try {
    $stmt = $conn->prepare("
        SELECT tr.result_id, tr.test_type, tr.request_status, tr.result_value, tr.requested_at, tr.recorded_at,
               p.first_name, p.last_name, p.bed_number,
               IFNULL(d.name, 'Unknown') AS department_name
        FROM test_results tr
        JOIN patients p ON tr.patient_id = p.patient_id
        LEFT JOIN departments d ON p.department_id = d.department_id
        WHERE tr.requested_by = ?
    ");
    $stmt->execute([$user_id]);
    $test_results = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching test results: " . $e->getMessage();
    error_log("Database error in doctor_dashboard.php: " . $e->getMessage());
}

// Handle test request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_test'])) {
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $test_type = htmlspecialchars(trim($_POST['test_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);

    if ($patient_id && $test_type && $department_id) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO test_results (patient_id, test_type, request_status, requested_by, requested_at, department_id)
                VALUES (?, ?, 'requested', ?, NOW(), ?)
            ");
            $stmt->execute([$patient_id, $test_type, $user_id, $department_id]);
            $success = "Test requested successfully.";
        } catch (PDOException $e) {
            $error = "Error requesting test: " . $e->getMessage();
            error_log("Database error in doctor_dashboard.php: " . $e->getMessage());
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
    <title>Doctor Dashboard - MMH EHR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.6/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { background-color: #ffffff; border: 2px solid #e5e7eb; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .table-header { background-color: #e5e7eb; }
        .btn-primary { background-color: #3b82f6; border: 2px solid #3b82f6; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-export { background-color: #10b981; border: 2px solid #10b981; transition: background-color 0.3s; }
        .btn-export:hover { background-color: #059669; }
        @media (max-width: 640px) { table { display: block; overflow-x: auto; white-space: nowrap; } }
    </style>
</head>
<body class="bg-gray-100">
<?php include '../includes/header.php'; ?>

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

        <!-- Test Results -->
        <div class="card p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Patient Test Results</h2>
            <button onclick="exportToPDF()" class="btn-export text-white px-4 py-2 rounded mb-4">Export to PDF</button>
            <?php if (empty($test_results)): ?>
                <p class="text-gray-600">No test results found.</p>
            <?php else: ?>
                <table id="test-results-table" class="w-full border-collapse mb-4">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Patient</th>
                            <th class="border border-gray-300 p-2">Test Type</th>
                            <th class="border border-gray-300 p-2">Status</th>
                            <th class="border border-gray-300 p-2">Result</th>
                            <th class="border border-gray-300 p-2">Bed Number</th>
                            <th class="border border-gray-300 p-2">Department</th>
                            <th class="border border-gray-300 p-2">Requested At</th>
                            <th class="border border-gray-300 p-2">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($test_results as $result): ?>
                            <tr>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['test_type']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['request_status']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['result_value'] ?? 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['bed_number'] ?? 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['department_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['requested_at']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['recorded_at'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Request New Test -->
            <h3 class="text-lg font-semibold mb-2">Request New Test</h3>
            <form method="POST" class="space-y-4">
                <div>
                    <label for="patient_id" class="block text-gray-700">Patient ID</label>
                    <input type="number" name="patient_id" id="patient_id" class="w-full p-2 border border-gray-300 rounded" required>
                </div>
                <div>
                    <label for="test_type" class="block text-gray-700">Test Type</label>
                    <select name="test_type" id="test_type" class="w-full p-2 border border-gray-300 rounded" required>
                        <option value="blood test">Blood Test</option>
                        <option value="urine test">Urine Test</option>
                        <option value="x-ray">X-Ray</option>
                        <option value="ultrasound">Ultrasound</option>
                        <option value="ct scan">CT Scan</option>
                        <option value="mri">MRI</option>
                    </select>
                </div>
                <div>
                    <label for="department_id" class="block text-gray-700">Department ID</label>
                    <input type="number" name="department_id" id="department_id" class="w-full p-2 border border-gray-300 rounded" required>
                </div>
                <button type="submit" name="request_test" class="btn-primary text-white px-4 py-2 rounded">Request Test</button>
            </form>
        </div>
    </section>
    <script>
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.autoTable({
                html: '#test-results-table',
                theme: 'grid',
                headStyles: { fillColor: [229, 231, 235] },
                margin: { top: 10 },
                columnStyles: {
                    0: { cellWidth: 30 }, // Patient
                    1: { cellWidth: 20 }, // Test Type
                    2: { cellWidth: 20 }, // Status
                    3: { cellWidth: 30 }, // Result
                    4: { cellWidth: 20 }, // Bed Number
                    5: { cellWidth: 20 }, // Department
                    6: { cellWidth: 20 }, // Requested At
                    7: { cellWidth: 20 }  // Recorded At
                }
            });
            doc.save('test_results_doctor.pdf');
        }
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn = null;
}
?>