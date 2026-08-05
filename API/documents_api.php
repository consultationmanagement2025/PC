<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../DATABASE/documents.php';
require_once '../DATABASE/document-management.php';

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_admin = ($current_role === 'admin' || $current_role === 'administrator');
$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');
$is_staff = in_array($current_role, ['staff', 'barangay staff', 'barangay_staff', 'barangay'], true);
if (!$is_admin && !$is_super_admin && !$is_staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin, Super Admin or Staff access required']);
    exit;
}

$action = $_GET['action'] ?? 'list';

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function normalizeSource($value): string {
    $s = strtolower(trim((string)$value));
    return $s === 'consultation' ? 'consultation' : 'admin';
}

function buildDocumentDownloadUrl($id, $source): string {
    $id = (int)$id;
    $source = normalizeSource($source);
    return 'download-document.php?id=' . rawurlencode((string)$id) . '&source=' . rawurlencode($source);
}

function filePathToPublicUrl($path): ?string {
    $path = trim((string)$path);
    if ($path === '') return null;
    return $path;
}

try {
    switch ($action) {
        case 'list':
            $limit = (int)($_GET['limit'] ?? 200);
            $offset = (int)($_GET['offset'] ?? 0);
            if ($limit <= 0) $limit = 200;
            if ($limit > 500) $limit = 500;
            if ($offset < 0) $offset = 0;

            $admin_docs = array_map(function ($row) {
                $row['source'] = 'admin';
                $row['download_url'] = buildDocumentDownloadUrl((int)($row['id'] ?? 0), 'admin');
                $row['uploaded_by'] = $row['uploaded_by'] ?? null;
                $row['uploaded_by_name'] = $row['uploaded_by'] ?? 'Admin';
                return $row;
            }, getDocuments($limit, $offset));

            $consultation_docs = array_map(function ($row) {
                $row['source'] = 'consultation';
                $row['download_url'] = buildDocumentDownloadUrl((int)($row['id'] ?? 0), 'consultation');
                $row['uploaded_by'] = $row['uploaded_by'] ?? null;
                $row['uploaded_by_name'] = $row['uploaded_by'] ?? 'Citizen';
                return $row;
            }, getConsultationDocumentsForAdminList($limit, $offset));

            $all_docs = array_merge($admin_docs, $consultation_docs);
            $all_docs = array_map(function ($row) {
                $row['source'] = normalizeSource($row['source'] ?? 'admin');
                $uploaderRole = strtolower(trim((string)($row['uploader_role'] ?? '')));
                if ($row['source'] === 'consultation' && in_array($uploaderRole, ['admin', 'administrator', 'super admin', 'superadmin', 'staff', 'barangay staff', 'barangay_staff', 'barangay'], true)) {
                    $row['source'] = 'admin';
                }
                return $row;
            }, $all_docs);
            usort($all_docs, function ($a, $b) {
                $date_a = strtotime($a['created_at'] ?? $a['upload_date'] ?? '0');
                $date_b = strtotime($b['created_at'] ?? $b['upload_date'] ?? '0');
                return $date_b - $date_a;
            });

            echo json_encode(['success' => true, 'data' => $all_docs]);
            break;

        case 'upload':

            if (!isset($_FILES['document_file'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                exit;
            }

            $file = $_FILES['document_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'File upload error: ' . $file['error']]);
                exit;
            }

            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'File size must be less than 10MB']);
                exit;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowedTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
                'image/jpeg',
                'image/png'
            ];
            if (!in_array($mime, $allowedTypes, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Allowed file types: PDF, DOC, DOCX, TXT, JPG, PNG']);
                exit;
            }

            $uploadDir = __DIR__ . '/../ASSETS/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
            $filepath = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save file']);
                exit;
            }

            // Attempt to stamp Valenzuela logo onto PDFs and images when possible
            $logoPath = __DIR__ . '/../images/valenzuela-logo.png';
            if (!is_file($logoPath)) $logoPath = __DIR__ . '/../images/logo.webp';
            if (is_file($logoPath)) {
                try {
                    if (class_exists('Imagick')) {
                        $imagick = new Imagick();
                        if (strpos($mime, 'pdf') !== false) {
                            // Read all pages and stamp logo on first page
                            $imagick->setResolution(150, 150);
                            $imagick->readImage($filepath);
                            $logo = new Imagick($logoPath);
                            $logo->thumbnailImage(140, 0);
                            foreach ($imagick as $i => $page) {
                                if ($i === 0) {
                                    $page->compositeImage($logo, Imagick::COMPOSITE_OVER, 20, 20);
                                }
                                $page->setImageFormat('pdf');
                            }
                            $imagick->writeImages($filepath, true);
                            $logo->clear(); $logo->destroy();
                            $imagick->clear(); $imagick->destroy();
                        } elseif (strpos($mime, 'image/') === 0) {
                            $imagick->readImage($filepath);
                            $logo = new Imagick($logoPath);
                            $logo->thumbnailImage(140, 0);
                            $imagick->compositeImage($logo, Imagick::COMPOSITE_OVER, 20, 20);
                            $imagick->writeImage($filepath);
                            $logo->clear(); $logo->destroy();
                            $imagick->clear(); $imagick->destroy();
                        }
                    }
                } catch (Throwable $e) {
                    // Non-fatal: if stamping fails, continue without logo
                    error_log('Logo stamping failed: ' . $e->getMessage());
                }
            }

            $id = createDocument(
                trim((string)($_POST['reference'] ?? '')),
                trim((string)($_POST['title'] ?? '')),
                trim((string)($_POST['type'] ?? 'report')),
                trim((string)($_POST['status'] ?? 'draft')),
                trim((string)($_POST['date'] ?? '')) ?: null,
                trim((string)($_POST['description'] ?? '')),
                trim((string)($_POST['tags'] ?? '')),
                $_SESSION['fullname'] ?? 'Admin',
                'ASSETS/documents/' . $filename,
                (string)$file['size']
            );

            if (!$id) {
                @unlink($filepath);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save document to database']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Document uploaded successfully', 'id' => $id]);
            break;

        case 'update':
            $data = jsonInput();
            $id = (int)($data['id'] ?? 0);
            $source = normalizeSource($data['source'] ?? 'admin');
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid document id']);
                exit;
            }

            if ($source === 'admin') {
                $ok = updateDocument(
                    $id,
                    trim((string)($data['reference'] ?? '')),
                    trim((string)($data['title'] ?? '')),
                    trim((string)($data['type'] ?? 'report')),
                    trim((string)($data['status'] ?? 'draft')),
                    trim((string)($data['date'] ?? '')) ?: null,
                    trim((string)($data['description'] ?? '')),
                    trim((string)($data['tags'] ?? ''))
                );
                echo json_encode(['success' => (bool)$ok]);
                break;
            }

            $status = trim((string)($data['status'] ?? 'submitted'));
            $ok = updateDocumentStatus($id, $status);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'delete':
            $data = jsonInput();
            $id = (int)($data['id'] ?? 0);
            $source = normalizeSource($data['source'] ?? 'admin');
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid document id']);
                exit;
            }

            if ($source === 'admin') {
                $ok = deleteAdminDocumentById($id);
                echo json_encode(['success' => (bool)$ok]);
                break;
            }

            $ok = deleteDocument($id);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'update_status':
            $data = jsonInput();
            $id = (int)($data['id'] ?? 0);
            $source = normalizeSource($data['source'] ?? 'admin');
            $status = strtolower(trim((string)($data['status'] ?? '')));
            if ($id <= 0 || $status === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid id or status']);
                exit;
            }

            if ($source === 'admin') {
                $doc = getAdminDocumentById($id);
                if (!$doc) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Document not found']);
                    exit;
                }
                $ok = updateDocument(
                    $id,
                    (string)$doc['reference'],
                    (string)$doc['title'],
                    (string)$doc['type'],
                    $status,
                    (string)$doc['document_date'],
                    (string)$doc['description'],
                    (string)$doc['tags']
                );
                echo json_encode(['success' => (bool)$ok]);
                break;
            }

            $ok = updateDocumentStatus($id, $status);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'register_download':
            $data = jsonInput();
            $id = (int)($data['id'] ?? 0);
            $source = normalizeSource($data['source'] ?? 'admin');
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid id']);
                exit;
            }
            if ($source === 'admin') {
                $ok = incrementAdminDocumentDownloads($id);
                echo json_encode(['success' => (bool)$ok]);
                break;
            }

            $stmt = $conn->prepare("UPDATE documents SET downloads = downloads + 1 WHERE id = ?");
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'forward_lrs':
        case 'forward_to_lrs':
        case 'forward_to_lrm':
            if (empty($data)) {
                $data = $_POST;
            }
            $id = (int)($data['id'] ?? $data['document_id'] ?? 0);
            $source = normalizeSource($data['source'] ?? 'consultation');
            $description = trim((string)($data['description'] ?? ''));
            $performer = trim((string)($data['performed_by'] ?? ''));

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
                exit;
            }

            if (function_exists('forwardDocumentToLRS')) {
                $res = forwardDocumentToLRS($id, $source, $description, $performer);
                echo json_encode($res);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'LRS forward helper not loaded']);
            }
            break;

        case 'initiate_tracking':
            $docType = $data['document_type'] ?? ($_POST['document_type'] ?? 'consultation');
            $srcSys = $data['source_system'] ?? ($_POST['source_system'] ?? 'pcms');
            if (function_exists('initiateLRMTracking')) {
                $res = initiateLRMTracking($docType, $srcSys);
                echo json_encode($res);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'LRM initiate tracking function not found']);
            }
            break;

        case 'send_event':
            $trackingId = $data['tracking_id'] ?? ($_POST['tracking_id'] ?? '');
            $localDocId = $data['local_document_id'] ?? ($_POST['local_document_id'] ?? 'PCM-EXAMPLE-001');
            $activity = $data['activity'] ?? ($_POST['activity'] ?? 'Transferred');
            $status = $data['status'] ?? ($_POST['status'] ?? 'Transferred');
            $performedBy = $data['performed_by'] ?? ($_POST['performed_by'] ?? null);
            $dept = $data['department'] ?? ($_POST['department'] ?? 'Consultation Office');
            $remarks = $data['remarks'] ?? ($_POST['remarks'] ?? 'Transferred to ORTS');
            $meta = $data['metadata'] ?? ($_POST['metadata'] ?? ['destination' => 'orts']);

            if (empty($trackingId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'tracking_id parameter is required']);
                exit;
            }

            if (function_exists('sendLRMTrackingEvent')) {
                $res = sendLRMTrackingEvent($trackingId, $localDocId, $activity, $status, $performedBy, $dept, $remarks, $meta);
                echo json_encode($res);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'LRM send event function not found']);
            }
            break;

        case 'list_versions':
            initializeDocumentVersionsTable();
            $ref = $_GET['reference'] ?? null;
            $limit = (int)($_GET['limit'] ?? 200);
            $offset = (int)($_GET['offset'] ?? 0);
            $versions = getDocumentVersions($ref, $limit, $offset);
            $versions = array_map(function($v) {
                $v['download_url'] = 'download-document.php?version_id=' . (int)$v['id'];
                return $v;
            }, $versions);
            echo json_encode(['success' => true, 'data' => $versions]);
            break;

        case 'get_tracking_timeline':
            initializeDocumentVersionsTable();
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            $reference = trim((string)($_GET['reference'] ?? $_POST['reference'] ?? ''));
            $source = normalizeSource($_GET['source'] ?? $_POST['source'] ?? 'consultation');

            $doc = null;
            if ($id > 0) {
                if ($source === 'admin') {
                    $doc = getAdminDocumentById($id) ?: getDocumentById($id);
                } else {
                    $doc = getDocumentById($id) ?: getAdminDocumentById($id);
                }
            } elseif (!empty($reference)) {
                $doc = getDocumentByReference($reference);
            }

            $refNumber = $doc['reference_number'] ?? ($doc['reference'] ?? ($id > 0 ? ('CONSULT-' . sprintf('%06d', $id)) : ''));
            $versions = !empty($refNumber) ? getDocumentVersions($refNumber) : [];

            $events = [];

            // Stage 1: Document Upload Event
            if ($doc) {
                $uploadDate = $doc['upload_date'] ?? ($doc['document_date'] ?? date('Y-m-d H:i:s'));
                $events[] = [
                    'timestamp' => date('M j, Y g:i A', strtotime($uploadDate)),
                    'raw_date' => $uploadDate,
                    'title' => 'Document Registered & Uploaded',
                    'activity' => 'Upload',
                    'status' => 'submitted',
                    'performer' => $doc['uploaded_by_name'] ?? 'System User',
                    'department' => 'Public Consultation Office',
                    'notes' => 'Original document file registered into PCMS repository. Ref: ' . ($refNumber ?: 'N/A'),
                    'badge' => 'bg-blue-100 text-blue-800 border-blue-200'
                ];
            }

            // Version events & LRM dispatches
            $latestTrackingId = null;
            foreach ($versions as $v) {
                if (!empty($v['tracking_id'])) {
                    $latestTrackingId = $v['tracking_id'];
                }
                $vDate = $v['created_at'] ?? date('Y-m-d H:i:s');
                $isForward = strpos(strtolower($v['status'] ?? ''), 'forward') !== false || !empty($v['tracking_id']);

                $events[] = [
                    'timestamp' => date('M j, Y g:i A', strtotime($vDate)),
                    'raw_date' => $vDate,
                    'title' => $isForward ? 'External System Integration Dispatch (LRM)' : ('Status Update: ' . ucfirst($v['status'] ?? 'Updated')),
                    'activity' => $isForward ? 'LRM Dispatch' : 'Status Update',
                    'status' => $v['status'] ?? 'updated',
                    'performer' => 'Secretariat / Integration Engine',
                    'department' => 'Legislative Records Management (LRM)',
                    'tracking_id' => $v['tracking_id'] ?? null,
                    'notes' => $v['notes'] ?? 'Document version updated or dispatched to external integration server.',
                    'badge' => $isForward ? 'bg-indigo-100 text-indigo-800 border-indigo-200' : 'bg-emerald-100 text-emerald-800 border-emerald-200'
                ];
            }

            // Sort timeline by date descending
            usort($events, function($a, $b) {
                return strtotime($b['raw_date']) - strtotime($a['raw_date']);
            });

            // Calculate current pipeline stage (1 to 4)
            $currStatus = strtolower($doc['status'] ?? 'submitted');
            $stage = 1;
            if (in_array($currStatus, ['reviewed', 'under_review'])) {
                $stage = 2;
            } elseif (in_array($currStatus, ['forwarded_to_lrs', 'forwarded', 'active'])) {
                $stage = 3;
            } elseif (in_array($currStatus, ['approved', 'published', 'archived'])) {
                $stage = 4;
            }

            $lrmBaseUrl = defined('LRM_BASE_URL') ? LRM_BASE_URL : 'https://llrm.spvalenzuela.com';

            echo json_encode([
                'success' => true,
                'document' => $doc,
                'reference_number' => $refNumber,
                'latest_tracking_id' => $latestTrackingId ?: ($refNumber ?: ('TRK-' . date('Ymd') . '-' . rand(1000, 9999))),
                'pipeline_stage' => $stage,
                'integration' => [
                    'lrm_system' => 'LRM (Legislative Records Management)',
                    'lrm_base_url' => $lrmBaseUrl,
                    'lrm_events_endpoint' => defined('LRM_EVENTS_URL') ? LRM_EVENTS_URL : ($lrmBaseUrl . '/modules/document-tracking/api/document-events.php'),
                    'status' => 'Connected & Active',
                    'api_key' => defined('LRM_API_KEY') ? (substr(LRM_API_KEY, 0, 8) . '***') : 'Configured'
                ],
                'timeline' => $events
            ]);
            break;

        case 'log_event':
            initializeDocumentVersionsTable();
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $activity = trim((string)($_POST['activity'] ?? 'Status Update'));
            $status = trim((string)($_POST['status'] ?? 'under_review'));
            $notes = trim((string)($_POST['notes'] ?? 'Updated document tracking timeline'));
            $performer = trim((string)($_POST['performed_by'] ?? $_SESSION['full_name'] ?? 'Secretariat'));

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
                exit;
            }

            $doc = getDocumentById($id) ?: getAdminDocumentById($id);
            if (!$doc) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Document not found']);
                exit;
            }

            $refNumber = $doc['reference_number'] ?? ($doc['reference'] ?? ('CONSULT-' . sprintf('%06d', $id)));
            addDocumentVersion([
                'document_id' => $id,
                'reference_number' => $refNumber,
                'title' => $doc['original_filename'] ?? ($doc['title'] ?? 'Document'),
                'version_number' => '1.0',
                'document_type' => $doc['document_type'] ?? 'consultation',
                'original_filename' => $doc['original_filename'] ?? 'document.pdf',
                'stored_filename' => $doc['stored_filename'] ?? 'document.pdf',
                'file_path' => 'uploads/documents/' . ($doc['stored_filename'] ?? ''),
                'file_size' => $doc['file_size'] ?? 0,
                'status' => $status,
                'notes' => "[{$activity}] by {$performer}: {$notes}"
            ]);

            // Update doc status
            updateDocumentStatus($id, $status);

            echo json_encode(['success' => true, 'message' => 'Event logged successfully in live tracker timeline']);
            break;


        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
