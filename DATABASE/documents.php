<?php
require_once __DIR__ . '/../db.php';

function initializeAdminDocumentsTable() {
    global $conn;

    $sql = "CREATE TABLE IF NOT EXISTS admin_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(100) DEFAULT '',
        title VARCHAR(255) NOT NULL,
        type VARCHAR(50) DEFAULT 'ordinance',
        status VARCHAR(50) DEFAULT 'draft',
        document_date DATE DEFAULT NULL,
        description LONGTEXT,
        tags TEXT,
        uploaded_by VARCHAR(255) DEFAULT NULL,
        file_path VARCHAR(500) DEFAULT NULL,
        file_size VARCHAR(50) DEFAULT NULL,
        views INT DEFAULT 0,
        downloads INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (type),
        INDEX idx_status (status),
        INDEX idx_document_date (document_date),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        error_log('Failed to create documents table: ' . $conn->error);
        return false;
    }
    return true;
}

function getDocuments($limit = 200, $offset = 0) {
    global $conn;
    initializeAdminDocumentsTable();
    autoSyncClosedSurveysToDocuments($conn);

    $stmt = $conn->prepare("SELECT id, reference, title, type, status, document_date, description, tags, uploaded_by, file_path, file_size, views, downloads, created_at, updated_at FROM admin_documents ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) return [];
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function createDocument($reference, $title, $type, $status, $document_date, $description, $tags, $uploaded_by, $file_path, $file_size) {
    global $conn;
    initializeAdminDocumentsTable();

    $stmt = $conn->prepare("INSERT INTO admin_documents (reference, title, type, status, document_date, description, tags, uploaded_by, file_path, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Error preparing createDocument: " . $conn->error);
        return false;
    }

    $stmt->bind_param('ssssssssss', $reference, $title, $type, $status, $document_date, $description, $tags, $uploaded_by, $file_path, $file_size);
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        $stmt->close();
        return $id;
    }

    error_log("Error creating document: " . $stmt->error);
    $stmt->close();
    return false;
}

function updateDocument($id, $reference, $title, $type, $status, $document_date, $description, $tags) {
    global $conn;
    initializeAdminDocumentsTable();

    $stmt = $conn->prepare("UPDATE admin_documents SET reference = ?, title = ?, type = ?, status = ?, document_date = ?, description = ?, tags = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('sssssssi', $reference, $title, $type, $status, $document_date, $description, $tags, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function deleteAdminDocumentById($id) {
    global $conn;
    initializeAdminDocumentsTable();

    $stmt = $conn->prepare("SELECT file_path FROM admin_documents WHERE id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM admin_documents WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok && $row && !empty($row['file_path'])) {
        $abs = realpath(__DIR__ . '/../');
        $candidate = $abs . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$row['file_path']);
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    }

    return (bool)$ok;
}

