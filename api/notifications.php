<?php
/**
 * APM - Notifications API
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';

requireLogin();
header('Content-Type: application/json');

$user   = currentUser();
$action = $_GET['action'] ?? 'list';
$pdo    = getDB();

switch ($action) {
    case 'count':
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user['id']]);
        echo json_encode(['count' => (int)$stmt->fetchColumn()]);
        break;

    case 'mark_read':
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
                ->execute([$id, $user['id']]);
        } else {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['id']]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'list':
    default:
        $stmt = $pdo->prepare(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute([$user['id']]);
        echo json_encode($stmt->fetchAll());
        break;
}
