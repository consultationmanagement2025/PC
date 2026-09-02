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

# Code for getPCSurveyAnswerData and renderPCSurveyAnswersChart
survey_fix_code = """function getPCSurveyAnswerData() {
    const selectEl = document.getElementById('pc-survey-select');
    const selectedId = selectEl ? String(selectEl.value || 'all') : 'all';
    const apiData = window._pcLiveVoteStatsResponse?.data || {};
    const overall = window._pcLiveVoteStatsResponse?.overall || {};

    let agree = 0;
    let disagree = 0;
    let other = 0;

    if (selectedId === 'all') {
        agree = Number(overall.agree_votes || 0);
        disagree = Number(overall.disagree_votes || 0);
        other = Number(overall.other_votes || 0);
        if (agree === 0 && disagree === 0 && other === 0) {
            for (const cid in apiData) {
                agree += Number(apiData[cid].agree_votes || 0);
                disagree += Number(apiData[cid].disagree_votes || 0);
                other += Number(apiData[cid].other_votes || 0);
            }
        }
    } else if (apiData[selectedId]) {
        agree = Number(apiData[selectedId].agree_votes || 0);
        disagree = Number(apiData[selectedId].disagree_votes || 0);
        other = Number(apiData[selectedId].other_votes || 0);
    }

    const totalRespondents = agree + disagree + other;
    
    const respondentsEl = document.getElementById('pc-survey-respondents-count');
    const formsEl = document.getElementById('pc-survey-forms-count');
    const agreeEl = document.getElementById('pc-survey-agree-count');
    const disagreeEl = document.getElementById('pc-survey-disagree-count');
    const surveyCountBadge = document.getElementById('pc-survey-total-badge');

    if (respondentsEl) respondentsEl.textContent = String(totalRespondents);
    if (formsEl) formsEl.textContent = String(selectedId === 'all' ? (overall.survey_count || Object.keys(apiData).length) : 1);
    if (agreeEl) agreeEl.textContent = `Agree: ${agree}`;
    if (disagreeEl) disagreeEl.textContent = `Disagree: ${disagree}`;
    if (surveyCountBadge) surveyCountBadge.textContent = `${overall.survey_count || Object.keys(apiData).length} surveys`;

    return {
        labels: ['Agree', 'Disagree', 'Other'],
        counts: [agree, disagree, other]
    };
}

function renderPCSurveyAnswersChart(consultations) {
    const ctx = document.getElementById('pcSurveyAnswersChart');
    if (!ctx) return;

    const targetUrl = typeof getApiUrl === 'function' ? getApiUrl('API/consultation_feedback.php?action=get_all_vote_stats') : 'API/consultation_feedback.php?action=get_all_vote_stats';

    fetch(targetUrl)
        .then(r => r.json())
        .then(d => {
            if (d && d.success) {
                window._pcLiveVoteStatsResponse = d;
                refreshPCSurveySelector(consultations);
                doRenderPCSurveyAnswersChart();
            }
        })
        .catch(err => {
            console.error('[SurveySummary] Error fetching vote stats:', err);
            doRenderPCSurveyAnswersChart();
        });
}"""

def process_file(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # 1. Update loadFeedbackFromApi URL fetch
    content = content.replace("fetchWithTimeout('API/feedback_api.php?action=list&limit=200&offset=0'", "fetchWithTimeout(typeof getApiUrl === 'function' ? getApiUrl('API/feedback_api.php?action=list&limit=200&offset=0') : 'API/feedback_api.php?action=list&limit=200&offset=0'")

    # 2. Replace renderPCSurveyAnswersChart & insert getPCSurveyAnswerData
    old_survey_render = r"function renderPCSurveyAnswersChart\(consultations\)\s*\{[\s\S]*?doRenderPCSurveyAnswersChart\(\);\s*\}\);"
    content = re.sub(old_survey_render, survey_fix_code, content)

    # If getPCSurveyAnswerData is missing, insert it before doRenderPCSurveyAnswersChart
    if "function getPCSurveyAnswerData()" not in content:
        content = content.replace("function doRenderPCSurveyAnswersChart()", survey_fix_code + "\n\nfunction doRenderPCSurveyAnswersChart()")

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

    print(f"Processed survey and feedback fixes in {filepath}")

for fp in files_to_update:
    process_file(fp)
