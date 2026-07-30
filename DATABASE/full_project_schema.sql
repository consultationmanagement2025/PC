-- =============================================================
-- PCMS Full Database Schema
-- Project: CAPSTONE/PC (Valenzuela Public Consultation Portal)
-- Database: pc_db
-- =============================================================

CREATE DATABASE IF NOT EXISTS `pc_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `pc_db`;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =============================================================
-- 1) USERS
-- =============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `fullname` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `username` VARCHAR(100) DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'citizen',
    `district` VARCHAR(50) DEFAULT NULL,
    `barangay` VARCHAR(100) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `last_login` DATETIME DEFAULT NULL,
    `profile_photo` VARCHAR(500) DEFAULT NULL,
    `language` VARCHAR(10) NOT NULL DEFAULT 'en',
    `theme` VARCHAR(10) NOT NULL DEFAULT 'light',
    `email_notif` TINYINT(1) NOT NULL DEFAULT 1,
    `announcement_notif` TINYINT(1) NOT NULL DEFAULT 1,
    `feedback_notif` TINYINT(1) NOT NULL DEFAULT 1,
    `valid_id_path` VARCHAR(255) DEFAULT NULL,
    `verification_status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    `reset_token` VARCHAR(255) DEFAULT NULL,
    `reset_expires` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    UNIQUE KEY `uk_users_username` (`username`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 2) CONSULTATIONS
