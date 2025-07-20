<?php
ini_set('session.cookie_httponly', 1);
session_start();
include '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$action = $_GET['action'] ?? 'list';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: vitals.php?action=new&error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    // Validate required fields including patient_id
    $patient_id = $_POST['patient_id'] ?? '';
    $blood_pressure = trim($_POST['blood_pressure'] ?? '');
    $heart_rate = $_POST['heart_rate'] ?? '';
    $temperature = $_POST['temperature'] ?? '';
    $respiratory_rate = $_POST['respiratory_rate'] ?? '';
    $is_critical = isset($_POST['is_critical']) ? 1 : 0;

    if (empty($patient_id) || empty($blood_pressure) || empty($heart_rate) || empty($temperature) || empty($respiratory_rate)) {
        header("Location: vitals.php?action=new&error=" . urlencode("All fields must be filled"));
        exit();
    }

    // Basic blood pressure format validation (e.g., 120/80)
    if (!preg_match('/^\d{2,3}\/\d{2,3}$/', $blood_pressure)) {
        header("Location: vitals.php?action=new&error=" . urlencode("Blood pressure must be in the format 'systolic/diastolic', e.g., 120/80"));
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO vitals (patient_id, blood_pressure, heart_rate, temperature, respiratory_rate, is_critical, recorded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$patient_id, $blood_pressure, $heart_rate, $temperature, $respiratory_rate, $is_critical]);
        header("Location: vitals.php?action=new&success=" . urlencode("Vitals recorded successfully"));
        exit();
    } catch (PDOException $e) {
        header("Location: vitals.php?action=new&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}

// Fetch active patients for selection if action is new
if ($action === 'new') {
    $stmt = $conn->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE discharged_at IS NULL ORDER BY last_name");
    $stmt->execute();
    $active_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// For history, fetch vitals of active patients only
if ($action === 'history' || $action === 'list') {
    $stmt = $conn->prepare("
        SELECT v.*, p.first_name, p.last_name 
        FROM vitals v 
        JOIN patients p ON v.patient_id = p.patient_id 
        WHERE p.discharged_at IS NULL 
        ORDER BY v.recorded_at DESC
    ");
    $stmt->execute();
    $vitals_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Patient Vitals - MMH</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <section class="max-w-4xl mx-auto mt-10 p-6 bg-white rounded-lg shadow-md border border-gray-300">
        <h1 class="text-3xl font-semibold mb-6 text-blue-600 flex items-center gap-2">
            <i class="fa-solid fa-heart-pulse"></i> 
            <?php echo ucfirst($action) === 'New' ? 'New Vitals Reading' : 'Vitals History'; ?>
        </h1>

        <?php if ($success): ?>
            <p class="text-green-600 font-medium mb-4"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="text-red-600 font-medium mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($action === 'new'): ?>
            <form action="vitals.php?action=new" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div>
                    <label for="patient_id" class="block mb-1 font-semibold text-gray-700">Select Patient</label>
                    <select name="patient_id" id="patient_id" required
                        class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Choose Patient --</option>
                        <?php foreach ($active_patients as $patient): ?>
                            <option value="<?php echo $patient['patient_id']; ?>">
                                <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="blood_pressure" class="block mb-1 font-semibold text-gray-700">Blood Pressure (e.g., 120/80 mmHg)</label>
                    <input type="text" id="blood_pressure" name="blood_pressure" placeholder="120/80" required
                        class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label for="heart_rate" class="block mb-1 font-semibold text-gray-700">Heart Rate (bpm)</label>
                        <input type="number" id="heart_rate" name="heart_rate" placeholder="72" required min="30" max="200"
                            class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label for="temperature" class="block mb-1 font-semibold text-gray-700">Temperature (°C)</label>
                        <input type="number" step="0.1" id="temperature" name="temperature" placeholder="36.6" required min="30" max="45"
                            class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6 items-center">
                    <div>
                        <label for="respiratory_rate" class="block mb-1 font-semibold text-gray-700">Respiratory Rate (breaths/min)</label>
                        <input type="number" id="respiratory_rate" name="respiratory_rate" placeholder="16" required min="10" max="60"
                            class="w-full border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div class="flex items-center gap-2 mt-6">
                        <input type="checkbox" id="is_critical" name="is_critical" class="h-5 w-5 text-red-600" />
                        <label for="is_critical" class="font-semibold text-red-600">Critical Condition</label>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-md transition">
                    Submit Reading
                </button>
            </form>
        <?php else: ?>
            <?php if (empty($vitals_history)): ?>
                <p class="text-gray-600">No vitals records found for active patients.</p>
            <?php else: ?>
                <table class="w-full border-collapse border border-gray-300 text-left">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 p-2">Patient</th>
                            <th class="border border-gray-300 p-2">BP (mmHg)</th>
                            <th class="border border-gray-300 p-2">Heart Rate (bpm)</th>
                            <th class="border border-gray-300 p-2">Temp (°C)</th>
                            <th class="border border-gray-300 p-2">Resp Rate (breaths/min)</th>
                            <th class="border border-gray-300 p-2">Critical</th>
                            <th class="border border-gray-300 p-2">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vitals_history as $vital): ?>
                            <tr>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($vital['last_name'] . ' ' . $vital['first_name']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($vital['blood_pressure']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($vital['heart_rate']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($vital['temperature']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($vital['respiratory_rate']); ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo $vital['is_critical'] ? 'Yes' : 'No'; ?></td>
                                <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($vital['recorded_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</body>
</html>
