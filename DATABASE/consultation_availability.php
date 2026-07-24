<?php
require_once __DIR__ . '/../db.php';

// Create consultation_availability table
$sql = "CREATE TABLE IF NOT EXISTS consultation_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL DEFAULT '09:00:00',
    end_time TIME NOT NULL DEFAULT '17:00:00',
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    max_consultations INT NOT NULL DEFAULT 5,
    current_consultations INT NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_admin_date (admin_id, date),
    INDEX idx_date (date),
    INDEX idx_admin_id (admin_id)
)";

if ($conn->query($sql)) {
    echo "Table consultation_availability created successfully\n";
} else {
    echo "Error creating consultation_availability table: " . $conn->error . "\n";
}

// Create consultation_bookings table for scheduled consultations
$sql = "CREATE TABLE IF NOT EXISTS consultation_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    availability_id INT NOT NULL,
    user_id INT,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled',
    meeting_type ENUM('in_person', 'online', 'phone') NOT NULL DEFAULT 'in_person',
    meeting_location VARCHAR(255),
    meeting_link VARCHAR(500),
    notes TEXT,
    reminder_sent BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_consultation_id (consultation_id),
    INDEX idx_availability_id (availability_id),
    INDEX idx_user_id (user_id),
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status)
)";

if ($conn->query($sql)) {
    echo "Table consultation_bookings created successfully\n";
} else {
    echo "Error creating consultation_bookings table: " . $conn->error . "\n";
}

// Insert default availability for next 30 days (for testing)
$admin_id = 1; // Assuming admin has ID 1
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+30 days'));

// Clear existing availability for this admin
$delete_sql = "DELETE FROM consultation_availability WHERE admin_id = ?";
$stmt = $conn->prepare($delete_sql);
$stmt->execute([$admin_id]);

// Insert availability for each day
$insert_sql = "INSERT INTO consultation_availability 
    (admin_id, date, is_available, max_consultations, notes) 
    VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);

$current = new DateTime($start_date);
$end = new DateTime($end_date);

while ($current <= $end) {
    $date_str = $current->format('Y-m-d');
    $day_of_week = (int)$current->format('w'); // 0 = Sunday, 6 = Saturday
    
    // Make weekends unavailable
    $is_available = ($day_of_week != 0 && $day_of_week != 6);
    $max_consultations = $is_available ? 5 : 0;
    $notes = $is_available ? 'Regular business hours' : 'Weekend - Not available';
    
    $stmt->execute([$admin_id, $date_str, $is_available, $max_consultations, $notes]);
    $current->modify('+1 day');
}

echo "Default availability for next 30 days inserted successfully\n";

?>
