<?php
require_once __DIR__ . '/../db.php';

echo "Cleaning and re-seeding feedback table with correct sentiment tags...\n";

// Truncate feedback
$conn->query("TRUNCATE TABLE feedback");

$feedbackData = [
    // Consultation 1: Waste Segregation
    1 => [
        ['Juan Dela Cruz', 'juan.delacruz@yahoo.com', 2, 'Waste Management', 'Stricter segregation schedules are needed because trash collectors still mix organic and recyclable bins during morning pickup.', 'negative', -2.50],
        ['Elena Bautista', 'elena.bautista@outlook.com', 5, 'Recycling & Sanitation', 'We fully support the mandatory segregation policy! Please provide more neighborhood materials recovery facility (MRF) drop-off points.', 'positive', 2.50],
        ['Ramon Fernandez', 'ramon.f@gmail.com', 4, 'Public Information', 'Regular barangay eco-seminars and educational flyers in Tagalog would help households comply better with the plastic reduction guidelines.', 'positive', 2.50],
        ['Teresa Reyes', 'teresa.reyes@valenzuela.ph', 2, 'Enforcement & Compliance', 'Need clearer fines and penalty enforcement for commercial establishments that dump non-biodegradable waste into open waterways.', 'negative', -2.50],
        ['Antonio Mendoza', 'antonio.mendoza@gmail.com', 4, 'Community Support', 'Great initiative for cleaner streets. Hopefully the city can provide branded color-coded trash bags to encourage low-income families.', 'positive', 2.50]
    ],
    // Consultation 2: Bike Lanes
    2 => [
        ['Maria Santos', 'maria.santos@gmail.com', 4, 'Bike Infrastructure', 'Protected bike lanes along McArthur Highway will protect daily commuters and students from fast-moving trucks.', 'positive', 2.50],
        ['Roberto Cruz', 'roberto.cruz@yahoo.com', 2, 'Traffic & Enforcement', 'Bollards should be installed permanently; delivery vans constantly block bike lanes during morning rush hour.', 'negative', -2.50],
        ['Carmela Ocampo', 'carmela.ocampo@gmail.com', 5, 'Commuter Safety', 'Expanding connected bicycle routes makes cycling to work much safer. Excellent initiative by the LGU!', 'positive', 2.50],
        ['Gabriel Torres', 'gabriel.torres@outlook.com', 3, 'Urban Planning', 'Ensure bike lane surfaces are smooth and adequately lit at night for evening call-center workers.', 'neutral', 0.00],
        ['Lourdes Garcia', 'lourdes.garcia@gmail.com', 4, 'Accessibility', 'Add bike parking racks near public markets and LRT stations so commuters can park securely.', 'positive', 2.50],
        ['Paolo Roxas', 'paolo.roxas@valenzuela.gov.ph', 4, 'Public Safety', 'Integrating dedicated solar lighting along bike corridors will encourage night cycling.', 'positive', 2.50]
    ],
    // Consultation 3: Flood Control Plan
    3 => [
        ['Elena Bautista', 'elena.bautista@outlook.com', 2, 'Flood Control', 'The drainage culverts along McArthur Highway frequently clog during high tide. High-capacity pumping stations should be prioritized.', 'negative', -2.50],
        ['Ramon Fernandez', 'ramon.f@gmail.com', 4, 'Urban Drainage', 'Upgrading neighborhood street drainage before the typhoon season will greatly reduce water buildup in residential compounds.', 'positive', 2.50],
        ['Teresa Reyes', 'teresa.reyes@valenzuela.ph', 2, 'Road Construction', 'Please coordinate road diggings with water utility companies so newly paved roads are not immediately destroyed for pipe repairs.', 'negative', -2.50],
        ['Antonio Mendoza', 'antonio.mendoza@gmail.com', 4, 'Public Infrastructure', 'Debris traps along major river outlets are essential to maintain fast water discharge during heavy rainfall.', 'positive', 2.50]
    ],
    // Consultation 4: Flood Control Initiative
    4 => [
        ['Maria Santos', 'maria.santos@gmail.com', 5, 'River Dredging', 'River dredging and wall reinforcement along Polo River must be completed urgently to safeguard riverside barangays.', 'positive', 2.50],
        ['Juan Dela Cruz', 'juan.delacruz@yahoo.com', 2, 'Pumping Stations', 'Automated diesel backup generators are needed at all pumping stations so operations do not fail during power outages.', 'negative', -2.50],
        ['Roberto Cruz', 'roberto.cruz@yahoo.com', 4, 'Drainage Maintenance', 'Regular quarterly declogging of underground drainage pipes prevents seasonal flooding in commercial districts.', 'positive', 2.50],
        ['Carmela Ocampo', 'carmela.ocampo@gmail.com', 3, 'Community Warning', 'Early flood warning sirens should be synchronized with barangay emergency response units.', 'neutral', 0.00],
        ['Gabriel Torres', 'gabriel.torres@outlook.com', 4, 'Infrastructure Resilience', 'Constructing elevated seawalls and floodgates protects coastal lowlands from tidal surges.', 'positive', 2.50]
    ],
    // Consultation 5: Plastic Ban Enforcement
    5 => [
        ['Teresa Reyes', 'teresa.reyes@valenzuela.ph', 4, 'Single-Use Plastics', 'Public markets need strict inspection for single-use plastic bags. Reusable bayong incentives should be introduced.', 'positive', 2.50],
        ['Antonio Mendoza', 'antonio.mendoza@gmail.com', 2, 'Business Compliance', 'Supermarkets and fast-food chains must comply with paper packaging; penalties for violations should be enforced.', 'negative', -2.50],
        ['Maria Santos', 'maria.santos@gmail.com', 5, 'Environmental Health', 'Eliminating styrofoam food containers will drastically reduce plastic pollution clogging our city waterways.', 'positive', 2.50],
        ['Juan Dela Cruz', 'juan.delacruz@yahoo.com', 4, 'Public Information', 'Conduct eco-friendly packaging trade fairs so small sari-sari store owners can source affordable paper alternatives.', 'positive', 2.50],
        ['Elena Bautista', 'elena.bautista@outlook.com', 3, 'Implementation Timeline', 'Provide a 60-day grace period for small vendors to transition away from existing plastic bag inventories.', 'neutral', 0.00],
        ['Ramon Fernandez', 'ramon.f@gmail.com', 4, 'Community Education', 'School zero-waste campaigns and barangay plastic redemption centers encourage youth participation.', 'positive', 2.50]
    ],
    // Consultation 17: Parks & Open Spaces
    17 => [
        ['Juan Dela Cruz', 'juan.delacruz@yahoo.com', 3, 'Park Amenities', 'Public parks need clean, accessible public restrooms and dedicated drinking fountains for seniors and children.', 'neutral', 0.00],
        ['Elena Bautista', 'elena.bautista@outlook.com', 5, 'Community Spaces', 'The park revitalization program is wonderful! Adding exercise equipment and free WiFi will bring our community together.', 'positive', 2.50],
        ['Ramon Fernandez', 'ramon.f@gmail.com', 4, 'Security & Maintenance', 'Ensure 24/7 security guards and adequate lighting at night to prevent vandalism and unauthorized gatherings.', 'positive', 2.50],
        ['Teresa Reyes', 'teresa.reyes@valenzuela.ph', 4, 'Urban Greenery', 'Requesting more shaded green areas and indigenous tree plantings rather than pure concrete pavement in community plazas.', 'positive', 2.50],
        ['Antonio Mendoza', 'antonio.mendoza@gmail.com', 3, 'Children Playground', 'Playground equipment must be certified safe and cushioned with rubber flooring to prevent toddler injuries.', 'neutral', 0.00],
        ['Carmela Ocampo', 'carmela.ocampo@gmail.com', 5, 'Public Wellness', 'Outdoor fitness equipment and jogging paths promote healthy living among senior citizens.', 'positive', 2.50]
    ],
    // Consultation 18: Community Park Lighting
    18 => [
        ['Antonio Mendoza', 'antonio.mendoza@gmail.com', 5, 'Public Safety & Utilities', 'Installation of solar LED lighting along interior park alleys will improve public safety and cut barangay electricity expenses.', 'positive', 2.50],
        ['Maria Santos', 'maria.santos@gmail.com', 4, 'Night Security', 'Bright solar lamps around basketball courts allow youth to play safely at night and discourage loitering.', 'positive', 2.50],
        ['Roberto Cruz', 'roberto.cruz@yahoo.com', 2, 'Maintenance & Repairs', 'Defective lights must have a 48-hour repair response time so dark corners do not become crime hazards.', 'negative', -2.50],
        ['Carmela Ocampo', 'carmela.ocampo@gmail.com', 4, 'Energy Efficiency', 'Solar-powered poles with battery storage ensure lighting remains functional even during typhoon blackout power outages.', 'positive', 2.50]
    ],
    // Consultation 20: Test Proposal
    20 => [
        ['Teresa Reyes', 'teresa.reyes@valenzuela.ph', 4, 'Public Service Access', 'Public service desks should have scheduled weekend consultations for daily wage earners who cannot take time off work.', 'positive', 2.50],
        ['Antonio Mendoza', 'antonio.mendoza@gmail.com', 5, 'Digital Portal Convenience', 'Online document submission and status tracking greatly reduce financial burdens and travel time for residents.', 'positive', 2.50],
        ['Carmela Ocampo', 'carmela.ocampo@gmail.com', 4, 'Barangay Outreach', 'Need more mobile information desks in outer barangays to serve residents far from the city hall center.', 'positive', 2.50],
        ['Roberto Cruz', 'roberto.cruz@yahoo.com', 3, 'Public Information', 'Clearer public notices regarding application requirements to avoid citizens making repeated trips to city offices.', 'neutral', 0.00],
        ['Maria Santos', 'maria.santos@gmail.com', 4, 'Community Support', 'Establishing clear SLA response targets for citizen inquiries builds strong trust between LGU and the public.', 'positive', 2.50],
        ['Juan Dela Cruz', 'juan.delacruz@yahoo.com', 5, 'Service Efficiency', 'Automated SMS notification alerts when public documents are ready for pickup saves valuable citizen time.', 'positive', 2.50]
    ],
    // Consultation 24: Legal Assistance
    24 => [
        ['Maria Santos', 'maria.santos@gmail.com', 4, 'Legal Accessibility', 'Legal assistance desks should have scheduled weekend consultations for daily wage earners who cannot take time off work.', 'positive', 2.50],
        ['Juan Dela Cruz', 'juan.delacruz@yahoo.com', 5, 'Indigent Legal Aid', 'Free notary and document drafting services for indigents and senior citizens will greatly reduce financial burdens.', 'positive', 2.50],
        ['Elena Bautista', 'elena.bautista@outlook.com', 4, 'Barangay Justice System', 'Need more paralegal volunteers in outer barangays to mediate neighborhood disputes before filing formal court cases.', 'positive', 2.50],
        ['Ramon Fernandez', 'ramon.f@gmail.com', 3, 'Public Information', 'Clearer public notices regarding requirements for free legal counseling to avoid citizens making repeated trips.', 'neutral', 0.00],
        ['Teresa Reyes', 'teresa.reyes@valenzuela.ph', 2, 'Service Speed', 'Average waiting times during pilot runs were over 2 hours. A digital queue appointment system is needed.', 'negative', -2.50],
        ['Antonio Mendoza', 'antonio.mendoza@gmail.com', 4, 'Scope of Assistance', 'Clarify whether family law and tenant-landlord disputes are covered under the scope of assistance.', 'positive', 2.50]
    ]
];

