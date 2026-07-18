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

// Check if user is a student
if ($_SESSION['role'] !== 'student') {
    header("Location: admin_dashboard.php");
    exit();
}

require_once __DIR__ . '/db_connect.php';

$success_message = "";
$error_message = "";

// Handle drop/unenroll schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_schedule'])) {
    $enrollment_id = (int)$_POST['enrollment_id'];

    // Verify this enrollment belongs to the current student
    $check_query = "SELECT id FROM student_schedules WHERE id = ? AND student_email = ? AND status != 'Dropped'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $enrollment_id, $user_email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        // Update status to Dropped
        $drop_query = "UPDATE student_schedules SET status = 'Dropped', updated_at = NOW() WHERE id = ?";
        $drop_stmt = $conn->prepare($drop_query);
        $drop_stmt->bind_param("i", $enrollment_id);
        if ($drop_stmt->execute()) {
            $success_message = "Class dropped successfully!";
        } else {
            $error_message = "Failed to drop class. Please try again.";
        }
        $drop_stmt->close();
    } else {
        $check_stmt->close();
        $error_message = "Class not found or already dropped.";
    }
}

// Get available semesters from schedules
$sem_query = "SELECT DISTINCT semester FROM schedules ORDER BY semester DESC";
$sem_result = $conn->query($sem_query);
$semesters = [];
while ($row = $sem_result->fetch_assoc()) {
    $semesters[] = $row['semester'];
}

// Get current semester from GET parameter or default to first available
$current_semester = $_GET['semester'] ?? ($semesters[0] ?? '1st Semester 2024-2025');

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_schedule'])) {
    $schedule_id = (int)$_POST['schedule_id'];

    // Check if schedule exists and is active
    $check_query = "SELECT id FROM schedules WHERE id = ? AND semester = ? AND status = 'Active'";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $schedule_id, $current_semester);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Check if already enrolled
        $enroll_check = "SELECT id FROM student_schedules WHERE student_email = ? AND schedule_id = ? AND status != 'Dropped'";
        $enroll_stmt = $conn->prepare($enroll_check);
        $enroll_stmt->bind_param("si", $user_email, $schedule_id);
        $enroll_stmt->execute();
        $enroll_result = $enroll_stmt->get_result();

        if ($enroll_result->num_rows === 0) {
            // Enroll student
            $insert_query = "INSERT INTO student_schedules (student_email, schedule_id, status) VALUES (?, ?, 'Enrolled')";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("si", $user_email, $schedule_id);
            if ($insert_stmt->execute()) {
                $success_message = "Successfully enrolled in the class!";
            } else {
                $error_message = "Failed to enroll. Please try again.";
            }
            $insert_stmt->close();
        } else {
            $error_message = "You are already enrolled in this class.";
        }
        $enroll_stmt->close();
    } else {
        $error_message = "Class not found or not available.";
    }
    $check_stmt->close();
}

// Handle schedule change request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_change'])) {
    $schedule_id = (int)$_POST['schedule_id'];
    $requested_room = trim($_POST['requested_room']);
    $requested_day = $_POST['requested_day'];
    $requested_start_time = $_POST['requested_start_time'];
    $requested_end_time = $_POST['requested_end_time'];
    $change_reason = trim($_POST['change_reason']);

    // Validate
    if (empty($requested_room) || empty($requested_day) || empty($requested_start_time) || empty($requested_end_time) || empty($change_reason)) {
        $error_message = "All fields are required.";
    } elseif (strtotime($requested_start_time) >= strtotime($requested_end_time)) {
        $error_message = "End time must be after start time.";
    } else {
        // Check if student is enrolled in this schedule
        $check_query = "SELECT id FROM student_schedules WHERE student_email = ? AND schedule_id = ? AND status != 'Dropped'";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $user_email, $schedule_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {
            $error_message = "You are not enrolled in this schedule.";
        } else {
            $enrollment = $check_result->fetch_assoc();
            $enrollment_id = $enrollment['id'];
            $check_stmt->close();

            // Update with requested changes
            $update_query = "UPDATE student_schedules
                             SET requested_room = ?,
                                 requested_day = ?,
                                 requested_start_time = ?,
                                 requested_end_time = ?,
                                 change_reason = ?,
                                 status = 'Pending Change',
                                 updated_at = NOW()
                             WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssssi", $requested_room, $requested_day, $requested_start_time, $requested_end_time, $change_reason, $enrollment_id);
            if ($update_stmt->execute()) {
                $success_message = "Schedule change request submitted successfully! Waiting for admin approval.";
            } else {
                $error_message = "Failed to submit request. Please try again.";
            }
            $update_stmt->close();
        }
    }
}

