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
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), ['admin', 'nurse'])) {
    error_log("Unauthorized access attempt at " . date('Y-m-d H:i:s') . ", role=" . ($_SESSION['role'] ?? 'none'));
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}

// Include database connection
include '../includes/db_connect.php';

// Include TCPDF for PDF export
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/config/tcpdf_config.php';

// Check TCPDF constants
if (!defined('PDF_PAGE_ORIENTATION')) {
    error_log("TCPDF constants not loaded at " . date('Y-m-d H:i:s'));
    die("TCPDF constants not loaded properly!");
}

// Get user role and department
$user_role = strtolower($_SESSION['role']);
// Validate user_id
$user_id = $_SESSION['user_id'];
if (!is_numeric($user_id)) {
    // Fallback: Try to map string user_id to integer from users table
    try {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? OR username = ?");
        $stmt->execute([$user_id, $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && is_numeric($user['user_id'])) {
            $user_id = (int)$user['user_id'];
            $_SESSION['user_id'] = $user_id; // Update session with integer
        } else {
            $error = "Invalid user ID format in session.";
            error_log("Invalid user_id in session: " . var_export($_SESSION['user_id'], true));
            header("Location: ../index.php?error=" . urlencode("Invalid user ID"));
            exit();
        }
    } catch (PDOException $e) {
        $error = "Database error validating user ID: " . $e->getMessage();
        error_log("Database error in reports.php: " . $e->getMessage());
        header("Location: ../index.php?error=" . urlencode("Database error"));
        exit();
    }
} else {
    $user_id = (int)$user_id; // Ensure integer
}
$user_department_id = filter_var($_SESSION['department_id'] ?? 0, FILTER_VALIDATE_INT);
if ($user_department_id === false) {
    $user_department_id = 0; // Default to 0 if invalid
}
$error = '';
$success = '';

// Set default time range (last 24 hours)
$start_time = date('Y-m-d H:i:s', strtotime('-24 hours'));
$end_time = date('Y-m-d H:i:s');

// Handle date filter form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter_report'])) {
    $start_time = htmlspecialchars(trim($_POST['start_time'] ?? ''), ENT_QUOTES, 'UTF-8');
    $end_time = htmlspecialchars(trim($_POST['end_time'] ?? ''), ENT_QUOTES, 'UTF-8');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $start_time) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $end_time)) {
        $error = "Invalid date-time format for start or end time.";
    } else {
        // Convert to MySQL datetime format
        $start_time = str_replace('T', ' ', $start_time) . ':00';
        $end_time = str_replace('T', ' ', $end_time) . ':00';
        // Validate as actual dates
        if (!DateTime::createFromFormat('Y-m-d H:i:s', $start_time) || 
            !DateTime::createFromFormat('Y-m-d H:i:s', $end_time)) {
            $error = "Invalid date-time values provided.";
        }
    }
}

