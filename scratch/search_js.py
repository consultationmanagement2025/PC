import re

with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    lines = f.readlines()

print(f"Total lines in app-features.js: {len(lines)}")

keywords = ['mapDbFeedbackToUi', 'getFeedbackSentimentStats', 'updateFeedbackStatsUI', 'renderFeedbackTable', 'renderPublicConsultation', 'buildConsultationSummary']

for kw in keywords:
    print(f"\n--- Matches for '{kw}' ---")
    for idx, line in enumerate(lines):
        if kw in line:
            print(f"Line {idx+1}: {line.strip()[:100]}")
