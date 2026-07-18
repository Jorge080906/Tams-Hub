<?php
session_start();

// Check if user is logged in
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

$success_message = "";
$error_message = "";

// Handle approve reservation
if (isset($_GET['approve'])) {
    $reservation_id = (int)$_GET['approve'];
    $update_query = "UPDATE reservations SET status = 'Approved', updated_at = NOW() WHERE id = ? AND status = 'Pending'";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $reservation_id);
    if ($stmt->execute()) {
        $success_message = "Reservation approved successfully.";
    } else {
        $error_message = "Failed to approve reservation.";
    }
    $stmt->close();
}

// Handle reject reservation
if (isset($_GET['reject'])) {
    $reservation_id = (int)$_GET['reject'];
    $update_query = "UPDATE reservations SET status = 'Rejected', updated_at = NOW() WHERE id = ? AND status = 'Pending'";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $reservation_id);
    if ($stmt->execute()) {
        $success_message = "Reservation rejected.";
    } else {
        $error_message = "Failed to reject reservation.";
    }
    $stmt->close();
}

// Handle complete reservation
if (isset($_GET['complete'])) {
    $reservation_id = (int)$_GET['complete'];
    $update_query = "UPDATE reservations SET status = 'Completed', updated_at = NOW() WHERE id = ? AND status = 'Approved'";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $reservation_id);
    if ($stmt->execute()) {
        $success_message = "Reservation marked as completed.";
    } else {
        $error_message = "Failed to complete reservation.";
    }
    $stmt->close();
}

// Handle resource deletion
if (isset($_GET['delete_resource'])) {
    $resource_id = (int)$_GET['delete_resource'];
    $current_section = $_GET['section'] ?? 'resources';

    $check_query = "SELECT id FROM reservations WHERE resource_id = ? AND status IN ('Pending', 'Approved')";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $resource_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $check_stmt->close();
        $error_message = "Cannot delete resource with active reservations.";
    } else {
        $check_stmt->close();
        $delete_query = "DELETE FROM resources WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $resource_id);
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            // PRG redirect to prevent form resubmission on refresh
            header("Location: admin_dashboard.php?section=" . urlencode($current_section) . "&deleted=1");
            exit();
        } else {
            $delete_stmt->close();
            $error_message = "Failed to delete resource.";
        }
    }
}

// Check for success redirect from delete
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success_message = "Resource deleted successfully.";
}

// Check for success redirect from admin creation
if (isset($_GET['created']) && $_GET['created'] == 'admin') {
    $success_message = "Admin account created successfully.";
}

// Check for success redirect from student creation
if (isset($_GET['created']) && $_GET['created'] == 'student') {
    $success_message = "Student account created successfully.";
}

// Handle resource status update
if (isset($_GET['update_status']) && isset($_GET['new_status'])) {
    $resource_id = (int)$_GET['update_status'];
    $new_status = $_GET['new_status'];
    $allowed_status = ['Available', 'Reserved', 'Out for Repair', 'Unavailable'];

    if (in_array($new_status, $allowed_status)) {
        $update_query = "UPDATE resources SET status = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $new_status, $resource_id);
        if ($update_stmt->execute()) {
            $success_message = "Resource status updated successfully.";
        } else {
            $error_message = "Failed to update resource status.";
        }
        $update_stmt->close();
    }
}

// Handle add student account
if (isset($_POST['add_student'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($first_name) || empty($last_name) || empty($student_number) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@fit.edu.ph')) {
        $error_message = "You must use a valid FEU Tech email address (@fit.edu.ph).";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^[0-9]{9}$/', $student_number)) {
        $error_message = "Student number must be 9 digits (e.g., 202412345).";
    } else {
        $check_query = "SELECT email FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error_message = "This email is already registered.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (email, password, first_name, last_name, student_number, role) VALUES (?, ?, ?, ?, ?, 'student')";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssss", $email, $hashed_password, $first_name, $last_name, $student_number);
            if ($insert_stmt->execute()) {
                $insert_stmt->close();
                // PRG redirect to prevent form resubmission on refresh
                header("Location: admin_dashboard.php?section=users&created=student");
                exit();
            } else {
                $error_message = "Failed to create student account.";
                $insert_stmt->close();
            }
        }
    }
}

