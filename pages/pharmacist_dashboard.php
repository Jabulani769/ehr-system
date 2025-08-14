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
error_log("Session data on pharmacist_dashboard.php access: " . json_encode($_SESSION) . " at " . date('Y-m-d H:i:s'));

// Security: Validate session and role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'pharmacist') {
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

// Fetch medication requests
try {
    $stmt = $conn->prepare("
        SELECT mr.request_id, mr.medication_name, mr.dosage, mr.request_status, mr.requested_at,
               p.first_name, p.last_name, p.bed_number
        FROM medication_requests mr
        JOIN patients p ON mr.patient_id = p.patient_id
        WHERE mr.request_status = 'pending'
    ");
    $stmt->execute();
    $med_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching medication requests: " . $e->getMessage();
    error_log("Database error in pharmacist_dashboard.php: " . $e->getMessage());
}

// Handle medication request fulfillment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fulfill_request'])) {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    if ($request_id) {
        try {
            $stmt = $conn->prepare("
                UPDATE medication_requests
                SET request_status = 'fulfilled', fulfilled_by = ?, fulfilled_at = NOW()
                WHERE request_id = ? AND request_status = 'pending'
            ");
            $stmt->execute([$user_id, $request_id]);
            if ($stmt->rowCount() > 0) {
                $success = "Medication request fulfilled successfully.";
            } else {
                $error = "No pending request found or already fulfilled.";
            }
        } catch (PDOException $e) {
            $error = "Error fulfilling request: " . $e->getMessage();
            error_log("Database error in pharmacist_dashboard.php: " . $e->getMessage());
        }
    } else {
        $error = "Invalid request ID.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist Dashboard - MMH EHR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.6/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .navbar { background-color: #f1f5f9; }
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
    <nav class="navbar p-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">MMH EHR - Pharmacist Dashboard</h1>
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

        <!-- Medication Requests -->
        <div class="card p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Pending Medication Requests</h2>
            <button onclick="exportToPDF()" class="btn-export text-white px-4 py-2 rounded mb-4">Export to PDF</button>
            <?php if (empty($med_requests)): ?>
                <p class="text-gray-600">No pending medication requests found.</p>
            <?php else: ?>
                <table id="med-requests-table" class="w-full border-collapse mb-4">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Patient</th>
                            <th class="border border-gray-300 p-2">Medication</th>
                            <th class="border border-gray-300 p-2">Dosage</th>
                            <th class="border border-gray-300 p-2">Bed Number</th>
                            <th class="border border-gray-300 p-2">Requested At</th>
                            <th class="border border-gray-300 p-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($med_requests as $request): ?>
                            <tr>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($request['medication_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($request['dosage'] ?? 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($request['bed_number'] ?? 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($request['requested_at']); ?></td>
                                <td class="border border-gray-300 p-2">
                                    <form method="POST">
                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                        <button type="submit" name="fulfill_request" class="btn-primary text-white px-2 py-1 rounded">Fulfill</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
    <script>
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.autoTable({
                html: '#med-requests-table',
                theme: 'grid',
                headStyles: { fillColor: [229, 231, 235] },
                margin: { top: 10 },
                columnStyles: {
                    0: { cellWidth: 30 }, // Patient
                    1: { cellWidth: 30 }, // Medication
                    2: { cellWidth: 20 }, // Dosage
                    3: { cellWidth: 20 }, // Bed Number
                    4: { cellWidth: 30 }, // Requested At
                    5: { cellWidth: 20 }  // Action
                }
            });
            doc.save('medication_requests.pdf');
        }
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn = null;
}
?>