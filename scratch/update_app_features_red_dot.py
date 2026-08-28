import os

app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

old_render_start = """    let hearings = Array.isArray(AppData.phmsFeedback) ? [...AppData.phmsFeedback] : [];"""

new_render_start = """    let hearings = Array.isArray(AppData.phmsFeedback) ? [...AppData.phmsFeedback] : [];

    // Filter out unapproved / pending items from main table (pending items require admin approval in Ingestion Approval Sheet)
    hearings = hearings.filter(h => String(h.approval_status || 'approved').toLowerCase() === 'approved');"""

old_empty_msg = """        const errDetail = window._phms_last_fetch_error ? escapeHtml(window._phms_last_fetch_error) : 'Please ensure the PHMS server is running or click "Sync PHMS Data" to refresh.';"""

new_empty_msg = """        const errDetail = window._phms_last_fetch_error ? escapeHtml(window._phms_last_fetch_error) : 'No approved PHMS citizen hearing feedback available. Incoming pending packages can be reviewed & approved in the "Ingestion Approval Sheet".';"""

old_row_rendering = """        const approvalStatus = (h.approval_status || 'approved').toLowerCase();
        let statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-white uppercase tracking-wider inline-flex items-center gap-1"><i class="bi bi-check-circle-fill text-emerald-400 text-[10px]"></i> COMPLETED</span>';
        if (approvalStatus === 'pending' || status === 'pending_approval') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-white uppercase tracking-wider inline-flex items-center gap-1" title="Data package is awaiting admin approval in Ingestion Approval Sheet"><i class="bi bi-hourglass-split text-white text-[10px]"></i> PENDING APPROVAL</span>';
        } else if (approvalStatus === 'rejected' || status === 'rejected') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white uppercase tracking-wider inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-white text-[10px]"></i> REJECTED</span>';
        } else if (status === 'active' || status === 'open') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wider">ACTIVE</span>';
        }

        return `
            <tr class="border-b border-gray-100 hover:bg-blue-50/60 transition cursor-pointer select-none" style="cursor: pointer !important; pointer-events: auto !important;">
                <td class="px-4 py-3.5">
                    <div class="font-bold text-gray-900 text-xs leading-snug">${title}</div>
                    <div class="mt-1">${statusBadge}</div>
                </td>"""

new_row_rendering = """        const approvalStatus = (h.approval_status || 'approved').toLowerCase();
        const isNewlyApproved = Boolean(h.is_newly_approved || Number(h.is_newly_approved) === 1);

        let statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-white uppercase tracking-wider inline-flex items-center gap-1"><i class="bi bi-check-circle-fill text-emerald-400 text-[10px]"></i> COMPLETED</span>';
        if (isNewlyApproved) {
            statusBadge = '<span class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white uppercase tracking-wider inline-flex items-center gap-1.5 shadow-sm" title="Newly Approved Citizen Feedback Package"><span class="w-2 h-2 rounded-full bg-white animate-pulse"></span> NEWLY APPROVED</span>';
        } else if (approvalStatus === 'pending' || status === 'pending_approval') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500 text-white uppercase tracking-wider inline-flex items-center gap-1" title="Data package is awaiting admin approval in Ingestion Approval Sheet"><i class="bi bi-hourglass-split text-white text-[10px]"></i> PENDING APPROVAL</span>';
        } else if (approvalStatus === 'rejected' || status === 'rejected') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white uppercase tracking-wider inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-white text-[10px]"></i> REJECTED</span>';
        } else if (status === 'active' || status === 'open') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wider">ACTIVE</span>';
        }

        const redDotIndicator = isNewlyApproved ? '<span class="inline-flex items-center justify-center w-3 h-3 rounded-full bg-rose-600 shadow-md animate-ping mr-2" title="Newly Approved Feedback Package"></span><span class="inline-flex items-center justify-center w-2.5 h-2.5 rounded-full bg-rose-600 shadow-sm mr-1.5" title="Newly Approved Feedback Package"></span>' : '';

        return `
            <tr class="border-b border-gray-100 ${isNewlyApproved ? 'bg-rose-50/40 hover:bg-rose-100/50 border-l-4 border-l-rose-500' : 'hover:bg-blue-50/60'} transition cursor-pointer select-none" style="cursor: pointer !important; pointer-events: auto !important;">
                <td class="px-4 py-3.5">
                    <div class="font-bold text-gray-900 text-xs leading-snug flex items-center">${redDotIndicator}<span>${title}</span></div>
                    <div class="mt-1">${statusBadge}</div>
                </td>"""

for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if "hearings = hearings.filter(h => String(h.approval_status || 'approved').toLowerCase() === 'approved');" not in code:
        code = code.replace(old_render_start, new_render_start)

    code = code.replace(old_empty_msg, new_empty_msg)
    code = code.replace(old_row_rendering, new_row_rendering)

    # Clear red dot when clicking view feedback modal
    old_modal_start = "window.pfpShowPhmsDetailModal = function (hearingId) {"
    new_modal_start = """window.pfpShowPhmsDetailModal = function (hearingId) {
    if (hearingId) {
        fetch(`API/feedback_api.php?action=phms_clear_newly_approved&hearing_id=${encodeURIComponent(hearingId)}`, { method: 'POST' }).catch(() => {});
        if (Array.isArray(window.AppData?.phmsFeedback)) {
            const match = window.AppData.phmsFeedback.find(h => String(h.hearing_id || h.phms_hearing_id || h.queue_id) === String(hearingId));
            if (match) match.is_newly_approved = 0;
            if (typeof pfpRenderPhmsTable === 'function') setTimeout(pfpRenderPhmsTable, 300);
        }
    }"""

    if "phms_clear_newly_approved" not in code and old_modal_start in code:
        code = code.replace(old_modal_start, new_modal_start)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated app-features.js in:", fpath)

print("Finished updating app-features.js files!")
