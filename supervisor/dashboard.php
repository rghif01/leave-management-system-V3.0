<?php
/**
 * APM - Supervisor Dashboard
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['supervisor', 'admin']);

$pdo  = getDB();
$user = currentUser();

// Supervisor sees only their team
$teamId  = $user['team_id'];
$shiftId = $user['shift_id'];

// Team info
$teamInfo = null;
if ($teamId) {
    $stmt = $pdo->prepare("SELECT t.*, s.name as shift_name, s.color from teams t JOIN shifts s ON t.shift_id=s.id WHERE t.id=?");
    $stmt->execute([$teamId]);
    $teamInfo = $stmt->fetch();
}

// Stats
$pending = 0;
$approved = 0;
$absentToday = 0;
$teamMembers = 0;

if ($teamId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE team_id=? AND status='pending'"); $stmt->execute([$teamId]); $pending = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE team_id=? AND status='approved' AND MONTH(created_at)=MONTH(NOW())"); $stmt->execute([$teamId]); $approved = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM leave_requests WHERE team_id=? AND status='approved' AND ? BETWEEN start_date AND end_date"); $stmt->execute([$teamId, date('Y-m-d')]); $absentToday = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE team_id=? AND active=1 AND role_id=3"); $stmt->execute([$teamId]); $teamMembers = $stmt->fetchColumn();
}

// Recent leave requests for this team
$recentLeaves = [];
if ($teamId) {
    $stmt = $pdo->prepare(
        "SELECT lr.*, u.first_name, u.last_name FROM leave_requests lr JOIN users u ON lr.user_id=u.id 
         WHERE lr.team_id=? ORDER BY lr.created_at DESC LIMIT 8"
    );
    $stmt->execute([$teamId]);
    $recentLeaves = $stmt->fetchAll();
}

// Today's schedule
$todaySchedule = $shiftId ? getShiftSchedule($shiftId, date('Y-m-d')) : 'unknown';

$pageTitle = 'Supervisor Dashboard';
$activePage = 'dashboard';
include BASE_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 text-primary me-2"></i>Supervisor Dashboard</h4>
            <p class="text-muted mb-0 small">
                <?php if ($teamInfo): ?>
                    <?= e($teamInfo['shift_name']) ?> &mdash; 
                    <span class="badge bg-primary"><?= e($teamInfo['code']) ?></span> <?= e($teamInfo['name']) ?>
                <?php endif; ?>
                &mdash; <?= date('l, d F Y') ?>
            </p>
        </div>
        <?php if ($shiftId): ?>
        <div><?= scheduleBadge($todaySchedule) ?> <span class="text-muted small ms-1">Today's schedule</span></div>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card text-center">
            <div class="card-body"><div class="fs-2 fw-bold text-primary"><?= $teamMembers ?></div><div class="text-muted small">Team Members</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-center <?= $pending > 0 ? 'border-warning' : '' ?>">
            <div class="card-body"><div class="fs-2 fw-bold text-warning"><?= $pending ?></div><div class="text-muted small">Pending Approval</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-center">
            <div class="card-body"><div class="fs-2 fw-bold text-success"><?= $approved ?></div><div class="text-muted small">Approved/Month</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-center <?= $absentToday > 0 ? 'border-danger' : '' ?>">
            <div class="card-body"><div class="fs-2 fw-bold text-danger"><?= $absentToday ?></div><div class="text-muted small">Absent Today</div></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between">
                <h6 class="mb-0 fw-bold">Team Leave Requests</h6>
                <a href="/APM/supervisor/leaves.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Employee</th><th>Dates</th><th>Days</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentLeaves)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No requests yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($recentLeaves as $lr): ?>
                            <tr>
                                <td>
                                    <div class="avatar-sm d-inline-flex me-2"><?= strtoupper(substr($lr['first_name'],0,1).substr($lr['last_name'],0,1)) ?></div>
                                    <span class="fw-semibold small"><?= e($lr['first_name'].' '.$lr['last_name']) ?></span>
                                </td>
                                <td class="small"><?= formatDate($lr['start_date']) ?> - <?= formatDate($lr['end_date']) ?></td>
                                <td class="fw-bold"><?= $lr['total_days'] ?></td>
                                <td><?= statusBadge($lr['status']) ?></td>
                                <td>
                                    <a href="/APM/supervisor/leave_detail.php?id=<?= $lr['id'] ?>" class="btn btn-xs btn-outline-info py-0 px-2" style="font-size:0.75rem">
                                        <?= $lr['status'] === 'pending' ? 'Review' : 'View' ?>
                                    </a>
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
    
    <div class="col-lg-4">
        <!-- Team Capacity Today -->
        <div class="card mb-3">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Team Capacity - Today</h6></div>
            <div class="card-body">
                <?php if ($teamInfo): 
                    $maxPerDay = $teamInfo['max_leave_per_day'];
                    $currentlyOut = getTeamLeaveCount($teamId, date('Y-m-d'));
                    $pct = $maxPerDay > 0 ? ($currentlyOut / $maxPerDay) * 100 : 0;
                    $color = $currentlyOut >= $maxPerDay ? 'danger' : ($currentlyOut >= $maxPerDay - 1 ? 'warning' : 'success');
                ?>
                <div class="text-center mb-3">
                    <div class="fs-1 fw-bold text-<?= $color ?>"><?= $currentlyOut ?></div>
                    <div class="text-muted">out of <?= $maxPerDay ?> max</div>
                </div>
                <div class="progress" style="height:10px">
                    <div class="progress-bar bg-<?= $color ?>" style="width:<?= min(100,$pct) ?>%"></div>
                </div>
                <div class="mt-2 text-center small text-<?= $color ?>">
                    <?php if ($currentlyOut >= $maxPerDay): ?>⚠️ Team at capacity<?php else: ?>✅ <?= $maxPerDay - $currentlyOut ?> slot(s) available<?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Quick Actions</h6></div>
            <div class="card-body d-grid gap-2">
                <a href="/APM/supervisor/leaves.php?status=pending" class="btn btn-outline-warning btn-sm text-start">
                    <i class="bi bi-hourglass-split me-2"></i>Review Pending (<?= $pending ?>)
                </a>
                <a href="/APM/supervisor/calendar.php" class="btn btn-outline-primary btn-sm text-start">
                    <i class="bi bi-calendar3 me-2"></i>Team Calendar
                </a>
                <a href="/APM/supervisor/reports.php" class="btn btn-outline-success btn-sm text-start">
                    <i class="bi bi-bar-chart me-2"></i>Team Reports
                </a>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
