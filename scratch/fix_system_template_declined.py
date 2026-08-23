import shutil

file_path = r'c:\xampp\htdocs\CAP101\PC\system-template-full.php'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target_block_1 = """                                                                 <?php if (!$is_read_only_super_admin): ?>
                                                                     <select onchange="updateConsultationStatus(<?= (int)$c['id'] ?>, this.value, event)" class="text-xs border rounded px-1 py-0.5">
                                                                         <option value="">Set Status</option>
                                                                         <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                                                         <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                                                         <option value="viewed" <?= $status === 'viewed' ? 'selected' : '' ?>>Viewed</option>
                                                                         <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied</option>
                                                                         <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                                         <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                                                                         <option value="declined" <?= ($status === 'declined' || $status === 'rejected') ? 'selected' : '' ?>>Declined</option>
                                                                         <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                                                                     </select>
                                                                     <button type="button" onclick="event.preventDefault(); event.stopPropagation(); openDeclineCitizenSubmissionModal(<?= (int)$c['id'] ?>)" class="text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Decline</button>
                                                                 <?php else: ?>"""

replacement_block_1 = """                                                                 <?php if (!$is_read_only_super_admin): ?>
                                                                     <?php if ($status === 'declined' || $status === 'rejected'): ?>
                                                                         <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Declined</span>
                                                                     <?php else: ?>
                                                                         <select onchange="updateConsultationStatus(<?= (int)$c['id'] ?>, this.value, event)" class="text-xs border rounded px-1 py-0.5">
                                                                             <option value="">Set Status</option>
                                                                             <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                                                                             <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                                                             <option value="viewed" <?= $status === 'viewed' ? 'selected' : '' ?>>Viewed</option>
                                                                             <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied</option>
                                                                             <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                                             <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                                                                             <option value="declined" <?= ($status === 'declined' || $status === 'rejected') ? 'selected' : '' ?>>Declined</option>
                                                                             <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                                                                         </select>
                                                                         <button type="button" onclick="event.preventDefault(); event.stopPropagation(); openDeclineCitizenSubmissionModal(<?= (int)$c['id'] ?>)" class="text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 rounded border border-rose-200 inline-flex items-center gap-1"><i class="bi bi-x-circle-fill text-[10px]"></i> Decline</button>
                                                                     <?php endif; ?>
                                                                 <?php else: ?>"""

count = content.count(target_block_1)
print(f"Found {count} occurrences of target_block_1")

if count > 0:
    content = content.replace(target_block_1, replacement_block_1)
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("Replaced successfully in main system-template-full.php!")

    # Copy to subdirectories
    shutil.copy2(file_path, r'c:\xampp\htdocs\CAP101\PC\admin\system-template-full.php')
    shutil.copy2(file_path, r'c:\xampp\htdocs\CAP101\PC\admin-side\system-template-full.php')
    print("Copied updated system-template-full.php to admin/ and admin-side/!")
else:
    print("WARNING: target_block_1 not found!")
