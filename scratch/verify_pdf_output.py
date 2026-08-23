import os

pdf_dir = r'c:\xampp\htdocs\CAP101\PC\uploads\documents'
files = [f for f in os.listdir(pdf_dir) if f.startswith('Unified_Citizen_Feedback_Summary_')]

print("Found PDF files:", files)

if files:
    filePath = os.path.join(pdf_dir, files[0])
    with open(filePath, 'rb') as f:
        content = f.read().decode('utf-8', errors='ignore')
    
    print("\n=== PDF HEADER CHECK ===")
    print(content[:300])

    print("\n=== MANDATORY FOOTER TEXT CHECK ===")
    has_text = "Compiled automatically by PCM" in content or "PCM" in content
    print(f"Mandatory Ending Text Present: {has_text}")
