import os

app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

print("=== FIXING AI BRIEF REFERENCE ERROR IN APP-FEATURES.JS FILES ===")

for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # 1. Update function signature from renderAiCommitteeBriefModalHtml(brief) to renderAiCommitteeBriefModalHtml(brief, c)
    code = code.replace("function renderAiCommitteeBriefModalHtml(brief) {", "function renderAiCommitteeBriefModalHtml(brief, c) {")

    # 2. Update invocation from renderAiCommitteeBriefModalHtml(brief); to renderAiCommitteeBriefModalHtml(brief, consultation);
    code = code.replace("renderAiCommitteeBriefModalHtml(brief);", "renderAiCommitteeBriefModalHtml(brief, consultation);")

    # 3. Clean up backdrop on catch
    old_catch = """    } catch (e) {
        console.error('AI Brief compilation failed:', e);
        showNotification(`AI Compilation failed: ${e.message}`, 'error');
    }"""

    new_catch = """    } catch (e) {
        console.error('AI Brief compilation failed:', e);
        const modal = document.getElementById('pfq-ai-brief-modal');
        if (modal) modal.remove();
        showNotification(`AI Compilation failed: ${e.message}`, 'error');
    }"""

    if old_catch in code:
        code = code.replace(old_catch, new_catch)

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Fixed AI brief ReferenceError in:", fpath)

print("Finished fixing app-features.js files!")
