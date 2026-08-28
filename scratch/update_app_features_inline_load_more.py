import os

app_features_files = [
    r'c:\xampp\htdocs\CAP101\PC\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js',
    r'c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js'
]

new_js_code = """
// ============================================================================
// REAL-TIME SYSTEM NOTIFICATIONS & INLINE SCROLL LOAD MORE HANDLERS
// ============================================================================

window._pfpCurrentNotifLimit = 20;
window._pfpHasMoreNotifications = true;

window.pfpCheckNotificationScrollPosition = function (el) {
    if (!el) return;
    const isAtBottom = (el.scrollTop + el.clientHeight >= el.scrollHeight - 25);
    const container = document.getElementById('notifications-load-more-container');
    if (container) {
        if (isAtBottom && window._pfpHasMoreNotifications) {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }
};

window.pfpLoadPreviousNotifications = async function () {
    const btn = document.getElementById('btn-load-previous-notifs');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin text-sm"></i> Loading previous notifications...`;
    }

    window._pfpCurrentNotifLimit += 20;

    try {
        let apiUrl = `API/notifications_api.php?action=list&limit=${window._pfpCurrentNotifLimit}`;
        if (typeof getApiUrl === 'function') apiUrl = getApiUrl(apiUrl);
        const res = await fetch(`${apiUrl}&_t=${Date.now()}`, { cache: 'no-store' });
        const resData = await res.json().catch(() => null);

        if (res.ok && resData && resData.success && resData.data) {
            const items = Array.isArray(resData.data.items) ? resData.data.items : [];
            const previousCount = Array.isArray(window.AppData?.notifications) ? window.AppData.notifications.length : 0;
            
            if (!window.AppData) window.AppData = {};
            window.AppData.notifications = items;

            if (items.length <= previousCount) {
                window._pfpHasMoreNotifications = false;
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = `<span class="text-xs text-gray-400 font-medium">All notifications loaded</span>`;
                }
            } else {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="bi bi-clock-history text-sm"></i> View Previous Notifications`;
                }
            }

            const listEl = document.getElementById('notifications-list');
            if (listEl) {
                listEl.innerHTML = items.map(n => pfpRenderNotificationItemHtml(n)).join('');
                listEl.scrollTop = listEl.scrollHeight - listEl.clientHeight - 30;
            }
        }
    } catch (e) {
        console.warn('Error loading previous notifications:', e);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<i class="bi bi-clock-history text-sm"></i> Retry Loading Previous`;
        }
    }
};

function pfpRenderNotificationItemHtml(n) {
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
}

window.pfpHandleNotificationClick = async function (id, type, message) {
    console.log('[Notification Clicked]', { id, type, message });

    const notifDropdown = document.getElementById('notifications-dropdown');
    if (notifDropdown) {
        notifDropdown.classList.add('hidden');
        notifDropdown.style.display = 'none';
    }

    if (id) {
        window.pfpMarkSingleNotifRead(id);
    }

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
        let apiUrl = `API/notifications_api.php?action=list&limit=${window._pfpCurrentNotifLimit || 20}`;
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
                    listEl.innerHTML = items.map(n => pfpRenderNotificationItemHtml(n)).join('');
                    pfpCheckNotificationScrollPosition(listEl);
                }
            }
        }
    } catch (e) {
        console.warn('Error loading notifications:', e);
    }
};
"""

print("=== REPLACING NOTIFICATION HANDLERS IN APP-FEATURES.JS FILES ===")
for fpath in app_features_files:
    if not os.path.exists(fpath):
        continue
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    # Find start of notification section
    marker = "// REAL-TIME SYSTEM NOTIFICATIONS"
    if marker in code:
        idx = code.find(marker)
        code = code[:idx] + new_js_code
    else:
        code += new_js_code

    with open(fpath, 'w', encoding='utf-8') as f:
        f.write(code)
    print("Updated inline load-more notification logic in:", fpath)

print("Finished updating app-features.js!")
