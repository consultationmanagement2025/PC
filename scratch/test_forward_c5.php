<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/test_new_visibility_logic.php';

$cRes = $conn->query("SELECT * FROM consultations WHERE id = 5");
$c5 = $cRes->fetch_assoc();

echo "\n--- BEFORE FORWARDING --- \n";
echo "ID 5 ('{$c5['title']}') visible: " . (isConsultationVisibleToExpertTest($c5, 2, 'resource_person', 'Environment & Sanitation') ? "YES" : "NO") . "\n";

$c5['forwarded_to_expert'] = 1;
echo "\n--- AFTER ADMIN FORWARDS --- \n";
echo "ID 5 ('{$c5['title']}') visible: " . (isConsultationVisibleToExpertTest($c5, 2, 'resource_person', 'Environment & Sanitation') ? "YES" : "NO") . "\n";
