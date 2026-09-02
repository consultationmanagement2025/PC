import subprocess

php_code = """<?php
require_once 'db.php';
require_once 'DATABASE/audit-log.php';

$conn = dbEnsureConnection();

echo "=== SEEDING COMPREHENSIVE SYSTEM AUDIT TRAIL ===\\n";

$logsToSeed = [
    // Consultation Postings
    [1, 'Consultation Administrator', 'Posted Consultation', 'consultation', 5, 'Posted new public consultation: "Plastic Ban Enforcement Ordinance" (Category: Health & Sanitation)'],
    [1, 'Consultation Administrator', 'Posted Consultation', 'consultation', 1, 'Posted new public consultation: "Proposed Waste Segregation Enforcement Program" (Category: Health & Sanitation)'],
    [2, 'Council Secretariat', 'Posted Consultation', 'consultation', 2, 'Posted new public consultation: "Citywide Livelihood and Micro-Enterprise Development Survey" (Category: Livelihood)'],
    [2, 'Council Secretariat', 'Posted Consultation', 'consultation', 3, 'Posted new public consultation: "Extension of Operating Hours for Selected Public Markets" (Category: Market & Slaughterhouse)'],

    // File Downloads & Views
    [7, 'Super Administrator', 'Downloaded Document File', 'document', 101, 'Downloaded document file: "Plastic_Ban_Implementation_Guide.pdf" (PDF, 2.4MB)'],
    [12, 'Juan Dela Cruz (Citizen)', 'Downloaded Document File', 'document', 102, 'Downloaded document file: "Waste_Segregation_Fines_Draft.docx" (DOCX, 1.1MB)'],
    [15, 'Maria Santos (Citizen)', 'Downloaded Document File', 'document', 103, 'Downloaded document file: "Public_Market_Vendor_Schedule.pdf" (PDF, 3.8MB)'],

    // Citizen Feedback Submissions
    [12, 'Juan Dela Cruz (Citizen)', 'Submitted Citizen Feedback', 'feedback', 10, 'Submitted feedback on "Plastic Ban Enforcement Ordinance": "Strong support for eco-bag distribution in Malinta."'],
    [15, 'Maria Santos (Citizen)', 'Submitted Citizen Feedback', 'feedback', 11, 'Submitted feedback on "Proposed Waste Segregation Enforcement Program": "Suggest color-coded trash bins per barangay."'],

    // Survey Voting
    [12, 'Juan Dela Cruz (Citizen)', 'Voted in Survey', 'survey_vote', 5, 'Voted "Agree" on survey: "Do you support stricter enforcement of the plastic bag ban?"'],
    [15, 'Maria Santos (Citizen)', 'Voted in Survey', 'survey_vote', 1, 'Voted "Agree" on survey: "Do you support strict fines for unsegregated garbage collection?"'],

    // PHMS Inter-system Ingestion & Approvals
    [7, 'Super Administrator', 'Approved PHMS Data Package', 'phms_ingestion', 3, 'Approved & merged PHMS Queue Item #3 ("Consultation on Drainage Upgrades for Flood Control") into PCMS'],
    [7, 'Super Administrator', 'Approved PHMS Data Package', 'phms_ingestion', 4, 'Approved & merged PHMS Queue Item #4 ("Public Hearing: Local Market Vendor Guidelines") into PCMS'],

    // Resolution Reports & ORTS Brief Transmittals
    [7, 'Super Administrator', 'Uploaded Resolution Report', 'report', 201, 'Uploaded Executive Policy Resolution Report: "Resolution_No_2026_Plastic_Ban_Final.pdf"'],
    [7, 'Super Administrator', 'Transmitted Brief to ORTS', 'report', 5, 'Transmitted AI Policy Brief for consultation #5 to ORTS Ordinance Tracking System']
];

$count = 0;
foreach ($logsToSeed as $l) {
    list($adminId, $adminUser, $action, $entityType, $entityId, $details) = $l;
    $ok = logAction($adminId, $adminUser, $action, $entityType, $entityId, null, null, 'success', $details);
    if ($ok) $count++;
}

echo "Successfully inserted $count audit trail log records into audit_logs table!\\n";
?>"""

with open(r"c:\xampp\htdocs\CAP101\PC\scratch\seed_audit.php", "w") as f:
    f.write(php_code)

res = subprocess.run(["C:\\xampp\\php\\php.exe", r"c:\xampp\htdocs\CAP101\PC\scratch\seed_audit.php"], capture_output=True, text=True)
print(res.stdout[:3000])
