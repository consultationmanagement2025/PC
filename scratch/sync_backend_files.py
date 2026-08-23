import shutil
import os

base_dir = r'c:\xampp\htdocs\CAP101\PC'

files_to_sync = [
    (r'DATABASE\feedback.php', r'admin\DATABASE\feedback.php'),
    (r'DATABASE\feedback.php', r'admin-side\DATABASE\feedback.php'),
    (r'API\feedback_api.php', r'admin\API\feedback_api.php'),
    (r'API\feedback_api.php', r'admin-side\API\feedback_api.php')
]

for src_rel, dst_rel in files_to_sync:
    src_path = os.path.join(base_dir, src_rel)
    dst_path = os.path.join(base_dir, dst_rel)
    if os.path.exists(src_path):
        os.makedirs(os.path.dirname(dst_path), exist_ok=True)
        shutil.copy2(src_path, dst_path)
        print(f"Copied {src_rel} -> {dst_rel}")

print("Backend sync complete!")
