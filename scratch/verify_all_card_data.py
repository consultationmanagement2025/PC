import urllib.request
import json
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

base_url = "http://localhost/CAP101/PC"

print("=== VERIFYING CARD & SYNTHESIS DATA DISPLAYS ===")

# 1. Test feedback API
try:
    req = urllib.request.Request(f"{base_url}/API/feedback_api.php?action=list&limit=200")
    with urllib.request.urlopen(req, context=ctx) as resp:
        data = json.loads(resp.read().decode('utf-8'))
        fb_list = data.get('data', [])
        print(f"✓ Feedback API returned {len(fb_list)} feedback items.")
        
        sentiments = {}
        for fb in fb_list:
            stag = fb.get('sentiment_tag') or fb.get('sentiment')
            sentiments[stag] = sentiments.get(stag, 0) + 1
        print(f"  Feedback Sentiment Distribution: {sentiments}")
except Exception as e:
    print(f"✗ Feedback API error: {e}")

# 2. Test AI Committee Brief Compilation for each consultation
consultation_ids = [1, 2, 3, 4, 5, 17, 18, 20, 24]
for cid in consultation_ids:
    try:
        req = urllib.request.Request(f"{base_url}/API/consultation_feedback_ai.php?action=get_brief&consultation_id={cid}")
        with urllib.request.urlopen(req, context=ctx) as resp:
            data = json.loads(resp.read().decode('utf-8'))
            if data.get('success'):
                brief = data.get('data', {})
                title = brief.get('title')
                committee = brief.get('committee_assigned') or brief.get('assigned_committee')
                stats = brief.get('stats', {})
                problems = brief.get('problems', [])
                solutions = brief.get('solutions', [])
                print(f"✓ Consultation #{cid} ('{title[:35]}...'):")
                print(f"    Committee: {committee}")
                print(f"    Feedback Count: {stats.get('total_submissions')} | Tone: {stats.get('dominant_sentiment')}")
                print(f"    Problems Identified: {len(problems)} | Solutions Proposed: {len(solutions)}")
                if problems:
                    print(f"    Sample Issue: {problems[0].get('category')} -> {problems[0].get('issue')[:70]}...")
            else:
                print(f"✗ Consultation #{cid} Brief error: {data.get('message')}")
    except Exception as e:
        print(f"✗ Consultation #{cid} Brief fetch error: {e}")

print("\n=== VERIFICATION COMPLETE ===")