try {
    // Fetch data for reports
    if ($user_role === 'admin') {
        // Admin: System-wide report
        $stmt = $conn->prepare("
            SELECT p.patient_id, p.first_name, p.last_name, p.bed_number, p.admitted_at, p.is_critical,
                   IFNULL(d.name, 'Unknown') as department_name,
                   u.username as recorded_by
            FROM patients p
            LEFT JOIN departments d ON p.department_id = d.department_id
            LEFT JOIN users u ON u.department_id = p.department_id
            WHERE p.admitted_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_time, $end_time]);
        $admitted_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $admitted_count = count($admitted_patients);

        $stmt = $conn->prepare("
            SELECT p.patient_id, p.first_name, p.last_name, p.bed_number, p.admitted_at,
                   IFNULL(d.name, 'Unknown') as department_name,
                   u.username as recorded_by
            FROM patients p
            LEFT JOIN departments d ON p.department_id = d.department_id
            LEFT JOIN users u ON u.department_id = p.department_id
            WHERE p.is_critical = 1 AND p.discharged_at IS NULL
            AND p.admitted_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_time, $end_time]);
        $critical_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $critical_count = count($critical_patients);

        $stmt = $conn->prepare("
            SELECT d.death_id, p.first_name, p.last_name, d.date_of_death, d.cause_of_death,
                   IFNULL(d2.name, 'Unknown') as department_name,
                   u.username as recorded_by
            FROM deaths d
            JOIN patients p ON d.patient_id = p.patient_id
            LEFT JOIN departments d2 ON d.department_id = d2.department_id
            JOIN users u ON d.recorded_by = u.user_id
            WHERE d.recorded_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_time, $end_time]);
        $deaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $death_count = count($deaths);

        $stmt = $conn->prepare("
            SELECT p.first_name, p.last_name, l.test_type, l.request_status, l.result_value, l.requested_at,
                   IFNULL(d.name, 'Unknown') as department_name
            FROM test_results l
            JOIN patients p ON l.patient_id = p.patient_id
            LEFT JOIN departments d ON p.department_id = d.department_id
            WHERE l.requested_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_time, $end_time]);
        $test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("
            SELECT p.first_name, p.last_name, m.medication_name, m.dosage, m.frequency, m.start_date,
                   IFNULL(d.name, 'Unknown') as department_name
            FROM medications m
            JOIN patients p ON m.patient_id = p.patient_id
            LEFT JOIN departments d ON p.department_id = d.department_id
            WHERE m.start_date BETWEEN ? AND ?
        ");
        $stmt->execute([$start_time, $end_time]);
        $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("
            SELECT el.log_id, el.user_id, el.role, el.department_id, el.export_type, el.exported_at,
                   IFNULL(d.name, 'Unknown') as department_name,
                   u.username as exported_by
            FROM export_logs el
            LEFT JOIN departments d ON el.department_id = d.department_id
            LEFT JOIN users u ON el.user_id = u.user_id
            WHERE el.exported_at BETWEEN ? AND ?
        ");
        $stmt->execute([$start_time, $end_time]);
        $export_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $export_count = count($export_logs);
    } else {
        // Nurse: Department-specific shift report
        if ($user_department_id === 0) {
            $error = "User department not set (department_id = 0). Contact the administrator.";
        } else {
            $stmt = $conn->prepare("
                SELECT p.patient_id, p.first_name, p.last_name, p.bed_number, p.admitted_at, p.is_critical,
                       u.username as recorded_by
                FROM patients p
                LEFT JOIN users u ON u.department_id = p.department_id
                WHERE p.department_id = ? AND p.admitted_at BETWEEN ? AND ?
            ");
            $stmt->execute([$user_department_id, $start_time, $end_time]);
            $admitted_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $admitted_count = count($admitted_patients);

            $stmt = $conn->prepare("
                SELECT p.patient_id, p.first_name, p.last_name, p.bed_number, p.admitted_at,
                       u.username as recorded_by
                FROM patients p
                LEFT JOIN users u ON u.department_id = p.department_id
                WHERE p.department_id = ? AND p.is_critical = 1 AND p.discharged_at IS NULL
                AND p.admitted_at BETWEEN ? AND ?
            ");
            $stmt->execute([$user_department_id, $start_time, $end_time]);
            $critical_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $critical_count = count($critical_patients);

            $stmt = $conn->prepare("
                SELECT d.death_id, p.first_name, p.last_name, d.date_of_death, d.cause_of_death,
                       u.username as recorded_by
                FROM deaths d
                JOIN patients p ON d.patient_id = p.patient_id
                JOIN users u ON d.recorded_by = u.user_id
                WHERE d.department_id = ? AND d.recorded_at BETWEEN ? AND ?
            ");
            $stmt->execute([$user_department_id, $start_time, $end_time]);
            $deaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $death_count = count($deaths);
        }
    }

    // Handle PDF export
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_pdf'])) {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('MMH EHR');
        $pdf->SetTitle($user_role === 'admin' ? 'System Report' : 'Shift Report');
        $pdf->SetHeaderData('', 0, 'MMH EHR Report', $start_time . ' to ' . $end_time);
        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $pdf->AddPage();
        $html = '';

        if ($user_role === 'admin') {
            $html .= "<h1>System-wide Report ($start_time to $end_time)</h1>";
            $html .= "<h2>Admitted Patients ($admitted_count)</h2>";
            if (empty($admitted_patients)) {
                $html .= "<p>No patients admitted in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Patient</th><th>Bed Number</th><th>Admitted At</th><th>Department</th><th>Recorded By</th></tr>";
                foreach ($admitted_patients as $patient) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['bed_number']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['admitted_at']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['department_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['recorded_by'] ?? 'N/A') . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            $html .= "<h2>Critical Patients Admitted ($critical_count)</h2>";
            if (empty($critical_patients)) {
                $html .= "<p>No critical patients admitted in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Patient</th><th>Bed Number</th><th>Admitted At</th><th>Department</th><th>Recorded By</th></tr>";
                foreach ($critical_patients as $patient) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['bed_number']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['admitted_at']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['department_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['recorded_by'] ?? 'N/A') . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            $html .= "<h2>Deaths Recorded ($death_count)</h2>";
            if (empty($deaths)) {
                $html .= "<p>No deaths recorded in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Patient</th><th>Date of Death</th><th>Cause of Death</th><th>Department</th><th>Recorded By</th></tr>";
                foreach ($deaths as $death) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($death['first_name'] . ' ' . $death['last_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($death['date_of_death']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($death['cause_of_death']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($death['department_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($death['recorded_by']) . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            $html .= "<h2>Test Results</h2>";
            if (empty($test_results)) {
                $html .= "<p>No test results recorded in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Patient</th><th>Test Type</th><th>Status</th><th>Result</th><th>Requested At</th><th>Department</th></tr>";
                foreach ($test_results as $result) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($result['first_name'] . ' ' . $result['last_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($result['test_type']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($result['request_status']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($result['result_value'] ?? 'N/A') . "</td>";
                    $html .= "<td>" . htmlspecialchars($result['requested_at']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($result['department_name']) . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            $html .= "<h2>Medications</h2>";
            if (empty($medications)) {
                $html .= "<p>No medications recorded in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Patient</th><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Start Date</th><th>Department</th></tr>";
                foreach ($medications as $med) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($med['first_name'] . ' ' . $med['last_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($med['medication_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($med['dosage']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($med['frequency'] ?? 'Not specified') . "</td>";
                    $html .= "<td>" . htmlspecialchars($med['start_date']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($med['department_name']) . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            $html .= "<h2>Export Logs ($export_count)</h2>";
            if (empty($export_logs)) {
                $html .= "<p>No exports recorded in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Log ID</th><th>User ID</th><th>Role</th><th>Department</th><th>Export Type</th><th>Exported At</th><th>Exported By</th></tr>";
                foreach ($export_logs as $log) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($log['log_id']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($log['user_id']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($log['role']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($log['department_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($log['export_type']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($log['exported_at']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($log['exported_by'] ?? 'N/A') . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            // Log export action
            $stmt = $conn->prepare("
                INSERT INTO export_logs (user_id, role, department_id, export_type, exported_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $user_role, $user_department_id, 'system_report']);
        } else {
            $html .= "<h1>Shift Report ($start_time to $end_time)</h1>";
            $html .= "<h2>Admitted Patients ($admitted_count)</h2>";
            if (empty($admitted_patients)) {
                $html .= "<p>No patients admitted in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Patient</th><th>Bed Number</th><th>Admitted At</th><th>Critical</th><th>Recorded By</th></tr>";
                foreach ($admitted_patients as $patient) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['bed_number']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['admitted_at']) . "</td>";
                    $html .= "<td>" . ($patient['is_critical'] ? 'Yes' : 'No') . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['recorded_by'] ?? 'N/A') . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            $html .= "<h2>Critical Patients Admitted ($critical_count)</h2>";
            if (empty($critical_patients)) {
                $html .= "<p>No critical patients admitted in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Patient</th><th>Bed Number</th><th>Admitted At</th><th>Recorded By</th></tr>";
                foreach ($critical_patients as $patient) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['bed_number']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['admitted_at']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($patient['recorded_by'] ?? 'N/A') . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            $html .= "<h2>Deaths Recorded ($death_count)</h2>";
            if (empty($deaths)) {
                $html .= "<p>No deaths recorded in this period.</p>";
            } else {
                $html .= "<table border='1' cellpadding='5'>";
                $html .= "<tr><th>Patient</th><th>Date of Death</th><th>Cause of Death</th><th>Recorded By</th></tr>";
                foreach ($deaths as $death) {
                    $html .= "<tr>";
                    $html .= "<td>" . htmlspecialchars($death['first_name'] . ' ' . $death['last_name']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($death['date_of_death']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($death['cause_of_death']) . "</td>";
                    $html .= "<td>" . htmlspecialchars($death['recorded_by']) . "</td>";
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            // Log export action
            $stmt = $conn->prepare("
                INSERT INTO export_logs (user_id, role, department_id, export_type, exported_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $user_role, $user_department_id, 'shift_report']);
        }
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($user_role === 'admin' ? 'system_report.pdf' : 'shift_report.pdf', 'D');
        exit();
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Database error in reports.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - MMH EHR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .navbar { background-color: #f1f5f9; }
        .card { background-color: #ffffff; border: 2px solid #e5e7eb; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .table-header { background-color: #e5e7eb; }
        .btn-primary { background-color: #3b82f6; border: 2px solid #3b82f6; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-secondary { background-color: #10b981; border: 2px solid #10b981; transition: background-color 0.3s; }
        .btn-secondary:hover { background-color: #059669; }
        .sticky { position: sticky; top: 0; z-index: 10; background-color: #f1f5f9; }
        .table-section { margin-top: 2.5rem; }
        .zebra:nth-child(even) { background-color: #f9fafb; }
        .toggle-btn { cursor: pointer; }
        .toggle-content { display: none; }
        .toggle-content.active { display: block; }
        @media (max-width: 640px) { 
            table { display: block; overflow-x: auto; white-space: nowrap; }
            .sticky { position: static; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="navbar p-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">MMH EHR - Reports</h1>
        <div class="space-x-4">
            <?php if ($user_role === 'admin'): ?>
                <a href="admin_dashboard.php" class="text-blue-600 hover:underline">Back to Admin Dashboard</a>
            <?php endif; ?>
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

        <!-- Date Filter Form -->
        <div class="card p-6 mb-6 sticky">
            <h2 class="text-xl font-semibold mb-4">Filter Report by Date</h2>
            <form method="POST" action="">
                <div class="flex flex-col sm:flex-row sm:space-x-4 mb-4">
                    <div class="flex-1">
                        <label for="start_time" class="block text-gray-700">Start Time</label>
                        <input type="datetime-local" name="start_time" id="start_time" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $start_time)); ?>" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div class="flex-1">
                        <label for="end_time" class="block text-gray-700">End Time</label>
                        <input type="datetime-local" name="end_time" id="end_time" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $end_time)); ?>" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                </div>
                <div class="flex space-x-4">
                    <button type="submit" name="filter_report" class="btn-primary text-white px-4 py-2 rounded">Apply Filter</button>
                    <button type="submit" name="export_pdf" class="btn-secondary text-white px-4 py-2 rounded">Export to PDF</button>
                </div>
            </form>
        </div>

        <!-- Report Content -->
        <?php if ($user_role === 'admin'): ?>
            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="admitted-patients">Admitted Patients (<?php echo $admitted_count; ?>) <i class="fas fa-chevron-down"></i></h2>
                <div id="admitted-patients" class="toggle-content active">
                    <?php if (empty($admitted_patients)): ?>
                        <p class="text-gray-600">No patients admitted in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Patient</th>
                                    <th class="border border-gray-300 p-2">Bed Number</th>
                                    <th class="border border-gray-300 p-2">Admitted At</th>
                                    <th class="border border-gray-300 p-2">Department</th>
                                    <th class="border border-gray-300 p-2">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admitted_patients as $index => $patient): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['department_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['recorded_by'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="critical-patients">Critical Patients Admitted (<?php echo $critical_count; ?>) <i class="fas fa-chevron-down"></i></h2>
                <div id="critical-patients" class="toggle-content">
                    <?php if (empty($critical_patients)): ?>
                        <p class="text-gray-600">No critical patients admitted in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Patient</th>
                                    <th class="border border-gray-300 p-2">Bed Number</th>
                                    <th class="border border-gray-300 p-2">Admitted At</th>
                                    <th class="border border-gray-300 p-2">Department</th>
                                    <th class="border border-gray-300 p-2">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($critical_patients as $index => $patient): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['department_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['recorded_by'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="deaths">Deaths Recorded (<?php echo $death_count; ?>) <i class="fas fa-chevron-down"></i></h2>
                <div id="deaths" class="toggle-content">
                    <?php if (empty($deaths)): ?>
                        <p class="text-gray-600">No deaths recorded in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Patient</th>
                                    <th class="border border-gray-300 p-2">Date of Death</th>
                                    <th class="border border-gray-300 p-2">Cause of Death</th>
                                    <th class="border border-gray-300 p-2">Department</th>
                                    <th class="border border-gray-300 p-2">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deaths as $index => $death): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['first_name'] . ' ' . $death['last_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['date_of_death']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['cause_of_death']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['department_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['recorded_by']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="test-results">Test Results <i class="fas fa-chevron-down"></i></h2>
                <div id="test-results" class="toggle-content">
                    <?php if (empty($test_results)): ?>
                        <p class="text-gray-600">No test results recorded in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Patient</th>
                                    <th class="border border-gray-300 p-2">Test Type</th>
                                    <th class="border border-gray-300 p-2">Status</th>
                                    <th class="border border-gray-300 p-2">Result</th>
                                    <th class="border border-gray-300 p-2">Requested At</th>
                                    <th class="border border-gray-300 p-2">Department</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($test_results as $index => $result): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['test_type']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['request_status']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['result_value'] ?? 'N/A'); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['requested_at']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['department_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="medications">Medications <i class="fas fa-chevron-down"></i></h2>
                <div id="medications" class="toggle-content">
                    <?php if (empty($medications)): ?>
                        <p class="text-gray-600">No medications recorded in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Patient</th>
                                    <th class="border border-gray-300 p-2">Medication</th>
                                    <th class="border border-gray-300 p-2">Dosage</th>
                                    <th class="border border-gray-300 p-2">Frequency</th>
                                    <th class="border border-gray-300 p-2">Start Date</th>
                                    <th class="border border-gray-300 p-2">Department</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medications as $index => $med): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($med['first_name'] . ' ' . $med['last_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($med['dosage']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($med['frequency'] ?? 'Not specified'); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($med['start_date']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($med['department_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="export-logs">Export Logs (<?php echo $export_count; ?>) <i class="fas fa-chevron-down"></i></h2>
                <div id="export-logs" class="toggle-content">
                    <?php if (empty($export_logs)): ?>
                        <p class="text-gray-600">No exports recorded in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Log ID</th>
                                    <th class="border border-gray-300 p-2">User ID</th>
                                    <th class="border border-gray-300 p-2">Role</th>
                                    <th class="border border-gray-300 p-2">Department</th>
                                    <th class="border border-gray-300 p-2">Export Type</th>
                                    <th class="border border-gray-300 p-2">Exported At</th>
                                    <th class="border border-gray-300 p-2">Exported By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($export_logs as $index => $log): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['log_id']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['user_id']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['role']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['department_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['export_type']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['exported_at']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($log['exported_by'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="admitted-patients">Admitted Patients (<?php echo $admitted_count; ?>) <i class="fas fa-chevron-down"></i></h2>
                <div id="admitted-patients" class="toggle-content active">
                    <?php if (empty($admitted_patients)): ?>
                        <p class="text-gray-600">No patients admitted in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Patient</th>
                                    <th class="border border-gray-300 p-2">Bed Number</th>
                                    <th class="border border-gray-300 p-2">Admitted At</th>
                                    <th class="border border-gray-300 p-2">Critical</th>
                                    <th class="border border-gray-300 p-2">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admitted_patients as $index => $patient): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo $patient['is_critical'] ? '<span class="text-red-600">Yes</span>' : 'No'; ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['recorded_by'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="critical-patients">Critical Patients Admitted (<?php echo $critical_count; ?>) <i class="fas fa-chevron-down"></i></h2>
                <div id="critical-patients" class="toggle-content">
                    <?php if (empty($critical_patients)): ?>
                        <p class="text-gray-600">No critical patients admitted in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Patient</th>
                                    <th class="border border-gray-300 p-2">Bed Number</th>
                                    <th class="border border-gray-300 p-2">Admitted At</th>
                                    <th class="border border-gray-300 p-2">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($critical_patients as $index => $patient): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['recorded_by'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card p-6 table-section">
                <h2 class="text-xl font-semibold mb-4 toggle-btn" data-target="deaths">Deaths Recorded (<?php echo $death_count; ?>) <i class="fas fa-chevron-down"></i></h2>
                <div id="deaths" class="toggle-content">
                    <?php if (empty($deaths)): ?>
                        <p class="text-gray-600">No deaths recorded in this period.</p>
                    <?php else: ?>
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="table-header">
                                    <th class="border border-gray-300 p-2">Patient</th>
                                    <th class="border border-gray-300 p-2">Date of Death</th>
                                    <th class="border border-gray-300 p-2">Cause of Death</th>
                                    <th class="border border-gray-300 p-2">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deaths as $index => $death): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'zebra' : ''; ?>">
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['first_name'] . ' ' . $death['last_name']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['date_of_death']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['cause_of_death']); ?></td>
                                        <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['recorded_by']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <script>
        document.querySelectorAll('.toggle-btn').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-target');
                const content = document.getElementById(targetId);
                content.classList.toggle('active');
                const icon = button.querySelector('i');
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-up');
            });
        });
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn = null;
}
?>