<?php
// Set session settings before starting session
ini_set('session.cookie_httponly', 1);
session_start();
// Secure authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
include '../includes/db_connect.php';

$action = $_GET['action'] ?? 'list';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action === 'escalate') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: emergency.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    try {
        // Handle escalation message
        $patient_id = (int)$_POST['patient_id'];
        $recipient_id = (int)$_POST['recipient_id'];
        $message_text = trim($_POST['message_text'] ?? '');

        if (empty($recipient_id) || empty($message_text)) {
            header("Location: emergency.php?action=escalate&patient_id=$patient_id&error=" . urlencode("Recipient and message text are required"));
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, patient_id, message_text, is_urgent, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $recipient_id, $patient_id, $message_text, 1]);
        header("Location: emergency.php?success=" . urlencode("Escalation message sent successfully"));
        exit();
    } catch (PDOException $e) {
        header("Location: emergency.php?action=$action&patient_id=$patient_id&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}

// Fetch critical patients with latest vitals
$stmt = $conn->prepare("
    SELECT p.patient_id, p.first_name, p.last_name, p.bed_number, p.is_critical, 
           v.blood_pressure, v.heart_rate, v.temperature, v.respiratory_rate, v.recorded_at
    FROM patients p
    LEFT JOIN (
        SELECT patient_id, blood_pressure, heart_rate, temperature, respiratory_rate, recorded_at
        FROM vitals
        WHERE (patient_id, recorded_at) IN (
            SELECT patient_id, MAX(recorded_at)
            FROM vitals
            GROUP BY patient_id
        )
    ) v ON p.patient_id = v.patient_id
    WHERE p.is_critical = 1 AND p.discharged_at IS NULL
    ORDER BY p.admitted_at DESC
");
$stmt->execute();
$critical_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch doctors for escalation
$stmt = $conn->prepare("SELECT user_id, username FROM users WHERE role = 'doctor'");
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Emergency Dashboard - MMH EHR</title>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <section class="mt-10 w-full max-w-4xl m-auto p-6">
        <h1 class="text-[28px] font-semibold text-center">Emergency Dashboard</h1>
        <p class="text-center">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>

        <?php if ($success): ?>
            <p class="text-green-500 text-center"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="text-red-500 text-center"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($action === 'escalate' && isset($_GET['patient_id'])): ?>
            <?php
            $patient_id = (int)$_GET['patient_id'];
            $stmt = $conn->prepare("SELECT first_name, last_name FROM patients WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient):
            ?>
            <!-- Escalate form -->
            <form action="emergency.php?action=escalate" method="POST" class="p-6 bg-white border border-gray-300 rounded-xl shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <h2 class="text-[24px] font-semibold mb-4">Escalate: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                <div class="mb-4">
                    <select name="recipient_id" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['user_id']; ?>"><?php echo htmlspecialchars($doctor['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <textarea name="message_text" placeholder="Escalation Details" class="w-full h-20 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required></textarea>
                </div>
                <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 rounded-md">Send Escalation</button>
            </form>
            <?php else: ?>
                <p class="text-red-500 text-center">Patient not found or already discharged</p>
            <?php endif; ?>
            <div class="mt-4 text-center">
                <a href="emergency.php" class="text-blue-600 hover:underline">Back to Emergency Dashboard</a>
            </div>
        <?php else: ?>
            <!-- Critical patients list -->
            <h2 class="text-[24px] font-semibold mb-4">Critical Patients</h2>
            <?php if (count($critical_patients) > 0): ?>
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border border-gray-300 p-2">Name</th>
                            <th class="border border-gray-300 p-2">Bed</th>
                            <th class="border border-gray-300 p-2">Latest Vitals</th>
                            <th class="border border-gray-300 p-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($critical_patients as $patient): ?>
                            <tr class="bg-red-100">
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($patient['bed_number'] ?: 'N/A'); ?></td>
                                <td class="border border-gray-300 p-2">
                                    <?php if ($patient['recorded_at']): ?>
                                        BP: <?php echo htmlspecialchars($patient['blood_pressure']); ?>,
                                        HR: <?php echo htmlspecialchars($patient['heart_rate']); ?>,
                                        Temp: <?php echo htmlspecialchars($patient['temperature']); ?>°C,
                                        Resp: <?php echo htmlspecialchars($patient['respiratory_rate']); ?>,
                                        Recorded: <?php echo htmlspecialchars($patient['recorded_at']); ?>
                                    <?php else: ?>
                                        No vitals recorded
                                    <?php endif; ?>
                                </td>
                                <td class="border border-gray-300 p-2">
                                    <a href="vitals.php?action=history&patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:underline">Vitals History</a> |
                                    <a href="patient_management.php?action=edit&patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:underline">Manage</a> |
                                    <a href="emergency.php?action=escalate&patient_id=<?php echo $patient['patient_id']; ?>" class="text-red-600 hover:underline">Escalate to Doctor</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-600 text-center">No critical patients at this time.</p>
            <?php endif; ?>
            <!-- <div class="mt-4 text-center">
                <a href="nurse_dashboard.php" class="text-blue-600 hover:underline">Back to Dashboard</a> |
                <a href="../index.php?action=logout" class="text-blue-600 hover:underline">Logout</a>
            </div> -->
        <?php endif; ?>
    </section>
</body>
</html>