<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">    
    <title>MMH|Nurse Dashboard</title>
</head>
<body class="bg-gray-100">

    <?php include 'header.php';?>

    <!-- section for displaying critical patient information -->
    <section class="h-[10vh] pl-20 mt-6"> <!--the section should only show only a few critical patinets if their more it should show a view all button that will be linked to the vitals.php-->
        <div class="border-l-2 border-red-700">
            <h1 class="pl-2 font-semibold text-red-700"><i class="fa-solid fa-triangle-exclamation"></i> Critical Patient (1)</h1>
            <p class="pl-2 font-semibold">Alex Kamba</p>
            <p class="pl-2"><!--critical vital reading--></p>
        </div>
    </section>

    <section class="flex flex-wrap justify-between gap-4 px-20 mt-14">
        <!-- patient vitals div routing buttons [record and monitor patients vitals] -->
        <div class="w-[30%] bg-purple p-6 shadow-xl rounded-md bg-white">
            <div class="flex justify-between items-center">
                <h1 class="text-[20px] text-teal-500"><i class="fa-solid fa-heart-pulse"></i> Patient Vitals</h1>
                <div class="bg-teal-200 px-2 rounded-xl">
                    <!-- should display number of patients with critical vitals from the database -->
                    <p class="text-[14px]">1 Critical</p>
                </div>
            </div>

            <div class="mt-4">
                <button class="w-full h-10 mt-2  bg-teal-500 hover:bg-teal-700 text-white text-center transition-colors duration-200 rounded-md">
                    <i class="fa-solid fa-plus pr-2"></i> New Reading
                </button>
                <button class="w-full h-10 mt-2 text-teal-500 border-2 border-teal-400 rounded-md">
                    View History
                </button>
            </div>
        </div>

        <!-- patient drug admistration routing buttons div [mamage medication admistration] -->
        <div class="w-[30%] bg-purple p-6 shadow-xl rounded-md border-green-300 bg-white">
            <h1 class="text-[20px] text-blue-700"><i class="fa-solid fa-pills"></i> Medications</h1>

            <div class="mt-4">
                <button class="w-full h-10 mt-2 bg-blue-500 hover:bg-blue-700 text-white text-center transition-colors duration-200 rounded-md">
                    <i class="fa-solid fa-syringe pr-2"></i> Administer Now
                </button>
                <button class="w-full h-10 mt-2 text-blue-500 border-2 border-blue-400 transition-colors duration-200 rounded-md">
                    Medication Schedule
                </button>
            </div>
        </div>

        <!-- patient magement pages routinh buttons [manage patients and assigments] -->
        <div class="w-[30%] bg-purple p-6 shadow-xl rounded-md border-green-300 bg-white">
                <h1 class="text-[20px] text-purple-600"><i class="fa-solid fa-bed-pulse"></i> Ward Management</h1>

            <div class="mt-4 flex gap-2">
                <button class="w-full h-10 mt-2 bg-purple-500 hover:bg-purple-700 text-white transition-colors duration-200 rounded-md">
                    Patient List
                </button>
                <button class="w-full h-10 mt-2 bg-purple-200 hover:bg-purple-300 text-purple-500 transition-colors duration-200 rounded-md">
                    Bed Reassignment
                </button>
            </div>
            <div class="flex gap-2">
                <button class="w-full h-10 mt-2 border-2 border-purple-500 text-purple-500 transition-colors duration-200 rounded-md">
                    New Admission
                </button>
                <button class="w-full h-10 mt-2 border-2 border-purple-500 text-purple-500 transition-colors duration-200 rounded-md">
                    Discharge
                </button>
            </div>
        </div>
    </section>

    <section class="flex flex-wrap justify-center gap-10 self-center mt-14">
        <div class="bg-green-200 px-2 w-[20%] text-green-900 rounded-md text-center">
            <i class="fa-solid fa-message"></i> 
            <p>Ward Messages</p>
        </div>

        <div class=" bg-blue-200 px-2 w-[20%] text-blue-500 rounded-md text-center">
            <i class="fa-solid fa-file"></i> 
            <p>Shift Report</p>
        </div>

        <div class="bg-red-200 px-2 w-[20%] text-red-500 rounded-md text-center">
            <i class="fa-solid fa-truck-medical"></i> 
            <p>Emergency</p>
        </div>

        <div class="bg-gray-200 px-2 w-[20%] text-gray-500 rounded-md text-center">
            <i class="fa-solid fa-flask-vial"></i> 
            <p>Lab/Radio Results</p>
        </div>
    </section>
</body>
</html>