// Handle add admin account
if (isset($_POST['add_admin'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($first_name) || empty($last_name) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@fit.edu.ph')) {
        $error_message = "You must use a valid FEU Tech email address (@fit.edu.ph).";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        $check_query = "SELECT email FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error_message = "This email is already registered.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Generate unique 9-digit student_number for admin
            $admin_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
            $admin_count_result = $conn->query($admin_count_query);
            $admin_count = $admin_count_result->fetch_assoc()['count'];
            $student_number = str_pad($admin_count + 1, 9, '0', STR_PAD_LEFT); // e.g., 000000001

            $insert_query = "INSERT INTO users (email, password, first_name, last_name, student_number, role) VALUES (?, ?, ?, ?, ?, 'admin')";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssss", $email, $hashed_password, $first_name, $last_name, $student_number);
            if ($insert_stmt->execute()) {
                $insert_stmt->close();
                // PRG redirect to prevent form resubmission on refresh
                header("Location: admin_dashboard.php?section=users&created=admin");
                exit();
            } else {
                $error_message = "Failed to create admin account.";
                $insert_stmt->close();
            }
        }
    }
}

// Handle upgrade student to admin
if (isset($_POST['upgrade_to_admin'])) {
    $target_email = trim($_POST['user_email'] ?? '');

    if (empty($target_email)) {
        $error_message = "User email is required.";
    } else {
        $check_query = "SELECT email, role FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $target_email);
        $check_stmt->execute();
        $user = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if (!$user) {
            $error_message = "User not found.";
        } elseif ($user['role'] === 'admin') {
            $error_message = "User is already an admin.";
        } else {
            $update_query = "UPDATE users SET role = 'admin' WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("s", $target_email);
            if ($update_stmt->execute()) {
                $success_message = "User upgraded to admin successfully.";
            } else {
                $error_message = "Failed to upgrade user.";
            }
            $update_stmt->close();
        }
    }
}

// Handle downgrade admin to student
if (isset($_POST['downgrade_to_student'])) {
    $target_email = trim($_POST['user_email'] ?? '');
    $current_admin_email = $user_email; // From session

    if (empty($target_email)) {
        $error_message = "User email is required.";
    } else {
        $check_query = "SELECT email, role FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $target_email);
        $check_stmt->execute();
        $user = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if (!$user) {
            $error_message = "User not found.";
        } elseif ($user['role'] === 'student') {
            $error_message = "User is already a student.";
        } elseif ($user['email'] === $current_admin_email) {
            $error_message = "You cannot downgrade your own account.";
        } else {
            $update_query = "UPDATE users SET role = 'student', student_number = IFNULL(student_number, '') WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("s", $target_email);
            if ($update_stmt->execute()) {
                $success_message = "Admin downgraded to student successfully.";
            } else {
                $error_message = "Failed to downgrade user.";
            }
            $update_stmt->close();
        }
    }
}

// Get admin user info
$user_query = "SELECT email, first_name, last_name, role FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

// Get all users for management table
$users_query = "SELECT email, first_name, last_name, student_number, role, created_at, last_login FROM users ORDER BY role DESC, created_at DESC";
$users_result = $conn->query($users_query);

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM resources) as total_resources,
    (SELECT COUNT(*) FROM reservations WHERE status = 'Pending') as pending_requests,
    (SELECT COUNT(*) FROM reservations WHERE status = 'Approved') as approved_today,
    (SELECT COUNT(*) FROM reservations WHERE status IN ('Rejected', 'Cancelled')) as rejected_cancelled,
    (SELECT COUNT(*) FROM reservations) as total_reservations";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get pending reservations for review queue
$pending_query = "SELECT r.*, u.first_name, u.last_name, u.student_number, res.name as resource_name, res.category 
                  FROM reservations r 
                  JOIN users u ON r.user_email = u.email 
                  JOIN resources res ON r.resource_id = res.id 
                  WHERE r.status = 'Pending' 
                  ORDER BY r.created_at ASC LIMIT 8";
$pending_reservations = $conn->query($pending_query);

// Get all reservations for the table
$all_reservations_query = "SELECT r.*, u.first_name, u.last_name, u.student_number, res.name as resource_name 
                           FROM reservations r 
                           JOIN users u ON r.user_email = u.email 
                           JOIN resources res ON r.resource_id = res.id 
                           ORDER BY r.created_at DESC LIMIT 15";
$all_reservations = $conn->query($all_reservations_query);

// Get resources for inventory
$resources_query = "SELECT * FROM resources ORDER BY category, name";
$resources_result = $conn->query($resources_query);

