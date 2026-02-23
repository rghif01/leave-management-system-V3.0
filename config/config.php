<?php
/**
 * APM Leave Management System
 * Database Configuration & Connection
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'u127859886_leave_manageme');
define('DB_USER', 'u127859886_leave_user');
define('DB_PASS', 'Abdellah-1996-2013');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'APM Leave Management');
define('APP_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/APM');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH);

// Timezone
date_default_timezone_set('Africa/Casablanca');

/**
 * Get PDO database connection (singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}
