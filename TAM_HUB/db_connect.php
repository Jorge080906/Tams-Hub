<?php

$hostname = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "tam_hub";

$conn = mysqli_connect(
    $hostname,
    $dbuser,
    $dbpass,
    $dbname
);

if (mysqli_connect_errno()) {
    echo "Failed to connect to Database... " . mysqli_connect_error();
    exit();
}