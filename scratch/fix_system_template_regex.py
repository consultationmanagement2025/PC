import re, shutil

file_path = r'c:\xampp\htdocs\CAP101\PC\system-template-full.php'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

pattern = re.compile(
    r'(<\?php\s+if\s+\(!\$is_read_only_super_admin\):\s*\?>)\s*'
    r'(<select\s+onchange="updateConsultationStatus\(<\?=\s*\(int\)\$c\[\'id\'\]\s*\?>,[\s\S]*?<\/select>)\s*'
    r'(<button\s+type="button"\s+onclick="event\.preventDefault\(\);[\s\S]*?openDeclineCitizenSubmissionModal[\s\S]*?<\/button>)',
    re.MULTILINE
)

matches = pattern.findall(content)
print(f"Regex matched {len(matches)} occurrences!")

def replacer(match):
    prefix = match.group(1)
    select_tag = match.group(2)
    button_tag = match.group(3)
    return (
        f"{prefix}\n"
        f"                                                                     <?php if ($status === 'declined' || $status === 'rejected'): ?>\n"
        f"                                                                         <span class=\"text-xs font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1\"><i class=\"bi bi-x-circle-fill text-[10px]\"></i> Declined</span>\n"
        f"                                                                     <?php else: ?>\n"
        f"                                                                         {select_tag}\n"
        f"                                                                         {button_tag}\n"
        f"                                                                     <?php endif; ?>"
    )

new_content, count = pattern.subn(replacer, content)
print(f"Replaced {count} occurrences using regex!")

if count > 0:
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Saved main system-template-full.php!")

    shutil.copy2(file_path, r'c:\xampp\htdocs\CAP101\PC\admin\system-template-full.php')
    shutil.copy2(file_path, r'c:\xampp\htdocs\CAP101\PC\admin-side\system-template-full.php')
    print("Copied updated system-template-full.php to admin/ and admin-side/!")
