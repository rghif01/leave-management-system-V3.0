<?php
/**
 * APM - Leave & Shift Helper Functions
 */

require_once __DIR__ . '/config.php';

/**
 * Calculate working days between two dates, excluding holidays
 */
function calculateLeaveDays(string $startDate, string $endDate, bool $excludeHolidays = true): float {
    $start = new DateTime($startDate);
    $end   = new DateTime($endDate);
    
    if ($start > $end) return 0;
    
    $holidays = [];
    if ($excludeHolidays) {
        $holidays = getApprovedHolidayDates($startDate, $endDate);
    }
    
    $days = 0;
    $current = clone $start;
    while ($current <= $end) {
        $dateStr = $current->format('Y-m-d');
        if (!in_array($dateStr, $holidays)) {
            $days++;
        }
        $current->modify('+1 day');
    }
    return (float)$days;
}

/**
 * Get approved holiday dates in range
 */
function getApprovedHolidayDates(string $startDate, string $endDate): array {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "SELECT holiday_date FROM holidays 
             WHERE active = 1 AND requires_approval = 0
             AND holiday_date BETWEEN ? AND ?"
        );
        $stmt->execute([$startDate, $endDate]);
        return array_column($stmt->fetchAll(), 'holiday_date');
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get leave balance for user/year
 */
function getLeaveBalance(int $userId, int $year = 0): array {
    if (!$year) $year = (int)date('Y');
    $pdo = getDB();
    
    // Check if balance record exists
    $stmt = $pdo->prepare("SELECT * FROM leave_balances WHERE user_id = ? AND year = ?");
    $stmt->execute([$userId, $year]);
    $balance = $stmt->fetch();
    
    if (!$balance) {
        // Create default balance
        $defaultDays = (int)(getSetting('annual_leave_days') ?? 21);
        $carryover = 0;
        
        // Check previous year carryover
        if (getSetting('carryover_enabled', '1') == '1') {
            $prevStmt = $pdo->prepare("SELECT (annual_days + carryover_days - used_days) as remaining 
                                       FROM leave_balances WHERE user_id = ? AND year = ?");
            $prevStmt->execute([$userId, $year - 1]);
            $prev = $prevStmt->fetch();
            if ($prev && $prev['remaining'] > 0) {
                $carryover = (float)$prev['remaining'];
            }
        }
        
        $ins = $pdo->prepare("INSERT INTO leave_balances (user_id, year, annual_days, carryover_days) VALUES (?, ?, ?, ?)");
        $ins->execute([$userId, $year, $defaultDays, $carryover]);
        
        return [
            'user_id'       => $userId,
            'year'          => $year,
            'annual_days'   => $defaultDays,
            'carryover_days'=> $carryover,
            'used_days'     => 0,
            'pending_days'  => 0,
            'total_days'    => $defaultDays + $carryover,
            'available_days'=> $defaultDays + $carryover,
        ];
    }
    
    $total = (float)$balance['annual_days'] + (float)$balance['carryover_days'];
    $available = $total - (float)$balance['used_days'] - (float)$balance['pending_days'];
    
    return array_merge($balance, [
        'total_days'     => $total,
        'available_days' => max(0, $available),
    ]);
}

/**
 * Get shift schedule type for a date
 * Rotation pattern: every 3 days cycles through morning/afternoon/night/off
 * Reference: Shift D = Afternoon on 2025-01-01
 */
function getShiftSchedule(int $shiftId, string $date): string {
    // Check DB first
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT schedule_type FROM shift_rotation WHERE shift_id = ? AND rotation_date = ?");
        $stmt->execute([$shiftId, $date]);
        $row = $stmt->fetch();
        if ($row) return $row['schedule_type'];
    } catch (Exception $e) {}
    
    // Calculate from rotation algorithm
    $schedules = ['morning', 'afternoon', 'night', 'off'];
    $referenceDate = new DateTime('2025-01-01');
    // Reference: Shift D (id=4) = afternoon on 2025-01-01
    // Shift A = morning, Shift B = off, Shift C = night on that date
    $shiftOffsets = [
        1 => 0, // Shift A = morning (offset 0)
        2 => 3, // Shift B = off (offset 3)
        3 => 2, // Shift C = night (offset 2)
        4 => 1, // Shift D = afternoon (offset 1)
    ];
    
    $targetDate = new DateTime($date);
    $daysDiff = (int)$referenceDate->diff($targetDate)->format('%r%a');
    $offset = $shiftOffsets[$shiftId] ?? 0;
    $index = (($daysDiff + $offset) % 4 + 4) % 4;
    
    return $schedules[$index];
}

/**
 * Get how many employees are on leave for a team on a date
 */
function getTeamLeaveCount(int $teamId, string $date): int {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM leave_requests 
             WHERE team_id = ? AND status IN ('supervisor_approved','approved')
             AND ? BETWEEN start_date AND end_date"
        );
        $stmt->execute([$teamId, $date]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get calendar color for a team on a date
 */
function getCalendarColor(int $teamId, string $date, int $maxAllowed): string {
    $count = getTeamLeaveCount($teamId, $date);
    $orange = (int)(getSetting('orange_threshold') ?? 1);
    $red    = (int)(getSetting('red_threshold') ?? 3);
    
    if ($count < $maxAllowed) return 'green';
    if ($count < $maxAllowed + $orange) return 'orange';
    if ($count < $maxAllowed + $red) return 'orange';
    return 'red';
}

/**
 * Generate unique request number
 */
function generateRequestNumber(): string {
    return 'LR-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Send notification to user
 */
function sendNotification(int $userId, string $title, string $message, string $type = 'info', ?int $requestId = null): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, type, related_request_id) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $title, $message, $type, $requestId]);
    } catch (Exception $e) {
        error_log('Notification error: ' . $e->getMessage());
    }
}

/**
 * Get unread notification count for current user
 */
function getUnreadNotificationCount(int $userId): int {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Format date for display
 */
function formatDate(string $date): string {
    return date('d M Y', strtotime($date));
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status): string {
    $map = [
        'pending'             => ['warning', 'Pending'],
        'supervisor_approved' => ['info', 'Supervisor Approved'],
        'approved'            => ['success', 'Approved'],
        'rejected'            => ['danger', 'Rejected'],
        'cancelled'           => ['secondary', 'Cancelled'],
    ];
    $cfg = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge bg-' . $cfg[0] . '">' . $cfg[1] . '</span>';
}

/**
 * Get schedule badge HTML
 */
function scheduleBadge(string $type): string {
    $map = [
        'morning'   => ['warning', '☀️ Morning'],
        'afternoon' => ['info', '🌤️ Afternoon'],
        'night'     => ['dark', '🌙 Night'],
        'off'       => ['secondary', '🔴 OFF'],
    ];
    $cfg = $map[$type] ?? ['secondary', ucfirst($type)];
    return '<span class="badge bg-' . $cfg[0] . '">' . $cfg[1] . '</span>';
}
