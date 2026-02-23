<?php
/**
 * APM - Operator Dashboard
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['operator', 'supervisor', 'admin']);

$pdo  = getDB();
$user = currentUser();
$uid  = $user['id'];
$year = (int)date('Y');

// Balance
$balance = getLeaveBalance($uid, $year);

// My recent requests
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$uid]);
$myLeaves = $stmt->fetchAll();

// Pending count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status='pending'");
$stmt->execute([$uid]); $pendingCount = $stmt->fetchColumn();

// Approved count this year
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status='approved' AND YEAR(start_date)=?");
$stmt->execute([$uid, $year]); $approvedCount = $stmt->fetchColumn();

// Upcoming leaves
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id=? AND status='approved' AND start_date >= CURDATE() ORDER BY start_date LIMIT 3");
$stmt->execute([$uid]); $upcoming = $stmt->fetchAll();

// Today's schedule
$todaySchedule = $user['shift_id'] ? getShiftSchedule($user['shift_id'], date('Y-m-d')) : 'unknown';

$pageTitle = 'My Dashboard';
$activePage = 'dashboard';
include BASE_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Welcome, <?= e($user['first_name']) ?>! 👋</h4>
            <p class="text-muted mb-0 small"><?= date('l, d F Y') ?></p>
        </div>
        <?php if ($user['shift_id']): ?>
        <div class="d-flex align-items-center gap-2">
            <?= scheduleBadge($todaySchedule) ?>
            <span class="text-muted small">Today's schedule</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Balance Card - Prominent -->
<div class="card mb-4" style="border-left: 4px solid #1a3c6e; background: linear-gradient(135deg, #f8faff, #ffffff)">
    <div class="card-body">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h5 class="fw-bold text-primary mb-3"><i class="bi bi-calendar-heart me-2"></i>Leave Balance <?= $year ?></h5>
                <div class="row g-2">
                    <div class="col-6 col-md-3 text-center">
                        <div class="fs-4 fw-bold text-primary"><?= $balance['annual_days'] ?></div>
                        <div class="small text-muted">Annual Days</div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="fs-4 fw-bold text-info"><?= $balance['carryover_days'] ?></div>
                        <div class="small text-muted">Carryover</div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="fs-4 fw-bold text-danger"><?= $balance['used_days'] ?></div>
                        <div class="small text-muted">Used</div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="fs-4 fw-bold <?= $balance['available_days'] < 5 ? 'text-danger' : 'text-success' ?>"><?= $balance['available_days'] ?></div>
                        <div class="small text-muted">Available</div>
                    </div>
                </div>
                <div class="mt-3">
                    <?php $pct = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0; ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Used <?= $balance['used_days'] ?> of <?= $balance['total_days'] ?> total days</span>
                        <span><?= round($pct) ?>%</span>
                    </div>
                    <div class="progress" style="height:10px">
                        <div class="progress-bar <?= $pct > 80 ? 'bg-danger' : ($pct > 50 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= min(100,$pct) ?>%"></div>
                    </div>
                </div>
                <?php if ($balance['pending_days'] > 0): ?>
                <div class="mt-2 small text-warning"><i class="bi bi-hourglass me-1"></i><?= $balance['pending_days'] ?> day(s) pending approval</div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-center">
                <a href="/APM/operator/apply.php" class="btn btn-primary btn-lg w-100 mb-2">
                    <i class="bi bi-plus-circle me-1"></i>Apply for Leave
                </a>
                <a href="/APM/operator/my_leaves.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-list-ul me-1"></i>My Requests
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Requests -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between">
                <h6 class="mb-0 fw-bold">My Recent Requests</h6>
                <a href="/APM/operator/my_leaves.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($myLeaves)): ?>
                <div class="text-center py-4 text-muted"><i class="bi bi-calendar-x fs-2 d-block mb-2"></i>No leave requests yet</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($myLeaves as $lr): ?>
                    <a href="/APM/operator/my_leaves.php" class="list-group-item list-group-item-action leave-<?= e($lr['leave_type']) ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small"><?= formatDate($lr['start_date']) ?> → <?= formatDate($lr['end_date']) ?></div>
                                <div class="text-muted small"><?= $lr['total_days'] ?> day(s) &middot; <?= ucfirst($lr['leave_type']) ?></div>
                                <?php if ($lr['reason']): ?><div class="text-muted" style="font-size:0.72rem"><?= e(substr($lr['reason'],0,60)) ?><?= strlen($lr['reason'])>60?'...':'' ?></div><?php endif; ?>
                            </div>
                            <?= statusBadge($lr['status']) ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upcoming & Quick Info -->
    <div class="col-lg-5">
        <!-- Upcoming leaves -->
        <?php if (!empty($upcoming)): ?>
        <div class="card mb-3">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check text-success me-2"></i>Upcoming Approved Leaves</h6></div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($upcoming as $u): ?>
                    <div class="list-group-item">
                        <div class="fw-semibold small text-success"><?= formatDate($u['start_date']) ?> → <?= formatDate($u['end_date']) ?></div>
                        <div class="text-muted small"><?= $u['total_days'] ?> day(s)</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Stats summary -->
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">My Stats <?= $year ?></h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom small"><span>Requests this year</span><span class="fw-bold"><?= $approvedCount + $pendingCount ?></span></div>
                <div class="d-flex justify-content-between py-2 border-bottom small"><span>Approved</span><span class="fw-bold text-success"><?= $approvedCount ?></span></div>
                <div class="d-flex justify-content-between py-2 small"><span>Pending</span><span class="fw-bold text-warning"><?= $pendingCount ?></span></div>
                <div class="mt-3">
                    <a href="/APM/operator/calendar.php" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-calendar3 me-1"></i>View My Calendar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
