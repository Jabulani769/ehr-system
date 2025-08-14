<?php

session_start();
include '../includes/db_connect.php';

if (!isset($_SESSION['user_id'], $_SESSION['department_id'])) {
    header('Location: ../index.php?error=' . urlencode('Please log in'));
    exit();
}

// Get department name from ID
$stmt = $conn->prepare("SELECT name FROM departments WHERE department_id = ?");
$stmt->execute([$_SESSION['department_id']]);
$dept = $stmt->fetch(PDO::FETCH_ASSOC);
$department = $dept ? $dept['name'] : '';
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'inbox';

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Messages - Mulanje Mission Hospital EHR</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100">
<?php include '../includes/header.php'; ?>

<section class="container mx-auto p-6 max-w-4xl">
    <h1 class="text-3xl font-bold mb-6">Messages</h1>

    <?php if ($success): ?>
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="mb-6 flex space-x-4">
        <a href="messages.php?action=inbox" class="text-blue-600 hover:underline <?php echo $action === 'inbox' ? 'font-bold' : ''; ?>">Inbox</a>
        <a href="messages.php?action=compose" class="text-green-600 hover:underline <?php echo $action === 'compose' ? 'font-bold' : ''; ?>">Compose</a>
    </div>

    <?php if ($action === 'compose'): ?>
        <form action="send_message.php" method="POST" class="bg-white p-6 rounded shadow-md border max-w-lg mx-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
            <h2 class="text-xl font-semibold mb-4">Send Message</h2>

            <div class="mb-4">
                <label for="receiver_department" class="block mb-1 font-medium">To Department</label>
                <select id="receiver_department" name="receiver_department" required
                    class="w-full h-10 border-b-2 border-gray-400 focus:border-blue-600 outline-none rounded">
                    <option value="">-- Select --</option>
                    <option value="nurse">Nurse</option>
                    <option value="doctor">Doctor</option>
                    <option value="admin">Admin</option>
                    <option value="radiology/pharmacy">Radiology/Pharmacy</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="subject" class="block mb-1 font-medium">Subject</label>
                <input id="subject" type="text" name="subject" required
                    class="w-full h-10 border-b-2 border-gray-400 focus:border-blue-600 outline-none rounded" />
            </div>

            <div class="mb-4">
                <label for="body" class="block mb-1 font-medium">Message</label>
                <textarea id="body" name="body" required rows="5"
                    class="w-full border-b-2 border-gray-400 focus:border-blue-600 outline-none rounded resize-y"></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-md font-semibold">
                Send
            </button>
        </form>

    <?php elseif ($action === 'view' && isset($_GET['message_id'])): ?>
        <?php
        $stmt = $conn->prepare("SELECT m.*, u.username AS sender_name FROM messages m JOIN users u ON m.sender_id = u.user_id WHERE m.message_id = ? AND m.receiver_department = ?");
        $stmt->execute([$_GET['message_id'], $department]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($message) {
            // Mark as read
            $stmt_update = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE message_id = ?");
            $stmt_update->execute([$_GET['message_id']]);
        }
        ?>
        <?php if ($message): ?>
            <article class="bg-white p-6 rounded shadow-md border max-w-3xl mx-auto">
                <h2 class="text-2xl font-semibold mb-2"><?php echo htmlspecialchars($message['subject']); ?></h2>
                <p class="text-gray-600 mb-1">From: <?php echo htmlspecialchars($message['sender_name']); ?></p>
                <p class="text-gray-500 mb-6 text-sm">Sent: <?php echo htmlspecialchars($message['sent_at']); ?></p>
                <div class="whitespace-pre-line"><?php echo nl2br(htmlspecialchars($message['body'])); ?></div>
                <div class="mt-6">
                    <a href="messages.php?action=inbox" class="text-blue-600 hover:underline">&larr; Back to Inbox</a>
                </div>
            </article>
        <?php else: ?>
            <p class="text-center text-red-600 font-semibold">Message not found or inaccessible.</p>
            <div class="mt-4 text-center">
                <a href="messages.php?action=inbox" class="text-blue-600 hover:underline">&larr; Back to Inbox</a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Inbox -->
        <?php
        $stmt = $conn->prepare("SELECT m.*, u.username AS sender_name FROM messages m JOIN users u ON m.sender_id = u.user_id WHERE m.receiver_department = ? ORDER BY m.sent_at DESC");
        $stmt->execute([$department]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <?php if (count($messages) > 0): ?>
            <table class="w-full border-collapse border border-gray-300 rounded-md overflow-hidden shadow-sm">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="border border-gray-300 p-2 text-left">From</th>
                        <th class="border border-gray-300 p-2 text-left">Subject</th>
                        <th class="border border-gray-300 p-2 text-left">Sent</th>
                        <th class="border border-gray-300 p-2 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                        <tr class="<?php echo !$msg['is_read'] ? 'bg-yellow-50 font-semibold' : ''; ?>">
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($msg['sender_name']); ?></td>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($msg['subject']); ?></td>
                            <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($msg['sent_at']); ?></td>
                            <td class="border border-gray-300 p-2">
                                <a href="messages.php?action=view&message_id=<?php echo $msg['message_id']; ?>" class="text-blue-600 hover:underline">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-600">No messages in your inbox.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>

</body>
</html>
