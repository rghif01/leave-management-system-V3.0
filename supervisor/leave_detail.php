<?php
/**
 * APM - Supervisor Leave Detail
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['supervisor', 'admin']);

$pdo    = getDB();
$user   = currentUser();
$teamId = $user['team_id'];
$id     = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT lr.*, u.first_name, u.last_name, u.email, u.employee_id, u.seniority_date,
            t.name as team_name, t.code as team_code, t.max_leave_per_day,
            s.name as shift_name
     FROM leave_requests lr
     JOIN users u ON lr.user_id = u.id
     JOIN teams t ON lr.team_id = t.id
     JOIN shifts s ON lr.shift_id = s.id
     WHERE lr.id = ? AND lr.team_id = ?"
);
$stmt->execute([$id, $teamId]);
$leave = $stmt->fetch();

if (!$leave) { setFlash('danger', 'Not found.'); header('Location: /APM/supervisor/leaves.php'); exit; }

$balance = getLeaveBalance($leave['user_id'], (int)date('Y', strtotime($leave['start_date'])));

// Count other employees on leave same period
$concStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT user_id) FROM leave_requests 
     WHERE team_id=? AND status IN ('supervisor_approved','approved') 
     AND start_date <= ? AND end_date >= ? AND user_id != ?"
);
$concStmt->execute([$teamId, $leave['end_date'], $leave['start_date'], $leave['user_id']]);
$concurrent = $concStmt->fetchColumn();

$pageTitle = 'Leave Detail';
$activePage = 'leaves';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="/APM/supervisor/leaves.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    <h4 class="fw-bold mb-0">Leave Detail &mdash; <code><?= e($leave['request_number']) ?></code></h4>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between">
                <h6 class="mb-0 fw-bold">Leave Information</h6><?= statusBadge($leave['status']) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><div class="text-muted small">Start Date</div><div class="fw-semibold"><?= formatDate($leave['start_date']) ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">End Date</div><div class="fw-semibold"><?= formatDate($leave['end_date']) ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">Total Days</div><div class="fw-bold fs-5 text-primary"><?= $leave['total_days'] ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">Type</div><span class="badge bg-primary"><?= e($leave['leave_type']) ?></span></div>
                    <div class="col-md-6"><div class="text-muted small">Others on leave same period</div><span class="badge <?= $concurrent >= $leave['max_leave_per_day'] ? 'bg-danger' : 'bg-success' ?>"><?= $concurrent ?> concurrent (max <?= $leave['max_leave_per_day'] ?>)</span></div>
                    <div class="col-md-6"><div class="text-muted small">Submitted</div><?= date('d M Y H:i', strtotime($leave['created_at'])) ?></div>
                    <?php if ($leave['reason']): ?>
                    <div class="col-12"><div class="text-muted small">Reason</div><div class="bg-light p-2 rounded"><?= e($leave['reason']) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($leave['status'] === 'pending'): ?>
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Your Decision</h6></div>
            <div class="card-body">
                <?php if ($balance['available_days'] < $leave['total_days']): ?>
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Warning: Employee has insufficient leave balance (<?= $balance['available_days'] ?> available, <?= $leave['total_days'] ?> requested)</div>
                <?php endif; ?>
                <?php if ($concurrent >= $leave['max_leave_per_day']): ?>
                <div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Team is at maximum capacity (<?= $leave['max_leave_per_day'] ?> people) during this period</div>
                <?php endif; ?>
                <div class="row g-2">
                    <div class="col-md-6">
                        <form method="POST" action="/APM/supervisor/leaves.php">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                            <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                            <input type="hidden" name="act" value="approve">
                            <div class="mb-2"><input type="text" class="form-control" name="note" placeholder="Approval note (optional)"></div>
                            <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Approve & Send to Admin</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="POST" action="/APM/supervisor/leaves.php">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                            <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                            <input type="hidden" name="act" value="reject">
                            <div class="mb-2"><input type="text" class="form-control" name="note" placeholder="Reason for rejection" required></div>
                            <button type="submit" class="btn btn-danger w-100"><i class="bi bi-x-lg me-1"></i>Reject</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Employee</h6></div>
            <div class="card-body text-center">
                <div class="avatar-lg mx-auto mb-2"><?= strtoupper(substr($leave['first_name'],0,1).substr($leave['last_name'],0,1)) ?></div>
                <div class="fw-bold"><?= e($leave['first_name'].' '.$leave['last_name']) ?></div>
                <div class="text-muted small"><?= e($leave['email']) ?></div>
                <hr>
                <div class="row text-start small g-2">
                    <div class="col-6 text-muted">Team:</div><div class="col-6 fw-semibold"><?= e($leave['team_code']) ?></div>
                    <?php if ($leave['seniority_date']): ?><div class="col-6 text-muted">Seniority:</div><div class="col-6"><?= formatDate($leave['seniority_date']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Leave Balance</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between small mb-2"><span>Annual</span><span class="fw-bold"><?= $balance['annual_days'] ?></span></div>
                <div class="d-flex justify-content-between small mb-2"><span>Carryover</span><span class="fw-bold"><?= $balance['carryover_days'] ?></span></div>
                <div class="d-flex justify-content-between small mb-2"><span>Used</span><span class="fw-bold text-danger"><?= $balance['used_days'] ?></span></div>
                <hr>
                <div class="d-flex justify-content-between"><span class="fw-bold">Available</span><span class="fw-bold fs-5 text-<?= $balance['available_days'] >= $leave['total_days'] ? 'success' : 'danger' ?>"><?= $balance['available_days'] ?></span></div>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
