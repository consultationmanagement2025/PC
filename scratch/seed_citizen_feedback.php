<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/feedback.php';

initializeFeedbackTable();

echo "=== SEEDING REALISTIC CITIZEN FEEDBACK FOR ALL CONSULTATIONS ===\n";

$consultations = [];
$res = $conn->query("SELECT id, title, category, description, status FROM consultations");
while ($r = $res->fetch_assoc()) {
    $consultations[] = $r;
}

$sampleCitizens = [
    ['name' => 'Maria Santos', 'email' => 'maria.santos@gmail.com', 'phone' => '09171234567'],
    ['name' => 'Juan Dela Cruz', 'email' => 'juan.delacruz@yahoo.com', 'phone' => '09182345678'],
    ['name' => 'Elena Bautista', 'email' => 'elena.bautista@outlook.com', 'phone' => '09203456789'],
    ['name' => 'Ramon Fernandez', 'email' => 'ramon.f@gmail.com', 'phone' => '09154567890'],
    ['name' => 'Teresa Reyes', 'email' => 'teresa.reyes@valenzuela.ph', 'phone' => '09225678901'],
    ['name' => 'Antonio Mendoza', 'email' => 'antonio.mendoza@gmail.com', 'phone' => '09176789012'],
    ['name' => 'Carmela Ocampo', 'email' => 'carmela.ocampo@yahoo.com', 'phone' => '09197890123'],
    ['name' => 'Roberto Cruz', 'email' => 'roberto.cruz@gmail.com', 'phone' => '09288901234'],
];

$feedbackTemplatesByCategory = [
    'Environment' => [
        ['msg' => 'Stricter segregation schedules are needed because trash collectors still mix organic and recyclable bins during morning pickup.', 'rating' => 2, 'sentiment' => 'negative', 'category' => 'Waste Management'],
        ['msg' => 'We fully support the mandatory segregation policy! Please provide more neighborhood materials recovery facility (MRF) drop-off points.', 'rating' => 5, 'sentiment' => 'positive', 'category' => 'Recycling & Sanitation'],
        ['msg' => 'Regular barangay eco-seminars and educational flyers in Tagalog would help households comply better with the plastic reduction guidelines.', 'rating' => 4, 'sentiment' => 'positive', 'category' => 'Public Information'],
        ['msg' => 'Need clearer fines and penalty enforcement for commercial establishments that dump non-biodegradable waste into open waterways.', 'rating' => 2, 'sentiment' => 'negative', 'category' => 'Enforcement & Compliance'],
        ['msg' => 'Great initiative for cleaner streets. Hopefully the city can provide branded color-coded trash bags to encourage low-income families.', 'rating' => 4, 'sentiment' => 'positive', 'category' => 'Community Support']
    ],
    'Infrastructure' => [
        ['msg' => 'The drainage culverts along McArthur Highway frequently clog during high tide. High-capacity pumping stations should be prioritized.', 'rating' => 2, 'sentiment' => 'negative', 'category' => 'Flood Control'],
        ['msg' => 'Upgrading neighborhood street drainage before the typhoon season will greatly reduce water buildup in residential compounds.', 'rating' => 4, 'sentiment' => 'positive', 'category' => 'Urban Drainage'],
        ['msg' => 'Please coordinate road diggings with water utility companies so newly paved roads are not immediately destroyed for pipe repairs.', 'rating' => 2, 'sentiment' => 'negative', 'category' => 'Road Construction'],
        ['msg' => 'Installation of solar LED lighting along interior alleys will improve public safety and cut down barangay electricity expenses.', 'rating' => 5, 'sentiment' => 'positive', 'category' => 'Public Safety & Utilities'],
        ['msg' => 'Requesting speed bumps and pedestrian lane repainting near public school zones to safeguard students crossing daily.', 'rating' => 3, 'sentiment' => 'neutral', 'category' => 'Pedestrian Safety']
    ],
    'Traffic & Transport' => [
        ['msg' => 'Protected bike lanes need concrete bollards instead of painted lines because delivery motorcycles continuously encroach into bicycle paths.', 'rating' => 2, 'sentiment' => 'negative', 'category' => 'Bicycle Infrastructure'],
        ['msg' => 'The bike lane network makes daily commuting to work much cheaper and healthier. Please connect it to the central transport terminal.', 'rating' => 5, 'sentiment' => 'positive', 'category' => 'Active Mobility'],
        ['msg' => 'Tricycle terminals need proper loading bays so they do not block active traffic lanes during peak rush hours.', 'rating' => 3, 'sentiment' => 'neutral', 'category' => 'Traffic Management'],
        ['msg' => 'More CCTV surveillance and traffic enforcers stationed at major intersections during 5PM-8PM rush hour.', 'rating' => 4, 'sentiment' => 'positive', 'category' => 'Enforcement']
    ],
    'General Governance' => [
        ['msg' => 'Public parks need clean, accessible public restrooms and dedicated drinking fountains for seniors and children.', 'rating' => 3, 'sentiment' => 'neutral', 'category' => 'Park Amenities'],
        ['msg' => 'The park revitalization program is wonderful! Adding exercise equipment and free WiFi will bring our community together.', 'rating' => 5, 'sentiment' => 'positive', 'category' => 'Community Spaces'],
        ['msg' => 'Ensure 24/7 security guards and adequate lighting at night to prevent vandalism and unauthorized gatherings.', 'rating' => 4, 'sentiment' => 'positive', 'category' => 'Security & Maintenance'],
        ['msg' => 'Requesting more shaded green areas and indigenous tree plantings rather than pure concrete pavement in community plazas.', 'rating' => 4, 'sentiment' => 'positive', 'category' => 'Urban Greenery']
    ],
    'Governance' => [
        ['msg' => 'Legal assistance desks should have scheduled weekend consultations for daily wage earners who cannot take time off work.', 'rating' => 4, 'sentiment' => 'positive', 'category' => 'Legal Accessibility'],
        ['msg' => 'Free notary and document drafting services for indigents and senior citizens will greatly reduce financial burdens.', 'rating' => 5, 'sentiment' => 'positive', 'category' => 'Indigent Legal Aid'],
        ['msg' => 'Need more paralegal volunteers in outer barangays to mediate neighborhood disputes before filing formal court cases.', 'rating' => 4, 'sentiment' => 'positive', 'category' => 'Barangay Justice System'],
        ['msg' => 'Clearer public notices regarding requirements for free legal counseling to avoid citizens making repeated trips.', 'rating' => 3, 'sentiment' => 'neutral', 'category' => 'Public Information']
    ]
];

