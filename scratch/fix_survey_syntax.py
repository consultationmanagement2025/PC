import os
import subprocess

files_to_update = [
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
]

for filepath in files_to_update:
    if not os.path.exists(filepath):
        continue
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Remove double closing brace after renderPCSurveyAnswersChart
    bad_pattern = """            doRenderPCSurveyAnswersChart();
        });
}
}"""
    good_pattern = """            doRenderPCSurveyAnswersChart();
        });
}"""
    if bad_pattern in content:
        content = content.replace(bad_pattern, good_pattern)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Fixed syntax brace in {filepath}")

    res = subprocess.run(["node", "-c", filepath], capture_output=True, text=True)
    if res.returncode == 0:
        print(f"Node syntax check PASSED: {os.path.basename(filepath)}")
    else:
        print(f"Node syntax ERROR in {os.path.basename(filepath)}:\n{res.stderr}")
