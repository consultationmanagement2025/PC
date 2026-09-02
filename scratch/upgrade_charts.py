import re
import os

files_to_update = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

# Modern center plugin & modern chart defaults snippet
modern_chart_enhancements = """
// ==========================================
// Modern Chart Center Summary & Styling Plugin
// ==========================================
const modernCenterSummaryPlugin = {
    id: 'modernCenterSummaryText',
    beforeDraw(chart) {
        if (!chart.config || chart.config.type !== 'doughnut') return;
        const { width, height, ctx } = chart;
        const dataset = chart.data.datasets && chart.data.datasets[0];
        if (!dataset || !dataset.data) return;
        
        const data = dataset.data.map(v => Number(v) || 0);
        const visible = data.map((v, i) => (chart.getDataVisibility(i) ? v : 0));
        const total = visible.reduce((a, b) => a + b, 0);

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        
        const centerX = width / 2;
        const centerY = (chart.chartArea ? (chart.chartArea.top + chart.chartArea.bottom) / 2 : height / 2);

        // Render Total Number in Center
        ctx.font = '700 22px Inter, system-ui, -apple-system, sans-serif';
        ctx.fillStyle = '#0f172a';
        ctx.fillText(total, centerX, centerY - 6);

        // Render Subtext Label
        ctx.font = '600 10px Inter, system-ui, -apple-system, sans-serif';
        ctx.fillStyle = '#64748b';
        ctx.fillText('TOTAL', centerX, centerY + 14);

        ctx.restore();
    }
};

if (typeof Chart !== 'undefined' && Chart.register) {
    try {
        Chart.register(modernCenterSummaryPlugin);
    } catch (e) {}
}
"""

