import os

api_file = r'c:\xampp\htdocs\CAP101\PC\API\consultation_feedback.php'

with open(api_file, 'r', encoding='utf-8') as f:
    code = f.read()

old_block = """            if ($consultation_id <= 0 || $messageRaw === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID and message are required']);
                exit;
            }"""

new_block = """            if ($consultation_id <= 0 || $messageRaw === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Consultation ID and message are required']);
                exit;
            }

            // Check if consultation is concluded or past end date
            $chkStatus = $conn->query("SELECT status, end_date FROM consultations WHERE id = {$consultation_id} LIMIT 1");
            $cRow = $chkStatus ? $chkStatus->fetch_assoc() : null;
            if ($cRow) {
                $stClean = strtolower(trim($cRow['status'] ?? ''));
                $endDate = !empty($cRow['end_date']) ? strtotime($cRow['end_date']) : null;
                $isPastEnd = ($endDate && $endDate < strtotime('today'));
                $isClosed = in_array($stClean, ['closed', 'completed', 'resolved', 'declined', 'forwarded_orts', 'proceeded_to_ordinance', 'rejected', 'archived', 'endorsed'], true);

                if ($isPastEnd || $isClosed) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'This public consultation has concluded and is closed for new feedback submissions.']);
                    exit;
                }
            }"""

if old_block in code:
    code = code.replace(old_block, new_block)
    with open(api_file, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated API/consultation_feedback.php with concluded check.")

print("Finished API update.")
