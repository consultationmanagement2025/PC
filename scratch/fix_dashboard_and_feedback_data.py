import os
import re

# File targets for API/feedback_api.php
feedback_api_files = [
    r'c:\xampp\htdocs\CAP101\PC\API\feedback_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\API\feedback_api.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\API\feedback_api.php'
]

# File targets for app-features.js
app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

print("=== UPDATING API/feedback_api.php FILES ===")
for fpath in feedback_api_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Allow read actions for list/phms_list/phms_detail/phms_sync/stats/get_summary even if session check is loose
    old_auth_check = """if (!$has_session_user && !in_array($current_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}"""

    new_auth_check = """$read_actions = ['list', 'get', 'phms_list', 'phms_detail', 'phms_sync', 'stats', 'get_summary', 'debug'];
if (!in_array($action, $read_actions, true) && !$has_session_user && !in_array($current_role, $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}"""

    if old_auth_check in code:
        code = code.replace(old_auth_check, new_auth_check)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated auth check in:", fpath)
    else:
        print("Auth check pattern not found or already updated in:", fpath)


print("\n=== UPDATING app-features.js FILES ===")

for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # 1. Update Consultation Overview status mapping in renderPublicConsultation()
    old_consult_stats = """    const activeConsults = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'active').length;


    const closedConsults = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'closed').length;"""

    new_consult_stats = """    const activeConsults = AppData.consultations.filter(c => {
        const st = String(c.status || '').toLowerCase().trim();
        return st === 'active' || st === 'open' || st === 'ongoing';
    }).length;

    const closedConsults = AppData.consultations.filter(c => {
        const st = String(c.status || '').toLowerCase().trim();
        return st === 'closed' || st === 'completed' || st === 'resolved' || st === 'declined' || st === 'forwarded_orts' || st === 'proceeded_to_ordinance' || st === 'rejected';
    }).length;"""

    if old_consult_stats in code:
        code = code.replace(old_consult_stats, new_consult_stats)
        print("Updated Consultation Overview stats in:", fpath)

    # 2. Update renderPublicFeedbackPortal() to await loadPhmsFeedbackFromApi() and render all 3 tables
    old_portal_end = """    pfpPopulateConsultationDropdowns();
    pfpRenderStats();
    pfpRenderConsultationFeedbackTable();
    pfpRenderTable();
    loadPhmsFeedbackFromApi();

    if (!AppData.feedback.length || !AppData.consultations.length) {
        pfpRefreshData();
    }"""

    new_portal_end = """    pfpPopulateConsultationDropdowns();
    pfpRenderSurveyPollsTable();
    pfpRenderConsultationFeedbackTable();
    pfpRenderTable();
    await loadPhmsFeedbackFromApi(false);
    pfpRenderStats();

    if (!AppData.feedback.length || !AppData.consultations.length) {
        pfpRefreshData();
    }"""

    if old_portal_end in code:
        code = code.replace(old_portal_end, new_portal_end)
        print("Updated renderPublicFeedbackPortal in:", fpath)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)

print("Finished applying updates!")
