<?php
/**
 * APM - Operator Apply for Leave
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['operator', 'supervisor', 'admin']);

$pdo  = getDB();
$user = currentUser();
$uid  = $user['id'];

// Get user full info
$stmt = $pdo->prepare("SELECT u.*, t.name as team_name, t.code as team_code, t.max_leave_per_day, t.id as tid, s.name as shift_name, s.id as sid FROM users u LEFT JOIN teams t ON u.team_id=t.id LEFT JOIN shifts s ON u.shift_id=s.id WHERE u.id=?");
$stmt->execute([$uid]);
$userInfo = $stmt->fetch();

if (!$userInfo['team_id']) {
    setFlash('warning', 'You are not assigned to a team. Please contact your administrator.');
    header('Location: /APM/operator/dashboard.php'); exit;
}

$year    = (int)date('Y');
$balance = getLeaveBalance($uid, $year);
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) { $error = 'Invalid security token.'; goto display; }
    
    $startDate = sanitize($_POST['start_date'] ?? '');
    $endDate   = sanitize($_POST['end_date'] ?? '');
    $leaveType = in_array($_POST['leave_type'], ['annual','holiday','emergency','medical','other']) ? $_POST['leave_type'] : 'annual';
    $reason    = sanitize($_POST['reason'] ?? '');
    
    // Validate
    if (!$startDate || !$endDate) { $error = 'Start and end dates are required.'; goto display; }
    if ($startDate > $endDate) { $error = 'Start date must be before end date.'; goto display; }
    if ($startDate < date('Y-m-d')) { $error = 'Cannot apply for past dates.'; goto display; }
    
    $maxDays = (int)(getSetting('max_leave_days_per_request') ?? 30);
    $totalDays = calculateLeaveDays($startDate, $endDate, $leaveType !== 'holiday');
    
    if ($totalDays <= 0) { $error = 'No working days in selected range (all holidays?).'; goto display; }
    if ($totalDays > $maxDays) { $error = "Maximum $maxDays days per request."; goto display; }
    
    // Check balance (except holiday type which doesn't deduct)
    $deduct = $leaveType !== 'holiday';
    if ($deduct && $totalDays > $balance['available_days']) {
        $error = "Insufficient leave balance. Available: {$balance['available_days']} days, Requested: $totalDays days.";
        goto display;
    }
    
    // Check overlapping requests
    $overlapStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status NOT IN ('rejected','cancelled') AND start_date <= ? AND end_date >= ?");
    $overlapStmt->execute([$uid, $endDate, $startDate]);
    if ($overlapStmt->fetchColumn() > 0) { $error = 'You already have a leave request overlapping these dates.'; goto display; }
    
    // Create request
    $reqNum = generateRequestNumber();
    $stmt = $pdo->prepare(
        "INSERT INTO leave_requests (request_number, user_id, team_id, shift_id, start_date, end_date, total_days, leave_type, reason, status, deduct_from_balance) 
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([$reqNum, $uid, $userInfo['team_id'], $userInfo['shift_id'], $startDate, $endDate, $totalDays, $leaveType, $reason, 'pending', $deduct ? 1 : 0]);
    $leaveId = $pdo->lastInsertId();
    
    // Notify supervisor(s) of team
    $supStmt = $pdo->prepare("SELECT id FROM users WHERE team_id=? AND role_id=2 AND active=1");
    $supStmt->execute([$userInfo['team_id']]);
    $supervisors = $supStmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($supervisors as $supId) {
        sendNotification($supId, '⏰ New Leave Request', 
            "{$user['full_name']} submitted a leave request from " . formatDate($startDate) . " to " . formatDate($endDate) . " ($totalDays days).", 
            'info', $leaveId);
    }
    
    // Also notify admins
    $adminStmt = $pdo->query("SELECT id FROM users WHERE role_id=1 AND active=1");
    foreach ($adminStmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
        sendNotification($adminId, 'New Leave Request', "{$user['full_name']} - $totalDays days leave request submitted.", 'info', $leaveId);
    }
    
    logActivity('LEAVE_SUBMIT', "Leave request submitted: $reqNum ($totalDays days)");
    setFlash('success', "Leave request submitted successfully! Request #: <strong>$reqNum</strong>. Awaiting supervisor approval.");
    header('Location: /APM/operator/my_leaves.php'); exit;
}

display:
// Get holidays in current/next 3 months for calendar display
$holidays = $pdo->prepare("SELECT holiday_date FROM holidays WHERE active=1 AND holiday_date >= CURDATE() AND holiday_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH) ORDER BY holiday_date");
$holidays->execute();
$holidayDates = array_column($holidays->fetchAll(), 'holiday_date');

$pageTitle = 'Apply for Leave';
$activePage = 'apply';
include BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center gap-2 mb-4">
            <a href="/APM/operator/dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
            <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-2"></i>Apply for Leave</h4>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
        <?php endif; ?>
        
        <!-- Balance Summary -->
        <div class="card mb-3" style="border-left: 4px solid #10b981">
            <div class="card-body py-2">
                <div class="row text-center g-2">
                    <div class="col-4"><div class="fw-bold text-success fs-5"><?= $balance['available_days'] ?></div><div class="text-muted small">Available Days</div></div>
                    <div class="col-4"><div class="fw-bold text-warning fs-5"><?= $balance['pending_days'] ?></div><div class="text-muted small">Pending</div></div>
                    <div class="col-4"><div class="fw-bold text-danger fs-5"><?= $balance['used_days'] ?></div><div class="text-muted small">Used</div></div>
                </div>
            </div>
        </div>
        
        <!-- Team capacity info -->
        <?php
        $teamId = $userInfo['team_id'];
        $todayCount = getTeamLeaveCount($teamId, date('Y-m-d'));
        $maxPerDay  = $userInfo['max_leave_per_day'];
        ?>
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <span class="text-muted small">Team <strong><?= e($userInfo['team_code']) ?></strong> today:</span>
                        <span class="ms-2 badge <?= $todayCount >= $maxPerDay ? 'bg-danger' : ($todayCount >= $maxPerDay - 1 ? 'bg-warning text-dark' : 'bg-success') ?>">
                            <?= $todayCount ?>/<?= $maxPerDay ?> on leave
                        </span>
                    </div>
                    <div class="text-muted small"><?= e($userInfo['shift_name']) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Application Form -->
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Leave Application Form</h6></div>
            <div class="card-body">
                <form method="POST" id="leaveForm">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Leave Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="leave_type" id="leaveType" required>
                            <option value="annual">Annual Leave (deducted from balance)</option>
                            <option value="holiday">Holiday Leave (not deducted - admin approval required)</option>
                            <option value="medical">Medical Leave</option>
                            <option value="emergency">Emergency Leave</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" id="start_date" 
                                   min="<?= date('Y-m-d') ?>" required value="<?= e($_POST['start_date']??'') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="end_date" id="end_date" 
                                   min="<?= date('Y-m-d') ?>" required value="<?= e($_POST['end_date']??'') ?>">
                        </div>
                    </div>
                    
                    <!-- Days Calculator -->
                    <div class="alert alert-info py-2 mb-3" id="daysCalculator" style="display:none">
                        <i class="bi bi-calculator me-1"></i>
                        Calculated: <strong id="calculated_days">0</strong> working day(s)
                        <span id="balanceWarning" class="text-danger ms-2" style="display:none">
                            <i class="bi bi-exclamation-triangle"></i> Exceeds available balance!
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason / Notes</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Optional: reason for leave request..."><?= e($_POST['reason']??'') ?></textarea>
                    </div>
                    
                    <!-- Holidays notice -->
                    <?php if (!empty($holidayDates)): ?>
                    <div class="alert alert-warning py-2 mb-3 small">
                        <i class="bi bi-star me-1"></i><strong>Upcoming Holidays (auto-excluded from count):</strong>
                        <?php foreach (array_slice($holidayDates, 0, 5) as $hd): ?>
                        <span class="badge bg-warning text-dark ms-1"><?= date('d M', strtotime($hd)) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn">
                        <i class="bi bi-send me-2"></i>Submit Leave Request
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$availableDays = $balance['available_days'];
$extraJs = <<<JS
const availableDays = {$availableDays};
const start = document.getElementById('start_date');
const end   = document.getElementById('end_date');
const calc  = document.getElementById('daysCalculator');
const daysEl = document.getElementById('calculated_days');
const warn  = document.getElementById('balanceWarning');

async function recalculate() {
    const s = start.value, e = end.value;
    if (!s || !e || s > e) { calc.style.display='none'; return; }
    
    if (end.min < s) end.min = s;
    
    const res = await fetch(`/APM/api/calculate_days.php?start=\${s}&end=\${e}`);
    const data = await res.json();
    const days = data.days || 0;
    
    daysEl.textContent = days;
    calc.style.display = 'block';
    
    const leaveType = document.getElementById('leaveType').value;
    if (leaveType !== 'holiday' && days > availableDays) {
        warn.style.display = 'inline';
        document.getElementById('submitBtn').disabled = true;
    } else {
        warn.style.display = 'none';
        document.getElementById('submitBtn').disabled = false;
    }
}

start.addEventListener('change', () => { if (end.value && end.value < start.value) end.value = start.value; recalculate(); });
end.addEventListener('change', recalculate);
document.getElementById('leaveType').addEventListener('change', recalculate);
JS;

include BASE_PATH . '/includes/footer.php';
?>
