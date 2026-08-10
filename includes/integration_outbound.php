<?php
/**
 * PCMS outbound — sync consultation updates back to PHMS (+ ORTS on close)
 */

$http_client_file = __DIR__ . '/../../shared/integration/HttpClient.php';
if (file_exists($http_client_file)) {
    require_once $http_client_file;
} elseif (file_exists(__DIR__ . '/../shared/integration/HttpClient.php')) {
    require_once __DIR__ . '/../shared/integration/HttpClient.php';
}

function pcms_consultation_submission_counts(mysqli $conn, int $consultationId): array
{
    $counts = [
        'posts' => 0,
        'feedback' => 0,
        'votes' => 0,
        'total_submissions' => 0,
    ];

    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM posts WHERE consultation_id = {$consultationId}");
        if ($r && ($row = $r->fetch_assoc())) {
            $counts['posts'] = (int) $row['c'];
        }
    } catch (Throwable $e) {
    }

    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM feedback WHERE consultation_id = {$consultationId}");
        if ($r && ($row = $r->fetch_assoc())) {
            $counts['feedback'] = (int) $row['c'];
        }
    } catch (Throwable $e) {
        // feedback table schema may differ
        try {
            $r = $conn->query("SELECT COUNT(*) AS c FROM consultation_feedback WHERE consultation_id = {$consultationId}");
            if ($r && ($row = $r->fetch_assoc())) {
                $counts['feedback'] = (int) $row['c'];
            }
        } catch (Throwable $e2) {
        }
    }

    try {
        $r = $conn->query(
            "SELECT
                (SELECT COUNT(*) FROM consultation_votes WHERE consultation_id = {$consultationId})
              + (SELECT COUNT(*) FROM consultation_guest_votes WHERE consultation_id = {$consultationId}) AS c"
        );
        if ($r && ($row = $r->fetch_assoc())) {
            $counts['votes'] = (int) $row['c'];
        }
    } catch (Throwable $e) {
    }

    $counts['total_submissions'] = $counts['posts'] + $counts['feedback'] + $counts['votes'];
    return $counts;
}

function pcms_integration_on_consultation_updated(array $consultation, string $event = 'consultation_sync'): void
{
    if (!function_exists('lgu2_client')) {
        return;
    }
    $client = lgu2_client('PCMS');
    $linked = !empty($consultation['phms_hearing_id']) || !empty($consultation['external_ref']);
    $eventName = $linked ? $event : 'consultation_update';

    $payload = [
        'event' => $eventName,
        'consultation_id' => $consultation['id'] ?? null,
        'hearing_id' => $consultation['phms_hearing_id'] ?? null,
        'title' => $consultation['title'] ?? null,
        'status' => $consultation['status'] ?? null,
        'external_ref' => $consultation['external_ref'] ?? ('PCMS-C-' . ($consultation['id'] ?? '')),
        'source_system' => 'PCMS',
        'linked' => $linked,
    ];

    if (!empty($consultation['submission_counts']) && is_array($consultation['submission_counts'])) {
        $payload['submission_counts'] = $consultation['submission_counts'];
        $payload['posts_count'] = $consultation['submission_counts']['posts'] ?? 0;
        $payload['feedback_count'] = $consultation['submission_counts']['feedback'] ?? 0;
        $payload['votes_count'] = $consultation['submission_counts']['votes'] ?? 0;
        $payload['total_submissions'] = $consultation['submission_counts']['total_submissions'] ?? 0;
    }

    $client->notify('PHMS', 'events.php', $payload);
}

function pcms_integration_on_consultation_closed(array $consultation): void
{
    global $conn;

    $id = isset($consultation['id']) ? (int) $consultation['id'] : 0;
    $counts = ['posts' => 0, 'feedback' => 0, 'votes' => 0, 'total_submissions' => 0];
    if ($id > 0 && isset($conn) && $conn instanceof mysqli) {
        $counts = pcms_consultation_submission_counts($conn, $id);
    }

    $consultation['status'] = 'closed';
    $consultation['submission_counts'] = $counts;
    pcms_integration_on_consultation_updated($consultation, 'consultation_sync');

    // Optional ORTS note when we can resolve an ordinance link
    if (!function_exists('lgu2_client')) {
        return;
    }
    $client = lgu2_client('PCMS');
    $ortsDocId = null;
    $ref = $consultation['external_ref'] ?? null;
    if (is_string($ref) && preg_match('/ORTS-DOC-(\d+)/i', $ref, $m)) {
        $ortsDocId = (int) $m[1];
    }

    // Try resolve via PHMS hearing → orts_document_id
    $hearingId = isset($consultation['phms_hearing_id']) ? (int) $consultation['phms_hearing_id'] : 0;
    if (!$ortsDocId && $hearingId > 0) {
        try {
            $phms = lgu2_phms_pdo();
            if ($phms) {
                $stmt = $phms->prepare('SELECT orts_document_id, external_ref FROM public_hearings WHERE id = ? LIMIT 1');
                $stmt->execute([$hearingId]);
                $h = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($h) {
                    if (!empty($h['orts_document_id'])) {
                        $ortsDocId = (int) $h['orts_document_id'];
                    }
                    if (!$ref && !empty($h['external_ref'])) {
                        $ref = $h['external_ref'];
                    }
                }
            }
        } catch (Throwable $e) {
            // PHMS DB may be unavailable; skip ORTS note
        }
    }

    if ($ortsDocId || $ref) {
        $total = (int) ($counts['total_submissions'] ?? 0);
        $summary = "PCMS consultation #{$id} closed. Submissions: {$total}"
            . " (posts={$counts['posts']}, feedback={$counts['feedback']}, votes={$counts['votes']})";
        $client->notify('ORTS', 'events.php', [
            'event' => 'consultation_closed',
            'document_id' => $ortsDocId,
            'reference_number' => $ref,
            'external_ref' => $ref,
            'consultation_id' => $id,
            'hearing_id' => $hearingId ?: null,
            'notes' => $summary,
            'location' => 'Public Consultation Closed',
            'submission_counts' => $counts,
            'source_system' => 'PCMS',
        ]);
    }
}

/**
 * Sends event payload to CMS (Committee Management System) Live Endpoint
 */
function pcms_send_cms_event(array $customPayload = []): array
{
    $cmsApiUrl = "https://cms.spvalenzuela.com/api/v1/events.php";

    $defaultPayload = [
        "source_system"   => "PCMS",
        "event"           => "consultation_feedback",
        "consultation_id" => 12,
        "committee_id"    => 3,
        "committee_name"  => "Committee on Finance",
        "title"           => "Public Consultation on Ordinance No. 001",
        "description"     => "Consultation feedback requiring committee review.",
        "referral_date"   => date('Y-m-d'),
        "notes"           => "Referred for committee hearing and action."
    ];

    $payload = array_merge($defaultPayload, array_filter($customPayload, function ($v) {
        return $v !== null && $v !== '';
    }));

    $ch = curl_init($cmsApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer cms_live_9c1e5a7b3f8042d6b8e2a4c7f1d90638'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => ($error === '' && $httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'payload' => $payload
    ];
}

