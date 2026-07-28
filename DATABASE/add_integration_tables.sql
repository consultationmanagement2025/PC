-- PCMS Integration Tables Schema Migration
-- Created: 2026-07-29

-- 1. External API Clients & API Keys Table
CREATE TABLE IF NOT EXISTS `api_clients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_name` VARCHAR(100) NOT NULL,
    `api_key_hash` VARCHAR(255) NOT NULL,
    `api_key_prefix` VARCHAR(16) NOT NULL,
    `allowed_scopes` VARCHAR(255) DEFAULT 'read:consultations',
    `rate_limit_per_min` INT DEFAULT 60,
    `status` ENUM('active', 'suspended', 'revoked') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Webhook Subscriptions Table
CREATE TABLE IF NOT EXISTS `webhook_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_name` VARCHAR(100) NOT NULL,
    `target_url` VARCHAR(255) NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `secret_token` VARCHAR(100) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Webhook Execution Logs Table
CREATE TABLE IF NOT EXISTS `webhook_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subscription_id` INT NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `payload_json` TEXT NOT NULL,
    `response_code` INT DEFAULT 0,
    `status` ENUM('success', 'failed', 'pending_retry') DEFAULT 'pending_retry',
    `retry_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`subscription_id`) REFERENCES `webhook_subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Outbound SMS Outbox Table
CREATE TABLE IF NOT EXISTS `sms_outbox` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_phone` VARCHAR(20) NOT NULL,
    `message_text` TEXT NOT NULL,
    `provider_reference` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('queued', 'sent', 'failed') DEFAULT 'queued',
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