$fallbackTemplates = $feedbackTemplatesByCategory['General Governance'];
$totalInserted = 0;

foreach ($consultations as $c) {
    $cid = (int)$c['id'];
    $cat = $c['category'] ?? 'General Governance';
    
    $templates = $feedbackTemplatesByCategory[$cat] ?? $feedbackTemplatesByCategory['General Governance'];
    if (empty($templates)) $templates = $fallbackTemplates;
    
    $numToInsert = 4 + ($cid % 3);
    for ($i = 0; $i < $numToInsert; $i++) {
        $cit = $sampleCitizens[($i + $cid) % count($sampleCitizens)];
        $tpl = $templates[$i % count($templates)];
        
        $tracking_token = 'FDBK-' . date('Y') . '-' . strtoupper(substr(md5($cid . $cit['email'] . $i), 0, 6));
        $fbHash = hash('sha256', $cid . '|' . $cit['email'] . '|' . $tpl['msg']);
        $topicTags = json_encode([$cat, $tpl['category']]);
        
        $score = $tpl['sentiment'] === 'positive' ? 2.5 : ($tpl['sentiment'] === 'negative' ? -2.5 : 0.0);
        $tag = $tpl['sentiment'];
        $rating = $tpl['rating'];
        $category = $tpl['category'];
        $msg = $tpl['msg'];
        
        $stmt = $conn->prepare("INSERT INTO feedback (
            guest_name, guest_email, guest_phone, consultation_id, rating, category, message, 
            sentiment_tag, sentiment_score, topic_tags, tracking_token, feedback_hash, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reviewed')");
        
        if ($stmt) {
            $stmt->bind_param(
                'sssisssdssss',
                $cit['name'], $cit['email'], $cit['phone'], $cid, $rating, $category, $msg,
                $tag, $score, $topicTags, $tracking_token, $fbHash
            );
            if ($stmt->execute()) {
                $totalInserted++;
            } else {
                echo "Error inserting feedback: " . $stmt->error . "\n";
            }
            $stmt->close();
        }
    }
    
    // Update posts_count in consultations
    $cntRes = $conn->query("SELECT COUNT(*) FROM feedback WHERE consultation_id = $cid");
    $newCount = $cntRes ? (int)$cntRes->fetch_row()[0] : 0;
    $conn->query("UPDATE consultations SET posts_count = $newCount WHERE id = $cid");
    echo "Consultation #$cid ('{$c['title']}') now has $newCount feedback entries.\n";
}

echo "\nDone! Inserted $totalInserted total feedback entries.\n";
