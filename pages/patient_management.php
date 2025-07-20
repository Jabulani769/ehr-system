<?php
// Set session settings before starting session
ini_set('session.cookie_httponly', 1);
session_start();
// Secure authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'nurse') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../includes/db_connect.php';

// Initialize sort parameters (default: newest admission)
$sort_by = isset($_GET['sort']) && in_array($_GET['sort'], ['admitted_at', 'last_name']) ? $_GET['sort'] : 'admitted_at';
$sort_order = ($sort_by === 'admitted_at') ? 'DESC' : 'ASC';

$action = $_GET['action'] ?? 'list';
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: patient_management.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    try {
        if ($action === 'admit') {
            // Handle patient admission with bed number and critical status
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $dob = $_POST['dob'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $bed_number = trim($_POST['bed_number'] ?? '');
            $is_critical = isset($_POST['is_critical']) ? 1 : 0;

            if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender)) {
                header("Location: patient_management.php?action=admit&error=" . urlencode("All required fields must be filled"));
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO patients (first_name, last_name, dob, gender, phone, bed_number, is_critical, admitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$first_name, $last_name, $dob, $gender, $phone, $bed_number, $is_critical]);
            header("Location: patient_management.php?success=" . urlencode("Patient admitted successfully"));
            exit();
        } elseif ($action === 'edit' && isset($_POST['patient_id'])) {
            // Handle patient editing
            $patient_id = $_POST['patient_id'];
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $dob = $_POST['dob'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $is_critical = isset($_POST['is_critical']) ? 1 : 0;

            if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender)) {
                header("Location: patient_management.php?action=edit&patient_id=$patient_id&error=" . urlencode("All required fields must be filled"));
                exit();
            }

            $stmt = $conn->prepare("UPDATE patients SET first_name = ?, last_name = ?, dob = ?, gender = ?, phone = ?, is_critical = ? WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$first_name, $last_name, $dob, $gender, $phone, $is_critical, $patient_id]);
            header("Location: patient_management.php?success=" . urlencode("Patient updated successfully"));
            exit();
        } elseif ($action === 'discharge' && isset($_POST['patient_id'])) {
            // Handle patient discharge with notes
            $patient_id = $_POST['patient_id'];
            $discharge_notes = trim($_POST['discharge_notes'] ?? '');

            $stmt = $conn->prepare("UPDATE patients SET discharged_at = NOW(), discharge_notes = ? WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$discharge_notes, $patient_id]);
            header("Location: patient_management.php?success=" . urlencode("Patient discharged successfully"));
            exit();
        } elseif ($action === 'assign_bed' && isset($_POST['patient_id'])) {
            // Handle bed reassignment
            $patient_id = $_POST['patient_id'];
            $bed_number = trim($_POST['bed_number'] ?? '');

            if (empty($bed_number)) {
                header("Location: patient_management.php?action=assign_bed&patient_id=$patient_id&error=" . urlencode("Bed number is required"));
                exit();
            }

            $stmt = $conn->prepare("UPDATE patients SET bed_number = ? WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$bed_number, $patient_id]);
            header("Location: patient_management.php?success=" . urlencode("Bed reassigned successfully"));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: patient_management.php?action=$action&error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Patient Management - MMH EHR</title>
</head>
<body>
<?php include '../includes/header.php'; ?>
    <section class="mt-10 w-full max-w-7xl m-auto p-6">
        <h1 class="text-[28px] font-semibold text-center">Patient Management</h1>

        <?php if ($success): ?>
            <p class="text-green-500 text-center"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="text-red-500 text-center"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ($action === 'admit'): ?>
            <!-- Admit form with bed number and critical status -->
            <form action="patient_management.php?action=admit" method="POST" class="w-[60%] m-auto p-6 bg-white border border-gray-300 rounded-xl shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <h2 class="text-[24px] font-semibold mb-4">Admit New Patient</h2>
                <div class="mb-4">
                    <input type="text" name="first_name" placeholder="First Name" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <div class="mb-4">
                    <input type="text" name="last_name" placeholder="Last Name" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <div class="mb-4">
                    <input type="date" name="dob" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <div class="mb-4">
                    <select name="gender" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-4">
                    <input type="text" name="phone" placeholder="Phone" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700">
                </div>
                <div class="mb-4">
                    <input type="text" name="bed_number" placeholder="Bed Number" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700">
                </div>
                <div class="mb-4">
                    <label><input type="checkbox" name="is_critical"> Critical Condition</label>
                </div>
                <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 rounded-md">Admit Patient</button>
            </form>
        <?php elseif ($action === 'edit' && isset($_GET['patient_id'])): ?>
            <?php
            $patient_id = $_GET['patient_id'];
            $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient):
            ?>
            <!-- Edit form -->
            <form action="patient_management.php?action=edit" method="POST" class="w-[60%] m-auto p-6 bg-white border border-gray-300 rounded-xl shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <h2 class="text-[24px] font-semibold mb-4">Edit Patient</h2>
                <div class="mb-4">
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($patient['first_name']); ?>" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <div class="mb-4">
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($patient['last_name']); ?>" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <div class="mb-4">
                    <input type="date" name="dob" value="<?php echo $patient['dob']; ?>" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <div class="mb-4">
                    <select name="gender" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                        <option value="male" <?php echo $patient['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo $patient['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo $patient['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="mb-4">
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($patient['phone']); ?>" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700">
                </div>
                <div class="mb-4">
                    <label><input type="checkbox" name="is_critical" <?php echo $patient['is_critical'] ? 'checked' : ''; ?>> Critical Condition</label>
                </div>
                <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 rounded-md">Update Patient</button>
            </form>
            <?php else: ?>
                <p class="text-red-500 text-center">Patient not found or already discharged</p>
            <?php endif; ?>
        <?php elseif ($action === 'discharge' && isset($_GET['patient_id'])): ?>
            <?php
            $patient_id = $_GET['patient_id'];
            $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient):
            ?>
            <!-- Discharge form with notes -->
            <form action="patient_management.php?action=discharge" method="POST" class="p-6 bg-white border border-gray-300 rounded-xl shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <h2 class="text-[24px] font-semibold mb-4">Discharge Patient: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                <div class="mb-4">
                    <textarea name="discharge_notes" placeholder="Discharge Notes" class="w-full h-20 border-b-2 border-gray-400 outline-none focus:border-b-blue-700"></textarea>
                </div>
                <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 rounded-md">Discharge Patient</button>
            </form>
            <?php else: ?>
                <p class="text-red-500 text-center">Patient not found or already discharged</p>
            <?php endif; ?>
        <?php elseif ($action === 'assign_bed' && isset($_GET['patient_id'])): ?>
            <?php
            $patient_id = $_GET['patient_id'];
            $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient):
            ?>
            <!-- Reassign bed form -->
            <form action="patient_management.php?action=assign_bed" method="POST" class="p-6 bg-white border border-gray-300 rounded-xl shadow-md">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <h2 class="text-[24px] font-semibold mb-4">Reassign Bed for: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                <div class="mb-4">
                    <input type="text" name="bed_number" placeholder="New Bed Number" value="<?php echo htmlspecialchars($patient['bed_number']); ?>" class="w-full h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700" required>
                </div>
                <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 rounded-md">Reassign Bed</button>
            </form>
            <?php else: ?>
                <p class="text-red-500 text-center">Patient not found or already discharged</p>
            <?php endif; ?>
        <?php else: ?>
            <!-- Patient list with sorting and vitals link -->
            <div class="mb-4 flex justify-between">
                <a href="patient_management.php?action=admit" class="text-blue-600 hover:underline">Admit New Patient</a>
                <div>
                    <label for="sort">Sort By:</label>
                    <select id="sort" onchange="window.location.href='patient_management.php?sort='+this.value">
                        <option value="admitted_at" <?php echo $sort_by === 'admitted_at' ? 'selected' : ''; ?>>Newest Admission</option>
                        <option value="last_name" <?php echo $sort_by === 'last_name' ? 'selected' : ''; ?>>Alphabetical</option>
                    </select>
                </div>
            </div>
            <?php
            // Filter active patients
            $query = "
                SELECT p.*, 
                    EXISTS (
                        SELECT 1 FROM vitals v 
                        WHERE v.patient_id = p.patient_id 
                        AND v.is_critical = TRUE 
                        AND p.discharged_at IS NULL
                    ) AS is_critical_dynamic
                FROM patients p
                WHERE p.discharged_at IS NULL
                ORDER BY $sort_by $sort_order
            ";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-300 p-2">Name</th>
                        <th class="border border-gray-300 p-2">DOB</th>
                        <th class="border border-gray-300 p-2">Gender</th>
                        <th class="border border-gray-300 p-2">Phone</th>
                        <th class="border border-gray-300 p-2">Bed</th>
                        <th class="border border-gray-300 p-2">Critical</th>
                        <th class="border border-gray-300 p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                            <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['dob']); ?></td>
                            <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['gender']); ?></td>
                            <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['phone']); ?></td>
                            <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                            <td class="border border-gray-300 text-center p-2">
                                <?php echo $patient['is_critical_dynamic'] ? '<span class="text-red-600 font-semibold">Yes</span>' : 'No'; ?>
                            </td>
                            <td class="border border-gray-300 text-center p-2">
                                <a href="patient_management.php?action=edit&patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:underline">Edit</a> |
                                <a href="patient_management.php?action=discharge&patient_id=<?php echo $patient['patient_id']; ?>" class="text-red-600 hover:underline">Discharge</a> |
                                <a href="patient_management.php?action=assign_bed&patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:underline">Reassign Bed</a> |
                                <a href="vitals.php?action=history&patient_id=<?php echo $patient['patient_id']; ?>" class="text-blue-600 hover:underline">Vitals</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</body>
</html>