// Get recent activity
$recent_query = "SELECT r.*, u.first_name, u.last_name, res.name as resource_name 
                 FROM reservations r 
                 JOIN users u ON r.user_email = u.email 
                 JOIN resources res ON r.resource_id = res.id 
                 ORDER BY r.updated_at DESC LIMIT 5";
$recent_activity = $conn->query($recent_query);

// Get resource counts for stats
$resource_stats_query = "SELECT
    SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN status = 'Reserved' THEN 1 ELSE 0 END) as reserved,
    SUM(CASE WHEN status = 'Out for Repair' THEN 1 ELSE 0 END) as repair,
    SUM(CASE WHEN status = 'Unavailable' THEN 1 ELSE 0 END) as unavailable
    FROM resources";
$resource_stats = $conn->query($resource_stats_query)->fetch_assoc();

// Set current page for sidebar active state
$current_section = $_GET['section'] ?? 'dashboard';

// Map sections to sidebar navigation items
$section_to_page = [
    'dashboard' => 'dashboard',
    'reservations' => 'reservations',
    'users' => 'users',
    'resources' => 'resources',
    'reports' => 'reports'
];

$current_page = $section_to_page[$current_section] ?? 'dashboard';

$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tams-Hub | Admin Resource Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="dashboardstyle_admin.css" />
    <style>
    /* Admin-specific styles that extend dashboardstyle_admin.css */
    .action-btn-primary { background: var(--accent-primary); color: var(--text-white); }
    .action-btn-warning { background: var(--accent-gold); color: var(--text-primary); }
    .action-btn:hover { filter: brightness(1.1); }
    .inline-form { display: inline; }
    .text-center-muted { text-align: center; color: var(--text-muted); padding: 40px; }
    .btn-secondary { background: var(--text-secondary); color: var(--text-white); border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background 0.2s; }
    .btn-secondary:hover { background: #334155; }
    .btn-secondary-admin { background: #6a4c93; color: white; }
    .btn-secondary-admin:hover { background: #5a3d7a; }
    .empty-state-cell { text-align: center; color: var(--text-muted); padding: 40px; }
    .link-primary { color: var(--accent-primary); text-decoration: none; font-weight: 500; }
    .link-primary:hover { text-decoration: underline; }
    .text-muted { color: var(--text-muted); font-size: 13px; }
    .move-up { margin-top: -10px; }
    .action-group { display: flex; gap: 6px; }
    .section-title { margin: 0 0 14px; font-size: 16px; font-weight: 600; color: #1e293b; }
    .panel { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; }
    .panel-table { padding: 0; overflow: hidden; }
    .quick-actions { display: flex; flex-direction: column; gap: 8px; }
    .quick-actions .request-btn { width: 100%; }
    .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .profile-label { display: block; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
    .profile-value { display: block; color: #1e293b; font-weight: 600; font-size: 14px; }
    .stats-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .stat-card { background: #fff; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 14px; border: 1px solid #e2e8f0; transition: box-shadow 0.2s; }
    .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; color: #fff; }
    .icon-blue { background: #3b82f6; }
    .icon-green { background: #10b981; }
    .icon-purple { background: #8b5cf6; }
    .icon-red { background: #ef4444; }
    .stat-info { display: flex; flex-direction: column; }
    .stat-label { font-size: 12px; color: #64748b; margin-bottom: 2px; }
    .stat-value { font-size: 22px; font-weight: 700; color: #0f172a; }
    .dashboard-columns { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; margin-bottom: 24px; align-items: start; }
    .side-column { display: flex; flex-direction: column; gap: 20px; }
    .reserve-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #10b981; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; cursor: pointer; transition: background 0.2s; }
    .reserve-btn:hover { background: #059669; }
    .request-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #10b981; color: #fff; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; transition: background 0.2s, transform 0.1s; }
    .request-btn:hover { background: #059669; transform: translateY(-1px); }
    .request-btn-secondary { background: #475569; }
    .request-btn-secondary:hover { background: #334155; }
    .alert-banner { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 20px; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    @media (max-width: 1024px) { .stats-grid-4 { grid-template-columns: repeat(2, 1fr); } .dashboard-columns { grid-template-columns: 1fr; } .profile-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 768px) { .stats-grid-4 { grid-template-columns: 1fr; } .profile-grid { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; } }
    .section-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
    .section-actions { display: flex; gap: 12px; }
    .btn-secondary-admin { background: #6a4c93; color: white; }
    .btn-secondary-admin:hover { background: #5a3d7a; }
    .empty-state-cell { text-align: center; color: #8a8f98; padding: 40px; }
    .link-primary { color: #10b981; text-decoration: none; font-weight: 500; }
    .link-primary:hover { text-decoration: underline; }
    .text-muted { color: #8a8f98; font-size: 13px; }
    .move-up { margin-top: -10px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/admin_sidebar.php'; ?>

<main class="content-wrapper">
        <header class="topbar">
            <button class="menu-btn" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>
            <div class="topbar-title">Admin Resource Center</div>
            <div class="topbar-right">
                <i class="fa-regular fa-bell"></i>
                <div class="user-chip"><i class="fa-solid fa-circle-user"></i><span><?php echo htmlspecialchars($full_name); ?></span></div>
                <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="page-content">
            
            <!-- Alert Banner -->
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

            <?php if ($current_section === 'dashboard' || $current_section === ''): ?>
            <!-- Stats Grid -->
            <section class="stats-grid stats-grid-4">
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><i class="fa-solid fa-clock"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Pending Approvals</span>
                        <span class="stat-value"><?php echo $stats['pending_requests']; ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-green"><i class="fa-solid fa-check"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Approved Today</span>
                        <span class="stat-value"><?php echo $stats['approved_today']; ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-purple"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Total Resources</span>
                        <span class="stat-value"><?php echo $stats['total_resources']; ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-red"><i class="fa-solid fa-user-slash"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">Rejected / Cancelled</span>
                        <span class="stat-value"><?php echo $stats['rejected_cancelled']; ?></span>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($current_section === 'reservations'): ?>
            <!-- Dashboard Columns -->
            <section class="dashboard-columns" id="reservations">
                <!-- Left: Reservation Review Queue -->
                <div class="panel">
                    <h3 class="section-title">Reservation Review Queue</h3>
                    <?php if ($pending_reservations->num_rows > 0): ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th>Student</th>
                                    <th>Date/Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($res = $pending_reservations->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($res['resource_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($res['start_datetime'])); ?><br>
                                            <small><?php echo date('h:i A', strtotime($res['start_datetime'])); ?> - <?php echo date('h:i A', strtotime($res['end_datetime'])); ?></small>
                                        </td>
                                        <td><span class="status-tag status-pending">Pending</span></td>
                                        <td>
                                            <div class="action-group">
                                                <a href="?approve=<?php echo $res['id']; ?>" onclick="return confirm('Approve this reservation?');" class="action-btn action-btn-primary">Approve</a>
                                                <a href="?reject=<?php echo $res['id']; ?>" onclick="return confirm('Reject this reservation?');" class="action-btn delete">Reject</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <p class="empty-state-title">No Pending Requests</p>
                            <p class="empty-state-desc">All reservation requests have been processed.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right: Quick Actions + Profile -->
                <div class="side-column">
                    <div class="panel">
                        <h3 class="section-title">Quick Actions</h3>
                        <div class="quick-actions">
                            <a href="add_resource.php" class="request-btn"><i class="fa-solid fa-plus"></i> Add Resource</a>
                            <a href="admin_schedule.php" class="request-btn request-btn-secondary"><i class="fa-solid fa-calendar-days"></i> Manage Schedules</a>
                            <a href="admin_schedule_approvals.php" class="request-btn request-btn-secondary"><i class="fa-solid fa-calendar-check"></i> Schedule Approvals</a>
                        </div>
                    </div>

                    <div class="panel">
                        <h3 class="section-title">Admin Snapshot</h3>
                        <div class="profile-grid">
                            <div>
                                <span class="profile-label">Total Students</span>
                                <span class="profile-value"><?php echo $stats['total_students']; ?></span>
                            </div>
                            <div>
                                <span class="profile-label">Available Resources</span>
                                <span class="profile-value"><?php echo $resource_stats['available'] ?? 0; ?></span>
                            </div>
                            <div>
                                <span class="profile-label">Resource Types</span>
                                <span class="profile-value"><?php
                                    $type_query = "SELECT COUNT(DISTINCT category) as count FROM resources";
                                    $type_result = $conn->query($type_query);
                                    echo $type_result->fetch_assoc()['count'];
                                ?></span>
                            </div>
                            <div>
                                <span class="profile-label">Pending Approvals</span>
                                <span class="profile-value" style="color: #a5720b;"><?php echo $stats['pending_requests']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($current_section === 'resources'): ?>
            <!-- Resource Inventory -->
            <section class="section-block" id="resources">
                <div class="panel panel-table">
                    <div class="section-header">
                        <h3 class="section-title">Resource Inventory</h3>
                        <a href="add_resource.php" class="request-btn"><i class="fa-solid fa-plus"></i> Add Resource</a>
                    </div>
                    <?php if ($resources_result->num_rows > 0): ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Available</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($resource = $resources_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($resource['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($resource['category']); ?></td>
                                        <td><?php echo $resource['quantity']; ?></td>
                                        <td><span class="status-tag status-<?php echo strtolower(str_replace(' ', '-', $resource['status'])); ?>"><?php echo $resource['status']; ?></span></td>
                                        <td>
                                            <a href="edit_resource.php?id=<?php echo $resource['id']; ?>" class="action-btn edit"><i class="fa-solid fa-pen"></i> Edit</a>
                                            <a href="?section=<?php echo urlencode($current_section); ?>&delete_resource=<?php echo $resource['id']; ?>" onclick="return confirm('Delete this resource?');" class="action-btn delete"><i class="fa-solid fa-trash"></i> Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <table class="requests-table">
                            <tbody>
                                <tr>
                                    <td colspan="5" class="empty-state-cell">No resources found. <a href="add_resource.php" class="link-primary">Add your first resource</a></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($current_section === 'reports'): ?>
            <!-- Recent Activity -->
            <section class="section-block" id="reports">
                <div class="panel">
                    <h3 class="section-title">Recent Activity</h3>
                    <?php if ($recent_activity->num_rows > 0): ?>
                        <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                            <div class="mini-card" style="margin-bottom: 12px;">
                                <h4><?php echo date('M d, Y h:i A', strtotime($activity['updated_at'])); ?></h4>
                                <p>
                                    <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                    - <?php echo htmlspecialchars($activity['resource_name']); ?>
                                    <?php if (!empty($activity['purpose'])): ?>
                                        <br><small class="text-muted">Purpose: <?php echo htmlspecialchars(substr($activity['purpose'], 0, 50)) . (strlen($activity['purpose']) > 50 ? '...' : ''); ?></small>
                                    <?php endif; ?>
                                </p>
                                <span class="status-tag status-<?php echo strtolower($activity['status']); ?>">
                                    <?php echo $activity['status']; ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="mini-card">
                            <p>No recent activity.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h3 class="section-title">Daily Summary</h3>
                    <div class="profile-grid">
                        <div>
                            <span class="profile-label">Total Reservations</span>
                            <span class="profile-value"><?php echo $stats['total_reservations']; ?></span>
                        </div>
                        <div>
                            <span class="profile-label">Pending</span>
                            <span class="profile-value" style="color: #a5720b;"><?php echo $stats['pending_requests']; ?></span>
                        </div>
                        <div>
                            <span class="profile-label">Approved</span>
                            <span class="profile-value" style="color: #1e7a3c;"><?php echo $stats['approved_today']; ?></span>
                        </div>
                        <div>
                            <span class="profile-label">Rejected/Cancelled</span>
                            <span class="profile-value" style="color: #9d2f2f;"><?php echo $stats['rejected_cancelled']; ?></span>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($current_section === 'users'): ?>
            <!-- User Management -->
            <section class="section-block" id="users">
                <div class="section-header">
                    <h3 class="section-title"><i class="fa-solid fa-users-cog" style="color: #10b981;"></i> User Management</h3>
                    <div class="section-actions">
                        <button class="btn-secondary" onclick="openAddStudentModal()"><i class="fa-solid fa-user-plus"></i> Add Student</button>
                        <button class="btn-secondary btn-secondary-admin" onclick="openAddAdminModal()"><i class="fa-solid fa-user-shield"></i> Add Admin</button>
                    </div>
                </div>

                <div class="panel panel-table">
                    <div class="table-wrap">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Student #</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_result->num_rows > 0): ?>
                                    <?php while ($user = $users_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['student_number'] ?: '—'); ?></td>
                                            <td>
                                                <span class="status-tag <?php echo $user['role'] === 'admin' ? 'status-busy' : 'status-approved'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td><?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?></td>
                                            <td>
                                                <?php if ($user['role'] === 'student'): ?>
                                                    <form method="POST" class="inline-form" onsubmit="return confirm('Upgrade this student to admin?');">
                                                        <input type="hidden" name="upgrade_to_admin" value="1">
                                                        <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                                        <button type="submit" class="action-btn action-btn-primary"><i class="fa-solid fa-user-shield"></i> Make Admin</button>
                                                    </form>
                                                <?php elseif ($user['email'] !== $user_email): ?>
                                                    <form method="POST" class="inline-form" onsubmit="return confirm('Downgrade this admin to student?');">
                                                        <input type="hidden" name="downgrade_to_student" value="1">
                                                        <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                                                        <button type="submit" class="action-btn action-btn-warning"><i class="fa-solid fa-user-graduate"></i> Make Student</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center-muted">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>

        </div>
    </main>

    <script src="dashboardscript.js"></script>
    <script>
        // Modal functions
        function openAddStudentModal() {
            document.getElementById('addStudentModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeAddStudentModal() {
            document.getElementById('addStudentModal').classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('addStudentForm').reset();
        }
        function openAddAdminModal() {
            document.getElementById('addAdminModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeAddAdminModal() {
            document.getElementById('addAdminModal').classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('addAdminForm').reset();
        }
        // Close modals on overlay click
        document.getElementById('addStudentModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddStudentModal();
        });
        document.getElementById('addAdminModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddAdminModal();
        });
        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddStudentModal();
                closeAddAdminModal();
            }
        });
    </script>

    <!-- Add Student Modal -->
    <div class="modal-overlay" id="addStudentModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> Add Student Account</h3>
                <button class="modal-close" onclick="closeAddStudentModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" class="modal-body" id="addStudentForm">
                <input type="hidden" name="add_student" value="1">
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" required placeholder="Enter first name">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" required placeholder="Enter last name">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-envelope"></i> Email <span class="required">*</span></label>
                    <input type="email" name="email" required placeholder="name@fit.edu.ph" pattern=".*@fit\.edu\.ph$">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-id-card"></i> Student Number <span class="required">*</span></label>
                    <input type="text" name="student_number" required placeholder="202412345 (9 digits)" pattern="[0-9]{9}" maxlength="9">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Password <span class="required">*</span></label>
                    <input type="password" name="password" required placeholder="Minimum 8 characters" minlength="8">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" required placeholder="Confirm password" minlength="8">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeAddStudentModal()"><i class="fa-solid fa-xmark"></i> Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-user-plus"></i> Create Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal-overlay" id="addAdminModal">
        <div class="modal">
            <div class="modal-header modal-header-admin">
                <h3><i class="fa-solid fa-user-shield"></i> Add Admin Account</h3>
                <button class="modal-close" onclick="closeAddAdminModal()" aria-label="Close">&times;</button>
            </div>
            <form method="POST" class="modal-body" id="addAdminForm">
                <input type="hidden" name="add_admin" value="1">
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" required placeholder="Enter first name">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" required placeholder="Enter last name">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-envelope"></i> Email <span class="required">*</span></label>
                    <input type="email" name="email" required placeholder="name@fit.edu.ph" pattern=".*@fit\.edu\.ph$">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Password <span class="required">*</span></label>
                    <input type="password" name="password" required placeholder="Minimum 8 characters" minlength="8">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Confirm Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" required placeholder="Confirm password" minlength="8">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeAddAdminModal()"><i class="fa-solid fa-xmark"></i> Cancel</button>
                    <button type="submit" class="btn-primary btn-primary-admin"><i class="fa-solid fa-user-shield"></i> Create Admin</button>
                </div>
            </form>
        </div>
    </div>

    <script src="dashboardscript.js"></script>
    <script>
        // Modal functions
        function openAddStudentModal() {
            document.getElementById('addStudentModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeAddStudentModal() {
            document.getElementById('addStudentModal').classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('addStudentForm').reset();
        }
        function openAddAdminModal() {
            document.getElementById('addAdminModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeAddAdminModal() {
            document.getElementById('addAdminModal').classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('addAdminForm').reset();
        }
        // Close modals on overlay click
        document.getElementById('addStudentModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddStudentModal();
        });
        document.getElementById('addAdminModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddAdminModal();
        });
        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddStudentModal();
                closeAddAdminModal();
            }
        });
    </script>
</body>
</html>