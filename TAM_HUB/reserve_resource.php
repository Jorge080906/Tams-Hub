<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_email']) && !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Get the email from session
$user_email = $_SESSION['user_email'] ?? $_SESSION['email'] ?? '';

// Check if user is a student
if ($_SESSION['role'] !== 'student') {
    header("Location: admin_dashboard.php");
    exit();
}

require_once __DIR__ . '/db_connect.php';

$error_message = "";
$success_message = "";

// Get resource info if pre-selected
$resource_id = isset($_GET['resource_id']) ? (int)$_GET['resource_id'] : 0;

$resource_info = null;
if ($resource_id > 0) {
    $query = "SELECT * FROM resources WHERE id = ? AND status = 'Available' AND quantity > 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $resource_info = $result->fetch_assoc();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $resource_id = (int)$_POST['resource_id'];
    $start_datetime = $_POST['start_datetime'];
    $end_datetime = $_POST['end_datetime'];
    $purpose = trim($_POST['purpose']);

    // Set timezone for consistent comparisons (FEU Tech timezone)
    $tz = new DateTimeZone('Asia/Manila');

    // Validate and normalize datetime format
    $start_dt = DateTime::createFromFormat('Y-m-d\TH:i', $start_datetime, $tz);
    $end_dt = DateTime::createFromFormat('Y-m-d\TH:i', $end_datetime, $tz);
    $now = new DateTime('now', $tz);

    if (!$start_dt || !$end_dt) {
        $error_message = "Invalid date/time format.";
    }
    // Validate inputs
    elseif (empty($resource_id) || empty($start_datetime) || empty($end_datetime) || empty($purpose)) {
        $error_message = "All fields are required.";
    } elseif ($start_dt >= $end_dt) {
        $error_message = "End date/time must be after start date/time.";
    } elseif ($start_dt < $now) {
        $error_message = "Cannot reserve a past date/time.";
    } else {
        // Normalize datetime format for database (MySQL format: Y-m-d H:i:s)
        $start_db = $start_dt->format('Y-m-d H:i:s');
        $end_db = $end_dt->format('Y-m-d H:i:s');

        // Check if resource exists and is available
        $check_query = "SELECT id, quantity FROM resources WHERE id = ? AND status = 'Available' AND quantity > 0";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $resource_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows == 0) {
            $error_message = "Resource is not available for reservation.";
        } else {
            // Check for overlapping reservations (using database datetime format)
            $overlap_query = "SELECT id FROM reservations
                              WHERE resource_id = ?
                              AND status IN ('Pending', 'Approved')
                              AND ((start_datetime <= ? AND end_datetime > ?)
                                   OR (start_datetime < ? AND end_datetime >= ?)
                                   OR (start_datetime >= ? AND end_datetime <= ?))";
            $overlap_stmt = $conn->prepare($overlap_query);
            $overlap_stmt->bind_param("issssss", $resource_id, $end_db, $start_db,
                                      $start_db, $end_db, $start_db, $end_db);
            $overlap_stmt->execute();
            $overlap_result = $overlap_stmt->get_result();

            if ($overlap_result->num_rows > 0) {
                $error_message = "This resource is already reserved for the selected time slot.";
            } else {
                // Insert reservation
                $insert_query = "INSERT INTO reservations (user_email, resource_id, purpose, start_datetime, end_datetime)
                                 VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("sisss", $user_email, $resource_id, $purpose, $start_db, $end_db);

                if ($insert_stmt->execute()) {
                    $success_message = "Reservation submitted successfully! Waiting for admin approval.";
                    $resource_id = 0;
                } else {
                    $error_message = "Failed to submit reservation. Please try again.";
                }
                $insert_stmt->close();
            }
            $overlap_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get all available resources for dropdown
$resources_query = "SELECT * FROM resources WHERE status = 'Available' AND quantity > 0 ORDER BY category, name";
$resources_result = $conn->query($resources_query);

// Current datetime for min attribute (format: Y-m-d\TH:i)
$now = new DateTime();
$now->setTimezone(new DateTimeZone('Asia/Manila')); // FEU Tech timezone
$min_datetime = $now->format('Y-m-d\TH:i');

// Get user info
$user_query = "SELECT first_name, last_name FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

// Set current page for sidebar active state
$current_page = 'reserve';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tams-Hub | Reserve Resource</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="dashboardstyle.css" />
    <style>
        /* Additional styles for the reserve page */
        .form-panel {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(4, 78, 25, 0.14);
            padding: 24px;
            margin-bottom: 22px;
        }

        .form-panel h2 {
            color: #064814;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #eef1b4;
            padding-bottom: 12px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            color: #2c313a;
            margin-bottom: 5px;
            font-size: 14px;
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
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #d4a017;
            outline: none;
            background: #fff;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn-submit {
            background: #d4a017;
            color: #2c313a;
            border: none;
            padding: 12px 28px;
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
            padding: 12px 28px;
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

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
            flex-wrap: wrap;
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

        .resource-card {
            background: #fbfcf7;
            border: 1px solid rgba(12, 93, 31, 0.12);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
        }

        .resource-card h4 {
            margin: 0 0 4px;
            color: #064814;
        }

        .resource-card p {
            margin: 0;
            color: #7e8590;
            font-size: 13px;
            line-height: 1.5;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
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

<?php include __DIR__ . '/student_sidebar.php'; ?>

<main class="content-wrapper">
    <header class="topbar">
        <button class="menu-btn" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-title">Reserve a Resource</div>
        <div class="topbar-right">
            <i class="fa-regular fa-bell icon-btn"></i>
            <div class="user-chip"><i class="fa-solid fa-circle-user"></i><span><?php echo htmlspecialchars($full_name); ?></span></div>
            <a href="logout.php" style="color: #f7ef63; text-decoration: none; font-weight: 700; padding: 6px 14px; border: 1px solid #f7ef63; border-radius: 6px; transition: all 0.3s ease;" 
               onmouseover="this.style.background='#f7ef63'; this.style.color='#064814';" 
               onmouseout="this.style.background='transparent'; this.style.color='#f7ef63';">
                <i class="fa-solid fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="page-content">

        <!-- Alert Messages -->
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

        <!-- Main Content Grid -->
        <section class="grid-2">

            <!-- Left: Reservation Form -->
            <div class="form-panel">
                <h2><i class="fa-solid fa-calendar-plus" style="color: #d4a017;"></i> Make a Reservation</h2>

                <form method="POST" action="reserve_resource.php">
                    <div class="form-group">
                        <label for="resource_id"><i class="fa-solid fa-box"></i> Select Resource</label>
                        <select name="resource_id" id="resource_id" required>
                            <option value="">Choose a resource...</option>
                            <?php while ($res = $resources_result->fetch_assoc()): ?>
                                <option value="<?php echo $res['id']; ?>" 
                                        <?php echo ($resource_id == $res['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($res['name'] . ' - ' . $res['category'] . ' (Available: ' . $res['quantity'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($resources_result->num_rows == 0): ?>
                            <p style="color: #9d2f2f; font-size: 13px; margin-top: 5px;">No resources currently available.</p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="start_datetime"><i class="fa-solid fa-clock"></i> Start Date & Time</label>
                        <input type="datetime-local" name="start_datetime" id="start_datetime" required min="<?php echo $min_datetime; ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_datetime"><i class="fa-solid fa-clock"></i> End Date & Time</label>
                        <input type="datetime-local" name="end_datetime" id="end_datetime" required min="<?php echo $min_datetime; ?>">
                    </div>

                    <div class="form-group">
                        <label for="purpose"><i class="fa-solid fa-pen"></i> Purpose / Reason</label>
                        <textarea name="purpose" id="purpose" rows="3" placeholder="e.g., Project presentation, Lab activity, Research..." required></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Submit Reservation</button>
                        <a href="student_dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
                    </div>
                </form>
            </div>

            <!-- Right: Resource Info & Tips -->
            <div>
                <div class="form-panel">
                    <h3 style="color: #064814; margin-top: 0; border-bottom: 2px solid #eef1b4; padding-bottom: 10px;">
                        <i class="fa-solid fa-lightbulb" style="color: #d4a017;"></i> Reservation Tips
                    </h3>
                    <div style="font-size: 14px; color: #2c313a; line-height: 1.7;">
                        <p><i class="fa-solid fa-check-circle" style="color: #1e7a3c;"></i> <strong>Plan ahead</strong> - Book resources early to secure your preferred time.</p>
                        <p><i class="fa-solid fa-clock" style="color: #d4a017;"></i> <strong>Be punctual</strong> - Arrive on time for your scheduled reservation.</p>
                        <p><i class="fa-solid fa-rotate-left" style="color: #a5720b;"></i> <strong>Cancel if needed</strong> - You can cancel pending reservations from your dashboard.</p>
                        <p><i class="fa-solid fa-triangle-exclamation" style="color: #9d2f2f;"></i> <strong>No-shows</strong> - Repeated no-shows may result in restricted access.</p>
                    </div>
                </div>

                <div class="form-panel" style="background: #fbfcf7;">
                    <h3 style="color: #064814; margin-top: 0; border-bottom: 2px solid #eef1b4; padding-bottom: 10px;">
                        <i class="fa-solid fa-boxes-stacked" style="color: #1e7a3c;"></i> Currently Available
                    </h3>
                    <?php
                    $count_query = "SELECT COUNT(*) as total FROM resources WHERE status = 'Available' AND quantity > 0";
                    $count_result = $conn->query($count_query);
                    $available_count = $count_result->fetch_assoc()['total'];
                    ?>
                    <p style="font-size: 28px; font-weight: 800; color: #1e7a3c; margin: 10px 0;"><?php echo $available_count; ?></p>
                    <p style="color: #7e8590; font-size: 14px; margin: 0;">resources available for reservation</p>
                    
                    <hr style="border: none; border-top: 1px solid #eef0f2; margin: 15px 0;">
                    
                    <p style="font-size: 13px; color: #7e8590; margin: 0;">
                        <i class="fa-solid fa-info-circle"></i> 
                        Need help? Contact the Resource Center at <strong>resource@fit.edu.ph</strong>
                    </p>
                </div>
            </div>

        </section>

        <!-- Available Resources Table -->
        <section class="section-block">
            <div class="panel" style="background: #fff; border-radius: 18px; box-shadow: 0 8px 24px rgba(4, 78, 25, 0.14); padding: 18px;">
                <h3 class="section-title"><i class="fa-solid fa-list" style="color: #d4a017;"></i> All Available Resources</h3>
                <div class="table-wrap">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $list_query = "SELECT * FROM resources WHERE status = 'Available' AND quantity > 0 ORDER BY category, name";
                            $list_result = $conn->query($list_query);
                            
                            if ($list_result->num_rows > 0):
                                while ($item = $list_result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 40)) . (strlen($item['description'] ?? '') > 40 ? '...' : ''); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                </tr>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #7e8590; padding: 30px;">No resources available at the moment.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div>
</main>

<script src="dashboardscript.js"></script>
</body>
</html>