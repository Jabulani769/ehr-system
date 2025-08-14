<?php
// Set session settings before starting session
ini_set('session.cookie_httponly', 1);
session_start();

// Secure authentication check
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), ['nurse', 'doctor', 'lab', 'radiology'])) {
    error_log("Unauthorized access attempt at " . date('Y-m-d H:i:s') . ", role=" . ($_SESSION['role'] ?? 'none'));
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}

// Generate CSRF token only if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include __DIR__ . '/../config.php';
include __DIR__ . '/../includes/db_connect.php';

// Check database connection
if (!isset($conn)) {
    error_log("Database connection not established at " . date('Y-m-d H:i:s'));
    die("Database connection not established. Please contact the administrator.");
}

$action = $_GET['action'] ?? 'list';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['all', 'patient', 'test_type', 'status']) ? $_GET['filter'] : 'all';
$patient_id = isset($_GET['patient_id']) && $filter === 'patient' ? (int)$_GET['patient_id'] : null;
$test_type = isset($_GET['test_type']) && $filter === 'test_type' ? trim($_GET['test_type']) : null;
$status = isset($_GET['status']) && $filter === 'status' ? trim($_GET['status']) : null;

// Pagination settings
$results_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;

// Validate pagination parameters
if ($results_per_page <= 0 || $offset < 0) {
    header("Location: test_results.php?error=" . urlencode("Invalid pagination parameters"));
    exit();
}

