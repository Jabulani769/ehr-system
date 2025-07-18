<?php
session_start();
// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
include 'pages/db_connect.php';

// 9ebff7d5b21cc09a151fcc958cdec66f
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">    
    <title>Mulanje Mission Hospital</title>
</head>
<body>
    <!-- for the header -->
    <section class="self-center h-[10vh] bg-slate-400">

    </section>

    <!-- for the login form -->
    <section class="mt-20 w-full max-w-md m-auto">
        <form action="pages/login.php" method="POST" class="p-6 bg-white border border-gray-300 rounded-xl shadow-md">
            <h1 class="text-[28px] text-center font-semibold">MMH EHR</h1>
            <!-- div for displaying error messages -->
            <div class="w-full h-10">
                <p id="error"></p>
            </div>

            <div class="relative mb-4">
                <div class="absolute insert-y-0 left-0 pointer-none flex self-center pl-2">
                    <i class="fa-solid fa-user text-gray-600 text-[20px]"></i>
                </div>
                <input type="text" id="employeeID" name="employeeID"  placeholder="Employee ID" class="bg-transparent w-full pl-10 h-10 border-b-2 border-gray-400 outline-none focus:border-b-blue-700 transition-colors duration-200 placeholder:text-[black] required">
            </div>

            <div class="relative">
                <div class="absolute insert-y-0 left-0 pointer-none flex self-center pl-2">
                    <i class="fa-solid fa-lock text-gray-600 text-[20px]"></i>
                </div>
                <input type="password" id="password" name="password" placeholder="Password" class="bg-transparent w-full pl-10 h-10 border-b-2 border-gray-400  outline-none focus:border-b-blue-700 transition-colors duration-200 placeholder:text-[black] required">
            </div>

            <div class="w-fit float-right mt-2">
                <a href="#" class="text-blue-600 hover:underline">Forgot Password?</a>
            </div>

            <button class="w-full h-10 border-2 border-blue-500 bg-blue-500 hover:bg-blue-600 transition-colors duration-200 rounded-md my-4">Login</button>
            
            <div class="w-fit m-auto">
                <a href="#">Dont have an account? <span><a href="#" class="underline">contact IT department</a></span></a>
            </div>
        </form>
    </section>
</body>
</html>