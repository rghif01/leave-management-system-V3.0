<?php
/**
 * APM - Admin Calendar
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['admin']);

$pdo    = getDB();
$shifts = $pdo->query("SELECT * FROM shifts WHERE active=1 ORDER BY id")->fetchAll();
$teams  = $pdo->query("SELECT t.*, s.name as shift_name FROM teams t JOIN shifts s ON t.shift_id=s.id WHERE t.active=1 ORDER BY s.id, t.code")->fetchAll();

$pageTitle = 'Leave Calendar';
$activePage = 'leaves';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-calendar3 text-primary me-2"></i>Leave Calendar</h4>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" id="teamFilter" style="width:auto">
            <option value="">All Teams</option>
            <?php foreach ($teams as $t): ?>
            <option value="<?= $t['id'] ?>"><?= e($t['code'].' - '.$t['shift_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="card mb-3"><div class="card-body py-2">
    <div class="d-flex flex-wrap gap-3 small align-items-center">
        <span class="fw-semibold">Legend:</span>
        <span><span style="display:inline-block;width:12px;height:12px;background:#10b981;border-radius:2px;margin-right:3px"></span>Approved</span>
        <span><span style="display:inline-block;width:12px;height:12px;background:#3b82f6;border-radius:2px;margin-right:3px"></span>Supervisor Approved</span>
        <span><span style="display:inline-block;width:12px;height:12px;background:#f59e0b;border-radius:2px;margin-right:3px"></span>Pending</span>
        <span><span style="display:inline-block;width:12px;height:12px;background:#6366f1;border-radius:2px;margin-right:3px"></span>Holiday</span>
    </div>
</div></div>

<div class="card"><div class="card-body"><div id="calendar"></div></div></div>

<?php
$extraJs = <<<JS
let calendar;
document.addEventListener('DOMContentLoaded', function() {
    calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listMonth' },
        height: 'auto',
        events: function(info, success, failure) {
            const teamId = document.getElementById('teamFilter').value;
            fetch(`/APM/api/calendar.php?start=\${info.startStr}&end=\${info.endStr}&team_id=\${teamId}`)
                .then(r => r.json()).then(success).catch(failure);
        },
        eventClick: function(info) {
            const p = info.event.extendedProps;
            if (p.type === 'leave') {
                window.open(`/APM/admin/leave_detail.php?id=\${info.event.id.replace('leave_','')}`, '_blank');
            }
        }
    });
    calendar.render();
    
    document.getElementById('teamFilter').addEventListener('change', () => calendar.refetchEvents());
});
JS;

include BASE_PATH . '/includes/footer.php';
?>
