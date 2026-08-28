import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\UTILS\orts_integration_utils.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\UTILS\orts_integration_utils.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\UTILS\orts_integration_utils.php'
]

sync_func_code = """

if (!function_exists('syncOrtsConsultationsToPcms')) {
    /**
     * Synchronizes active ORTS ordinances into PCMS public consultations table
     * so citizens can browse them and submit feedback on the PCMS portal.
     */
    function syncOrtsConsultationsToPcms($conn): array {
        if (!$conn || !($conn instanceof mysqli)) {
            return ['success' => false, 'message' => 'Database connection required'];
        }

        $res = fetchOrtsDocuments(['limit' => 50]);
        if (empty($res['success'])) {
            return ['success' => false, 'message' => 'Failed to fetch documents from ORTS API', 'details' => $res];
        }

        $documents = [];
        if (!empty($res['data']['documents']) && is_array($res['data']['documents'])) {
            $documents = $res['data']['documents'];
        } elseif (!empty($res['data']) && is_array($res['data'])) {
            $documents = $res['data'];
        }

        if (empty($documents)) {
            return ['success' => true, 'message' => 'No active documents returned from ORTS', 'imported' => 0];
        }

        $imported = 0;
        $updated = 0;

        foreach ($documents as $doc) {
            $ref = trim((string)($doc['reference_number'] ?? $doc['tracking_number'] ?? $doc['reference'] ?? ''));
            $title = trim((string)($doc['title'] ?? ''));
            if ($ref === '' || $title === '') continue;

            $desc = trim((string)($doc['description'] ?? $doc['summary'] ?? 'Ordinance file submitted for public consultation and legislative tracking.'));
            $category = trim((string)($doc['category'] ?? $doc['committee'] ?? 'Ordinance Consultation'));

            // Check existing consultation by tracking_number or external_ref
            $chkStmt = $conn->prepare("SELECT id FROM consultations WHERE tracking_number = ? OR external_ref = ? LIMIT 1");
            if ($chkStmt) {
                $chkStmt->bind_param('ss', $ref, $ref);
                $chkStmt->execute();
                $cRow = $chkStmt->get_result()->fetch_assoc();
                $chkStmt->close();

                if ($cRow) {
                    $uStmt = $conn->prepare("UPDATE consultations SET title = ?, description = ?, category = ?, external_ref = ?, synced_at = NOW() WHERE id = ?");
                    if ($uStmt) {
                        $cid = (int)$cRow['id'];
                        $uStmt->bind_param('ssssi', $title, $desc, $category, $ref, $cid);
                        $uStmt->execute();
                        $uStmt->close();
                        $updated++;
                    }
                } else {
                    $iStmt = $conn->prepare("INSERT INTO consultations (title, description, category, status, type, tracking_number, external_ref, source_system, created_at, synced_at) VALUES (?, ?, ?, 'active', 'ordinance', ?, ?, 'ORTS', NOW(), NOW())");
                    if ($iStmt) {
                        $iStmt->bind_param('sssss', $title, $desc, $category, $ref, $ref);
                        $iStmt->execute();
                        $iStmt->close();
                        $imported++;
                    }
                }
            }
        }

        return [
            'success' => true,
            'message' => "ORTS Consultation sync completed. Imported: {$imported}, Updated: {$updated}.",
            'imported' => $imported,
            'updated' => $updated,
            'total_processed' => count($documents)
        ];
    }
}
"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        print(f"Missing: {fpath}")
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()

    if 'function syncOrtsConsultationsToPcms' not in content:
        content += sync_func_code
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Appended syncOrtsConsultationsToPcms to {fpath}")
    else:
        print(f"Function already present in {fpath}")

print("Done updating UTILS/orts_integration_utils.php files.")
