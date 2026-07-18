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

// Handle approve schedule change
if (isset($_GET['approve'])) {
    $request_id = (int)$_GET['approve'];

    // Get the request details first
    $get_query = "SELECT ss.*, s.subject_name, s.room as current_room, s.day_of_week as current_day,
                         s.start_time as current_start, s.end_time as current_end
                  FROM student_schedules ss
                  JOIN schedules s ON ss.schedule_id = s.id
                  WHERE ss.id = ? AND ss.status = 'Pending Change'";
    $get_stmt = $conn->prepare($get_query);
    $get_stmt->bind_param("i", $request_id);
    $get_stmt->execute();
    $request = $get_stmt->get_result()->fetch_assoc();
    $get_stmt->close();

    if ($request) {
        // Create a new schedule entry with the requested values from student_schedules
        $insert_schedule_query = "INSERT INTO schedules (subject_code, subject_name, room, day_of_week, start_time, end_time, semester, status)
                                  SELECT s.subject_code, s.subject_name, ss.requested_room, ss.requested_day, ss.requested_start_time, ss.requested_end_time, s.semester, 'Active'
                                  FROM schedules s
                                  JOIN student_schedules ss ON ss.schedule_id = s.id
                                  WHERE ss.id = ?";
        $insert_stmt = $conn->prepare($insert_schedule_query);
        $insert_stmt->bind_param("i", $request_id);

        if ($insert_stmt->execute()) {
            $new_schedule_id = $conn->insert_id;
            $insert_stmt->close();

            // Update student_schedules to point to the new schedule and clear requested fields
            $update_query = "UPDATE student_schedules
                             SET schedule_id = ?,
                                 status = 'Enrolled',
                                 requested_room = NULL,
                                 requested_day = NULL,
                                 requested_start_time = NULL,
                                 requested_end_time = NULL,
                                 change_reason = NULL,
                                 admin_remarks = CONCAT(IFNULL(admin_remarks, ''), 'Approved on ', NOW(), '; '),
                                 updated_at = NOW()
                             WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ii", $new_schedule_id, $request_id);

            if ($update_stmt->execute()) {
                $success_message = "Schedule change approved successfully.";
            } else {
                $error_message = "Failed to update student schedule.";
            }
            $update_stmt->close();
        } else {
            $insert_stmt->close();
            $error_message = "Failed to create new schedule entry.";
        }
    } else {
        $error_message = "Request not found or already processed.";
    }
}

// Handle reject schedule change
if (isset($_GET['reject']) && isset($_GET['remarks'])) {
    $request_id = (int)$_GET['reject'];
    $remarks = trim($_GET['remarks']);

    if (empty($remarks)) {
        $error_message = "Rejection remarks are required.";
    } else {
        $update_query = "UPDATE student_schedules
                         SET status = 'Enrolled',
                             requested_room = NULL,
                             requested_day = NULL,
                             requested_start_time = NULL,
                             requested_end_time = NULL,
                             change_reason = NULL,
                             admin_remarks = CONCAT(IFNULL(admin_remarks, ''), 'Rejected: ', ?, '; '),
                             updated_at = NOW()
                         WHERE id = ? AND status = 'Pending Change'";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $remarks, $request_id);
        if ($update_stmt->execute()) {
            $success_message = "Schedule change rejected.";
        } else {
            $error_message = "Failed to reject schedule change.";
        }
        $update_stmt->close();
    }
}

// Get all pending schedule change requests
$pending_query = "SELECT ss.*, u.first_name, u.last_name, u.student_number, u.email as student_email,
                         s.subject_name, s.room as current_room, s.day_of_week as current_day,
                         s.start_time as current_start, s.end_time as current_end
                  FROM student_schedules ss
                  JOIN users u ON ss.student_email = u.email
                  JOIN schedules s ON ss.schedule_id = s.id
                  WHERE ss.status = 'Pending Change'
                  ORDER BY ss.created_at ASC";
$pending_requests = $conn->query($pending_query);

// Get all processed requests (approved/rejected)
$history_query = "SELECT ss.*, u.first_name, u.last_name, u.student_number,
                         s.subject_name, s.room as current_room, s.day_of_week as current_day,
                         s.start_time as current_start, s.end_time as current_end
                  FROM student_schedules ss
                  JOIN users u ON ss.student_email = u.email
                  JOIN schedules s ON ss.schedule_id = s.id
                  WHERE ss.status IN ('Enrolled', 'Dropped') AND ss.admin_remarks IS NOT NULL
                  ORDER BY ss.updated_at DESC LIMIT 20";
$history_requests = $conn->query($history_query);

// Get admin user info
$user_query = "SELECT email, first_name, last_name, role FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("s", $user_email);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];

