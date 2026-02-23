<?php
/**
 * APM - Supervisor Calendar
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['supervisor', 'admin']);

$user = currentUser();
$pageTitle = 'Team Calendar';
$activePage = 'calendar';
include BASE_PATH . '/includes/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-calendar3 text-primary me-2"></i>Team Leave Calendar</h4>

<!-- Legend -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <span class="fw-semibold small">Legend:</span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#10b981;border-radius:3px;margin-right:4px;"></span><span class="small">Approved</span></span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#3b82f6;border-radius:3px;margin-right:4px;"></span><span class="small">Sup. Approved</span></span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#f59e0b;border-radius:3px;margin-right:4px;"></span><span class="small">Pending</span></span>
            <span><span style="display:inline-block;width:14px;height:14px;background:#6366f1;border-radius:3px;margin-right:4px;"></span><span class="small">Holiday</span></span>
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
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        height: 'auto',
        events: {
            url: '/APM/api/calendar.php',
            failure: function() { APM.toast('Failed to load calendar events', 'danger'); }
        },
        eventClick: function(info) {
            const p = info.event.extendedProps;
            if (p.type === 'leave') {
                const msg = `Request: \${p.request}\nStatus: \${p.status}\nTeam: \${p.team}`;
                alert(msg);
            }
        },
        dayCellDidMount: function(info) {
            // Color coding based on capacity could be enhanced here
        },
        eventMouseEnter: function(info) {
            const p = info.event.extendedProps;
            if (p.type === 'leave') {
                info.el.setAttribute('title', `\${info.event.title}\nStatus: \${p.status}\nRequest: \${p.request}`);
            }
        }
    });
    calendar.render();
});
JS;

include BASE_PATH . '/includes/footer.php';
?>
