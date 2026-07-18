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

// Set current page for sidebar active state
$current_page = 'dashboard';
$current_section = $_GET['section'] ?? 'dashboard';

// Map sections to sidebar navigation items
$section_to_page = [
    'dashboard' => 'dashboard',
    'resources' => 'resources',
    'history' => 'reservations',
    'schedule' => 'schedule'
];

$current_page = $section_to_page[$current_section] ?? 'dashboard';

$success_message = "";
$error_message = "";

// Handle cancel reservation
if (isset($_GET['cancel'])) {
    $reservation_id = (int)$_GET['cancel'];

    $check_query = "SELECT id FROM reservations WHERE id = ? AND user_email = ? AND status = 'Pending'";
    if ($stmt = $conn->prepare($check_query)) {
        $stmt->bind_param("is", $reservation_id, $user_email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->close();
            $update_query = "UPDATE reservations SET status = 'Cancelled' WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $reservation_id);
            if ($update_stmt->execute()) {
                $success_message = "Reservation cancelled successfully.";
            }
            $update_stmt->close();
        } else {
            $error_message = "You can only cancel your own pending reservations.";
        }
    }
}

// Get user information
$user_query = "SELECT email, first_name, last_name, student_number, role FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// If user not found, redirect to login
if (!$user_data) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Get user's active reservations (Pending and Approved)
$active_query = "SELECT r.*, res.name as resource_name, res.category
                 FROM reservations r
                 JOIN resources res ON r.resource_id = res.id
                 WHERE r.user_email = ? AND r.status IN ('Pending', 'Approved')
                 ORDER BY r.created_at DESC";
$active_stmt = $conn->prepare($active_query);
$active_stmt->bind_param("s", $user_email);
$active_stmt->execute();
$active_reservations = $active_stmt->get_result();
$active_stmt->close();

// Get user's reservation history
$history_query = "SELECT r.*, res.name as resource_name, res.category
                  FROM reservations r
                  JOIN resources res ON r.resource_id = res.id
                  WHERE r.user_email = ? AND r.status IN ('Rejected', 'Cancelled', 'Completed')
                  ORDER BY r.created_at DESC LIMIT 10";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bind_param("s", $user_email);
$history_stmt->execute();
$history_reservations = $history_stmt->get_result();
$history_stmt->close();

// Get available resources
$resources_query = "SELECT * FROM resources WHERE status = 'Available' AND quantity > 0 ORDER BY category, name";
$resources_result = $conn->query($resources_query);

// Get count of available resources
$count_query = "SELECT COUNT(*) as total FROM resources WHERE status = 'Available' AND quantity > 0";
$count_result = $conn->query($count_query);
$available_count = $count_result->fetch_assoc()['total'];

