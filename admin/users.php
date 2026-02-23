<?php
/**
 * APM - Admin Users Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);

$pdo = getDB();
$user = currentUser();
$action = $_GET['action'] ?? 'list';
$error = $success = '';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrf($csrf)) { setFlash('danger', 'Invalid security token.'); header('Location: /APM/admin/users.php'); exit; }
    
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'add' || $postAction === 'edit') {
        $uid        = (int)($_POST['user_id'] ?? 0);
        $empId      = sanitize($_POST['employee_id'] ?? '');
        $firstName  = sanitize($_POST['first_name'] ?? '');
        $lastName   = sanitize($_POST['last_name'] ?? '');
        $email      = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $roleId     = (int)($_POST['role_id'] ?? 3);
        $shiftId    = (int)($_POST['shift_id'] ?? 0) ?: null;
        $teamId     = (int)($_POST['team_id'] ?? 0) ?: null;
        $seniority  = sanitize($_POST['seniority_date'] ?? '');
        $phone      = sanitize($_POST['phone'] ?? '');
        $password   = $_POST['password'] ?? '';
        $annualDays = (int)($_POST['annual_days'] ?? 21);
        
        if (empty($firstName) || empty($lastName) || empty($email)) {
            setFlash('danger', 'Name and email are required.'); header('Location: /APM/admin/users.php'); exit;
        }
        
        if ($postAction === 'add') {
            if (empty($password)) { setFlash('danger', 'Password required for new user.'); header('Location: /APM/admin/users.php'); exit; }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (employee_id, first_name, last_name, email, password, role_id, shift_id, team_id, seniority_date, phone) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$empId, $firstName, $lastName, $email, $hash, $roleId, $shiftId, $teamId, $seniority ?: null, $phone]);
            $newUid = $pdo->lastInsertId();
            
            // Create default leave balance
            $pdo->prepare("INSERT INTO leave_balances (user_id, year, annual_days) VALUES (?, YEAR(NOW()), ?)")->execute([$newUid, $annualDays]);
            
            logActivity('USER_ADD', "Added user: $email");
            setFlash('success', "User '$firstName $lastName' added successfully.");
        } else {
            $upd = "UPDATE users SET employee_id=?, first_name=?, last_name=?, email=?, role_id=?, shift_id=?, team_id=?, seniority_date=?, phone=?";
            $params = [$empId, $firstName, $lastName, $email, $roleId, $shiftId, $teamId, $seniority ?: null, $phone];
            if (!empty($password)) { $upd .= ", password=?"; $params[] = password_hash($password, PASSWORD_BCRYPT); }
            $upd .= " WHERE id=?";
            $params[] = $uid;
            $pdo->prepare($upd)->execute($params);
            
            // Update leave balance if changed
            $pdo->prepare("UPDATE leave_balances SET annual_days=? WHERE user_id=? AND year=YEAR(NOW())")->execute([$annualDays, $uid]);
            
            logActivity('USER_EDIT', "Edited user ID: $uid");
            setFlash('success', 'User updated successfully.');
        }
        header('Location: /APM/admin/users.php');
        exit;
    }
    
    if ($postAction === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === $user['id']) { setFlash('danger', 'Cannot delete your own account.'); header('Location: /APM/admin/users.php'); exit; }
        $pdo->prepare("UPDATE users SET active = 0 WHERE id = ?")->execute([$uid]);
        logActivity('USER_DELETE', "Deactivated user ID: $uid");
        setFlash('success', 'User deactivated.');
        header('Location: /APM/admin/users.php');
        exit;
    }
    
    if ($postAction === 'adjust_balance') {
        $uid   = (int)$_POST['user_id'];
        $year  = (int)($_POST['year'] ?? date('Y'));
        $days  = (float)$_POST['annual_days'];
        $carry = (float)($_POST['carryover_days'] ?? 0);
        $note  = sanitize($_POST['note'] ?? '');
        $pdo->prepare("INSERT INTO leave_balances (user_id, year, annual_days, carryover_days, adjusted_by, adjustment_note) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE annual_days=VALUES(annual_days), carryover_days=VALUES(carryover_days), adjusted_by=VALUES(adjusted_by), adjustment_note=VALUES(adjustment_note)")
            ->execute([$uid, $year, $days, $carry, $user['id'], $note]);
        logActivity('BALANCE_ADJUST', "Adjusted balance for user $uid: $days days");
        setFlash('success', 'Leave balance updated.');
        header('Location: /APM/admin/users.php');
        exit;
    }
}

// Fetch data
$search    = sanitize($_GET['search'] ?? '');
$filterRole = (int)($_GET['role'] ?? 0);
$filterShift = (int)($_GET['shift'] ?? 0);

$where = "WHERE u.active = 1";
$params = [];
if ($search) { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)"; $q = "%$search%"; $params = array_merge($params, [$q,$q,$q,$q]); }
if ($filterRole) { $where .= " AND u.role_id = ?"; $params[] = $filterRole; }
if ($filterShift) { $where .= " AND u.shift_id = ?"; $params[] = $filterShift; }

$stmt = $pdo->prepare(
    "SELECT u.*, r.name as role_name, s.name as shift_name, t.name as team_name, t.code as team_code,
            lb.annual_days, lb.carryover_days, lb.used_days
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     LEFT JOIN shifts s ON u.shift_id = s.id
     LEFT JOIN teams t ON u.team_id = t.id
     LEFT JOIN leave_balances lb ON u.id = lb.user_id AND lb.year = YEAR(NOW())
     $where ORDER BY r.id, u.first_name"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roles  = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$shifts = $pdo->query("SELECT * FROM shifts WHERE active=1 ORDER BY id")->fetchAll();
$teams  = $pdo->query("SELECT t.*, s.name as shift_name FROM teams t JOIN shifts s ON t.shift_id = s.id WHERE t.active=1 ORDER BY s.id, t.code")->fetchAll();

// Edit user data
$editUser = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editUser = $stmt->fetch();
}

$pageTitle = 'Users Management';
$activePage = 'users';
include BASE_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="fw-bold mb-0"><i class="bi bi-people text-primary me-2"></i>Users Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="bi bi-person-plus me-1"></i>Add User
        </button>
    </div>
</div>

<!-- Search & Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search name, email, ID...">
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $filterRole == $r['id'] ? 'selected' : '' ?>><?= e(ucfirst($r['name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="shift">
                    <option value="">All Shifts</option>
                    <?php foreach ($shifts as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterShift == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="usersTable">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Shift / Team</th>
                        <th>Leave Balance</th>
                        <th>Seniority</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No users found</td></tr>
                    <?php else: ?>
                    <?php foreach ($users as $u): 
                        $total = ((float)($u['annual_days']??21)) + ((float)($u['carryover_days']??0));
                        $used  = (float)($u['used_days']??0);
                        $avail = max(0, $total - $used);
                        $pct   = $total > 0 ? ($used / $total) * 100 : 0;
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
                                <div>
                                    <div class="fw-semibold"><?= e($u['first_name'].' '.$u['last_name']) ?></div>
                                    <div class="text-muted small"><?= e($u['email']) ?></div>
                                    <?php if ($u['employee_id']): ?><div class="text-muted" style="font-size:0.72rem"><?= e($u['employee_id']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php $roleColors = ['admin'=>'danger','supervisor'=>'info','operator'=>'success'];
                                  $rc = $roleColors[$u['role_name']] ?? 'secondary'; ?>
                            <span class="badge bg-<?= $rc ?>"><?= e(ucfirst($u['role_name'])) ?></span>
                        </td>
                        <td>
                            <div class="small"><?= e($u['shift_name'] ?? '—') ?></div>
                            <div class="text-muted small"><?= e($u['team_code'] ?? '') ?> <?= $u['team_name'] ? '(' . e($u['team_name']) . ')' : '' ?></div>
                        </td>
                        <td>
                            <div class="small"><?= $avail ?>/<?= $total ?> days left</div>
                            <div class="progress mt-1" style="height:5px;width:80px">
                                <div class="progress-bar <?= $pct > 80 ? 'bg-danger' : ($pct > 50 ? 'bg-warning' : 'bg-success') ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </td>
                        <td class="small"><?= $u['seniority_date'] ? formatDate($u['seniority_date']) : '—' ?></td>
                        <td class="small text-muted"><?= $u['last_login'] ? date('d/m/y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <button class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.75rem"
                                    onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-xs btn-outline-success py-0 px-2" style="font-size:0.75rem"
                                    onclick="adjustBalance(<?= $u['id'] ?>, '<?= e($u['first_name'].' '.$u['last_name']) ?>', <?= $total ?>, <?= $u['carryover_days']??0 ?>)">
                                    <i class="bi bi-calendar-plus"></i>
                                </button>
                                <?php if ($u['id'] != $user['id']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:0.75rem"
                                        data-confirm="Deactivate <?= e($u['first_name']) ?>?">
                                        <i class="bi bi-person-dash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="userModalTitle"><i class="bi bi-person-plus me-2"></i>Add User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="user_id" id="userId" value="0">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employee ID</label>
                            <input type="text" class="form-control" name="employee_id" id="empId" placeholder="e.g. EMP001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role_id" id="roleId" required>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e(ucfirst($r['name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="firstName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="lastName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="userEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" class="form-control" name="phone" id="userPhone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span id="pwdRequired" class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" id="userPassword" placeholder="Leave blank to keep current">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Seniority Date</label>
                            <input type="date" class="form-control" name="seniority_date" id="seniorityDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Shift</label>
                            <select class="form-select" name="shift_id" id="shiftId" onchange="filterTeams(this.value)">
                                <option value="">-- Select Shift --</option>
                                <?php foreach ($shifts as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Team</label>
                            <select class="form-select" name="team_id" id="teamId">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($teams as $t): ?>
                                <option value="<?= $t['id'] ?>" data-shift="<?= $t['shift_id'] ?>"><?= e($t['code'].' - '.$t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Annual Leave Days</label>
                            <input type="number" class="form-control" name="annual_days" id="annualDays" value="21" min="0" max="365">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Balance Adjustment Modal -->
<div class="modal fade" id="balanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Adjust Leave Balance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                <input type="hidden" name="action" value="adjust_balance">
                <input type="hidden" name="user_id" id="balanceUserId">
                <div class="modal-body">
                    <p class="fw-semibold" id="balanceUserName"></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Year</label>
                            <input type="number" class="form-control" name="year" value="<?= date('Y') ?>" min="2020" max="2099">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Annual Days</label>
                            <input type="number" class="form-control" name="annual_days" id="balAnnual" min="0" max="365" step="0.5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Carryover Days</label>
                            <input type="number" class="form-control" name="carryover_days" id="balCarry" min="0" max="365" step="0.5" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Note</label>
                            <textarea class="form-control" name="note" rows="2" placeholder="Reason for adjustment..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$teamsJson = json_encode($teams);
$extraJs = <<<JS
const teams = $teamsJson;

function editUser(u) {
    document.getElementById('userModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit User';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('userId').value = u.id;
    document.getElementById('empId').value = u.employee_id || '';
    document.getElementById('firstName').value = u.first_name;
    document.getElementById('lastName').value = u.last_name;
    document.getElementById('userEmail').value = u.email;
    document.getElementById('userPhone').value = u.phone || '';
    document.getElementById('seniorityDate').value = u.seniority_date || '';
    document.getElementById('roleId').value = u.role_id;
    document.getElementById('shiftId').value = u.shift_id || '';
    document.getElementById('annualDays').value = u.annual_days || 21;
    document.getElementById('pwdRequired').textContent = '';
    filterTeams(u.shift_id, u.team_id);
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function adjustBalance(uid, name, annual, carry) {
    document.getElementById('balanceUserId').value = uid;
    document.getElementById('balanceUserName').textContent = 'Employee: ' + name;
    document.getElementById('balAnnual').value = annual;
    document.getElementById('balCarry').value = carry;
    new bootstrap.Modal(document.getElementById('balanceModal')).show();
}

function filterTeams(shiftId, selectedTeam = null) {
    const sel = document.getElementById('teamId');
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (!shiftId || opt.dataset.shift == shiftId) ? '' : 'none';
    });
    if (selectedTeam) sel.value = selectedTeam;
}
JS;

include BASE_PATH . '/includes/footer.php';
?>
