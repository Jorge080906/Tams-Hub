<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_email']) && !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Get the email from session
$user_email = $_SESSION['user_email'] ?? $_SESSION['email'] ?? '';

// Check if user is an admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: student_dashboard.php");
    exit();
}

require_once __DIR__ . '/db_connect.php';

$error_message = "";
$success_message = "";

// Get admin user info
$user_query = "SELECT first_name, last_name FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

// Set current page for sidebar active state
$current_page = 'add_resource';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $specifications = trim($_POST['specifications']);
    $status = $_POST['status'];
    $quantity = (int)$_POST['quantity'];
    
    if (empty($name) || empty($category) || empty($quantity)) {
        $error_message = "Name, Category, and Quantity are required.";
    } else {
        $insert_query = "INSERT INTO resources (name, description, category, specifications, status, quantity) 
                         VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("sssssi", $name, $description, $category, $specifications, $status, $quantity);
        
        if ($stmt->execute()) {
            $success_message = "Resource added successfully!";
            // Clear form
            $name = $description = $category = $specifications = "";
            $status = 'Available';
            $quantity = 1;
        } else {
            $error_message = "Failed to add resource. Please try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tams-Hub | Add Resource</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="dashboardstyle_admin.css" />
    <style>
        /* Additional styles for forms */
        .form-panel {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(4, 78, 25, 0.14);
            padding: 28px 32px;
            max-width: 700px;
            margin: 0 auto;
        }

        .form-panel h2 {
            color: #064814;
            margin-top: 0;
            margin-bottom: 24px;
            border-bottom: 2px solid #eef1b4;
            padding-bottom: 14px;
            font-size: 24px;
        }

        .form-panel h2 i {
            color: #d4a017;
            margin-right: 10px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            color: #2c313a;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .form-group label i {
            color: #d4a017;
            width: 20px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s;
            background: #fafbf7;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #d4a017;
            outline: none;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(212, 160, 23, 0.15);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%232c313a' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .btn-submit {
            background: #d4a017;
            color: #2c313a;
            border: none;
            padding: 12px 32px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #bb8a13;
        }

        .btn-back {
            background: #2c313a;
            color: #fff;
            border: none;
            padding: 12px 32px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #1c2027;
        }

        .alert-banner {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .alert-success {
            background: #e5f6e8;
            color: #1e7a3c;
        }

        .alert-error {
            background: #fde7e7;
            color: #9d2f2f;
        }

        .page-content-centered {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 30px 20px;
            min-height: calc(100vh - 68px);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .form-panel {
                padding: 20px;
            }
            .form-actions {
                flex-direction: column;
            }
            .form-actions a,
            .form-actions button {
                width: 100%;
                justify-content: center;
            }
        }   
            
            .move-up {
                margin-top: -10px;
         }
    </style>
</head>
<body>

<?php include __DIR__ . '/admin_sidebar.php'; ?>

<main class="content-wrapper">
    <header class="topbar">
        <button class="menu-btn" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Add Resource</div>
        <div class="topbar-right">
            <i class="fa-regular fa-bell"></i>
            <div class="user-chip"><i class="fa-solid fa-circle-user"></i><span><?php echo htmlspecialchars($full_name); ?></span></div>
            <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="page-content-centered">

        <div class="form-panel">
            <h2><i class="fa-solid fa-square-plus"></i> Add New Resource</h2>

            <?php if (!empty($success_message)): ?>
                <div class="alert-banner alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert-banner alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_resource.php">
                <div class="form-group">
                    <label for="name"><i class="fa-solid fa-tag"></i> Name:</label>
                    <input type="text" id="name" name="name" placeholder="Enter resource name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category"><i class="fa-solid fa-list"></i> Category:</label>
                        <select id="category" name="category" required>
                            <option value="Hardware">Hardware</option>
                            <option value="Room">Room</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="quantity"><i class="fa-solid fa-hashtag"></i> Quantity:</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description"><i class="fa-solid fa-align-left"></i> Description:</label>
                    <textarea id="description" name="description" rows="3" placeholder="Enter resource description"></textarea>
                </div>

                <div class="form-group">
                    <label for="specifications"><i class="fa-solid fa-microchip"></i> Specifications:</label>
                    <textarea id="specifications" name="specifications" rows="3" placeholder="Enter technical specifications"></textarea>
                </div>

                <div class="form-group">
                    <label for="status"><i class="fa-solid fa-circle"></i> Status:</label>
                    <select id="status" name="status" required>
                        <option value="Available">Available</option>
                        <option value="Reserved">Reserved</option>
                        <option value="Out for Repair">Out for Repair</option>
                        <option value="Unavailable">Unavailable</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Add Resource</button>
                    <a href="admin_dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
                </div>
            </form>
        </div>

    </div>
</main>

<script src="dashboardscript_admin.js"></script>
</body>
</html>