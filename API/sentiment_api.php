<?php
/**
 * PCMS Sentiment Analysis API
 * Analyzes feedback text and returns sentiment classification.
 * Uses keyword-based scoring with weighted terms.
 * 
 * Actions:
 *   analyze  — Analyze a single text
 *   batch    — Analyze multiple feedback items by IDs
 */
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../db.php';

// Admin-only
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$is_staff = in_array($current_role, ['staff', 'barangay staff', 'barangay_staff', 'barangay'], true);
if ($current_role !== 'admin' && $current_role !== 'administrator' && $current_role !== 'super admin' && $current_role !== 'superadmin' && !$is_staff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'analyze';

// ── Sentiment lexicon (word → score, -3 to +3) ──
$positive_words = [
    // Strong positive (+3)
    'excellent' => 3, 'outstanding' => 3, 'amazing' => 3, 'fantastic' => 3, 'wonderful' => 3,
    'exceptional' => 3, 'superb' => 3, 'brilliant' => 3, 'perfect' => 3, 'love' => 3,
    'best' => 3, 'great' => 3, 'awesome' => 3,
    // Moderate positive (+2)
    'good' => 2, 'nice' => 2, 'helpful' => 2, 'satisfied' => 2, 'pleased' => 2,
    'happy' => 2, 'thankful' => 2, 'grateful' => 2, 'appreciate' => 2, 'impressive' => 2,
    'efficient' => 2, 'effective' => 2, 'responsive' => 2, 'professional' => 2,
    'improved' => 2, 'improvement' => 2, 'convenient' => 2, 'accessible' => 2,
    'commend' => 2, 'recommend' => 2, 'support' => 2, 'clean' => 2, 'safe' => 2,
    'organized' => 2, 'transparent' => 2, 'fair' => 2,
    // Mild positive (+1)
    'okay' => 1, 'fine' => 1, 'decent' => 1, 'acceptable' => 1, 'adequate' => 1,
    'reasonable' => 1, 'hope' => 1, 'hopeful' => 1, 'agree' => 1, 'useful' => 1,
    'better' => 1, 'working' => 1, 'functional' => 1, 'progress' => 1,
    'thank' => 1, 'thanks' => 1, 'salamat' => 1, 'maganda' => 1, 'mabuti' => 1,
    'maayos' => 1, 'ok' => 1,
    // Filipino positive
    'magaling' => 3, 'napakaganda' => 3, 'napakahusay' => 3, 'masaya' => 2,
    'maligaya' => 2, 'mabait' => 2, 'malinis' => 2, 'maayos' => 2,
];

$negative_words = [
    // Strong negative (-3)
    'terrible' => -3, 'horrible' => -3, 'awful' => -3, 'worst' => -3, 'disgusting' => -3,
    'unacceptable' => -3, 'outrageous' => -3, 'corrupt' => -3, 'corruption' => -3,
    'scam' => -3, 'fraud' => -3, 'abuse' => -3, 'abusive' => -3, 'hate' => -3,
    'useless' => -3, 'incompetent' => -3, 'negligent' => -3, 'dangerous' => -3,
    // Moderate negative (-2)
    'bad' => -2, 'poor' => -2, 'slow' => -2, 'delayed' => -2, 'broken' => -2,
    'disappointed' => -2, 'disappointing' => -2, 'frustrating' => -2, 'frustrated' => -2,
    'complaint' => -2, 'complain' => -2, 'problem' => -2, 'issue' => -2,
    'unfair' => -2, 'unjust' => -2, 'ignored' => -2, 'neglected' => -2,
    'dirty' => -2, 'unsafe' => -2, 'damaged' => -2, 'failed' => -2, 'failure' => -2,
    'rude' => -2, 'disrespectful' => -2, 'unprofessional' => -2,
    'worse' => -2, 'waste' => -2, 'wasted' => -2,
    // Mild negative (-1)
    'concern' => -1, 'worried' => -1, 'unclear' => -1, 'confusing' => -1,
    'difficult' => -1, 'inconvenient' => -1, 'lacking' => -1, 'missing' => -1,
    'needs improvement' => -1, 'could be better' => -1, 'not enough' => -1,
    'waiting' => -1, 'long' => -1, 'crowded' => -1,
    // Filipino negative
    'pangit' => -3, 'basura' => -3, 'walang kwenta' => -3, 'nakakagalit' => -3,
    'masama' => -2, 'mabagal' => -2, 'marumi' => -2, 'sira' => -2,
    'hindi maganda' => -2, 'hindi maayos' => -2,
];

// Negation words that flip sentiment
$negation_words = ['not', 'no', 'never', 'neither', 'nobody', 'nothing',
    'nowhere', 'nor', "don't", "doesn't", "didn't", "won't", "wouldn't",
    "couldn't", "shouldn't", "isn't", "aren't", "wasn't", "weren't",
    'hindi', 'wala', 'walang', 'huwag'];

// Intensifiers that amplify sentiment
$intensifiers = ['very' => 1.5, 'really' => 1.5, 'extremely' => 2.0, 'absolutely' => 2.0,
    'totally' => 1.5, 'completely' => 1.5, 'highly' => 1.5, 'so' => 1.3,
    'too' => 1.3, 'most' => 1.5, 'sobra' => 1.5, 'napaka' => 2.0, 'grabe' => 1.5];

// ── Topic / Theme keywords ──
$topic_keywords = [
    'infrastructure' => ['road', 'roads', 'bridge', 'building', 'construction', 'drainage', 'flood', 'flooding', 'sidewalk', 'pothole', 'traffic', 'kalye', 'kalsada', 'gusali', 'baha'],
    'health' => ['health', 'hospital', 'clinic', 'medical', 'medicine', 'doctor', 'nurse', 'vaccine', 'covid', 'dengue', 'kalusugan', 'ospital'],
    'education' => ['school', 'education', 'student', 'teacher', 'classroom', 'scholarship', 'paaralan', 'estudyante', 'guro'],
    'safety' => ['crime', 'police', 'safety', 'security', 'drugs', 'violence', 'theft', 'robbery', 'barangay tanod', 'kaligtasan', 'krimen', 'pulis'],
    'environment' => ['garbage', 'waste', 'pollution', 'clean', 'tree', 'green', 'environment', 'basura', 'kalikasan', 'polusyon'],
    'social_services' => ['ayuda', 'aid', 'assistance', 'poverty', 'livelihood', 'housing', 'senior', 'pwd', 'subsidy', 'tulong', 'kahirapan', 'pabahay'],
    'governance' => ['corrupt', 'corruption', 'transparency', 'budget', 'tax', 'government', 'official', 'mayor', 'barangay', 'gobyerno', 'buwis', 'opisyal'],
    'public_service' => ['service', 'queue', 'waiting', 'process', 'permit', 'id', 'document', 'office', 'staff', 'serbisyo', 'pila', 'proseso'],
];

// ── Emotion keywords ──
$emotion_keywords = [
    'anger' => ['angry', 'furious', 'outraged', 'mad', 'galit', 'nagagalit', 'nakakagalit', 'disgusted', 'livid'],
    'fear' => ['afraid', 'scared', 'worried', 'anxious', 'fear', 'unsafe', 'takot', 'natatakot', 'nag-aalala', 'dangerous'],
    'joy' => ['happy', 'glad', 'thankful', 'grateful', 'proud', 'excited', 'masaya', 'nagpapasalamat', 'proud'],
    'sadness' => ['sad', 'disappointed', 'heartbroken', 'hopeless', 'malungkot', 'dismayado', 'walang pag-asa'],
    'trust' => ['trust', 'believe', 'confident', 'faith', 'reliable', 'tiwala', 'mapagkakatiwalaan'],
    'frustration' => ['frustrated', 'annoyed', 'irritated', 'fed up', 'tired of', 'bored', 'inis', 'naiirita', 'sawa'],
];

/**
 * Analyze sentiment of a text string.
 * Returns comprehensive analysis with sentiment, topics, emotions, urgency, and recommendations.
 */
function analyzeSentiment($text) {
    global $positive_words, $negative_words, $negation_words, $intensifiers, $topic_keywords, $emotion_keywords;

    if (empty(trim($text))) {
        return ['sentiment' => 'neutral', 'score' => 0, 'confidence' => 0, 'keywords' => [], 'topics' => [], 'emotions' => [], 'urgency' => 'low', 'summary' => 'No text provided.', 'recommendation' => 'N/A'];
    }

    $originalText = $text;
    $text = mb_strtolower($text, 'UTF-8');
    $clean = preg_replace("/[^a-z0-9\s']/u", ' ', $text);
    $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

    $totalScore = 0;
    $matchedKeywords = [];
    $wordCount = count($words);
    $positiveCount = 0;
    $negativeCount = 0;

    for ($i = 0; $i < $wordCount; $i++) {
        $word = $words[$i];
        $score = 0;

        if (isset($positive_words[$word])) $score = $positive_words[$word];
        if (isset($negative_words[$word])) $score = $negative_words[$word];

        if ($i < $wordCount - 1) {
            $bigram = $word . ' ' . $words[$i + 1];
            if (isset($positive_words[$bigram])) $score = $positive_words[$bigram];
            if (isset($negative_words[$bigram])) $score = $negative_words[$bigram];
        }

        if ($score !== 0) {
            $negated = false;
            for ($j = max(0, $i - 2); $j < $i; $j++) {
                if (in_array($words[$j], $negation_words)) { $negated = true; break; }
            }
            if ($negated) $score = -$score * 0.75;
            if ($i > 0 && isset($intensifiers[$words[$i - 1]])) $score *= $intensifiers[$words[$i - 1]];

            $totalScore += $score;
            if ($score > 0) $positiveCount++;
            if ($score < 0) $negativeCount++;
            $matchedKeywords[] = ['word' => $word, 'score' => round($score, 2), 'negated' => $negated];
        }
    }

    // Sentiment label
    if ($totalScore > 1) { $sentiment = 'positive'; }
    elseif ($totalScore < -1) { $sentiment = 'negative'; }
    else { $sentiment = 'neutral'; }

    // Confidence
    $keywordCount = count($matchedKeywords);
    $magnitude = abs($totalScore);
    $confidence = min(1.0, ($magnitude / 5) * 0.6 + ($keywordCount / 5) * 0.4);

    // ── Topic Detection ──
    $detectedTopics = [];
    foreach ($topic_keywords as $topic => $kws) {
        $hits = 0;
        foreach ($kws as $kw) {
            if (strpos($text, $kw) !== false) $hits++;
        }
        if ($hits > 0) $detectedTopics[] = ['topic' => $topic, 'hits' => $hits];
    }
    usort($detectedTopics, fn($a, $b) => $b['hits'] - $a['hits']);

    // ── Emotion Detection ──
    $detectedEmotions = [];
    foreach ($emotion_keywords as $emotion => $kws) {
        $hits = 0;
        foreach ($kws as $kw) {
            if (strpos($text, $kw) !== false) $hits++;
        }
        if ($hits > 0) $detectedEmotions[] = ['emotion' => $emotion, 'intensity' => min(1.0, $hits / 3)];
    }
    usort($detectedEmotions, fn($a, $b) => $b['intensity'] <=> $a['intensity']);

    // ── Urgency Assessment ──
    $urgencyScore = 0;
    $urgentPhrases = ['urgent', 'emergency', 'immediately', 'asap', 'right now', 'help', 'please help',
        'agad', 'tulong', 'ngayon', 'agaran', 'danger', 'dangerous', 'critical', 'dying', 'dead'];
    foreach ($urgentPhrases as $p) {
        if (strpos($text, $p) !== false) $urgencyScore += 2;
    }
    if ($totalScore < -3) $urgencyScore += 2;
    if ($totalScore < -6) $urgencyScore += 2;
    if (preg_match('/[!]{2,}/', $originalText)) $urgencyScore += 1;
    if (mb_strtoupper($originalText) === $originalText && mb_strlen($originalText) > 10) $urgencyScore += 1;

    if ($urgencyScore >= 4) $urgency = 'critical';
    elseif ($urgencyScore >= 2) $urgency = 'high';
    elseif ($negativeCount > 0) $urgency = 'medium';
    else $urgency = 'low';

    // ── Generate actionable summary ──
    $topicNames = array_map(fn($t) => ucwords(str_replace('_', ' ', $t['topic'])), array_slice($detectedTopics, 0, 3));
    $emotionNames = array_map(fn($e) => ucfirst($e['emotion']), array_slice($detectedEmotions, 0, 2));

    $summary = '';
    if ($sentiment === 'positive') {
        $summary = 'This citizen expresses satisfaction';
        if (!empty($topicNames)) $summary .= ' regarding ' . implode(' and ', $topicNames);
        $summary .= '. Positive feedback like this can be highlighted in reports to show community approval.';
    } elseif ($sentiment === 'negative') {
        $summary = 'This citizen raises concerns';
        if (!empty($topicNames)) $summary .= ' about ' . implode(' and ', $topicNames);
        if (!empty($emotionNames)) $summary .= ', expressing ' . mb_strtolower(implode(' and ', $emotionNames));
        $summary .= '. This requires attention and a timely response.';
    } else {
        $summary = 'This feedback is mostly neutral or mixed';
        if (!empty($topicNames)) $summary .= ', touching on ' . implode(' and ', $topicNames);
        $summary .= '. Consider reviewing for any underlying concerns not captured by keywords.';
    }

    // ── Recommended admin action ──
    if ($urgency === 'critical') {
        $recommendation = 'Immediate action required. Assign to relevant department head and respond within 24 hours.';
    } elseif ($sentiment === 'negative' && $urgency === 'high') {
        $recommendation = 'Flag for priority review. Acknowledge the citizen\'s concern and provide a concrete timeline for resolution.';
    } elseif ($sentiment === 'negative') {
        $recommendation = 'Schedule a follow-up response. Address the specific issues raised and offer next steps or a point of contact.';
    } elseif ($sentiment === 'positive') {
        $recommendation = 'Send a thank-you acknowledgment. Consider featuring this feedback in community reports or public recognition.';
    } else {
        $recommendation = 'Review for any implicit concerns. A brief acknowledgment response is recommended.';
    }

    return [
        'sentiment' => $sentiment,
        'score' => round($totalScore, 2),
        'confidence' => round($confidence, 2),
        'keywords' => $matchedKeywords,
        'topics' => $detectedTopics,
        'emotions' => $detectedEmotions,
        'urgency' => $urgency,
        'summary' => $summary,
        'recommendation' => $recommendation,
        'positive_count' => $positiveCount,
        'negative_count' => $negativeCount,
        'word_count' => $wordCount,
    ];
}

try {
    switch ($action) {
        case 'analyze':
            $data = json_decode(file_get_contents('php://input'), true);
            $text = trim($data['text'] ?? '');

            if ($text === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Text is required']);
                exit;
            }

            $result = analyzeSentiment($text);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'batch':
            // Analyze all feedback or specific IDs
            $data = json_decode(file_get_contents('php://input'), true);
            $ids = $data['ids'] ?? [];

            if (!empty($ids) && is_array($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $conn->prepare("SELECT id, message, guest_name FROM feedback WHERE id IN ($placeholders)");
                $types = str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$ids);
            } else {
                $stmt = $conn->prepare("SELECT id, message, guest_name FROM feedback ORDER BY created_at DESC LIMIT 200");
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $results = [];

            while ($row = $result->fetch_assoc()) {
                $analysis = analyzeSentiment($row['message']);
                $results[] = [
                    'id' => (int)$row['id'],
                    'author' => $row['guest_name'] ?? 'Guest',
                    'message_preview' => mb_substr($row['message'], 0, 100, 'UTF-8'),
                    'sentiment' => $analysis['sentiment'],
                    'score' => $analysis['score'],
                    'confidence' => $analysis['confidence'],
                    'keywords' => $analysis['keywords']
                ];
            }
            $stmt->close();

            // Summary stats
            $positive = count(array_filter($results, fn($r) => $r['sentiment'] === 'positive'));
            $negative = count(array_filter($results, fn($r) => $r['sentiment'] === 'negative'));
            $neutral = count(array_filter($results, fn($r) => $r['sentiment'] === 'neutral'));
            $total = count($results);
            $avgScore = $total > 0 ? round(array_sum(array_column($results, 'score')) / $total, 2) : 0;

            echo json_encode([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total' => $total,
                    'positive' => $positive,
                    'negative' => $negative,
                    'neutral' => $neutral,
                    'average_score' => $avgScore
                ]
            ]);
            break;

        case 'save':
            // Allow saving by analyzing and returning the result for now
            $data = json_decode(file_get_contents('php://input'), true);
            $text = trim($data['text'] ?? '');
            if ($text === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Text is required']);
                exit;
            }
            $result = analyzeSentiment($text);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action. Use: analyze, batch, save']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
