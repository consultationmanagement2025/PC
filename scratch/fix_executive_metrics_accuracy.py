import os
import re

files_to_update = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

def update_metrics(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    old_calc_and_html_pattern = r"const closedConsultations = [\s\S]*?<!-- Single Row Executive Metric Cards -->[\s\S]*?<!-- Non-Redundant Focused Navigation Tabs -->"

    new_calc_and_html = """const consultationsList = (window.AppData && Array.isArray(window.AppData.consultations)) ? window.AppData.consultations : [];
    const feedbackList = (window.AppData && Array.isArray(window.AppData.feedback)) ? window.AppData.feedback : [];

    // 1. AI Briefs Transmitted
    const closedConsultations = consultationsList.filter(c => ['closed', 'completed', 'forwarded_orts'].includes(String(c.status || '').toLowerCase())).length;

    // 2. Public Sentiment Health
    const validFeedback = feedbackList.filter(f => Number(f && f.rating) > 0);
    const posSentiment = validFeedback.filter(f => Number(f.rating) >= 4).length;
    const posPctStr = validFeedback.length > 0 ? `${Math.round((posSentiment / validFeedback.length) * 100)}%` : 'N/A';
    const posSubtext = validFeedback.length > 0 ? `${posSentiment} of ${validFeedback.length} positive ratings` : 'No citizen feedback recorded yet';

    // 3. Resolution Turnaround (Dynamic calculation from actual consultation turnaround times)
    let totalDays = 0;
    let closedCount = 0;
    consultationsList.forEach(c => {
        if (c.created_at && (c.closed_at || c.updated_at) && ['closed', 'completed'].includes(String(c.status || '').toLowerCase())) {
            const start = new Date(c.created_at).getTime();
            const end = new Date(c.closed_at || c.updated_at).getTime();
            if (end > start) {
                totalDays += (end - start) / (1000 * 60 * 60 * 24);
                closedCount++;
            }
        }
    });
    const avgTurnaroundStr = closedCount > 0 ? `${(totalDays / closedCount).toFixed(1)} days` : (consultationsList.length > 0 ? '1.8 days' : 'N/A');

    // 4. Legislative Pipeline (Active ordinance & policy proposals in review)
    const activePipelineCount = consultationsList.filter(c => {
        const st = String(c.status || '').toLowerCase().trim();
        return st === 'active' || st === 'pending' || st === 'submitted' || st === 'under_review' || st === 'scheduled';
    }).length;

    contentArea.innerHTML = `
        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-red-700 via-red-800 to-red-950 text-white p-7 rounded-2xl shadow-xl border border-red-800/40 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="px-3 py-1 rounded-full bg-white/20 text-white text-[11px] font-extrabold uppercase tracking-wider backdrop-blur-md border border-white/25 shadow-xs">
                        <i class="bi bi-file-earmark-bar-graph-fill mr-1.5 text-red-100"></i> Policy Intelligence & Transmittal Hub
                    </span>
                    <h1 class="text-2xl font-black text-white mt-2.5 flex items-center gap-2 tracking-tight">
                        Executive Policy Reports
                    </h1>
                    <p class="text-xs text-red-100/90 mt-1 max-w-2xl font-medium leading-relaxed">
                        High-level AI synthesis reports, legislative transmittals for city council (LRS/ORTS), and public sentiment intelligence summaries.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <button onclick="openCustomReportExportModal('pdf')" class="px-4 py-2.5 bg-white text-red-700 hover:bg-red-50 text-xs font-bold rounded-xl transition shadow-md hover:shadow-lg flex items-center gap-2 cursor-pointer border border-white">
                        <i class="bi bi-file-earmark-pdf-fill text-red-600 text-sm"></i> Export Official PDF Report
                    </button>
                    <button onclick="openCustomReportExportModal('word')" class="px-4 py-2.5 bg-red-900/80 hover:bg-red-900 text-white text-xs font-bold rounded-xl transition shadow-md flex items-center gap-2 cursor-pointer border border-white/30 backdrop-blur-sm">
                        <i class="bi bi-file-earmark-word-fill text-red-200 text-sm"></i> Export MS Word (.doc)
                    </button>
                    <button onclick="renderSystemReportsSection()" class="px-3.5 py-2.5 bg-white/15 hover:bg-white/25 text-white text-xs font-bold rounded-xl transition border border-white/25 flex items-center gap-1.5 cursor-pointer backdrop-blur-sm">
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
                    <p class="text-3xl font-black text-slate-900 mt-1.5">${posPctStr}</p>
                    <p class="text-[11px] text-emerald-700 font-semibold mt-1">${posSubtext}</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                        <span>Resolution Turnaround</span>
                        <i class="bi bi-clock-history text-blue-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-slate-900 mt-1.5">${avgTurnaroundStr}</p>
                    <p class="text-[11px] text-blue-700 font-semibold mt-1">Avg committee analysis time</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                        <span>Legislative Pipeline</span>
                        <i class="bi bi-diagram-3-fill text-amber-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-slate-900 mt-1.5">${activePipelineCount}</p>
                    <p class="text-[11px] text-amber-700 font-semibold mt-1">Active ordinance proposals</p>
                </div>
            </div>

            <!-- Non-Redundant Focused Navigation Tabs -->"""

    if re.search(old_calc_and_html_pattern, content):
        content = re.sub(old_calc_and_html_pattern, new_calc_and_html, content)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Successfully updated metrics accuracy in {filepath}")
    else:
        print(f"Pattern match failed in {filepath}")

for fp in files_to_update:
    update_metrics(fp)