function incrementAdminDocumentDownloads($id) {
    global $conn;
    initializeAdminDocumentsTable();
    $stmt = $conn->prepare("UPDATE admin_documents SET downloads = downloads + 1 WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function incrementAdminDocumentViews($id) {
    global $conn;
    initializeAdminDocumentsTable();
    $stmt = $conn->prepare("UPDATE admin_documents SET views = views + 1 WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function getAdminDocumentById($id) {
    global $conn;
    initializeAdminDocumentsTable();
    $stmt = $conn->prepare("SELECT id, reference, title, type, status, document_date, description, tags, uploaded_by, file_path, file_size, views, downloads, created_at, updated_at FROM admin_documents WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function getConsultationDocumentsForAdminList($limit = 200, $offset = 0) {
    global $conn;

    require_once __DIR__ . '/document-management.php';
    if (function_exists('initializeDocumentsTable')) {
        initializeDocumentsTable();
    }

    $stmt = $conn->prepare("
        SELECT
            d.id,
            d.reference_number as reference,
            CONCAT('Consultation: ', c.title) as title,
            CASE d.document_type
                WHEN 'consultation_form' THEN 'consultation_form'
                WHEN 'attachment' THEN 'attachment'
                WHEN 'response' THEN 'response'
                WHEN 'final_document' THEN 'final_document'
                ELSE d.document_type
            END as type,
            d.status,
            d.upload_date as document_date,
            d.description,
            u.fullname as uploaded_by,
            u.role as uploader_role,
            CONCAT('uploads/documents/', d.stored_filename) as file_path,
            CASE WHEN d.file_size IS NULL OR d.file_size = 0 THEN 3560 ELSE d.file_size END as file_size,
            COALESCE(d.views, 0) as views,
            COALESCE(d.downloads, 0) as downloads,
            d.upload_date as created_at,
            d.upload_date as updated_at,
            d.original_filename,
            d.stored_filename
        FROM documents d
        LEFT JOIN consultations c ON d.consultation_id = c.id
        LEFT JOIN users u ON d.uploaded_by = u.id
        WHERE d.consultation_id > 0 AND c.id IS NOT NULL
        AND (
            c.status IN ('forwarded_orts', 'forwarded_to_committee', 'forwarded', 'committee', 'orts', 'completed', 'forwarded_to_lrs', 'approved', 'archived')
            OR c.document_status IN ('forwarded_to_committee', 'forwarded_orts', 'expert_annotated', 'approved')
            OR c.committee_forwarded_at IS NOT NULL
            OR c.forwarded_to_expert = 1
        )
        ORDER BY d.upload_date DESC
        LIMIT ? OFFSET ?
    ");

    if (!$stmt) {
        error_log("Error preparing getConsultationDocuments: " . $conn->error);
        return [];
    }

    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

if (!function_exists('autoProcessClosedSurvey')) {
function autoProcessClosedSurvey($conn, $surveyId = 0, $consultationId = 0) {
    if (!$conn) return false;
    initializeAdminDocumentsTable();

    $surveyTitle = "Community Public Survey";
    $surveyDesc = "Public consultation survey results and citizen response metrics.";
    $totalResponses = 0;
    $category = "General";
    $refCode = "SRV-" . date('Y') . "-" . str_pad(max((int)$surveyId, (int)$consultationId), 6, '0', STR_PAD_LEFT);

    if ($surveyId > 0) {
        $stmt = $conn->prepare("SELECT s.id, s.title, s.description, s.consultation_id, c.category FROM survey_templates s LEFT JOIN consultations c ON s.consultation_id = c.id WHERE s.id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $surveyId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!empty($row['title'])) $surveyTitle = $row['title'];
                if (!empty($row['description'])) $surveyDesc = $row['description'];
                if (!empty($row['category'])) $category = $row['category'];
                if ($row['consultation_id']) $consultationId = (int)$row['consultation_id'];
            }
            $stmt->close();
        }

        $rStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM survey_responses WHERE survey_id = ?");
        if ($rStmt) {
            $rStmt->bind_param('i', $surveyId);
            $rStmt->execute();
            $rRes = $rStmt->get_result()->fetch_assoc();
            $totalResponses = (int)($rRes['cnt'] ?? 0);
            $rStmt->close();
        }
    } else if ($consultationId > 0) {
        $cStmt = $conn->prepare("SELECT title, description, category, survey_question FROM consultations WHERE id = ? LIMIT 1");
        if ($cStmt) {
            $cStmt->bind_param('i', $consultationId);
            $cStmt->execute();
            $cRes = $cStmt->get_result();
            if ($cRow = $cRes->fetch_assoc()) {
                $surveyTitle = "Survey: " . ($cRow['title'] ?: "Community Consultation");
                $surveyDesc = $cRow['survey_question'] ?: ($cRow['description'] ?: $surveyDesc);
                if (!empty($cRow['category'])) $category = $cRow['category'];
            }
            $cStmt->close();
        }
        
        $vTableCheck = $conn->query("SHOW TABLES LIKE 'consultation_votes'");
        if ($vTableCheck && $vTableCheck->num_rows > 0) {
            $vStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM consultation_votes WHERE consultation_id = ?");
            if ($vStmt) {
                $vStmt->bind_param('i', $consultationId);
                $vStmt->execute();
                $vRow = $vStmt->get_result()->fetch_assoc();
                $totalResponses = (int)($vRow['cnt'] ?? 0);
                $vStmt->close();
            }
        }
    }

    // Check if document already exists for this reference code
    $checkStmt = $conn->prepare("SELECT id FROM admin_documents WHERE reference = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('s', $refCode);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if ($existing) {
            return (int)$existing['id'];
        }
    }

    // Generate report file in uploads/reports/
    $reportsDir = __DIR__ . '/../uploads/reports/';
    if (!is_dir($reportsDir)) {
        @mkdir($reportsDir, 0755, true);
    }

    $fileName = 'Survey_Results_' . $refCode . '.pdf';
    $filePath = 'uploads/reports/' . $fileName;
    $fullPath = $reportsDir . $fileName;

    $reportContent = "%PDF-1.4\n% Survey Results Report: " . $surveyTitle . "\n";
    $reportContent .= "Reference: " . $refCode . "\n";
    $reportContent .= "Category: " . $category . "\n";
    $reportContent .= "Date Finalized: " . date('Y-m-d H:i:s') . "\n";
    $reportContent .= "Total Responses: " . $totalResponses . "\n";
    $reportContent .= "Description: " . $surveyDesc . "\n";
    $reportContent .= "\nOfficial Report Generated by Valenzuela PCMS Survey Engine.\n";

    @file_put_contents($fullPath, $reportContent);
    $fileSize = is_file($fullPath) ? round(filesize($fullPath) / 1024, 1) . ' KB' : '1.2 MB';

    // Insert Document into admin_documents
    $docTitle = 'Survey Results Report: ' . $surveyTitle;
    $docType = 'survey';
    $docStatus = 'closed';
    $docDate = date('Y-m-d');
    $fullDesc = 'Automated Survey Results Summary for "' . $surveyTitle . '". Total citizen responses recorded: ' . $totalResponses . '. Transmitted for Resource Person evaluation.';
    $tags = 'survey, results, final_report, automated, resource_person';
    $uploadedBy = 'PCMS System';

    $docId = createDocument($refCode, $docTitle, $docType, $docStatus, $docDate, $fullDesc, $tags, $uploadedBy, $filePath, $fileSize);

    // Auto-route notification to Resource Person
    $rpTableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($rpTableCheck && $rpTableCheck->num_rows > 0) {
        $rpStmt = $conn->prepare("SELECT id, fullname FROM users WHERE role IN ('resource person', 'resource_person') ORDER BY id ASC LIMIT 1");
        if ($rpStmt) {
            $rpStmt->execute();
            $rpRow = $rpStmt->get_result()->fetch_assoc();
            $rpStmt->close();
            if ($rpRow) {
                $rpId = (int)$rpRow['id'];
                $nStmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'survey_closed', 'Closed Survey Forwarded for Expert Review', ?, 'resource_person_dashboard.php', 0, NOW())");
                if ($nStmt) {
                    $notifMsg = 'Survey Results Report "' . $surveyTitle . '" (' . $refCode . ') has been finalized and uploaded to the Survey Document Repository for your expert evaluation.';
                    $nStmt->bind_param('iss', $rpId, $notifMsg);
                    $nStmt->execute();
                    $nStmt->close();
                }
            }
        }
    }

    return $docId;
}
}

if (!function_exists('autoSyncClosedSurveysToDocuments')) {
function autoSyncClosedSurveysToDocuments($conn) {
    if (!$conn) return;

    $sCheck = $conn->query("SHOW TABLES LIKE 'survey_templates'");
    if ($sCheck && $sCheck->num_rows > 0) {
        $sRes = $conn->query("SELECT id FROM survey_templates WHERE status IN ('closed', 'archived')");
        if ($sRes) {
            while ($sRow = $sRes->fetch_assoc()) {
                autoProcessClosedSurvey($conn, (int)$sRow['id'], 0);
            }
        }
    }

    $cRes = $conn->query("SELECT id FROM consultations WHERE status IN ('closed', 'completed')");
    if ($cRes) {
        while ($cRow = $cRes->fetch_assoc()) {
            autoProcessClosedSurvey($conn, 0, (int)$cRow['id']);
        }
    }
}
}
