<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Get current semester filter (for preserving after POST redirect)
$current_semester = $_GET['semester'] ?? '1st Semester 2024-2025';

// Handle add schedule - with PRG pattern
if (isset($_POST['add_schedule'])) {
    $subject_code = strtoupper(trim($_POST['subject_code']));
    $subject_name = trim($_POST['subject_name']);
    $room = trim($_POST['room']);
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $semester = trim($_POST['semester']) ?: '1st Semester 2024-2025';

    // Validate
    if (empty($subject_code) || empty($subject_name) || empty($room) || empty($day_of_week) || empty($start_time) || empty($end_time)) {
        $error_message = "All fields are required.";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error_message = "End time must be after start time.";
    } else {
        // Check for conflicts
        $check_query = "SELECT id FROM schedules WHERE room = ? AND day_of_week = ? AND status = 'Active'
                        AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ssssss", $room, $day_of_week, $start_time, $start_time, $end_time, $end_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Room conflict: This room is already booked for the selected time on that day.";
        } else {
            $insert_query = "INSERT INTO schedules (subject_code, subject_name, room, day_of_week, start_time, end_time, semester) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssssss", $subject_code, $subject_name, $room, $day_of_week, $start_time, $end_time, $semester);
            if ($insert_stmt->execute()) {
                // PRG Pattern: Redirect to preserve semester filter and prevent form resubmission
                header("Location: admin_schedule.php?semester=" . urlencode($semester) . "&added=1");
                exit();
            } else {
                $error_message = "Failed to add schedule. Subject code may already exist.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
}

// Check for success redirect
if (isset($_GET['added']) && $_GET['added'] == '1') {
    $success_message = "Schedule added successfully.";
}
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $success_message = "Schedule updated successfully.";
}
if (isset($_GET['toggled']) && $_GET['toggled'] == '1') {
    $success_message = "Schedule status updated successfully.";
}
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success_message = "Schedule deleted successfully.";
}

// Handle toggle status - with PRG pattern
if (isset($_GET['toggle_status']) && isset($_GET['schedule_id'])) {
    $schedule_id = (int)$_GET['schedule_id'];
    $current_status = $_GET['current_status'];
    $new_status = $current_status === 'Active' ? 'Inactive' : 'Active';

    $update_query = "UPDATE schedules SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $new_status, $schedule_id);
    if ($update_stmt->execute()) {
        // PRG Pattern: Redirect with semester preserved
        header("Location: admin_schedule.php?semester=" . urlencode($current_semester) . "&toggled=1");
        exit();
    } else {
        $error_message = "Failed to update status.";
    }
    $update_stmt->close();
}

// Handle delete schedule - with PRG pattern
if (isset($_GET['delete_schedule'])) {
    $schedule_id = (int)$_GET['delete_schedule'];

    // Check if any students are enrolled
    $check_query = "SELECT COUNT(*) as count FROM student_schedules WHERE schedule_id = ? AND status != 'Dropped'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $schedule_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($check_result['count'] > 0) {
        $error_message = "Cannot delete: Students are enrolled in this schedule.";
    } else {
        $delete_query = "DELETE FROM schedules WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $schedule_id);
        if ($delete_stmt->execute()) {
            // PRG Pattern: Redirect with semester preserved
            header("Location: admin_schedule.php?semester=" . urlencode($current_semester) . "&deleted=1");
            exit();
        } else {
            $error_message = "Failed to delete schedule.";
        }
        $delete_stmt->close();
    }
}

// Handle edit schedule - with PRG pattern
if (isset($_POST['edit_schedule'])) {
    $schedule_id = (int)$_POST['schedule_id'];
    $subject_code = strtoupper(trim($_POST['subject_code']));
    $subject_name = trim($_POST['subject_name']);
    $room = trim($_POST['room']);
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $semester = trim($_POST['semester']) ?: '1st Semester 2024-2025';

    if (empty($subject_code) || empty($subject_name) || empty($room) || empty($day_of_week) || empty($start_time) || empty($end_time)) {
        $error_message = "All fields are required.";
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error_message = "End time must be after start time.";
    } else {
        // Check for conflicts (excluding current schedule)
        $check_query = "SELECT id FROM schedules WHERE room = ? AND day_of_week = ? AND status = 'Active' AND id != ?
                        AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ssissss", $room, $day_of_week, $schedule_id, $start_time, $start_time, $end_time, $end_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Room conflict: This room is already booked for the selected time on that day.";
        } else {
            $update_query = "UPDATE schedules SET subject_code = ?, subject_name = ?, room = ?, day_of_week = ?, start_time = ?, end_time = ?, semester = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssssssi", $subject_code, $subject_name, $room, $day_of_week, $start_time, $end_time, $semester, $schedule_id);
            if ($update_stmt->execute()) {
                // PRG Pattern: Redirect with semester preserved
                header("Location: admin_schedule.php?semester=" . urlencode($semester) . "&updated=1");
                exit();
            } else {
                $error_message = "Failed to update schedule.";
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}

// Get filter semester and alias for redirects
$filter_semester = $_GET['semester'] ?? '1st Semester 2024-2025';
$current_semester = $filter_semester; // Alias for use in redirects

// Get all schedules
$semester_param = $filter_semester;
$query = "SELECT * FROM schedules WHERE semester = ? ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), start_time";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $semester_param);
$stmt->execute();
$schedules = $stmt->get_result();
$stmt->close();

