<?php
/**
 * APM - Notifications Page (all roles)
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireLogin();

$user = currentUser();
$pdo  = getDB();
$role = $user['role'];

// Mark all as read
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);

// Get all notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user['id']]);
$notifs = $stmt->fetchAll();

$pageTitle = 'Notifications';
$activePage = 'notifications';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-bell text-primary me-2"></i>Notifications</h4>
    <span class="badge bg-secondary"><?= count($notifs) ?> total</span>
</div>

<?php if (empty($notifs)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-bell-slash fs-1 text-muted d-block mb-2"></i>
        <p class="text-muted">No notifications yet</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="list-group list-group-flush">
        <?php foreach ($notifs as $n): ?>
        <div class="list-group-item <?= !$n['is_read'] ? 'notification-unread' : '' ?> py-3">
            <div class="d-flex align-items-start gap-3">
                <?php $icons = ['success'=>'✅','danger'=>'❌','warning'=>'⚠️','info'=>'ℹ️']; ?>
                <span class="fs-4"><?= $icons[$n['type']] ?? 'ℹ️' ?></span>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <div class="fw-semibold small"><?= e($n['title']) ?></div>
                        <div class="text-muted" style="font-size:0.72rem"><?= date('d M y H:i', strtotime($n['created_at'])) ?></div>
                    </div>
                    <div class="text-muted small mt-1"><?= e($n['message']) ?></div>
                    <?php if ($n['related_request_id']): ?>
                    <a href="/APM/<?= $role ?>/<?= $role==='operator'?'my_leaves':'leaves' ?>.php" class="btn btn-xs btn-outline-primary mt-1 py-0 px-2" style="font-size:0.73rem">View Request</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
