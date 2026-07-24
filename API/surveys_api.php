<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? 'list';
$public_actions = ['public_list', 'public_details', 'submit_response'];
$is_public_action = in_array($action, $public_actions, true);

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if (!$is_public_action && $current_role !== 'admin' && $current_role !== 'administrator' && $current_role !== 'super admin' && $current_role !== 'superadmin' && !in_array($current_role, ['staff', 'barangay staff', 'barangay_staff', 'barangay'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$is_super_admin = ($current_role === 'super admin' || $current_role === 'superadmin');
$is_staff = in_array($current_role, ['staff', 'barangay staff', 'barangay_staff', 'barangay'], true);

function initializeSurveyTables(mysqli $conn): bool {
    $queries = [];

    $queries[] = "CREATE TABLE IF NOT EXISTS survey_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        consultation_id INT DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        status ENUM('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
        starts_at DATETIME DEFAULT NULL,
        ends_at DATETIME DEFAULT NULL,
        allow_anonymous TINYINT(1) NOT NULL DEFAULT 1,
        allow_multiple_per_email TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_consultation_id (consultation_id),
        INDEX idx_status (status),
        INDEX idx_created_by (created_by),
        CONSTRAINT fk_survey_templates_consultation FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE SET NULL,
        CONSTRAINT fk_survey_templates_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS survey_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        question_text TEXT NOT NULL,
        question_type ENUM('single_choice','multiple_choice','text','rating') NOT NULL DEFAULT 'single_choice',
        is_required TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_survey_id (survey_id),
        INDEX idx_sort_order (sort_order),
        CONSTRAINT fk_survey_questions_survey FOREIGN KEY (survey_id) REFERENCES survey_templates(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS survey_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        option_text VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        INDEX idx_question_id (question_id),
        INDEX idx_option_sort (sort_order),
        CONSTRAINT fk_survey_options_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        user_id INT DEFAULT NULL,
        citizen_name VARCHAR(255) DEFAULT NULL,
        citizen_email VARCHAR(255) DEFAULT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_survey_id (survey_id),
        INDEX idx_user_id (user_id),
        CONSTRAINT fk_survey_responses_survey FOREIGN KEY (survey_id) REFERENCES survey_templates(id) ON DELETE CASCADE,
        CONSTRAINT fk_survey_responses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $queries[] = "CREATE TABLE IF NOT EXISTS survey_response_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        response_id INT NOT NULL,
        question_id INT NOT NULL,
        selected_option_id INT DEFAULT NULL,
        answer_text TEXT DEFAULT NULL,
        INDEX idx_response_id (response_id),
        INDEX idx_question_id (question_id),
        INDEX idx_selected_option_id (selected_option_id),
        CONSTRAINT fk_survey_response_items_response FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE CASCADE,
        CONSTRAINT fk_survey_response_items_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
        CONSTRAINT fk_survey_response_items_option FOREIGN KEY (selected_option_id) REFERENCES survey_options(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($queries as $sql) {
        if (!$conn->query($sql)) {
            error_log('Survey table init failed: ' . $conn->error);
            return false;
        }
    }

    // Backward compatibility for existing installations where these columns do not exist yet.
    $columnChecks = [
        'starts_at' => "ALTER TABLE survey_templates ADD COLUMN starts_at DATETIME DEFAULT NULL AFTER status",
        'ends_at' => "ALTER TABLE survey_templates ADD COLUMN ends_at DATETIME DEFAULT NULL AFTER starts_at",
        'allow_anonymous' => "ALTER TABLE survey_templates ADD COLUMN allow_anonymous TINYINT(1) NOT NULL DEFAULT 1 AFTER ends_at",
        'allow_multiple_per_email' => "ALTER TABLE survey_templates ADD COLUMN allow_multiple_per_email TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_anonymous"
    ];
    foreach ($columnChecks as $col => $alterSql) {
        $chk = $conn->query("SHOW COLUMNS FROM survey_templates LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$chk) return false;
        $exists = $chk->num_rows > 0;
        $chk->free();
        if (!$exists && !$conn->query($alterSql)) {
            error_log('Survey column migration failed for ' . $col . ': ' . $conn->error);
            return false;
        }
    }

    return true;
}

if (!initializeSurveyTables($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to initialize survey tables']);
    exit;
}

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

try {
    switch ($action) {
        case 'public_list':
            $stmt = $conn->prepare("
                SELECT
                    s.id,
                    s.consultation_id,
                    s.title,
                    s.description,
                    s.status,
                    s.starts_at,
                    s.ends_at,
                    s.allow_anonymous,
                    s.allow_multiple_per_email,
                    s.created_at,
                    c.title AS consultation_title,
                    COALESCE(q.question_count, 0) AS question_count,
                    COALESCE(r.response_count, 0) AS response_count
                FROM survey_templates s
                LEFT JOIN consultations c ON c.id = s.consultation_id
                LEFT JOIN (SELECT survey_id, COUNT(*) AS question_count FROM survey_questions GROUP BY survey_id) q ON q.survey_id = s.id
                LEFT JOIN (SELECT survey_id, COUNT(*) AS response_count FROM survey_responses GROUP BY survey_id) r ON r.survey_id = s.id
                WHERE s.status = 'active'
                  AND (s.starts_at IS NULL OR s.starts_at <= NOW())
                  AND (s.ends_at IS NULL OR s.ends_at >= NOW())
                ORDER BY s.created_at DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'public_details':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid survey ID']);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT s.*, c.title AS consultation_title
                FROM survey_templates s
                LEFT JOIN consultations c ON c.id = s.consultation_id
                WHERE s.id = ?
                  AND s.status = 'active'
                  AND (s.starts_at IS NULL OR s.starts_at <= NOW())
                  AND (s.ends_at IS NULL OR s.ends_at >= NOW())
                LIMIT 1
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $surveyResult = $stmt->get_result();
            $survey = $surveyResult->fetch_assoc();
            $stmt->close();
            if (!$survey) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Survey not available']);
                exit;
            }

            $questions = [];
            $qStmt = $conn->prepare("
                SELECT id, question_text, question_type, is_required, sort_order
                FROM survey_questions
                WHERE survey_id = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $qStmt->bind_param('i', $id);
            $qStmt->execute();
            $qRes = $qStmt->get_result();
            while ($q = $qRes->fetch_assoc()) {
                $q['options'] = [];
                $questions[(int)$q['id']] = $q;
            }
            $qStmt->close();

            if (!empty($questions)) {
                $questionIds = array_keys($questions);
                $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
                $types = str_repeat('i', count($questionIds));
                $oSql = "SELECT id, question_id, option_text, sort_order FROM survey_options WHERE question_id IN ($placeholders) ORDER BY sort_order ASC, id ASC";
                $oStmt = $conn->prepare($oSql);
                $oStmt->bind_param($types, ...$questionIds);
                $oStmt->execute();
                $oRes = $oStmt->get_result();
                while ($o = $oRes->fetch_assoc()) {
                    $qid = (int)$o['question_id'];
                    if (isset($questions[$qid])) {
                        $questions[$qid]['options'][] = $o;
                    }
                }
                $oStmt->close();
            }

            echo json_encode(['success' => true, 'data' => [
                'survey' => $survey,
                'questions' => array_values($questions)
            ]]);
            break;

        case 'list':
            $limit = (int)($_GET['limit'] ?? 200);
            $offset = (int)($_GET['offset'] ?? 0);
            if ($limit < 1) $limit = 200;
            if ($limit > 1000) $limit = 1000;
            if ($offset < 0) $offset = 0;

            $stmt = $conn->prepare("
                SELECT
                    s.id,
                    s.consultation_id,
                    s.title,
                    s.description,
                    s.status,
                    s.created_by,
                    s.created_at,
                    s.updated_at,
                    c.title AS consultation_title,
                    COALESCE(q.question_count, 0) AS question_count,
                    COALESCE(r.response_count, 0) AS response_count
                FROM survey_templates s
                LEFT JOIN consultations c ON c.id = s.consultation_id
                LEFT JOIN (
                    SELECT survey_id, COUNT(*) AS question_count
                    FROM survey_questions
                    GROUP BY survey_id
                ) q ON q.survey_id = s.id
                LEFT JOIN (
                    SELECT survey_id, COUNT(*) AS response_count
                    FROM survey_responses
                    GROUP BY survey_id
                ) r ON r.survey_id = s.id
                ORDER BY s.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('ii', $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        case 'create':

            $data = jsonInput();
            $title = trim((string)($data['title'] ?? ''));
            $description = trim((string)($data['description'] ?? ''));
            $status = strtolower(trim((string)($data['status'] ?? 'draft')));
            if ($status === 'published') $status = 'active';
            $consultation_id = isset($data['consultation_id']) && (int)$data['consultation_id'] > 0 ? (int)$data['consultation_id'] : null;
            $startsAtRaw = trim((string)($data['starts_at'] ?? ''));
            $endsAtRaw = trim((string)($data['ends_at'] ?? ''));
            $startsAt = $startsAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($startsAtRaw)) : null;
            $endsAt = $endsAtRaw !== '' ? date('Y-m-d H:i:s', strtotime($endsAtRaw)) : null;
            $allowAnonymous = !empty($data['allow_anonymous']) ? 1 : 0;
            $allowMultiplePerEmail = !empty($data['allow_multiple_per_email']) ? 1 : 0;
            $questions = isset($data['questions']) && is_array($data['questions']) ? $data['questions'] : [];

            if ($title === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Survey title is required']);
                exit;
            }
            if (count($questions) === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'At least one question is required']);
                exit;
            }
            $validStatuses = ['draft', 'active', 'closed', 'archived'];
            if (!in_array($status, $validStatuses, true)) $status = 'draft';

            if ($consultation_id !== null) {
                $chkConsult = $conn->prepare("SELECT id FROM consultations WHERE id = ? LIMIT 1");
                if (!$chkConsult) {
                    throw new RuntimeException('Failed to validate consultation ID');
                }
                $chkConsult->bind_param('i', $consultation_id);
                if (!$chkConsult->execute()) {
                    $msg = $chkConsult->error ?: 'Failed to validate consultation ID';
                    $chkConsult->close();
                    throw new RuntimeException($msg);
                }
                $consultRes = $chkConsult->get_result();
                $existsConsultation = (bool)$consultRes->fetch_assoc();
                $chkConsult->close();
                if (!$existsConsultation) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
                    exit;
                }
            }

            $conn->begin_transaction();
            try {
                $creatorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                if ($creatorId !== null && $creatorId > 0) {
                    $chkUser = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                    if ($chkUser) {
                        $chkUser->bind_param('i', $creatorId);
                        if ($chkUser->execute()) {
                            $userRes = $chkUser->get_result();
                            if (!$userRes->fetch_assoc()) {
                                $creatorId = null;
                            }
                        } else {
                            $creatorId = null;
                        }
                        $chkUser->close();
                    } else {
                        $creatorId = null;
                    }
                } else {
                    $creatorId = null;
                }

                $stmt = $conn->prepare("
                    INSERT INTO survey_templates (consultation_id, title, description, status, starts_at, ends_at, allow_anonymous, allow_multiple_per_email, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare survey insert');
                }
                $stmt->bind_param(
                    'isssssiii',
                    $consultation_id,
                    $title,
                    $description,
                    $status,
                    $startsAt,
                    $endsAt,
                    $allowAnonymous,
                    $allowMultiplePerEmail,
                    $creatorId
                );
                if (!$stmt->execute()) {
                    $msg = $stmt->error ?: 'Failed to create survey';
                    $stmt->close();
                    throw new RuntimeException($msg);
                }
                $surveyId = (int)$conn->insert_id;
                $stmt->close();
                if ($surveyId <= 0) {
                    throw new RuntimeException('Survey insert did not return a valid ID');
                }

                $qStmt = $conn->prepare("
                    INSERT INTO survey_questions (survey_id, question_text, question_type, is_required, sort_order)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $oStmt = $conn->prepare("
                    INSERT INTO survey_options (question_id, option_text, sort_order)
                    VALUES (?, ?, ?)
                ");
                if (!$qStmt || !$oStmt) {
                    if ($qStmt) $qStmt->close();
                    if ($oStmt) $oStmt->close();
                    throw new RuntimeException('Failed to prepare question/option inserts');
                }

                $sortOrder = 1;
                $insertedQuestions = 0;
                foreach ($questions as $q) {
                    $qText = trim((string)($q['question_text'] ?? ''));
                    $qType = strtolower(trim((string)($q['question_type'] ?? 'single_choice')));
                    $isRequired = !empty($q['is_required']) ? 1 : 0;

                    if ($qText === '') continue;
                    if (!in_array($qType, ['single_choice', 'multiple_choice', 'text', 'rating'], true)) {
                        $qType = 'single_choice';
                    }

                    $qStmt->bind_param('issii', $surveyId, $qText, $qType, $isRequired, $sortOrder);
                    if (!$qStmt->execute()) {
                        $msg = $qStmt->error ?: 'Failed to insert survey question';
                        throw new RuntimeException($msg);
                    }
                    $questionId = (int)$conn->insert_id;
                    if ($questionId <= 0) {
                        throw new RuntimeException('Invalid question insert ID');
                    }
                    $insertedQuestions++;

                    $options = isset($q['options']) && is_array($q['options']) ? $q['options'] : [];
                    if ($qType === 'rating' && count($options) === 0) {
                        $options = ['1', '2', '3', '4', '5'];
                    }

                    if (in_array($qType, ['single_choice', 'multiple_choice', 'rating'], true)) {
                        $optSort = 1;
                        foreach ($options as $opt) {
                            $optText = trim((string)$opt);
                            if ($optText === '') continue;
                            $oStmt->bind_param('isi', $questionId, $optText, $optSort);
                            if (!$oStmt->execute()) {
                                $msg = $oStmt->error ?: 'Failed to insert survey option';
                                throw new RuntimeException($msg);
                            }
                            $optSort++;
                        }
                    }

                    $sortOrder++;
                }

                $qStmt->close();
                $oStmt->close();
                if ($insertedQuestions <= 0) {
                    throw new RuntimeException('No valid survey questions were saved');
                }

                $conn->commit();
                echo json_encode(['success' => true, 'id' => $surveyId]);
            } catch (Throwable $t) {
                $conn->rollback();
                throw $t;
            }
            break;

        case 'delete':

            $data = jsonInput();
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid survey ID']);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM survey_templates WHERE id = ?");
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'update_status':

            $data = jsonInput();
            $id = (int)($data['id'] ?? 0);
            $status = strtolower(trim((string)($data['status'] ?? '')));
            if ($status === 'published') $status = 'active';
            if ($id <= 0 || !in_array($status, ['draft', 'active', 'closed', 'archived'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid id or status']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE survey_templates SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $id);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'details':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid survey ID']);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT s.*, c.title AS consultation_title
                FROM survey_templates s
                LEFT JOIN consultations c ON c.id = s.consultation_id
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $surveyResult = $stmt->get_result();
            $survey = $surveyResult->fetch_assoc();
            $stmt->close();
            if (!$survey) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Survey not found']);
                exit;
            }

            $questions = [];
            $qStmt = $conn->prepare("
                SELECT id, question_text, question_type, is_required, sort_order
                FROM survey_questions
                WHERE survey_id = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $qStmt->bind_param('i', $id);
            $qStmt->execute();
            $qRes = $qStmt->get_result();
            while ($q = $qRes->fetch_assoc()) {
                $q['options'] = [];
                $questions[(int)$q['id']] = $q;
            }
            $qStmt->close();

            if (!empty($questions)) {
                $questionIds = array_keys($questions);
                $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
                $types = str_repeat('i', count($questionIds));
                $oSql = "SELECT id, question_id, option_text, sort_order FROM survey_options WHERE question_id IN ($placeholders) ORDER BY sort_order ASC, id ASC";
                $oStmt = $conn->prepare($oSql);
                $oStmt->bind_param($types, ...$questionIds);
                $oStmt->execute();
                $oRes = $oStmt->get_result();
                while ($o = $oRes->fetch_assoc()) {
                    $qid = (int)$o['question_id'];
                    if (isset($questions[$qid])) {
                        $questions[$qid]['options'][] = $o;
                    }
                }
                $oStmt->close();
            }

            echo json_encode(['success' => true, 'data' => [
                'survey' => $survey,
                'questions' => array_values($questions)
            ]]);
            break;

        case 'results':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid survey ID']);
                exit;
            }

            $surveyStmt = $conn->prepare("SELECT id, title, status FROM survey_templates WHERE id = ? LIMIT 1");
            $surveyStmt->bind_param('i', $id);
            $surveyStmt->execute();
            $surveyRes = $surveyStmt->get_result();
            $survey = $surveyRes->fetch_assoc();
            $surveyStmt->close();
            if (!$survey) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Survey not found']);
                exit;
            }

            $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM survey_responses WHERE survey_id = ?");
            $countStmt->bind_param('i', $id);
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $totalResponses = (int)($countRes->fetch_assoc()['total'] ?? 0);
            $countStmt->close();

            $questions = [];
            $qStmt = $conn->prepare("SELECT id, question_text, question_type FROM survey_questions WHERE survey_id = ? ORDER BY sort_order ASC, id ASC");
            $qStmt->bind_param('i', $id);
            $qStmt->execute();
            $qRes = $qStmt->get_result();
            while ($q = $qRes->fetch_assoc()) {
                $qId = (int)$q['id'];
                $type = (string)$q['question_type'];
                $entry = [
                    'id' => $qId,
                    'question_text' => $q['question_text'],
                    'question_type' => $type,
                    'option_counts' => [],
                    'text_answers' => []
                ];

                if (in_array($type, ['single_choice', 'multiple_choice', 'rating'], true)) {
                    $optStmt = $conn->prepare("
                        SELECT o.id, o.option_text, COUNT(ri.id) AS cnt
                        FROM survey_options o
                        LEFT JOIN survey_response_items ri ON ri.selected_option_id = o.id
                        LEFT JOIN survey_responses sr ON sr.id = ri.response_id AND sr.survey_id = ?
                        WHERE o.question_id = ?
                        GROUP BY o.id, o.option_text
                        ORDER BY o.sort_order ASC, o.id ASC
                    ");
                    $optStmt->bind_param('ii', $id, $qId);
                    $optStmt->execute();
                    $optRes = $optStmt->get_result();
                    while ($opt = $optRes->fetch_assoc()) {
                        $entry['option_counts'][] = [
                            'option_id' => (int)$opt['id'],
                            'option_text' => $opt['option_text'],
                            'count' => (int)$opt['cnt']
                        ];
                    }
                    $optStmt->close();
                } else {
                    $txtStmt = $conn->prepare("
                        SELECT ri.answer_text
                        FROM survey_response_items ri
                        JOIN survey_responses sr ON sr.id = ri.response_id
                        WHERE sr.survey_id = ? AND ri.question_id = ? AND ri.answer_text IS NOT NULL AND ri.answer_text <> ''
                        ORDER BY ri.id DESC
                        LIMIT 20
                    ");
                    $txtStmt->bind_param('ii', $id, $qId);
                    $txtStmt->execute();
                    $txtRes = $txtStmt->get_result();
                    while ($row = $txtRes->fetch_assoc()) {
                        $entry['text_answers'][] = $row['answer_text'];
                    }
                    $txtStmt->close();
                }

                $questions[] = $entry;
            }
            $qStmt->close();

            echo json_encode(['success' => true, 'data' => [
                'survey' => $survey,
                'total_responses' => $totalResponses,
                'questions' => $questions
            ]]);
            break;

        case 'submit_response':
            $data = jsonInput();
            $surveyId = (int)($data['survey_id'] ?? 0);
            $answers = isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : [];
            $citizenName = trim((string)($data['citizen_name'] ?? ''));
            $citizenEmail = trim((string)($data['citizen_email'] ?? ''));
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

            if ($surveyId <= 0 || count($answers) === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Survey ID and answers are required']);
                exit;
            }

            $statusStmt = $conn->prepare("SELECT status FROM survey_templates WHERE id = ? LIMIT 1");
            $statusStmt->bind_param('i', $surveyId);
            $statusStmt->execute();
            $statusRes = $statusStmt->get_result();
            $surveyRow = $statusRes->fetch_assoc();
            $statusStmt->close();
            if (!$surveyRow || $surveyRow['status'] !== 'active') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Survey is not active']);
                exit;
            }

            $metaStmt = $conn->prepare("SELECT starts_at, ends_at, allow_anonymous, allow_multiple_per_email FROM survey_templates WHERE id = ? LIMIT 1");
            $metaStmt->bind_param('i', $surveyId);
            $metaStmt->execute();
            $metaRes = $metaStmt->get_result();
            $meta = $metaRes->fetch_assoc() ?: [];
            $metaStmt->close();
            $startsAt = $meta['starts_at'] ?? null;
            $endsAt = $meta['ends_at'] ?? null;
            $allowAnonymous = (int)($meta['allow_anonymous'] ?? 1) === 1;
            $allowMultiplePerEmail = (int)($meta['allow_multiple_per_email'] ?? 0) === 1;
            $now = date('Y-m-d H:i:s');
            if ($startsAt && $now < $startsAt) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Survey has not started yet']);
                exit;
            }
            if ($endsAt && $now > $endsAt) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Survey is already closed by end date']);
                exit;
            }
            if (!$allowAnonymous && $citizenEmail === '' && !$userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Anonymous responses are not allowed']);
                exit;
            }
            if (!$allowMultiplePerEmail && $citizenEmail !== '') {
                $dupeStmt = $conn->prepare("SELECT id FROM survey_responses WHERE survey_id = ? AND citizen_email = ? LIMIT 1");
                $dupeStmt->bind_param('is', $surveyId, $citizenEmail);
                $dupeStmt->execute();
                $dupeRes = $dupeStmt->get_result();
                $hasDupe = (bool)$dupeRes->fetch_assoc();
                $dupeStmt->close();
                if ($hasDupe) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Multiple submissions per email are not allowed']);
                    exit;
                }
            }

            $conn->begin_transaction();
            try {
                $resStmt = $conn->prepare("INSERT INTO survey_responses (survey_id, user_id, citizen_name, citizen_email) VALUES (?, ?, ?, ?)");
                $resStmt->bind_param('iiss', $surveyId, $userId, $citizenName, $citizenEmail);
                $resStmt->execute();
                $responseId = (int)$conn->insert_id;
                $resStmt->close();

                $itemStmt = $conn->prepare("
                    INSERT INTO survey_response_items (response_id, question_id, selected_option_id, answer_text)
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($answers as $a) {
                    $questionId = (int)($a['question_id'] ?? 0);
                    $selectedOptionId = isset($a['selected_option_id']) && (int)$a['selected_option_id'] > 0 ? (int)$a['selected_option_id'] : null;
                    $answerText = trim((string)($a['answer_text'] ?? ''));
                    if ($questionId <= 0) continue;

                    $itemStmt->bind_param('iiis', $responseId, $questionId, $selectedOptionId, $answerText);
                    $itemStmt->execute();
                }
                $itemStmt->close();

                $conn->commit();
                echo json_encode(['success' => true, 'response_id' => $responseId]);
            } catch (Throwable $t) {
                $conn->rollback();
                throw $t;
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    error_log('surveys_api error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
