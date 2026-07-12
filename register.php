<?php // register.php
session_start();

// Corrected redirect logic: send logged-in users to dashboards to avoid a redirect loop with login.php
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
        exit();
    }
}

// Fixed fatal typo: changed __DIE__ to standard PHP magic constant __DIR__
require_once __DIR__ . '/db_connect.php'; //commented out for testing purposes, uncomment when deploying.
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']); // Re-added extraction to match HTML form inputs and DB schema
    $last_name = trim($_POST['last_name']);   // Re-added extraction to match HTML form inputs and DB schema
    $student_number = trim($_POST['student_number']);
    $course = trim($_POST['course']);         // Re-added extraction to match HTML form inputs and DB schema
    $year_level = (int)$_POST['year_level'];  // Re-added extraction to match HTML form inputs and DB schema
    $contact_number = trim($_POST['contact_number']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role']; // 'student' or 'admin'

    // 1. Basic empty field validation
    if (empty($email) || empty($first_name) || empty($last_name) || 
        empty($student_number) || empty($course) || empty($password) || 
        empty($confirm_password) || empty($role)) {
        $error_message = "All fields are required.";
    } 
    // 2. Enforce valid FEU Tech email domain structure (Fixed undefined variable $username to $email)
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@fit.edu.ph')) {
        $error_message = "You must use a valid FEU Tech email address (@fit.edu.ph).";
    } 
    // 3. Confirm password uniformity
    elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } 
    // 4. Enforce basic password strength minimum
    elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } 
    // 5. Validate student number format (Updated regex to accept optional hyphens like 2024-12345 or 202412345)
    elseif (!preg_match('/^[0-9]{4}-?[0-9]{5}$/', $student_number)) {
        $error_message = "Student number must be 9 digits (e.g., 202412345 or 2024-12345)";
    } 
    else {
        // 6. Check if the username/email is already registered (Fixed query checking 'email' column instead of 'username')
        $check_query = "SELECT id FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($check_query)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error_message = "This email is already registered.";
                $stmt->close();
            } else {
                $stmt->close();
                
                // 7. Core Registration Logic: Hash and Insert
                // Using standard PASSWORD_DEFAULT (BCRYPT) algorithm
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $insert_query = "INSERT INTO users (email, password, first_name, 
                                last_name, student_number, course, year_level, contact_number, role) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = $conn->prepare($insert_query)) {
                    // Fixed bind_param type string: 9 variables mapped to 'ssssssiss'
                    $stmt->bind_param("ssssssiss", $email, $hashed_password, $first_name, $last_name, 
                                     $student_number, $course, $year_level, $contact_number, $role);
                    
                    if ($stmt->execute()) {
                        $stmt->close();
                        // Redirect to login page with a success trigger parameter (Changed to registered=1 to match login.php check)
                        header("Location: login.php?registered=1");
                        exit();
                    } else {
                        $error_message = "Something went wrong on our end. Please try again.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TAM-Hub - Create Account</title>
</head>
<body>

    <h2>TAM-Hub Registration</h2>

    <?php if (!empty($error_message)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div>
            <label for="email">FEU Tech Email Address:</label>
            <input type="email" id="email" name="email" placeholder="name@fit.edu.ph" required>
        </div>
        
        <div>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required>
        </div>
        
        <div>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required>
        </div>
        
        <div>
            <label for="student_number">Student Number (YYYY-XXXXX):</label>
            <input type="text" id="student_number" name="student_number" placeholder="2024-12345" required>
        </div>
        
        <div>
            <label for="course">Course/Program:</label>
            <input type="text" id="course" name="course" placeholder="e.g., BSIT, BSCS" required>
        </div>
        
        <div>
            <label for="year_level">Year Level:</label>
            <select id="year_level" name="year_level" required>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
            </select>
        </div>
        
        <div>
            <label for="contact_number">Contact Number:</label>
            <input type="text" id="contact_number" name="contact_number" placeholder="09171234567">
        </div>
        
        <div>
            <label for="role">Account Type / Role:</label>
            <select id="role" name="role" required>
                <option value="">Select</option>
                <option value="student">Student</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <div>
            <label for="password">Password (Min. 8 characters):</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div>
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <button type="submit">Register Account</button>
    </form>

    <br>
    <p>Already have an account? <a href="login.php">Back to Login</a></p>

</body>
</html>