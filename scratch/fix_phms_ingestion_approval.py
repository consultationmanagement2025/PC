import os
import re

db_files = [
    r"c:\xampp\htdocs\CAP101\PC\DATABASE\feedback.php",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\DATABASE\feedback.php",
    r"c:\xampp\htdocs\CAP101\PC\admin\DATABASE\feedback.php",
]

js_files = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

# 1. Update getPendingPhmsApprovals in DB files
new_db_func = """function getPendingPhmsApprovals($statusFilter = 'all') {
    global $conn;
    initializeHearingQueueTable();
    
    // First try fetching strictly pending items
    $sqlPending = "SELECT hq.phms_hearing_id, 
                          MAX(hq.queue_id) as queue_id, 
                          MAX(hq.full_name) as full_name, 
                          MAX(hq.email) as email, 
                          MAX(hq.status) as status, 
                          MAX(hq.approval_status) as approval_status,
                          MAX(hq.created_at) as created_at, 
                          MAX(hq.consultation_id) as consultation_id, 
                          MAX(hq.payload_json) as payload_json, 
                          COUNT(*) as feedback_count, 
                          c.title as consultation_title
                   FROM hearing_queue hq
                   LEFT JOIN consultations c ON hq.consultation_id = c.id
                   WHERE hq.approval_status = 'pending'
                   GROUP BY COALESCE(hq.phms_hearing_id, hq.queue_id)
                   ORDER BY created_at DESC";
    $res = $conn->query($sqlPending);
    $items = [];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $payload = [];
            if (!empty($row['payload_json'])) {
                $payload = is_string($row['payload_json']) ? json_decode($row['payload_json'], true) : $row['payload_json'];
            }
            $responses = $payload['citizen_responses'] ?? $payload['citizen_feedback'] ?? [];
            $row['parsed_payload'] = $payload;
            $row['feedback_count'] = max((int)$row['feedback_count'], count($responses), (int)($payload['feedback_count'] ?? 0));
            $items[] = $row;
        }
        return $items;
    }

    // If no pending items exist, fetch all recent PHMS transmittals with their approval_status
    $sqlAll = "SELECT hq.phms_hearing_id, 
                      MAX(hq.queue_id) as queue_id, 
                      MAX(hq.full_name) as full_name, 
                      MAX(hq.email) as email, 
                      MAX(hq.status) as status, 
                      MAX(hq.approval_status) as approval_status,
                      MAX(hq.created_at) as created_at, 
                      MAX(hq.consultation_id) as consultation_id, 
                      MAX(hq.payload_json) as payload_json, 
                      COUNT(*) as feedback_count, 
                      c.title as consultation_title
               FROM hearing_queue hq
               LEFT JOIN consultations c ON hq.consultation_id = c.id
               GROUP BY COALESCE(hq.phms_hearing_id, hq.queue_id)
               ORDER BY created_at DESC LIMIT 20";
    $resAll = $conn->query($sqlAll);
    if ($resAll && $resAll->num_rows > 0) {
        while ($row = $resAll->fetch_assoc()) {
            $payload = [];
            if (!empty($row['payload_json'])) {
                $payload = is_string($row['payload_json']) ? json_decode($row['payload_json'], true) : $row['payload_json'];
            }
            $responses = $payload['citizen_responses'] ?? $payload['citizen_feedback'] ?? [];
            $row['parsed_payload'] = $payload;
            $row['feedback_count'] = max((int)$row['feedback_count'], count($responses), (int)($payload['feedback_count'] ?? 0));
            $items[] = $row;
        }
    }
    return $items;
}"""

for db_path in db_files:
    if os.path.exists(db_path):
        with open(db_path, 'r', encoding='utf-8') as f:
            c = f.read()
        c = re.sub(r"function getPendingPhmsApprovals\(\)\s*\{[\s\S]*?return \$items;\s*\}", new_db_func, c)
        with open(db_path, 'w', encoding='utf-8') as f:
            f.write(c)
        print(f"Updated getPendingPhmsApprovals in {db_path}")

# 2. Update JS files openPhmsDataApprovalSheetModal fetch call & row rendering
for js_path in js_files:
    if not os.path.exists(js_path):
        continue
    with open(js_path, 'r', encoding='utf-8') as f:
        c = f.read()

    # Fix fetch URL to use getApiUrl
    c = c.replace("fetch('API/feedback_api.php?action=phms_pending_approvals')", "fetch(typeof getApiUrl === 'function' ? getApiUrl('API/feedback_api.php?action=phms_pending_approvals') : 'API/feedback_api.php?action=phms_pending_approvals')")

    # Update row template to handle both pending and approved items nicely
    old_row_render = r"return `\s*<tr class=\"hover:bg-slate-50 transition\">[\s\S]*?</tr>\s*`;"
    new_row_render = """const isPending = (String(item.approval_status || '').toLowerCase() === 'pending');
                const statusBadge = isPending 
                    ? '<span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px]"><i class="bi bi-hourglass-split mr-1"></i>PENDING APPROVAL</span>'
                    : '<span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]"><i class="bi bi-check-circle-fill mr-1"></i>APPROVED & INGESTED</span>';

                const actionBtns = isPending 
                    ? `<div class="flex items-center justify-center gap-1.5">
                        <button type="button" onclick="approveSinglePhmsPayload(${qId})" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded text-[11px] transition shadow-2xs">
                            Approve
                        </button>
                        <button type="button" onclick="rejectSinglePhmsPayload(${qId})" class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded text-[11px] transition shadow-2xs">
                            Reject
                        </button>
                       </div>`
                    : `<span class="text-[11px] text-emerald-700 font-bold flex items-center justify-center gap-1"><i class="bi bi-check2-all"></i> Merged into PCMS</span>`;

                return `
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-3 font-mono font-bold text-blue-700">${hId}</td>
                        <td class="p-3 font-bold text-slate-900">${title}</td>
                        <td class="p-3 text-slate-600">${dateStr}</td>
                        <td class="p-3 text-center font-bold text-slate-800">${count} Entries</td>
                        <td class="p-3 text-center">${statusBadge}</td>
                        <td class="p-3 text-center">${actionBtns}</td>
                    </tr>
                `;"""

    c = re.sub(old_row_render, new_row_render, c)

    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(c)
    print(f"Updated JS approval sheet modal in {js_path}")

print("Done updating PHMS ingestion approval sheet logic!")
