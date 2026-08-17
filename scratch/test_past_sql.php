<?php
require_once __DIR__ . '/../db.php';

$past_sql = "SELECT id, title, category, response_mode, status, end_date, created_at 
            FROM consultations 
            WHERE status IN ('closed', 'completed', 'archived', 'officialized', 'passed', 'enacted', 'resolved') 
               OR (end_date IS NOT NULL AND end_date != '' AND end_date < NOW())
            ORDER BY created_at DESC LIMIT 12";

$res = $conn->query($past_sql);
echo "Past items count: " . ($res ? $res->num_rows : 0) . "\n";
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']} | Status: {$r['status']} | EndDate: {$r['end_date']} | Title: {$r['title']}\n";
}
