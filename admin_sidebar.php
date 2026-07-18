<?php
/**
 * Admin Sidebar Navigation
 * Include this in all admin-facing pages
 *
 * Usage:
 *   $current_page = 'dashboard'; // or 'schedule', 'schedule_approvals', 'add_resource', 'edit_resource', etc.
 *   include 'admin_sidebar.php';
 */

// Determine current page for active state
$current_page = $current_page ?? 'dashboard';
$full_name = $full_name ?? 'Admin';
$user_email = $user_email ?? '';

// Navigation items configuration
$nav_items = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'url' => 'admin_dashboard.php',
        'is_anchor' => false
    ],
    'reservations' => [
        'label' => 'Reservations',
        'icon' => 'fa-clipboard-list',
        'url' => 'admin_dashboard.php?section=reservations',
        'is_anchor' => false
    ],
    'schedule' => [
        'label' => 'Manage Schedules',
        'icon' => 'fa-calendar-days',
        'url' => 'admin_schedule.php',
        'is_anchor' => false
    ],
    'schedule_approvals' => [
        'label' => 'Schedule Approvals',
        'icon' => 'fa-calendar-check',
        'url' => 'admin_schedule_approvals.php',
        'is_anchor' => false
    ],
    'users' => [
        'label' => 'User Management',
        'icon' => 'fa-users-cog',
        'url' => 'admin_dashboard.php?section=users',
        'is_anchor' => false
    ],
    'resources' => [
        'label' => 'Resources',
        'icon' => 'fa-boxes-stacked',
        'url' => 'admin_dashboard.php?section=resources',
        'is_anchor' => false
    ],
    'reports' => [
        'label' => 'Reports',
        'icon' => 'fa-chart-line',
        'url' => 'admin_dashboard.php?section=reports',
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
        <div class="brand-subtitle">ADMIN RESOURCE<br />MANAGEMENT CENTER</div>
    </a>

    <hr class="sidebar-divider" />

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