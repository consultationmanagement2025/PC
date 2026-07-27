<?php
// Server-Sent Events endpoint to stream report updates to the admin UI
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // disable nginx buffering when available
set_time_limit(0);
// Allow CORS for local admin UI (restrict if deploying publicly)
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';

function send_event($id, $payload) {
    echo "id: {$id}\n";
    echo "event: reports\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush();
    @flush();
}

$lastId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? intval($_SERVER['HTTP_LAST_EVENT_ID']) : 0;
$counter = $lastId ?: 0;

while (!connection_aborted()) {
    try {
        // Overall summary
        $summary = ['consultations_total' => 0, 'pending_review' => 0, 'survey_responses' => 0, 'feedback_total' => 0, 'feedback_avg_rating' => 0.0];
        $r = $conn->query("SELECT COUNT(*) AS consultations_total, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_review FROM consultations");
        if ($r) {
            $row = $r->fetch_assoc();
            $summary['consultations_total'] = (int)($row['consultations_total'] ?? 0);
            $summary['pending_review'] = (int)($row['pending_review'] ?? 0);
        }

        $r = $conn->query("SELECT (SELECT COUNT(*) FROM consultation_votes) + (SELECT COUNT(*) FROM consultation_guest_votes) AS survey_responses");
        if ($r) {
            $row = $r->fetch_assoc();
            $summary['survey_responses'] = (int)($row['survey_responses'] ?? 0);
        }

        $r = $conn->query("SELECT COUNT(*) AS total, AVG(COALESCE(rating,0)) AS avg_rating FROM feedback");
        if ($r) {
            $row = $r->fetch_assoc();
            $summary['feedback_total'] = (int)($row['total'] ?? 0);
            $summary['feedback_avg_rating'] = (float)($row['avg_rating'] ?? 0.0);
        }

        // Status breakdown
        $status_breakdown = [];
        $r = $conn->query("SELECT status, COUNT(*) AS total FROM consultations GROUP BY status ORDER BY total DESC");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $status_breakdown[] = ['status' => $row['status'], 'total' => (int)$row['total']];
            }
        }

        // Category breakdown (top 6)
        $category_breakdown = [];
        $r = $conn->query("SELECT COALESCE(category,'Uncategorized') AS category, COUNT(*) AS total FROM consultations GROUP BY category ORDER BY total DESC LIMIT 6");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $category_breakdown[] = ['category' => $row['category'], 'total' => (int)$row['total']];
            }
        }

        // Recent consultations
        $recent_consultations = [];
        $r = $conn->query("SELECT id, title, category, status, type, created_at FROM consultations ORDER BY created_at DESC LIMIT 6");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $recent_consultations[] = $row;
            }
        }

        // Recent feedback
        $recent_feedback = [];
        $r = $conn->query("SELECT id, guest_name, category, rating, status, created_at FROM feedback ORDER BY created_at DESC LIMIT 6");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $recent_feedback[] = $row;
            }
        }

        $payload = [
            'overall' => $summary,
            'status_breakdown' => $status_breakdown,
            'category_breakdown' => $category_breakdown,
            'recent_consultations' => $recent_consultations,
            'recent_feedback' => $recent_feedback,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        send_event(++$counter, $payload);

    } catch (Exception $ex) {
        // send an error event and continue
        send_event(++$counter, ['error' => $ex->getMessage()]);
    }

    // Wait before next update; tune interval for your environment
    sleep(5);
}

exit;

?>