-- =============================================================
CREATE TABLE IF NOT EXISTS `consultations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `admin_id` INT(11) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `topic` VARCHAR(255) DEFAULT NULL,
    `description` LONGTEXT NOT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `district` VARCHAR(50) DEFAULT NULL,
    `barangay` VARCHAR(100) DEFAULT NULL,
    `status` ENUM(
        'draft','pending','active','viewed','replied','completed','closed',
        'archived','approved','rejected','disapproved','scheduled'
    ) NOT NULL DEFAULT 'active',
    `type` ENUM('admin','user') NOT NULL DEFAULT 'admin',
    `start_date` DATETIME DEFAULT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `preferred_datetime` DATETIME DEFAULT NULL,
    `scheduled_datetime` DATETIME DEFAULT NULL,
    `scheduled_date` DATE DEFAULT NULL,
    `scheduled_time` TIME DEFAULT NULL,
    `consultation_date` DATE DEFAULT NULL,
    `consultation_method` VARCHAR(50) DEFAULT NULL,
    `user_name` VARCHAR(255) DEFAULT NULL,
    `user_email` VARCHAR(255) DEFAULT NULL,
    `allow_email_notifications` TINYINT(1) NOT NULL DEFAULT 1,
    `expected_posts` INT(11) NOT NULL DEFAULT 0,
    `views` INT(11) NOT NULL DEFAULT 0,
    `posts_count` INT(11) NOT NULL DEFAULT 0,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `source_url` VARCHAR(255) DEFAULT NULL,
    `response_mode` ENUM('feedback','survey','hybrid') NOT NULL DEFAULT 'hybrid',
    `survey_question` VARCHAR(255) DEFAULT NULL,
    `survey_option_a` VARCHAR(100) DEFAULT 'Agree',
    `survey_option_b` VARCHAR(100) DEFAULT 'Disagree',
    `allow_guest_quick_vote` TINYINT(1) NOT NULL DEFAULT 1,
    `allow_guest_verified_vote` TINYINT(1) NOT NULL DEFAULT 1,
    `admin_note` TEXT DEFAULT NULL,
    `has_communication` TINYINT(1) NOT NULL DEFAULT 0,
    `last_communication_at` DATETIME DEFAULT NULL,
    `booking_id` INT(11) DEFAULT NULL,
    `summary_token` VARCHAR(128) DEFAULT NULL,
    `summary_token_expires` DATETIME DEFAULT NULL,
    `edit_token` VARCHAR(64) DEFAULT NULL,
    `edit_token_expires` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_consultations_status` (`status`),
    KEY `idx_consultations_type` (`type`),
    KEY `idx_consultations_dates` (`start_date`, `end_date`),
    KEY `idx_consultations_admin` (`admin_id`),
    KEY `idx_consultations_user` (`user_id`),
    KEY `idx_consultations_booking` (`booking_id`),
    CONSTRAINT `fk_consultations_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_consultations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 3) POSTS
-- =============================================================
CREATE TABLE IF NOT EXISTS `posts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `consultation_id` INT(11) DEFAULT NULL,
    `author` VARCHAR(255) DEFAULT NULL,
    `content` LONGTEXT NOT NULL,
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `category` VARCHAR(100) NOT NULL DEFAULT 'General',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_posts_created_at` (`created_at`),
    KEY `idx_posts_user_id` (`user_id`),
    KEY `idx_posts_consultation` (`consultation_id`),
    KEY `idx_posts_status` (`status`),
    CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_posts_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 4) FEEDBACK
-- =============================================================
CREATE TABLE IF NOT EXISTS `feedback` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `guest_name` VARCHAR(255) DEFAULT NULL,
    `guest_email` VARCHAR(255) DEFAULT NULL,
    `guest_phone` VARCHAR(50) DEFAULT NULL,
    `consultation_id` INT(11) DEFAULT NULL,
    `rating` INT(11) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `message` LONGTEXT,
    `status` ENUM('new','reviewed','responded','closed','pending','approved','rejected') NOT NULL DEFAULT 'new',
    `admin_response` LONGTEXT,
    `admin_respondent` INT(11) DEFAULT NULL,
    `responded_at` TIMESTAMP NULL DEFAULT NULL,
    `edit_token` VARCHAR(64) DEFAULT NULL,
    `edit_token_expires` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feedback_consultation` (`consultation_id`),
    KEY `idx_feedback_status` (`status`),
    KEY `idx_feedback_admin` (`admin_respondent`),
    CONSTRAINT `fk_feedback_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_feedback_admin` FOREIGN KEY (`admin_respondent`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_feedback_rating` CHECK (`rating` IS NULL OR (`rating` >= 1 AND `rating` <= 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 5) ANNOUNCEMENTS
-- =============================================================
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `image_path` VARCHAR(255) DEFAULT NULL,
    `admin_id` INT(11) DEFAULT NULL,
    `admin_user` VARCHAR(255) DEFAULT NULL,
    `visibility` VARCHAR(50) NOT NULL DEFAULT 'public',
    `status` VARCHAR(50) NOT NULL DEFAULT 'published',
    `allow_comments` TINYINT(1) NOT NULL DEFAULT 1,
    `liked_by` LONGTEXT DEFAULT NULL,
    `saved_by` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_announcements_created` (`created_at`),
    KEY `idx_announcements_status` (`status`),
    KEY `idx_announcements_visibility` (`visibility`),
    KEY `idx_announcements_admin` (`admin_id`),
    CONSTRAINT `fk_announcements_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 6) CONSULTATION AVAILABILITY + BOOKINGS
-- =============================================================
CREATE TABLE IF NOT EXISTS `consultation_availability` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `admin_id` INT(11) NOT NULL,
    `date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `max_bookings` INT(11) NOT NULL DEFAULT 1,
    `current_bookings` INT(11) NOT NULL DEFAULT 0,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_avail_date` (`date`),
    KEY `idx_avail_admin_date` (`admin_id`, `date`),
    KEY `idx_avail_available` (`is_available`),
    CONSTRAINT `fk_consultation_availability_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `consultation_bookings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` INT(11) NOT NULL,
    `availability_id` INT(11) NOT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `citizen_name` VARCHAR(255) DEFAULT NULL,
    `citizen_email` VARCHAR(255) DEFAULT NULL,
    `booking_status` ENUM('pending','confirmed','cancelled','completed','scheduled','no_show') NOT NULL DEFAULT 'pending',
    `booking_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `meeting_type` ENUM('in_person','online','phone') DEFAULT 'in_person',
    `meeting_location` VARCHAR(255) DEFAULT NULL,
    `meeting_link` VARCHAR(500) DEFAULT NULL,
    `confirmed_at` TIMESTAMP NULL DEFAULT NULL,
    `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
    `admin_notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_bookings_consultation` (`consultation_id`),
    KEY `idx_bookings_availability` (`availability_id`),
    KEY `idx_bookings_user` (`user_id`),
    KEY `idx_bookings_status` (`booking_status`),
    CONSTRAINT `fk_consultation_bookings_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_consultation_bookings_availability` FOREIGN KEY (`availability_id`) REFERENCES `consultation_availability` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_consultation_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_consultations_booking_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = 'fk_consultations_booking'
      AND TABLE_NAME = 'consultations'
);
SET @fk_consultations_booking_sql := IF(
    @fk_consultations_booking_exists = 0,
    'ALTER TABLE `consultations` ADD CONSTRAINT `fk_consultations_booking` FOREIGN KEY (`booking_id`) REFERENCES `consultation_bookings` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_fk_consultations_booking FROM @fk_consultations_booking_sql;
EXECUTE stmt_fk_consultations_booking;
DEALLOCATE PREPARE stmt_fk_consultations_booking;

-- =============================================================
-- 7) VOTES + COMMENTS + COMMUNICATION
-- =============================================================
CREATE TABLE IF NOT EXISTS `consultation_votes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `vote_option` ENUM('agree','disagree') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_consultation_user` (`consultation_id`,`user_id`),
    KEY `idx_vote_consultation` (`consultation_id`),
    KEY `idx_vote_option` (`vote_option`),
    CONSTRAINT `fk_vote_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vote_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `consultation_guest_votes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` INT(11) NOT NULL,
    `guest_email` VARCHAR(255) DEFAULT NULL,
    `device_token` VARCHAR(64) NOT NULL,
    `vote_option` ENUM('agree','disagree') NOT NULL,
    `otp_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `ip_hash` CHAR(64) DEFAULT NULL,
    `user_agent_hash` CHAR(64) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_guest_consultation_device` (`consultation_id`,`device_token`),
    KEY `idx_guest_vote_consultation` (`consultation_id`),
    KEY `idx_guest_vote_option` (`vote_option`),
    CONSTRAINT `fk_guest_vote_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `consultation_comments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comments_consultation_id` (`consultation_id`),
    KEY `idx_comments_user_id` (`user_id`),
    CONSTRAINT `fk_comments_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `consultation_communication` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` INT(11) NOT NULL,
    `sender_type` ENUM('admin','user') NOT NULL,
    `sender_id` INT(11) NOT NULL,
    `sender_name` VARCHAR(255) NOT NULL,
    `sender_email` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `message_type` ENUM('initial_review','admin_response','user_reply','status_update') NOT NULL DEFAULT 'admin_response',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comm_consultation_id` (`consultation_id`),
    KEY `idx_comm_created_at` (`created_at`),
    CONSTRAINT `fk_comm_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 8) DOCUMENTS
