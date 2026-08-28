import os

index_file = r'c:\xampp\htdocs\CAP101\PC\public\index.php'
api_file = r'c:\xampp\htdocs\CAP101\PC\API\consultation_feedback.php'

# 1. Update public/index.php
with open(index_file, 'r', encoding='utf-8') as f:
    code = f.read()

# Replace HTML submission area with wrapper & concluded banner
old_html_form = """                <div class="pt-6 border-t border-slate-100">
                    <h4 class="text-sm font-bold text-slate-900 mb-3">Submit Your Voice & Rating</h4>
                    <form id="feedback-form" onsubmit="handleFeedbackSubmit(event)" class="space-y-4">
                        <input type="hidden" id="modal-consultation-id" name="consultation_id" value="">

                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-700">Rating:</span>
                            <div class="star-rating flex items-center gap-1 text-slate-300 text-lg" id="star-rating-picker">
                                <i class="fa-solid fa-star active" data-rating="1"></i>
                                <i class="fa-solid fa-star active" data-rating="2"></i>
                                <i class="fa-solid fa-star active" data-rating="3"></i>
                                <i class="fa-solid fa-star active" data-rating="4"></i>
                                <i class="fa-solid fa-star active" data-rating="5"></i>
                            </div>
                            <input type="hidden" id="feedback-rating" name="rating" value="5">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Feedback Type</label>
                                <select id="feedback-category" name="category" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300">
                                    <option value="suggestion">Suggestion / Improvement</option>
                                    <option value="concern">Concern / Objection</option>
                                    <option value="question">Inquiry / Question</option>
                                    <option value="support">Full Support</option>
                                </select>
                            </div>
                            <?php if (!$is_logged_in): ?>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Your Name</label>
                                <input type="text" id="feedback-name" name="guest_name" placeholder="Citizen Name" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300">
                            </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Your Detailed Feedback</label>
                            <textarea id="feedback-message" name="message" rows="3" required placeholder="State your recommendations or comments regarding this consultation topic..." class="w-full px-4 py-2.5 text-xs rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue outline-none resize-none"></textarea>
                        </div>

                        <button type="submit" class="w-full bg-valenzuela-blue hover:bg-blue-800 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors shadow-sm">
                            Submit Feedback & Voice
                        </button>
                    </form>
                </div>"""

new_html_form = """                <div class="pt-6 border-t border-slate-100">
                    <!-- Concluded / Closed Consultation Banner -->
                    <div id="concluded-consultation-banner" class="hidden p-5 bg-amber-50/90 border border-amber-200/90 rounded-2xl text-amber-900 shadow-xs flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-700 border border-amber-200/80 flex items-center justify-center shrink-0 text-base font-bold">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div>
                            <h4 class="font-black text-xs text-amber-950 uppercase tracking-wider">Public Consultation Concluded</h4>
                            <p class="text-xs text-amber-800 font-medium mt-0.5 leading-relaxed">This consultation survey has concluded. Submissions are closed and public feedback is available for viewing only.</p>
                        </div>
                    </div>

                    <!-- Open Feedback Submission Form Wrapper -->
                    <div id="feedback-submission-wrapper">
                        <h4 class="text-sm font-bold text-slate-900 mb-3">Submit Your Voice & Rating</h4>
                        <form id="feedback-form" onsubmit="handleFeedbackSubmit(event)" class="space-y-4">
                            <input type="hidden" id="modal-consultation-id" name="consultation_id" value="">

                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-slate-700">Rating:</span>
                                <div class="star-rating flex items-center gap-1 text-slate-300 text-lg" id="star-rating-picker">
                                    <i class="fa-solid fa-star active" data-rating="1"></i>
                                    <i class="fa-solid fa-star active" data-rating="2"></i>
                                    <i class="fa-solid fa-star active" data-rating="3"></i>
                                    <i class="fa-solid fa-star active" data-rating="4"></i>
                                    <i class="fa-solid fa-star active" data-rating="5"></i>
                                </div>
                                <input type="hidden" id="feedback-rating" name="rating" value="5">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Feedback Type</label>
                                    <select id="feedback-category" name="category" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300">
                                        <option value="suggestion">Suggestion / Improvement</option>
                                        <option value="concern">Concern / Objection</option>
                                        <option value="question">Inquiry / Question</option>
                                        <option value="support">Full Support</option>
                                    </select>
                                </div>
                                <?php if (!$is_logged_in): ?>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Your Name</label>
                                    <input type="text" id="feedback-name" name="guest_name" placeholder="Citizen Name" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300">
                                </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Your Detailed Feedback</label>
                                <textarea id="feedback-message" name="message" rows="3" required placeholder="State your recommendations or comments regarding this consultation topic..." class="w-full px-4 py-2.5 text-xs rounded-xl border border-slate-300 focus:ring-2 focus:ring-valenzuela-blue outline-none resize-none"></textarea>
                            </div>

                            <button type="submit" class="w-full bg-valenzuela-blue hover:bg-blue-800 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors shadow-sm">
                                Submit Feedback & Voice
                            </button>
                        </form>
                    </div>
                </div>"""

