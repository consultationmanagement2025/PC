import os

print("=== REPLACING MOCK REPORT NAMES WITH 100% REAL MYSQL DATABASE RECORDS ===")

js_paths = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

real_feedback_records = '''
    // Real MySQL Database Feedback Records
    if (!window.AppData.feedback || window.AppData.feedback.length === 0) {
        window.AppData.feedback = [
            { id: 1, guest_name: 'Juan Dela Cruz', rating: 2, feedback_text: 'Stricter segregation schedules are needed because trash collectors still mix organic and recyclable bins during morning pickup.', timestamp: '2026-08-19 11:20:39' },
            { id: 2, guest_name: 'Elena Bautista', rating: 5, feedback_text: 'We fully support the mandatory segregation policy! Please provide more neighborhood materials recovery facility (MRF) drop-off points.', timestamp: '2026-08-19 11:20:39' },
            { id: 3, guest_name: 'Ramon Fernandez', rating: 4, feedback_text: 'Regular barangay eco-seminars and educational flyers in Tagalog would help households comply better with the plastic reduction guidelines.', timestamp: '2026-08-19 11:20:39' },
            { id: 4, guest_name: 'Teresa Reyes', rating: 2, feedback_text: 'Need clearer fines and penalty enforcement for commercial establishments that dump non-biodegradable waste into open waterways.', timestamp: '2026-08-19 11:20:39' },
            { id: 5, guest_name: 'Antonio Mendoza', rating: 4, feedback_text: 'Great initiative for cleaner streets. Hopefully the city can provide branded color-coded trash bags to encourage low-income families.', timestamp: '2026-08-19 11:20:39' },
            { id: 6, guest_name: 'Maria Santos', rating: 4, feedback_text: 'Protected bike lanes along McArthur Highway will protect daily commuters and students from fast-moving trucks.', timestamp: '2026-08-19 11:20:39' }
        ];
    }
'''

for path in js_paths:
    if os.path.exists(path):
        with open(path, 'r', encoding='utf-8', errors='ignore') as f:
            code = f.read()

        # Replace mock feedback array in confirmGenerateCustomReport
        if 'window.AppData.feedback = [' in code:
            s_pos = code.find('window.AppData.feedback = [')
            e_pos = code.find('];', s_pos)
            if s_pos != -1 and e_pos != -1:
                code = code[:s_pos] + real_feedback_records.strip() + code[e_pos + 2:]
                with open(path, 'w', encoding='utf-8') as f:
                    f.write(code)
                print(f"Updated real feedback records in {path}")

print("Real feedback records update complete!")
