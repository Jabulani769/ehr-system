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

$department_id = $_SESSION['department_id'] ?? 0;
if ($department_id === 0) {
    $error = "User department not set (department_id = 0). Contact the administrator.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: patient_management.php?error=" . urlencode("Invalid CSRF token"));
        exit();
    }

    try {
        if ($action === 'admit') {
            // Handle patient admission with bed number, critical status, and department_id
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $dob = $_POST['dob'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $bed_number = trim($_POST['bed_number'] ?? '');
            $is_critical = isset($_POST['is_critical']) ? 1 : 0;

            if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || $department_id === 0) {
                header("Location: patient_management.php?action=admit&error=" . urlencode("All required fields must be filled and department must be set"));
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO patients (first_name, last_name, dob, gender, phone, bed_number, is_critical, admitted_at, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$first_name, $last_name, $dob, $gender, $phone, $bed_number, $is_critical, $department_id]);
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

            if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || $department_id === 0) {
                header("Location: patient_management.php?action=edit&patient_id=$patient_id&error=" . urlencode("All required fields must be filled and department must be set"));
                exit();
            }

            $stmt = $conn->prepare("UPDATE patients SET first_name = ?, last_name = ?, dob = ?, gender = ?, phone = ?, is_critical = ?, department_id = ? WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$first_name, $last_name, $dob, $gender, $phone, $is_critical, $department_id, $patient_id]);
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Patient Management - MMH EHR</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .card {
            background-color: #ffffff;
            border: 2px solid #e5e7eb; /* border-gray-200 */
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #3b82f6; /* bg-blue-500 */
            border: 2px solid #3b82f6;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #2563eb; /* hover:bg-blue-600 */
        }
        .table-header {
            background-color: #e5e7eb; /* bg-gray-200 */
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

        <?php if ($action === 'admit'): ?>
            <!-- Admit form with bed number and critical status -->
            <div class="card w-[60%] sm:w-full m-auto p-6 mb-6">
                <form action="patient_management.php?action=admit" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <h2 class="text-xl font-semibold mb-4">Admit New Patient</h2>
                    <div class="mb-4">
                        <input type="text" name="first_name" placeholder="First Name" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div class="mb-4">
                        <input type="text" name="last_name" placeholder="Last Name" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div class="mb-4">
                        <input type="date" name="dob" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div class="mb-4">
                        <select name="gender" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <input type="text" name="phone" placeholder="Phone" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                    <div class="mb-4">
                        <input type="text" name="bed_number" placeholder="Bed Number" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                    <div class="mb-4">
                        <label><input type="checkbox" name="is_critical"> Critical Condition</label>
                    </div>
                    <button type="submit" class="btn-primary text-white px-4 py-2 rounded">Admit Patient</button>
                </form>
            </div>
        <?php elseif ($action === 'edit' && isset($_GET['patient_id'])): ?>
            <?php
            $patient_id = $_GET['patient_id'];
            $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ? AND discharged_at IS NULL");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient):
            ?>
            <!-- Edit form -->
            <div class="card p-6 mb-6">
                <form action="patient_management.php?action=edit" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <h2 class="text-xl font-semibold mb-4">Edit Patient</h2>
                    <div class="mb-4">
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($patient['first_name']); ?>" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div class="mb-4">
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($patient['last_name']); ?>" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div class="mb-4">
                        <input type="date" name="dob" value="<?php echo $patient['dob']; ?>" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <div class="mb-4">
                        <select name="gender" class="w-full p-2 border border-gray-300 rounded" required>
                            <option value="male" <?php echo $patient['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $patient['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $patient['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($patient['phone']); ?>" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                    <div class="mb-4">
                        <label><input type="checkbox" name="is_critical" <?php echo $patient['is_critical'] ? 'checked' : ''; ?>> Critical Condition</label>
                    </div>
                    <button type="submit" class="btn-primary text-white px-4 py-2 rounded">Update Patient</button>
                </form>
            </div>
            <?php else: ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    Patient not found or already discharged
                </div>
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
            <div class="card p-6 mb-6">
                <form action="patient_management.php?action=discharge" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <h2 class="text-xl font-semibold mb-4">Discharge Patient: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                    <div class="mb-4">
                        <textarea name="discharge_notes" placeholder="Discharge Notes" class="w-full p-2 border border-gray-300 rounded"></textarea>
                    </div>
                    <button type="submit" class="btn-primary text-white px-4 py-2 rounded">Discharge Patient</button>
                </form>
            </div>
            <?php else: ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    Patient not found or already discharged
                </div>
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
            <div class="card p-6 mb-6">
                <form action="patient_management.php?action=assign_bed" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <h2 class="text-xl font-semibold mb-4">Reassign Bed for: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                    <div class="mb-4">
                        <input type="text" name="bed_number" placeholder="New Bed Number" value="<?php echo htmlspecialchars($patient['bed_number']); ?>" class="w-full p-2 border border-gray-300 rounded" required>
                    </div>
                    <button type="submit" class="btn-primary text-white px-4 py-2 rounded">Reassign Bed</button>
                </form>
            </div>
            <?php else: ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    Patient not found or already discharged
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Patient list with sorting and vitals link -->
            <div class="card p-6">
                <div class="mb-4 flex justify-between items-center">
                    <a href="patient_management.php?action=admit" class="text-blue-600 hover:underline">Admit New Patient</a>
                    <div>
                        <label for="sort" class="mr-2">Sort By:</label>
                        <select id="sort" onchange="window.location.href='patient_management.php?sort='+this.value" class="p-2 border border-gray-300 rounded">
                            <option value="admitted_at" <?php echo $sort_by === 'admitted_at' ? 'selected' : ''; ?>>Newest Admission</option>
                            <option value="last_name" <?php echo $sort_by === 'last_name' ? 'selected' : ''; ?>>Alphabetical</option>
                        </select>
                    </div>
                </div>
                <?php
                // Filter active patients in the nurse's department
                $query = "
                    SELECT p.*, 
                        EXISTS (
                            SELECT 1 FROM vitals v 
                            WHERE v.patient_id = p.patient_id 
                            AND v.is_critical = TRUE 
                            AND p.discharged_at IS NULL
                        ) AS is_critical_dynamic
                    FROM patients p
                    WHERE p.discharged_at IS NULL AND p.department_id = ?
                    ORDER BY $sort_by $sort_order
                ";
                $stmt = $conn->prepare($query);
                $stmt->execute([$department_id]);
                $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="table-header">
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
                <?php if (empty($patients)): ?>
                    <p class="text-gray-600 text-center mt-4">No active patients found in your department.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>