<?php
/**
 * APM - Common HTML Header
 * Variables expected: $pageTitle (string), $activePage (string)
 */
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

$user = currentUser();
$unreadCount = isLoggedIn() ? getUnreadNotificationCount($user['id']) : 0;
$csrfToken = generateCsrfToken();
$flash = getFlash();
$role = $user['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a3c6e">
    <meta name="description" content="APM Leave Management System">
    <title><?= e($pageTitle ?? 'APM') ?> - APM Leave Management</title>
    
    <!-- PWA -->
    <link rel="manifest" href="/APM/manifest.json">
    <link rel="apple-touch-icon" href="/APM/assets/icons/icon-192.png">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FullCalendar -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/APM/assets/css/main.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary-custom shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/APM/<?= $role === 'admin' ? 'admin' : ($role === 'supervisor' ? 'supervisor' : 'operator') ?>/dashboard.php">
            <img src="/APM/assets/icons/icon-48.png" alt="APM" width="30" class="me-2">
            APM Leave
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'dashboard' ? 'active' : '' ?>" href="/APM/admin/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'users' ? 'active' : '' ?>" href="/APM/admin/users.php"><i class="bi bi-people"></i> Users</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'leaves' ? 'active' : '' ?>" href="/APM/admin/leaves.php"><i class="bi bi-calendar-check"></i> Leave Requests</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'shifts' ? 'active' : '' ?>" href="/APM/admin/shifts.php"><i class="bi bi-clock-history"></i> Shifts & Teams</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'holidays' ? 'active' : '' ?>" href="/APM/admin/holidays.php"><i class="bi bi-star"></i> Holidays</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'reports' ? 'active' : '' ?>" href="/APM/admin/reports.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'settings' ? 'active' : '' ?>" href="/APM/admin/settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                <?php elseif ($role === 'supervisor'): ?>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'dashboard' ? 'active' : '' ?>" href="/APM/supervisor/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'leaves' ? 'active' : '' ?>" href="/APM/supervisor/leaves.php"><i class="bi bi-calendar-check"></i> Leave Requests</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'calendar' ? 'active' : '' ?>" href="/APM/supervisor/calendar.php"><i class="bi bi-calendar3"></i> Calendar</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'reports' ? 'active' : '' ?>" href="/APM/supervisor/reports.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'dashboard' ? 'active' : '' ?>" href="/APM/operator/dashboard.php"><i class="bi bi-house"></i> Home</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'apply' ? 'active' : '' ?>" href="/APM/operator/apply.php"><i class="bi bi-plus-circle"></i> Apply Leave</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'my_leaves' ? 'active' : '' ?>" href="/APM/operator/my_leaves.php"><i class="bi bi-list-ul"></i> My Leaves</a></li>
                    <li class="nav-item"><a class="nav-link <?= ($activePage??'') === 'calendar' ? 'active' : '' ?>" href="/APM/operator/calendar.php"><i class="bi bi-calendar3"></i> Calendar</a></li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav align-items-center">
                <!-- Notifications Bell -->
                <li class="nav-item me-2">
                    <a class="nav-link position-relative" href="/APM/<?= $role === 'admin' ? 'admin' : ($role === 'supervisor' ? 'supervisor' : 'operator') ?>/notifications.php">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <div class="avatar-sm me-2"><?= strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1)) ?></div>
                        <span class="d-none d-md-inline"><?= e($user['full_name']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?= e($user['role']) ?></h6></li>
                        <li><a class="dropdown-item" href="/APM/<?= $role ?>/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/APM/api/auth.php?action=logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash Messages -->
<?php if ($flash): ?>
<div class="container-fluid pt-2">
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<input type="hidden" id="csrfToken" value="<?= e($csrfToken) ?>">
<div class="container-fluid py-3">
