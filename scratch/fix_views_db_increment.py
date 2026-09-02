import os
import re

api_files = [
    r"c:\xampp\htdocs\CAP101\PC\API\consultations_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\API\consultations_api.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\API\consultations_api.php",
]

old_get_case = """            $consultation = getConsultationById($id);

            if ($consultation) {

                echo json_encode(['success' => true, 'data' => $consultation]);"""

new_get_case = """            $consultation = getConsultationById($id);

            if ($consultation) {
                @$conn->query("UPDATE consultations SET views = COALESCE(views, 0) + 1 WHERE id = " . (int)$id);
                $consultation['views'] = (int)($consultation['views'] ?? 0) + 1;
                echo json_encode(['success' => true, 'data' => $consultation]);"""

for fp in api_files:
    if os.path.exists(fp):
        with open(fp, 'r', encoding='utf-8') as f:
            c = f.read()
        if old_get_case in c:
            c = c.replace(old_get_case, new_get_case)
            with open(fp, 'w', encoding='utf-8') as f:
                f.write(c)
            print(f"Updated views DB increment in {fp}")
        else:
            print(f"Old case 'get' not matched in {fp}")
