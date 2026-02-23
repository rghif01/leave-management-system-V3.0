<?php
/**
 * APM - Admin Leave Requests
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);

$pdo  = getDB();
$user = currentUser();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrf($csrf)) { setFlash('danger', 'Invalid token.'); header('Location: /APM/admin/leaves.php'); exit; }
    
    $leaveId = (int)$_POST['leave_id'];
    $act     = $_POST['act'] ?? '';
    $note    = sanitize($_POST['note'] ?? '');
    
    $stmt = $pdo->prepare("SELECT lr.*, u.email, u.first_name, u.last_name, u.id as owner_id, lr.total_days, lr.deduct_from_balance FROM leave_requests lr JOIN users u ON lr.user_id = u.id WHERE lr.id = ?");
    $stmt->execute([$leaveId]);
    $leave = $stmt->fetch();
    
    if (!$leave) { setFlash('danger', 'Leave request not found.'); header('Location: /APM/admin/leaves.php'); exit; }
    
    if ($act === 'approve') {
        $pdo->prepare("UPDATE leave_requests SET status='approved', admin_id=?, admin_action_at=NOW(), admin_note=?, updated_at=NOW() WHERE id=?")->execute([$user['id'], $note, $leaveId]);
        
        // Deduct from balance
        if ($leave['deduct_from_balance']) {
            $pdo->prepare("UPDATE leave_balances SET used_days = used_days + ?, pending_days = GREATEST(0, pending_days - ?) WHERE user_id = ? AND year = YEAR(?)")->execute([$leave['total_days'], $leave['total_days'], $leave['owner_id'], $leave['start_date']]);
        }
        
        // Notify employee
        sendNotification($leave['owner_id'], 'Leave Approved ✅', "Your leave request ({$leave['request_number']}) from " . formatDate($leave['start_date']) . " to " . formatDate($leave['end_date']) . " has been finally approved.", 'success', $leaveId);
        
        logActivity('LEAVE_APPROVE', "Admin approved leave ID: $leaveId");
        setFlash('success', 'Leave request approved successfully.');
        
    } elseif ($act === 'reject') {
        $pdo->prepare("UPDATE leave_requests SET status='rejected', admin_id=?, admin_action_at=NOW(), admin_note=?, updated_at=NOW() WHERE id=?")->execute([$user['id'], $note, $leaveId]);
        
        // Release pending days
        $pdo->prepare("UPDATE leave_balances SET pending_days = GREATEST(0, pending_days - ?) WHERE user_id = ? AND year = YEAR(?)")->execute([$leave['total_days'], $leave['owner_id'], $leave['start_date']]);
        
        sendNotification($leave['owner_id'], 'Leave Rejected ❌', "Your leave request ({$leave['request_number']}) has been rejected by admin. Note: $note", 'danger', $leaveId);
        
        logActivity('LEAVE_REJECT', "Admin rejected leave ID: $leaveId");
        setFlash('warning', 'Leave request rejected.');
    }
    
    header('Location: /APM/admin/leaves.php' . (isset($_GET['status']) ? '?status=' . e($_GET['status']) : ''));
    exit;
}

// Filters
$status   = sanitize($_GET['status'] ?? '');
$search   = sanitize($_GET['search'] ?? '');
$shift    = (int)($_GET['shift'] ?? 0);
$team     = (int)($_GET['team'] ?? 0);
$month    = sanitize($_GET['month'] ?? '');
$type     = sanitize($_GET['type'] ?? '');

$where  = "WHERE 1=1";
$params = [];

if ($status) { $where .= " AND lr.status = ?"; $params[] = $status; }
if ($search) { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR lr.request_number LIKE ?)"; $q = "%$search%"; array_push($params,$q,$q,$q); }
if ($shift)  { $where .= " AND lr.shift_id = ?"; $params[] = $shift; }
if ($team)   { $where .= " AND lr.team_id = ?"; $params[] = $team; }
if ($month)  { $where .= " AND DATE_FORMAT(lr.start_date, '%Y-%m') = ?"; $params[] = $month; }
if ($type)   { $where .= " AND lr.leave_type = ?"; $params[] = $type; }

$stmt = $pdo->prepare(
    "SELECT lr.*, u.first_name, u.last_name, u.email, u.employee_id,
            t.name as team_name, t.code as team_code,
            s.name as shift_name,
            sup.first_name as sup_first, sup.last_name as sup_last
     FROM leave_requests lr
     JOIN users u ON lr.user_id = u.id
     JOIN teams t ON lr.team_id = t.id
     JOIN shifts s ON lr.shift_id = s.id
     LEFT JOIN users sup ON lr.supervisor_id = sup.id
     $where
     ORDER BY FIELD(lr.status,'supervisor_approved','pending','approved','rejected','cancelled'), lr.created_at DESC"
);
$stmt->execute($params);
$leaves = $stmt->fetchAll();

$shifts = $pdo->query("SELECT * FROM shifts WHERE active=1")->fetchAll();
$teams  = $pdo->query("SELECT t.*, s.name as shift_name FROM teams t JOIN shifts s ON t.shift_id=s.id WHERE t.active=1")->fetchAll();

$pageTitle = 'Leave Requests';
$activePage = 'leaves';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-calendar-check text-primary me-2"></i>Leave Requests</h4>
    <div class="d-flex gap-2">
        <a href="/APM/admin/reports.php?export=excel" class="btn btn-sm btn-success"><i class="bi bi-file-excel me-1"></i>Excel</a>
        <a href="/APM/admin/reports.php?export=pdf" class="btn btn-sm btn-danger"><i class="bi bi-file-pdf me-1"></i>PDF</a>
    </div>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3">
    <?php 
    $tabs = [''=> 'All', 'pending'=>'Pending', 'supervisor_approved'=>'Supervisor Approved', 'approved'=>'Approved', 'rejected'=>'Rejected'];
    foreach ($tabs as $s => $label):
        // Count
        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests" . ($s ? " WHERE status='$s'" : ''));
        $cStmt->execute();
        $cnt = $cStmt->fetchColumn();
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $status === $s ? 'active' : '' ?>" href="?status=<?= $s ?>">
            <?= $label ?>
            <?php if ($cnt > 0): ?><span class="badge bg-<?= $s==='pending'?'warning':($s==='supervisor_approved'?'info':($s==='approved'?'success':($s==='rejected'?'danger':'secondary'))) ?> ms-1"><?= $cnt ?></span><?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search employee/request..."></div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="shift">
                    <option value="">All Shifts</option>
                    <?php foreach ($shifts as $s): ?><option value="<?= $s['id'] ?>" <?= $shift==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="team">
                    <option value="">All Teams</option>
                    <?php foreach ($teams as $t): ?><option value="<?= $t['id'] ?>" <?= $team==$t['id']?'selected':'' ?>><?= e($t['code']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="month" class="form-control form-control-sm" name="month" value="<?= e($month) ?>"></div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="type">
                    <option value="">All Types</option>
                    <option value="annual" <?= $type==='annual'?'selected':'' ?>>Annual</option>
                    <option value="holiday" <?= $type==='holiday'?'selected':'' ?>>Holiday</option>
                    <option value="medical" <?= $type==='medical'?'selected':'' ?>>Medical</option>
                    <option value="emergency" <?= $type==='emergency'?'selected':'' ?>>Emergency</option>
                </select>
            </div>
            <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary w-100">Go</button></div>
        </form>
    </div>
</div>

<!-- Leave Requests Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Request #</th>
                        <th>Employee</th>
                        <th>Team/Shift</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Supervisor</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No leave requests found</td></tr>
                    <?php else: ?>
                    <?php foreach ($leaves as $lr): ?>
                    <tr class="<?= $lr['status']==='supervisor_approved' ? 'table-warning' : '' ?>">
                        <td><code class="small"><?= e($lr['request_number']) ?></code></td>
                        <td>
                            <div class="fw-semibold small"><?= e($lr['first_name'].' '.$lr['last_name']) ?></div>
                            <div class="text-muted" style="font-size:0.72rem"><?= e($lr['employee_id'] ?? '') ?></div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark"><?= e($lr['team_code']) ?></span>
                            <div class="text-muted small"><?= e($lr['shift_name']) ?></div>
                        </td>
                        <td><span class="badge bg-light text-dark text-capitalize"><?= e($lr['leave_type']) ?></span></td>
                        <td>
                            <div class="small"><?= formatDate($lr['start_date']) ?></div>
                            <div class="text-muted small"><?= formatDate($lr['end_date']) ?></div>
                        </td>
                        <td class="fw-bold text-center"><?= $lr['total_days'] ?></td>
                        <td><?= statusBadge($lr['status']) ?></td>
                        <td class="small text-muted">
                            <?php if ($lr['sup_first']): ?>
                                <?= e($lr['sup_first'].' '.$lr['sup_last']) ?>
                                <?php if ($lr['supervisor_action_at']): ?>
                                <div style="font-size:0.7rem"><?= date('d/m/y', strtotime($lr['supervisor_action_at'])) ?></div>
                                <?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <a href="/APM/admin/leave_detail.php?id=<?= $lr['id'] ?>" class="btn btn-xs btn-outline-info py-0 px-2" style="font-size:0.75rem"><i class="bi bi-eye"></i></a>
                            <?php if (in_array($lr['status'], ['pending','supervisor_approved'])): ?>
                            <button class="btn btn-xs btn-outline-success py-0 px-2" style="font-size:0.75rem"
                                onclick="showAction(<?= $lr['id'] ?>, 'approve')"><i class="bi bi-check-lg"></i></button>
                            <button class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:0.75rem"
                                onclick="showAction(<?= $lr['id'] ?>, 'reject')"><i class="bi bi-x-lg"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="actionModalHeader">
                <h5 class="modal-title" id="actionModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                <input type="hidden" name="leave_id" id="actionLeaveId">
                <input type="hidden" name="act" id="actionType">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Note / Comment</label>
                        <textarea class="form-control" name="note" rows="3" placeholder="Optional note..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="actionBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
function showAction(id, act) {
    document.getElementById('actionLeaveId').value = id;
    document.getElementById('actionType').value = act;
    const isApprove = act === 'approve';
    document.getElementById('actionModalHeader').className = 'modal-header ' + (isApprove ? 'bg-success text-white' : 'bg-danger text-white');
    document.getElementById('actionModalTitle').textContent = isApprove ? '✅ Approve Leave Request' : '❌ Reject Leave Request';
    document.getElementById('actionBtn').className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
    document.getElementById('actionBtn').textContent = isApprove ? 'Approve' : 'Reject';
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
JS;

include BASE_PATH . '/includes/footer.php';
?>
