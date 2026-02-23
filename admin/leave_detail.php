<?php
/**
 * APM - Leave Request Detail (Admin)
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT lr.*, u.first_name, u.last_name, u.email, u.employee_id, u.phone, u.seniority_date,
            t.name as team_name, t.code as team_code, t.max_leave_per_day,
            s.name as shift_name,
            sup.first_name as sup_first, sup.last_name as sup_last,
            adm.first_name as adm_first, adm.last_name as adm_last
     FROM leave_requests lr
     JOIN users u ON lr.user_id = u.id
     JOIN teams t ON lr.team_id = t.id
     JOIN shifts s ON lr.shift_id = s.id
     LEFT JOIN users sup ON lr.supervisor_id = sup.id
     LEFT JOIN users adm ON lr.admin_id = adm.id
     WHERE lr.id = ?"
);
$stmt->execute([$id]);
$leave = $stmt->fetch();

if (!$leave) { setFlash('danger', 'Leave request not found.'); header('Location: /APM/admin/leaves.php'); exit; }

// Balance info
$balance = getLeaveBalance($leave['user_id'], (int)date('Y', strtotime($leave['start_date'])));

// How many on leave from same team on those dates
$concurrentCount = 0;
$startD = new DateTime($leave['start_date']);
$endD   = new DateTime($leave['end_date']);
$curr = clone $startD;
$peakCount = 0;
while ($curr <= $endD) {
    $c = getTeamLeaveCount($leave['team_id'], $curr->format('Y-m-d'));
    if ($c > $peakCount) $peakCount = $c;
    $curr->modify('+1 day');
}

$pageTitle = 'Leave Detail - ' . $leave['request_number'];
$activePage = 'leaves';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="/APM/admin/leaves.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
    <h4 class="fw-bold mb-0"><i class="bi bi-file-text text-primary me-2"></i>Leave Request Detail</h4>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Request Info -->
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between">
                <h6 class="mb-0 fw-bold">Request Information</h6>
                <?= statusBadge($leave['status']) ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Request Number</div>
                        <code class="fs-6"><?= e($leave['request_number']) ?></code>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Leave Type</div>
                        <span class="badge bg-primary text-capitalize"><?= e($leave['leave_type']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Start Date</div>
                        <div class="fw-semibold"><?= formatDate($leave['start_date']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">End Date</div>
                        <div class="fw-semibold"><?= formatDate($leave['end_date']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Total Days</div>
                        <div class="fw-bold fs-5 text-primary"><?= $leave['total_days'] ?> day(s)</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Submitted On</div>
                        <div><?= date('d M Y H:i', strtotime($leave['created_at'])) ?></div>
                    </div>
                    <?php if ($leave['reason']): ?>
                    <div class="col-12">
                        <div class="text-muted small mb-1">Reason</div>
                        <div class="bg-light rounded p-2"><?= e($leave['reason']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Deduct from Balance</div>
                        <span class="badge <?= $leave['deduct_from_balance'] ? 'bg-warning' : 'bg-success' ?>">
                            <?= $leave['deduct_from_balance'] ? 'Yes' : 'No (Holiday)' ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Peak Concurrent Leaves (team)</div>
                        <span class="badge <?= $peakCount >= $leave['max_leave_per_day'] ? 'bg-danger' : 'bg-success' ?>">
                            <?= $peakCount ?> / <?= $leave['max_leave_per_day'] ?> max
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Approval Timeline -->
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Approval Timeline</h6></div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item success">
                        <div class="fw-semibold small">Request Submitted</div>
                        <div class="text-muted small"><?= date('d M Y H:i', strtotime($leave['created_at'])) ?></div>
                        <div class="small text-success">By: <?= e($leave['first_name'].' '.$leave['last_name']) ?></div>
                    </div>
                    
                    <div class="timeline-item <?= in_array($leave['status'], ['supervisor_approved','approved','rejected']) ? 'success' : '' ?>">
                        <div class="fw-semibold small">Supervisor Review</div>
                        <?php if ($leave['supervisor_action_at']): ?>
                        <div class="text-muted small"><?= date('d M Y H:i', strtotime($leave['supervisor_action_at'])) ?></div>
                        <div class="small">By: <?= e(($leave['sup_first']??'').' '.($leave['sup_last']??'')) ?></div>
                        <?php if ($leave['supervisor_note']): ?><div class="small text-muted mt-1"><?= e($leave['supervisor_note']) ?></div><?php endif; ?>
                        <?php else: ?><div class="text-muted small">Pending</div><?php endif; ?>
                    </div>
                    
                    <div class="timeline-item <?= $leave['status']==='approved' ? 'success' : ($leave['status']==='rejected' ? 'danger' : '') ?>">
                        <div class="fw-semibold small">Admin Final Decision</div>
                        <?php if ($leave['admin_action_at']): ?>
                        <div class="text-muted small"><?= date('d M Y H:i', strtotime($leave['admin_action_at'])) ?></div>
                        <div class="small">By: <?= e(($leave['adm_first']??'').' '.($leave['adm_last']??'')) ?></div>
                        <?php if ($leave['admin_note']): ?><div class="small text-muted mt-1"><?= e($leave['admin_note']) ?></div><?php endif; ?>
                        <?php else: ?><div class="text-muted small">Pending</div><?php endif; ?>
                    </div>
                </div>
                
                <?php if (in_array($leave['status'], ['pending','supervisor_approved'])): ?>
                <div class="border-top pt-3 mt-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                <input type="hidden" name="act" value="approve">
                                <div class="mb-2"><input type="text" class="form-control form-control-sm" name="note" placeholder="Approval note (optional)"></div>
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Approve</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                <input type="hidden" name="act" value="reject">
                                <div class="mb-2"><input type="text" class="form-control form-control-sm" name="note" placeholder="Rejection reason..." required></div>
                                <button type="submit" class="btn btn-danger w-100"><i class="bi bi-x-lg me-1"></i>Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Employee Info -->
        <div class="card mb-3">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Employee</h6></div>
            <div class="card-body text-center">
                <div class="avatar-lg mx-auto mb-2"><?= strtoupper(substr($leave['first_name'],0,1).substr($leave['last_name'],0,1)) ?></div>
                <div class="fw-bold"><?= e($leave['first_name'].' '.$leave['last_name']) ?></div>
                <div class="text-muted small"><?= e($leave['email']) ?></div>
                <?php if ($leave['employee_id']): ?><div class="badge bg-light text-dark mt-1"><?= e($leave['employee_id']) ?></div><?php endif; ?>
                <hr>
                <div class="row text-start g-2 small">
                    <div class="col-6 text-muted">Shift:</div>
                    <div class="col-6 fw-semibold"><?= e($leave['shift_name']) ?></div>
                    <div class="col-6 text-muted">Team:</div>
                    <div class="col-6 fw-semibold"><?= e($leave['team_code'].' - '.$leave['team_name']) ?></div>
                    <?php if ($leave['seniority_date']): ?>
                    <div class="col-6 text-muted">Seniority:</div>
                    <div class="col-6"><?= formatDate($leave['seniority_date']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Balance -->
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Leave Balance <?= date('Y', strtotime($leave['start_date'])) ?></h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2 small">
                    <span>Annual Days</span><span class="fw-bold"><?= $balance['annual_days'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span>Carryover</span><span class="fw-bold"><?= $balance['carryover_days'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span>Used</span><span class="fw-bold text-danger"><?= $balance['used_days'] ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span>Pending</span><span class="fw-bold text-warning"><?= $balance['pending_days'] ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Available</span>
                    <span class="fw-bold fs-5 text-<?= $balance['available_days'] >= $leave['total_days'] ? 'success' : 'danger' ?>">
                        <?= $balance['available_days'] ?>
                    </span>
                </div>
                <?php if ($balance['available_days'] < $leave['total_days']): ?>
                <div class="alert alert-warning mt-2 small py-1 mb-0"><i class="bi bi-exclamation-triangle"></i> Insufficient balance</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
