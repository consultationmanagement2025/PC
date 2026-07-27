<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../UTILS/consultation_feedback_utils.php';
require_once '../DATABASE/audit-log.php';

function normalizeRole($role) {
    return strtolower(trim((string)$role));
}

function isAdminRole($role) {
    $r = normalizeRole($role);
    return in_array($r, ['admin', 'administrator', 'super admin', 'superadmin'], true);
}

function ensureAiColumns($conn) {
    $result = $conn->query("SHOW COLUMNS FROM posts");
    if (!$result) {
        return;
    }

    $existing = [];
    while ($row = $result->fetch_assoc()) {
        $existing[] = $row['Field'];
    }

    $required = [
        'ai_sentiment_tag' => "VARCHAR(20) DEFAULT NULL",
        'ai_sentiment_score' => "DECIMAL(6,2) DEFAULT NULL",
        'ai_urgency' => "VARCHAR(20) DEFAULT NULL",
        'ai_topics' => "TEXT DEFAULT NULL",
        'ai_last_analyzed' => "DATETIME DEFAULT NULL"
    ];

    foreach ($required as $col => $def) {
        if (!in_array($col, $existing, true)) {
            $conn->query("ALTER TABLE posts ADD COLUMN $col $def");
        }
    }
}

function analyzeText($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return [
            'sentiment' => 'neutral',
            'score' => 0,
            'urgency' => 'low',
            'topics' => []
        ];
    }

    $positive = [
        'good' => 2, 'great' => 3, 'excellent' => 3, 'satisfied' => 2, 'helpful' => 2,
        'thank' => 1, 'thanks' => 1, 'support' => 2, 'safe' => 2, 'improved' => 2,
        'maayos' => 2, 'maganda' => 2, 'salamat' => 1
    ];
    $negative = [
        'bad' => -2, 'worst' => -3, 'slow' => -2, 'problem' => -2, 'issue' => -2,
        'unsafe' => -2, 'dirty' => -2, 'corrupt' => -3, 'failed' => -2, 'delayed' => -2,
        'mabagal' => -2, 'marumi' => -2, 'pangit' => -3, 'hindi' => -1
    ];
    $urgentWords = ['urgent', 'emergency', 'asap', 'immediately', 'danger', 'critical', 'agaran', 'tulong', 'agad'];
    $topicsMap = [
        'infrastructure' => ['road', 'roads', 'drainage', 'flood', 'pothole', 'traffic', 'kalsada', 'baha'],
        'health' => ['health', 'hospital', 'clinic', 'medicine', 'doctor', 'kalusugan', 'ospital'],
        'education' => ['school', 'student', 'teacher', 'education', 'paaralan', 'guro'],
        'safety' => ['safety', 'police', 'crime', 'security', 'kaligtasan', 'pulis'],
        'environment' => ['garbage', 'waste', 'pollution', 'environment', 'basura', 'kalikasan'],
        'governance' => ['service', 'permit', 'office', 'queue', 'process', 'serbisyo', 'pila']
    ];

    $lc = mb_strtolower($text, 'UTF-8');
    $clean = preg_replace("/[^a-z0-9\s']/u", ' ', $lc);
    $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

    $score = 0.0;
    foreach ($words as $w) {
        if (isset($positive[$w])) {
            $score += $positive[$w];
        }
        if (isset($negative[$w])) {
            $score += $negative[$w];
        }
    }

    $topics = [];
    foreach ($topicsMap as $topic => $keys) {
        $hits = 0;
        foreach ($keys as $k) {
            if (strpos($lc, $k) !== false) {
                $hits++;
            }
        }
        if ($hits > 0) {
            $topics[$topic] = $hits;
        }
    }
    arsort($topics);

    $urgencyScore = 0;
    foreach ($urgentWords as $u) {
        if (strpos($lc, $u) !== false) {
            $urgencyScore += 2;
        }
    }
    if ($score <= -4) {
        $urgencyScore += 2;
    }
    if (preg_match('/[!]{2,}/', $text)) {
        $urgencyScore += 1;
    }

    if ($score > 1) {
        $sentiment = 'positive';
    } elseif ($score < -1) {
        $sentiment = 'negative';
    } else {
        $sentiment = 'neutral';
    }

    if ($urgencyScore >= 4) {
        $urgency = 'critical';
    } elseif ($urgencyScore >= 2) {
        $urgency = 'high';
    } elseif ($sentiment === 'negative') {
        $urgency = 'medium';
    } else {
        $urgency = 'low';
    }

    return [
        'sentiment' => $sentiment,
        'score' => round($score, 2),
        'urgency' => $urgency,
        'topics' => array_slice(array_keys($topics), 0, 3)
    ];
}

