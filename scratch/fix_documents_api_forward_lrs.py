import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\API\documents_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\documents_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\documents_api.php'
]

old_forward_case = """        case 'forward_lrs':
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
            break;"""

new_forward_case = """        case 'forward_lrs':
        case 'forward_to_lrs':
        case 'forward_to_lrm':
            $inputData = array_merge($_GET, $_POST, jsonInput());
            $id = (int)($inputData['id'] ?? $inputData['document_id'] ?? $inputData['consultation_id'] ?? 0);
            $source = normalizeSource($inputData['source'] ?? 'consultation');
            $description = trim((string)($inputData['description'] ?? ''));
            $performer = trim((string)($inputData['performed_by'] ?? $_SESSION['fullname'] ?? 'Admin'));

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
            break;"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if old_forward_case in code:
        code = code.replace(old_forward_case, new_forward_case)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated case forward_lrs in:", fpath)
    else:
        print("Old forward case not found in:", fpath)
