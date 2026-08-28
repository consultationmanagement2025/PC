<?php
/**
 * ORTS (Ordinance Routing & Tracking System) API Integration Utility
 * Handles automated cURL dispatch of AI-summarized & validated consultations to ORTS.
 */

if (!function_exists('isConsultationCheckedByExpert')) {
    /**
     * Checks whether a consultation file/report has been formally reviewed, annotated,
     * or checked by a Resource Person (Technical Expert).
     */
    function isConsultationCheckedByExpert($consultationId, $conn) {
        if (!$conn || (int)$consultationId <= 0) return false;
        $cid = (int)$consultationId;

        // 1. Check consultations table for expert annotations or endorsed status
        $stmt = $conn->prepare("SELECT document_status, expert_notes, expert_last_updated_at, status FROM consultations WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $cid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $docStatus = strtolower(trim((string)($row['document_status'] ?? '')));
                $expertNotes = trim((string)($row['expert_notes'] ?? ''));
                $lastUpdated = $row['expert_last_updated_at'] ?? null;
                $status = strtolower(trim((string)($row['status'] ?? '')));

                if (in_array($docStatus, ['expert_annotated', 'approved', 'endorsed', 'reviewed', 'checked'], true)) {
                    return true;
                }
                if ($status === 'endorsed') {
                    return true;
                }
                if (!empty($expertNotes) && $expertNotes !== '{}' && $expertNotes !== '[]' && $expertNotes !== 'null') {
                    return true;
                }
                if (!empty($lastUpdated) && $lastUpdated !== '0000-00-00 00:00:00') {
                    return true;
                }
            }
        }

        // 2. Check resolution_reports table for uploaded expert reports
        $rStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM resolution_reports WHERE consultation_id = ?");
        if ($rStmt) {
            $rStmt->bind_param("i", $cid);
            $rStmt->execute();
            $rRow = $rStmt->get_result()->fetch_assoc();
            $rStmt->close();
            if ($rRow && (int)$rRow['cnt'] > 0) {
                return true;
            }
        }

        // 3. Check consultation_document_audit_trail for expert annotation actions
        $aStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM consultation_document_audit_trail WHERE consultation_id = ? AND action_type IN ('inline_annotation_added', 'report_uploaded', 'expert_review_completed', 'endorsed')");
        if ($aStmt) {
            $aStmt->bind_param("i", $cid);
            $aStmt->execute();
            $aRow = $aStmt->get_result()->fetch_assoc();
            $aStmt->close();
            if ($aRow && (int)$aRow['cnt'] > 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('sendOrtsEvent')) {
    /**
     * Sends an arbitrary event payload to the ORTS API endpoint using cURL.
     * Target Endpoint: https://ort.spvalenzuela.com/api/v1/events.php
     *
     * @param array $payload Key-value pairs representing the event payload.
     * @return array Response metadata including success status, HTTP code, raw & decoded response, and payload sent.
     */
    function sendOrtsEvent(array $payload = []): array {
        $url = "https://ort.spvalenzuela.com/api/v1/events.php";
        $token = "pcms_live_5a9c3e7f1b6048d2e6a8c4f9b1d70328";

        if (!isset($payload['source_system'])) {
            $payload['source_system'] = 'PCMS';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json",
                "X-Source-System: PCMS"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            error_log("ORTS API cURL Error: " . $curlErr);
            return [
                'success' => false,
                'message' => "cURL dispatch error: {$curlErr}",
                'http_code' => $httpCode,
                'payload_sent' => $payload,
                'endpoint' => $url
            ];
        }

        $decoded = json_decode($response, true);
        $isSuccess = ($httpCode >= 200 && $httpCode < 300);

        return [
            'success' => $isSuccess,
            'http_code' => $httpCode,
            'response_raw' => $response,
            'response' => $decoded,
            'payload_sent' => $payload,
            'endpoint' => $url
        ];
    }
}

if (!function_exists('sendFeedbackToOrts')) {
    /**
     * Pushes citizen feedback to ORTS API event endpoint.
     * Target Endpoint: POST https://ort.spvalenzuela.com/api/v1/events.php
     *
     * @param int $documentId ORTS Document ID
     * @param string $ref Reference Number (e.g. ORD-2025-001)
     * @param string $notes Citizen feedback text
     * @param string $name Submitter name
     * @param string $type Feedback type: support | oppose | suggestion | general
     * @return array Response from ORTS API
     */
    function sendFeedbackToOrts(int $documentId, string $ref, string $notes, string $name = '', string $type = 'general'): array {
        $payload = [
            'event' => 'public_feedback_received',
            'document_id' => $documentId,
            'reference_number' => $ref,
            'notes' => $notes,
            'submitter_name' => $name,
            'feedback_type' => $type,
            'source_system' => 'PCMS'
        ];
        return sendOrtsEvent($payload);
    }
}

if (!function_exists('fetchOrtsDocuments')) {
    /**
     * Fetches documents from the ORTS API endpoint.
     * Target Endpoint: GET https://ort.spvalenzuela.com/api/v1/documents.php
     *
     * @param array $filters Query parameters (e.g., ['status' => 'Committee Stage', 'limit' => 20, 'id' => 42, 'ref' => 'ORD-2025-001'])
     * @return array Decoded response containing documents list or document details.
     */
    function fetchOrtsDocuments(array $filters = []): array {
        $baseUrl = "https://ort.spvalenzuela.com/api/v1/documents.php";
        $token = "pcms_live_5a9c3e7f1b6048d2e6a8c4f9b1d70328";

        if (!empty($filters)) {
            $queryString = http_build_query($filters);
            $baseUrl .= '?' . $queryString;
        }

        $ch = curl_init($baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "X-Source-System: PCMS",
                "Content-Type: application/json"
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            error_log("ORTS API fetch error: " . $curlErr);
            return [
                'success' => false,
                'message' => "cURL fetch error: {$curlErr}",
                'http_code' => $httpCode,
                'endpoint' => $baseUrl
            ];
        }

        $decoded = json_decode($response, true);
        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'data' => $decoded['data'] ?? $decoded,
            'raw' => $response,
            'endpoint' => $baseUrl
        ];
    }
}

if (!function_exists('sendToOrtsApi')) {
    function sendToOrtsApi($consultationId, $conn) {
        if (!$conn) {
            return ['success' => false, 'message' => 'Database connection required'];
        }

        $consultationId = (int)$consultationId;
        if ($consultationId <= 0) {
            return ['success' => false, 'message' => 'Invalid consultation ID'];
        }

        // 1. Fetch consultation details
        $stmt = $conn->prepare("SELECT id, title, description, category, tracking_number, external_ref, ai_committee_brief, status, committee_assigned FROM consultations WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'DB prepare error'];
        }
        $stmt->bind_param("i", $consultationId);
        $stmt->execute();
        $consultation = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$consultation) {
            return ['success' => false, 'message' => 'Consultation not found'];
        }

        // 2. Fetch submission counts from feedback table
        $submissionCount = 0;
        $cntStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM feedback WHERE consultation_id = ?");
        if ($cntStmt) {
            $cntStmt->bind_param("i", $consultationId);
            $cntStmt->execute();
            $cntRes = $cntStmt->get_result()->fetch_assoc();
            $submissionCount = (int)($cntRes['cnt'] ?? 0);
            $cntStmt->close();
        }

        // 3. Fetch primary document ID from documents table if registered
        $docId = $consultationId;
        $docStmt = $conn->prepare("SELECT id FROM documents WHERE consultation_id = ? ORDER BY id DESC LIMIT 1");
        if ($docStmt) {
            $docStmt->bind_param("i", $consultationId);
            $docStmt->execute();
            $dRow = $docStmt->get_result()->fetch_assoc();
            if ($dRow && !empty($dRow['id'])) {
                $docId = (int)$dRow['id'];
            }
            $docStmt->close();
        }

        // 4. Extract conclusion / summary notes from AI brief
        $brief = json_decode($consultation['ai_committee_brief'] ?? '{}', true);
        $conclusion = $brief['conclusion'] ?? $consultation['description'] ?? 'Public consultation completed.';
        $notesStr = "Public consultation completed. Recommendations: " . $conclusion;
        
        $refNo = !empty($consultation['tracking_number']) ? $consultation['tracking_number'] : (!empty($consultation['external_ref']) ? $consultation['external_ref'] : ("CONSULT-" . sprintf("%06d", $consultationId)));

        // 5. Construct payload matching ORTS specification
        $payload = [
            "event" => "consultation_forwarded",
            "document_id" => $docId,
            "reference_number" => $refNo,
            "tracking_number" => $refNo,
            "title" => $consultation['title'] ?? 'Public Consultation File',
            "description" => $consultation['description'] ?? 'Public consultation details and feedback synthesis',
            "category" => $consultation['category'] ?? 'General Policy',
            "committee" => $consultation['committee_assigned'] ?? 'Rules & Governance Committee',
            "location" => "Public Consultation Office",
            "notes" => $notesStr,
            "submission_counts" => max($submissionCount, 1),
            "source_system" => "PCMS",
            "ai_brief" => $brief
        ];

        // 6. Dispatch via sendOrtsEvent helper
        $res = sendOrtsEvent($payload);

        // Update consultation status to forwarded_orts & forwarded_to_committee if successful
        if (!empty($res['success'])) {
            $upStmt = $conn->prepare("UPDATE consultations SET status = 'forwarded_orts', document_status = 'forwarded_to_committee', committee_forwarded_at = NOW() WHERE id = ?");
            if ($upStmt) {
                $upStmt->bind_param("i", $consultationId);
                $upStmt->execute();
                $upStmt->close();
            }
        }

        return $res;
    }
}


