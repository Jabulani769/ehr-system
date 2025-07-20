<?php
// Set session settings before starting session
ini_set('session.cookie_httponly', 1);
session_start();
// Secure authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['nurse', 'doctor', 'lab'])) {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}
// Generate CSRF token only if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include '../includes/db_connect.php';

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
$results_per_page = (int)$results_per_page;
$offset = (int)$offset;
if ($results_per_page <= 0 || $offset < 0) {
    header("Location: lab_results.php?error=" . urlencode("Invalid pagination parameters"));
    exit();
}

// Count total results for pagination
$count_query = "SELECT COUNT(*) as total FROM lab_results lr JOIN patients p ON lr.patient_id = p.patient_id";
$count_params = [];
if ($filter === 'patient' && $patient_id) {
    $count_query .= " WHERE lr.patient_id = ?";
    $count_params[] = $patient_id;
} elseif ($filter === 'test_type' && $test_type) {
    $count_query .= " WHERE lr.test_type = ?";
    $count_params[] = $test_type;
} elseif ($filter === 'status' && $status) {
    $count_query .= " WHERE lr.request_status = ?";
    $count_params[] = $status;
}
$stmt = $conn->prepare($count_query);
$stmt->execute($count_params);
$total_results = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $results_per_page);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: lab_results.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    try {
        if ($action === 'request' && in_array($_SESSION['role'], ['nurse', 'doctor'])) {
            // Handle test request
            $patient_id = (int)$_POST['patient_id'];
            $test_type = trim($_POST['test_type'] ?? '');

            if (empty($patient_id) || empty($test_type)) {
                header("Location: lab_results.php?action=request&error=" . urlencode("All fields are required"));
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO lab_results (patient_id, test_type, request_status, requested_by, requested_at) VALUES (?, ?, 'requested', ?, NOW())");
            $stmt->execute([$patient_id, $test_type, $_SESSION['user_id']]);
            // Regenerate CSRF token after successful submission
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: lab_results.php?success=" . urlencode("Test requested successfully"));
            exit();
        } elseif ($action === 'upload' && $_SESSION['role'] === 'lab') {
            // Handle result upload
            $result_id = (int)$_POST['result_id'];
            $result_value = trim($_POST['result_value'] ?? '');
            $image_path = null;

            if (empty($result_id) || empty($result_value)) {
                header("Location: lab_results.php?action=upload&result_id=$result_id&error=" . urlencode("Result value is required"));
                exit();
            }

            // Handle image upload
            if (!empty($_FILES['image']['name'])) {
                $allowed_types = ['image/jpeg', 'image/png'];
                $max_size = 2 * 1024 * 1024; // 2MB
                if (!in_array($_FILES['image']['type'], $allowed_types) || $_FILES['image']['size'] > $max_size) {
                    header("Location: lab_results.php?action=upload&result_id=$result_id&error=" . urlencode("Invalid image (JPEG/PNG, max 2MB)"));
                    exit();
                }

                $upload_dir = '../Uploads/lab_images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $image_path = $upload_dir . uniqid() . '_' . basename($_FILES['image']['name']);
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                    header("Location: lab_results.php?action=upload&result_id=$result_id&error=" . urlencode("Failed to upload image"));
                    exit();
                }
            }

            $stmt = $conn->prepare("UPDATE lab_results SET result_value = ?, image_path = ?, recorded_by = ?, recorded_at = NOW(), request_status = 'completed' WHERE result_id = ?");
            $stmt->execute([$result_value, $image_path, $_SESSION['user_id'], $result_id]);
            // Regenerate CSRF token after successful submission
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: lab_results.php?success=" . urlencode("Result uploaded successfully"));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: lab_results.php?action=$action&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}

// Fetch patients for dropdown
$stmt = $conn->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE discharged_at IS NULL");
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending requests for lab staff
if ($_SESSION['role'] === 'lab') {
    $stmt = $conn->prepare("SELECT result_id, patient_id, test_type, p.first_name, p.last_name FROM lab_results lr JOIN patients p ON lr.patient_id = p.patient_id WHERE request_status = 'requested'");
    $stmt->execute();
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch lab results with pagination
$query = "SELECT lr.result_id, lr.patient_id, lr.test_type, lr.request_status, lr.result_value, lr.image_path, lr.requested_at, lr.recorded_at, p.first_name, p.last_name 
          FROM lab_results lr 
          JOIN patients p ON lr.patient_id = p.patient_id";
$params = [];
if ($filter === 'patient' && $patient_id) {
    $query .= " WHERE lr.patient_id = ?";
    $params[] = $patient_id;
} elseif ($filter === 'test_type' && $test_type) {
    $query .= " WHERE lr.test_type = ?";
    $params[] = $test_type;
} elseif ($filter === 'status' && $status) {
    $query .= " WHERE lr.request_status = ?";
    $params[] = $status;
}
$query .= " ORDER BY lr.requested_at DESC LIMIT $results_per_page OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct test types for filtering
$stmt = $conn->prepare("SELECT DISTINCT test_type FROM lab_results");
$stmt->execute();
$test_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Lab Results - MMH EHR</title>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <section class="mt-10 w-full max-w-4xl m-auto p-6">
        <h1 class="text-[28px] font-semibold text-center">Lab Results</h1>
        <p class="text-center">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>

        <?php if ($success): ?>
            <p class="text-green-500 text-center"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="text-red-500 text-center"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($action === 'request' && in_array($_SESSION['role'], ['nurse', 'doctor'])): ?>
            <!-- Request test form -->
            <form action="lab_results.php?action=request" method="POST" class="p-6 bg-white border border-gray-300 rounded-xl shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <h2 class="text-[24px] font-semibold mb-4">Request Lab Test</h2>
                <div class="mb-4">
                    <select name="patient_id" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['patient_id']; ?>"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <input type="text" name="test_type" placeholder="Test Type (e.g., Blood Test, X-Ray)" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 rounded-md">Request Test</button>
            </form>
            <div class="mt-4 text-center">
                <a href="lab_results.php" class="text-blue-600 hover:underline">Back to Lab Results</a>
            </div>
        <?php elseif ($action === 'upload' && $_SESSION['role'] === 'lab' && isset($_GET['result_id'])): ?>
            <!-- Upload result form -->
            <?php
            $result_id = (int)$_GET['result_id'];
            $stmt = $conn->prepare("SELECT lr.test_type, p.first_name, p.last_name FROM lab_results lr JOIN patients p ON lr.patient_id = p.patient_id WHERE lr.result_id = ?");
            $stmt->execute([$result_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($request):
            ?>
            <form action="lab_results.php?action=upload" method="POST" enctype="multipart/form-data" class="p-6 bg-white border border-gray-300 rounded-xl shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="result_id" value="<?php echo $result_id; ?>">
                <h2 class="text-[24px] font-semibold mb-4">Upload Result: <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name'] . ' - ' . $request['test_type']); ?></h2>
                <div class="mb-4">
                    <textarea name="result_value" placeholder="Result Value" class="w-full h-20 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required></textarea>
                </div>
                <div class="mb-4">
                    <label for="image">Upload Image (JPEG/PNG, max 2MB):</label>
                    <input type="file" name="image" accept="image/jpeg,image/png" class="w-full h-10 border-b-2 border-gray-400 outline-none">
                </div>
                <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 rounded-md">Upload Result</button>
            </form>
            <?php else: ?>
                <p class="text-red-500 text-center">Request not found</p>
            <?php endif; ?>
            <div class="mt-4 text-center">
                <a href="lab_results.php" class="text-blue-600 hover:underline">Back to Lab Results</a>
            </div>
        <?php else: ?>
            <!-- Lab results list with filtering and pagination -->
            <div class="mb-4 flex justify-between">
                <?php if (in_array($_SESSION['role'], ['nurse', 'doctor'])): ?>
                    <a href="lab_results.php?action=request" class="text-blue-600 hover:underline">Request New Test</a>
                <?php endif; ?>
                <div>
                    <label for="filter">Filter By:</label>
                    <select id="filter" onchange="updateFilter(this)">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Results</option>
                        <option value="patient" <?php echo $filter === 'patient' ? 'selected' : ''; ?>>Patient</option>
                        <option value="test_type" <?php echo $filter === 'test_type' ? 'selected' : ''; ?>>Test Type</option>
                        <option value="status" <?php echo $filter === 'status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                    <?php if ($filter === 'patient'): ?>
                        <select id="patient_id" onchange="window.location.href='lab_results.php?filter=patient&patient_id='+this.value">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>" <?php echo $patient_id == $patient['patient_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($filter === 'test_type'): ?>
                        <select id="test_type" onchange="window.location.href='lab_results.php?filter=test_type&test_type='+encodeURIComponent(this.value)">
                            <option value="">Select Test Type</option>
                            <?php foreach ($test_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $test_type === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($filter === 'status'): ?>
                        <select id="status" onchange="window.location.href='lab_results.php?filter=status&status='+this.value">
                            <option value="">Select Status</option>
                            <option value="requested" <?php echo $status === 'requested' ? 'selected' : ''; ?>>Requested</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($_SESSION['role'] === 'lab' && count($pending_requests) > 0): ?>
                <h2 class="text-[24px] font-semibold mb-4">Pending Test Requests</h2>
                <table class="w-full border-collapse border border-gray-300 mb-8">
                    <thead>
                        <tr class="bg-gray-200">
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
                                    <a href="lab_results.php?action=upload&result_id=<?php echo $request['result_id']; ?>" class="text-blue-600 hover:underline">Upload Result</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <h2 class="text-[24px] font-semibold mb-4">Lab Results</h2>
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200">
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
                    <?php foreach ($lab_results as $result): ?>
                        <tr>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['test_type']); ?></td>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['request_status']); ?></td>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($result['result_value'] ?: 'N/A'); ?></td>
                            <td class="border border-gray-300 p-2">
                                <?php if ($result['image_path']): ?>
                                    <a href="<?php echo htmlspecialchars($result['image_path']); ?>" target="_blank">
                                        <img src="<?php echo htmlspecialchars($result['image_path']); ?>" alt="Lab Image" style="max-width: 100px;">
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
                        <a href="lab_results.php?filter=<?php echo $filter; ?><?php echo $filter === 'patient' ? '&patient_id=' . $patient_id : ($filter === 'test_type' ? '&test_type=' . urlencode($test_type) : ($filter === 'status' ? '&status=' . $status : '')); ?>&page=<?php echo $page - 1; ?>" class="text-blue-600 hover:underline">Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="lab_results.php?filter=<?php echo $filter; ?><?php echo $filter === 'patient' ? '&patient_id=' . $patient_id : ($filter === 'test_type' ? '&test_type=' . urlencode($test_type) : ($filter === 'status' ? '&status=' . $status : '')); ?>&page=<?php echo $i; ?>" class="text-blue-600 hover:underline <?php echo $i === $page ? 'font-bold' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="lab_results.php?filter=<?php echo $filter; ?><?php echo $filter === 'patient' ? '&patient_id=' . $patient_id : ($filter === 'test_type' ? '&test_type=' . urlencode($test_type) : ($filter === 'status' ? '&status=' . $status : '')); ?>&page=<?php echo $page + 1; ?>" class="text-blue-600 hover:underline">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <!-- <div class="mt-4 text-center">
                <a href="nurse_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a> |
                <a href="../index.php?action=logout" class="text-blue-600 hover:underline">Logout</a>
            </div> -->
        <?php endif; ?>
    </section>
    <script>
        function updateFilter(select) {
            if (select.value === 'patient' || select.value === 'test_type' || select.value === 'status') {
                window.location.href = 'lab_results.php?filter=' + select.value;
            } else {
                window.location.href = 'lab_results.php?filter=all';
            }
        }
    </script>
</body>
</html>