if old_html_form in code:
    code = code.replace(old_html_form, new_html_form)
    print("Updated consultation modal HTML form wrapper.")

# Replace JS inside openConsultationModal
old_js_modal = """                        document.getElementById('modal-end-date').textContent = d.end_date ? new Date(d.end_date).toLocaleDateString() : 'N/A';
                        document.getElementById('modal-description').textContent = d.description;"""

new_js_modal = """                        document.getElementById('modal-end-date').textContent = d.end_date ? new Date(d.end_date).toLocaleDateString() : 'N/A';
                        document.getElementById('modal-description').textContent = d.description;

                        // Check if consultation is concluded/closed or past end_date
                        const stClean = String(d.status || '').toLowerCase().trim();
                        let isPastEndDate = false;
                        if (d.end_date) {
                            const endDateVal = new Date(d.end_date);
                            endDateVal.setHours(23, 59, 59, 999);
                            if (endDateVal.getTime() < Date.now()) {
                                isPastEndDate = true;
                            }
                        }

                        const isConcludedOrClosed = isPastEndDate || ['closed', 'completed', 'resolved', 'declined', 'forwarded_orts', 'proceeded_to_ordinance', 'rejected', 'archived', 'endorsed'].includes(stClean);

                        const wrapperEl = document.getElementById('feedback-submission-wrapper');
                        const bannerEl = document.getElementById('concluded-consultation-banner');

                        if (isConcludedOrClosed) {
                            if (wrapperEl) wrapperEl.classList.add('hidden');
                            if (bannerEl) bannerEl.classList.remove('hidden');
                        } else {
                            if (wrapperEl) wrapperEl.classList.remove('hidden');
                            if (bannerEl) bannerEl.classList.add('hidden');
                        }"""

if old_js_modal in code:
    code = code.replace(old_js_modal, new_js_modal)
    print("Updated openConsultationModal JS to evaluate concluded status & past end_date.")

# Update PHP backend check in public/index.php
old_backend_check = """        if ($consultation_id <= 0 || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Consultation ID and feedback message are required.']);
            exit;
        }"""

new_backend_check = """        if ($consultation_id <= 0 || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Consultation ID and feedback message are required.']);
            exit;
        }

        // Verify if consultation is concluded or past end date
        $statusCheck = $conn->query("SELECT status, end_date, type FROM consultations WHERE id = $consultation_id LIMIT 1");
        $cRow = $statusCheck ? $statusCheck->fetch_assoc() : null;
        if ($cRow) {
            $stClean = strtolower(trim($cRow['status'] ?? ''));
            $endDate = !empty($cRow['end_date']) ? strtotime($cRow['end_date']) : null;
            $isPastEnd = ($endDate && $endDate < strtotime('today'));
            $isClosed = in_array($stClean, ['closed', 'completed', 'resolved', 'declined', 'forwarded_orts', 'proceeded_to_ordinance', 'rejected', 'archived', 'endorsed'], true);

            if ($isPastEnd || $isClosed) {
                echo json_encode(['success' => false, 'message' => 'This public consultation has concluded and is closed for new feedback submissions.']);
                exit;
            }
        }"""

if old_backend_check in code:
    code = code.replace(old_backend_check, new_backend_check)
    print("Updated public/index.php POST handler with concluded check.")

with open(index_file, 'w', encoding='utf-8') as f:
    f.write(code)

print("Successfully updated public/index.php!")
