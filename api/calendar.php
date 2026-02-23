<?php
/**
 * APM - Calendar Events API
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireLogin();

header('Content-Type: application/json');

$user = currentUser();
$pdo  = getDB();

$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end'] ?? date('Y-m-t');
$teamId = (int)($_GET['team_id'] ?? $user['team_id'] ?? 0);

// Sanitize dates
$start = date('Y-m-d', strtotime($start));
$end   = date('Y-m-d', strtotime($end));

$events = [];

// 1. Approved leave requests
$params = [$start, $end];
$where  = "lr.status IN ('approved','supervisor_approved') AND lr.end_date >= ? AND lr.start_date <= ?";

if (!isAdmin() && isSupervisor()) {
    $where .= " AND lr.team_id = ?";
    $params[] = $user['team_id'];
} elseif (isOperator()) {
    $where .= " AND (lr.user_id = ? OR lr.team_id = ?)";
    $params[] = $user['id'];
    $params[] = $user['team_id'];
} elseif ($teamId && isAdmin()) {
    $where .= " AND lr.team_id = ?";
    $params[] = $teamId;
}

$stmt = $pdo->prepare(
    "SELECT lr.id, lr.request_number, lr.start_date, lr.end_date, lr.status,
            u.first_name, u.last_name, t.name as team_name, t.code as team_code,
            s.name as shift_name
     FROM leave_requests lr
     JOIN users u ON lr.user_id = u.id
     JOIN teams t ON lr.team_id = t.id
     JOIN shifts s ON lr.shift_id = s.id
     WHERE $where
     ORDER BY lr.start_date"
);
$stmt->execute($params);

foreach ($stmt->fetchAll() as $row) {
    $colorMap = [
        'approved' => '#10b981',
        'supervisor_approved' => '#3b82f6',
        'pending' => '#f59e0b',
        'rejected' => '#ef4444',
    ];
    $color = $colorMap[$row['status']] ?? '#6b7280';
    
    $events[] = [
        'id'    => 'leave_' . $row['id'],
        'title' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['team_code'] . ')',
        'start' => $row['start_date'],
        'end'   => date('Y-m-d', strtotime($row['end_date'] . ' +1 day')),
        'color' => $color,
        'extendedProps' => [
            'type'     => 'leave',
            'status'   => $row['status'],
            'team'     => $row['team_name'],
            'shift'    => $row['shift_name'],
            'request'  => $row['request_number'],
        ]
    ];
}

// 2. Holidays
$hStmt = $pdo->prepare(
    "SELECT id, name, holiday_date, type FROM holidays 
     WHERE active = 1 AND holiday_date BETWEEN ? AND ?"
);
$hStmt->execute([$start, $end]);
foreach ($hStmt->fetchAll() as $h) {
    $events[] = [
        'id'    => 'holiday_' . $h['id'],
        'title' => '🌙 ' . $h['name'],
        'start' => $h['holiday_date'],
        'allDay'=> true,
        'color' => '#6366f1',
        'extendedProps' => ['type' => 'holiday', 'holiday_type' => $h['type']]
    ];
}

// 3. Shift schedule for current user (operator/supervisor)
if (!isAdmin() && $user['shift_id']) {
    $d = new DateTime($start);
    $eDate = new DateTime($end);
    while ($d <= $eDate) {
        $dateStr = $d->format('Y-m-d');
        $schedule = getShiftSchedule($user['shift_id'], $dateStr);
        $scheduleColors = ['morning'=>'#fef3c7','afternoon'=>'#dbeafe','night'=>'#e9d5ff','off'=>'#f3f4f6'];
        $textColors = ['morning'=>'#92400e','afternoon'=>'#1e40af','night'=>'#6b21a8','off'=>'#4b5563'];
        $scheduleLabels = ['morning'=>'☀️ Morning','afternoon'=>'🌤️ Afternoon','night'=>'🌙 Night','off'=>'🔴 OFF'];
        
        if ($schedule !== 'off') {
            $events[] = [
                'id'            => 'shift_' . $dateStr,
                'title'         => $scheduleLabels[$schedule] ?? $schedule,
                'start'         => $dateStr,
                'allDay'        => true,
                'backgroundColor' => $scheduleColors[$schedule] ?? '#f3f4f6',
                'borderColor'   => 'transparent',
                'textColor'     => $textColors[$schedule] ?? '#4b5563',
                'display'       => 'background',
                'extendedProps' => ['type' => 'shift', 'schedule' => $schedule]
            ];
        }
        $d->modify('+1 day');
    }
}

echo json_encode($events);
