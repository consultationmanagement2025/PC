from pathlib import Path
import re

path = Path(__file__).resolve().parent / 'DATABASE' / 'consultations.php'
text = path.read_text(encoding='utf-8')
marker = '    return null;\n\n}\n\n// Get consultation posts count\n'
if marker not in text:
    raise SystemExit('Marker not found')
insert = '''    return null;

}

function generateConsultationTrackingNumber($consultation_id) {
    $consultation_id = (int)$consultation_id;
    return sprintf('CONSULT-%06d', $consultation_id);
}

function assignConsultationTrackingNumber($consultation_id) {
    global $conn;

    initializeConsultationsTable();

    $consultation_id = (int)$consultation_id;
    $tracking_number = generateConsultationTrackingNumber($consultation_id);

    $stmt = $conn->prepare("UPDATE consultations SET tracking_number = ? WHERE id = ? AND (tracking_number IS NULL OR tracking_number = '')");
    if ($stmt) {
        $stmt->bind_param('si', $tracking_number, $consultation_id);
        $stmt->execute();
        $stmt->close();
    }

    return $tracking_number;
}

function getConsultationByTrackingNumber($tracking_number) {
    global $conn;

    initializeConsultationsTable();
    syncConsultationStatuses();

    $tracking_number = trim((string)$tracking_number);
    if ($tracking_number === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM consultations WHERE tracking_number = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $tracking_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $consultation = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($consultation) {
        $consultation['posts_count'] = getConsultationPostsCount((int)$consultation['id']);
        $consultation['vote_stats'] = getConsultationVoteStats((int)$consultation['id']);
    }

    return $consultation;
}

// Get consultation posts count\n'''
text = text.replace(marker, insert, 1)
path.write_text(text, encoding='utf-8')
print('inserted helpers')