-- Unified schema for both DATABASE/documents.php and
-- DATABASE/document-management.php usage.
-- =============================================================
CREATE TABLE IF NOT EXISTS `documents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` INT(11) DEFAULT NULL,
    `reference` VARCHAR(100) DEFAULT '',
    `reference_number` VARCHAR(50) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT 'ordinance',
    `document_type` ENUM('consultation_form','attachment','response','final_document') DEFAULT 'consultation_form',
    `status` ENUM('draft','submitted','reviewed','approved','rejected') NOT NULL DEFAULT 'submitted',
    `document_date` DATE DEFAULT NULL,
    `description` LONGTEXT,
    `tags` TEXT,
    `original_filename` VARCHAR(255) DEFAULT NULL,
    `stored_filename` VARCHAR(255) DEFAULT NULL,
    `file_type` VARCHAR(100) DEFAULT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` BIGINT(20) DEFAULT NULL,
    `uploaded_by` INT(11) DEFAULT NULL,
    `upload_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `views` INT(11) NOT NULL DEFAULT 0,
    `downloads` INT(11) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_docs_consultation` (`consultation_id`),
    KEY `idx_docs_reference` (`reference_number`),
    KEY `idx_docs_type` (`type`),
    KEY `idx_docs_status` (`status`),
    KEY `idx_docs_document_date` (`document_date`),
    KEY `idx_docs_created_at` (`created_at`),
    CONSTRAINT `fk_docs_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_docs_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 9) NOTIFICATIONS
