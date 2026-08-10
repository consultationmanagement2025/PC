import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\DATABASE\document-management.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\document-management.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\document-management.php'
]

lookup_code_old = """    if ($source === 'admin') {
        $doc = getAdminDocumentById($id);
        if ($doc) {
            $isConsultation = false;
        } else {
            $doc = getDocumentById($id);
            if ($doc) $isConsultation = true;
        }
    } else {
        $doc = getDocumentById($id);
        if ($doc) {
            $isConsultation = true;
        } else {
            $doc = getAdminDocumentById($id);
            if ($doc) $isConsultation = false;
        }
    }

    if (!$doc) {
        return ['success' => false, 'message' => 'Document ID ' . $id . ' not found'];
    }"""

lookup_code_new = """    if ($source === 'admin') {
        $doc = getAdminDocumentById($id);
        if ($doc) {
            $isConsultation = false;
        } else {
            $doc = getDocumentById($id);
            if ($doc) $isConsultation = true;
        }
    } else {
        $doc = getDocumentById($id);
        if ($doc) {
            $isConsultation = true;
        } else {
            // Check if document exists for consultation_id = $id
            $chkByCId = $conn->prepare("SELECT * FROM documents WHERE consultation_id = ? ORDER BY id DESC LIMIT 1");
            if ($chkByCId) {
                $chkByCId->bind_param('i', $id);
                $chkByCId->execute();
                $cRes = $chkByCId->get_result();
                $doc = $cRes ? $cRes->fetch_assoc() : null;
                $chkByCId->close();
            }
            if ($doc) {
                $isConsultation = true;
            } else {
                // Check if $id is a valid consultation ID
                $cChk = $conn->prepare("SELECT id FROM consultations WHERE id = ? LIMIT 1");
                if ($cChk) {
                    $cChk->bind_param('i', $id);
                    $cChk->execute();
                    $cExists = $cChk->get_result()->fetch_assoc();
                    $cChk->close();
                    if ($cExists) {
                        require_once __DIR__ . '/../UTILS/generate_consultation_documents.php';
                        if (function_exists('generateConsultationDocuments')) {
                            generateConsultationDocuments($id);
                            $chkByCId = $conn->prepare("SELECT * FROM documents WHERE consultation_id = ? ORDER BY id DESC LIMIT 1");
                            if ($chkByCId) {
                                $chkByCId->bind_param('i', $id);
                                $chkByCId->execute();
                                $cRes = $chkByCId->get_result();
                                $doc = $cRes ? $cRes->fetch_assoc() : null;
                                $chkByCId->close();
                            }
                        }
                    }
                }
                if ($doc) {
                    $isConsultation = true;
                } else {
                    $doc = getAdminDocumentById($id);
                    if ($doc) $isConsultation = false;
                }
            }
        }
    }

    if (!$doc) {
        return ['success' => false, 'message' => 'Document ID ' . $id . ' not found'];
    }"""

for fpath in files_to_update:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if lookup_code_old in code:
        code = code.replace(lookup_code_old, lookup_code_new)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated lookup logic in:", fpath)
    else:
        print("Lookup code old not found in:", fpath)
