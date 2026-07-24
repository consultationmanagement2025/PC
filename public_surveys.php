<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Surveys - PCMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto p-5 md:p-8 space-y-5">
        <div class="bg-gradient-to-r from-red-700 to-red-600 rounded-xl text-white p-6 md:p-8 shadow">
            <h1 class="text-3xl md:text-4xl font-extrabold">Public Surveys</h1>
            <p class="mt-2 text-red-100">Participate in active City of Valenzuela consultation surveys.</p>
        </div>

        <div id="statsRow" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Active Surveys</div>
                <div id="statActive" class="text-2xl font-bold text-gray-900">0</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Total Responses</div>
                <div id="statResponses" class="text-2xl font-bold text-gray-900">0</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Closing Soon</div>
                <div id="statClosingSoon" class="text-2xl font-bold text-amber-600">0</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Questions</div>
                <div id="statQuestions" class="text-2xl font-bold text-gray-900">0</div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <div class="xl:col-span-4 bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-2xl font-bold text-gray-900">Active Surveys</h2>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 gap-2">
                    <input id="searchInput" type="text" class="px-3 py-2 border border-gray-300 rounded-lg" placeholder="Search title or consultation">
                    <select id="sortSelect" class="px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="latest">Latest First</option>
                        <option value="responses">Most Responses</option>
                        <option value="ending">Ending Soon</option>
                        <option value="title">Title A-Z</option>
                    </select>
                </div>
                <div id="surveyList" class="mt-3 space-y-2"></div>
            </div>

            <div class="xl:col-span-8 bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-2xl font-bold text-gray-900">Survey Form</h2>
                <div id="formHost" class="mt-3 text-gray-600">Select a survey to answer.</div>
            </div>
        </div>
    </div>

    <script>
        let surveyListCache = [];
        let selectedSurveyId = null;
        let selectedSurveyDetails = null;

        const el = (id) => document.getElementById(id);
        const esc = (v) => String(v ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
        const cssEsc = (v) => (window.CSS && typeof window.CSS.escape === 'function')
            ? window.CSS.escape(String(v))
            : String(v).replace(/["\\]/g, '\\$&');

        function notify(msg, type) {
            if (typeof window.showNotification === 'function') {
                window.showNotification(msg, type || 'info');
            } else {
                alert(msg);
            }
        }

        function draftKey(surveyId) {
            return `pcms_survey_draft_${Number(surveyId)}`;
        }

        function parseDate(s) {
            if (!s) return null;
            const d = new Date(s);
            return Number.isNaN(d.getTime()) ? null : d;
        }

        function daysUntil(dateText) {
            const d = parseDate(dateText);
            if (!d) return null;
            const now = new Date();
            const ms = d.getTime() - now.getTime();
            return Math.ceil(ms / (1000 * 60 * 60 * 24));
        }

        function statusChip(item) {
            const days = daysUntil(item.ends_at);
            if (days !== null && days < 0) {
                return '<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Ended</span>';
            }
            if (days !== null && days <= 3) {
                return '<span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Closing Soon</span>';
            }
            return '<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Active</span>';
        }

        function updateStats(rows) {
            const active = rows.length;
            const responses = rows.reduce((sum, r) => sum + Number(r.response_count || 0), 0);
            const questions = rows.reduce((sum, r) => sum + Number(r.question_count || 0), 0);
            const closingSoon = rows.filter(r => {
                const d = daysUntil(r.ends_at);
                return d !== null && d >= 0 && d <= 3;
            }).length;

            el('statActive').textContent = String(active);
            el('statResponses').textContent = String(responses);
            el('statQuestions').textContent = String(questions);
            el('statClosingSoon').textContent = String(closingSoon);
        }

        function applyListFilters() {
            const q = String(el('searchInput').value || '').trim().toLowerCase();
            const sort = String(el('sortSelect').value || 'latest');
            let rows = surveyListCache.filter(r => {
                if (!q) return true;
                return String(r.title || '').toLowerCase().includes(q) ||
                    String(r.consultation_title || '').toLowerCase().includes(q);
            });

            rows.sort((a, b) => {
                if (sort === 'responses') return Number(b.response_count || 0) - Number(a.response_count || 0);
                if (sort === 'title') return String(a.title || '').localeCompare(String(b.title || ''));
                if (sort === 'ending') {
                    const ad = daysUntil(a.ends_at);
                    const bd = daysUntil(b.ends_at);
                    if (ad === null && bd === null) return 0;
                    if (ad === null) return 1;
                    if (bd === null) return -1;
                    return ad - bd;
                }
                return Number(b.id || 0) - Number(a.id || 0);
            });

            renderSurveyList(rows);
        }

        function renderSurveyList(rows) {
            const host = el('surveyList');
            if (!rows.length) {
                host.innerHTML = '<div class="text-sm text-gray-500 py-3">No active surveys available.</div>';
                return;
            }

            host.innerHTML = rows.map(item => {
                const id = Number(item.id || 0);
                const activeClass = id === selectedSurveyId ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white';
                return `
                    <button onclick="openSurvey(${id})" class="w-full text-left border ${activeClass} rounded-lg p-3 hover:border-red-300 hover:bg-red-50 transition">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-semibold text-gray-900">${esc(item.title || 'Untitled Survey')}</div>
                            ${statusChip(item)}
                        </div>
                        <div class="text-xs text-gray-600 mt-1">${esc(item.consultation_title || 'General Public Consultation')}</div>
                        <div class="mt-2 text-xs text-gray-500 flex flex-wrap gap-2">
                            <span>Questions: ${Number(item.question_count || 0)}</span>
                            <span>Responses: ${Number(item.response_count || 0)}</span>
                        </div>
                    </button>
                `;
            }).join('');
        }

        async function loadPublicSurveys() {
            try {
                const res = await fetch('API/surveys_api.php?action=public_list', { headers: { 'Accept': 'application/json' } });
                const data = await res.json().catch(() => null);
                if (!res.ok || !data || !data.success || !Array.isArray(data.data)) {
                    throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
                }
                surveyListCache = data.data;
                updateStats(surveyListCache);
                applyListFilters();

                if (surveyListCache.length && (selectedSurveyId === null || !surveyListCache.some(s => Number(s.id) === selectedSurveyId))) {
                    openSurvey(Number(surveyListCache[0].id));
                }
            } catch (e) {
                notify(e && e.message ? String(e.message) : 'Failed to load surveys', 'error');
            }
        }

        function getDraft(surveyId) {
            try {
                const raw = localStorage.getItem(draftKey(surveyId));
                return raw ? JSON.parse(raw) : {};
            } catch (_) {
                return {};
            }
        }

        function saveDraft() {
            if (!selectedSurveyId || !selectedSurveyDetails) return;
            const payload = {
                citizen_name: String(el('citizenName')?.value || ''),
                citizen_email: String(el('citizenEmail')?.value || ''),
                answers: {}
            };
            for (const q of selectedSurveyDetails.questions || []) {
                const qid = Number(q.id);
                if (q.question_type === 'text') {
                    payload.answers[qid] = String(document.querySelector(`[data-q-text="${qid}"]`)?.value || '');
                } else if (q.question_type === 'single_choice' || q.question_type === 'rating') {
                    const checked = document.querySelector(`input[name="q_${qid}"]:checked`);
                    payload.answers[qid] = checked ? String(checked.value) : '';
                } else if (q.question_type === 'multiple_choice') {
                    const values = Array.from(document.querySelectorAll(`input[name="q_${qid}[]"]:checked`)).map(x => x.value);
                    payload.answers[qid] = values;
                }
            }
            localStorage.setItem(draftKey(selectedSurveyId), JSON.stringify(payload));
        }

        function clearDraft() {
            if (!selectedSurveyId) return;
            localStorage.removeItem(draftKey(selectedSurveyId));
            notify('Draft cleared.', 'info');
        }

        function applyDraft(surveyId, details) {
            const d = getDraft(surveyId);
            if (!d || typeof d !== 'object') return;
            if (el('citizenName')) el('citizenName').value = String(d.citizen_name || '');
            if (el('citizenEmail')) el('citizenEmail').value = String(d.citizen_email || '');
            for (const q of details.questions || []) {
                const qid = Number(q.id);
                const val = d.answers ? d.answers[qid] : null;
                if (val === undefined || val === null) continue;
                if (q.question_type === 'text') {
                    const input = document.querySelector(`[data-q-text="${qid}"]`);
                    if (input) input.value = String(val);
                } else if (q.question_type === 'single_choice' || q.question_type === 'rating') {
                    const choice = document.querySelector(`input[name="q_${qid}"][value="${cssEsc(val)}"]`);
                    if (choice) choice.checked = true;
                } else if (q.question_type === 'multiple_choice' && Array.isArray(val)) {
                    for (const item of val) {
                        const choice = document.querySelector(`input[name="q_${qid}[]"][value="${cssEsc(item)}"]`);
                        if (choice) choice.checked = true;
                    }
                }
            }
        }

        function requiredErrorList(details) {
            const missing = [];
            for (const q of details.questions || []) {
                if (!Number(q.is_required)) continue;
                const qid = Number(q.id);
                if (q.question_type === 'text') {
                    const v = String(document.querySelector(`[data-q-text="${qid}"]`)?.value || '').trim();
                    if (!v) missing.push(q.question_text || `Question ${qid}`);
                } else if (q.question_type === 'single_choice' || q.question_type === 'rating') {
                    const checked = document.querySelector(`input[name="q_${qid}"]:checked`);
                    if (!checked) missing.push(q.question_text || `Question ${qid}`);
                } else if (q.question_type === 'multiple_choice') {
                    const checked = document.querySelectorAll(`input[name="q_${qid}[]"]:checked`);
                    if (!checked.length) missing.push(q.question_text || `Question ${qid}`);
                }
            }
            return missing;
        }

        function collectAnswers(details) {
            const answers = [];
            for (const q of details.questions || []) {
                const qid = Number(q.id);
                if (q.question_type === 'text') {
                    const text = String(document.querySelector(`[data-q-text="${qid}"]`)?.value || '').trim();
                    if (text) answers.push({ question_id: qid, answer_text: text });
                } else if (q.question_type === 'single_choice' || q.question_type === 'rating') {
                    const checked = document.querySelector(`input[name="q_${qid}"]:checked`);
                    if (checked) answers.push({ question_id: qid, selected_option_id: Number(checked.value) });
                } else if (q.question_type === 'multiple_choice') {
                    const checked = Array.from(document.querySelectorAll(`input[name="q_${qid}[]"]:checked`));
                    for (const input of checked) {
                        answers.push({ question_id: qid, selected_option_id: Number(input.value) });
                    }
                }
            }
            return answers;
        }

        function renderQuestion(q, idx) {
            const qid = Number(q.id);
            const requiredMark = Number(q.is_required) ? '<span class="text-red-600">*</span>' : '';
            const title = `<div class="font-medium text-gray-900">${idx + 1}. ${esc(q.question_text || '')} ${requiredMark}</div>`;
            if (q.question_type === 'text') {
                return `
                    <div class="border border-gray-200 rounded-lg p-3">
                        ${title}
                        <textarea data-q-text="${qid}" class="mt-2 w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" placeholder="Your answer"></textarea>
                    </div>
                `;
            }
            const options = Array.isArray(q.options) ? q.options : [];
            const multiple = q.question_type === 'multiple_choice';
            const inputType = multiple ? 'checkbox' : 'radio';
            const name = multiple ? `q_${qid}[]` : `q_${qid}`;
            return `
                <div class="border border-gray-200 rounded-lg p-3">
                    ${title}
                    <div class="mt-2 space-y-2">
                        ${options.map(o => `
                            <label class="flex items-center gap-2 text-sm text-gray-800">
                                <input type="${inputType}" name="${name}" value="${Number(o.id)}">
                                <span>${esc(o.option_text || '')}</span>
                            </label>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        function surveyMetaRow(s) {
            const starts = s.starts_at ? new Date(s.starts_at).toLocaleString() : 'No start limit';
            const ends = s.ends_at ? new Date(s.ends_at).toLocaleString() : 'No end limit';
            return `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-600 mt-2">
                    <div>Starts: ${esc(starts)}</div>
                    <div>Ends: ${esc(ends)}</div>
                    <div>Consultation: ${esc(s.consultation_title || 'General')}</div>
                    <div>Anonymous Allowed: ${Number(s.allow_anonymous) ? 'Yes' : 'No'}</div>
                </div>
            `;
        }

        async function openSurvey(id) {
            selectedSurveyId = Number(id);
            try {
                const res = await fetch(`API/surveys_api.php?action=public_details&id=${selectedSurveyId}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json().catch(() => null);
                if (!res.ok || !data || !data.success || !data.data) {
                    throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
                }
                selectedSurveyDetails = data.data;
                renderForm(data.data);
                applyListFilters();
            } catch (e) {
                notify(e && e.message ? String(e.message) : 'Failed to load survey details', 'error');
            }
        }

        function renderForm(details) {
            const s = details.survey || {};
            const questions = Array.isArray(details.questions) ? details.questions : [];
            const host = el('formHost');
            host.innerHTML = `
                <div class="space-y-4">
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="text-lg font-bold text-gray-900">${esc(s.title || 'Untitled Survey')}</div>
                        <div class="text-sm text-gray-700 mt-1">${esc(s.description || 'No description')}</div>
                        ${surveyMetaRow(s)}
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Name (optional)</label>
                            <input id="citizenName" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Your name">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Email ${Number(s.allow_anonymous) ? '(optional)' : '<span class="text-red-600">*</span>'}</label>
                            <input id="citizenEmail" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="you@example.com">
                        </div>
                    </div>

                    <div class="space-y-3">
                        ${questions.map((q, idx) => renderQuestion(q, idx)).join('')}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button onclick="submitSurveyResponse()" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Submit Response</button>
                        <button onclick="saveDraft(); notify('Draft saved.', 'success');" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Save Draft</button>
                        <button onclick="clearDraft()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Clear Draft</button>
                    </div>

                    <div id="submitStatus" class="text-sm"></div>
                </div>
            `;

            host.querySelectorAll('input, textarea, select').forEach(node => {
                node.addEventListener('input', saveDraft);
                node.addEventListener('change', saveDraft);
            });
            applyDraft(selectedSurveyId, details);
        }

        async function submitSurveyResponse() {
            if (!selectedSurveyDetails || !selectedSurveyId) return;
            const missing = requiredErrorList(selectedSurveyDetails);
            if (missing.length) {
                notify(`Please answer required questions: ${missing.slice(0, 2).join(', ')}${missing.length > 2 ? '...' : ''}`, 'error');
                return;
            }

            const survey = selectedSurveyDetails.survey || {};
            const citizen_name = String(el('citizenName')?.value || '').trim();
            const citizen_email = String(el('citizenEmail')?.value || '').trim();

            if (!Number(survey.allow_anonymous) && !citizen_email) {
                notify('Email is required for this survey.', 'error');
                return;
            }

            const answers = collectAnswers(selectedSurveyDetails);
            if (!answers.length) {
                notify('Please answer at least one question before submitting.', 'error');
                return;
            }

            try {
                const payload = { survey_id: selectedSurveyId, citizen_name, citizen_email, answers };
                const res = await fetch('API/surveys_api.php?action=submit_response', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json().catch(() => null);
                if (!res.ok || !data || !data.success) {
                    throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
                }
                localStorage.removeItem(draftKey(selectedSurveyId));
                const refId = Number(data.response_id || 0);
                el('submitStatus').innerHTML = `<span class="text-green-700">Submitted successfully. Reference ID: <strong>${refId}</strong></span>`;
                notify('Thank you. Your response was submitted.', 'success');
                await loadPublicSurveys();
                await openSurvey(selectedSurveyId);
            } catch (e) {
                notify(e && e.message ? String(e.message) : 'Failed to submit response', 'error');
            }
        }

        window.openSurvey = openSurvey;
        window.submitSurveyResponse = submitSurveyResponse;
        window.clearDraft = clearDraft;
        window.saveDraft = saveDraft;

        el('searchInput').addEventListener('input', applyListFilters);
        el('sortSelect').addEventListener('change', applyListFilters);
        loadPublicSurveys();
    </script>
</body>
</html>
