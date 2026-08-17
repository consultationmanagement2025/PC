<?php
/**
 * ORTS (Ordinance Routing & Tracking System) API Integration Utility
 * Handles automated cURL dispatch of AI-summarized & validated consultations to ORTS.
 */

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
