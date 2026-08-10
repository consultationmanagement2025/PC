import os, re

# 1. Update DATABASE/consultations.php
c_db_path = r'c:\xampp\htdocs\CAP101\PC\DATABASE\consultations.php'
if os.path.exists(c_db_path):
    with open(c_db_path, 'r', encoding='utf-8') as f:
        code = f.read()

    old_insert = """$stmt = $conn->prepare("INSERT INTO consultations (title, description, category, district, barangay, start_date, end_date, admin_id, expected_posts, status, type, image_path, user_name, user_email, allow_email_notifications, source_url, response_mode, survey_question, survey_option_a, survey_option_b, allow_guest_quick_vote, allow_guest_verified_vote)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");"""

    new_insert = """$stmt = $conn->prepare("INSERT INTO consultations (title, description, category, district, barangay, start_date, end_date, admin_id, expected_posts, status, type, image_path, user_name, user_email, allow_email_notifications, source_url, response_mode, survey_question, survey_option_a, survey_option_b, allow_guest_quick_vote, allow_guest_verified_vote, ai_analyzed, forwarded_to_expert, document_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'draft')");"""

    if old_insert in code:
        code = code.replace(old_insert, new_insert)
        with open(c_db_path, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated createConsultation INSERT in DATABASE/consultations.php to set ai_analyzed = 0, forwarded_to_expert = 0.")

# Also update admin copies of DATABASE/consultations.php if they exist
for copy_path in [r'c:\xampp\htdocs\CAP101\PC\admin\DATABASE\consultations.php', r'c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\consultations.php']:
    if os.path.exists(copy_path):
        with open(copy_path, 'r', encoding='utf-8') as f:
            c = f.read()
        if old_insert in c:
            c = c.replace(old_insert, new_insert)
            with open(copy_path, 'w', encoding='utf-8') as f:
                f.write(c)
            print("Updated createConsultation in:", copy_path)

# 2. Update API/consultation_feedback_ai.php
ai_api_path = r'c:\xampp\htdocs\CAP101\PC\API\consultation_feedback_ai.php'
if os.path.exists(ai_api_path):
    with open(ai_api_path, 'r', encoding='utf-8') as f:
        code = f.read()

    old_ai_update = "$uStmt = $conn->prepare(\"UPDATE consultations SET ai_committee_brief = ?, committee_assigned = ? WHERE id = ?\");"
    new_ai_update = "$uStmt = $conn->prepare(\"UPDATE consultations SET ai_committee_brief = ?, committee_assigned = ?, ai_analyzed = 1 WHERE id = ?\");"

    if old_ai_update in code:
        code = code.replace(old_ai_update, new_ai_update)
        with open(ai_api_path, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated API/consultation_feedback_ai.php to set ai_analyzed = 1 when AI brief is generated.")

# 3. Update API/forward_to_resource_person.php
fwd_api_path = r'c:\xampp\htdocs\CAP101\PC\API\forward_to_resource_person.php'
if os.path.exists(fwd_api_path):
    with open(fwd_api_path, 'r', encoding='utf-8') as f:
        code = f.read()

    code = code.replace("DEFAULT 1", "DEFAULT 0")
    with open(fwd_api_path, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated API/forward_to_resource_person.php column defaults to DEFAULT 0.")
