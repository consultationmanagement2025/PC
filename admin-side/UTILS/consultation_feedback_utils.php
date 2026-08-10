<?php
require_once __DIR__ . '/../db.php';

function ensureFeedbackSummaryTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS consultation_feedback_summaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        consultation_id INT NOT NULL,
        summary_text LONGTEXT NOT NULL,
        metadata JSON DEFAULT NULL,
        generated_by INT DEFAULT NULL,
        generated_by_name VARCHAR(255) DEFAULT NULL,
        generated_by_role VARCHAR(100) DEFAULT NULL,
        version INT DEFAULT 1,
        generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        archived_at DATETIME DEFAULT NULL,
        INDEX idx_consultation_id (consultation_id),
        INDEX idx_generated_at (generated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        error_log('Failed to create consultation_feedback_summaries table: ' . $conn->error);
        return false;
    }
    return true;
}

function ensureFeedbackArchiveTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS consultation_feedback_archive (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feedback_id INT NOT NULL,
        consultation_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        user_name VARCHAR(255) DEFAULT NULL,
        user_email VARCHAR(255) DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        content LONGTEXT,
        sentiment_tag VARCHAR(20) DEFAULT NULL,
        sentiment_score DECIMAL(6,2) DEFAULT NULL,
        topic_tags JSON DEFAULT NULL,
        analysis_summary LONGTEXT,
        source_status VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT NULL,
        archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_feedback_id (feedback_id),
        INDEX idx_consultation_id (consultation_id),
        INDEX idx_archived_at (archived_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        error_log('Failed to create consultation_feedback_archive table: ' . $conn->error);
        return false;
    }
    return true;
}

if (!function_exists('analyzeFeedbackText')) {
function analyzeFeedbackText($text) {
    $text = trim((string)$text);
    $result = [
        'sentiment' => 'neutral',
        'score' => 0.0,
        'urgency' => 'low',
        'topics' => [],
        'summary' => null,
    ];
    if ($text === '') {
        return $result;
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
    $clean = preg_replace('/[^a-z0-9\s]/u', ' ', $lc);
    $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

    $score = 0;
    foreach ($words as $w) {
        if (isset($positive[$w])) {
            $score += $positive[$w];
        }
        if (isset($negative[$w])) {
            $score += $negative[$w];
        }
    }

    $topicCounts = [];
    foreach ($topicsMap as $topic => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($lc, $keyword) !== false) {
                $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
            }
        }
    }
    arsort($topicCounts);
    $topics = array_slice(array_keys($topicCounts), 0, 3);

    $urgencyScore = 0;
    foreach ($urgentWords as $term) {
        if (strpos($lc, $term) !== false) {
            $urgencyScore += 2;
        }
    }
    if (preg_match('/[!]{2,}/', $text)) {
        $urgencyScore += 1;
    }
    if ($score <= -4) {
        $urgencyScore += 1;
    }

    if ($score > 1) {
        $result['sentiment'] = 'positive';
    } elseif ($score < -1) {
        $result['sentiment'] = 'negative';
    }
    $result['score'] = round($score, 2);

    if ($urgencyScore >= 4) {
        $result['urgency'] = 'critical';
    } elseif ($urgencyScore >= 2) {
        $result['urgency'] = 'high';
    } elseif ($result['sentiment'] === 'negative') {
        $result['urgency'] = 'medium';
    }

    $result['topics'] = $topics;
    $summaryParts = [];
    if (!empty($topics)) {
        $summaryParts[] = 'Topics: ' . implode(', ', $topics);
    }
    $summaryParts[] = 'Tone: ' . ucfirst($result['sentiment']);
    $summaryParts[] = 'Urgency: ' . ucfirst($result['urgency']);
    $result['summary'] = implode('; ', $summaryParts);

    return $result;
}
}