// Get student info
$user_query = "SELECT email, first_name, last_name, student_number, role FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// If user not found, redirect to login
if (!$user_data) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

// Set current page for sidebar active state
$current_page = 'schedule';

// Get student's schedules with enrollment status
$schedule_query = "SELECT ss.*, s.subject_code, s.subject_name, s.room, s.day_of_week, s.start_time, s.end_time, s.semester
                   FROM student_schedules ss
                   JOIN schedules s ON ss.schedule_id = s.id
                   WHERE ss.student_email = ? AND s.semester = ? AND ss.status != 'Dropped' AND s.status = 'Active'
                   ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), s.start_time";
$schedule_stmt = $conn->prepare($schedule_query);
$schedule_stmt->bind_param("ss", $user_email, $current_semester);
$schedule_stmt->execute();
$student_schedules = $schedule_stmt->get_result();
$schedule_stmt->close();

// Get all available schedules for this semester (for enrollment)
$available_query = "SELECT s.* FROM schedules s
                    WHERE s.semester = ? AND s.status = 'Active'
                    AND NOT EXISTS (
                        SELECT 1 FROM student_schedules ss
                        WHERE ss.schedule_id = s.id AND ss.student_email = ? AND ss.status != 'Dropped'
                    )
                    ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), s.start_time";
$available_stmt = $conn->prepare($available_query);
$available_stmt->bind_param("ss", $current_semester, $user_email);
$available_stmt->execute();
$available_schedules = $available_stmt->get_result();
$available_stmt->close();

// Organize schedules by day for weekly grid
$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$schedule_by_day = [];
while ($row = $student_schedules->fetch_assoc()) {
    $day = $row['day_of_week'];
    if (!isset($schedule_by_day[$day])) {
        $schedule_by_day[$day] = [];
    }
    $schedule_by_day[$day][] = $row;
}

// Time slots for grid (7:00 AM to 9:00 PM in 30-min intervals)
$time_slots = [];
$start = strtotime('07:00');
$end = strtotime('21:00');
while ($start <= $end) {
    $time_slots[] = date('H:i', $start);
    $start = strtotime('+30 minutes', $start);
}

