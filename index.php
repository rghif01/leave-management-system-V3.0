<?php
/**
 * APM Leave Management System - Login Page
 */
define('BASE_PATH', __DIR__);
require_once 'config/session.php';
require_once 'config/functions.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'operator';
    header("Location: /APM/{$role}/dashboard.php");
    exit;
}

$error = '';
$pageTitle = 'Login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';
    
    if (!validateCsrf($csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "SELECT u.*, r.name as role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.email = ? AND u.active = 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            createSession($user);
            
            // Update last login
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            
            logActivity('LOGIN', "User logged in: {$user['email']}", $user['id']);
            
            $role = $user['role_name'];
            header("Location: /APM/{$role}/dashboard.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
            logActivity('LOGIN_FAILED', "Failed login attempt for: {$email}");
            sleep(1); // Brute force delay
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a3c6e">
    <title>Login - APM Leave Management</title>
    <link rel="manifest" href="/APM/manifest.json">
    <link rel="apple-touch-icon" href="/APM/assets/icons/icon-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #1a3c6e 0%, #2563a8 50%, #1e40af 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card { 
            max-width: 420px; width: 100%; 
            border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: slideUp 0.4s ease;
        }
        @keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .login-header { 
            background: linear-gradient(135deg, #1a3c6e, #2563a8); 
            border-radius: 20px 20px 0 0; padding: 2rem; text-align: center;
        }
        .login-logo { 
            width: 70px; height: 70px; background: white; border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .login-logo span { font-size: 28px; font-weight: 900; color: #1a3c6e; }
        .btn-login { 
            background: linear-gradient(135deg, #1a3c6e, #2563a8); 
            border: none; padding: 0.75rem; font-size: 1rem; font-weight: 600;
            transition: opacity 0.2s;
        }
        .btn-login:hover { opacity: 0.9; }
        .form-control:focus { border-color: #2563a8; box-shadow: 0 0 0 0.2rem rgba(37,99,168,0.2); }
        .input-group-text { background: #f8fafc; }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="login-card card mx-auto">
        <div class="login-header">
            <div class="login-logo"><span>APM</span></div>
            <h4 class="text-white fw-bold mb-1">Leave Management</h4>
            <p class="text-white-50 mb-0 small">Sign in to your account</p>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="your@email.com" required autofocus autocomplete="email">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="password" 
                               id="passwordField" placeholder="••••••••" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePwd">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100 rounded-3 text-white">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>
            
            <div class="text-center mt-3 text-muted small">
                <i class="bi bi-shield-check text-success"></i> Secured with industry-standard encryption
            </div>
        </div>
    </div>
    
    <p class="text-center text-white-50 mt-3 small">
        &copy; <?= date('Y') ?> APM Port Management &mdash; Leave System v1.0
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Password toggle
document.getElementById('togglePwd')?.addEventListener('click', () => {
    const f = document.getElementById('passwordField');
    const icon = document.getElementById('eyeIcon');
    f.type = f.type === 'password' ? 'text' : 'password';
    icon.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});

// PWA
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/APM/service-worker.js').catch(() => {});
}
</script>
</body>
</html>
