<?php
/**
 * APM - Supervisor Leave Requests
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['supervisor', 'admin']);

$pdo  = getDB();
$user = currentUser();
$teamId = $user['team_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) { setFlash('danger', 'Invalid token.'); header('Location: /APM/supervisor/leaves.php'); exit; }
    
    $leaveId = (int)$_POST['leave_id'];
    $act     = $_POST['act'] ?? '';
    $note    = sanitize($_POST['note'] ?? '');
    
    $stmt = $pdo->prepare("SELECT lr.*, u.id as owner_id, u.first_name, u.last_name FROM leave_requests lr JOIN users u ON lr.user_id=u.id WHERE lr.id=? AND lr.team_id=?");
    $stmt->execute([$leaveId, $teamId]);
    $leave = $stmt->fetch();
    
    if (!$leave) { setFlash('danger', 'Not found or no permission.'); header('Location: /APM/supervisor/leaves.php'); exit; }
    
    if ($act === 'approve') {
        $pdo->prepare("UPDATE leave_requests SET status='supervisor_approved', supervisor_id=?, supervisor_action_at=NOW(), supervisor_note=? WHERE id=?")
            ->execute([$user['id'], $note, $leaveId]);
        
        $pdo->prepare("UPDATE leave_balances SET pending_days = pending_days + ? WHERE user_id = ? AND year = YEAR(?)")
            ->execute([$leave['total_days'], $leave['owner_id'], $leave['start_date']]);
        
        sendNotification($leave['owner_id'], 'Leave Approved by Supervisor', 
            "Your leave request from " . formatDate($leave['start_date']) . " is pending admin final approval.", 
            'info', $leaveId);
        
        // Notify admin
        $admins = $pdo->query("SELECT id FROM users WHERE role_id=1 AND active=1")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $adminId) {
            sendNotification($adminId, 'Leave Needs Final Approval', 
                $leave['first_name'] . " " . $leave['last_name'] . "'s leave has supervisor approval - needs your review.", 
                'warning', $leaveId);
        }
        
        logActivity('SUP_APPROVE', "Supervisor approved leave ID: $leaveId");
        setFlash('success', 'Leave request approved. Awaiting admin final approval.');
        
    } elseif ($act === 'reject') {
        $pdo->prepare("UPDATE leave_requests SET status='rejected', supervisor_id=?, supervisor_action_at=NOW(), supervisor_note=? WHERE id=?")
            ->execute([$user['id'], $note, $leaveId]);
        
        sendNotification($leave['owner_id'], 'Leave Rejected', 
            "Your leave request was rejected by supervisor. Reason: $note", 
            'danger', $leaveId);
        
        logActivity('SUP_REJECT', "Supervisor rejected leave ID: $leaveId");
        setFlash('warning', 'Leave request rejected.');
    }
    
    header('Location: /APM/supervisor/leaves.php'); exit;
}

$status  = sanitize($_GET['status'] ?? '');
$search  = sanitize($_GET['search'] ?? '');
$month   = sanitize($_GET['month'] ?? '');

$where  = "WHERE lr.team_id = ?";
$params = [$teamId];
if ($status) { $where .= " AND lr.status = ?"; $params[] = $status; }
if ($search) { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR lr.request_number LIKE ?)"; $q = "%$search%"; array_push($params,$q,$q,$q); }
if ($month)  { $where .= " AND DATE_FORMAT(lr.start_date,'%Y-%m') = ?"; $params[] = $month; }

$stmt = $pdo->prepare(
    "SELECT lr.*, u.first_name, u.last_name, u.employee_id FROM leave_requests lr JOIN users u ON lr.user_id=u.id 
     $where ORDER BY FIELD(lr.status,'pending','supervisor_approved','approved','rejected'), lr.created_at DESC"
);
$stmt->execute($params);
$leaves = $stmt->fetchAll();

$pageTitle = 'Team Leave Requests';
$activePage = 'leaves';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-calendar-check text-primary me-2"></i>Team Leave Requests</h4>
    <a href="/APM/supervisor/reports.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-bar-chart me-1"></i>Reports</a>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3">
    <?php foreach ([''=> 'All', 'pending'=>'Pending', 'supervisor_approved'=>'My Approved', 'approved'=>'Final Approved', 'rejected'=>'Rejected'] as $s => $label):
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE team_id=?" . ($s ? " AND status='$s'" : ''));
        $cStmt->execute([$teamId]); $cnt = $cStmt->fetchColumn();
    ?>
    <li class="nav-item"><a class="nav-link <?= $status===$s?'active':'' ?>" href="?status=<?= $s ?>">
        <?= $label ?><?php if ($cnt>0): ?><span class="badge bg-<?= $s==='pending'?'warning':($s==='supervisor_approved'?'info':'secondary') ?> ms-1"><?= $cnt ?></span><?php endif; ?>
    </a></li>
    <?php endforeach; ?>
</ul>

<!-- Filters -->
<div class="card mb-3"><div class="card-body py-2">
    <form method="GET" class="row g-2">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search..."></div>
        <div class="col-md-3"><input type="month" class="form-control form-control-sm" name="month" value="<?= e($month) ?>"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Filter</button></div>
    </form>
</div></div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Employee</th><th>Request #</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Submitted</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block"></i>No requests found</td></tr>
                    <?php else: foreach ($leaves as $lr): ?>
                    <tr class="<?= $lr['status']==='pending' ? 'table-warning bg-opacity-25' : '' ?>">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm"><?= strtoupper(substr($lr['first_name'],0,1).substr($lr['last_name'],0,1)) ?></div>
                                <div>
                                    <div class="fw-semibold small"><?= e($lr['first_name'].' '.$lr['last_name']) ?></div>
                                    <div class="text-muted" style="font-size:0.72rem"><?= e($lr['employee_id']??'') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><code class="small"><?= e($lr['request_number']) ?></code></td>
                        <td><span class="badge bg-light text-dark text-capitalize"><?= e($lr['leave_type']) ?></span></td>
                        <td class="small"><?= formatDate($lr['start_date']) ?><br><span class="text-muted">to <?= formatDate($lr['end_date']) ?></span></td>
                        <td class="fw-bold text-center"><?= $lr['total_days'] ?></td>
                        <td><?= statusBadge($lr['status']) ?></td>
                        <td class="small text-muted"><?= date('d/m/y', strtotime($lr['created_at'])) ?></td>
                        <td>
                            <a href="/APM/supervisor/leave_detail.php?id=<?= $lr['id'] ?>" class="btn btn-xs btn-outline-info py-0 px-2" style="font-size:0.75rem"><i class="bi bi-eye"></i></a>
                            <?php if ($lr['status'] === 'pending'): ?>
                            <button class="btn btn-xs btn-outline-success py-0 px-2" style="font-size:0.75rem" onclick="doAction(<?= $lr['id'] ?>,'approve')"><i class="bi bi-check-lg"></i></button>
                            <button class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:0.75rem" onclick="doAction(<?= $lr['id'] ?>,'reject')"><i class="bi bi-x-lg"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header" id="modalHeader"><h5 class="modal-title" id="modalTitle">Confirm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="leave_id" id="actionLeaveId">
            <input type="hidden" name="act" id="actionType">
            <div class="modal-body">
                <div class="mb-0"><label class="form-label">Note / Reason</label><textarea class="form-control" name="note" rows="2" placeholder="Optional note..."></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn" id="actionBtn">Confirm</button></div>
        </form>
    </div></div>
</div>

<?php
$extraJs = <<<JS
function doAction(id, act) {
    document.getElementById('actionLeaveId').value = id;
    document.getElementById('actionType').value = act;
    const isApprove = act === 'approve';
    document.getElementById('modalHeader').className = 'modal-header ' + (isApprove ? 'bg-success text-white' : 'bg-danger text-white');
    document.getElementById('modalTitle').textContent = isApprove ? '✅ Approve Leave' : '❌ Reject Leave';
    document.getElementById('actionBtn').className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
    document.getElementById('actionBtn').textContent = isApprove ? 'Approve' : 'Reject';
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
JS;
include BASE_PATH . '/includes/footer.php';
?>
