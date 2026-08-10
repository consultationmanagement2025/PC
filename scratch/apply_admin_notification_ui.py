import os, re

files_to_update = [
    r'c:\xampp\htdocs\CAP101\PC\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin\resource_person_dashboard.php',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\resource_person_dashboard.php'
]

new_drawer_html = """    <!-- Admin-Style Notification Drawer -->
    <div id="notif-drawer" class="fixed inset-y-0 right-0 w-full sm:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col border-l border-slate-200">
        <!-- Header Banner with Admin Gradient -->
        <div class="bg-gradient-to-r from-red-700 via-red-800 to-slate-900 text-white p-5 flex items-center justify-between shadow-md">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-white/10 text-amber-300 flex items-center justify-center text-lg shadow-inner border border-white/20">
                    <i class="bi bi-bell-fill"></i>
                </div>
                <div>
                    <h3 class="font-black text-sm tracking-wide">Notifications & Alerts</h3>
                    <p class="text-[11px] text-white/70">Expert Advisory Updates</p>
                </div>
            </div>
            <button onclick="toggleNotificationDrawer()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white text-base transition flex items-center justify-center">&times;</button>
        </div>

        <!-- Action Bar -->
        <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex justify-between items-center text-xs">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                <i class="bi bi-bell text-amber-600"></i> <?php echo $unread_notif_count; ?> Unread Alert(s)
            </span>
            <button onclick="markAllNotificationsRead()" class="text-red-700 hover:text-red-900 font-extrabold text-[11px] flex items-center gap-1 hover:underline transition">
                <i class="bi bi-check2-all"></i> Mark all read
            </button>
        </div>

        <!-- Notifications Body -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2.5">
            <?php if (empty($notifications_list)): ?>
                <div class="p-10 text-center text-slate-400 space-y-3 my-auto">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-300 mx-auto flex items-center justify-center text-2xl border border-slate-200">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-600">No Notifications Yet</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Dispatched consultations and admin updates will appear here.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notifications_list as $n): ?>
                    <?php 
                        $type = strtolower($n['type'] ?? 'info');
                        $badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
                        if ($type === 'assignment') $badgeClass = 'bg-red-100 text-red-800 border-red-200';
                        elseif ($type === 'approval') $badgeClass = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                        elseif ($type === 'rejection') $badgeClass = 'bg-rose-100 text-rose-800 border-rose-200';
                    ?>
                    <div onclick="openNotificationDetailModal('<?php echo htmlspecialchars(addslashes($n['title'])); ?>', '<?php echo htmlspecialchars(addslashes($n['message'])); ?>', '<?php echo strtoupper($n['type']); ?>', '<?php echo date('M j, Y g:i A', strtotime($n['created_at'])); ?>')"
                         class="p-4 rounded-2xl border transition-all duration-200 cursor-pointer space-y-2 shadow-2xs hover:shadow-md <?php echo !$n['is_read'] ? 'bg-amber-50/70 border-amber-300/80 hover:bg-amber-100/70' : 'bg-white border-slate-200/80 hover:bg-slate-50'; ?>">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider border <?php echo $badgeClass; ?>">
                                <?php echo htmlspecialchars($n['type']); ?>
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium"><i class="bi bi-clock mr-1"></i><?php echo date('M j, g:i a', strtotime($n['created_at'])); ?></span>
                        </div>
                        <h4 class="font-bold text-xs text-slate-900 leading-snug"><?php echo htmlspecialchars($n['title']); ?></h4>
                        <p class="text-[11px] text-slate-600 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars($n['message']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ADMIN-STYLE NOTIFICATION DETAIL MODAL -->
    <div id="notif-detail-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 space-y-0 animate-in fade-in zoom-in duration-150">
            <div class="bg-gradient-to-r from-red-700 via-red-800 to-slate-900 text-white p-6 flex items-start justify-between">
                <div class="space-y-1.5 pr-4">
                    <span id="modal-notif-type" class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/20 text-white border border-white/30">NOTIFICATION</span>
                    <h3 id="modal-notif-title" class="text-base font-extrabold leading-tight text-white">Title</h3>
                    <p id="modal-notif-time" class="text-[11px] text-white/70 font-medium"><i class="bi bi-clock mr-1"></i>Date</p>
                </div>
                <button type="button" onclick="closeNotificationDetailModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white text-base transition flex items-center justify-center shrink-0">&times;</button>
            </div>

            <div class="p-6 space-y-4">
                <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/90 text-xs text-slate-700 leading-relaxed font-medium" id="modal-notif-message">
                    Message content
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeNotificationDetailModal()" class="w-full bg-slate-900 hover:bg-black text-white font-bold py-3 px-5 rounded-2xl text-xs transition shadow-md flex items-center justify-center gap-1">
                        <i class="bi bi-check-circle"></i> Close & Mark Read
                    </button>
                </div>
            </div>
        </div>
    </div>
"""

js_detail_modal = """
    function openNotificationDetailModal(title, message, type, timeStr) {
        document.getElementById('modal-notif-title').textContent = title;
        document.getElementById('modal-notif-message').textContent = message;
        document.getElementById('modal-notif-type').textContent = type || 'ALERT';
        document.getElementById('modal-notif-time').innerHTML = '<i class="bi bi-clock mr-1"></i>' + timeStr;
        document.getElementById('notif-detail-modal').classList.remove('hidden');
    }

    function closeNotificationDetailModal() {
        document.getElementById('notif-detail-modal').classList.add('hidden');
        markAllNotificationsRead();
    }
"""

for filepath in files_to_update:
    if not os.path.exists(filepath):
        continue
    with open(filepath, 'r', encoding='utf-8') as f:
        code = f.read()

    # 1. Replace Notification Drawer markup
    code = re.sub(
        r'<!-- Notification Drawer -->.*?<!-- WORKSTATION MODAL',
        new_drawer_html + '\n\n    <!-- WORKSTATION MODAL',
        code,
        flags=re.DOTALL
    )

    # 2. Add JS functions for detail modal if not present
    if 'openNotificationDetailModal' not in code and 'function toggleNotificationDrawer()' in code:
        code = code.replace('function toggleNotificationDrawer()', js_detail_modal + '\n    function toggleNotificationDrawer()')

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(code)

    print("Successfully updated Admin notification UI in:", filepath)
