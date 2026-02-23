<?php
/**
 * APM - Admin Reports
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin', 'supervisor']);

$pdo  = getDB();
$user = currentUser();

$type   = sanitize($_GET['type'] ?? 'overview');
$year   = (int)($_GET['year'] ?? date('Y'));
$month  = sanitize($_GET['month'] ?? '');
$shiftF = (int)($_GET['shift'] ?? 0);
$teamF  = (int)($_GET['team'] ?? 0);
$export = sanitize($_GET['export'] ?? '');

// Restrict supervisor to their team
if (isSupervisor()) {
    $teamF  = $user['team_id'];
    $shiftF = $user['shift_id'];
}

// Build WHERE clause
$where  = "WHERE YEAR(lr.start_date) = $year";
$params = [];
if ($month) { $where .= " AND DATE_FORMAT(lr.start_date,'%Y-%m') = ?"; $params[] = $month; }
if ($shiftF) { $where .= " AND lr.shift_id = ?"; $params[] = $shiftF; }
if ($teamF)  { $where .= " AND lr.team_id = ?"; $params[] = $teamF; }

// Main report query
$stmt = $pdo->prepare(
    "SELECT u.first_name, u.last_name, u.employee_id, u.email,
            t.name as team_name, t.code as team_code, s.name as shift_name,
            SUM(CASE WHEN lr.status='approved' THEN lr.total_days ELSE 0 END) as approved_days,
            SUM(CASE WHEN lr.status='pending' THEN lr.total_days ELSE 0 END) as pending_days,
            SUM(CASE WHEN lr.status='rejected' THEN lr.total_days ELSE 0 END) as rejected_days,
            COUNT(CASE WHEN lr.status='approved' THEN 1 END) as approved_count,
            MAX(CASE WHEN lr.status='approved' THEN lr.end_date END) as last_leave_date,
            lb.annual_days, lb.carryover_days, lb.used_days as balance_used,
            (lb.annual_days + lb.carryover_days - lb.used_days) as remaining_balance
     FROM users u
     LEFT JOIN leave_requests lr ON u.id = lr.user_id $where
     LEFT JOIN teams t ON u.team_id = t.id
     LEFT JOIN shifts s ON u.shift_id = s.id
     LEFT JOIN leave_balances lb ON u.id = lb.user_id AND lb.year = $year
     WHERE u.active = 1 AND u.role_id = 3
     " . ($teamF ? " AND u.team_id = $teamF" : "") . "
     GROUP BY u.id, u.first_name, u.last_name, u.employee_id, u.email, t.name, t.code, s.name, lb.annual_days, lb.carryover_days, lb.used_days
     ORDER BY s.name, t.name, u.first_name"
);
$stmt->execute($params);
$report = $stmt->fetchAll();

// Summary stats
$totalEmployees = count($report);
$totalApproved  = array_sum(array_column($report, 'approved_days'));
$totalPending   = array_sum(array_column($report, 'pending_days'));
$absentToday    = 0;
$todayStmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM leave_requests WHERE status='approved' AND ? BETWEEN start_date AND end_date");
$todayStmt->execute([date('Y-m-d')]);
$absentToday = $todayStmt->fetchColumn();

// Monthly distribution
$monthlyStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(start_date,'%Y-%m') as month, COUNT(*) as count, SUM(total_days) as total_days
     FROM leave_requests WHERE YEAR(start_date) = ? AND status = 'approved'
     " . ($teamF ? " AND team_id = $teamF" : "") . "
     GROUP BY DATE_FORMAT(start_date,'%Y-%m') ORDER BY month"
);
$monthlyStmt->execute([$year]);
$monthly = $monthlyStmt->fetchAll();

// Handle Export
if ($export === 'pdf' || $export === 'excel') {
    require_once BASE_PATH . '/exports/export_handler.php';
    exportReport($export, $report, $year, $month);
}

$shifts = $pdo->query("SELECT * FROM shifts WHERE active=1")->fetchAll();
$teams  = $pdo->query("SELECT t.*, s.name as shift_name FROM teams t JOIN shifts s ON t.shift_id=s.id WHERE t.active=1")->fetchAll();

$pageTitle = 'Reports';
$activePage = 'reports';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart text-primary me-2"></i>Leave Reports</h4>
    <div class="d-flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'excel'])) ?>" class="btn btn-success btn-sm">
            <i class="bi bi-file-excel me-1"></i>Export Excel
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'pdf'])) ?>" class="btn btn-danger btn-sm">
            <i class="bi bi-file-pdf me-1"></i>Export PDF
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="year">
                    <?php for ($y=2024; $y<=2027; $y++): ?><option value="<?=$y?>" <?=$y==$year?'selected':''?>><?=$y?></option><?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="month" class="form-control form-control-sm" name="month" value="<?= e($month) ?>"></div>
            <?php if (!isSupervisor()): ?>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="shift">
                    <option value="">All Shifts</option>
                    <?php foreach ($shifts as $s): ?><option value="<?=$s['id']?>" <?=$shiftF==$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="team">
                    <option value="">All Teams</option>
                    <?php foreach ($teams as $t): ?><option value="<?=$t['id']?>" <?=$teamF==$t['id']?'selected':''?>><?=e($t['code'].' - '.$t['shift_name'])?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">Apply</button></div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-primary"><?= $totalEmployees ?></div>
                <div class="text-muted small">Total Employees</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-success"><?= $totalApproved ?></div>
                <div class="text-muted small">Approved Days</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-warning"><?= $totalPending ?></div>
                <div class="text-muted small">Pending Days</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-3 fw-bold text-danger"><?= $absentToday ?></div>
                <div class="text-muted small">Absent Today</div>
            </div>
        </div>
    </div>
</div>

<!-- Report Table -->
<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-bold">Employee Leave Report - <?= $year ?></h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="reportTable">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Team / Shift</th>
                        <th>Total Balance</th>
                        <th>Used</th>
                        <th>Pending</th>
                        <th>Remaining</th>
                        <th>Leave Count</th>
                        <th>Last Leave</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No data available</td></tr>
                    <?php else: ?>
                    <?php foreach ($report as $row):
                        $total = ((float)($row['annual_days']??21)) + ((float)($row['carryover_days']??0));
                        $remaining = max(0, (float)($row['remaining_balance']??$total));
                        $pct = $total > 0 ? (((float)($row['balance_used']??0)) / $total) * 100 : 0;
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold small"><?= e($row['first_name'].' '.$row['last_name']) ?></div>
                            <div class="text-muted" style="font-size:0.72rem"><?= e($row['employee_id']??'') ?></div>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?= e($row['team_code']??'—') ?></span>
                            <div class="text-muted small"><?= e($row['shift_name']??'—') ?></div>
                        </td>
                        <td>
                            <div class="small fw-semibold"><?= $total ?> days</div>
                            <div class="progress mt-1" style="height:4px;width:70px">
                                <div class="progress-bar <?= $pct>80?'bg-danger':($pct>50?'bg-warning':'bg-success') ?>" style="width:<?= min(100,$pct) ?>%"></div>
                            </div>
                        </td>
                        <td class="text-danger fw-semibold"><?= $row['approved_days']??0 ?></td>
                        <td class="text-warning fw-semibold"><?= $row['pending_days']??0 ?></td>
                        <td class="<?= $remaining < 5 ? 'text-danger' : 'text-success' ?> fw-bold"><?= $remaining ?></td>
                        <td><?= $row['approved_count']??0 ?> <span class="text-muted small">requests</span></td>
                        <td class="small text-muted"><?= $row['last_leave_date'] ? formatDate($row['last_leave_date']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
