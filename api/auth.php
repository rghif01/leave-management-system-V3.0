<?php
/**
 * APM - Auth API (logout, etc.)
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'logout':
        if (isLoggedIn()) {
            logActivity('LOGOUT', 'User logged out');
        }
        destroySession();
        header('Location: /APM/index.php');
        exit;

    case 'check_session':
        header('Content-Type: application/json');
        echo json_encode(['logged_in' => isLoggedIn()]);
        break;
        
    default:
        header('Location: /APM/index.php');
        exit;
}
