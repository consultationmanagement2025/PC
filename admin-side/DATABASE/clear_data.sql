-- =============================================================
-- Clear All Data (Keep User Accounts)
-- This script clears all consultation, vote, document, and submission data
-- while preserving user accounts and their settings
-- =============================================================

USE `pc_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- Clear consultation-related data
TRUNCATE TABLE `consultation_communication`;
TRUNCATE TABLE `consultation_guest_votes`;
TRUNCATE TABLE `consultation_votes`;
TRUNCATE TABLE `consultation_bookings`;
TRUNCATE TABLE `consultation_availability`;
TRUNCATE TABLE `consultations`;

-- Clear posts and comments
TRUNCATE TABLE `post_comments`;
TRUNCATE TABLE `posts`;

-- Clear feedback
TRUNCATE TABLE `feedback`;

-- Clear announcements
TRUNCATE TABLE `announcements`;

-- Clear documents
TRUNCATE TABLE `admin_documents`;
TRUNCATE TABLE `documents`;

-- Clear issue reports
TRUNCATE TABLE `issue_reports`;

-- Clear notifications
TRUNCATE TABLE `notifications`;

-- Clear logs
TRUNCATE TABLE `audit_logs`;
TRUNCATE TABLE `user_logs`;
TRUNCATE TABLE `process_history`;

-- Clear survey data
TRUNCATE TABLE `survey_response_items`;
TRUNCATE TABLE `survey_responses`;
TRUNCATE TABLE `survey_options`;
TRUNCATE TABLE `survey_questions`;
TRUNCATE TABLE `survey_templates`;

-- Clear security tables
TRUNCATE TABLE `rate_limits`;
TRUNCATE TABLE `two_factor_auth`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- Done
-- =============================================================
