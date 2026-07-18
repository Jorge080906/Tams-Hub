<?php
/**
 * Student Sidebar Navigation
 * Include this in all student-facing pages
 *
 * Usage:
 *   $current_page = 'dashboard'; // or 'schedule', 'reserve', etc.
 *   include 'student_sidebar.php';
 */

// Determine current page for active state
$current_page = $current_page ?? 'dashboard';
$full_name = $full_name ?? 'Student';
$user_email = $user_email ?? '';

// Navigation items configuration
$nav_items = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'url' => 'student_dashboard.php',
        'is_anchor' => false
    ],
    'schedule' => [
        'label' => 'My Schedule',
        'icon' => 'fa-calendar-days',
        'url' => 'student_schedule.php',
        'is_anchor' => false
    ],
    'resources' => [
        'label' => 'Available Resources',
        'icon' => 'fa-boxes-stacked',
        'url' => 'student_dashboard.php?section=resources',
        'is_anchor' => false
    ],
    'reservations' => [
        'label' => 'My Reservations',
        'icon' => 'fa-clock-rotate-left',
        'url' => 'student_dashboard.php?section=history',
        'is_anchor' => false
    ],
    'reserve' => [
        'label' => 'New Reservation',
        'icon' => 'fa-calendar-plus',
        'url' => 'reserve_resource.php',
        'is_anchor' => false
    ]
];
?>

<!-- SIDEBAR -->
<aside class="main-sidebar" id="sidebar">
    <a href="#" class="brand-link">
        <div class="brand-logo">
            <img src="FEU_LOGOpng.png" alt="Tams-Hub Logo" class="brand-image move-up">
        </div>
        <h1 class="brand-title">Tams-Hub</h1>
        <div class="brand-subtitle">STUDENT ONLINE<br>RESOURCE CENTER</div>
    </a>

    <hr class="sidebar-divider">

    <div class="sidebar">
        <nav>
            <ul class="nav-sidebar">
                <?php foreach ($nav_items as $page_key => $item): ?>
                    <li class="nav-item <?php echo $current_page === $page_key ? 'active' : ''; ?>">
                        <a href="<?php echo htmlspecialchars($item['url']); ?>" class="nav-link">
                            <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                            <p><?php echo $item['label']; ?></p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
</aside>