<?php
/**
 * APM - Session Management & Security
 */

require_once __DIR__ . '/config.php';

// Start session securely
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ];
        session_set_cookie_params($cookieParams);
        session_start();
        
        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['last_regeneration'])) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// Check if user is logged in
function isLoggedIn(): bool {
    startSecureSession();
    if (!isset($_SESSION['user_id'], $_SESSION['user_ip'])) return false;
    if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
        destroySession();
        return false;
    }
    // Session timeout check
    $timeout = (int)(getSetting('session_timeout') ?? 480) * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        destroySession();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// Require login - redirect if not authenticated
function requireLogin(string $redirect = '/APM/index.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

// Require specific role
function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        include BASE_PATH . '/includes/403.php';
        exit;
    }
}

// Create login session
function createSession(array $user): void {
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id']         = $user['id'];
    $_SESSION['email']           = $user['email'];
    $_SESSION['first_name']      = $user['first_name'];
    $_SESSION['last_name']       = $user['last_name'];
    $_SESSION['role']            = $user['role_name'];
    $_SESSION['role_id']         = $user['role_id'];
    $_SESSION['shift_id']        = $user['shift_id'];
    $_SESSION['team_id']         = $user['team_id'];
    $_SESSION['user_ip']         = $_SERVER['REMOTE_ADDR'];
    $_SESSION['last_activity']   = time();
    $_SESSION['last_regeneration'] = time();
}

// Destroy session
function destroySession(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
}

// Generate CSRF token
function generateCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCsrf(string $token): bool {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// XSS Protection - escape output
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Sanitize input
function sanitize(mixed $input): string {
    return trim(strip_tags((string)$input));
}

// Get setting from DB
function getSetting(string $key, mixed $default = null): mixed {
    static $settings = [];
    if (empty($settings)) {
        try {
            $pdo = getDB();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            return $default;
        }
    }
    return $settings[$key] ?? $default;
}

// Log activity
function logActivity(string $action, string $description = '', int $userId = 0): void {
    try {
        $pdo = getDB();
        $uid = $userId ?: ($_SESSION['user_id'] ?? null);
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $uid,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]);
    } catch (Exception $e) {
        error_log('Log error: ' . $e->getMessage());
    }
}

// Flash messages
function setFlash(string $type, string $message): void {
    startSecureSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    startSecureSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Current user info
function currentUser(): array {
    return [
        'id'         => $_SESSION['user_id'] ?? 0,
        'email'      => $_SESSION['email'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name'  => $_SESSION['last_name'] ?? '',
        'role'       => $_SESSION['role'] ?? '',
        'role_id'    => $_SESSION['role_id'] ?? 3,
        'shift_id'   => $_SESSION['shift_id'] ?? null,
        'team_id'    => $_SESSION['team_id'] ?? null,
        'full_name'  => trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')),
    ];
}

// Check if admin
function isAdmin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function isSupervisor(): bool { return ($_SESSION['role'] ?? '') === 'supervisor'; }
function isOperator(): bool { return ($_SESSION['role'] ?? '') === 'operator'; }
