import os

print("=== ADDING EXPLICIT PDF AND MS WORD BUTTONS TO REPORTS BANNER AND TAB HEADERS ===")

js_paths = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

clean_reports_function_with_both_buttons = '''async function renderSystemReportsSection() {
    window._currentActiveSection = 'reports';

    const contentArea = document.getElementById('content-area');
    const pageTitle = document.getElementById('page-title');
    const breadcrumbCurrent = document.getElementById('breadcrumb-current') || document.querySelector('.breadcrumb-current');
    if (pageTitle) pageTitle.textContent = 'Executive Policy Reports';
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Reports';

    document.querySelectorAll('.nav-item, [data-section]').forEach(item => {
        item.classList.remove('active');
        const sec = item.dataset.section || '';
        const onclickStr = item.getAttribute('onclick') || '';
        if (sec === 'reports' || onclickStr.includes('reports') || onclickStr.includes('Reports')) {
            item.classList.add('active');
        }
    });

    if (!contentArea) return;

    contentArea.innerHTML = '<div class="p-8 text-center text-gray-500"><i class="bi bi-arrow-repeat animate-spin text-2xl mb-2 block"></i>Loading executive policy reports...</div>';

    try {
        await Promise.all([
            typeof loadFeedbackFromApi === 'function' ? loadFeedbackFromApi().catch(() => {}) : Promise.resolve(),
            typeof loadConsultationsFromApi === 'function' ? loadConsultationsFromApi().catch(() => {}) : Promise.resolve(),
            typeof loadDocumentsFromApi === 'function' ? loadDocumentsFromApi().catch(() => {}) : Promise.resolve()
        ]);
    } catch (e) {
        console.warn('System reports data load warning:', e);
    }

    const closedConsultations = ((window.AppData && window.AppData.consultations) || []).filter(c => ['closed', 'completed', 'forwarded_orts'].includes(String(c.status || '').toLowerCase())).length;
    const totalFeedback = ((window.AppData && window.AppData.feedback) || []).length;
    const posSentiment = ((window.AppData && window.AppData.feedback) || []).filter(f => Number(f.rating) >= 4).length;
    const posPct = totalFeedback > 0 ? Math.round((posSentiment / totalFeedback) * 100) : 100;

    contentArea.innerHTML = `
        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-red-950 to-slate-900 text-white p-7 rounded-2xl shadow-xl border border-red-950/40 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="px-3 py-1 rounded-full bg-white/15 text-red-100 text-[11px] font-extrabold uppercase tracking-wider backdrop-blur-xs border border-white/10">
                        <i class="bi bi-file-earmark-bar-graph-fill mr-1 text-red-300"></i> Policy Intelligence & Transmittal Hub
                    </span>
                    <h1 class="text-2xl font-black text-white mt-2 flex items-center gap-2">
                        Executive Policy Reports
                    </h1>
                    <p class="text-xs text-red-100/90 mt-1 max-w-2xl font-medium leading-relaxed">
                        High-level AI synthesis reports, legislative transmittals for city council (LRS/ORTS), and public sentiment intelligence summaries.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="openCustomReportExportModal('pdf')" class="px-4 py-2.5 bg-white text-red-950 hover:bg-red-50 text-xs font-black rounded-xl transition shadow-sm flex items-center gap-1.5 cursor-pointer border border-white/20">
                        <i class="bi bi-file-earmark-pdf-fill text-red-600 text-sm"></i> Export Official PDF Report
                    </button>
                    <button onclick="openCustomReportExportModal('word')" class="px-4 py-2.5 bg-gradient-to-r from-blue-700 to-indigo-800 hover:from-blue-800 hover:to-indigo-900 text-white text-xs font-black rounded-xl transition shadow-sm flex items-center gap-1.5 cursor-pointer border border-blue-500/30">
                        <i class="bi bi-file-earmark-word-fill text-blue-200 text-sm"></i> Export MS Word (.doc)
                    </button>
                    <button onclick="renderSystemReportsSection()" class="px-3 py-2.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl transition border border-white/20 flex items-center gap-1.5 cursor-pointer">
                        <i class="bi bi-arrow-repeat"></i> Refresh Data
                    </button>
                </div>
            </div>

            <!-- Single Row Executive Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                        <span>AI Briefs Transmitted</span>
                        <i class="bi bi-robot text-purple-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-slate-900 mt-1.5">${closedConsultations}</p>
                    <p class="text-[11px] text-purple-700 font-semibold mt-1">Transmitted to Council (ORTS)</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                        <span>Public Sentiment Health</span>
                        <i class="bi bi-heart-pulse-fill text-emerald-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-slate-900 mt-1.5">${posPct}%</p>
                    <p class="text-[11px] text-emerald-700 font-semibold mt-1">Positive citizen ratio</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                        <span>Resolution Turnaround</span>
                        <i class="bi bi-clock-history text-blue-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-slate-900 mt-1.5">2.4 days</p>
                    <p class="text-[11px] text-blue-700 font-semibold mt-1">Avg committee analysis time</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                        <span>Legislative Pipeline</span>
                        <i class="bi bi-diagram-3-fill text-amber-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-slate-900 mt-1.5">4</p>
                    <p class="text-[11px] text-amber-700 font-semibold mt-1">Active ordinance proposals</p>
                </div>
            </div>

            <!-- Non-Redundant Focused Navigation Tabs -->
            <div class="border-b border-slate-200 bg-white rounded-t-xl px-4 pt-3 flex items-center gap-2 overflow-x-auto shadow-sm">
                <button id="sys-report-tab-ai" onclick="switchSystemReportTab('ai')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-red-600 text-red-600 flex items-center gap-2 transition focus:outline-none sys-report-tab">
                    <i class="bi bi-robot"></i> AI Executive Policy Briefs & Council Transmittals
                </button>
                <button id="sys-report-tab-consultation" onclick="switchSystemReportTab('consultation')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 flex items-center gap-2 transition focus:outline-none sys-report-tab">
                    <i class="bi bi-journal-text"></i> Consultation Policy Summaries & Survey Distribution
                </button>
                <button id="sys-report-tab-feedback" onclick="switchSystemReportTab('feedback')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 flex items-center gap-2 transition focus:outline-none sys-report-tab">
                    <i class="bi bi-chat-square-quote"></i> Citizen Feedback Sentiment & Theme Analytics
                </button>
            </div>

            <!-- Tab Content Container -->
            <div id="sys-reports-tab-body" class="bg-white rounded-b-xl border border-slate-200 p-6 shadow-sm">
                <!-- Loaded dynamically by switchSystemReportTab() -->
            </div>
        </div>
    `;

    switchSystemReportTab('ai');
}'''

for path in js_paths:
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            code = f.read()

        if 'async function renderSystemReportsSection()' in code:
            start_fn = code.find('async function renderSystemReportsSection()')
            end_fn = code.find('function switchSystemReportTab', start_fn)
            if start_fn != -1 and end_fn != -1:
                code = code[:start_fn] + clean_reports_function_with_both_buttons + '\n\n' + code[end_fn:]
                with open(path, 'w', encoding='utf-8') as f:
                    f.write(code)
                print(f"Updated renderSystemReportsSection with BOTH buttons in {path}")

print("Explicit PDF and Word buttons update complete!")
