(function () {
    function sbEscapeHtml(input) {
        return String(input ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function sbNotify(message, type) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type || 'info');
        } else {
            alert(message);
        }
    }

    window.openPublicSurveysPage = function () {
        // Use direct navigation instead of window.open to avoid popup blockers.
        window.location.href = '../system-template-full.php#consultation-management';
    };

    function sbStatusBadge(status) {
        const s = String(status || '').toLowerCase();
        if (s === 'active' || s === 'published') return '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">published</span>';
        if (s === 'draft') return '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">draft</span>';
        if (s === 'closed') return '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Closed</span>';
        if (s === 'archived') return '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">Archived</span>';
        return `<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">${sbEscapeHtml(status || 'N/A')}</span>`;
    }

    let sbQuestionCounter = 0;
    let sbSurveysCache = [];

    function sbConsultationOptions() {
        const list = (window.AppData && Array.isArray(window.AppData.consultations)) ? window.AppData.consultations : [];
        return list.map(c => `<option value="${Number(c.id)}">${sbEscapeHtml(c.title || 'Untitled Consultation')}</option>`).join('');
    }

    function sbRenderStats(rows) {
        const total = rows.length;
        const active = rows.filter(r => String(r.status).toLowerCase() === 'active').length;
        const draft = rows.filter(r => String(r.status).toLowerCase() === 'draft').length;
        const closed = rows.filter(r => String(r.status).toLowerCase() === 'closed').length;
        const responses = rows.reduce((sum, r) => sum + Number(r.response_count || 0), 0);
        const questions = rows.reduce((sum, r) => sum + Number(r.question_count || 0), 0);

        const set = (id, v) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(v);
        };
        set('sb-stat-total', total);
        set('sb-stat-active', active);
        set('sb-stat-draft', draft);
        set('sb-stat-closed', closed);
        set('sb-stat-responses', responses);
        set('sb-stat-questions', questions);
    }

    function sbQuestionCard(index, seed) {
        const q = seed || {};
        const type = q.question_type || 'rating';
        const required = !!q.is_required;
        const options = Array.isArray(q.options) ? q.options : [];
        const optionsText = options.map(o => (typeof o === 'string' ? o : (o.option_text || ''))).join(',');
        return `
            <div class="border border-gray-200 rounded-lg p-3 bg-white" data-sb-question="${index}">
                <div class="grid grid-cols-1 md:grid-cols-8 gap-2">
                    <div class="md:col-span-4">
                        <label class="block text-sm text-gray-700 mb-1">Question text</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg sb-question-text" value="${sbEscapeHtml(q.question_text || '')}" placeholder="How satisfied are you with this survey?">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm text-gray-700 mb-1">Type</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg sb-question-type" onchange="sbToggleQuestionOptions(${index})">
                            <option value="rating" ${type === 'rating' ? 'selected' : ''}>rating</option>
                            <option value="single_choice" ${type === 'single_choice' ? 'selected' : ''}>single_choice</option>
                            <option value="multiple_choice" ${type === 'multiple_choice' ? 'selected' : ''}>multiple_choice</option>
                            <option value="text" ${type === 'text' ? 'selected' : ''}>text</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 sb-options-wrap ${type === 'text' ? 'hidden' : ''}">
                        <label class="block text-sm text-gray-700 mb-1">Options (comma-separated)</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg sb-question-options" value="${sbEscapeHtml(optionsText)}" placeholder="Yes,No">
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" class="sb-question-required" ${required ? 'checked' : ''}>
                        Required
                    </label>
                    <button type="button" class="px-3 py-1.5 text-sm border border-red-300 text-red-600 rounded hover:bg-red-50" onclick="sbRemoveQuestion(${index})">Remove</button>
                </div>
            </div>
        `;
    }

    window.sbAddQuestion = function (seed) {
        sbQuestionCounter += 1;
        const container = document.getElementById('sb-questions-container');
        if (!container) return;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = sbQuestionCard(sbQuestionCounter, seed);
        container.appendChild(wrapper.firstElementChild);
    };

    window.sbRemoveQuestion = function (index) {
        const node = document.querySelector(`[data-sb-question="${index}"]`);
        if (node) node.remove();
    };

    window.sbToggleQuestionOptions = function (index) {
        const root = document.querySelector(`[data-sb-question="${index}"]`);
        if (!root) return;
        const typeSel = root.querySelector('.sb-question-type');
        const wrap = root.querySelector('.sb-options-wrap');
        if (!typeSel || !wrap) return;
        if (typeSel.value === 'text') {
            wrap.classList.add('hidden');
        } else {
            wrap.classList.remove('hidden');
        }
    };

    function sbCollectQuestions() {
        const nodes = Array.from(document.querySelectorAll('[data-sb-question]'));
        const out = [];
        for (const node of nodes) {
            const text = String(node.querySelector('.sb-question-text')?.value || '').trim();
            const type = String(node.querySelector('.sb-question-type')?.value || 'single_choice').trim();
            const required = !!node.querySelector('.sb-question-required')?.checked;
            const rawOptions = String(node.querySelector('.sb-question-options')?.value || '');
            const options = rawOptions.split(',').map(s => s.trim()).filter(Boolean);
            if (!text) continue;
            out.push({
                question_text: text,
                question_type: type,
                is_required: required ? 1 : 0,
                options: options
            });
        }
        return out;
    }

    window.sbCreateSurvey = async function () {
        const title = String(document.getElementById('sb-title')?.value || '').trim();
        const description = String(document.getElementById('sb-description')?.value || '').trim();
        let status = String(document.getElementById('sb-status')?.value || 'draft').trim().toLowerCase();
        if (status === 'published') status = 'active';
        const consultationRaw = String(document.getElementById('sb-consultation-id')?.value || '').trim();
        const consultation_id = consultationRaw ? Number(consultationRaw) : null;
        const starts_at = String(document.getElementById('sb-starts-at')?.value || '').trim();
        const ends_at = String(document.getElementById('sb-ends-at')?.value || '').trim();
        const allow_anonymous = !!document.getElementById('sb-allow-anonymous')?.checked;
        const allow_multiple_per_email = !!document.getElementById('sb-allow-multiple')?.checked;
        const questions = sbCollectQuestions();

        if (!title) {
            sbNotify('Survey title is required.', 'error');
            return;
        }
        if (!questions.length) {
            sbNotify('Please add at least one valid question.', 'error');
            return;
        }

        const payload = {
            title,
            description,
            status,
            consultation_id,
            starts_at: starts_at || null,
            ends_at: ends_at || null,
            allow_anonymous: allow_anonymous ? 1 : 0,
            allow_multiple_per_email: allow_multiple_per_email ? 1 : 0,
            questions
        };
        try {
            const res = await fetch('API/surveys_api.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
            }
            const newId = Number(data.id || 0);
            if (newId <= 0) {
                throw new Error('Survey was not saved. Please check required fields and try again.');
            }
            sbNotify(`Survey created successfully. ID: ${newId}`, 'success');
            document.getElementById('sb-title').value = '';
            document.getElementById('sb-description').value = '';
            document.getElementById('sb-status').value = 'draft';
            const cId = document.getElementById('sb-consultation-id');
            if (cId) cId.value = '';
            const sAt = document.getElementById('sb-starts-at');
            if (sAt) sAt.value = '';
            const eAt = document.getElementById('sb-ends-at');
            if (eAt) eAt.value = '';
            const aAnon = document.getElementById('sb-allow-anonymous');
            if (aAnon) aAnon.checked = true;
            const aMulti = document.getElementById('sb-allow-multiple');
            if (aMulti) aMulti.checked = false;
            const qContainer = document.getElementById('sb-questions-container');
            if (qContainer) qContainer.innerHTML = '';
            sbQuestionCounter = 0;
            window.sbAddQuestion();
            await window.sbLoadSurveys();
        } catch (e) {
            sbNotify(e && e.message ? String(e.message) : 'Failed to create survey.', 'error');
        }
    };

    function sbRenderList(rows) {
        const tbody = document.getElementById('sb-table-body');
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No surveys yet.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(s => `
            <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-700">${Number(s.id || 0)}</td>
                <td class="px-4 py-3 font-semibold text-gray-900">${sbEscapeHtml(s.title || '')}</td>
                <td class="px-4 py-3">${sbStatusBadge(s.status)}</td>
                <td class="px-4 py-3 text-gray-700">${Number(s.question_count || 0)}</td>
                <td class="px-4 py-3 text-gray-700">${Number(s.response_count || 0)}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <button onclick="sbViewResults(${Number(s.id)})" class="px-2 py-1 text-xs rounded border border-blue-300 text-blue-600 hover:bg-blue-50">Results</button>
                        <button onclick="sbSetStatus(${Number(s.id)}, 'published')" class="px-2 py-1 text-xs rounded border border-yellow-300 text-yellow-600 hover:bg-yellow-50">Publish</button>
                        <button onclick="sbSetStatus(${Number(s.id)}, 'closed')" class="px-2 py-1 text-xs rounded border border-gray-400 text-gray-700 hover:bg-gray-50">Close</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    window.sbSetStatus = async function (id, status) {
        try {
            const res = await fetch('API/surveys_api.php?action=update_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ id: Number(id), status: String(status || '').toLowerCase() })
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
            }
            sbNotify('Survey status updated.', 'success');
            await window.sbLoadSurveys();
        } catch (e) {
            sbNotify(e && e.message ? String(e.message) : 'Failed to update survey status.', 'error');
        }
    };

    window.sbLoadSurveys = async function () {
        try {
            const res = await fetch('API/surveys_api.php?action=list&limit=200&offset=0', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success || !Array.isArray(data.data)) {
                throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
            }
            sbSurveysCache = data.data;
            sbRenderStats(sbSurveysCache);
            sbRenderList(sbSurveysCache);
        } catch (e) {
            sbNotify(e && e.message ? String(e.message) : 'Failed to load surveys.', 'error');
        }
    };

    window.sbDeleteSurvey = async function (id) {
        if (!confirm('Delete this survey? This will also delete its questions and responses.')) return;
        try {
            const res = await fetch('API/surveys_api.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ id: Number(id) })
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
            }
            sbNotify('Survey deleted.', 'success');
            await window.sbLoadSurveys();
            const detail = document.getElementById('sb-detail-panel');
            if (detail) detail.classList.add('hidden');
            const result = document.getElementById('sb-results-panel');
            if (result) result.classList.add('hidden');
        } catch (e) {
            sbNotify(e && e.message ? String(e.message) : 'Failed to delete survey.', 'error');
        }
    };

    window.sbViewSurvey = async function (id) {
        try {
            const res = await fetch(`API/surveys_api.php?action=details&id=${Number(id)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
            }
            const panel = document.getElementById('sb-detail-panel');
            const body = document.getElementById('sb-detail-body');
            if (!panel || !body) return;

            const survey = data.data.survey || {};
            const questions = Array.isArray(data.data.questions) ? data.data.questions : [];

            body.innerHTML = `
                <div class="mb-3">
                    <h4 class="text-lg font-bold text-gray-900">${sbEscapeHtml(survey.title || '')}</h4>
                    <p class="text-sm text-gray-600">${sbEscapeHtml(survey.description || 'No description')}</p>
                    <div class="mt-1">${sbStatusBadge(survey.status)}</div>
                </div>
                <div class="space-y-3">
                    ${questions.map((q, idx) => `
                        <div class="border rounded-lg p-3 bg-gray-50">
                            <div class="font-semibold text-gray-900">${idx + 1}. ${sbEscapeHtml(q.question_text || '')}</div>
                            <div class="text-xs text-gray-500 mt-1">Type: ${sbEscapeHtml(q.question_type || '')} | Required: ${Number(q.is_required) ? 'Yes' : 'No'}</div>
                            ${(q.options && q.options.length) ? `
                                <ul class="mt-2 text-sm text-gray-700 list-disc pl-5">
                                    ${q.options.map(o => `<li>${sbEscapeHtml(o.option_text || '')}</li>`).join('')}
                                </ul>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            `;
            panel.classList.remove('hidden');
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            sbNotify(e && e.message ? String(e.message) : 'Failed to load survey details.', 'error');
        }
    };

    window.sbViewResults = async function (id) {
        try {
            const res = await fetch(`API/surveys_api.php?action=results&id=${Number(id)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) {
                throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
            }

            const panel = document.getElementById('sb-results-panel');
            const body = document.getElementById('sb-results-body');
            if (!panel || !body) return;

            const results = data.data || {};
            const questions = Array.isArray(results.questions) ? results.questions : [];

            body.innerHTML = `
                <div class="mb-3">
                    <h4 class="text-lg font-bold text-gray-900">${sbEscapeHtml(results.survey?.title || '')}</h4>
                    <p class="text-sm text-gray-600">Total Responses: <strong>${Number(results.total_responses || 0)}</strong></p>
                </div>
                <div class="space-y-3">
                    ${questions.map((q, idx) => `
                        <div class="border rounded-lg p-3 bg-white">
                            <div class="font-semibold text-gray-900">${idx + 1}. ${sbEscapeHtml(q.question_text || '')}</div>
                            <div class="text-xs text-gray-500 mt-1 mb-2">Type: ${sbEscapeHtml(q.question_type || '')}</div>
                            ${(q.option_counts && q.option_counts.length) ? `
                                <div class="space-y-1">
                                    ${q.option_counts.map(o => `
                                        <div class="flex items-center justify-between text-sm">
                                            <span>${sbEscapeHtml(o.option_text || '')}</span>
                                            <span class="font-semibold">${Number(o.count || 0)}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : `
                                <ul class="list-disc pl-5 text-sm text-gray-700">
                                    ${(q.text_answers || []).map(t => `<li>${sbEscapeHtml(t)}</li>`).join('') || '<li>No text answers yet.</li>'}
                                </ul>
                            `}
                        </div>
                    `).join('')}
                </div>
            `;
            panel.classList.remove('hidden');
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            sbNotify(e && e.message ? String(e.message) : 'Failed to load survey results.', 'error');
        }
    };

    window.renderSurveyBuilder = function () {
        const contentArea = document.getElementById('content-area');
        const pageTitle = document.querySelector('.page-title');
        const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
        if (pageTitle) pageTitle.textContent = 'Survey Builder';
        if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Survey Builder';
        if (!contentArea) return;

        contentArea.innerHTML = `
            <div class="space-y-5">
                <div class="bg-gradient-to-r from-red-600 to-red-700 text-white p-8 rounded-lg shadow">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-5xl md:text-4xl font-extrabold tracking-tight mb-2">Survey Builder</h1>
                            <p class="text-red-100 text-lg">Create, publish, and analyze consultation surveys</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                    <div class="xl:col-span-5 bg-white rounded-lg border border-gray-200 p-5">
                        <h3 class="text-4xl md:text-3xl font-bold text-gray-900 mb-3">Create Survey</h3>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Title</label>
                                <input id="sb-title" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Citizen Satisfaction Survey">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Description</label>
                                <textarea id="sb-description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Consultation ID (optional)</label>
                                    <input id="sb-consultation-id" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Status</label>
                                    <select id="sb-status" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                        <option value="draft">draft</option>
                                        <option value="published">published</option>
                                        <option value="closed">closed</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Starts At</label>
                                    <input id="sb-starts-at" type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Ends At</label>
                                    <input id="sb-ends-at" type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input id="sb-allow-anonymous" type="checkbox" checked>
                                    Allow anonymous responses
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input id="sb-allow-multiple" type="checkbox">
                                    Allow multiple submissions per email
                                </label>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-lg font-semibold text-gray-900">Questions</h4>
                                    <button type="button" onclick="sbAddQuestion()" class="px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">+ Add Question</button>
                                </div>
                                <div id="sb-questions-container" class="space-y-2"></div>
                            </div>

                            <button onclick="sbCreateSurvey()" class="w-full px-4 py-2.5 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Create Survey</button>
                        </div>
                    </div>

                    <div class="xl:col-span-7 space-y-4">
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-3xl md:text-2xl font-bold text-gray-900">Survey Registry</h3>
                                <button onclick="sbLoadSurveys()" class="px-3 py-1.5 rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Refresh</button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="border-b border-gray-200">
                                        <tr>
                                            <th class="px-2 py-2 text-left font-semibold text-gray-900">ID</th>
                                            <th class="px-2 py-2 text-left font-semibold text-gray-900">Title</th>
                                            <th class="px-2 py-2 text-left font-semibold text-gray-900">Status</th>
                                            <th class="px-2 py-2 text-left font-semibold text-gray-900">Questions</th>
                                            <th class="px-2 py-2 text-left font-semibold text-gray-900">Responses</th>
                                            <th class="px-2 py-2 text-left font-semibold text-gray-900">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sb-table-body"></tbody>
                                </table>
                            </div>
                        </div>

                        <div id="sb-results-panel" class="bg-white rounded-lg border border-gray-200 p-4">
                            <h3 class="text-3xl md:text-2xl font-bold text-gray-900 mb-2">Survey Results</h3>
                            <div id="sb-results-body" class="text-gray-500">Select a survey and click "Results".</div>
                        </div>

                        <div id="sb-detail-panel" class="hidden bg-white rounded-lg border border-gray-200 p-4">
                            <h3 class="text-lg font-bold text-gray-900 mb-3">Survey Details</h3>
                            <div id="sb-detail-body"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        sbQuestionCounter = 0;
        window.sbAddQuestion({
            question_text: 'How satisfied are you with this survey?',
            question_type: 'rating',
            is_required: 1,
            options: ['Yes', 'No']
        });
        window.sbLoadSurveys();
    };
})();
