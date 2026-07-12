<?php
session_start();

require_once __DIR__ . '/db_connect.php';

$error_message = '';
$success_message = '';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit;
    }

    if ($_SESSION['role'] === 'student') {
        header('Location: student_dashboard.php');
        exit;
    }
}

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
            SELECT id, email, password, first_name, last_name, role
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

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];

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
        <div class="form-container sign-up">
            <form id="signupForm" action="register.php" method="POST">
                <h1>Create Account</h1>

                <div id="signup-message" class="form-message"></div>

                <div class="social-icons">
                    <a href="276871961319-qt3q8mokrshal5spm7nlibs0q5clca7p.apps.googleusercontent.com" id="google-signup" class="icon"><i class="fa-brands fa-google"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
                <span>or use your email for registration</span>
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="name@fit.edu.ph" required>
                <input type="text" name="student_number" placeholder="Student Number" required>
                <input type="text" name="course" placeholder="Course (e.g., BSIT, BSCS)" required>
                <input type="text" name="contact_number" placeholder="Contact Number">
                <input type="password" name="password" placeholder="Password (min. 8 characters)" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <input type="hidden" name="year_level" value="1">
                <input type="hidden" name="role" value="student">
                <button type="submit">Sign Up</button>
            </form>
        </div>
        <div class="form-container sign-in">
            <form id="loginForm" action="login.php" method="POST">
                <h1>Sign In</h1>

                <div id="login-message" class="form-message"></div>

                <div class="social-icons">
                    <a href="276871961319-qt3q8mokrshal5spm7nlibs0q5clca7p.apps.googleusercontent.com" id="google-signin" class="icon"><i class="fa-brands fa-google"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
                <span>or use your email password</span>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <a href="#">Forget Your Password?</a>
                <button type="submit">Sign In</button>
            </form>
        </div>
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

    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="loginscript.js"></script>
    <script src="authscript.js"></script>
    <script>
        window.onload = function () {
            google.accounts.id.initialize({
                client_id: "276871961319-qt3q8mokrshal5spm7nlibs0q5clca7p.apps.googleusercontent.com",
                callback: handleCredentialResponse
            });
        };

        function handleCredentialResponse(response) {
            console.log("User info token:", response.credential);
        }
    </script>
</body>

</html>