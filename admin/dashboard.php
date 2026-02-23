<?php
/**
 * APM - Admin Dashboard
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);

$pdo = getDB();
$user = currentUser();

// Statistics
$stats = [];

// Total employees
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE active = 1 AND role_id = 3");
$stats['total_employees'] = $stmt->fetchColumn();

// Pending leaves
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
$stats['pending'] = $stmt->fetchColumn();

// Approved this month
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
$stmt->execute();
$stats['approved_month'] = $stmt->fetchColumn();

// Absent today
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM leave_requests WHERE status = 'approved' AND ? BETWEEN start_date AND end_date");
$stmt->execute([$today]);
$stats['absent_today'] = $stmt->fetchColumn();

// Supervisor pending
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'supervisor_approved'");
$stats['supervisor_approved'] = $stmt->fetchColumn();

// Total teams
$stats['total_teams'] = $pdo->query("SELECT COUNT(*) FROM teams WHERE active=1")->fetchColumn();

// Recent leave requests
$recentLeaves = $pdo->query(
    "SELECT lr.*, u.first_name, u.last_name, t.name as team_name, t.code as team_code, s.name as shift_name
     FROM leave_requests lr
     JOIN users u ON lr.user_id = u.id
     JOIN teams t ON lr.team_id = t.id
     JOIN shifts s ON lr.shift_id = s.id
     ORDER BY lr.created_at DESC LIMIT 10"
)->fetchAll();

// Monthly leave stats by shift
$monthlyStats = $pdo->query(
    "SELECT s.name as shift_name, COUNT(lr.id) as count
     FROM shifts s
     LEFT JOIN leave_requests lr ON s.id = lr.shift_id 
         AND MONTH(lr.created_at) = MONTH(NOW())
         AND YEAR(lr.created_at) = YEAR(NOW())
     GROUP BY s.id, s.name"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
$activePage = 'dashboard';
include BASE_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold text-dark mb-0"><i class="bi bi-speedometer2 text-primary me-2"></i>Admin Dashboard</h4>
                <p class="text-muted mb-0 small"><?= date('l, d F Y') ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="/APM/admin/leaves.php?status=supervisor_approved" class="btn btn-warning btn-sm">
                    <i class="bi bi-bell-fill me-1"></i>Awaiting Approval
                    <?php if ($stats['supervisor_approved'] > 0): ?>
                        <span class="badge bg-white text-dark ms-1"><?= $stats['supervisor_approved'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="/APM/admin/reports.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-bar-chart me-1"></i>Reports
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $stats['total_employees'] ?></div>
                    <div class="text-muted small">Employees</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $stats['pending'] ?></div>
                    <div class="text-muted small">Pending</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $stats['supervisor_approved'] ?></div>
                    <div class="text-muted small">Sup. Approved</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-calendar-check"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $stats['approved_month'] ?></div>
                    <div class="text-muted small">Approved/Month</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-person-x"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $stats['absent_today'] ?></div>
                    <div class="text-muted small">Absent Today</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card stat-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-diagram-3"></i></div>
                <div>
                    <div class="fw-bold fs-4"><?= $stats['total_teams'] ?></div>
                    <div class="text-muted small">Teams</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Leave Requests -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Recent Leave Requests</h6>
                <a href="/APM/admin/leaves.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Team/Shift</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLeaves)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No leave requests yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentLeaves as $lr): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-sm"><?= strtoupper(substr($lr['first_name'],0,1).substr($lr['last_name'],0,1)) ?></div>
                                        <div>
                                            <div class="fw-semibold small"><?= e($lr['first_name'].' '.$lr['last_name']) ?></div>
                                            <div class="text-muted" style="font-size:0.75rem"><?= e($lr['request_number']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark"><?= e($lr['team_code']) ?></span>
                                    <div class="text-muted" style="font-size:0.75rem"><?= e($lr['shift_name']) ?></div>
                                </td>
                                <td>
                                    <div style="font-size:0.8rem"><?= formatDate($lr['start_date']) ?></div>
                                    <div class="text-muted" style="font-size:0.75rem">to <?= formatDate($lr['end_date']) ?></div>
                                </td>
                                <td><span class="fw-bold"><?= $lr['total_days'] ?></span></td>
                                <td><?= statusBadge($lr['status']) ?></td>
                                <td>
                                    <a href="/APM/admin/leave_detail.php?id=<?= $lr['id'] ?>" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.75rem">Review</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Stats -->
    <div class="col-lg-4">
        <!-- Monthly by Shift -->
        <div class="card mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2"></i>This Month by Shift</h6>
            </div>
            <div class="card-body">
                <?php foreach ($monthlyStats as $ms): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold"><?= e($ms['shift_name']) ?></span>
                        <span class="text-muted"><?= $ms['count'] ?> requests</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <?php $max = max(array_column($monthlyStats, 'count'), 1); ?>
                        <div class="progress-bar" style="width:<?= ($ms['count']/$max)*100 ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="/APM/admin/users.php?action=add" class="btn btn-outline-primary btn-sm text-start">
                    <i class="bi bi-person-plus me-2"></i>Add New User
                </a>
                <a href="/APM/admin/shifts.php?action=add" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="bi bi-plus-circle me-2"></i>Add Shift/Team
                </a>
                <a href="/APM/admin/holidays.php?action=add" class="btn btn-outline-success btn-sm text-start">
                    <i class="bi bi-star me-2"></i>Add Holiday
                </a>
                <a href="/APM/admin/reports.php?export=pdf" class="btn btn-outline-danger btn-sm text-start">
                    <i class="bi bi-file-pdf me-2"></i>Export PDF Report
                </a>
                <a href="/APM/admin/backup.php" class="btn btn-outline-dark btn-sm text-start">
                    <i class="bi bi-cloud-download me-2"></i>Database Backup
                </a>
                <a href="/APM/admin/logs.php" class="btn btn-outline-info btn-sm text-start">
                    <i class="bi bi-journal-text me-2"></i>Activity Logs
                </a>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
