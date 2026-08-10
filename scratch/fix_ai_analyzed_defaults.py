import os

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php'
]

for filepath in files_to_update:
    if not os.path.exists(filepath):
        continue
    with open(filepath, 'r', encoding='utf-8') as f:
        code = f.read()

    # 1. Update schema defaults in ensureResourcePersonSchema
    code = code.replace("DEFAULT 1", "DEFAULT 0")

    # 2. Update isConsultationVisibleToExpert defaults
    code = code.replace(
        "$aiAnalyzed = isset($cRow['ai_analyzed']) ? (int)$cRow['ai_analyzed'] : 1;",
        "$aiAnalyzed = isset($cRow['ai_analyzed']) ? (int)$cRow['ai_analyzed'] : 0;"
    )
    code = code.replace(
        "$forwarded = isset($cRow['forwarded_to_expert']) ? (int)$cRow['forwarded_to_expert'] : 1;",
        "$forwarded = isset($cRow['forwarded_to_expert']) ? (int)$cRow['forwarded_to_expert'] : 0;"
    )

    # 3. Update isForwardedByAdmin check
    old_fwd_check = "$isForwardedByAdmin = ($assignedTo > 0 || $forwarded === 1 || in_array($docStatus, ['sent_to_expert', 'expert_annotated', 'admin_validated', 'forwarded_to_committee']));"
    new_fwd_check = "$isForwardedByAdmin = ($assignedTo === $user_id || $forwarded === 1 || in_array($docStatus, ['sent_to_expert', 'expert_annotated', 'admin_validated', 'forwarded_to_committee']));"
    code = code.replace(old_fwd_check, new_fwd_check)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated visibility filtering and schema defaults in:", filepath)