if (!function_exists('syncOrtsConsultationsToPcms')) {
    /**
     * Synchronizes active ORTS ordinances into PCMS public consultations table
     * so citizens can browse them and submit feedback on the PCMS portal.
     */
    function syncOrtsConsultationsToPcms($conn): array {
        if (!$conn || !($conn instanceof mysqli)) {
            return ['success' => false, 'message' => 'Database connection required'];
        }

        $res = fetchOrtsDocuments(['limit' => 50]);
        if (empty($res['success'])) {
            return ['success' => false, 'message' => 'Failed to fetch documents from ORTS API', 'details' => $res];
        }

        $documents = [];
        if (!empty($res['data']['documents']) && is_array($res['data']['documents'])) {
            $documents = $res['data']['documents'];
        } elseif (!empty($res['data']) && is_array($res['data'])) {
            $documents = $res['data'];
        }

        if (empty($documents)) {
            return ['success' => true, 'message' => 'No active documents returned from ORTS', 'imported' => 0];
        }

        $imported = 0;
        $updated = 0;

        foreach ($documents as $doc) {
            $ref = trim((string)($doc['reference_number'] ?? $doc['tracking_number'] ?? $doc['reference'] ?? ''));
            $title = trim((string)($doc['title'] ?? ''));
            if ($ref === '' || $title === '') continue;

            $desc = trim((string)($doc['description'] ?? $doc['summary'] ?? 'Ordinance file submitted for public consultation and legislative tracking.'));
            $category = trim((string)($doc['category'] ?? $doc['committee'] ?? 'Ordinance Consultation'));

            // Check existing consultation by tracking_number or external_ref
            $chkStmt = $conn->prepare("SELECT id FROM consultations WHERE tracking_number = ? OR external_ref = ? LIMIT 1");
            if ($chkStmt) {
                $chkStmt->bind_param('ss', $ref, $ref);
                $chkStmt->execute();
                $cRow = $chkStmt->get_result()->fetch_assoc();
                $chkStmt->close();

                if ($cRow) {
                    $uStmt = $conn->prepare("UPDATE consultations SET title = ?, description = ?, category = ?, external_ref = ?, synced_at = NOW() WHERE id = ?");
                    if ($uStmt) {
                        $cid = (int)$cRow['id'];
                        $uStmt->bind_param('ssssi', $title, $desc, $category, $ref, $cid);
                        $uStmt->execute();
                        $uStmt->close();
                        $updated++;
                    }
                } else {
                    $iStmt = $conn->prepare("INSERT INTO consultations (title, description, category, status, type, tracking_number, external_ref, source_system, created_at, synced_at) VALUES (?, ?, ?, 'active', 'ordinance', ?, ?, 'ORTS', NOW(), NOW())");
                    if ($iStmt) {
                        $iStmt->bind_param('sssss', $title, $desc, $category, $ref, $ref);
                        $iStmt->execute();
                        $iStmt->close();
                        $imported++;
                    }
                }
            }
        }

        return [
            'success' => true,
            'message' => "ORTS Consultation sync completed. Imported: {$imported}, Updated: {$updated}.",
            'imported' => $imported,
            'updated' => $updated,
            'total_processed' => count($documents)
        ];
    }
}