// Calculate grid height based on time slots (30px per slot + header)
$num_slots = count($time_slots);
$grid_height = ($num_slots * 30) + 55; // 55px for day header
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="dashboardstyle.css">
    <style>
        /* Weekly Schedule Grid Styles */
        .schedule-grid {
            display: grid;
            grid-template-columns: 80px repeat(6, 1fr);
            gap: 1px;
            background: #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }
        .time-column {
            background: #f5f5f5;
            border-right: 2px solid #e0e0e0;
            box-sizing: border-box;
        }
        .day-column {
            background: #fff;
            min-height: <?php echo $grid_height; ?>px;
            position: relative;
            box-sizing: border-box;
        }
        .day-header {
            background: #064814;
            color: #d4a017;
            padding: 12px 8px;
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            border-bottom: 2px solid #d4a017;
            box-sizing: border-box;
            height: 55px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .day-header .day-name {
            font-size: 14px;
            font-weight: 700;
        }
        .day-header .day-date {
            font-size: 11px;
            opacity: 0.8;
            margin-top: 2px;
        }
        .time-slot {
            height: 30px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #7e8590;
            background: #fafbf7;
        }
        .time-slot.current-time {
            background: #e5f6e8;
            color: #1e7a3c;
            font-weight: 700;
        }

        .schedule-block {
            position: absolute;
            left: 2px;
            right: 2px;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 11px;
            color: #fff;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            z-index: 10;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            min-height: 48px;
            word-break: break-word;
            hyphens: auto;
        }
        .schedule-block:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .schedule-block.subject-automata { background: linear-gradient(135deg, #1e7a3c, #2d9f5e); }
        .schedule-block.subject-purposive { background: linear-gradient(135deg, #d4a017, #e8b84a); color: #2c313a; }
        .schedule-block.subject-python { background: linear-gradient(135deg, #306998, #4a90d9); }
        .schedule-block.subject-techno { background: linear-gradient(135deg, #a43b3b, #c95a5a); }
        .schedule-block.subject-appdev { background: linear-gradient(135deg, #6a4c93, #8b6fc4); }
        .schedule-block.subject-default { background: linear-gradient(135deg, #064814, #156B2E); } /* Default for any subject */

        .schedule-block .subject-name {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 2px;
            white-space: normal;
            overflow: visible;
            text-overflow: unset;
            line-height: 1.2;
            word-break: break-word;
        }
        .schedule-block .subject-room {
            font-size: 10px;
            opacity: 0.95;
            white-space: normal;
            overflow: visible;
            text-overflow: unset;
            line-height: 1.2;
            word-break: break-word;
            margin-top: 1px;
        }
        .schedule-block .subject-status {
            font-size: 8px;
            opacity: 0.8;
            margin-top: 2px;
            line-height: 1.2;
        }

        .schedule-block .schedule-actions {
            margin-top: 6px;
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        .schedule-block .schedule-actions button,
        .schedule-block .schedule-actions form {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 9px;
            cursor: pointer;
            white-space: nowrap;
        }
        .schedule-block .schedule-actions button:hover,
        .schedule-block .schedule-actions form:hover button {
            background: rgba(255,255,255,0.3);
        }
        .schedule-block .schedule-actions form {
            margin: 0;
        }

        .day-column.empty-day {
            background: repeating-linear-gradient(
                45deg,
                #fafbf7,
                #fafbf7 10px,
                #f5f6f0 10px,
                #f5f6f0 20px
            );
        }
        .empty-day-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #a0a5ad;
            font-size: 13px;
            padding: 20px;
            text-align: center;
        }
        .empty-day-message i { font-size: 24px; margin-bottom: 8px; opacity: 0.5; }

        /* Pending Requests */
        .request-card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(4, 78, 25, 0.08);
            border: 1px solid #eef0f2;
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .request-subject {
            font-weight: 700;
            color: #2c313a;
            font-size: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-pending { background: #fdf3e0; color: #a5720b; }
        .status-approved { background: #e5f6e8; color: #1e7a3c; }
        .status-rejected { background: #fde7e7; color: #9d2f2f; }
        .status-enrolled { background: #e5f6e8; color: #1e7a3c; }

        .request-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .request-box {
            background: #fbfcf7;
            border: 1px solid rgba(12, 93, 31, 0.12);
            border-radius: 12px;
            padding: 16px;
        }
        .request-box.current { border-color: #d4a017; }
        .request-box.requested { border-color: #1e7a3c; }
        .request-box h5 {
            margin: 0 0 12px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7e8590;
        }
        .request-box.current h5 { color: #a5720b; }
        .request-box.requested h5 { color: #1e7a3c; }
        .request-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .request-row:last-child { margin-bottom: 0; }
        .request-label { color: #7e8590; }
        .request-value { font-weight: 600; color: #2c313a; }

        .request-reason {
            background: #f5f5f5;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            line-height: 1.6;
            color: #2c313a;
        }
        .request-reason-label { font-weight: 700; color: #064814; margin-bottom: 4px; display: block; }

        /* Modal */
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
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(4, 78, 25, 0.2);
            width: 100%;
            max-width: 480px;
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
        .modal-header h3 { margin: 0; font-size: 18px; }
        .modal-close { background: none; border: none; color: #d4a017; font-size: 20px; cursor: pointer; }
        .modal-body { padding: 24px; }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-weight: 700;
            color: #2c313a;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s;
            background: #fafbf7;
            box-sizing: border-box;
        }
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #d4a017;
            outline: none;
            background: #fff;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
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
        .btn-primary:hover { background: #bb8a13; }
        .btn-secondary {
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
        .btn-secondary:hover { background: #1c2027; }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .schedule-grid {
                grid-template-columns: 60px 1fr;
                overflow-x: auto;
            }
            .day-column { min-width: 140px; }
            .request-comparison { grid-template-columns: 1fr; }
            .schedule-legend { justify-content: center; }
        }

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

        <div class="topbar-title">My Weekly Schedule</div>

        <div class="topbar-right">
            <i class="fa-regular fa-bell"></i>
            <div class="user-chip">
                <i class="fa-solid fa-circle-user"></i>
                <span><?php echo htmlspecialchars($full_name); ?></span>
            </div>
            <a href="logout.php" style="color: #f8f53e; text-decoration: none; font-weight: 600; padding: 6px 14px; border: 1px solid #f8f53e; border-radius: 6px; transition: all 0.3s ease;"
               onmouseover="this.style.background='#f8f53e'; this.style.color='#064814';"
               onmouseout="this.style.background='transparent'; this.style.color='#f8f53e';">
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
            <div class="alert-banner" style="background: #f8d7da; color: #721c24;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Semester Selector -->
        <div class="section-block" style="margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <h3 class="section-title" style="margin: 0;"><i class="fa-solid fa-calendar-days" style="color: #d4a017;"></i> Weekly Schedule - <?php echo htmlspecialchars($current_semester); ?></h3>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                        <label style="font-weight: 600; color: #2c313a; font-size: 14px;">Semester:</label>
                        <select name="semester" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 13px; background: #fff;">
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?php echo htmlspecialchars($sem); ?>" <?php echo $sem === $current_semester ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sem); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <span style="font-size: 13px; color: #7e8590;">Student #: <?php echo htmlspecialchars($user_data['student_number']); ?></span>
                </div>
            </div>
        </div>

        <!-- Weekly Schedule Grid -->
        <div class="panel" style="background: #fff; border-radius: 18px; box-shadow: 0 8px 24px rgba(4, 78, 25, 0.14); padding: 0; overflow: hidden;">
            <div class="schedule-grid" id="scheduleGrid">
                <!-- Time Column -->
                <div class="time-column">
                    <div class="day-header" style="background: #064814; color: #d4a017; height: 55px; display: flex; align-items: center; justify-content: center;">Time</div>
                    <?php foreach ($time_slots as $slot): ?>
                        <?php
                        $slot_time = strtotime($slot . ':00');
                        $is_current = false;
                        $now = strtotime(date('H:i'));
                        if ($now >= $slot_time && $now < $slot_time + 1800) $is_current = true;
                        ?>
                        <div class="time-slot <?php echo $is_current ? 'current-time' : ''; ?>">
                            <?php echo date('g:i A', $slot_time); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Day Columns -->
                <?php foreach ($days_order as $day): ?>
                    <div class="day-column <?php echo !isset($schedule_by_day[$day]) ? 'empty-day' : ''; ?>" data-day="<?php echo $day; ?>">
                        <div class="day-header">
                            <div class="day-name"><?php echo $day; ?></div>
                            <div class="day-date"><?php echo date('M d', strtotime("next " . strtolower($day))); ?></div>
                        </div>
                        <?php if (isset($schedule_by_day[$day])): ?>
                            <?php foreach ($schedule_by_day[$day] as $sched): ?>
                                <?php
                                $start_minutes = (intval(substr($sched['start_time'], 0, 2)) * 60) + intval(substr($sched['start_time'], 3, 2));
                                $end_minutes = (intval(substr($sched['end_time'], 0, 2)) * 60) + intval(substr($sched['end_time'], 3, 2));
                                $start_7am = 7 * 60;
                                $top = (($start_minutes - $start_7am) / 30) * 30; // 30px per 30 min slot
                                $height = (($end_minutes - $start_minutes) / 30) * 30;
                                $known_subjects = ['automata', 'purposive', 'python', 'techno', 'appdev'];
                                $subject_class = strtolower($sched['subject_code']);
                                // Use default styling for unknown subjects
                                if (!in_array($subject_class, $known_subjects)) {
                                    $subject_class = 'default';
                                }
                                $status_class = '';
                                $status_text = '';
                                if ($sched['status'] === 'Pending Change') {
                                    $status_class = 'style="border: 2px dashed #a5720b;"';
                                    $status_text = '<div class="subject-status"><i class="fa-solid fa-clock"></i> Pending Change</div>';
                                }
                                ?>
                                <div class="schedule-block subject-<?php echo $subject_class; ?>"
                                     style="top: <?php echo $top; ?>px; height: <?php echo $height; ?>px; <?php echo $status_class; ?>"
                                     title="Click to request change">
                                    <div class="subject-name"><?php echo htmlspecialchars($sched['subject_name']); ?></div>
                                    <div class="subject-room"><i class="fa-solid fa-door-open"></i> <?php echo htmlspecialchars($sched['room']); ?></div>
                                    <div class="subject-room"><i class="fa-solid fa-clock"></i> <?php echo date('g:i A', strtotime($sched['start_time'])); ?> - <?php echo date('g:i A', strtotime($sched['end_time'])); ?></div>
                                    <?php echo $status_text; ?>
                                    <div class="schedule-actions">
                                        <button type="button" onclick="openChangeModal(<?php echo htmlspecialchars(json_encode($sched)); ?>); event.stopPropagation();" title="Request change">
                                            <i class="fa-solid fa-pen"></i> Change
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Drop this class? You will need to enroll again to get it back.');" title="Drop class">
                                            <input type="hidden" name="drop_schedule" value="1">
                                            <input type="hidden" name="enrollment_id" value="<?php echo $sched['id']; ?>">
                                            <button type="submit">
                                                <i class="fa-solid fa-trash"></i> Drop
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-day-message">
                                <i class="fa-solid fa-calendar-xmark"></i>
                                <span>No classes scheduled</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Pending Change Requests -->
        <?php
        // Get pending requests
        $pending_query = "SELECT ss.*, s.subject_name, s.room as current_room, s.day_of_week as current_day,
                                 s.start_time as current_start, s.end_time as current_end
                          FROM student_schedules ss
                          JOIN schedules s ON ss.schedule_id = s.id
                          WHERE ss.student_email = ? AND ss.status = 'Pending Change'
                          ORDER BY ss.created_at DESC";
        $pending_stmt = $conn->prepare($pending_query);
        $pending_stmt->bind_param("s", $user_email);
        $pending_stmt->execute();
        $pending_requests = $pending_stmt->get_result();
        $pending_stmt->close();

        // Get history
        $history_query = "SELECT ss.*, s.subject_name, s.room as current_room, s.day_of_week as current_day,
                                 s.start_time as current_start, s.end_time as current_end
                          FROM student_schedules ss
                          JOIN schedules s ON ss.schedule_id = s.id
                          WHERE ss.student_email = ? AND ss.status IN ('Enrolled', 'Dropped') AND ss.admin_remarks IS NOT NULL
                          ORDER BY ss.updated_at DESC LIMIT 10";
        $history_stmt = $conn->prepare($history_query);
        $history_stmt->bind_param("s", $user_email);
        $history_stmt->execute();
        $history_requests = $history_stmt->get_result();
        $history_stmt->close();
        ?>

        <?php if ($pending_requests->num_rows > 0): ?>
            <div class="section-block">
                <h3 class="section-title"><i class="fa-solid fa-clock" style="color: #a5720b;"></i> Pending Schedule Change Requests</h3>
                <?php while ($req = $pending_requests->fetch_assoc()): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div class="request-subject"><?php echo htmlspecialchars($req['subject_name']); ?></div>
                            <span class="status-badge status-pending"><i class="fa-solid fa-clock"></i> Pending Review</span>
                        </div>

                        <div class="request-comparison">
                            <div class="request-box current">
                                <h5>Current Schedule</h5>
                                <div class="request-row">
                                    <span class="request-label">Room:</span>
                                    <span class="request-value"><?php echo htmlspecialchars($req['current_room']); ?></span>
                                </div>
                                <div class="request-row">
                                    <span class="request-label">Day:</span>
                                    <span class="request-value"><?php echo htmlspecialchars($req['current_day']); ?></span>
                                </div>
                                <div class="request-row">
                                    <span class="request-label">Time:</span>
                                    <span class="request-value"><?php echo date('g:i A', strtotime($req['current_start'])); ?> - <?php echo date('g:i A', strtotime($req['current_end'])); ?></span>
                                </div>
                            </div>

                            <div class="request-box requested">
                                <h5>Requested Change</h5>
                                <div class="request-row">
                                    <span class="request-label">Room:</span>
                                    <span class="request-value"><?php echo htmlspecialchars($req['requested_room']); ?></span>
                                </div>
                                <div class="request-row">
                                    <span class="request-label">Day:</span>
                                    <span class="request-value"><?php echo htmlspecialchars($req['requested_day']); ?></span>
                                </div>
                                <div class="request-row">
                                    <span class="request-label">Time:</span>
                                    <span class="request-value"><?php echo date('g:i A', strtotime($req['requested_start_time'])); ?> - <?php echo date('g:i A', strtotime($req['requested_end_time'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($req['change_reason'])): ?>
                            <div class="request-reason">
                                <span class="request-reason-label"><i class="fa-solid fa-comment"></i> Your Reason:</span>
                                <?php echo htmlspecialchars($req['change_reason']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <!-- Request History -->
        <?php if ($history_requests->num_rows > 0): ?>
            <div class="section-block">
                <h3 class="section-title"><i class="fa-solid fa-history" style="color: #7e8590;"></i> Request History</h3>
                <div class="panel panel-table">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Action</th>
                                <th>Requested Change</th>
                                <th>Admin Remarks</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($hist = $history_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($hist['subject_name']); ?></td>
                                    <td>
                                        <?php
                                        if (strpos($hist['admin_remarks'] ?? '', 'Approved') !== false) {
                                            echo '<span class="status-tag status-ready"><i class="fa-solid fa-check"></i> Approved</span>';
                                        } else {
                                            echo '<span class="status-tag status-rejected"><i class="fa-solid fa-xmark"></i> Rejected</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($hist['requested_room'])): ?>
                                            Room: <?php echo htmlspecialchars($hist['requested_room']); ?><br>
                                            Day: <?php echo htmlspecialchars($hist['requested_day']); ?><br>
                                            Time: <?php echo date('g:i A', strtotime($hist['requested_start_time'])); ?> - <?php echo date('g:i A', strtotime($hist['requested_end_time'])); ?>
                                        <?php else: ?>
                                            <span style="color: #8a8f98;">No change requested</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($hist['admin_remarks'] ?? '', 0, 100)) . (strlen($hist['admin_remarks'] ?? '') > 100 ? '...' : ''); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($hist['updated_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Available Classes to Enroll -->
        <?php if ($available_schedules->num_rows > 0): ?>
            <div class="section-block">
                <h3 class="section-title"><i class="fa-solid fa-plus-circle" style="color: #1e7a3c;"></i> Available Classes to Enroll</h3>
                <div class="panel panel-table">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Room</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($avail = $available_schedules->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($avail['subject_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($avail['room']); ?></td>
                                    <td><?php echo htmlspecialchars($avail['day_of_week']); ?></td>
                                    <td><?php echo date('g:i A', strtotime($avail['start_time'])); ?> - <?php echo date('g:i A', strtotime($avail['end_time'])); ?></td>
                                    <td>
                                        <form method="POST" action="student_schedule.php" style="display: inline;">
                                            <input type="hidden" name="enroll_schedule" value="1">
                                            <input type="hidden" name="schedule_id" value="<?php echo $avail['id']; ?>">
                                            <button type="submit" class="reserve-btn" style="font-size: 11px; padding: 4px 10px;">Enroll</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

</main>

<!-- Change Request Modal -->
<div class="modal-overlay" id="changeModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen-to-square" style="color: #d4a017;"></i> Request Schedule Change</h3>
            <button class="modal-close" onclick="closeChangeModal()">&times;</button>
        </div>
        <form method="POST" class="modal-body" id="changeForm">
            <input type="hidden" name="request_change" value="1">
            <input type="hidden" name="schedule_id" id="modal_schedule_id">

            <div class="form-group">
                <label><i class="fa-solid fa-book"></i> Subject</label>
                <input type="text" id="modal_subject_name" readonly style="background: #f0f0f0; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-door-open"></i> Current Room</label>
                <input type="text" id="modal_current_room" readonly style="background: #f0f0f0; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-calendar-day"></i> Current Day</label>
                <input type="text" id="modal_current_day" readonly style="background: #f0f0f0; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-clock"></i> Current Time</label>
                <input type="text" id="modal_current_time" readonly style="background: #f0f0f0; cursor: not-allowed;">
            </div>

            <hr style="border: none; border-top: 1px solid #eef0f2; margin: 20px 0;">

            <div class="form-group">
                <label for="requested_room"><i class="fa-solid fa-door-open"></i> Requested Room <span style="color: #9d2f2f;">*</span></label>
                <input type="text" name="requested_room" id="requested_room" required placeholder="e.g., FIT609, Innovation Center Room 201">
            </div>

            <div class="form-group">
                <label for="requested_day"><i class="fa-solid fa-calendar-day"></i> Requested Day <span style="color: #9d2f2f;">*</span></label>
                <select name="requested_day" id="requested_day" required>
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
                    <label for="requested_start_time"><i class="fa-solid fa-clock"></i> Start Time <span style="color: #9d2f2f;">*</span></label>
                    <input type="time" name="requested_start_time" id="requested_start_time" required>
                </div>
                <div class="form-group">
                    <label for="requested_end_time"><i class="fa-solid fa-clock"></i> End Time <span style="color: #9d2f2f;">*</span></label>
                    <input type="time" name="requested_end_time" id="requested_end_time" required>
                </div>
            </div>

            <div class="form-group">
                <label for="change_reason"><i class="fa-solid fa-comment"></i> Reason for Change <span style="color: #9d2f2f;">*</span></label>
                <textarea name="change_reason" id="change_reason" rows="3" required placeholder="Please explain why you need this schedule change..."></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeChangeModal()"><i class="fa-solid fa-xmark"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script src="dashboardscript.js"></script>
<script>
    // Modal functions
    function openChangeModal(schedule) {
        document.getElementById('modal_schedule_id').value = schedule.schedule_id;
        document.getElementById('modal_subject_name').value = schedule.subject_name;
        document.getElementById('modal_current_room').value = schedule.room;
        document.getElementById('modal_current_day').value = schedule.day_of_week;
        document.getElementById('modal_current_time').value = formatTime(schedule.start_time) + ' - ' + formatTime(schedule.end_time);

        // Pre-fill with current values
        document.getElementById('requested_room').value = schedule.room;
        document.getElementById('requested_day').value = schedule.day_of_week;
        document.getElementById('requested_start_time').value = schedule.start_time;
        document.getElementById('requested_end_time').value = schedule.end_time;
        document.getElementById('change_reason').value = '';

        document.getElementById('changeModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeChangeModal() {
        document.getElementById('changeModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function formatTime(timeStr) {
        const [hours, minutes] = timeStr.split(':');
        const h = parseInt(hours);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12 = h % 12 || 12;
        return h12 + ':' + minutes + ' ' + ampm;
    }

    // Close modal on overlay click
    document.getElementById('changeModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeChangeModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeChangeModal();
        }
    });

    // Highlight current time slot
    function highlightCurrentTime() {
        const now = new Date();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();
        const slots = document.querySelectorAll('.time-slot');
        slots.forEach(slot => {
            const slotText = slot.textContent.trim();
            const [time, period] = slotText.split(' ');
            let [hours, minutes] = time.split(':').map(Number);
            if (period === 'PM' && hours !== 12) hours += 12;
            if (period === 'AM' && hours === 12) hours = 0;
            const slotMinutes = hours * 60 + minutes;
            if (currentMinutes >= slotMinutes && currentMinutes < slotMinutes + 30) {
                slot.classList.add('current-time');
            }
        });
    }

    highlightCurrentTime();
    // Update every minute
    setInterval(highlightCurrentTime, 60000);
</script>
</body>
</html>