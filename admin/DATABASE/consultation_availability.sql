-- Consultation Availability Table
-- Stores admin-defined available time slots for citizen consultations

CREATE TABLE IF NOT EXISTS `consultation_availability` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `date` date NOT NULL,
    `start_time` time NOT NULL,
    `end_time` time NOT NULL,
    `max_bookings` int(11) NOT NULL DEFAULT 1,
    `current_bookings` int(11) NOT NULL DEFAULT 0,
    `is_available` tinyint(1) NOT NULL DEFAULT 1,
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_date` (`date`),
    KEY `idx_admin_date` (`admin_id`, `date`),
    KEY `idx_available` (`is_available`),
    CONSTRAINT `fk_consultation_availability_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consultation Bookings Table
-- Links citizen consultations to specific time slots

CREATE TABLE IF NOT EXISTS `consultation_bookings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` int(11) NOT NULL,
    `availability_id` int(11) NOT NULL,
    `citizen_name` varchar(255) NOT NULL,
    `citizen_email` varchar(255) NOT NULL,
    `booking_status` enum('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
    `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
    `confirmed_at` timestamp NULL DEFAULT NULL,
    `cancelled_at` timestamp NULL DEFAULT NULL,
    `admin_notes` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_consultation` (`consultation_id`),
    KEY `idx_availability` (`availability_id`),
    KEY `idx_status` (`booking_status`),
    CONSTRAINT `fk_consultation_bookings_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_consultation_bookings_availability` FOREIGN KEY (`availability_id`) REFERENCES `consultation_availability` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update consultations table to include booking reference
ALTER TABLE `consultations` 
ADD COLUMN `booking_id` int(11) DEFAULT NULL AFTER `id`,
ADD COLUMN `scheduled_date` date DEFAULT NULL AFTER `booking_id`,
ADD COLUMN `scheduled_time` time DEFAULT NULL AFTER `scheduled_date`,
ADD KEY `idx_booking` (`booking_id`),
ADD CONSTRAINT `fk_consultations_booking` FOREIGN KEY (`booking_id`) REFERENCES `consultation_bookings` (`id`) ON DELETE SET NULL;