def process_file(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Update renderPCStatusChart
    old_pc_status_pattern = r"function renderPCStatusChart\(consultations\)\s*\{[\s\S]*?window\.pcStatusChart = new Chart\(ctx,\s*\{[\s\S]*?\}\);\s*\}"
    
    new_pc_status_code = """function renderPCStatusChart(consultations) {
    const ctx = document.getElementById('pcStatusChart');
    if (!ctx) return;

    const source = Array.isArray(consultations) ? consultations : (Array.isArray(AppData.consultations) ? AppData.consultations : []);
    const active = source.filter(c => String(c.status || '').toLowerCase() === 'active').length;
    const draft = source.filter(c => {
        const st = String(c.status || '').toLowerCase().trim();
        return st === 'draft' || st === 'pending' || st === 'submitted' || st === 'under_review' || st === 'pending_review' || st === 'for_approval' || st === 'scheduled';
    }).length;
    const closed = source.filter(c => String(c.status || '').toLowerCase() === 'closed').length;

    if (window.pcStatusChart) {
        try { window.pcStatusChart.destroy(); } catch (e) { }
    }

    window.pcStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Pending / In Review', 'Closed'],
            datasets: [{
                data: [active, draft, closed],
                backgroundColor: ['#10b981', '#3b82f6', '#94a3b8'],
                hoverBackgroundColor: ['#059669', '#2563eb', '#64748b'],
                borderWidth: 3,
                borderColor: '#ffffff',
                borderRadius: 6,
                spacing: 3,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        font: { family: 'Inter, system-ui, sans-serif', size: 12, weight: '500' },
                        color: '#334155'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { family: 'Inter, sans-serif', size: 13, weight: '600' },
                    bodyFont: { family: 'Inter, sans-serif', size: 12 },
                    padding: 12,
                    cornerRadius: 10,
                    boxPadding: 6,
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';
                            const value = Number(context.parsed || 0);
                            const data = Array.isArray(context.dataset?.data) ? context.dataset.data : [];
                            const totalVisible = data.reduce((sum, v, i) => sum + (context.chart.getDataVisibility(i) ? (Number(v) || 0) : 0), 0);
                            const percentage = totalVisible > 0 ? Math.round((value / totalVisible) * 100) : 0;
                            return ` ${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}"""

    # 2. Update renderPCFeedbackSentimentChart
    new_pc_sentiment_code = """function renderPCFeedbackSentimentChart() {
    const ctx = document.getElementById('pcFeedbackSentimentChart');
    if (!ctx) return;

    const stats = getFeedbackSentimentStats();
    const ratedBadge = document.getElementById('pc-rated-feedback-count');
    const posEl = document.getElementById('pc-positive-count');
    const neuEl = document.getElementById('pc-neutral-count');
    const negEl = document.getElementById('pc-negative-count');
    const summaryEl = document.getElementById('pc-feedback-stats-summary');
    const topicListEl = document.getElementById('pc-feedback-topic-list');
    if (ratedBadge) ratedBadge.textContent = String(stats.rated);
    if (posEl) posEl.textContent = `Positive: ${stats.positive}`;
    if (neuEl) neuEl.textContent = `Neutral: ${stats.neutral}`;
    if (negEl) negEl.textContent = `Negative: ${stats.negative}`;
    if (summaryEl) {
        const validFeedbackList = getFilteredValidFeedback();
        const totalFeedback = validFeedbackList.length;
        const avgRating = totalFeedback > 0 ? (validFeedbackList.reduce((sum, item) => sum + (Number(item && item.rating) > 0 ? Number(item.rating) : 0), 0) / totalFeedback) : 0;
        summaryEl.innerHTML = `Total feedback: <strong>${totalFeedback}</strong> · Avg rating: <strong>${avgRating.toFixed(1)} ★</strong>`;
    }
    if (topicListEl) {
        const topics = getTopicThemeBreakdown();
        topicListEl.innerHTML = topics.length
            ? topics.map((item) => `<div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm"><span class="text-gray-700">${escapeHtml(item.label)}</span><span class="font-semibold text-gray-900">${item.count}</span></div>`).join('')
            : '<div class="text-sm text-gray-500">No topic themes detected yet.</div>';
    }

    if (window.pcFeedbackSentimentChart) {
        try { window.pcFeedbackSentimentChart.destroy(); } catch (e) { }
    }

    window.pcFeedbackSentimentChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Positive (4-5★)', 'Neutral (3★)', 'Negative (1-2★)'],
            datasets: [{
                data: (stats.positive === 0 && stats.neutral === 0 && stats.negative === 0) ? [1] : [stats.positive, stats.neutral, stats.negative],
                backgroundColor: (stats.positive === 0 && stats.neutral === 0 && stats.negative === 0) ? ['#e2e8f0'] : ['#10b981', '#f59e0b', '#ef4444'],
                hoverBackgroundColor: ['#059669', '#d97706', '#dc2626'],
                borderWidth: 3,
                borderColor: '#ffffff',
                borderRadius: 6,
                spacing: 3,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 16,
                        font: { family: 'Inter, system-ui, sans-serif', size: 12, weight: '500' },
                        color: '#334155'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { family: 'Inter, sans-serif', size: 13, weight: '600' },
                    bodyFont: { family: 'Inter, sans-serif', size: 12 },
                    padding: 12,
                    cornerRadius: 10,
                    boxPadding: 6,
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';
                            const value = Number(context.parsed || 0);
                            return ` ${label}: ${value}`;
                        }
                    }
                }
            }
        }
    });
}"""

    # 3. Update renderPCSurveyAnswersChart
    new_pc_survey_code = """function doRenderPCSurveyAnswersChart() {
    const ctx = document.getElementById('pcSurveyAnswersChart');
    if (!ctx) return;

    if (window.pcSurveyAnswersChart) {
        try { window.pcSurveyAnswersChart.destroy(); } catch (e) { }
    }

    const { labels, counts } = getPCSurveyAnswerData();
    const hasData = counts.some(c => c > 0);

    window.pcSurveyAnswersChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Survey Responses',
                data: hasData ? counts : [0, 0, 0, 0, 0],
                backgroundColor: ['#dc2626', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4'],
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 38
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { family: 'Inter, sans-serif', size: 13, weight: '600' },
                    bodyFont: { family: 'Inter, sans-serif', size: 12 },
                    padding: 12,
                    cornerRadius: 10,
                    boxPadding: 6
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Inter, sans-serif', size: 12, weight: '500' }, color: '#64748b' }
                },
                y: {
                    grid: { color: '#f1f5f9' },
                    beginAtZero: true,
                    ticks: { precision: 0, font: { family: 'Inter, sans-serif', size: 12 }, color: '#64748b' }
                }
            }
        }
    });
}"""

    # Apply replacements
    content = re.sub(r"function renderPCStatusChart\(consultations\)\s*\{[\s\S]*?window\.pcStatusChart = new Chart\(ctx,\s*\{[\s\S]*?\}\);\s*\}", new_pc_status_code, content)
    content = re.sub(r"function renderPCFeedbackSentimentChart\(\)\s*\{[\s\S]*?window\.pcFeedbackSentimentChart = new Chart\(ctx,\s*\{[\s\S]*?\}\);\s*\}", new_pc_sentiment_code, content)
    content = re.sub(r"function doRenderPCSurveyAnswersChart\(\)\s*\{[\s\S]*?window\.pcSurveyAnswersChart = new Chart\(ctx,\s*\{[\s\S]*?\}\);\s*\}", new_pc_survey_code, content)

    # Ensure modernCenterSummaryPlugin is included at bottom if not already present
    if "modernCenterSummaryPlugin" not in content:
        content += "\n" + modern_chart_enhancements

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

    print(f"Successfully upgraded charts in {filepath}")

for fp in files_to_update:
    process_file(fp)
