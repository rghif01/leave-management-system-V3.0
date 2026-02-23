<?php
/**
 * APM - Profile Page (all roles)
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireLogin();

$pdo  = getDB();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) { setFlash('danger','Invalid token.'); header('Location: /APM/'.$user['role'].'/profile.php'); exit; }
    
    $phone   = sanitize($_POST['phone'] ?? '');
    $emailN  = (int)($_POST['email_notifications'] ?? 1);
    $newPwd  = $_POST['new_password'] ?? '';
    $curPwd  = $_POST['current_password'] ?? '';
    
    // Verify current password if changing
    if (!empty($newPwd)) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $curHash = $stmt->fetchColumn();
        if (!password_verify($curPwd, $curHash)) {
            setFlash('danger', 'Current password is incorrect.');
            header('Location: /APM/'.$user['role'].'/profile.php'); exit;
        }
        if (strlen($newPwd) < 8) {
            setFlash('danger', 'New password must be at least 8 characters.');
            header('Location: /APM/'.$user['role'].'/profile.php'); exit;
        }
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPwd, PASSWORD_BCRYPT), $user['id']]);
    }
    
    $pdo->prepare("UPDATE users SET phone=?, email_notifications=? WHERE id=?")->execute([$phone, $emailN, $user['id']]);
    logActivity('PROFILE_UPDATE', 'User updated profile');
    setFlash('success', 'Profile updated successfully.');
    header('Location: /APM/'.$user['role'].'/profile.php'); exit;
}

// Load full user data
$stmt = $pdo->prepare("SELECT u.*, r.name as role_name, s.name as shift_name, t.name as team_name, t.code as team_code FROM users u LEFT JOIN roles r ON u.role_id=r.id LEFT JOIN shifts s ON u.shift_id=s.id LEFT JOIN teams t ON u.team_id=t.id WHERE u.id=?");
$stmt->execute([$user['id']]);
$userFull = $stmt->fetch();

$balance = getLeaveBalance($user['id']);

$pageTitle = 'My Profile';
$activePage = 'profile';
include BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <h4 class="fw-bold mb-4"><i class="bi bi-person text-primary me-2"></i>My Profile</h4>
        
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body py-4">
                        <div class="avatar-lg mx-auto mb-3"><?= strtoupper(substr($userFull['first_name'],0,1).substr($userFull['last_name'],0,1)) ?></div>
                        <h5 class="fw-bold"><?= e($userFull['first_name'].' '.$userFull['last_name']) ?></h5>
                        <div class="text-muted small"><?= e($userFull['email']) ?></div>
                        <div class="mt-2">
                            <span class="badge bg-<?= $userFull['role_name']==='admin'?'danger':($userFull['role_name']==='supervisor'?'info':'success') ?>"><?= ucfirst($userFull['role_name']) ?></span>
                        </div>
                        <hr>
                        <?php if ($userFull['shift_name']): ?>
                        <div class="small text-muted"><?= e($userFull['shift_name']) ?></div>
                        <?php endif; ?>
                        <?php if ($userFull['team_name']): ?>
                        <div class="small"><span class="badge bg-primary"><?= e($userFull['team_code']) ?></span> <?= e($userFull['team_name']) ?></div>
                        <?php endif; ?>
                        <?php if ($userFull['employee_id']): ?>
                        <div class="small text-muted mt-1">ID: <?= e($userFull['employee_id']) ?></div>
                        <?php endif; ?>
                        <?php if ($userFull['last_login']): ?>
                        <div class="text-muted mt-2" style="font-size:0.72rem">Last login: <?= date('d M y H:i', strtotime($userFull['last_login'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Balance Card -->
                <div class="card mt-3">
                    <div class="card-header bg-white"><h6 class="mb-0 fw-semibold">Leave Balance <?= date('Y') ?></h6></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between small mb-2"><span>Annual</span><span class="fw-bold"><?= $balance['annual_days'] ?></span></div>
                        <div class="d-flex justify-content-between small mb-2"><span>Carryover</span><span class="fw-bold"><?= $balance['carryover_days'] ?></span></div>
                        <div class="d-flex justify-content-between small mb-2"><span>Used</span><span class="fw-bold text-danger"><?= $balance['used_days'] ?></span></div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between"><span class="fw-bold">Available</span><span class="fw-bold text-success"><?= $balance['available_days'] ?></span></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Update Profile</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">First Name</label>
                                    <input type="text" class="form-control" value="<?= e($userFull['first_name']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small">Last Name</label>
                                    <input type="text" class="form-control" value="<?= e($userFull['last_name']) ?>" disabled>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small">Email</label>
                                    <input type="email" class="form-control" value="<?= e($userFull['email']) ?>" disabled>
                                    <div class="form-text">Contact admin to change name or email</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?= e($userFull['phone']??'') ?>" placeholder="+212 6xx xxx xxx">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email Notifications</label>
                                    <select class="form-select" name="email_notifications">
                                        <option value="1" <?= ($userFull['email_notifications']??1)?'selected':'' ?>>Enabled</option>
                                        <option value="0" <?= !($userFull['email_notifications']??1)?'selected':'' ?>>Disabled</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr>
                            <h6 class="fw-bold mb-3">Change Password</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" placeholder="Enter current password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">New Password</label>
                                    <input type="password" class="form-control" name="new_password" placeholder="Min 8 characters" id="newPwd">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Confirm Password</label>
                                    <input type="password" class="form-control" placeholder="Confirm new password" id="confirmPwd" oninput="checkPwdMatch()">
                                    <div class="form-text" id="pwdMatchMsg"></div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
function checkPwdMatch() {
    const n = document.getElementById('newPwd').value;
    const c = document.getElementById('confirmPwd').value;
    const msg = document.getElementById('pwdMatchMsg');
    if (!c) { msg.textContent = ''; return; }
    if (n === c) { msg.textContent = '✅ Passwords match'; msg.className = 'form-text text-success'; }
    else { msg.textContent = '❌ Passwords do not match'; msg.className = 'form-text text-danger'; }
}
JS;

include BASE_PATH . '/includes/footer.php';
?>
