<?php
ini_set('session.cookie_httponly', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}
include '../includes/db_connect.php';

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'] ?? 0;

// Debug: Log department_id
error_log("User ID: $user_id, Department ID: $department_id");

// Validate department_id
if ($department_id === 0) {
    $error = "User department not set (department_id = 0). Contact the administrator.";
}

// Fetch patients for death recording (only active patients in nurse's department)
try {
    $stmt = $conn->prepare("SELECT patient_id, first_name, last_name 
                            FROM patients 
                            WHERE discharged_at IS NULL AND department_id = ? 
                            ORDER BY first_name, last_name");
    $stmt->execute([$department_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($patients)) {
        $error = "No active patients found in your department (department_id = $department_id).";
    }
} catch (PDOException $e) {
    $error = "Error fetching patients: " . $e->getMessage();
    $patients = [];
}

// Handle death recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_death'])) {
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $cause_of_death = filter_input(INPUT_POST, 'cause_of_death', FILTER_SANITIZE_STRING);
    $date_of_death = filter_input(INPUT_POST, 'date_of_death', FILTER_SANITIZE_STRING);

    if ($patient_id && $cause_of_death && $date_of_death) {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("INSERT INTO deaths (patient_id, date_of_death, cause_of_death, recorded_by, recorded_at, department_id) 
                                    VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$patient_id, $date_of_death, $cause_of_death, $user_id, $department_id]);
            $stmt = $conn->prepare("UPDATE patients SET discharged_at = ? WHERE patient_id = ? AND department_id = ?");
            $stmt->execute([$date_of_death, $patient_id, $department_id]);
            $conn->commit();
            $success = "Death recorded successfully.";
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error recording death: " . $e->getMessage();
        }
    } else {
        $error = "All fields are required.";
    }
}

// Fetch death reports
try {
    $stmt = $conn->prepare("SELECT d.death_id, p.first_name, p.last_name, d.date_of_death, d.cause_of_death, d.recorded_at 
                            FROM deaths d 
                            JOIN patients p ON d.patient_id = p.patient_id 
                            WHERE d.department_id = ? 
                            ORDER BY d.date_of_death DESC");
    $stmt->execute([$department_id]);
    $deaths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching death reports: " . $e->getMessage();
    $deaths = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Death Management - MMH EHR</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .navbar {
            background-color: #f1f5f9; /* bg-slate-100 */
        }
        .card {
            background-color: #ffffff;
            border: 2px solid #e5e7eb; /* border-gray-200 */
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .table-header {
            background-color: #e5e7eb; /* bg-gray-200 */
        }
        .btn-primary {
            background-color: #3b82f6; /* bg-blue-500 */
            border: 2px solid #3b82f6;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #2563eb; /* hover:bg-blue-600 */
        }
        @media (max-width: 640px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
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

        <?php if (isset($_GET['action']) && $_GET['action'] === 'record'): ?>
            <!-- Record Death -->
            <div class="card p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Record Death</h2>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="patient_id" class="block text-gray-700">Patient</label>
                        <select name="patient_id" id="patient_id" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="date_of_death" class="block text-gray-700">Date of Death</label>
                        <input type="datetime-local" name="date_of_death" id="date_of_death" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div class="mb-4">
                        <label for="cause_of_death" class="block text-gray-700">Cause of Death</label>
                        <textarea name="cause_of_death" id="cause_of_death" rows="5" class="w-full p-2 border border-gray-300 rounded" required></textarea>
                    </div>
                    <button type="submit" name="record_death" class="btn-primary text-white px-4 py-2 rounded">Record Death</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Death Reports -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold mb-4">Death Reports</h2>
                <?php if (empty($deaths)): ?>
                    <p class="text-gray-600">No deaths recorded.</p>
                <?php else: ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="table-header">
                                <th class="border border-gray-300 p-2">Patient</th>
                                <th class="border border-gray-300 p-2">Date of Death</th>
                                <th class="border border-gray-300 p-2">Cause of Death</th>
                                <th class="border border-gray-300 p-2">Recorded At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deaths as $death): ?>
                                <tr>
                                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['first_name'] . ' ' . $death['last_name']); ?></td>
                                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['date_of_death']); ?></td>
                                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['cause_of_death']); ?></td>
                                    <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($death['recorded_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>