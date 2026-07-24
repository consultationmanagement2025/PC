<?php
require_once __DIR__ . '/../db.php';

// Tables are created by DATABASE/DEPLOY.sql — this script only seeds sample availability rows.

// Insert sample availability data for the next 30 days
$admin_id = 1; // Assuming admin has ID 1
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+30 days'));

// Clear existing availability for this admin
$delete_sql = "DELETE FROM consultation_availability WHERE admin_id = ?";
$stmt = $conn->prepare($delete_sql);
$stmt->execute([$admin_id]);

// Insert availability for each day
$insert_sql = "INSERT INTO consultation_availability 
    (admin_id, date, start_time, end_time, max_bookings, is_available, notes) 
    VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert_sql);

$current = new DateTime($start_date);
$end = new DateTime($end_date);

while ($current <= $end) {
    $date_str = $current->format('Y-m-d');
    $day_of_week = (int)$current->format('w'); // 0 = Sunday, 6 = Saturday
    
    // Make weekdays available, weekends unavailable
    $is_available = ($day_of_week != 0 && $day_of_week != 6);
    $max_bookings = $is_available ? 5 : 0;
    $start_time = $is_available ? '09:00:00' : '00:00:00';
    $end_time = $is_available ? '17:00:00' : '00:00:00';
    $notes = $is_available ? 'Available for consultations' : 'Weekend - Not available';
    
    $stmt->execute([$admin_id, $date_str, $start_time, $end_time, $max_bookings, $is_available, $notes]);
    $current->modify('+1 day');
}

echo "Sample availability data for next 30 days inserted successfully\n";

echo "\nDatabase setup completed!\n";

?>
