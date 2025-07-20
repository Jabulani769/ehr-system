<?php
ini_set('session.cookie_httponly', 1);
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'nurse'])) {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}
include '../includes/db_connect.php';

// Get user role and department
$user_role = $_SESSION['role'];
$user_department_id = $_SESSION['department_id'] ?? 1;
$error = '';
$active_users = [];
$critical_patients = [];
$lab_results = [];
$medications = [];

try {
    if ($user_role === 'admin') {
        // Admin: Fetch all active users
        try {
            $stmt = $conn->prepare("SELECT u.employee_id, u.username, u.role, u.status, 
                                    IFNULL(d.name, 'Unknown') as name 
                                    FROM users u 
                                    LEFT JOIN departments d ON u.department_id = d.department_id 
                                    WHERE u.status = 'active'");
            $stmt->execute();
            $active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error .= "Error fetching active users: " . $e->getMessage() . "<br>";
        }

        // Admin: Fetch all critical patients
        try {
            $stmt = $conn->prepare("SELECT p.first_name, p.last_name, p.bed_number, p.admitted_at, 
                                    IFNULL(d.name, 'Unknown') as name 
                                    FROM patients p 
                                    LEFT JOIN departments d ON p.department_id = d.department_id 
                                    WHERE p.is_critical = 1 AND p.discharged_at IS NULL");
            $stmt->execute();
            $critical_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error .= "Error fetching critical patients: " . $e->getMessage() . "<br>";
        }

        // Admin: Fetch all lab results
        try {
            $stmt = $conn->prepare("SELECT p.first_name, p.last_name, l.test_type, l.request_status, l.result_value, l.requested_at 
                                    FROM lab_results l 
                                    JOIN patients p ON l.patient_id = p.patient_id");
            $stmt->execute();
            $lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error .= "Error fetching lab results: " . $e->getMessage() . "<br>";
        }

        // Admin: Fetch all medications
        try {
            $stmt = $conn->prepare("SELECT p.first_name, p.last_name, m.medication_name, m.dosage, 
                                    m.frequency, IFNULL(m.start_date, 'Unknown') as start_date 
                                    FROM medications m 
                                    JOIN patients p ON m.patient_id = p.patient_id");
            $stmt->execute();
            $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error .= "Error fetching medications: " . $e->getMessage() . "<br>";
        }
    } else {
        // Nurse: Fetch critical patients in their department
        try {
            $stmt = $conn->prepare("SELECT p.first_name, p.last_name, p.bed_number, p.admitted_at 
                                    FROM patients p 
                                    WHERE p.is_critical = 1 AND p.discharged_at IS NULL AND p.department_id = ?");
            $stmt->execute([$user_department_id]);
            $critical_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error .= "Error fetching critical patients: " . $e->getMessage() . "<br>";
        }
    }
} catch (PDOException $e) {
    $error .= "Database error: " . $e->getMessage() . "<br>";
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
    <title>Reports - MMH EHR</title>
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
        
        <?php if ($user_role === 'admin'): ?>
            <!-- Admin: Active Users Report -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold mb-4">Active Users Report</h2>
                <?php if (empty($active_users)): ?>
                    <p class="text-gray-600">No active users found.</p>
                <?php else: ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="table-header">
                                <th class="border border-gray-300 p-2">Employee ID</th>
                                <th class="border border-gray-300 p-2">Username</th>
                                <th class="border border-gray-300 p-2">Role</th>
                                <th class="border border-gray-300 p-2">Department</th>
                                <th class="border border-gray-300 p-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_users as $user): ?>
                                <tr>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['employee_id']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($user['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Admin: Critical Patients Report -->
            <div class="card p-6 mt-6">
                <h2 class="text-xl font-semibold mb-4">Critical Patients Report</h2>
                <?php if (empty($critical_patients)): ?>
                    <p class="text-gray-600">No critical patients found.</p>
                <?php else: ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="table-header">
                                <th class="border border-gray-300 p-2">Patient</th>
                                <th class="border border-gray-300 p-2">Bed Number</th>
                                <th class="border border-gray-300 p-2">Admitted At</th>
                                <th class="border border-gray-300 p-2">Department</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($critical_patients as $patient): ?>
                                <tr>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Admin: Lab Results Report -->
            <div class="card p-6 mt-6">
                <h2 class="text-xl font-semibold mb-4">Lab Results Report</h2>
                <?php if (empty($lab_results)): ?>
                    <p class="text-gray-600">No lab results found.</p>
                <?php else: ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="table-header">
                                <th class="border border-gray-300 p-2">Patient</th>
                                <th class="border border-gray-300 p-2">Test Type</th>
                                <th class="border border-gray-300 p-2">Status</th>
                                <th class="border border-gray-300 p-2">Result</th>
                                <th class="border border-gray-300 p-2">Requested At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lab_results as $result): ?>
                                <tr>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($result['test_type']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($result['request_status']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($result['result_value'] ?? 'N/A'); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($result['requested_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Admin: Medications Report -->
            <div class="card p-6 mt-6">
                <h2 class="text-xl font-semibold mb-4">Medications Report</h2>
                <?php if (empty($medications)): ?>
                    <p class="text-gray-600">No medications found.</p>
                <?php else: ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="table-header">
                                <th class="border border-gray-300 p-2">Patient</th>
                                <th class="border border-gray-300 p-2">Medication</th>
                                <th class="border border-gray-300 p-2">Dosage</th>
                                <th class="border border-gray-300 p-2">Frequency</th>
                                <th class="border border-gray-300 p-2">Start Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medications as $med): ?>
                                <tr>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($med['first_name'] . ' ' . $med['last_name']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($med['dosage']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($med['frequency'] ?? 'Not specified'); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($med['start_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Nurse: Critical Patients Report -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold mb-4">Critical Patients Report</h2>
                <?php if (empty($critical_patients)): ?>
                    <p class="text-gray-600">No critical patients found in your department.</p>
                <?php else: ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="table-header">
                                <th class="border border-gray-300 p-2">Patient</th>
                                <th class="border border-gray-300 p-2">Bed Number</th>
                                <th class="border border-gray-300 p-2">Admitted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($critical_patients as $patient): ?>
                                <tr>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['bed_number']); ?></td>
                                    <td class="border border-gray-300 text-center p-2"><?php echo htmlspecialchars($patient['admitted_at']); ?></td>
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