$stmt = $conn->prepare("INSERT INTO feedback (guest_name, guest_email, guest_phone, consultation_id, rating, category, message, sentiment_tag, sentiment_score, topic_tags, tracking_token, feedback_hash, status, created_at) VALUES (?, ?, '09171234567', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reviewed', NOW())");

$count = 0;
foreach ($feedbackData as $cId => $items) {
    foreach ($items as $idx => $item) {
        $name = $item[0];
        $email = $item[1];
        $rating = (int)$item[2];
        $cat = $item[3];
        $msg = $item[4];
        $sentimentTag = (string)$item[5];
        $score = (float)$item[6];
        $tags = json_encode([$cat, 'Public Consultation']);
        $tok = 'FDBK-' . date('Y') . '-' . strtoupper(substr(md5($cId . $email . $idx), 0, 6));
        $fHash = hash('sha256', $cId . '|' . $email . '|' . $msg);

        // Types: s = string, i = int, d = double
        // Parameters: name(s), email(s), cId(i), rating(i), cat(s), msg(s), sentimentTag(s), score(d), tags(s), tok(s), fHash(s) -> ssiisssdsss
        $stmt->bind_param('ssiisssdsss', $name, $email, $cId, $rating, $cat, $msg, $sentimentTag, $score, $tags, $tok, $fHash);
        $stmt->execute();
        $count++;
    }
}
$stmt->close();

echo "Successfully re-seeded {$count} feedback rows with valid sentiment_tag strings!\n";
