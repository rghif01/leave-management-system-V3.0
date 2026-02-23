-- ============================================================
-- APM Leave Management System - Database Schema
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `u127859886_leave_manageme` 
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `u127859886_leave_manageme`;

-- ============================================================
-- Table: roles
-- ============================================================
CREATE TABLE `roles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`name`, `description`) VALUES
('admin', 'Full system access'),
('supervisor', 'Team-level access'),
('operator', 'Employee-level access');

-- ============================================================
-- Table: shifts
-- ============================================================
CREATE TABLE `shifts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#007bff',
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `shifts` (`name`, `description`, `color`) VALUES
('Shift A', 'Shift Alpha', '#28a745'),
('Shift B', 'Shift Bravo', '#007bff'),
('Shift C', 'Shift Charlie', '#fd7e14'),
('Shift D', 'Shift Delta', '#dc3545');

-- ============================================================
-- Table: teams
-- ============================================================
CREATE TABLE `teams` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `shift_id` INT(11) NOT NULL,
  `max_leave_per_day` INT(11) DEFAULT 2,
  `description` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `shift_id` (`shift_id`),
  CONSTRAINT `teams_shift_fk` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert 3 teams per shift (A=1, B=2, C=3, D=4)
INSERT INTO `teams` (`name`, `code`, `shift_id`, `max_leave_per_day`) VALUES
('Remote Crane Controller - A', 'RCC', 1, 3),
('Deck Clerk - A', 'DM', 1, 2),
('Remote Quay Crane Checker - A', 'QCC', 1, 2),
('Remote Crane Controller - B', 'RCC', 2, 3),
('Deck Clerk - B', 'DM', 2, 2),
('Remote Quay Crane Checker - B', 'QCC', 2, 2),
('Remote Crane Controller - C', 'RCC', 3, 3),
('Deck Clerk - C', 'DM', 3, 2),
('Remote Quay Crane Checker - C', 'QCC', 3, 2),
('Remote Crane Controller - D', 'RCC', 4, 3),
('Deck Clerk - D', 'DM', 4, 2),
('Remote Quay Crane Checker - D', 'QCC', 4, 2);

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employee_id` VARCHAR(50) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role_id` INT(11) NOT NULL DEFAULT 3,
  `shift_id` INT(11) DEFAULT NULL,
  `team_id` INT(11) DEFAULT NULL,
  `seniority_date` DATE DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `email_notifications` TINYINT(1) DEFAULT 1,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `shift_id` (`shift_id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `users_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `users_shift_fk` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_team_fk` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin user (password: Admin@2024)
INSERT INTO `users` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `role_id`, `seniority_date`) VALUES
('ADM001', 'System', 'Administrator', 'admin@apm.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, '2020-01-01');

-- ============================================================
-- Table: leave_balances
-- ============================================================
CREATE TABLE `leave_balances` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `year` YEAR NOT NULL,
  `annual_days` INT(11) DEFAULT 21,
  `carryover_days` DECIMAL(5,1) DEFAULT 0,
  `used_days` DECIMAL(5,1) DEFAULT 0,
  `pending_days` DECIMAL(5,1) DEFAULT 0,
  `adjusted_by` INT(11) DEFAULT NULL,
  `adjustment_note` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_year` (`user_id`, `year`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `lb_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: holidays
-- ============================================================
CREATE TABLE `holidays` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `holiday_date` DATE NOT NULL,
  `type` ENUM('national', 'religious', 'other') DEFAULT 'national',
  `recurring` TINYINT(1) DEFAULT 0,
  `requires_approval` TINYINT(1) DEFAULT 1,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Moroccan Holidays 2025
INSERT INTO `holidays` (`name`, `holiday_date`, `type`, `recurring`) VALUES
('New Year''s Day', '2025-01-01', 'national', 1),
('Proclamation of Independence', '2025-01-11', 'national', 1),
('Labor Day', '2025-05-01', 'national', 1),
('Throne Day', '2025-07-30', 'national', 1),
('Oued Ed-Dahab Day', '2025-08-14', 'national', 1),
('Revolution Day', '2025-08-20', 'national', 1),
('Youth Day', '2025-08-21', 'national', 1),
('Green March', '2025-11-06', 'national', 1),
('Independence Day', '2025-11-18', 'national', 1),
('Eid Al-Fitr', '2025-03-30', 'religious', 0),
('Eid Al-Adha', '2025-06-06', 'religious', 0),
('Islamic New Year', '2025-06-27', 'religious', 0),
('Prophet''s Birthday', '2025-09-04', 'religious', 0);

-- ============================================================
-- Table: leave_requests
-- ============================================================
CREATE TABLE `leave_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_number` VARCHAR(30) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `team_id` INT(11) NOT NULL,
  `shift_id` INT(11) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` DECIMAL(5,1) NOT NULL,
  `leave_type` ENUM('annual', 'holiday', 'emergency', 'medical', 'other') DEFAULT 'annual',
  `reason` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'supervisor_approved', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
  `supervisor_id` INT(11) DEFAULT NULL,
  `supervisor_action_at` TIMESTAMP NULL DEFAULT NULL,
  `supervisor_note` TEXT DEFAULT NULL,
  `admin_id` INT(11) DEFAULT NULL,
  `admin_action_at` TIMESTAMP NULL DEFAULT NULL,
  `admin_note` TEXT DEFAULT NULL,
  `deduct_from_balance` TINYINT(1) DEFAULT 1,
  `priority_order` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_number` (`request_number`),
  KEY `user_id` (`user_id`),
  KEY `team_id` (`team_id`),
  KEY `shift_id` (`shift_id`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`),
  CONSTRAINT `lr_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lr_team_fk` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `lr_shift_fk` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: shift_rotation
-- ============================================================
CREATE TABLE `shift_rotation` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `shift_id` INT(11) NOT NULL,
  `rotation_date` DATE NOT NULL,
  `schedule_type` ENUM('morning','afternoon','night','off') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shift_date` (`shift_id`, `rotation_date`),
  KEY `shift_id` (`shift_id`),
  CONSTRAINT `sr_shift_fk` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','success','warning','danger') DEFAULT 'info',
  `related_request_id` INT(11) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notif_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: system_settings
-- ============================================================
CREATE TABLE `system_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `setting_group` VARCHAR(50) DEFAULT 'general',
  `description` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('site_name', 'APM Leave Management', 'general', 'Site name'),
('annual_leave_days', '21', 'leave', 'Default annual leave days per employee'),
('priority_rule', 'fifo_seniority', 'leave', 'Leave priority: fifo_seniority or fifo_only'),
('email_notifications', '1', 'notifications', 'Enable email notifications'),
('smtp_host', '', 'email', 'SMTP host'),
('smtp_port', '587', 'email', 'SMTP port'),
('smtp_user', '', 'email', 'SMTP username'),
('smtp_pass', '', 'email', 'SMTP password'),
('smtp_from', 'noreply@apm.com', 'email', 'From email address'),
('backup_retention_days', '30', 'backup', 'Days to keep backups'),
('orange_threshold', '1', 'calendar', 'Days over limit to show orange'),
('red_threshold', '3', 'calendar', 'Days over limit to show red'),
('session_timeout', '480', 'security', 'Session timeout in minutes'),
('max_leave_days_per_request', '30', 'leave', 'Maximum days per single leave request'),
('carryover_enabled', '1', 'leave', 'Enable carryover of unused leave days');

-- ============================================================
-- Table: activity_logs
-- ============================================================
CREATE TABLE `activity_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `action` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Table: csrf_tokens
-- ============================================================
CREATE TABLE `csrf_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `token` VARCHAR(64) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
