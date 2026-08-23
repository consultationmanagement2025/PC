<?php
require_once __DIR__ . '/../db.php';

$title = 'Proposed Community Legal Assistance Program';
$chk = $conn->prepare("SELECT id FROM consultations WHERE title = ? LIMIT 1");
$chk->bind_param('s', $title);
$chk->execute();
$res = $chk->get_result();

if (!$res || $res->num_rows === 0) {
    echo "Inserting '$title' into consultations table...\n";
    $stmt = $conn->prepare("INSERT INTO consultations (
        title, description, category, status, type, end_date, committee_assigned, tracking_number, created_at
    ) VALUES (
        ?, 
        'Citywide public consultation to establish free barangay-level legal aid desks and counseling services for indigent citizens and daily wage earners in Valenzuela City.',
        'Governance',
        'closed',
        'admin',
        '2026-08-15 23:59:59',
        'Rules & Governance Committee',
        'TRK-2026-000008',
        '2026-08-01 10:00:00'
    )");
    $stmt->bind_param('s', $title);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    echo "Created Consultation #$newId!\n";
    
    // Seed 6 realistic citizen submissions
    $feedbacks = [
        ['Maria Santos', 'maria.santos@gmail.com', 'Legal Accessibility', 'Legal assistance desks should have scheduled weekend consultations for daily wage earners who cannot take time off work.', 4, 'positive'],
        ['Juan Dela Cruz', 'juan.delacruz@yahoo.com', 'Indigent Legal Aid', 'Free notary and document drafting services for indigents and senior citizens will greatly reduce financial burdens.', 5, 'positive'],
        ['Elena Bautista', 'elena.bautista@outlook.com', 'Barangay Justice System', 'Need more paralegal volunteers in outer barangays to mediate neighborhood disputes before filing formal court cases.', 4, 'positive'],
        ['Ramon Fernandez', 'ramon.f@gmail.com', 'Public Information', 'Clearer public notices regarding requirements for free legal counseling to avoid citizens making repeated trips.', 3, 'neutral'],
        ['Teresa Reyes', 'teresa.reyes@valenzuela.ph', 'Service Speed', 'Average waiting times during pilot runs were over 2 hours. A digital queue appointment system is needed.', 2, 'negative'],
        ['Antonio Mendoza', 'antonio.mendoza@gmail.com', 'Scope of Assistance', 'Clarify whether family law and tenant-landlord disputes are covered under the scope of assistance.', 4, 'positive']
    ];
    
    foreach ($feedbacks as $idx => $f) {
        $tok = 'FDBK-' . date('Y') . '-' . strtoupper(substr(md5($newId . $f[1] . $idx), 0, 6));
        $fHash = hash('sha256', $newId . '|' . $f[1] . '|' . $f[3]);
        $score = $f[5] === 'positive' ? 2.5 : ($f[5] === 'negative' ? -2.5 : 0.0);
        $tags = json_encode(['Governance', $f[2]]);
        
        $fStmt = $conn->prepare("INSERT INTO feedback (guest_name, guest_email, consultation_id, rating, category, message, sentiment_tag, sentiment_score, topic_tags, tracking_token, feedback_hash, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reviewed', NOW())");
        $fStmt->bind_param('ssiisssdsss', $f[0], $f[1], $newId, $f[4], $f[2], $f[3], $f[5], $score, $tags, $tok, $fHash);
        $fStmt->execute();
        $fStmt->close();
    }
    
    $conn->query("UPDATE consultations SET posts_count = 6 WHERE id = $newId");
    echo "Seeded 6 citizen feedbacks for Consultation #$newId!\n";
} else {
    $row = $res->fetch_assoc();
    echo "Consultation '$title' already exists with ID #" . $row['id'] . "\n";
}
$chk->close();
