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

                // If document status is explicitly verified/annotated/endorsed
                if (in_array($docStatus, ['expert_annotated', 'approved', 'endorsed', 'reviewed', 'checked'], true)) {
                    return true;
                }

                // If consultation status is endorsed
                if ($status === 'endorsed') {
                    return true;
                }

                // If valid expert notes exist and are non-empty
                if (!empty($expertNotes) && $expertNotes !== '{}' && $expertNotes !== '[]' && $expertNotes !== 'null') {
                    return true;
                }

                // If expert update timestamp is recorded
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

if (!function_exists('sendToOrtsApi')) {
    function sendToOrtsApi($consultationId, $conn) {
        if (!$conn) {
            return ['success' => false, 'message' => 'Database connection required'];
        }

        $consultationId = (int)$consultationId;
        if ($consultationId <= 0) {
            return ['success' => false, 'message' => 'Invalid consultation ID'];
        }

        // 0. Strict Gatekeeping: Ensure file is checked by a Resource Person first
        if (!isConsultationCheckedByExpert($consultationId, $conn)) {
            return [
                'success' => false,
                'message' => 'Action Blocked: This consultation must first be checked and reviewed by a Resource Person before being transmitted to ORTS.',
                'awaiting_expert' => true
            ];
        }

        // 1. Fetch consultation details
        $stmt = $conn->prepare("SELECT id, title, description, ai_committee_brief, status FROM consultations WHERE id = ? LIMIT 1");
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

        // 5. Construct payload exactly matching ORTS specification
        $payload = [
            "event" => "consultation_closed",
            "document_id" => $docId,
            "location" => "Public Consultation Closed",
            "notes" => $notesStr,
            "submission_counts" => max($submissionCount, 1)
        ];

        // 6. ORTS API Endpoint & Authentication Token
        $url = "https://ort.spvalenzuela.com/api/v1/events.php";
        $token = "pcms_live_5a9c3e7f1b6048d2e6a8c4f9b1d70328";

        // 7. Dispatch cURL HTTP POST Request
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json"
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
            error_log("ORTS API cURL Error for Consultation #{$consultationId}: " . $curlErr);
            return [
                'success' => false,
                'message' => "cURL dispatch error: {$curlErr}",
                'http_code' => $httpCode,
                'payload_sent' => $payload
            ];
        }

        $decoded = json_decode($response, true);
        $isSuccess = ($httpCode >= 200 && $httpCode < 300);

        return [
            'success' => $isSuccess,
            'http_code' => $httpCode,
            'response_raw' => $response,
            'response' => $decoded,
            'payload_sent' => $payload
        ];
    }
}
