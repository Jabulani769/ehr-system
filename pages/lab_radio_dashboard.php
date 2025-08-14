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
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), ['lab', 'radiology', 'admin'])) {
    error_log("Unauthorized access attempt at " . date('Y-m-d H:i:s') . ", role=" . ($_SESSION['role'] ?? 'none'));
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}

// Include database connection
include '../includes/db_connect.php';

// Initialize variables
$user_id = $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role']);
$department_id = $_SESSION['department_id'] ?? 0;
$error = '';
$success = '';

// Fetch pending test results
try {
    $stmt = $conn->prepare("
        SELECT tr.test_id, tr.test_type, tr.request_status, tr.result_value, tr.result_image, p.first_name, p.last_name
        FROM test_results tr
        JOIN patients p ON tr.patient_id = p.patient_id
        WHERE tr.request_status = 'requested' AND tr.department_id = ?
    ");
    $stmt->execute([$department_id]);
    $pending_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching pending tests: " . $e->getMessage();
    error_log("Database error in lab_radio_dashboard.php: " . $e->getMessage());
}

// Handle test result upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_result'])) {
    $test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
    $result_value = htmlspecialchars(trim($_POST['result_value'] ?? ''), ENT_QUOTES, 'UTF-8');
    $result_image = $_FILES['result_image'] ?? null;

    if ($test_id && $result_value) {
        try {
            $upload_dir = '../Uploads/lab_images/';
            $image_path = null;
            if ($result_image && $result_image['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($result_image['type'], $allowed_types)) {
                    $error = "Invalid image type. Only JPEG, PNG, and GIF are allowed.";
                } else {
                    $image_name = uniqid() . '_' . basename($result_image['name']);
                    $image_path = $upload_dir . $image_name;
                    if (!move_uploaded_file($result_image['tmp_name'], $image_path)) {
                        $error = "Failed to upload image.";
                    }
                }
            }
            if (!$error) {
                $stmt = $conn->prepare("
                    UPDATE test_results 
                    SET result_value = ?, result_image = ?, request_status = 'completed', completed_at = NOW(), completed_by = ?
                    WHERE test_id = ? AND department_id = ?
                ");
                $stmt->execute([$result_value, $image_path, $user_id, $test_id, $department_id]);
                $success = "Test result uploaded successfully.";
            }
        } catch (PDOException $e) {
            $error = "Error uploading result: " . $e->getMessage();
            error_log("Database error in lab_radio_dashboard.php: " . $e->getMessage());
        }
    } else {
        $error = "Test ID and result value are required.";
    }
}

// Handle PDF export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_pdf'])) {
    try {
        // Log export action
        $stmt = $conn->prepare("
            INSERT INTO export_logs (user_id, role, department_id, export_type, exported_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $user_role, $department_id, 'pending_tests']);
        $success = "Export logged successfully.";
    } catch (PDOException $e) {
        $error = "Error logging export: " . $e->getMessage();
        error_log("Database error in lab_radio_dashboard.php: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($user_role); ?> Dashboard - MMH EHR</title>
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
        <h1 class="text-2xl font-bold text-gray-800">MMH EHR - <?php echo ucfirst($user_role); ?> Dashboard</h1>
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

        <div class="card p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Pending Test Results</h2>
            <form method="POST" class="mb-4">
                <button type="submit" name="export_pdf" class="btn-export text-white px-4 py-2 rounded">Export to PDF</button>
            </form>
            <?php if (empty($pending_tests)): ?>
                <p class="text-gray-600">No pending test results found.</p>
            <?php else: ?>
                <table id="pending-tests-table" class="w-full border-collapse">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Test ID</th>
                            <th class="border border-gray-300 p-2">Patient</th>
                            <th class="border border-gray-300 p-2">Test Type</th>
                            <th class="border border-gray-300 p-2">Status</th>
                            <th class="border border-gray-300 p-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_tests as $test): ?>
                            <tr>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['test_id']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['first_name'] . ' ' . $test['last_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['test_type']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['request_status']); ?></td>
                                <td class="border border-gray-300 p-2">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="test_id" value="<?php echo $test['test_id']; ?>">
                                        <textarea name="result_value" class="w-full p-2 border border-gray-300 rounded mb-2" placeholder="Enter result" required></textarea>
                                        <input type="file" name="result_image" class="mb-2" accept="image/*">
                                        <button type="submit" name="upload_result" class="btn-primary text-white px-2 py-1 rounded">Upload</button>
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
                html: '#pending-tests-table',
                theme: 'grid',
                headStyles: { fillColor: [229, 231, 235] },
                margin: { top: 10 },
                columnStyles: {
                    0: { cellWidth: 20 }, // Test ID
                    1: { cellWidth: 40 }, // Patient
                    2: { cellWidth: 30 }, // Test Type
                    3: { cellWidth: 30 }, // Status
                    4: { cellWidth: 60 }  // Action
                }
            });
            doc.save('pending_tests.pdf');
        }
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn = null;
}
?>