// Get distinct semesters for filter dropdown (fetch into array first for reuse)
$sem_query = "SELECT DISTINCT semester FROM schedules ORDER BY semester DESC";
$sem_result = $conn->query($sem_query);
$semesters = [];
while ($row = $sem_result->fetch_assoc()) {
    $semesters[] = $row['semester'];
}

// Get admin user info
$user_query = "SELECT email, first_name, last_name, role FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

// Set current page for sidebar active state
$current_page = 'schedule';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tams-Hub | Admin Schedule Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="dashboardstyle_admin.css" />
    <style>
        /* Schedule-specific styles */
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }
        .schedule-table th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #8a8f98;
            padding: 14px 12px;
            border-bottom: 1px solid #eef0f2;
        }
        .schedule-table td {
            font-size: 14px;
            color: #2c313a;
            padding: 14px 12px;
            border-bottom: 1px solid #f4f5f7;
        }
        .schedule-table tr:last-child td {
            border-bottom: none;
        }
        .schedule-table tr:hover td {
            background: #fbfcf7;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-active {
            background: #e5f6e8;
            color: #1e7a3c;
        }
        .status-inactive {
            background: #f0f0f0;
            color: #7e8590;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .action-btn.edit {
            background: #d4a017;
            color: #2c313a;
        }
        .action-btn.edit:hover {
            background: #bb8a13;
        }
        .action-btn.toggle {
            background: #105d11;
            color: #fff;
        }
        .action-btn.toggle:hover {
            background: #0b3d0b;
        }
        .action-btn.delete {
            background: #a43b3b;
            color: #fff;
        }
        .action-btn.delete:hover {
            background: #8b2f2f;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(4, 78, 25, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(4, 78, 25, 0.2);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            background: #064814;
            color: #fdfdfd;
            padding: 20px 24px;
            border-radius: 18px 18px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .modal-close {
            background: none;
            border: none;
            color: #d4a017;
            font-size: 20px;
            cursor: pointer;
        }
        .modal-body {
            padding: 24px;
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
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s;
            background: #fafbf7;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #d4a017;
            outline: none;
            background: #fff;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 10px;
        }
        .btn-primary {
            background: #d4a017;
            color: #2c313a;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #bb8a13;
        }
        .btn-secondary {
            background: #2c313a;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background: #1c2027;
        }

        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-bar select {
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            background: #fff;
        }

        .time-display {
            font-family: 'Segoe UI', monospace;
            font-weight: 600;
        }

        .subject-code {
            display: inline-block;
            background: #eef1b4;
            color: #064814;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .schedule-table {
                font-size: 12px;
            }
            .schedule-table th, .schedule-table td {
                padding: 10px 8px;
            }
            .action-btn {
                padding: 5px 8px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/admin_sidebar.php'; ?>

<main class="content-wrapper">
        <header class="topbar">
            <button class="menu-btn" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>
            <div class="topbar-title">Schedule Management</div>
            <div class="topbar-right">
                <i class="fa-regular fa-bell"></i>
                <div class="user-chip"><i class="fa-solid fa-circle-user"></i><span><?php echo htmlspecialchars($full_name); ?></span></div>
                <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="page-content">

            <!-- Alert Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert-banner" style="background: #e5f6e8; color: #1e7a3c; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert-banner" style="background: #fde7e7; color: #9d2f2f; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0; color: #064814;">Weekly Schedule Management</h2>
                    <p style="margin: 4px 0 0; color: #7e8590;">Manage subject schedules: rooms, days, and time slots</p>
                </div>
                <button class="btn-primary" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add Schedule</button>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" style="display: flex; gap: 12px; align-items: center;">
                    <label style="font-weight: 600; color: #2c313a;">Semester:</label>
                    <select name="semester" onchange="this.form.submit()">
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo htmlspecialchars($sem); ?>" <?php echo $sem === $filter_semester ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Schedule Table -->
            <div class="panel" style="background: #fff; border-radius: 18px; box-shadow: 0 8px 24px rgba(4, 78, 25, 0.14); padding: 0; overflow: hidden;">
                <div class="table-wrap" style="overflow-x: auto;">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Subject</th>
                                <th>Room</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Semester</th>
                                <th>Status</th>
                                <th style="width: 160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $counter = 1;
                            if ($schedules->num_rows > 0):
                                while ($schedule = $schedules->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <span class="subject-code"><?php echo htmlspecialchars($schedule['subject_code']); ?></span>
                                    <strong><?php echo htmlspecialchars($schedule['subject_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($schedule['room']); ?></td>
                                <td>
                                    <span style="text-transform: capitalize;"><?php echo htmlspecialchars($schedule['day_of_week']); ?></span>
                                </td>
                                <td>
                                    <span class="time-display">
                                        <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> -
                                        <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($schedule['semester']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $schedule['status'] === 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $schedule['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <button class="action-btn edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($schedule)); ?>)">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                        <a href="?toggle_status=1&schedule_id=<?php echo $schedule['id']; ?>&current_status=<?php echo $schedule['status']; ?>"
                                           class="action-btn toggle" onclick="return confirm('Toggle status to <?php echo $schedule['status'] === 'Active' ? 'Inactive' : 'Active'; ?>?');">
                                            <i class="fa-solid fa-<?php echo $schedule['status'] === 'Active' ? 'pause' : 'play'; ?>"></i>
                                            <?php echo $schedule['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                                        </a>
                                        <a href="?delete_schedule=<?php echo $schedule['id']; ?>"
                                           class="action-btn delete" onclick="return confirm('Delete this schedule? This cannot be undone.');">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #7e8590; padding: 40px;">
                                    No schedules found for this semester. <a href="#" onclick="openAddModal()" style="color: #d4a017;">Add one</a>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Add Schedule Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plus"></i> Add New Schedule</h3>
                <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
            </div>
            <form method="POST" action="admin_schedule.php" class="modal-body">
                <input type="hidden" name="current_semester" value="<?php echo htmlspecialchars($current_semester); ?>">
                <div class="form-group">
                    <label for="add_subject_code">Subject Code <span style="color: #9d2f2f;">*</span></label>
                    <input type="text" name="subject_code" id="add_subject_code" required placeholder="e.g., AUTOMATA" maxlength="20" style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label for="add_subject_name">Subject Name <span style="color: #9d2f2f;">*</span></label>
                    <input type="text" name="subject_name" id="add_subject_name" required placeholder="e.g., Automata Theory" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="add_room">Room <span style="color: #9d2f2f;">*</span></label>
                    <input type="text" name="room" id="add_room" required placeholder="e.g., FIT608" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="add_day_of_week">Day of Week <span style="color: #9d2f2f;">*</span></label>
                    <select name="day_of_week" id="add_day_of_week" required>
                        <option value="">Select day...</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label for="add_start_time">Start Time <span style="color: #9d2f2f;">*</span></label>
                        <input type="time" name="start_time" id="add_start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="add_end_time">End Time <span style="color: #9d2f2f;">*</span></label>
                        <input type="time" name="end_time" id="add_end_time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="add_semester">Semester <span style="color: #9d2f2f;">*</span></label>
                    <select name="semester" id="add_semester" required>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo htmlspecialchars($sem); ?>" <?php echo $sem === $current_semester ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem); ?>
                            </option>
                        <?php endforeach; ?>
                        <!-- Fallback if no semesters exist yet -->
                        <?php if (empty($semesters)): ?>
                            <option value="1st Semester 2024-2025" selected>1st Semester 2024-2025</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" name="add_schedule" class="btn-primary"><i class="fa-solid fa-save"></i> Save Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pen"></i> Edit Schedule</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" class="modal-body">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                <div class="form-group">
                    <label for="edit_subject_code">Subject Code <span style="color: #9d2f2f;">*</span></label>
                    <input type="text" name="subject_code" id="edit_subject_code" required placeholder="e.g., AUTOMATA" maxlength="20" style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label for="edit_subject_name">Subject Name <span style="color: #9d2f2f;">*</span></label>
                    <input type="text" name="subject_name" id="edit_subject_name" required placeholder="e.g., Automata Theory" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="edit_room">Room <span style="color: #9d2f2f;">*</span></label>
                    <input type="text" name="room" id="edit_room" required placeholder="e.g., FIT608" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="edit_day_of_week">Day of Week <span style="color: #9d2f2f;">*</span></label>
                    <select name="day_of_week" id="edit_day_of_week" required>
                        <option value="">Select day...</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label for="edit_start_time">Start Time <span style="color: #9d2f2f;">*</span></label>
                        <input type="time" name="start_time" id="edit_start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_end_time">End Time <span style="color: #9d2f2f;">*</span></label>
                        <input type="time" name="end_time" id="edit_end_time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_semester">Semester</label>
                    <select name="semester" id="edit_semester">
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo htmlspecialchars($sem); ?>" <?php echo $sem === $current_semester ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" name="edit_schedule" class="btn-primary"><i class="fa-solid fa-save"></i> Update Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script src="dashboardscript_admin.js"></script>
    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openEditModal(schedule) {
            document.getElementById('edit_schedule_id').value = schedule.id;
            document.getElementById('edit_subject_code').value = schedule.subject_code;
            document.getElementById('edit_subject_name').value = schedule.subject_name;
            document.getElementById('edit_room').value = schedule.room;
            document.getElementById('edit_day_of_week').value = schedule.day_of_week;
            document.getElementById('edit_start_time').value = schedule.start_time;
            document.getElementById('edit_end_time').value = schedule.end_time;
            document.getElementById('edit_semester').value = schedule.semester;

            document.getElementById('editModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>