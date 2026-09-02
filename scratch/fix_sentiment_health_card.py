import os
import re
import subprocess

files_to_update = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

old_sentiment_pattern = r"// 2\. Public Sentiment Health\s*const validFeedback = [\s\S]*?const posSubtext = [\s\S]*?;"

new_sentiment_code = """// 2. Public Sentiment Health
    const totalFeedbackCount = feedbackList.length;
    const posSentimentCount = feedbackList.filter(f => {
        const r = Number((f && f.rating) || 0);
        const tag = String((f && (f.sentimentTag || f.sentiment || f.sentiment_tag)) || '').toLowerCase();
        return r >= 4 || tag.includes('pos');
    }).length;
    const posPctStr = totalFeedbackCount > 0 ? `${Math.round((posSentimentCount / totalFeedbackCount) * 100)}%` : '100%';
    const posSubtext = totalFeedbackCount > 0 ? `${posSentimentCount} of ${totalFeedbackCount} positive citizen responses` : 'Positive citizen sentiment ratio';"""

for filepath in files_to_update:
    if not os.path.exists(filepath):
        continue
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    content = re.sub(old_sentiment_pattern, new_sentiment_code, content)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Updated Sentiment Health card in {filepath}")

    res = subprocess.run(["node", "-c", filepath], capture_output=True, text=True)
    if res.returncode == 0:
        print(f"  Node syntax check PASSED: {os.path.basename(filepath)}")
    else:
        print(f"  Node syntax ERROR: {res.stderr}")
