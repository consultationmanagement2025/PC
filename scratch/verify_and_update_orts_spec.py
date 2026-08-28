import os

utils_files = [
    r'c:\xampp\htdocs\CAP101\PC\UTILS\orts_integration_utils.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\UTILS\orts_integration_utils.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\UTILS\orts_integration_utils.php'
]

function_code = """
if (!function_exists('sendFeedbackToOrts')) {
    /**
     * Pushes citizen feedback to ORTS API event endpoint.
     * Target Endpoint: POST https://ort.spvalenzuela.com/api/v1/events.php
     *
     * @param int $documentId ORTS Document ID
     * @param string $ref Reference Number (e.g. ORD-2025-001)
     * @param string $notes Citizen feedback text
     * @param string $name Submitter name
     * @param string $type Feedback type: support | oppose | suggestion | general
     * @return array Response from ORTS API
     */
    function sendFeedbackToOrts(int $documentId, string $ref, string $notes, string $name = '', string $type = 'general'): array {
        $ch = curl_init('https://ort.spvalenzuela.com/api/v1/events.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POSTFIELDS => json_encode([
                'event' => 'public_feedback_received',
                'document_id' => $documentId,
                'reference_number' => $ref,
                'notes' => $notes,
                'submitter_name' => $name,
                'feedback_type' => $type,
                'source_system' => 'PCMS'
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer pcms_live_5a9c3e7f1b6048d2e6a8c4f9b1d70328',
                'X-Source-System: PCMS'
            ]
        ]);
        $res = json_decode(curl_exec($ch), true);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($curlErr) {
            error_log("ORTS sendFeedbackToOrts Error: " . $curlErr);
        }
        return $res ?? [];
    }
}
"""

print("=== VERIFYING & UPDATING UTILS/ORTS_INTEGRATION_UTILS.PHP FILES ===")

for fpath in utils_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if "function sendFeedbackToOrts" not in code:
        code = code + "\n" + function_code
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Appended sendFeedbackToOrts to:", fpath)
    else:
        print("sendFeedbackToOrts already present in:", fpath)

print("Done updating UTILS files!")