-- Note: user_id allows value 0 for broadcast notifications.
-- =============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `message` LONGTEXT NOT NULL,
    `type` VARCHAR(100) NOT NULL DEFAULT 'info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_id` (`user_id`),
    KEY `idx_notifications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 10) LOGS
-- =============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `admin_user` VARCHAR(255) NOT NULL,
    `admin_id` INT(11) DEFAULT NULL,
    `action` VARCHAR(500) NOT NULL,
    `entity_type` VARCHAR(100) DEFAULT NULL,
    `entity_id` INT(11) DEFAULT NULL,
    `old_value` LONGTEXT,
    `new_value` LONGTEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT,
    `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(50) NOT NULL DEFAULT 'success',
    `details` LONGTEXT,
    PRIMARY KEY (`id`),
    KEY `idx_audit_admin_id` (`admin_id`),
    KEY `idx_audit_timestamp` (`timestamp`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `username` VARCHAR(255) NOT NULL,
    `action` VARCHAR(500) NOT NULL,
    `action_type` VARCHAR(100) DEFAULT NULL,
    `entity_type` VARCHAR(100) DEFAULT NULL,
    `entity_id` INT(11) DEFAULT NULL,
    `description` LONGTEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT,
    `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(50) NOT NULL DEFAULT 'success',
    `details` LONGTEXT,
    PRIMARY KEY (`id`),
    KEY `idx_user_logs_user_id` (`user_id`),
    KEY `idx_user_logs_timestamp` (`timestamp`),
    KEY `idx_user_logs_action` (`action`),
    KEY `idx_user_logs_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 11) SECURITY TABLES
-- =============================================================
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `identifier` VARCHAR(255) NOT NULL,
    `window_start` INT(11) NOT NULL,
    `window_expires` INT(11) NOT NULL,
    `attempt_count` INT(11) NOT NULL DEFAULT 1,
    `locked_until` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_rate_limits_identifier` (`identifier`),
    KEY `idx_rate_limits_identifier` (`identifier`),
    KEY `idx_rate_limits_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_password_history_user_id` (`user_id`),
    CONSTRAINT `fk_password_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `two_factor_auth` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `totp_secret` VARCHAR(32) NOT NULL,
    `backup_codes` VARCHAR(500) DEFAULT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_two_factor_user` (`user_id`),
    KEY `idx_two_factor_user_id` (`user_id`),
    CONSTRAINT `fk_two_factor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `code` VARCHAR(6) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email_verifications_email` (`email`),
    KEY `idx_email_verifications_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 12) SEED DATA (DEFAULT ADMIN ACCOUNT)
-- Login email: admin@pcms.local
-- Login password: Admin@12345
-- =============================================================
INSERT INTO `users` (
    `fullname`,
    `name`,
    `username`,
    `email`,
    `password`,
    `role`,
    `status`,
    `verification_status`
) VALUES (
    'System Administrator',
    'System Administrator',
    'admin',
    'admin@pcms.local',
    '$2y$10$9EngZTEJPwSvW9o0nxaIS.NtpNWJXJzZkI08uIt32YyBhKSKTEbqi',
    'admin',
    'active',
    'verified'
)
ON DUPLICATE KEY UPDATE
    `password` = VALUES(`password`),
    `role` = VALUES(`role`),
    `status` = VALUES(`status`),
    `verification_status` = VALUES(`verification_status`),
    `fullname` = VALUES(`fullname`),
    `name` = VALUES(`name`),
    `username` = VALUES(`username`);

-- =============================================================
-- Done
-- =============================================================