// Count total results for pagination
$count_query = "SELECT COUNT(*) as total FROM test_results tr JOIN patients p ON tr.patient_id = p.patient_id";
$count_params = [];
if ($filter === 'patient' && $patient_id) {
    $count_query .= " WHERE tr.patient_id = ?";
    $count_params[] = $patient_id;
} elseif ($filter === 'test_type' && $test_type) {
    $count_query .= " WHERE tr.test_type = ?";
    $count_params[] = $test_type;
} elseif ($filter === 'status' && $status) {
    $count_query .= " WHERE tr.request_status = ?";
    $count_params[] = $status;
}
$stmt = $conn->prepare($count_query);
$stmt->execute($count_params);
$total_results = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $results_per_page);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: test_results.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    try {
        if ($action === 'request' && in_array(strtolower($_SESSION['role']), ['nurse', 'doctor'])) {
            // Handle test request
            $patient_id = (int)$_POST['patient_id'];
            $department = trim($_POST['department'] ?? '');
            $test_type = trim($_POST['test_type'] ?? '');

            if (empty($patient_id) || empty($department) || empty($test_type)) {
                header("Location: test_results.php?action=request&error=" . urlencode("All fields are required"));
                exit();
            }

            // Validate test_type based on department
            $valid_test_types = [
                'laboratory' => ['blood test', 'urine test'],
                'radiology' => ['x-ray', 'ultrasound', 'ct scan', 'mri']
            ];
            if (!in_array($test_type, $valid_test_types[strtolower($department)])) {
                header("Location: test_results.php?action=request&error=" . urlencode("Invalid test type for selected department"));
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO test_results (patient_id, test_type, request_status, requested_by, requested_at) VALUES (?, ?, 'requested', ?, NOW())");
            $stmt->execute([$patient_id, $test_type, $_SESSION['user_id']]);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: test_results.php?success=" . urlencode("Test requested successfully"));
            exit();
        } elseif ($action === 'upload' && in_array(strtolower($_SESSION['role']), ['lab', 'radiology'])) {
            // Handle result upload
            $result_id = (int)$_POST['result_id'];
            $result_value = htmlspecialchars(trim($_POST['result_value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $image_path = null;

            if (empty($result_id) || empty($result_value)) {
                header("Location: test_results.php?action=upload&result_id=$result_id&error=" . urlencode("Result value is required"));
                exit();
            }

            // Handle image upload
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 2 * 1024 * 1024; // 2MB
                if (!in_array($_FILES['image']['type'], $allowed_types) || $_FILES['image']['size'] > $max_size) {
                    header("Location: test_results.php?action=upload&result_id=$result_id&error=" . urlencode("Invalid image (JPEG/PNG, max 2MB)"));
                    exit();
                }

                $upload_dir = UPLOAD_DIR . 'lab_images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $image_name = time() . '_' . basename($_FILES['image']['name']);
                $image_path = $upload_dir . $image_name;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                    header("Location: test_results.php?action=upload&result_id=$result_id&error=" . urlencode("Failed to upload image"));
                    exit();
                }
            }

            $stmt = $conn->prepare("UPDATE test_results SET result_value = ?, image_path = ?, recorded_by = ?, recorded_at = NOW(), request_status = 'completed' WHERE result_id = ?");
            $stmt->execute([$result_value, $image_path, $_SESSION['user_id'], $result_id]);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: test_results.php?success=" . urlencode("Result uploaded successfully"));
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error in test_results.php: " . $e->getMessage());
        header("Location: test_results.php?action=$action&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}

// Fetch patients for dropdown
try {
    $stmt = $conn->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE discharged_at IS NULL");
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching patients: " . $e->getMessage();
    error_log("Database error in test_results.php: " . $e->getMessage());
}

// Fetch pending requests for lab/radiology staff
if (in_array(strtolower($_SESSION['role']), ['lab', 'radiology'])) {
    $test_types = ($_SESSION['role'] === 'lab') ? "'blood test', 'urine test'" : "'x-ray', 'ultrasound', 'ct scan', 'mri'";
    try {
        $stmt = $conn->prepare("SELECT tr.result_id, tr.patient_id, tr.test_type, p.first_name, p.last_name FROM test_results tr JOIN patients p ON tr.patient_id = p.patient_id WHERE tr.request_status = 'requested' AND LOWER(tr.test_type) IN ($test_types)");
        $stmt->execute();
        $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($pending_requests)) {
            error_log("No pending requests found for role=" . $_SESSION['role'] . ", test_types=$test_types at " . date('Y-m-d H:i:s'));
        }
    } catch (PDOException $e) {
        $error = "Error fetching pending requests: " . $e->getMessage();
        error_log("Database error in test_results.php: " . $e->getMessage());
    }
}

// Fetch test results with pagination
$query = "SELECT tr.result_id, tr.patient_id, tr.test_type, tr.request_status, tr.result_value, tr.image_path, tr.requested_at, tr.recorded_at, p.first_name, p.last_name 
          FROM test_results tr 
          JOIN patients p ON tr.patient_id = p.patient_id";
$params = [];
if ($filter === 'patient' && $patient_id) {
    $query .= " WHERE tr.patient_id = ?";
    $params[] = $patient_id;
} elseif ($filter === 'test_type' && $test_type) {
    $query .= " WHERE tr.test_type = ?";
    $params[] = $test_type;
} elseif ($filter === 'status' && $status) {
    $query .= " WHERE tr.request_status = ?";
    $params[] = $status;
}
$query .= " ORDER BY tr.requested_at DESC LIMIT $results_per_page OFFSET $offset";
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $test_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching test results: " . $e->getMessage();
    error_log("Database error in test_results.php: " . $e->getMessage());
}

// Fetch distinct test types for filtering
try {
    $stmt = $conn->prepare("SELECT DISTINCT test_type FROM test_results");
    $stmt->execute();
    $test_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "Error fetching test types: " . $e->getMessage();
    error_log("Database error in test_results.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab & Radiology Results - MMH EHR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card { background-color: #ffffff; border: 2px solid #e5e7eb; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .table-header { background-color: #e5e7eb; }
        .btn-primary { background-color: #3b82f6; border: 2px solid #3b82f6; transition: background-color 0.3s; }
        .btn-primary:hover { background-color: #2563eb; }
        @media (max-width: 640px) { table { display: block; overflow-x: auto; white-space: nowrap; } }
    </style>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <section class="container mx-auto p-6">
        <h1 class="text-3xl font-semibold text-center mb-4">Lab & Radiology Results</h1>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'request' && in_array(strtolower($_SESSION['role']), ['nurse', 'doctor'])): ?>
            <!-- Request test form -->
            <div class="card p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Request Test</h2>
                <form action="test_results.php?action=request" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-4">
                        <label for="patient_id" class="block text-gray-700">Patient</label>
                        <select id="patient_id" name="patient_id" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="department" class="block text-gray-700">Department</label>
                        <select id="department" name="department" class="w-full p-2 border border-gray-300 rounded" required onchange="updateTestTypes()">
                            <option value="">Select Department</option>
                            <option value="laboratory">Laboratory</option>
                            <option value="radiology">Radiology</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="test_type" class="block text-gray-700">Test Type</label>
                        <select id="test_type" name="test_type" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value="">Select Test Type</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary text-white px-4 py-2 rounded w-full">Request Test</button>
                </form>
                <div class="mt-4 text-center">
                    <a href="test_results.php" class="text-blue-600 hover:underline">Back to Results</a>
                </div>
            </div>
        <?php elseif ($action === 'upload' && in_array(strtolower($_SESSION['role']), ['lab', 'radiology']) && isset($_GET['result_id'])): ?>
            <!-- Upload result form -->
            <?php
            $result_id = (int)$_GET['result_id'];
            $stmt = $conn->prepare("SELECT tr.test_type, p.first_name, p.last_name FROM test_results tr JOIN patients p ON tr.patient_id = p.patient_id WHERE tr.result_id = ?");
            $stmt->execute([$result_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($request):
            ?>
            <div class="card p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Upload Result: <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name'] . ' - ' . $request['test_type']); ?></h2>
                <form action="test_results.php?action=upload" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="result_id" value="<?php echo $result_id; ?>">
                    <div class="mb-4">
                        <label for="result_value" class="block text-gray-700">Result</label>
                        <textarea id="result_value" name="result_value" class="w-full p-2 border border-gray-300 rounded" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="image" class="block text-gray-700">Upload Image (JPEG/PNG, max 2MB)</label>
                        <input type="file" id="image" name="image" class="w-full p-2 border border-gray-300 rounded" accept="image/jpeg,image/png">
                    </div>
                    <button type="submit" class="btn-primary text-white px-4 py-2 rounded w-full">Upload Result</button>
                </form>
                <div class="mt-4 text-center">
                    <a href="test_results.php" class="text-blue-600 hover:underline">Back to Results</a>
                </div>
            </div>
            <?php else: ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    Request not found
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Test results list with filtering and pagination -->
            <div class="card p-6 mb-6">
                <div class="flex justify-between mb-4">
                    <?php if (in_array(strtolower($_SESSION['role']), ['nurse', 'doctor'])): ?>
                        <a href="test_results.php?action=request" class="text-blue-600 hover:underline">Request New Test</a>
                    <?php endif; ?>
                    <div class="flex space-x-2">
                        <label for="filter" class="text-gray-700">Filter By:</label>
                        <select id="filter" class="p-2 border border-gray-300 rounded" onchange="updateFilter(this)">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Results</option>
                            <option value="patient" <?php echo $filter === 'patient' ? 'selected' : ''; ?>>Patient</option>
                            <option value="test_type" <?php echo $filter === 'test_type' ? 'selected' : ''; ?>>Test Type</option>
                            <option value="status" <?php echo $filter === 'status' ? 'selected' : ''; ?>>Status</option>
                        </select>
                        <?php if ($filter === 'patient'): ?>
                            <select id="patient_id" class="p-2 border border-gray-300 rounded" onchange="window.location.href='test_results.php?filter=patient&patient_id='+this.value">
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['patient_id']; ?>" <?php echo $patient_id == $patient['patient_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($filter === 'test_type'): ?>
                            <select id="test_type" class="p-2 border border-gray-300 rounded" onchange="window.location.href='test_results.php?filter=test_type&test_type='+encodeURIComponent(this.value)">
                                <option value="">Select Test Type</option>
                                <?php foreach ($test_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $test_type === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($filter === 'status'): ?>
                            <select id="status" class="p-2 border border-gray-300 rounded" onchange="window.location.href='test_results.php?filter=status&status='+this.value">
                                <option value="">Select Status</option>
                                <option value="requested" <?php echo $status === 'requested' ? 'selected' : ''; ?>>Requested</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (in_array(strtolower($_SESSION['role']), ['lab', 'radiology']) && !empty($pending_requests)): ?>
                    <h2 class="text-xl font-semibold mb-4">Pending Test Requests</h2>
                    <table class="w-full border-collapse border border-gray-300 mb-6">
                        <thead>
                            <tr class="table-header">
                                <th class="border border-gray-300 p-2">Patient</th>
                                <th class="border border-gray-300 p-2">Test Type</th>
                                <th class="border border-gray-300 p-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): ?>
                                <tr>
                                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($request['test_type']); ?></td>
                                    <td class="border border-gray-300 p-2">
                                        <a href="test_results.php?action=upload&result_id=<?php echo $request['result_id']; ?>" class="text-blue-600 hover:underline">Upload Result</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <h2 class="text-xl font-semibold mb-4">Test Results</h2>
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="table-header">
                            <th class="border border-gray-300 p-2">Patient</th>
                            <th class="border border-gray-300 p-2">Test Type</th>
                            <th class="border border-gray-300 p-2">Status</th>
                            <th class="border border-gray-300 p-2">Result</th>
                            <th class="border border-gray-300 p-2">Image</th>
                            <th class="border border-gray-300 p-2">Requested At</th>
                            <th class="border border-gray-300 p-2">Recorded At</th>
                            <th class="border border-gray-300 p-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($test_results as $result): ?>
                            <tr>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['test_type']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['request_status']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['result_value'] ?: 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2">
                                    <?php if ($result['image_path']): ?>
                                        <a href="<?php echo htmlspecialchars($result['image_path']); ?>" target="_blank">
                                            <img src="<?php echo htmlspecialchars($result['image_path']); ?>" alt="Test Image" class="max-w-[100px]">
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['requested_at']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['recorded_at'] ?: 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2">
                                    <a href="vitals.php?action=history&patient_id=<?php echo $result['patient_id']; ?>" class="text-blue-600 hover:underline">Vitals</a> |
                                    <a href="patient_management.php?action=edit&patient_id=<?php echo $result['patient_id']; ?>" class="text-blue-600 hover:underline">Manage</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Pagination controls -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-4 flex justify-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="test_results.php?filter=<?php echo $filter; ?><?php echo $filter === 'patient' ? '&patient_id=' . $patient_id : ($filter === 'test_type' ? '&test_type=' . urlencode($test_type) : ($filter === 'status' ? '&status=' . $status : '')); ?>&page=<?php echo $page - 1; ?>" class="text-blue-600 hover:underline">Previous</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="test_results.php?filter=<?php echo $filter; ?><?php echo $filter === 'patient' ? '&patient_id=' . $patient_id : ($filter === 'test_type' ? '&test_type=' . urlencode($test_type) : ($filter === 'status' ? '&status=' . $status : '')); ?>&page=<?php echo $i; ?>" class="text-blue-600 hover:underline <?php echo $i === $page ? 'font-bold' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="test_results.php?filter=<?php echo $filter; ?><?php echo $filter === 'patient' ? '&patient_id=' . $patient_id : ($filter === 'test_type' ? '&test_type=' . urlencode($test_type) : ($filter === 'status' ? '&status=' . $status : '')); ?>&page=<?php echo $page + 1; ?>" class="text-blue-600 hover:underline">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="mt-4 text-center">
                    <a href="nurse_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <script>
        function updateFilter(select) {
            if (select.value === 'patient' || select.value === 'test_type' || select.value === 'status') {
                window.location.href = 'test_results.php?filter=' + select.value;
            } else {
                window.location.href = 'test_results.php?filter=all';
            }
        }

        function updateTestTypes() {
            const department = document.getElementById('department').value.toLowerCase();
            const testTypeSelect = document.getElementById('test_type');
            testTypeSelect.innerHTML = '<option value="">Select Test Type</option>';
            const testTypes = {
                'laboratory': ['blood test', 'urine test'],
                'radiology': ['x-ray', 'ultrasound', 'CT scan', 'MRI']
            };
            if (department && testTypes[department]) {
                testTypes[department].forEach(type => {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type.charAt(0).toUpperCase() + type.slice(1);
                    testTypeSelect.appendChild(option);
                });
            }
        }
    </script>
</body>
</html>