// Get full name for display
$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="dashboardstyle.css">
    <title>TAM-Hub - Student Dashboard</title>
    <style>
    .move-up { margin-top: -10px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/student_sidebar.php'; ?>

<main class="content-wrapper">

    <header class="topbar">
        <button class="menu-btn" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="topbar-title">Resource Center</div>

        <div class="topbar-right">
            <i class="fa-regular fa-bell"></i>
            <div class="user-chip">
                <i class="fa-solid fa-circle-user"></i>
                <span><?php echo htmlspecialchars($full_name); ?></span>
            </div>
            <a href="logout.php" class="logout-link">
                <i class="fa-solid fa-sign-out-alt"></i> Logout
            </a>
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
        <!-- Dashboard View -->
        <section class="stats-grid stats-grid-4">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Active Reservations</span>
                    <span class="stat-value"><?php echo $active_reservations->num_rows; ?></span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Available Resources</span>
                    <span class="stat-value"><?php echo $available_count; ?></span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Pending Approvals</span>
                    <span class="stat-value">
                        <?php
                        $pending_count = 0;
                        $active_reservations->data_seek(0);
                        while ($r = $active_reservations->fetch_assoc()) {
                            if ($r['status'] === 'Pending') $pending_count++;
                        }
                        echo $pending_count;
                        ?>
                    </span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fa-solid fa-history"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Recent History</span>
                    <span class="stat-value"><?php echo $history_reservations->num_rows; ?></span>
                </div>
            </div>
        </section>

        <!-- Hero Card -->
        <section class="section-block">
            <div class="hero-card">
                <h4><i class="fa-solid fa-circle-user" style="color: var(--accent-gold);"></i> Welcome back, <?php echo htmlspecialchars($user_data['first_name']); ?></h4>
                <p class="big"><?php echo htmlspecialchars($full_name); ?></p>
                <p class="small">Student #<?php echo htmlspecialchars($user_data['student_number']); ?> | Ready to reserve resources</p>
            </div>
        </section>

        <!-- Main Content Columns -->
        <section class="dashboard-columns">

            <!-- Left: Active Reservations -->
            <div class="panel">
                <div class="section-header">
                    <h3 class="section-title"><i class="fa-solid fa-calendar-check"></i> My Active Reservations</h3>
                    <a href="reserve_resource.php" class="request-btn"><i class="fa-solid fa-plus"></i> New Reservation</a>
                </div>

                <?php if ($active_reservations->num_rows > 0): ?>
                    <?php
                    $active_reservations->data_seek(0);
                    while ($res = $active_reservations->fetch_assoc()):
                        $status_class = strtolower($res['status']);
                    ?>
                    <div class="request-card" style="margin-bottom: 16px;">
                        <div class="request-header">
                            <div class="request-student">
                                <div class="student-avatar">
                                    <i class="fa-solid fa-box"></i>
                                </div>
                                <div class="student-info">
                                    <h4><?php echo htmlspecialchars($res['resource_name']); ?></h4>
                                    <div class="student-meta">
                                        <span class="text-muted"><?php echo htmlspecialchars($res['category']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <span class="status-tag status-<?php echo $status_class; ?>">
                                <?php echo $res['status']; ?>
                            </span>
                        </div>

                        <div class="schedule-comparison">
                            <div class="schedule-box current">
                                <h5>Schedule</h5>
                                <div class="schedule-row">
                                    <span class="schedule-label">Date:</span>
                                    <span class="schedule-value"><?php echo date('M d, Y', strtotime($res['start_datetime'])); ?></span>
                                </div>
                                <div class="schedule-row">
                                    <span class="schedule-label">Time:</span>
                                    <span class="schedule-value">
                                        <?php echo date('h:i A', strtotime($res['start_datetime'])); ?> -
                                        <?php echo date('h:i A', strtotime($res['end_datetime'])); ?>
                                    </span>
                                </div>
                                <div class="schedule-row">
                                    <span class="schedule-label">Purpose:</span>
                                    <span class="schedule-value"><?php echo htmlspecialchars(substr($res['purpose'], 0, 50)) . (strlen($res['purpose']) > 50 ? '...' : ''); ?></span>
                                </div>
                            </div>

                            <div class="schedule-box requested">
                                <h5>Status Details</h5>
                                <?php if ($res['status'] === 'Pending'): ?>
                                    <div class="schedule-row">
                                        <span class="schedule-label">Status:</span>
                                        <span class="schedule-value" style="color: #a5720b;">Awaiting admin approval</span>
                                    </div>
                                    <div class="schedule-row">
                                        <span class="schedule-label">Requested:</span>
                                        <span class="schedule-value"><?php echo date('M d, Y h:i A', strtotime($res['created_at'])); ?></span>
                                    </div>
                                <?php elseif ($res['status'] === 'Approved'): ?>
                                    <div class="schedule-row">
                                        <span class="schedule-label">Status:</span>
                                        <span class="schedule-value" style="color: #1e7a3c;">Approved - Ready to use</span>
                                    </div>
                                    <div class="schedule-row">
                                        <span class="schedule-label">Approved:</span>
                                        <span class="schedule-value"><?php echo date('M d, Y', strtotime($res['updated_at'] ?? $res['created_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($res['status'] === 'Pending'): ?>
                        <div class="action-buttons">
                            <a href="?cancel=<?php echo $res['id']; ?>" class="btn-reject" onclick="return confirm('Cancel this reservation?');">
                                <i class="fa-solid fa-xmark"></i> Cancel Reservation
                            </a>
                        </div>
                        <?php elseif ($res['status'] === 'Approved'): ?>
                        <div class="action-buttons">
                            <span class="request-btn" style="background: #1e7a3c; cursor: default;">
                                <i class="fa-solid fa-check"></i> Approved
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-calendar-xmark"></i>
                        <h3>No Active Reservations</h3>
                        <p>You have no pending or approved reservations.</p>
                        <a href="reserve_resource.php" class="request-btn" style="margin-top: 16px; display: inline-flex;">
                            <i class="fa-solid fa-calendar-plus"></i> Make a Reservation
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Quick Actions + Profile -->
            <div class="side-column">

                <!-- Quick Actions -->
                <div class="panel">
                    <h3 class="section-title"><i class="fa-solid fa-bolt"></i> Quick Actions</h3>
                    <div class="quick-actions">
                        <a href="student_dashboard.php?section=resources" class="request-btn" style="justify-content: flex-start;">
                            <i class="fa-solid fa-magnifying-glass"></i> Browse Resources
                        </a>
                        <a href="reserve_resource.php" class="request-btn request-btn-secondary" style="justify-content: flex-start;">
                            <i class="fa-solid fa-calendar-plus"></i> New Reservation
                        </a>
                        <a href="student_dashboard.php?section=history" class="request-btn request-btn-secondary" style="justify-content: flex-start;">
                            <i class="fa-solid fa-clock-rotate-left"></i> View History
                        </a>
                        <a href="student_schedule.php" class="request-btn request-btn-secondary" style="justify-content: flex-start; background: #6a4c93;">
                            <i class="fa-solid fa-calendar-days"></i> My Schedule
                        </a>
                    </div>
                </div>

                <!-- My Profile -->
                <div class="panel">
                    <h3 class="section-title"><i class="fa-solid fa-user"></i> My Profile</h3>
                    <div class="profile-grid">
                        <div>
                            <span class="profile-label">Name:</span>
                            <span class="profile-value"><?php echo htmlspecialchars($full_name); ?></span>
                        </div>
                        <div>
                            <span class="profile-label">Email:</span>
                            <span class="profile-value"><?php echo htmlspecialchars($user_data['email']); ?></span>
                        </div>
                        <div>
                            <span class="profile-label">Student #:</span>
                            <span class="profile-value"><?php echo htmlspecialchars($user_data['student_number']); ?></span>
                        </div>
                        <div>
                            <span class="profile-label">Role:</span>
                            <span class="profile-value"><?php echo ucfirst(htmlspecialchars($user_data['role'])); ?></span>
                        </div>
                    </div>
                </div>

            </div>

        </section>
        <?php endif; ?>

        <?php if ($current_section === 'history'): ?>
        <!-- Reservation History -->
        <section class="section-block" id="history">
            <div class="section-header">
                <h3 class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Reservation History</h3>
            </div>
            <div class="panel panel-table">
                <?php if ($history_reservations->num_rows > 0): ?>
                    <div class="table-wrap">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th>Category</th>
                                    <th>Date/Time</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($res = $history_reservations->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($res['resource_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($res['category']); ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($res['start_datetime'])); ?><br>
                                            <small style="color: var(--text-muted);">
                                                <?php echo date('h:i A', strtotime($res['start_datetime'])); ?> -
                                                <?php echo date('h:i A', strtotime($res['end_datetime'])); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($res['purpose'], 0, 40)) . (strlen($res['purpose']) > 40 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="status-tag status-<?php echo strtolower($res['status']); ?>">
                                                <?php echo $res['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($res['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-history"></i>
                        <h3>No History Yet</h3>
                        <p>Your completed, rejected, or cancelled reservations will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($current_section === 'resources'): ?>
        <!-- Available Resources -->
        <section class="section-block" id="resources">
            <div class="section-header">
                <h3 class="section-title"><i class="fa-solid fa-boxes-stacked"></i> Available Resources</h3>
            </div>
            <div class="panel panel-table">
                <?php if ($resources_result->num_rows > 0): ?>
                    <div class="table-wrap">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Resource</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($resource = $resources_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($resource['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($resource['category']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($resource['description'] ?? '', 0, 50)) . (strlen($resource['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                        <td><?php echo $resource['quantity']; ?></td>
                                        <td>
                                            <a href="reserve_resource.php?resource_id=<?php echo $resource['id']; ?>" class="reserve-btn">Reserve</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-boxes-packing"></i>
                        <h3>No Resources Available</h3>
                        <p>No resources are currently available for reservation.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

</main>

<script src="dashboardscript.js"></script>
</body>
</html>