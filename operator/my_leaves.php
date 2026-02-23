<?php
/**
 * APM - Operator My Leaves
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['operator', 'supervisor', 'admin']);

$pdo  = getDB();
$user = currentUser();
$uid  = $user['id'];

// Cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) { setFlash('danger','Invalid token.'); header('Location: /APM/operator/my_leaves.php'); exit; }
    $lid = (int)$_POST['leave_id'];
    $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id=? AND user_id=?");
    $stmt->execute([$lid, $uid]);
    $lr = $stmt->fetch();
    if ($lr && in_array($lr['status'], ['pending'])) {
        $pdo->prepare("UPDATE leave_requests SET status='cancelled' WHERE id=? AND user_id=?")->execute([$lid, $uid]);
        setFlash('success', 'Leave request cancelled.');
    } else {
        setFlash('danger', 'Cannot cancel this request.');
    }
    header('Location: /APM/operator/my_leaves.php'); exit;
}

$year   = (int)($_GET['year'] ?? date('Y'));
$status = sanitize($_GET['status'] ?? '');

$where  = "WHERE lr.user_id = ?";
$params = [$uid];
if ($year)   { $where .= " AND YEAR(lr.start_date) = ?"; $params[] = $year; }
if ($status) { $where .= " AND lr.status = ?"; $params[] = $status; }

$stmt = $pdo->prepare(
    "SELECT lr.*, sup.first_name as sup_first, sup.last_name as sup_last,
            adm.first_name as adm_first, adm.last_name as adm_last
     FROM leave_requests lr
     LEFT JOIN users sup ON lr.supervisor_id = sup.id
     LEFT JOIN users adm ON lr.admin_id = adm.id
     $where ORDER BY lr.created_at DESC"
);
$stmt->execute($params);
$myLeaves = $stmt->fetchAll();

$balance = getLeaveBalance($uid, $year);

$pageTitle = 'My Leave Requests';
$activePage = 'my_leaves';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-list-ul text-primary me-2"></i>My Leave Requests</h4>
    <a href="/APM/operator/apply.php" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>New Request</a>
</div>

<!-- Balance Strip -->
<div class="card mb-3" style="border-left: 4px solid #10b981">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <div class="text-center"><div class="fw-bold text-success"><?= $balance['available_days'] ?></div><div class="text-muted small">Available</div></div>
            <div class="text-center"><div class="fw-bold text-warning"><?= $balance['pending_days'] ?></div><div class="text-muted small">Pending</div></div>
            <div class="text-center"><div class="fw-bold text-danger"><?= $balance['used_days'] ?></div><div class="text-muted small">Used</div></div>
            <div class="text-center"><div class="fw-bold text-primary"><?= $balance['total_days'] ?></div><div class="text-muted small">Total Balance</div></div>
            <div class="ms-auto">
                <select class="form-select form-select-sm" onchange="location.href='?year='+this.value+'&status=<?= e($status) ?>'">
                    <?php for ($y=date('Y')-1; $y<=date('Y')+1; $y++): ?>
                    <option value="<?= $y ?>" <?= $y==$year?'selected':''?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Status Filter -->
<ul class="nav nav-pills mb-3">
    <?php foreach ([''=> 'All', 'pending'=>'Pending', 'supervisor_approved'=>'Processing', 'approved'=>'Approved', 'rejected'=>'Rejected', 'cancelled'=>'Cancelled'] as $s => $label):
        $cnt = count(array_filter($myLeaves, fn($r) => $s === '' || $r['status'] === $s));
    ?>
    <li class="nav-item"><a class="nav-link py-1 px-3 <?= $status===$s?'active':'' ?>" href="?year=<?= $year ?>&status=<?= $s ?>"><?= $label ?><?php if ($cnt): ?> <span class="badge bg-secondary ms-1"><?= $cnt ?></span><?php endif; ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if (empty($myLeaves)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
        <h6 class="text-muted">No leave requests found</h6>
        <a href="/APM/operator/apply.php" class="btn btn-primary mt-2"><i class="bi bi-plus me-1"></i>Apply for Leave</a>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($myLeaves as $lr): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 leave-<?= e($lr['leave_type']) ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <code class="small text-muted"><?= e($lr['request_number']) ?></code>
                    <?= statusBadge($lr['status']) ?>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="text-muted small">From</div>
                        <div class="fw-semibold"><?= formatDate($lr['start_date']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">To</div>
                        <div class="fw-semibold"><?= formatDate($lr['end_date']) ?></div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small"><?= ucfirst($lr['leave_type']) ?></span>
                    <span class="fw-bold text-primary"><?= $lr['total_days'] ?> day(s)</span>
                </div>
                
                <?php if ($lr['status'] === 'rejected'): ?>
                <div class="alert alert-danger py-1 small mb-2">
                    <i class="bi bi-x-circle me-1"></i>
                    <?= $lr['admin_note'] ?: $lr['supervisor_note'] ?: 'Rejected' ?>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($lr['status'], ['supervisor_approved','approved'])): ?>
                <div class="small text-muted mb-2">
                    <i class="bi bi-person-check me-1 text-success"></i>
                    <?= e(($lr['sup_first']??'').' '.($lr['sup_last']??'')) ?: 'Supervisor' ?> ✓
                </div>
                <?php endif; ?>
                
                <?php if ($lr['status'] === 'approved'): ?>
                <div class="small text-muted mb-2">
                    <i class="bi bi-shield-check me-1 text-success"></i>
                    <?= e(($lr['adm_first']??'').' '.($lr['adm_last']??'')) ?: 'Admin' ?> ✓ Final
                </div>
                <?php endif; ?>
                
                <div class="d-flex gap-2 mt-3">
                    <?php if ($lr['status'] === 'pending'): ?>
                    <form method="POST" class="flex-grow-1">
                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                        <input type="hidden" name="leave_id" value="<?= $lr['id'] ?>">
                        <input type="hidden" name="cancel" value="1">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100" data-confirm="Cancel this request?">
                            <i class="bi bi-x"></i> Cancel
                        </button>
                    </form>
                    <?php endif; ?>
                    <small class="text-muted align-self-end"><?= date('d/m/y', strtotime($lr['created_at'])) ?></small>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
