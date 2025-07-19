<?php
ini_set('session.cookie_httponly', 1);
session_start();
include '../includes/db_connect.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'nurse') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}



$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$action = $_GET['action'] ?? 'list';

// Function to determine critical condition
function isCritical($blood_pressure, $heart_rate, $temperature, $respiratory_rate) {
    list($systolic, $diastolic) = array_map('intval', explode('/', $blood_pressure));
    $is_critical = (
        ($systolic > 180 || $systolic < 90 || $diastolic > 120 || $diastolic < 60) ||
        ($heart_rate > 100 || $heart_rate < 60) ||
        ($temperature > 38 || $temperature < 36) ||
        ($respiratory_rate > 20 || $respiratory_rate < 12)
    );
    return $is_critical ? 1 : 0;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'new') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: vitals.php?action=new&error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    $patient_id = $_POST['patient_id'] ?? '';
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $heart_rate = $_POST['heart_rate'] ?? '';
    $temperature = $_POST['temperature'] ?? '';
    $respiratory_rate = $_POST['respiratory_rate'] ?? '';

    if (empty($patient_id) || empty($blood_pressure) || empty($heart_rate) || empty($temperature) || empty($respiratory_rate)) {
        header("Location: vitals.php?action=new&error=" . urlencode("All fields are required"));
        exit();
    }

    if (!preg_match('/^\d{1,3}\/\d{1,3}$/', $blood_pressure)) {
        header("Location: vitals.php?action=new&error=" . urlencode("Invalid blood pressure format (e.g., 120/80)"));
        exit();
    }

    $is_critical = isCritical($blood_pressure, $heart_rate, $temperature, $respiratory_rate);

    try {
        $stmt = $conn->prepare("
            INSERT INTO vitals (patient_id, nurse_id, blood_pressure, heart_rate, temperature, respiratory_rate, is_critical)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $_SESSION['user_id'], $blood_pressure, $heart_rate, $temperature, $respiratory_rate, $is_critical]);
        header("Location: vitals.php?action=history&success=" . urlencode("Vitals recorded"));
        exit();
    } catch (PDOException $e) {
        header("Location: vitals.php?action=new&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search']) && $action == 'history') {
    $search = trim($_GET['search'] ?? '');
    $query = "
        SELECT v.*, p.first_name, p.last_name, u.employee_id
        FROM vitals v
        JOIN patients p ON v.patient_id = p.patient_id
        JOIN users u ON v.nurse_id = u.user_id
        WHERE LOWER(p.first_name) LIKE LOWER(?) OR LOWER(p.last_name) LIKE LOWER(?) OR p.patient_id = ?
        ORDER BY v.recorded_at DESC
    ";
    $stmt = $conn->prepare($query);
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search]);
    $vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $vitals = $conn->query("
        SELECT v.*, p.first_name, p.last_name, u.employee_id
        FROM vitals v
        JOIN patients p ON v.patient_id = p.patient_id
        JOIN users u ON v.nurse_id = u.user_id
        ORDER BY v.recorded_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitals Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    <div class="container mx-auto mt-10 px-4">
        <h1 class="text-2xl font-bold mb-4">Vitals Management</h1>
        <?php if (isset($_GET['error'])): ?>
            <p class="text-red-500 mb-4"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <p class="text-green-500 mb-4"><?php echo htmlspecialchars($_GET['success']); ?></p>
        <?php endif; ?>

        <?php if ($action == 'new'): ?>
            <form action="vitals.php?action=new" method="POST" class="mt-4 bg-white p-6 rounded-lg shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-4">
                    <label for="patient_id" class="block text-sm font-medium text-gray-700">Select Patient</label>
                    <select name="patient_id" id="patient_id" class="p-2 border rounded w-full" required>
                        <option value="">Select Patient</option>
                        <?php
                        $patients = $conn->query("SELECT patient_id, first_name, last_name FROM patients WHERE discharged_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($patients)) {
                            echo '<option value="" disabled>No active patients available</option>';
                        } else {
                            foreach ($patients as $patient) {
                                echo "<option value='{$patient['patient_id']}'>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="blood_pressure" class="block text-sm font-medium text-gray-700">Blood Pressure (e.g., 120/80)</label>
                    <input type="text" name="blood_pressure" id="blood_pressure" placeholder="Blood Pressure" 
                           class="p-2 border rounded w-full" required>
                </div>
                <div class="mb-4">
                    <label for="heart_rate" class="block text-sm font-medium text-gray-700">Heart Rate (bpm)</label>
                    <input type="number" name="heart_rate" id="heart_rate" placeholder="Heart Rate" 
                           class="p-2 border rounded w-full" required>
                </div>
                <div class="mb-4">
                    <label for="temperature" class="block text-sm font-medium text-gray-700">Temperature (°C)</label>
                    <input type="number" step="0.1" name="temperature" id="temperature" placeholder="Temperature" 
                           class="p-2 border rounded w-full" required>
                </div>
                <div class="mb-4">
                    <label for="respiratory_rate" class="block text-sm font-medium text-gray-700">Respiratory Rate (breaths/min)</label>
                    <input type="number" name="respiratory_rate" id="respiratory_rate" placeholder="Respiratory Rate" 
                           class="p-2 border rounded w-full" required>
                </div>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white p-2 rounded w-full">
                    <i class="fa-solid fa-save mr-2"></i> Save Vitals
                </button>
            </form>
        <?php elseif ($action == 'history'): ?>
            <form action="vitals.php?action=history" method="GET" class="mt-4 flex gap-2">
                <input type="hidden" name="action" value="history">
                <input type="text" name="search" placeholder="Search by patient name or ID" 
                       class="p-2 border rounded w-full" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded">
                    <i class="fa-solid fa-search mr-2"></i> Search
                </button>
            </form>
            <h2 class="text-xl font-semibold mt-6">Vitals History</h2>
            <table class="w-full mt-4 border bg-white rounded-lg shadow-md">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2">Patient</th>
                        <th class="p-2">Nurse</th>
                        <th class="p-2">BP</th>
                        <th class="p-2">HR</th>
                        <th class="p-2">Temp</th>
                        <th class="p-2">Resp</th>
                        <th class="p-2">Critical</th>
                        <th class="p-2">Date</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vitals as $vital): ?>
                        <tr>
                            <td class="p-2"><?php echo htmlspecialchars($vital['first_name'] . ' ' . $vital['last_name']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($vital['employee_id']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($vital['blood_pressure']); ?></td>
                            <td class="p-2"><?php echo $vital['heart_rate']; ?></td>
                            <td class="p-2"><?php echo $vital['temperature']; ?>°C</td>
                            <td class="p-2"><?php echo $vital['respiratory_rate']; ?></td>
                            <td class="p-2"><?php echo $vital['is_critical'] ? '<span class="text-red- element600">Yes</span>' : 'No'; ?></td>
                            <td class="p-2"><?php echo $vital['recorded_at']; ?></td>
                            <td class="p-2">
                                <a href="vitals.php?action=graph&patient_id=<?php echo $vital['patient_id']; ?>" 
                                   class="text-blue-600 hover:underline">View Graph</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($action == 'graph' && isset($_GET['patient_id'])): ?>
            <?php
            $patient_id = $_GET['patient_id'];
            $stmt = $conn->prepare("
                SELECT v.blood_pressure, v.heart_rate, v.temperature, v.respiratory_rate, v.recorded_at, 
                       p.first_name, p.last_name
                FROM vitals v
                JOIN patients p ON v.patient_id = p.patient_id
                WHERE v.patient_id = ?
                ORDER BY v.recorded_at DESC
            ");
            $stmt->execute([$patient_id]);
            $vitals_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $patient_name = $vitals_data ? htmlspecialchars($vitals_data[0]['first_name'] . ' ' . $vitals_data[0]['last_name']) : 'Unknown';
            ?>
            <h2 class="text-xl font-semibold mt-6">Vitals Graph for <?php echo $patient_name; ?></h2>
            <canvas id="vitalsChart" class="mt-4 max-w-4xl"></canvas>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
            <script src="../js/vitals-chart.js"></script>
            <script>
                const vitalsData = <?php echo json_encode($vitals_data); ?>;
            </script>
        <?php else: ?>
            <p class="mt-4">Select an action: 
                <a href="vitals.php?action=new" class="text-blue-600 hover:underline">New Reading</a> | 
                <a href="vitals.php?action=history" class="text-blue-600 hover:underline">View History</a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>