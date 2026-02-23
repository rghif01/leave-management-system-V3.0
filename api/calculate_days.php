<?php
/**
 * APM - Calculate Leave Days API
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/functions.php';

header('Content-Type: application/json');

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

if (!$start || !$end || $start > $end) {
    echo json_encode(['days' => 0, 'error' => 'Invalid dates']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    echo json_encode(['days' => 0, 'error' => 'Invalid date format']);
    exit;
}

$days = calculateLeaveDays($start, $end, true);
echo json_encode(['days' => $days, 'start' => $start, 'end' => $end]);
