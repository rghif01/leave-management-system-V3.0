<?php
/**
 * APM - Operator Calendar
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['operator', 'supervisor', 'admin']);

$user = currentUser();
$pageTitle = 'My Calendar';
$activePage = 'calendar';
include BASE_PATH . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-calendar3 text-primary me-2"></i>My Shift & Leave Calendar</h4>
    <a href="/APM/operator/apply.php" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>Apply Leave</a>
</div>

<!-- Legend -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-3 small align-items-center">
            <span class="fw-semibold">Legend:</span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#fef3c7;border:1px solid #f59e0b;border-radius:2px;margin-right:4px;"></span>Morning Shift</span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#dbeafe;border:1px solid #3b82f6;border-radius:2px;margin-right:4px;"></span>Afternoon Shift</span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#e9d5ff;border:1px solid #8b5cf6;border-radius:2px;margin-right:4px;"></span>Night Shift</span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#10b981;border-radius:2px;margin-right:4px;"></span>Approved Leave</span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#f59e0b;border-radius:2px;margin-right:4px;"></span>Pending Leave</span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#6366f1;border-radius:2px;margin-right:4px;"></span>Holiday</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<?php
$extraJs = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        height: 'auto',
        events: {
            url: '/APM/api/calendar.php',
            failure: () => APM.toast('Could not load calendar', 'danger')
        },
        eventClick: function(info) {
            const p = info.event.extendedProps;
            if (p.type === 'leave') {
                const lines = [
                    'Request: ' + (p.request || ''),
                    'Status: ' + p.status,
                    'Dates: ' + info.event.startStr + ' to ' + info.event.endStr
                ];
                alert(lines.join('\n'));
            } else if (p.type === 'holiday') {
                alert('Holiday: ' + info.event.title.replace('🌙 ', ''));
            }
        },
        eventMouseEnter: function(info) {
            const p = info.event.extendedProps;
            let tip = info.event.title;
            if (p.request) tip += '\nRequest #: ' + p.request;
            if (p.status) tip += '\nStatus: ' + p.status;
            info.el.title = tip;
        }
    });
    calendar.render();
});
JS;

include BASE_PATH . '/includes/footer.php';
?>