function buildConsultationSummary($title, $rows) {
    $total = count($rows);
    $sent = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
    $urg = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
    $topics = [];

    foreach ($rows as $row) {
        $a = analyzeText($row['content'] ?? '');
        if (isset($sent[$a['sentiment']])) {
            $sent[$a['sentiment']]++;
        }
        if (isset($urg[$a['urgency']])) {
            $urg[$a['urgency']]++;
        }
        foreach ($a['topics'] as $topic) {
            $topics[$topic] = ($topics[$topic] ?? 0) + 1;
        }
    }

    arsort($topics);
    $topTopics = array_slice(array_keys($topics), 0, 3);
    $dominantSentiment = 'neutral';
    if ($sent['negative'] > $sent['positive'] && $sent['negative'] >= $sent['neutral']) {
        $dominantSentiment = 'negative';
    } elseif ($sent['positive'] > $sent['negative'] && $sent['positive'] >= $sent['neutral']) {
        $dominantSentiment = 'positive';
    }

    $highRiskCount = (int)$urg['high'] + (int)$urg['critical'];
    $topicText = empty($topTopics) ? 'general public service concerns' : implode(', ', array_map('ucfirst', $topTopics));

    $summary = "Based on {$total} approved feedback entries for \"{$title}\", citizens mostly discuss {$topicText}. ";
    if ($dominantSentiment === 'negative') {
        $summary .= "Overall tone is concern-heavy, and {$highRiskCount} item(s) are marked high/critical urgency.";
    } elseif ($dominantSentiment === 'positive') {
        $summary .= "Overall tone is mostly positive, with citizens recognizing improvements.";
    } else {
        $summary .= "Overall tone is mixed/neutral, with both support and concerns present.";
    }

    $draft = "Thank you for your feedback on \"{$title}\". We have reviewed the concerns, especially around {$topicText}. ";
    if ($highRiskCount > 0) {
        $draft .= "Priority items have been forwarded for immediate action, and we will provide status updates as resolutions are implemented. ";
    } else {
        $draft .= "Your suggestions are being consolidated into the implementation plan for the next review cycle. ";
    }
    $draft .= "We appreciate your participation in helping improve city services.";

    return [
        'total_feedback' => $total,
        'sentiment_distribution' => $sent,
        'urgency_distribution' => $urg,
        'top_topics' => $topTopics,
        'dominant_sentiment' => $dominantSentiment,
        'summary' => $summary,
        'suggested_response' => $draft
    ];
}

$action = $_GET['action'] ?? 'consultation_summary';

try {
    switch ($action) {
        case 'consultation_summary':
            $consultationId = (int)($_GET['consultation_id'] ?? 0);
            $persist = (int)($_GET['persist'] ?? 0) === 1;
            if ($consultationId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'consultation_id is required']);
                exit;
            }

            $title = 'Consultation';
            $titleStmt = $conn->prepare("SELECT title FROM consultations WHERE id = ? LIMIT 1");
            if ($titleStmt) {
                $titleStmt->bind_param('i', $consultationId);
                $titleStmt->execute();
                $tRes = $titleStmt->get_result();
                $tRow = $tRes ? $tRes->fetch_assoc() : null;
                if (!empty($tRow['title'])) {
                    $title = $tRow['title'];
                }
                $titleStmt->close();
            }

            $stmt = $conn->prepare("SELECT id, content, category, created_at FROM posts WHERE consultation_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 500");
            if (!$stmt) {
                throw new Exception('Failed to prepare feedback query');
            }
            $stmt->bind_param('i', $consultationId);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
            $stmt->close();

            $summary = buildConsultationSummary($title, $rows);

            if ($persist && isAdminRole($_SESSION['role'] ?? '')) {
                ensureAiColumns($conn);
                $updateStmt = $conn->prepare("UPDATE posts SET ai_sentiment_tag = ?, ai_sentiment_score = ?, ai_urgency = ?, ai_topics = ?, ai_last_analyzed = NOW() WHERE id = ?");
                if ($updateStmt) {
                    foreach ($rows as $r) {
                        $a = analyzeText($r['content'] ?? '');
                        $topicsJson = json_encode($a['topics']);
                        $updateStmt->bind_param('sdssi', $a['sentiment'], $a['score'], $a['urgency'], $topicsJson, $r['id']);
                        $updateStmt->execute();
                    }
                    $updateStmt->close();
                }

                $summaryId = persistConsultationSummary(
                    $consultationId,
                    $summary,
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['fullname'] ?? null,
                    $_SESSION['role'] ?? null
                );
                if ($summaryId && function_exists('logAction')) {
                    logAction(
                        $_SESSION['user_id'] ?? null,
                        $_SESSION['fullname'] ?? 'Admin',
                        'generate_consultation_summary',
                        'consultation',
                        $consultationId,
                        null,
                        null,
                        'success',
                        'Generated and archived consultation feedback summary id=' . $summaryId
                    );
                }
            }

            echo json_encode(['success' => true, 'data' => $summary]);
            break;

        case 'draft_for_feedback':
            if (!isAdminRole($_SESSION['role'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $message = trim((string)($data['message'] ?? ''));
            if ($message === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'message is required']);
                exit;
            }

            $a = analyzeText($message);
            $topics = empty($a['topics']) ? 'your concern' : implode(', ', $a['topics']);
            $draft = "Thank you for raising this feedback. We have noted your points regarding {$topics}. ";
            if ($a['urgency'] === 'critical' || $a['urgency'] === 'high') {
                $draft .= "This has been escalated for priority review and action. ";
            } else {
                $draft .= "This has been endorsed to the responsible unit for assessment and next steps. ";
            }
            $draft .= "We appreciate your participation in this consultation.";

            echo json_encode([
                'success' => true,
                'data' => [
                    'analysis' => $a,
                    'draft_response' => $draft
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

