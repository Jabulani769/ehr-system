<?php
$host = 'localhost';
$dbname = 'ehr-system';
$username = 'root';
$password = '';
$conn = '';

try{
    $conn = mysqli_connect($host, $username, $password, $dbname);
} 
catch(mysqli_connect){
    echo "could not connect to the database";
}
?>