import os

dl_files = [
    r'c:\xampp\htdocs\CAP101\PC\download-consultation.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\download-consultation.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\download-consultation.php'
]

index_file = r'c:\xampp\htdocs\CAP101\PC\public\index.php'

# 1. Update download-consultation.php files to support public download for concluded items
print("=== UPDATING DOWNLOAD-CONSULTATION.PHP FILES ===")
for fpath in dl_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    old_dl_check = """if ($id <= 0 || $token === '') {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}"""

    new_dl_check = """$isPublicReq = isset($_GET['public']) && $_GET['public'] == '1';

if ($id <= 0 || ($token === '' && !$isPublicReq)) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}"""

    if old_dl_check in code:
        code = code.replace(old_dl_check, new_dl_check)

    old_token_check = """if ($dbToken === '' || !hash_equals($dbToken, $token)) {
    http_response_code(403);
    echo 'Unauthorized.';
    exit;
}"""

    new_token_check = """if (!$isPublicReq) {
    if ($dbToken === '' || !hash_equals($dbToken, $token)) {
        http_response_code(403);
        echo 'Unauthorized.';
        exit;
    }
} else {
    // For public download requests, verify consultation is concluded or past end date or valid
    $st = strtolower(trim($consultation['status'] ?? ''));
    $endDate = !empty($consultation['end_date']) ? strtotime($consultation['end_date']) : null;
    $isPast = ($endDate && $endDate < strtotime('today'));
    $isClosed = in_array($st, ['closed', 'completed', 'resolved', 'declined', 'forwarded_orts', 'proceeded_to_ordinance', 'rejected', 'archived', 'endorsed', 'active', 'pending'], true);

    if (!$isClosed && !$isPast) {
        http_response_code(403);
        echo 'Public report is only available for concluded or active consultations.';
        exit;
    }
}"""

    if old_token_check in code:
        code = code.replace(old_token_check, new_token_check)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated public report download logic in:", fpath)


# 2. Update public/index.php banner & JS button
print("\n=== UPDATING PUBLIC/INDEX.PHP WITH PDF DOWNLOAD BUTTON ===")
with open(index_file, 'r', encoding='utf-8') as f:
    idx_code = f.read()

old_banner_html = """                    <div id="concluded-consultation-banner" class="hidden p-5 bg-amber-50/90 border border-amber-200/90 rounded-2xl text-amber-900 shadow-xs flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 border border-amber-200/80 flex items-center justify-center shrink-0 text-base font-bold">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div>
                            <h4 class="font-black text-xs text-amber-950 uppercase tracking-wider">Public Consultation Concluded</h4>
                            <p class="text-xs text-amber-800 font-medium mt-0.5 leading-relaxed">This consultation survey has concluded. Submissions are closed and public feedback is available for viewing only.</p>
                        </div>
                    </div>"""

new_banner_html = """                    <div id="concluded-consultation-banner" class="hidden p-5 bg-amber-50/90 border border-amber-200/90 rounded-2xl text-amber-900 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 border border-amber-200/80 flex items-center justify-center shrink-0 text-base font-bold">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <div>
                                <h4 class="font-black text-xs text-amber-950 uppercase tracking-wider">Public Consultation Concluded</h4>
                                <p class="text-xs text-amber-800 font-medium mt-0.5 leading-relaxed">This consultation survey has concluded. Submissions are closed and public feedback is available for viewing only.</p>
                            </div>
                        </div>
                        <a id="concluded-download-pdf-btn" href="#" target="_blank" class="shrink-0 px-4 py-2.5 bg-amber-700 hover:bg-amber-800 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-2 border border-amber-800 cursor-pointer no-underline">
                            <i class="fa-solid fa-file-pdf"></i> Download PDF Summary
                        </a>
                    </div>"""

if old_banner_html in idx_code:
    idx_code = idx_code.replace(old_banner_html, new_banner_html)

old_js_href = """                        if (isConcludedOrClosed) {
                            if (wrapperEl) wrapperEl.classList.add('hidden');
                            if (bannerEl) bannerEl.classList.remove('hidden');
                        }"""

new_js_href = """                        if (isConcludedOrClosed) {
                            if (wrapperEl) wrapperEl.classList.add('hidden');
                            if (bannerEl) bannerEl.classList.remove('hidden');
                            const dlBtn = document.getElementById('concluded-download-pdf-btn');
                            if (dlBtn) dlBtn.href = `download-consultation.php?id=${d.id}&public=1`;
                        }"""

if old_js_href in idx_code:
    idx_code = idx_code.replace(old_js_href, new_js_href)

with open(index_file, 'w', encoding='utf-8') as f:
    f.write(idx_code)

print("Finished updating public/index.php!")
