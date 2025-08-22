<?php
// Security: Enable strict error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security: Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Change to 1 for HTTPS
session_start();

// Validate session and role
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), ['lab', 'radiology', 'admin'])) {
    error_log("Unauthorized access attempt at " . date('Y-m-d H:i:s') . ", role=" . ($_SESSION['role'] ?? 'none'));
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}

// Load DB
include '../includes/db_connect.php';

// Init
$user_id = (int)$_SESSION['user_id'];
$user_role = strtolower($_SESSION['role']);
$department_id = (int)($_SESSION['department_id'] ?? 0);
$username = $_SESSION['username'] ?? 'User';
$department_name = $_SESSION['department_name'] ?? 'Unknown Department';

$name_parts = explode(' ', $username);
$initials = strtoupper(substr($name_parts[0], 0, 1) . ($name_parts[1][0] ?? ''));

// Test types
$test_types = ($user_role === 'lab') ? ['blood test', 'urine test'] : ['x-ray', 'ultrasound', 'ct scan', 'mri'];

// Fetch pending tests
$pending_tests = [];
try {
    $placeholders = implode(',', array_fill(0, count($test_types), '?'));
    $stmt = $conn->prepare("
        SELECT tr.result_id, tr.test_type, tr.request_status, tr.requested_at,
               p.first_name, p.last_name, p.bed_number,
               IFNULL(d.name, 'Unknown') AS department_name
        FROM test_results tr
        JOIN patients p ON tr.patient_id = p.patient_id
        LEFT JOIN departments d ON p.department_id = d.department_id
        WHERE tr.request_status = 'requested'
        AND LOWER(tr.test_type) IN ($placeholders)
    ");
    $stmt->execute(array_map('strtolower', $test_types));
    $pending_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching pending tests: " . $e->getMessage();
    error_log("DB error (pending): " . $e->getMessage());
}

// Handle result update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_result'])) {
    $result_id = filter_input(INPUT_POST, 'result_id', FILTER_VALIDATE_INT);
    $result_value = htmlspecialchars(trim($_POST['result_value'] ?? ''), ENT_QUOTES, 'UTF-8');
    $recorded_by = $user_id;
    $recorded_at = date('Y-m-d H:i:s');
    $image_path = null;

    if ($user_role === 'radiology' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024;
        $file_type = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($file_type, $allowed_types) || $_FILES['image']['size'] > $max_size) {
            $error = "Invalid image file (JPEG/PNG/GIF, max 5MB).";
        } else {
            $upload_dir = __DIR__ . '/../uploads/lab_images/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $image_name = time() . '_' . basename($_FILES['image']['name']);
            $image_path = $upload_dir . $image_name;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                $error = "Image upload failed.";
            }
        }
    }

    if (!$error && $result_id && $result_value !== '') {
        try {
            $stmt = $conn->prepare("
                UPDATE test_results
                SET result_value = ?, image_path = ?, request_status = 'completed', recorded_by = ?, recorded_at = ?
                WHERE result_id = ? AND request_status = 'requested'
            ");
            $stmt->execute([$result_value, $image_path, $recorded_by, $recorded_at, $result_id]);
            $success = ($stmt->rowCount() > 0) ? "Test result updated." : "Nothing updated.";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } elseif (!$error) {
        $error = "Missing result or invalid input.";
    }
}

// Handle export logging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_pdf'])) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO export_logs (user_id, role, department_id, export_type, exported_at)
            VALUES (?, ?, ?, 'pending_tests', NOW())
        ");
        $stmt->execute([$user_id, $user_role, $department_id]);
        $success = "Export logged.";
    } catch (PDOException $e) {
        $error = "Export logging failed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo ucfirst($user_role); ?> Dashboard - Pending Tests</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.6/jspdf.plugin.autotable.min.js"></script>
</head>

<body class="bg-gray-100">
<div class="flex h-screen overflow-hidden">

  <!-- Sidebar -->
  <div class="hidden md:flex md:flex-shrink-0">
    <div class="flex flex-col w-64 h-full border-r border-gray-200 bg-white">
      <div class="flex items-center h-16 px-4 border-b border-gray-200">
        <h1 class="text-xl font-bold text-gray-800">MMH EHR</h1>
      </div>
      <div class="flex flex-col flex-grow overflow-y-auto">
        <div class="px-4 py-4">
          <div class="flex items-center pb-4 border-b border-gray-200">
            <div class="w-10 h-10 rounded-full border-2 bg-blue-600 text-white flex items-center justify-center font-semibold">
              <?php echo $initials; ?>
            </div>
            <div class="ml-3 text-sm text-gray-900 leading-tight">
              <div class="font-medium"><?php echo htmlspecialchars($username); ?></div>
              <div class="text-xs text-gray-600"><?php echo htmlspecialchars($department_name); ?></div>
            </div>
          </div>
        </div>
        <nav class="flex-1 px-2 space-y-1 text-sm">
          <a href="lab_radio_dashboard.php" class="flex items-center px-3 py-2 text-gray-600 rounded-md <?php echo basename($_SERVER['PHP_SELF']) === 'lab_radio_dashboard.php' ? 'bg-blue-100 text-blue-800' : 'hover:bg-gray-100'; ?>"><i class="fas fa-hourglass-start mr-3"></i>Pending Tests</a>
          <a href="completed_tests.php" class="flex items-center px-3 py-2 text-gray-600 rounded-md <?php echo basename($_SERVER['PHP_SELF']) === 'completed_tests.php' ? 'bg-blue-100 text-blue-800' : 'hover:bg-gray-100'; ?>"><i class="fas fa-check-circle mr-3"></i>Completed Tests</a>
        </nav>
        <div class="mt-auto px-4 py-4 border-t border-gray-200">
          <a href="../index.php?action=logout" class="flex items-center px-3 py-2 text-gray-600 rounded-md hover:bg-gray-100"><i class="fas fa-sign-out-alt mr-3"></i>Logout</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <div class="flex flex-col flex-1 overflow-hidden">
    <div class="flex items-center justify-between h-16 px-4 bg-white border-b border-gray-200">
      <h1 class="text-2xl font-semibold text-gray-800">Welcome, <?php echo ucfirst($user_role); ?> - Pending Tests</h1>
    </div>

    <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-gray-50">
      <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded"><?php echo htmlspecialchars($error); ?></div>
      <?php elseif (!empty($success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <!-- Pending Test Results Section -->
      <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Pending Test Results</h2>
          <form method="POST" class="mb-0">
            <button type="submit" name="export_pdf" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Export to PDF</button>
          </form>
        </div>
        <?php if (empty($pending_tests)): ?>
          <p class="text-gray-600">No pending test results found.</p>
        <?php else: ?>
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-gray-200">
                <th class="border border-gray-300 p-2">Patient</th>
                <th class="border border-gray-300 p-2">Test Type</th>
                <th class="border border-gray-300 p-2">Bed Number</th>
                <th class="border border-gray-300 p-2">Department</th>
                <th class="border border-gray-300 p-2">Requested At</th>
                <th class="border border-gray-300 p-2">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending_tests as $test): ?>
                <tr>
                  <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['first_name'] . ' ' . $test['last_name']); ?></td>
                  <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['test_type']); ?></td>
                  <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['bed_number'] ?? 'N/A'); ?></td>
                  <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['department_name']); ?></td>
                  <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($test['requested_at']); ?></td>
                  <td class="border border-gray-300 p-2">
                    <form method="POST" enctype="multipart/form-data">
                      <input type="hidden" name="result_id" value="<?php echo $test['result_id']; ?>">
                      <div class="mb-2">
                        <label for="result_value_<?php echo $test['result_id']; ?>" class="block text-gray-700">Result</label>
                        <textarea name="result_value" id="result_value_<?php echo $test['result_id']; ?>" class="w-full p-2 border border-gray-300 rounded" required></textarea>
                      </div>
                      <?php if ($user_role === 'radiology'): ?>
                        <div class="mb-2">
                          <label for="image_<?php echo $test['result_id']; ?>" class="block text-gray-700">Upload Image</label>
                          <input type="file" name="image" id="image_<?php echo $test['result_id']; ?>" class="w-full p-2 border border-gray-300 rounded" accept="image/*">
                        </div>
                      <?php endif; ?>
                      <button type="submit" name="update_result" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Update</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<script>
  function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.autoTable({
      html: '#pending-tests-table',
      theme: 'grid',
      headStyles: { fillColor: [229, 231, 235] },
      margin: { top: 10 }
    });

    doc.save('pending_tests_<?php echo $user_role; ?>_<?php echo date('Ymd_His'); ?>.pdf');
  }
</script>
</body>
</html>