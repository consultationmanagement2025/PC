import os

app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

js_code = """

// ============================================================================
// REAL-TIME SYSTEM NOTIFICATIONS & HISTORY VAULT MODAL (GLOBAL HANDLERS)
// ============================================================================

window.pfpHandleNotificationClick = async function (id, type, message) {
    console.log('[Notification Clicked]', { id, type, message });

    // 1. Close notification dropdown
    const notifDropdown = document.getElementById('notifications-dropdown');
    if (notifDropdown) {
        notifDropdown.classList.add('hidden');
        notifDropdown.style.display = 'none';
    }

    // 2. Mark notification read
    if (id) {
        window.pfpMarkSingleNotifRead(id);
    }

    // 3. Route user to corresponding module
    const msg = String(message || '').toLowerCase();
    const t = String(type || '').toLowerCase();

    if (t === 'phms_feedback' || msg.includes('phms') || msg.includes('hearing') || msg.includes('ingested') || msg.includes('ingestion')) {
        if (typeof showSection === 'function') showSection('public-feedback-queue');
        if (typeof pfpSwitchTab === 'function') pfpSwitchTab('phms');
        if (typeof showNotification === 'function') {
            showNotification('🏢 Opened PHMS Public Hearing Feedback Queue', 'info');
        }
        if (typeof loadPhmsFeedbackFromApi === 'function') {
            loadPhmsFeedbackFromApi(true);
        }
    } else if (t === 'feedback' || msg.includes('feedback') || msg.includes('proposal') || msg.includes('citizen')) {
        if (typeof showSection === 'function') showSection('public-feedback-queue');
        if (typeof pfpSwitchTab === 'function') pfpSwitchTab('consult');
        if (typeof showNotification === 'function') {
            showNotification('📩 Opened Citizen Consultation Feedback', 'info');
        }
    } else if (t === 'consultation' || msg.includes('survey') || msg.includes('poll') || msg.includes('vote')) {
        if (typeof showSection === 'function') showSection('public-feedback-queue');
        if (typeof pfpSwitchTab === 'function') pfpSwitchTab('survey');
        if (typeof showNotification === 'function') {
            showNotification('📊 Opened Community Survey & Poll Results', 'info');
        }
    } else if (t === 'ai_brief' || msg.includes('ai') || msg.includes('brief')) {
        if (typeof showSection === 'function') showSection('consultation-dashboard');
        if (typeof showNotification === 'function') {
            showNotification('🤖 Opened AI Executive Synthesis Brief', 'info');
        }
    } else {
        if (typeof showSection === 'function') showSection('public-feedback-queue');
    }
};

window.pfpMarkSingleNotifRead = async function (id) {
    if (!id) return;
    id = Number(id);

    const notifItem = document.querySelector(`#notifications-list [data-id="${id}"]`);
    if (notifItem) {
        notifItem.classList.remove('bg-white', 'font-medium');
        notifItem.classList.add('bg-gray-50/40', 'opacity-75');
        const redDot = notifItem.querySelector('.bg-red-500');
        if (redDot) redDot.remove();
    }

    if (window.AppData && Array.isArray(window.AppData.notifications)) {
        const item = window.AppData.notifications.find(n => Number(n.id) === id);
        if (item) item.is_read = 1;
    }

    pfpUpdateNotificationBadgeCount();

    try {
        await fetch('API/notifications_api.php?action=mark_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, is_read: 1 })
        });
    } catch (e) {
        console.warn('Failed to mark notification read on server:', e);
    }
};

window.pfpMarkAllNotificationsRead = async function () {
    const listContainer = document.getElementById('notifications-list');
    if (listContainer) {
        const items = listContainer.querySelectorAll('[data-id]');
        items.forEach(el => {
            el.classList.remove('bg-white', 'font-medium');
            el.classList.add('bg-gray-50/40', 'opacity-75');
            const redDot = el.querySelector('.bg-red-500');
            if (redDot) redDot.remove();
        });
    }

    if (window.AppData && Array.isArray(window.AppData.notifications)) {
        window.AppData.notifications.forEach(n => n.is_read = 1);
    }

    pfpUpdateNotificationBadgeCount();

    try {
        await fetch('API/notifications_api.php?action=mark_all_read', { method: 'POST' });
        if (typeof showNotification === 'function') {
            showNotification('All notifications marked as read', 'success');
        }
    } catch (e) {
        console.warn('Failed to mark all notifications read:', e);
    }
};

function pfpUpdateNotificationBadgeCount() {
    const notifs = (window.AppData && Array.isArray(window.AppData.notifications)) ? window.AppData.notifications : [];
    const unreadCount = notifs.filter(n => !n.is_read || Number(n.is_read) === 0).length;

    const badgeEls = document.querySelectorAll('#notification-badge, #unread-count, #notifications-btn .bg-red-500');
    badgeEls.forEach(badge => {
        if (unreadCount > 0) {
            badge.innerText = unreadCount;
            badge.classList.remove('hidden');
            badge.style.display = 'inline-flex';
        } else {
            badge.innerText = '0';
            badge.classList.add('hidden');
            badge.style.display = 'none';
        }
    });
}

window.loadNotifications = async function () {
    try {
        let apiUrl = 'API/notifications_api.php?action=list&limit=20';
        if (typeof getApiUrl === 'function') apiUrl = getApiUrl(apiUrl);
        const res = await fetch(`${apiUrl}&_t=${Date.now()}`, { cache: 'no-store' });
        if (!res.ok) return;
        const resData = await res.json().catch(() => null);
        if (resData && resData.success && resData.data) {
            const items = Array.isArray(resData.data.items) ? resData.data.items : [];
            if (!window.AppData) window.AppData = {};
            window.AppData.notifications = items;

            const unreadCount = Number(resData.data.unread ?? items.filter(n => !n.is_read || Number(n.is_read) === 0).length);

            const badgeEls = document.querySelectorAll('#notification-badge, #unread-count');
            badgeEls.forEach(b => {
                if (unreadCount > 0) {
                    b.innerText = unreadCount;
                    b.classList.remove('hidden');
                    b.style.display = 'inline-flex';
                } else {
                    b.innerText = '0';
                    b.classList.add('hidden');
                    b.style.display = 'none';
                }
            });

            const listEl = document.getElementById('notifications-list');
            if (listEl) {
                if (items.length === 0) {
                    listEl.innerHTML = `
                        <div class="p-6 text-center text-gray-400 text-xs font-medium">
                            <i class="bi bi-bell-slash text-2xl block mb-1 text-gray-300"></i>
                            No notifications yet
                        </div>
                    `;
                } else {
                    listEl.innerHTML = items.map(n => {
                        const isRead = Boolean(n.is_read && Number(n.is_read) === 1);
                        const msgRaw = n.message || '';
                        const msg = escapeHtml(msgRaw);
                        const msgAttr = msgRaw.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const type = String(n.type || 'info').toLowerCase();
                        const dateStr = n.created_at ? new Date(n.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Just now';

                        let title = 'System Notification';
                        let iconClass = 'bi-bell-fill text-blue-600 bg-blue-50 border-blue-100';

                        if (type === 'phms_feedback' || msgRaw.includes('PHMS')) {
                            title = '🏢 PHMS Hearing Feedback';
                            iconClass = 'bi-building-fill-gear text-emerald-600 bg-emerald-50 border-emerald-100';
                        } else if (msgRaw.includes('AI') || type === 'ai_brief') {
                            title = '🤖 AI Committee Brief';
                            iconClass = 'bi-robot text-purple-600 bg-purple-50 border-purple-100';
                        } else if (msgRaw.includes('Feedback') || msgRaw.includes('Proposal') || type === 'feedback') {
                            title = '📩 Citizen Feedback';
                            iconClass = 'bi-chat-left-text text-emerald-600 bg-emerald-50 border-emerald-100';
                        } else if (type === 'consultation' || msgRaw.includes('Survey')) {
                            title = '📊 Community Poll Update';
                            iconClass = 'bi-square-poll text-amber-600 bg-amber-50 border-amber-100';
                        }

                        return `
                            <div data-id="${n.id}" onclick="pfpHandleNotificationClick(${n.id}, '${type}', '${msgAttr}')" class="p-4 transition hover:bg-blue-50/70 flex items-start gap-3.5 relative cursor-pointer ${!isRead ? 'bg-white font-medium' : 'bg-gray-50/40 opacity-75'}">
                                <div class="w-10 h-10 rounded-2xl border flex items-center justify-center shrink-0 mt-0.5 ${iconClass}">
                                    <i class="bi bi-bell text-base"></i>
                                </div>
                                <div class="flex-1 min-w-0 pr-3">
                                    <div class="font-bold text-gray-900 text-xs leading-snug">${title}</div>
                                    <div class="text-xs text-gray-500 mt-0.5 leading-relaxed font-normal">${msg}</div>
                                    <div class="text-[11px] text-gray-400 mt-1 font-medium">${dateStr}</div>
                                </div>
                                ${!isRead ? '<span class="w-2.5 h-2.5 rounded-full bg-red-500 shrink-0 mt-1.5 ring-4 ring-red-50"></span>' : ''}
                            </div>
                        `;
                    }).join('');
                }
            }
        }
    } catch (e) {
        console.warn('Error loading notifications:', e);
    }
};

window.pfpOpenViewPreviousNotificationsModal = async function () {
    const dropdown = document.getElementById('notifications-dropdown');
    if (dropdown) {
        dropdown.classList.add('hidden');
        dropdown.style.display = 'none';
    }

    let oldModal = document.getElementById('pfp-previous-notifications-modal');
    if (oldModal) oldModal.remove();

    const modalHtml = `
        <div id="pfp-previous-notifications-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[9999] flex items-center justify-center p-4 md:p-6 overflow-y-auto animate-fade-in" style="z-index: 99999 !important;">
            <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 w-full max-w-3xl overflow-hidden flex flex-col max-h-[88vh] animate-scale-up">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 bg-gradient-to-r from-slate-900 via-slate-800 to-red-950 text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center text-red-400">
                            <i class="bi bi-clock-history text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-black text-lg text-white tracking-tight">Notification Log & History Vault</h3>
                            <p class="text-xs text-slate-300 font-medium">Full history of system alerts, PHMS syncs, and citizen feedback</p>
                        </div>
                    </div>
                    <button type="button" onclick="document.getElementById('pfp-previous-notifications-modal').remove()" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <!-- Filters Toolbar -->
                <div class="px-6 py-3.5 bg-gray-50 border-b border-gray-200/80 flex flex-wrap items-center justify-between gap-3 shrink-0">
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="pfpFilterNotifVault('all')" id="notif-vault-filter-all" class="px-3.5 py-1.5 rounded-xl font-bold text-xs bg-red-600 text-white shadow-sm transition cursor-pointer">All Logs</button>
                        <button type="button" onclick="pfpFilterNotifVault('unread')" id="notif-vault-filter-unread" class="px-3.5 py-1.5 rounded-xl font-bold text-xs bg-white text-gray-700 hover:bg-gray-200 border border-gray-200 transition cursor-pointer">Unread Only</button>
                        <button type="button" onclick="pfpFilterNotifVault('phms')" id="notif-vault-filter-phms" class="px-3.5 py-1.5 rounded-xl font-bold text-xs bg-white text-gray-700 hover:bg-gray-200 border border-gray-200 transition cursor-pointer">PHMS Integration</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="pfpMarkAllNotificationsRead()" class="px-3 py-1.5 text-xs font-bold text-red-600 hover:text-red-700 transition flex items-center gap-1 cursor-pointer">
                            <i class="bi bi-check2-all text-sm"></i> Mark All Read
                        </button>
                    </div>
                </div>

                <!-- Modal Body List -->
                <div id="pfp-notif-vault-list" class="p-6 overflow-y-auto space-y-3 flex-1">
                    <div class="py-12 text-center text-gray-400">
                        <i class="bi bi-arrow-repeat animate-spin text-2xl block mb-2 text-red-600"></i>
                        Loading complete notification log vault...
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between shrink-0">
                    <span class="text-xs text-gray-500 font-semibold" id="pfp-notif-vault-count">Showing notifications</span>
                    <button type="button" onclick="document.getElementById('pfp-previous-notifications-modal').remove()" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl transition shadow-sm cursor-pointer">
                        Close Log Vault
                    </button>
                </div>

            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    try {
        let apiUrl = 'API/notifications_api.php?action=list&limit=100';
        if (typeof getApiUrl === 'function') apiUrl = getApiUrl(apiUrl);
        const res = await fetch(`${apiUrl}&_t=${Date.now()}`, { cache: 'no-store' });
        const resData = await res.json().catch(() => null);
        if (res.ok && resData && resData.success && Array.isArray(resData.data?.items)) {
            window._pfp_notif_vault_items = resData.data.items;
            pfpRenderNotifVaultList(resData.data.items);
        } else {
            document.getElementById('pfp-notif-vault-list').innerHTML = `
                <div class="p-8 text-center text-gray-500 font-medium">No previous notifications found.</div>
            `;
        }
    } catch (e) {
        document.getElementById('pfp-notif-vault-list').innerHTML = `
            <div class="p-8 text-center text-red-500 font-medium">Failed to load notification history: ${escapeHtml(e.message)}</div>
        `;
    }
};

window.pfpRenderNotifVaultList = function (items) {
    const container = document.getElementById('pfp-notif-vault-list');
    const countEl = document.getElementById('pfp-notif-vault-count');
    if (!container) return;

    if (!Array.isArray(items) || items.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center text-gray-400 font-medium">
                <i class="bi bi-inbox text-3xl block mb-2 text-gray-300"></i>
                No matching notifications in log vault.
            </div>
        `;
        if (countEl) countEl.innerText = '0 notifications found';
        return;
    }

    if (countEl) countEl.innerText = `Showing ${items.length} notification logs`;

    container.innerHTML = items.map(n => {
        const isRead = Boolean(n.is_read && Number(n.is_read) === 1);
        const msgRaw = n.message || '';
        const msg = escapeHtml(msgRaw);
        const msgAttr = msgRaw.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const type = String(n.type || 'info').toLowerCase();
        const dateStr = n.created_at ? new Date(n.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';

        let title = 'System Log';
        let iconClass = 'bi-bell-fill text-blue-600 bg-blue-50 border-blue-200';

        if (type === 'phms_feedback' || msgRaw.includes('PHMS')) {
            title = '🏢 PHMS Hearing Integration';
            iconClass = 'bi-building-fill-gear text-emerald-600 bg-emerald-50 border-emerald-200';
        } else if (msgRaw.includes('AI') || type === 'ai_brief') {
            title = '🤖 AI Synthesis Executive Brief';
            iconClass = 'bi-robot text-purple-600 bg-purple-50 border-purple-200';
        } else if (msgRaw.includes('Feedback') || msgRaw.includes('Proposal') || type === 'feedback') {
            title = '📩 Citizen Consultation Feedback';
            iconClass = 'bi-chat-left-text text-emerald-600 bg-emerald-50 border-emerald-200';
        } else if (type === 'consultation' || msgRaw.includes('Survey')) {
            title = '📊 Community Survey Vote';
            iconClass = 'bi-square-poll text-amber-600 bg-amber-50 border-amber-200';
        }

        return `
            <div onclick="document.getElementById('pfp-previous-notifications-modal').remove(); pfpHandleNotificationClick(${n.id}, '${type}', '${msgAttr}')" class="p-4 rounded-2xl border transition hover:shadow-md flex items-start gap-4 cursor-pointer ${!isRead ? 'bg-white border-red-200 font-semibold shadow-sm' : 'bg-gray-50/60 border-gray-200/80 opacity-80'}">
                <div class="w-11 h-11 rounded-2xl border flex items-center justify-center shrink-0 ${iconClass}">
                    <i class="bi bi-bell text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-extrabold text-gray-900 text-xs md:text-sm">${title}</span>
                        <span class="text-[11px] font-bold text-gray-400 shrink-0">${dateStr}</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-1 leading-relaxed font-normal">${msg}</p>
                </div>
                ${!isRead ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-100 text-red-700 uppercase tracking-wider shrink-0 mt-1">NEW</span>' : ''}
            </div>
        `;
    }).join('');
};

window.pfpFilterNotifVault = function (filterType) {
    const items = window._pfp_notif_vault_items || [];
    ['all', 'unread', 'phms'].forEach(f => {
        const btn = document.getElementById(`notif-vault-filter-${f}`);
        if (btn) {
            if (f === filterType) {
                btn.className = 'px-3.5 py-1.5 rounded-xl font-bold text-xs bg-red-600 text-white shadow-sm transition cursor-pointer';
            } else {
                btn.className = 'px-3.5 py-1.5 rounded-xl font-bold text-xs bg-white text-gray-700 hover:bg-gray-200 border border-gray-200 transition cursor-pointer';
            }
        }
    });

    let filtered = [...items];
    if (filterType === 'unread') {
        filtered = filtered.filter(n => !n.is_read || Number(n.is_read) === 0);
    } else if (filterType === 'phms') {
        filtered = filtered.filter(n => String(n.type || '').toLowerCase() === 'phms_feedback' || String(n.message || '').includes('PHMS'));
    }

    pfpRenderNotifVaultList(filtered);
};
"""

print("=== APPENDING REAL-TIME NOTIFICATION SYSTEM TO APP-FEATURES.JS FILES ===")
for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    if "window.pfpHandleNotificationClick" not in code:
        code += js_code
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(code)
        print("Appended notification system handlers to:", fpath)
    else:
        print("Handlers already present in:", fpath)

print("Finished appending JS notification handlers!")
