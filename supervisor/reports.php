<?php
/**
 * APM - Supervisor Reports
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/functions.php';

requireRole(['supervisor', 'admin']);

$_GET['team'] = currentUser()['team_id'];
include BASE_PATH . '/admin/reports.php';
