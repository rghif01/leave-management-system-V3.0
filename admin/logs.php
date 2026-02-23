<?php
/**
 * APM - Activity Logs
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);
$pdo = getDB();

$search = sanitize($_GET['search'] ?? '');
$date   = sanitize($_GET['date'] ?? '');
$limit  = 100;

$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (al.action LIKE ? OR al.description LIKE ? OR u.first_name LIKE ? OR u.email LIKE ?)"; $q = "%$search%"; array_push($params,$q,$q,$q,$q); }
if ($date)   { $where .= " AND DATE(al.created_at) = ?"; $params[] = $date; }

$stmt = $pdo->prepare(
    "SELECT al.*, u.first_name, u.last_name, u.email, r.name as role_name
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     LEFT JOIN roles r ON u.role_id = r.id
     $where
     ORDER BY al.created_at DESC LIMIT $limit"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'Activity Logs';
$activePage = 'settings';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary me-2"></i>Activity Logs</h4>
    <span class="badge bg-secondary">Last <?= $limit ?> records</span>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search action, user..."></div>
            <div class="col-md-3"><input type="date" class="form-control form-control-sm" name="date" value="<?= e($date) ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Filter</button></div>
            <div class="col-md-2"><a href="/APM/admin/logs.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Date/Time</th><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="small text-muted"><?= date('d/m/y H:i', strtotime($log['created_at'])) ?></td>
                        <td class="small"><?= $log['first_name'] ? e($log['first_name'].' '.$log['last_name']) : '<span class="text-muted">System</span>' ?></td>
                        <td><?php if ($log['role_name']): ?><span class="badge bg-secondary small"><?= e($log['role_name']) ?></span><?php endif; ?></td>
                        <td>
                            <?php
                            $actionColors = ['LOGIN'=>'success','LOGOUT'=>'secondary','LOGIN_FAILED'=>'danger','LEAVE_APPROVE'=>'success','LEAVE_REJECT'=>'warning','USER_ADD'=>'info','BACKUP'=>'primary'];
                            $ac = $actionColors[$log['action']] ?? 'dark';
                            ?>
                            <span class="badge bg-<?= $ac ?> small"><?= e($log['action']) ?></span>
                        </td>
                        <td class="small"><?= e($log['description']) ?></td>
                        <td class="small text-muted"><?= e($log['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No logs found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
