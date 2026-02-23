<?php
/**
 * APM - Admin Settings
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) { setFlash('danger','Invalid token.'); header('Location: /APM/admin/settings.php'); exit; }
    
    $settingsToSave = [
        'site_name', 'annual_leave_days', 'priority_rule', 'email_notifications',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_from',
        'backup_retention_days', 'orange_threshold', 'red_threshold', 'session_timeout',
        'max_leave_days_per_request', 'carryover_enabled'
    ];
    
    foreach ($settingsToSave as $key) {
        if (isset($_POST[$key])) {
            $val = sanitize($_POST[$key]);
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$key, $val]);
        }
    }
    
    // Handle SMTP password separately (don't overwrite if empty)
    if (!empty($_POST['smtp_pass'])) {
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('smtp_pass',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([sanitize($_POST['smtp_pass'])]);
    }
    
    logActivity('SETTINGS_UPDATE', 'Admin updated system settings');
    setFlash('success', 'Settings saved successfully.');
    header('Location: /APM/admin/settings.php'); exit;
}

// Load all settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'System Settings';
$activePage = 'settings';
include BASE_PATH . '/includes/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-gear text-primary me-2"></i>System Settings</h4>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

<div class="row g-4">
    <!-- General -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>General Settings</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">System Name</label>
                    <input type="text" class="form-control" name="site_name" value="<?= e($settings['site_name']??'APM Leave Management') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Default Annual Leave Days</label>
                    <input type="number" class="form-control" name="annual_leave_days" value="<?= e($settings['annual_leave_days']??21) ?>" min="1" max="365">
                    <div class="form-text">Applied to new users by default</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Leave Priority Rule</label>
                    <select class="form-select" name="priority_rule">
                        <option value="fifo_seniority" <?= ($settings['priority_rule']??'')=='fifo_seniority'?'selected':'' ?>>First Come + Seniority</option>
                        <option value="fifo_only" <?= ($settings['priority_rule']??'')=='fifo_only'?'selected':'' ?>>First Come Only</option>
                        <option value="seniority_only" <?= ($settings['priority_rule']??'')=='seniority_only'?'selected':'' ?>>Seniority Only</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Enable Carryover</label>
                    <select class="form-select" name="carryover_enabled">
                        <option value="1" <?= ($settings['carryover_enabled']??'1')=='1'?'selected':'' ?>>Yes - Carry unused days to next year</option>
                        <option value="0" <?= ($settings['carryover_enabled']??'1')=='0'?'selected':'' ?>>No - Reset each year</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Max Days Per Request</label>
                    <input type="number" class="form-control" name="max_leave_days_per_request" value="<?= e($settings['max_leave_days_per_request']??30) ?>" min="1" max="365">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Session Timeout (minutes)</label>
                    <input type="number" class="form-control" name="session_timeout" value="<?= e($settings['session_timeout']??480) ?>" min="15" max="1440">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Calendar Settings -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2"></i>Calendar Color Thresholds</h6></div>
            <div class="card-body">
                <p class="text-muted small">Set how many people over the max limit triggers orange/red color on calendar</p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <span class="badge bg-warning text-dark me-2">🟠 Orange</span> Over-limit threshold
                    </label>
                    <input type="number" class="form-control" name="orange_threshold" value="<?= e($settings['orange_threshold']??1) ?>" min="1" max="10">
                    <div class="form-text">Show orange when X people over limit</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <span class="badge bg-danger me-2">🔴 Red</span> Heavy over-limit threshold
                    </label>
                    <input type="number" class="form-control" name="red_threshold" value="<?= e($settings['red_threshold']??3) ?>" min="1" max="20">
                    <div class="form-text">Show red when X+ people over limit</div>
                </div>
            </div>
        </div>
        
        <!-- Email Settings -->
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold"><i class="bi bi-envelope me-2"></i>Email Notifications</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Notifications</label>
                    <select class="form-select" name="email_notifications">
                        <option value="1" <?= ($settings['email_notifications']??'1')=='1'?'selected':'' ?>>Enabled</option>
                        <option value="0" <?= ($settings['email_notifications']??'1')=='0'?'selected':'' ?>>Disabled</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">SMTP Host</label>
                    <input type="text" class="form-control" name="smtp_host" value="<?= e($settings['smtp_host']??'') ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Port</label>
                        <input type="number" class="form-control" name="smtp_port" value="<?= e($settings['smtp_port']??587) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">From Email</label>
                        <input type="email" class="form-control" name="smtp_from" value="<?= e($settings['smtp_from']??'') ?>">
                    </div>
                </div>
                <div class="mb-3 mt-2">
                    <label class="form-label fw-semibold">SMTP Username</label>
                    <input type="text" class="form-control" name="smtp_user" value="<?= e($settings['smtp_user']??'') ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">SMTP Password</label>
                    <input type="password" class="form-control" name="smtp_pass" placeholder="Leave blank to keep current">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Backup -->
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white"><h6 class="mb-0 fw-bold"><i class="bi bi-cloud-download me-2"></i>Backup Settings</h6></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Backup Retention (days)</label>
                        <input type="number" class="form-control" name="backup_retention_days" value="<?= e($settings['backup_retention_days']??30) ?>" min="1" max="365">
                    </div>
                    <div class="col-md-3">
                        <a href="/APM/admin/backup.php" class="btn btn-outline-dark">
                            <i class="bi bi-cloud-download me-2"></i>Manual Backup Now
                        </a>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Cron Job Setup:</strong> Add this to Hostinger cron (daily):
                            <code class="d-block mt-1">0 2 * * * php /home/username/public_html/APM/admin/cron_backup.php</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-4 text-end">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>Save All Settings
    </button>
</div>
</form>

<?php include BASE_PATH . '/includes/footer.php'; ?>
