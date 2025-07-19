<?php
ini_set('session.cookie_httponly', 1);
session_start();
include '../includes/db_connect.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'nurse') {
    header("Location: ../index.php?error=" . urlencode("Unauthorized access"));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    <div class="container mx-auto mt-10 px-4">
        <h1 class="text-2xl font-bold mb-4">Ward Messages</h1>
        <p>Placeholder for ward messages functionality.</p>
    </div>
</body>
</html>