if (!function_exists('buildConsultationSummary')) {
function buildConsultationSummary($title, $rows) {
    $total = count($rows);
    $sentiment = ['positive' => 0, 'neutral' => 0, 'negative' => 0];
    $urgency = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
    $topics = [];

    foreach ($rows as $row) {
        $analysis = analyzeFeedbackText($row['content'] ?? $row['message'] ?? '');
        $sentiment[$analysis['sentiment']]++;
        $urgency[$analysis['urgency']]++;
        foreach ($analysis['topics'] as $topic) {
            $topics[$topic] = ($topics[$topic] ?? 0) + 1;
        }
    }

    arsort($topics);
    $topTopics = array_slice(array_keys($topics), 0, 5);

    $dominantSentiment = 'neutral';
    if ($sentiment['negative'] > $sentiment['positive'] && $sentiment['negative'] >= $sentiment['neutral']) {
        $dominantSentiment = 'negative';
    } elseif ($sentiment['positive'] > $sentiment['negative'] && $sentiment['positive'] >= $sentiment['neutral']) {
        $dominantSentiment = 'positive';
    }

    $summary = "Based on {$total} feedback entries for '{$title}', community sentiment is {$dominantSentiment}. ";
    if (!empty($topTopics)) {
        $summary .= 'Major themes include ' . implode(', ', $topTopics) . '. ';
    }
    if ($urgency['critical'] + $urgency['high'] > 0) {
        $summary .= 'Several submissions require priority attention, with ' . ($urgency['critical'] + $urgency['high']) . ' marked as high or critical urgency.';
    } else {
        $summary .= 'Feedback is generally stable with no immediate critical escalation flagged.';
    }

    return [
        'total_feedback' => $total,
        'sentiment_distribution' => $sentiment,
        'urgency_distribution' => $urgency,
        'top_topics' => $topTopics,
        'dominant_sentiment' => $dominantSentiment,
        'summary' => $summary,
        'generated_at' => date('Y-m-d H:i:s'),
    ];
}
}

function persistConsultationSummary($consultation_id, $summaryData, $generated_by = null, $generated_by_name = null, $generated_by_role = null) {
    global $conn;
    ensureFeedbackSummaryTable();
    $metadata = json_encode([
        'total_feedback' => $summaryData['total_feedback'] ?? 0,
        'sentiment_distribution' => $summaryData['sentiment_distribution'] ?? [],
        'urgency_distribution' => $summaryData['urgency_distribution'] ?? [],
        'top_topics' => $summaryData['top_topics'] ?? [],
        'dominant_sentiment' => $summaryData['dominant_sentiment'] ?? 'neutral'
    ]);

    $stmt = $conn->prepare("INSERT INTO consultation_feedback_summaries (consultation_id, summary_text, metadata, generated_by, generated_by_name, generated_by_role, version)
            VALUES (?, ?, ?, ?, ?, ?, 1)");
    if (!$stmt) {
        error_log('Error preparing persistConsultationSummary: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('ississ', $consultation_id, $summaryData['summary'], $metadata, $generated_by, $generated_by_name, $generated_by_role);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log('Error saving consultation feedback summary: ' . $stmt->error);
    }
    $id = $conn->insert_id;
    $stmt->close();
    return $ok ? $id : false;
}

function archiveConsultationFeedback($postId, $sourceStatus = null) {
    global $conn;
    ensureFeedbackArchiveTable();

    $stmt = $conn->prepare("SELECT id, consultation_id, user_id, author, content, category, status, created_at, ai_sentiment_tag, ai_sentiment_score, ai_topics, ai_urgency FROM posts WHERE id = ? LIMIT 1");
    if (!$stmt) {
        error_log('Error preparing archiveConsultationFeedback select: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('i', $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return false;
    }

    $topicTags = json_encode([]);
    if (!empty($row['ai_topics'])) {
        $topicTags = json_encode(json_decode($row['ai_topics'], true));
    }

    $insert = $conn->prepare("INSERT INTO consultation_feedback_archive (feedback_id, consultation_id, user_id, user_name, user_email, category, content, sentiment_tag, sentiment_score, topic_tags, analysis_summary, source_status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$insert) {
        error_log('Error preparing archiveConsultationFeedback insert: ' . $conn->error);
        return false;
    }
    $userEmail = null;
    $insert->bind_param('iiisssssdssss', $row['id'], $row['consultation_id'], $row['user_id'], $row['author'], $userEmail, $row['category'], $row['content'], $row['ai_sentiment_tag'], $row['ai_sentiment_score'], $topicTags, $row['ai_urgency'], $sourceStatus, $row['created_at']);
    $ok = $insert->execute();
    if (!$ok) {
        error_log('Error archiving consultation feedback: ' . $insert->error);
    }
    $insert->close();
    return $ok;
}