// Set current page for sidebar active state
$current_page = 'schedule_approvals';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tams-Hub | Schedule Approvals</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <link rel="stylesheet" href="dashboardstyle_admin.css" />
    <style>
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
            align-items: flex-start;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .request-student {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .student-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #eef1b4;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #064814;
            font-weight: 700;
            font-size: 16px;
        }
        .student-info h4 {
            margin: 0 0 4px;
            color: #2c313a;
            font-size: 16px;
        }
        .student-meta {
            font-size: 13px;
            color: #7e8590;
        }
        .status-pending {
            background: #fdf3e0;
            color: #a5720b;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-approved {
            background: #e5f6e8;
            color: #1e7a3c;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-rejected {
            background: #fde7e7;
            color: #9d2f2f;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .schedule-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .schedule-box {
            background: #fbfcf7;
            border: 1px solid rgba(12, 93, 31, 0.12);
            border-radius: 12px;
            padding: 16px;
        }
        .schedule-box.current {
            border-color: #d4a017;
        }
        .schedule-box.requested {
            border-color: #1e7a3c;
        }
        .schedule-box h5 {
            margin: 0 0 12px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #7e8590;
        }
        .schedule-box.current h5 { color: #a5720b; }
        .schedule-box.requested h5 { color: #1e7a3c; }
        .schedule-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .schedule-row:last-child { margin-bottom: 0; }
        .schedule-label { color: #7e8590; }
        .schedule-value { font-weight: 600; color: #2c313a; }

        .reason-box {
            background: #f5f5f5;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 13px;
            line-height: 1.6;
            color: #2c313a;
        }
        .reason-label { font-weight: 700; color: #064814; margin-bottom: 4px; display: block; }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-approve {
            background: #1e7a3c;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-approve:hover { background: #165e2e; }
        .btn-reject {
            background: #a43b3b;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-reject:hover { background: #8b2f2f; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(4, 78, 25, 0.6);
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
            max-width: 450px;
        }
        .modal-header {
            background: #a43b3b;
            color: #fff;
            padding: 18px 24px;
            border-radius: 18px 18px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 17px; }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 22px;
            cursor: pointer;
            opacity: 0.8;
        }
        .modal-close:hover { opacity: 1; }
        .modal-body { padding: 24px; }
        .modal-body .form-group { margin-bottom: 18px; }
        .modal-body label {
            display: block;
            font-weight: 700;
            color: #2c313a;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .modal-body textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            background: #fafbf7;
            box-sizing: border-box;
        }
        .modal-body textarea:focus {
            border-color: #d4a017;
            outline: none;
            background: #fff;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 8px;
        }
        .btn-modal-cancel {
            background: #2c313a;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-modal-confirm {
            background: #a43b3b;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7e8590;
        }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        .empty-state h3 { color: #2c313a; margin-bottom: 8px; }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .section-header h3 { margin: 0; color: #2c313a; }

        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #8a8f98;
            padding: 14px 12px;
            border-bottom: 1px solid #eef0f2;
        }
        .history-table td {
            font-size: 13px;
            color: #2c313a;
            padding: 14px 12px;
            border-bottom: 1px solid #f4f5f7;
        }

        @media (max-width: 768px) {
            .schedule-comparison { grid-template-columns: 1fr; }
            .request-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/admin_sidebar.php'; ?>

<main class="content-wrapper">
        <header class="topbar">
            <button class="menu-btn" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fa-solid fa-bars"></i></button>
            <div class="topbar-title">Schedule Approvals</div>
            <div class="topbar-right">
                <i class="fa-regular fa-bell"></i>
                <div class="user-chip"><i class="fa-solid fa-circle-user"></i><span><?php echo htmlspecialchars($full_name); ?></span></div>
                <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="page-content">

            <?php if (!empty($success_message)): ?>
                <div class="alert-banner alert-success" padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert-banner alert-error" padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Pending Requests Section -->
            <div class="section-header">
                <h3 class="section-title"><i class="fa-solid fa-clock" style="color: #a5720b;"></i> Pending Schedule Change Requests</h3>
            </div>

            <?php if ($pending_requests->num_rows > 0): ?>
                <?php while ($req = $pending_requests->fetch_assoc()): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div class="request-student">
                                <div class="student-avatar">
                                    <?php echo strtoupper($req['first_name'][0] . $req['last_name'][0]); ?>
                                </div>
                                <div class="student-info">
                                    <h4><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></h4>
                                    <div class="student-meta">
                                        <?php echo htmlspecialchars($req['student_number']); ?> • <?php echo htmlspecialchars($req['student_email']); ?>
                                    </div>
                                </div>
                            </div>
                            <span class="status-pending">Pending Review</span>
                        </div>

                        <div class="schedule-comparison">
                            <div class="schedule-box current">
                                <h5>Current Schedule</h5>
                                <div class="schedule-row">
                                    <span class="schedule-label">Subject:</span>
                                    <span class="schedule-value"><?php echo htmlspecialchars($req['subject_name']); ?></span>
                                </div>
                                <div class="schedule-row">
                                    <span class="schedule-label">Room:</span>
                                    <span class="schedule-value"><?php echo htmlspecialchars($req['current_room']); ?></span>
                                </div>
                                <div class="schedule-row">
                                    <span class="schedule-label">Day:</span>
                                    <span class="schedule-value"><?php echo htmlspecialchars($req['current_day']); ?></span>
                                </div>
                                <div class="schedule-row">
                                    <span class="schedule-label">Time:</span>
                                    <span class="schedule-value"><?php echo date('h:i A', strtotime($req['current_start'])); ?> - <?php echo date('h:i A', strtotime($req['current_end'])); ?></span>
                                </div>
                            </div>

                            <div class="schedule-box requested">
                                <h5>Requested Change</h5>
                                <div class="schedule-row">
                                    <span class="schedule-label">Room:</span>
                                    <span class="schedule-value"><?php echo htmlspecialchars($req['requested_room']); ?></span>
                                </div>
                                <div class="schedule-row">
                                    <span class="schedule-label">Day:</span>
                                    <span class="schedule-value"><?php echo htmlspecialchars($req['requested_day']); ?></span>
                                </div>
                                <div class="schedule-row">
                                    <span class="schedule-label">Time:</span>
                                    <span class="schedule-value"><?php echo date('h:i A', strtotime($req['requested_start_time'])); ?> - <?php echo date('h:i A', strtotime($req['requested_end_time'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($req['change_reason'])): ?>
                            <div class="reason-box">
                                <span class="reason-label"><i class="fa-solid fa-comment"></i> Student's Reason:</span>
                                <?php echo htmlspecialchars($req['change_reason']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="action-buttons">
                            <a href="?approve=<?php echo $req['id']; ?>" class="btn-approve" onclick="return confirm('Approve this schedule change?');">
                                <i class="fa-solid fa-check"></i> Approve
                            </a>
                            <button class="btn-reject" onclick="openRejectModal(<?php echo $req['id']; ?>)">
                                <i class="fa-solid fa-xmark"></i> Reject
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-check"></i>
                    <h3>No Pending Requests</h3>
                    <p>All schedule change requests have been processed.</p>
                </div>
            <?php endif; ?>

            <!-- History Section -->
            <div class="section-header">
                <h3 class="section-title"><i class="fa-solid fa-history" style="color: #7e8590;"></i> Approval History</h3>
            </div>

            <div class="panel" style="background: #fff; border-radius: 18px; box-shadow: 0 8px 24px rgba(4, 78, 25, 0.14); padding: 0; overflow: hidden;">
                <?php if ($history_requests->num_rows > 0): ?>
                    <div class="table-wrap" style="overflow-x: auto;">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
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
                                        <td>
                                            <strong><?php echo htmlspecialchars($hist['first_name'] . ' ' . $hist['last_name']); ?></strong><br>
                                            <small style="color: #8a8f98;"><?php echo htmlspecialchars($hist['student_number']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($hist['subject_name']); ?></td>
                                        <td>
                                            <?php
                                            if (strpos($hist['admin_remarks'] ?? '', 'Approved') !== false) {
                                                echo '<span class="status-tag status-ready">Approved</span>';
                                            } else {
                                                echo '<span class="status-tag status-rejected">Rejected</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($hist['requested_room'])): ?>
                                                Room: <?php echo htmlspecialchars($hist['requested_room']); ?><br>
                                                Day: <?php echo htmlspecialchars($hist['requested_day']); ?><br>
                                                Time: <?php echo date('h:i A', strtotime($hist['requested_start_time'])); ?> - <?php echo date('h:i A', strtotime($hist['requested_end_time'])); ?>
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
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-history"></i>
                        <h3>No History Yet</h3>
                        <p>Approved or rejected requests will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-xmark"></i> Reject Schedule Change</h3>
                <button class="modal-close" onclick="closeRejectModal()">&times;</button>
            </div>
            <form method="GET" class="modal-body" id="rejectForm">
                <input type="hidden" name="reject" id="rejectRequestId">
                <div class="form-group">
                    <label for="rejectRemarks">Rejection Remarks <span style="color: #9d2f2f;">*</span></label>
                    <textarea name="remarks" id="rejectRemarks" required placeholder="Explain why this request is being rejected..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-modal-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-modal-confirm"><i class="fa-solid fa-xmark"></i> Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script src="dashboardscript_admin.js"></script>
    <script>
        let currentRejectId = null;

        function openRejectModal(requestId) {
            currentRejectId = requestId;
            document.getElementById('rejectRequestId').value = requestId;
            document.getElementById('rejectRemarks').value = '';
            document.getElementById('rejectModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.body.style.overflow = '';
            currentRejectId = null;
        }

        // Close modal on overlay click
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRejectModal();
            }
        });
    </script>
</body>
</html>