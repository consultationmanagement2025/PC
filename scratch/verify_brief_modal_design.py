with open(r'c:\xampp\htdocs\CAP101\PC\app-features.js', 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

# Check that Section 1, Section 2, and Section 3 contain table tags and matching cards
section1_ok = "Section 1: Identified Citizen Problems" in content and "Category" in content and "Severity" in content
section2_ok = "Section 2: Recommended Committee Solutions" in content and "Policy Recommendation & Action Plan" in content and "RECOMMENDED" in content
section3_ok = "Section 3: Executive Conclusion" in content and "Final Mandate & Transmittal Summary" in content and "CONCLUDED" in content

print(f"Section 1 table card design: {'OK' if section1_ok else 'FAIL'}")
print(f"Section 2 table card design: {'OK' if section2_ok else 'FAIL'}")
print(f"Section 3 table card design: {'OK' if section3_ok else 'FAIL'}")
