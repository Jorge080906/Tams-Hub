<?php
session_start();

require_once __DIR__ . '/db_connect.php';

$error_message = '';
$success_message = '';

// If user is already logged in, redirect them
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

// Check for registration success
if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success_message = 'Registration successful! You can now log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        $query = '
            SELECT email, password, first_name, last_name, role
            FROM users
            WHERE email = ?
            LIMIT 1
        ';

        $stmt = $conn->prepare($query);

        if (!$stmt) {
            $error_message = 'A server error occurred.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_email'] = $user['email'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];

                $update_query = "UPDATE users SET last_login = NOW() WHERE email = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param('s', $email);
                $update_stmt->execute();
                $update_stmt->close();

                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                    exit;
                }

                header('Location: student_dashboard.php');
                exit;
            }

            $error_message = 'Invalid email or password.';
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="loginstyle.css">
    <title>Login Page</title>
</head>

<body>

    <div class="container" id="container">
        <!-- SIGN UP FORM -->
        <div class="form-container sign-up">
            <form id="signupForm" action="register.php" method="POST">
                <h1>Create Account</h1>

                <?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
                    <div class="form-message error">Registration failed. Please check your inputs.</div>
                <?php endif; ?>

                <div class="social-icons">
                    <a href="#" class="icon"><i class="fa-brands fa-google"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
                <span>or use your email for registration</span>
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="name@fit.edu.ph" required>
                <input type="text" name="student_number" placeholder="Student Number (9 digits)" required>
                <input type="text" name="course" placeholder="Course (e.g., BSIT, BSCS)" required>
                <input type="text" name="contact_number" placeholder="Contact Number">
                <input type="password" name="password" placeholder="Password (min. 8 characters)" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <input type="hidden" name="year_level" value="1">
                <input type="hidden" name="role" value="student">
                <button type="submit">Sign Up</button>
            </form>
        </div>

        <!-- SIGN IN FORM -->
        <div class="form-container sign-in">
            <form id="loginForm" action="login.php" method="POST">
                <h1>Sign In</h1>

                <!-- Display PHP error/success messages -->
                <?php if (!empty($error_message)): ?>
                    <div class="form-message error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="form-message success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <div class="social-icons">
                    <a href="#" class="icon"><i class="fa-brands fa-google"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
                <span>or use your email and password</span>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <a href="#">Forgot Your Password?</a>
                <button type="submit">Sign In</button>
            </form>
        </div>

        <!-- TOGGLE CONTAINER -->
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <img src="FEU.webp" alt="FEU Logo" class="logo">
                    <h1>Log In</h1>
                    <p>Enter your personal details to use all of site features</p>
                    <button class="hidden" id="signin">Sign In</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <img src="FEU.webp" alt="FEU Logo" class="logo">
                    <h1>Register</h1>
                    <p>To keep connected with us please register with your personal information</p>
                    <button class="hidden" id="signup">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <script src="loginscript.js"></script>
    <script>
        // Auto-show registration form if there was a registration error
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('error') === '1') {
                document.getElementById('container').classList.add('active');
            }
            if (urlParams.get('registered') === '1') {
                // Show success message on login form
                const container = document.getElementById('container');
                container.classList.remove('active');
            }
        };
    </script>
</body>

</html>
