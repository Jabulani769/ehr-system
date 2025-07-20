<?php
ini_set('session.cookie_httponly', 1);
session_start();
include '../includes/db_connect.php';

// Helper functions (put these in functions.php if you want)
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function format_datetime($datetime_str) {
    if (!$datetime_str) return '-';
    $dt = new DateTime($datetime_str);
    return $dt->format('M d, Y H:i');
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medication'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $patient_id = sanitize($_POST['patient_id']);
        $medication_name = sanitize($_POST['medication_name']);
        $dosage = sanitize($_POST['dosage']);
        $scheduled_time = sanitize($_POST['scheduled_time']);

        if (!empty($patient_id) && !empty($medication_name) && !empty($dosage)) {
            $stmt = $conn->prepare("INSERT INTO medications (patient_id, nurse_id, medication_name, dosage, scheduled_time, status) 
                                    VALUES (?, ?, ?, ?, ?, 'scheduled')");
            $stmt->execute([$patient_id, $_SESSION['user_id'], $medication_name, $dosage, $scheduled_time]);
            $success = "Medication scheduled successfully.";
            // Regenerate CSRF token after successful submission to prevent replay
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $error = "All fields are required.";
        }
    }
}

// Fetch patients (only active)
$patients = $conn->query("SELECT patient_id, first_name, last_name FROM patients WHERE discharged_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);

// Fetch medications with patient names
$medications = $conn->query("
    SELECT m.*, p.first_name, p.last_name
    FROM medications m
    JOIN patients p ON m.patient_id = p.patient_id
    ORDER BY m.scheduled_time DESC
")->fetchAll(PDO::FETCH_ASSOC);

$view = $_GET['view'] ?? 'form'; // Default to form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Medication Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 min-h-screen">
<?php include '../includes/header.php'; ?>

<div class="container mx-auto px-4 mt-8">
    <div class="flex justify-between mb-4">
        <h1 class="text-2xl font-bold text-blue-700"><i class="fa-solid fa-pills"></i> Medication Module</h1>
        <div class="space-x-2">
            <a href="?view=form" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                <i class="fa-solid fa-plus"></i> Add Medication
            </a>
            <a href="?view=table" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                <i class="fa-solid fa-table-list"></i> View Medications
            </a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 mb-4 rounded">
            <?= $success ?>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 mb-4 rounded">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($view === 'form'): ?>
        <!-- Medication Form -->
        <div class="bg-white p-6 rounded shadow-md max-w-lg mx-auto">
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>" />
                <div class="mb-4">
                    <label for="patient_id" class="block mb-1 font-semibold text-gray-700">Select Patient</label>
                    <select name="patient_id" id="patient_id" required class="w-full border-2 border-gray-300 outline-none rounded p-2">
                        <option value="">-- Choose patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['patient_id'] ?>">
                                <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="medication_name" class="block mb-1 font-semibold text-gray-700">Medication Name</label>
                    <input type="text" name="medication_name" id="medication_name" class="w-full border-2 outline-none border-gray-300 rounded p-2" required />
                </div>

                <div class="mb-4">
                    <label for="dosage" class="block mb-1 font-semibold text-gray-700">Dosage</label>
                    <input type="text" name="dosage" id="dosage" class="w-full border-2 outline-none border-gray-300 rounded p-2" required />
                </div>

                <div class="mb-4">
                    <label for="scheduled_time" class="block mb-1 font-semibold text-gray-700">Scheduled Time</label>
                    <input type="datetime-local" name="scheduled_time" id="scheduled_time" class="w-full border-2 outline-none border-gray-300 rounded p-2" />
                </div>

                <button type="submit" name="add_medication" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded w-full">
                    Submit
                </button>
            </form>
        </div>
    <?php elseif ($view === 'table'): ?>
        <!-- Medication Table -->
        <div class="overflow-x-auto bg-white p-4 rounded shadow-md max-w-5xl mx-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-700">
                        <th class="p-3">Patient</th>
                        <th class="p-3">Medication</th>
                        <th class="p-3">Dosage</th>
                        <th class="p-3">Scheduled Time</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Recorded At</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-200">
                    <?php if (empty($medications)): ?>
                        <tr><td colspan="6" class="text-center p-4 text-gray-500">No medication records available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($medications as $med): ?>
                            <tr>
                                <td class="p-3"><?= htmlspecialchars($med['first_name'] . ' ' . $med['last_name']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($med['medication_name']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($med['dosage']) ?></td>
                                <td class="p-3"><?= format_datetime($med['scheduled_time']) ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded-full 
                                        <?= $med['status'] === 'administered' ? 'bg-green-100 text-green-700' : ($med['status'] === 'missed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') ?>">
                                        <?= ucfirst($med['status']) ?>
                                    </span>
                                </td>
                                <td class="p-3"><?= format_datetime($med['recorded_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
