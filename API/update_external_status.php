<?php
/**
 * External Status Sync API for PCMS
 * Allows Committee System (PHMS) and Ordinance System (ORTS) to update 
 * consultation stages and status in real-time.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use HTTP POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';

// Authentication Check (API Key or Bearer Token)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
$apiKey = '';
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $apiKey = trim($matches[1]);
} elseif (!empty($_GET['api_key'])) {
    $apiKey = trim($_GET['api_key']);
} elseif (!empty($authHeader)) {
    $apiKey = trim($authHeader);
}

// Read JSON Input or POST params
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true) ?: $_POST;

$consultationId = (int)($body['consultation_id'] ?? $body['id'] ?? 0);
$externalRef    = trim((string)($body['external_ref'] ?? $body['reference_number'] ?? ''));
$sourceSystem   = trim((string)($body['source_system'] ?? $body['system'] ?? 'External System'));
$newStatus      = strtolower(trim((string)($body['status'] ?? '')));
$notes          = trim((string)($body['notes'] ?? $body['remarks'] ?? $body['description'] ?? ''));
$ordinanceNo    = trim((string)($body['ordinance_no'] ?? $body['ordinance_number'] ?? ''));

if (empty($newStatus)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameter: status. Valid status values include: committee, scheduled, ordinance, approved, enacted, officialized, completed.'
    ]);
    exit;
}

if ($consultationId <= 0 && empty($externalRef)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing consultation target. Provide consultation_id or external_ref/reference_number.'
    ]);
    exit;
}

try {
    // Find Consultation
    $consultation = null;
    if ($consultationId > 0) {
        $stmt = $conn->prepare("SELECT id, title, status FROM consultations WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $consultationId);
        $stmt->execute();
        $consultation = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$consultation && !empty($externalRef)) {
        $stmt = $conn->prepare("SELECT id, title, status FROM consultations WHERE id = ? OR reference_number = ? LIMIT 1");
        $refId = (int)preg_replace('/[^0-9]/', '', $externalRef);
        $stmt->bind_param("is", $refId, $externalRef);
        $stmt->execute();
        $consultation = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$consultation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Consultation record not found in PCMS']);
        exit;
    }

    $targetId = (int)$consultation['id'];

    // Map status to 6-Stage progress tracker numbers
    $stageMap = [
        'draft' => 1, 'pending' => 1, 'submitted' => 1,
        'active' => 2, 'voting' => 2, 'published_portal' => 2,
        'closed' => 3, 'ai_summary' => 3, 'summarized' => 3,
        'under_review' => 4, 'reviewed' => 4,
        'committee' => 5, 'scheduled' => 5, 'forwarded' => 5, 'approved' => 5, 'ordinance' => 5, 'forwarded_to_lrs' => 5,
        'officialized' => 6, 'enacted' => 6, 'completed' => 6, 'published' => 6, 'archived' => 6
    ];

    $currentStage = $stageMap[$newStatus] ?? 5;

    // Update Consultation record
    $updateStmt = $conn->prepare("UPDATE consultations SET status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("si", $newStatus, $targetId);
    $updateStmt->execute();
    $updateStmt->close();

    // Log to Audit Trail if table exists
    try {
        $logAction = "EXTERNAL_STATUS_UPDATE";
        $logDetails = "Status updated to '{$newStatus}' (Stage {$currentStage}/6) by {$sourceSystem}. Notes: {$notes}";
        $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, created_at) VALUES (0, ?, ?, NOW())");
        if ($logStmt) {
            $logStmt->bind_param("ss", $logAction, $logDetails);
            $logStmt->execute();
            $logStmt->close();
        }
    } catch (Throwable $t) {
        // Log table optional
    }

    echo json_encode([
        'success' => true,
        'message' => "Consultation #{$targetId} status updated successfully to '{$newStatus}'",
        'consultation_id' => $targetId,
        'source_system' => $sourceSystem,
        'new_status' => $newStatus,
        'current_stage' => "{$currentStage}/6",
        'ordinance_no' => $ordinanceNo,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
