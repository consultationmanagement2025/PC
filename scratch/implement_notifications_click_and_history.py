import os

# 1. Update system-template-full.php files
system_template_files = [
    r'c:\xampp\htdocs\CAP101\PC\system-template-full.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\system-template-full.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\system-template-full.php'
]

old_dropdown_html = """                                    <div id="notifications-list" class="overflow-y-auto max-h-80 divide-y divide-gray-100">
                                        <?php if (!empty($serverNotifsList)): ?>
                                            <?php foreach ($serverNotifsList as $sn): 
                                                $isRead = !empty($sn['is_read']);
                                                $msg = htmlspecialchars($sn['message']);
                                                $time = date('M d, Y H:i', strtotime($sn['created_at']));
                                                $title = 'System Notification';
                                                $type = strtolower($sn['type'] ?? 'info');
                                                $iconClass = 'bi-bell-fill text-blue-600 bg-blue-50 border-blue-100';

                                                if (strpos($msg, 'AI') !== false || $type === 'ai_brief') {
                                                    $title = '🤖 AI Committee Brief';
                                                    $iconClass = 'bi-robot text-purple-600 bg-purple-50 border-purple-100';
                                                } else if (strpos($msg, 'Feedback') !== false || strpos($msg, 'Proposal') !== false || $type === 'feedback') {
                                                    $title = '📩 Citizen Feedback';
                                                    $iconClass = 'bi-chat-left-text text-emerald-600 bg-emerald-50 border-emerald-100';
                                                } else if ($type === 'consultation' || strpos($msg, 'Survey') !== false) {
                                                    $title = '📊 Community Poll Update';
                                                    $iconClass = 'bi-square-poll text-amber-600 bg-amber-50 border-amber-100';
                                                }
                                            ?>
                                                <div data-id="<?php echo $sn['id']; ?>" onclick="pfpMarkSingleNotifRead(<?php echo $sn['id']; ?>)" class="p-4 transition hover:bg-gray-50/80 flex items-start gap-3.5 relative cursor-pointer <?php echo !$isRead ? 'bg-white font-medium' : 'bg-gray-50/40 opacity-75'; ?>">
                                                    <div class="w-10 h-10 rounded-2xl border flex items-center justify-center shrink-0 mt-0.5 <?php echo $iconClass; ?>">
                                                        <i class="bi bi-bell text-base"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0 pr-3">
                                                        <div class="font-bold text-gray-900 text-xs leading-snug"><?php echo $title; ?></div>
                                                        <div class="text-xs text-gray-500 mt-0.5 leading-relaxed font-normal"><?php echo $msg; ?></div>
                                                        <div class="text-[11px] text-gray-400 mt-1 font-medium"><?php echo $time; ?></div>
                                                    </div>
                                                    <?php if (!$isRead): ?>
                                                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 shrink-0 mt-1.5 ring-4 ring-red-50"></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="p-6 text-center text-gray-400 text-xs font-medium">No notifications yet</div>
                                        <?php endif; ?>
                                    </div>
                                </div>"""

new_dropdown_html = """                                    <div id="notifications-list" class="overflow-y-auto max-h-80 divide-y divide-gray-100">
                                        <?php if (!empty($serverNotifsList)): ?>
                                            <?php foreach ($serverNotifsList as $sn): 
                                                $isRead = !empty($sn['is_read']);
                                                $rawMsg = $sn['message'] ?? '';
                                                $msg = htmlspecialchars($rawMsg);
                                                $safeMsgAttr = str_replace(['\\'', '"'], ['\\\\\\'', '&quot;'], $rawMsg);
                                                $time = date('M d, Y H:i', strtotime($sn['created_at']));
                                                $title = 'System Notification';
                                                $type = strtolower($sn['type'] ?? 'info');
                                                $iconClass = 'bi-bell-fill text-blue-600 bg-blue-50 border-blue-100';

                                                if ($type === 'phms_feedback' || strpos($msg, 'PHMS') !== false) {
                                                    $title = '🏢 PHMS Hearing Feedback';
                                                    $iconClass = 'bi-building-fill-gear text-emerald-600 bg-emerald-50 border-emerald-100';
                                                } else if (strpos($msg, 'AI') !== false || $type === 'ai_brief') {
                                                    $title = '🤖 AI Committee Brief';
                                                    $iconClass = 'bi-robot text-purple-600 bg-purple-50 border-purple-100';
                                                } else if (strpos($msg, 'Feedback') !== false || strpos($msg, 'Proposal') !== false || $type === 'feedback') {
                                                    $title = '📩 Citizen Feedback';
                                                    $iconClass = 'bi-chat-left-text text-emerald-600 bg-emerald-50 border-emerald-100';
                                                } else if ($type === 'consultation' || strpos($msg, 'Survey') !== false) {
                                                    $title = '📊 Community Poll Update';
                                                    $iconClass = 'bi-square-poll text-amber-600 bg-amber-50 border-amber-100';
                                                }
                                            ?>
                                                <div data-id="<?php echo $sn['id']; ?>" onclick="pfpHandleNotificationClick(<?php echo $sn['id']; ?>, '<?php echo addslashes($type); ?>', '<?php echo $safeMsgAttr; ?>')" class="p-4 transition hover:bg-blue-50/70 flex items-start gap-3.5 relative cursor-pointer <?php echo !$isRead ? 'bg-white font-medium' : 'bg-gray-50/40 opacity-75'; ?>">
                                                    <div class="w-10 h-10 rounded-2xl border flex items-center justify-center shrink-0 mt-0.5 <?php echo $iconClass; ?>">
                                                        <i class="bi bi-bell text-base"></i>
                                                    </div>
                                                    <div class="flex-1 min-w-0 pr-3">
                                                        <div class="font-bold text-gray-900 text-xs leading-snug"><?php echo $title; ?></div>
                                                        <div class="text-xs text-gray-500 mt-0.5 leading-relaxed font-normal"><?php echo $msg; ?></div>
                                                        <div class="text-[11px] text-gray-400 mt-1 font-medium"><?php echo $time; ?></div>
                                                    </div>
                                                    <?php if (!$isRead): ?>
                                                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 shrink-0 mt-1.5 ring-4 ring-red-50"></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="p-6 text-center text-gray-400 text-xs font-medium"><i class="bi bi-bell-slash text-2xl block mb-1 text-gray-300"></i> No notifications yet</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="px-4 py-3 bg-gray-50/90 border-t border-gray-100 text-center shrink-0">
                                        <button type="button" onclick="pfpOpenViewPreviousNotificationsModal()" class="text-xs font-bold text-red-600 hover:text-red-800 transition flex items-center justify-center gap-1.5 mx-auto cursor-pointer">
                                            <i class="bi bi-clock-history text-sm"></i> View Previous Notifications
                                        </button>
                                    </div>
                                </div>"""

print("=== UPDATING SYSTEM-TEMPLATE-FULL.PHP FILES ===")
for fpath in system_template_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if old_dropdown_html in code:
        code = code.replace(old_dropdown_html, new_dropdown_html)
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Updated notifications dropdown footer & click handlers in:", fpath)
    else:
        print("Dropdown pattern not matched or already updated in:", fpath)

print("Finished updating PHP templates!")
