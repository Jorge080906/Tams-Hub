<?php
session_start();

// If already logged in, redirect
if (isset($_SESSION['email'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit;
    }
    if ($_SESSION['role'] === 'student') {
        header('Location: student_dashboard.php');
        exit;
    }
}

require_once __DIR__ . '/db_connect.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    // Validation
    if (empty($email) || empty($first_name) || empty($last_name) || 
        empty($student_number) || empty($password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@fit.edu.ph')) {
        $error_message = 'You must use a valid FEU Tech email address (@fit.edu.ph).';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/^[0-9]{9}$/', $student_number)) {
        $error_message = 'Student number must be 9 digits (e.g., 202412345)';
    } else {
        // Check if email already exists
        $check_query = 'SELECT email FROM users WHERE email = ?';
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error_message = 'This email is already registered.';
        } else {
            $check_stmt->close();

            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert_query = '
                INSERT INTO users (email, password, first_name, last_name, student_number, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ';

            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param(
                'ssssss',
                $email,
                $hashed_password,
                $first_name,
                $last_name,
                $student_number,
                $role
            );

            if ($insert_stmt->execute()) {
                $insert_stmt->close();
                header('Location: login.php?registered=1');
                exit;
            } else {
                $error_message = 'Something went wrong. Please try again.';
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// If there's an error, redirect back with error message
if (!empty($error_message)) {
    $_SESSION['registration_error'] = $error_message;
    header('Location: login.php?error=1');
    exit;
}
?>