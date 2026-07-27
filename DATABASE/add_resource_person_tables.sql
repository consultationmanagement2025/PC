-- Add resource person columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS expertise_areas TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS qualifications TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(50);
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT(11) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL;

-- Create resolution_reports table for storing uploaded resolution reports
CREATE TABLE IF NOT EXISTS resolution_reports (
    id INT(11) NOT NULL AUTO_INCREMENT,
    consultation_id INT(11) NOT NULL,
    uploaded_by INT(11) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consultation_id (consultation_id),
    KEY idx_uploaded_by (uploaded_by),
    CONSTRAINT fk_resolution_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    CONSTRAINT fk_resolution_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create info_requests table for storing additional information requests
CREATE TABLE IF NOT EXISTS info_requests (
    id INT(11) NOT NULL AUTO_INCREMENT,
    consultation_id INT(11) NOT NULL,
    requested_by INT(11) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'responded', 'closed') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consultation_id (consultation_id),
    KEY idx_requested_by (requested_by),
    KEY idx_user_email (user_email),
    CONSTRAINT fk_info_request_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    CONSTRAINT fk_info_request_requester FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
