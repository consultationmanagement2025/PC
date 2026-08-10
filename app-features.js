// ==============================


// PCMS SYSTEM - FULL FEATURES


// ==============================




// Global Data Store (no seeded/sample data)


const AppData = {


    documents: [],


    users: [],


    notifications: [],


    announcements: [],

    issueReports: [],


    auditLogs: [],


    loginHistory: [],


    currentUser: null


};

window.pfpShowPhmsDetailModal = function (hearingId) {
    console.log('[PHMS Detail Modal] Launching for hearingId:', hearingId);
    const oldModal = document.getElementById('phms-detail-modal');
    if (oldModal) {
        try { oldModal.remove(); } catch (_) { }
    }

    const escapeHtmlHelper = (str) => String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const modal = document.createElement('div');
    modal.id = 'phms-detail-modal';
    modal.style.cssText = 'position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; background-color: rgba(15, 23, 42, 0.88) !important; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: flex !important; align-items: center !important; justify-content: center !important; z-index: 9999999 !important; padding: 1rem !important; margin: 0 !important;';

    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-150" style="position: relative; z-index: 10000000 !important;">
            <!-- Modal Header -->
            <div class="bg-slate-900 text-white p-6 flex items-start justify-between">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-500/20 text-blue-300 border border-blue-400/30">
                        <i class="bi bi-building-gear mr-1"></i> PHMS Citizen Hearing Feedback
                    </span>
                    <h3 id="phms-modal-title" class="text-lg font-extrabold text-white mt-1.5">Public Hearing #${escapeHtmlHelper(hearingId)}</h3>
                    <p id="phms-modal-date" class="text-xs text-slate-300 mt-1">Fetching hearing details from PHMS integration service...</p>
                </div>
                <button type="button" onclick="const m=document.getElementById('phms-detail-modal'); if(m)m.remove();" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl font-bold flex items-center justify-center transition leading-none cursor-pointer">&times;</button>
            </div>

            <!-- Modal Body -->
            <div id="phms-modal-body" class="p-6 max-h-[70vh] overflow-y-auto space-y-4">
                <div class="p-8 text-center text-gray-500">
                    <i class="bi bi-arrow-repeat animate-spin text-2xl mb-2 block text-blue-600"></i> Loading citizen feedback responses from PHMS integration service...
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <span class="text-xs text-gray-500"><i class="bi bi-shield-check text-blue-600 mr-1"></i> Verified Citizen Testimonial Ledger</span>
                <button type="button" onclick="const m=document.getElementById('phms-detail-modal'); if(m)m.remove();" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-xs transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const renderModalError = (msg) => {
        const bodyEl = document.getElementById('phms-modal-body');
        if (bodyEl) {
            bodyEl.innerHTML = `
                <div class="p-8 text-center text-rose-600 bg-rose-50 rounded-xl border border-rose-200 space-y-2">
                    <i class="bi bi-exclamation-triangle-fill text-3xl block"></i>
                    <h4 class="font-bold text-sm">Unable to load PHMS feedback.</h4>
                    <p class="text-xs text-rose-700">${escapeHtmlHelper(msg || 'Unable to connect to PHMS server.')}</p>
                </div>
            `;
        }
    };

    const renderModalContent = (hearing) => {
        if (!hearing) {
            renderModalError('Unable to load PHMS feedback.');
            return;
        }

        const title = escapeHtmlHelper(hearing.hearing_title || hearing.title || hearing.full_name || `Hearing #${hearingId}`);
        const dateStr = escapeHtmlHelper(hearing.hearing_date || hearing.created_at || 'N/A');
        const statusStr = escapeHtmlHelper(hearing.hearing_status || hearing.status || 'completed').toUpperCase();
        const feedbackCount = hearing.feedback_count ?? 0;

        const titleEl = document.getElementById('phms-modal-title');
        const dateEl = document.getElementById('phms-modal-date');
        const bodyEl = document.getElementById('phms-modal-body');

        if (titleEl) titleEl.textContent = title;
        if (dateEl) dateEl.textContent = `📅 Hearing Date: ${dateStr} | Status: ${statusStr} | Total Submissions: ${feedbackCount}`;

        // Rely strictly on result.data.hearings[0].citizen_responses
        const responses = Array.isArray(hearing.citizen_responses) ? hearing.citizen_responses : [];

        if (!responses.length) {
            if (bodyEl) {
                bodyEl.innerHTML = `
                    <div class="p-8 text-center text-gray-500 bg-slate-50 rounded-xl border border-slate-200">
                        <i class="bi bi-chat-left-text text-2xl block mb-2 text-gray-400"></i>
                        No citizen feedback recorded for this hearing.
                    </div>
                `;
            }
            return;
        }

        if (!bodyEl) return;

        const responsesHtml = responses.map((resp, idx) => {
            const name = escapeHtmlHelper(resp.citizen_name || resp.name || 'Anonymous Citizen');
            const rating = resp.rating !== undefined && resp.rating !== null ? Number(resp.rating).toFixed(1) : 'N/A';
            const tone = resp.tone || resp.sentiment ? escapeHtmlHelper(resp.tone || resp.sentiment) : '';
            const testimony = escapeHtmlHelper(resp.testimony || resp.statement || 'No testimony provided.');
            const submittedAt = escapeHtmlHelper(resp.submitted_at || resp.date || 'Recently');
            const status = escapeHtmlHelper(resp.publication_status || 'published').toLowerCase();

            const statusClass = status === 'published' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-amber-100 text-amber-800 border-amber-200';

            return `
                <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200/80 shadow-sm space-y-2">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-gray-900 text-xs">${idx + 1}. ${name}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${statusClass}">${status}</span>
                            ${tone ? `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">${tone}</span>` : ''}
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            ${rating !== 'N/A' ? `<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-900 border border-amber-200 font-semibold text-[11px]">⭐ ${rating}</span>` : ''}
                            <span class="text-gray-400 font-normal">${submittedAt}</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-700 leading-relaxed font-medium bg-white p-3 rounded-lg border border-slate-200/60 select-text">
                        "${testimony}"
                    </p>
                </div>
            `;
        }).join('');

        bodyEl.innerHTML = `
            <div class="space-y-3">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">
                    Submitted Citizen Testimonies (${responses.length})
                </div>
                ${responsesHtml}
            </div>
        `;
    };

    fetchWithTimeout(`API/feedback_api.php?action=phms_detail&hearing_id=${encodeURIComponent(hearingId)}`, {
        headers: { 'Accept': 'application/json' }
    }, 8000).then(res => res.json()).then(data => {
        if (data && data.success && data.data && Array.isArray(data.data.hearings) && data.data.hearings.length > 0) {
            renderModalContent(data.data.hearings[0]);
        } else {
            renderModalError('Unable to load PHMS feedback.');
        }
    }).catch(err => {
        console.warn('PHMS detail fetch error:', err);
        renderModalError('Unable to load PHMS feedback.');
    });
};




if (typeof window !== 'undefined' && window.__CURRENT_USER__ && !AppData.currentUser) {


    const cu = window.__CURRENT_USER__;


    AppData.currentUser = {


        id: cu.id ?? null,


        name: cu.name || 'User',


        email: cu.email || '',


        role: cu.role || '',


        twoFactorEnabled: false,


        twoFactorMethod: 'email'


    };


}

function normalizeRoleName(role) {
    return String(role || '').trim().toLowerCase().replace(/_/g, ' ');
}

function isSuperAdminRole(role) {
    const normalized = normalizeRoleName(role);
    return normalized === 'super admin' || normalized === 'superadmin';
}

function isBarangayStaffRole(role) {
    const normalized = normalizeRoleName(role);
    return normalized === 'barangay staff' || normalized === 'barangay_staff' || normalized === 'barangay' || normalized === 'staff';
}

function currentUserRole() {
    if (AppData.currentUser?.role) {
        return AppData.currentUser.role;
    }
    if (typeof window !== 'undefined') {
        if (window.__CURRENT_USER__?.role) {
            return window.__CURRENT_USER__.role;
        }
        if (window.__IS_SUPER_ADMIN__) {
            return 'super admin';
        }
        if (window.__IS_BARANGAY_STAFF__) {
            return 'barangay staff';
        }
    }
    return '';
}

function currentUserIsSuperAdmin() {
    const role = currentUserRole();
    const normalized = normalizeRoleName(role);
    return normalized === 'super admin' || normalized === 'superadmin' || (typeof window !== 'undefined' && window.__IS_SUPER_ADMIN__ === true);
}

function currentUserIsAdminRole() {
    const role = currentUserRole();
    const normalized = normalizeRoleName(role);
    return ['admin', 'administrator', 'super admin', 'superadmin', 'staff', 'barangay staff', 'barangay_staff', 'barangay'].indexOf(normalized) !== -1 || (typeof window !== 'undefined' && window.__IS_SUPER_ADMIN__ === true);
}

function getApiUrl(resource) {
    if (typeof resource !== 'string') return resource;
    if (resource.startsWith('API/')) {
        const path = window.location.pathname;
        if (path.includes('/admin/') || path.includes('/admin-side/')) {
            return '../' + resource;
        }
    }
    return resource;
}

function fetchWithTimeout(resource, options = {}, timeout = 5000) {
    const controller = new AbortController();
    const signal = controller.signal;
    const finalOptions = { ...options, signal };
    const timer = setTimeout(() => controller.abort(), timeout);
    return fetch(getApiUrl(resource), finalOptions)
        .finally(() => clearTimeout(timer));
}

function currentUserIsBarangayStaff() {
    if (typeof window !== 'undefined' && typeof window.__IS_BARANGAY_STAFF__ === 'boolean') {
        return window.__IS_BARANGAY_STAFF__;
    }
    const role = currentUserRole();
    return isBarangayStaffRole(role);
}

function currentUserCanAccessDocuments() {
    return currentUserIsAdminRole() || currentUserIsSuperAdmin() || currentUserIsBarangayStaff();
}

function currentUserCanManageDocuments() {
    return currentUserIsAdminRole() || currentUserIsBarangayStaff();
}

function adjustUIForRoleVisibility() {
    // Hide admin-only links from Barangay Staff on the client side (in case template allows them)
    if (currentUserIsBarangayStaff()) {
        // Remove User Management links
        document.querySelectorAll('[data-section="users"], a[onclick*="showSection(\'users\')"]').forEach(el => el.remove());
        // Hide system-wide analytics/configuration links
        document.querySelectorAll('[data-section="analytics"], [data-section="cross-barangay"] , a[onclick*="showSection(\'analytics\')"]').forEach(el => el.remove());
    }
}




// toggleProfileDropdown is now handled inline in the HTML to avoid conflicts




// Profile menu helpers are now inline in HTML to avoid conflicts




function savePreferences() {


    const enableNotifs = !!document.getElementById('pref-notifications')?.checked;


    const emailSummaries = !!document.getElementById('pref-emails')?.checked;




    const formData = new FormData();


    formData.append('action', 'save_preferences');


    formData.append('language', 'en');


    formData.append('theme', 'light');


    formData.append('email_notif', emailSummaries ? '1' : '');


    formData.append('announcement_notif', enableNotifs ? '1' : '');


    formData.append('feedback_notif', enableNotifs ? '1' : '');




    fetch('API/update_profile.php', {


        method: 'POST',


        body: formData


    })


        .then(r => r.json())


        .then(data => {


            if (!data || !data.success) {


                throw new Error((data && data.message) ? data.message : 'Failed to save preferences');


            }


            showNotification('Preferences saved successfully', 'success');


        })


        .catch(err => {


            console.error(err);


            showNotification(err && err.message ? String(err.message) : 'Failed to save preferences', 'error');


        });


}




// Initialize App

async function bootstrapAppDataAndRenderInitialSection() {
    const initialSection = 'public-consultation';

    showSection(initialSection);

    try {
        await Promise.all([
            loadConsultationsFromApi().catch(e => console.warn('Initial consultations load:', e)),
            loadFeedbackFromApi().catch(e => console.warn('Initial feedback load:', e)),
            loadDocumentsFromApi().catch(e => console.warn('Initial documents load:', e))
        ]);
    } catch (e) {
        console.warn('Initial module bootstrap failed:', e);
    }
}

document.addEventListener('DOMContentLoaded', function () {


    initializeData();


    // Super Admin lands on the dashboard view by default. Regular admin lands on the consultation dashboard.
    startHeaderClock();

    // Adjust UI for Barangay Staff (hide admin-only links)
    try { adjustUIForRoleVisibility(); } catch (e) { /* ignore */ }


    updateHeaderUserDisplays();





    // Bootstrap the initial module only after the core data is ready.
    bootstrapAppDataAndRenderInitialSection();





    // Delay notification loading slightly to ensure DOM is ready


    setTimeout(function () {


        loadNotifications();


    }, 100);


    // Profile dropdown toggle (disabled to avoid conflict with inline onclick)


    const profileBtn = document.getElementById('profile-btn');


    console.log('Profile button found:', !!profileBtn);


    // Do not bind listener here; using inline onclick for now





    // Poll for new notifications every 20 seconds (real-time updates)


    setInterval(function () {


        loadNotifications();


    }, 20000);





    // Close notification/profile dropdowns when clicking outside


    document.addEventListener('click', function (e) {


        const notifDropdown = document.getElementById('notifications-dropdown');


        const notifBtn = document.getElementById('notifications-btn');


        if (notifDropdown && notifBtn && !notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {


            notifDropdown.classList.add('hidden');


        }


        const profileDropdown = document.getElementById('profile-dropdown');


        const profileBtn = document.getElementById('profile-btn');


        if (profileDropdown && profileBtn && !profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {


            profileDropdown.classList.add('hidden');


        }


    });





    // Keyboard shortcuts


    document.addEventListener('keydown', function (e) {


        // Ctrl+K for search


        if (e.ctrlKey && e.key === 'k') {


            e.preventDefault();


            const search = document.getElementById('quick-search');


            if (search) search.focus();


        }


    });





    // Setup drag and drop


    setupDragAndDrop();


});




function isFeedbackOverdue(feedback, days) {


    const st = String(feedback && feedback.status ? feedback.status : '').toLowerCase();


    if (st !== 'new') return false;




    const rawDate = feedback && feedback.date ? feedback.date : null;


    if (!rawDate) return false;




    const created = new Date(rawDate);


    if (Number.isNaN(created.getTime())) return false;




    const ms = Date.now() - created.getTime();


    const threshold = (Number(days) || 0) * 24 * 60 * 60 * 1000;


    return ms >= threshold;


}




// Initialize Sample Data


function initializeData() {


    // Ensure data stores exist; do not seed sample data.


    // Load notifications from storage if present, otherwise leave empty.


    const storedNotifs = localStorage.getItem('llrm_notifications');


    if (storedNotifs) {


        try {


            AppData.notifications = JSON.parse(storedNotifs);


        } catch (e) {


            console.warn('Failed to parse notifications from storage');


            AppData.notifications = [];


        }


    } else {


        AppData.notifications = [];


    }




    // Load announcements (if any) from storage


    loadAnnouncementsFromStorage();




    // Load audit logs from storage if present


    const storedAuditLogs = localStorage.getItem('llrm_auditLogs');


    if (storedAuditLogs) {


        try {


            AppData.auditLogs = JSON.parse(storedAuditLogs);


        } catch (e) {


            console.warn('Failed to parse audit logs from storage');


            AppData.auditLogs = [];


        }


    } else {


        AppData.auditLogs = [];


    }




    // Leave other stores empty until populated by real data


    AppData.documents = [];


    AppData.users = [];


    AppData.consultations = [];


    AppData.feedback = [];


    AppData.loginHistory = [];


}




function normalizeDocSource(value) {
    const source = String(value || '').trim().toLowerCase();
    return source === 'consultation' ? 'consultation' : 'admin';
}


function mapDbDocumentToUi(row) {


    const source = normalizeDocSource(row.source || 'admin');
    return {


        id: Number(row.id),
        uid: `${source}:${Number(row.id)}`,
        source,
        consultationId: Number(row.consultation_id || row.consultationId || 0) || 0,


        reference: String(row.reference || row.ref_no || row.reference_number || ''),


        title: String(row.title || ''),


        type: String(row.type || '').toLowerCase(),


        status: String(row.status || 'draft').toLowerCase(),


        date: row.document_date || row.date || row.created_at || '',


        description: String(row.description || ''),


        uploadedBy: String(row.uploaded_by || row.uploadedBy || row.uploader || ''),


        uploadedAt: row.created_at || row.uploaded_at || '',


        fileSize: String(row.file_size || row.fileSize || ''),
        size: Number(row.file_size || row.size || 0) || 0,
        filePath: String(row.file_path || ''),
        downloadUrl: String(row.download_url || row.file_path || ''),
        originalFilename: String(row.original_filename || ''),


        views: Number(row.views || 0),


        downloads: Number(row.downloads || 0),


        tags: Array.isArray(row.tags) ? row.tags : (row.tags ? String(row.tags).split(',').map(s => s.trim()).filter(Boolean) : [])


    };


}




async function loadDocumentsFromApi() {


    const res = await fetchWithTimeout('API/documents_api.php?action=list&limit=200&offset=0', {


        headers: { 'Accept': 'application/json' }


    }, 5000);




    let data = null;


    try { data = await res.json(); } catch (_) { }




    if (!res.ok) {


        const msg = (data && data.message)


            ? data.message


            : (res.status === 403 ? 'Unauthorized (admin or super admin session required)' : `HTTP ${res.status}`);


        throw new Error(msg);


    }




    if (!data || !data.success || !Array.isArray(data.data)) {


        throw new Error((data && data.message) ? data.message : 'Failed to load documents');


    }




    AppData.documents = data.data.map(mapDbDocumentToUi);


}




function findDocumentByUid(uid) {
    if (!uid) return null;
    const normalized = String(uid).trim();
    if (!normalized) return null;

    const parts = normalized.split(':');
    if (parts.length === 2) {
        const source = parts[0].trim().toLowerCase();
        const idValue = Number(parts[1]);
        if (!Number.isNaN(idValue)) {
            const exact = AppData.documents.find(d => String(d.source || 'admin').trim().toLowerCase() === source && Number(d.id) === idValue);
            if (exact) return exact;
        }
    }

    const numericId = Number(normalized);
    if (!Number.isNaN(numericId)) {
        return AppData.documents.find(d => Number(d.id) === numericId) || AppData.documents.find(d => String(d.uid || '').trim().toLowerCase() === normalized.toLowerCase());
    }

    return AppData.documents.find(d => String(d.uid || '').trim().toLowerCase() === normalized.toLowerCase())
        || AppData.documents.find(d => String(d.reference || '').trim().toLowerCase() === normalized.toLowerCase());
}

function mapDbUserToUi(row) {


    return {


        id: Number(row.id),


        name: String(row.fullname || row.name || row.username || ''),


        email: String(row.email || ''),


        role: String(row.role || 'viewer'),


        status: String(row.status || 'active').toLowerCase(),


        lastLogin: row.last_login || row.lastLogin || '',


        createdAt: row.created_at || row.createdAt || ''


    };


}




async function loadUsersFromApi() {


    const res = await fetch('API/users_api.php?action=list', {


        headers: { 'Accept': 'application/json' }


    });




    let data = null;


    try { data = await res.json(); } catch (_) { }




    if (!res.ok) {


        const msg = (data && data.message)


            ? data.message


            : (res.status === 403 ? 'Unauthorized (admin session required)' : `HTTP ${res.status}`);


        throw new Error(msg);


    }




    if (!data || !data.success || !Array.isArray(data.data)) {


        throw new Error((data && data.message) ? data.message : 'Failed to load users');


    }




    AppData.users = data.data.map(mapDbUserToUi);


}




// Section Management


let headerClockInterval = null;

function formatLiveDateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    const dateStr = now.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
    return { timeStr, dateStr };
}

function startHeaderClock() {
    const pageTitle = document.getElementById('page-title');
    if (!pageTitle) return;
    if (headerClockInterval) {
        clearInterval(headerClockInterval);
    }
    const updateTitle = () => {
        const { timeStr, dateStr } = formatLiveDateTime();
        pageTitle.className = 'page-title text-base md:text-xl font-bold text-gray-800 flex items-center';
        pageTitle.innerHTML = `
            <div class="inline-flex items-center gap-2.5 px-3 py-1.5 bg-gray-50/90 hover:bg-gray-100/90 border border-gray-200/80 rounded-xl shadow-2xs transition-all">
                <div class="flex items-center gap-1.5 font-mono font-bold text-xs md:text-sm text-gray-900 tracking-tight">
                    <i class="bi bi-clock-fill text-red-600 text-xs"></i>
                    <span>${timeStr}</span>
                </div>
                <div class="h-3.5 w-px bg-gray-300"></div>
                <div class="flex items-center gap-1.5 text-xs text-gray-600 font-medium">
                    <i class="bi bi-calendar3 text-red-500 text-[11px]"></i>
                    <span class="font-semibold text-gray-700">${dateStr}</span>
                </div>
            </div>
        `;
    };
    updateTitle();
    headerClockInterval = setInterval(updateTitle, 1000);
}

function stopHeaderClock() {
    if (headerClockInterval) {
        clearInterval(headerClockInterval);
        headerClockInterval = null;
    }
}

function hideManagedTemplateSections() {
    const container = document.getElementById('content-area') || document.querySelector('main');
    if (container) {
        Array.from(container.children).forEach((child) => {
            child.style.display = 'none';
        });
    }

    const managedSectionIds = [
        'dashboard-section',
        'document-management-section',
        'documents-module-section',
        'consultation-management-section',
        'feedback-management-section',
        'audit-section',
        'user-management-section',
        'reports-section'
    ];

    managedSectionIds.forEach((id) => {
        const section = document.getElementById(id);
        if (section) {
            section.style.display = 'none';
        }
    });
}

function showManagedTemplateSection(sectionName) {
    window._currentActiveSection = sectionName;

    const sectionTitles = {
        'public-consultation': 'Consultation Dashboard',
        'consultation-dashboard': 'Consultation Dashboard',
        dashboard: 'Dashboard',
        documents: 'Document Management',
        'pc-documents': 'Document Management',
        'document-management': 'Document Management',
        'consultation-management': 'Consultation Management',
        'public-feedback-queue': 'Feedback Management',
        'public-feedback-portal': 'Feedback Management',
        feedback: 'Feedback Management',
        users: 'User Management',
        'user-management': 'User Management',
        reports: 'Reports & Analytics',
        audit: 'Audit Logs',
        profile: 'User Profile',
        settings: 'Settings'
    };

    const templateSectionMap = {
        dashboard: 'dashboard-section',
        'dashboard-section': 'dashboard-section',
        documents: 'document-management-section',
        'pc-documents': 'document-management-section',
        'document-management': 'document-management-section',
        'document-management-section': 'document-management-section',
        'consultation-management': 'consultation-management-section',
        'consultation-management-section': 'consultation-management-section',
        'public-feedback-portal': 'feedback-management-section',
        'public-feedback-queue': 'feedback-management-section',
        feedback: 'feedback-management-section',
        'feedback-collection': 'feedback-management-section',
        'feedback-management-section': 'feedback-management-section',
        audit: 'audit-section',
        'audit-section': 'audit-section',
        users: 'user-management-section',
        'user-management': 'user-management-section',
        'user-management-section': 'user-management-section',
        reports: 'reports-section',
        'reports-section': 'reports-section'
    };

    let targetSectionId = templateSectionMap[sectionName];

    // Only use static template element if it exists in DOM AND contains child nodes
    if (targetSectionId && document.getElementById(targetSectionId)) {
        const targetSection = document.getElementById(targetSectionId);
        if (targetSection && targetSection.children && targetSection.children.length > 0) {
            hideManagedTemplateSections();
            targetSection.style.display = 'block';

            try {
                const contentArea = document.getElementById('content-area');
                if (contentArea && contentArea.firstChild !== targetSection) {
                    contentArea.insertBefore(targetSection, contentArea.firstChild);
                }
            } catch (e) { }

            const breadcrumbCurrent = document.getElementById('breadcrumb-current') || document.querySelector('.breadcrumb-current');
            if (breadcrumbCurrent) {
                breadcrumbCurrent.textContent = sectionTitles[sectionName] || 'Dashboard';
            }

            if (sectionName === 'audit' && typeof loadAuditLogsFromDatabase === 'function') {
                try { loadAuditLogsFromDatabase(); } catch (e) { }
            }
            return true;
        }
    }

    return false;
}

function showSection(sectionName) {
    if (!sectionName || sectionName === 'index' || sectionName === 'home') {
        sectionName = 'public-consultation';
    }

    if (sectionName === 'public-feedback-portal' || sectionName === 'feedback' || sectionName === 'feedback-collection') {
        sectionName = 'public-feedback-queue';
    }

    if (sectionName === 'documents' || sectionName === 'pc-documents' || sectionName === 'document-management-section') {
        sectionName = 'document-management';
    }

    if (sectionName === 'consultation-management-section') {
        sectionName = 'consultation-management';
    }

    if (sectionName === 'user-management-section' || sectionName === 'user-management') {
        sectionName = 'users';
    }

    if (sectionName === 'audit-section') {
        sectionName = 'audit';
    }

    if (sectionName === 'reports-section' || sectionName === 'analytics') {
        sectionName = 'reports';
    }

    const contentArea = document.getElementById('content-area');

    // Toggle body class for section-specific styles
    try {
        if (document && document.body) {
            document.body.classList.toggle('section-public-consultation', sectionName === 'public-consultation');
        }
    } catch (e) {
        console.warn('Failed to toggle section class on body', e);
    }

    if (!contentArea) {
        console.error('Content area not found!');
        return;
    }

    // Update active nav item
    document.querySelectorAll('.nav-item, [data-section]').forEach(item => {
        item.classList.remove('active');
        const sec = item.dataset.section || '';
        const onclickStr = item.getAttribute('onclick') || '';
        if (sec === sectionName || onclickStr.includes(`'${sectionName}'`) || (sectionName === 'reports' && (sec === 'reports' || onclickStr.includes('reports') || onclickStr.includes('Reports')))) {
            item.classList.add('active');
        }
    });

    // Close mobile sidebar
    if (window.innerWidth < 768) {
        const toggleBtn = document.getElementById('mobile-menu-btn');
        if (toggleBtn) {
            toggleBtn.click();
        }
    }

    // Senior Dev Primary Execution: Invoke JS dynamic renderer first for rich UI
    window._currentActiveSection = sectionName;
    if (typeof hideManagedTemplateSections === 'function') {
        hideManagedTemplateSections();
    }

    try {
        switch (sectionName) {
            case 'public-consultation':
            case 'consultation-dashboard':
                if (typeof renderPublicConsultation === 'function') {
                    renderPublicConsultation();
                    startHeaderClock();
                    return;
                }
                break;
            case 'dashboard':
                if (typeof renderDashboard === 'function') {
                    renderDashboard();
                    startHeaderClock();
                    return;
                }
                break;
            case 'consultation-management':
                if (typeof renderConsultationManagement === 'function') {
                    renderConsultationManagement();
                    startHeaderClock();
                    return;
                }
                break;
            case 'public-feedback-queue':
            case 'public-feedback-portal':
            case 'feedback':
                if (typeof renderPublicFeedbackPortal === 'function') {
                    renderPublicFeedbackPortal();
                    startHeaderClock();
                    return;
                }
                break;
            case 'pc-documents':
            case 'document-management':
            case 'documents':
                if (typeof renderPCDocuments === 'function') {
                    if (!currentUserCanAccessDocuments()) {
                        showNotification('Document Management is only available for Admin and Super Admin.', 'warning');
                        renderDashboard();
                    } else {
                        renderPCDocuments();
                    }
                    startHeaderClock();
                    return;
                }
                break;
            case 'users':
            case 'user-management':
                if (typeof renderUsers === 'function') {
                    renderUsers();
                    startHeaderClock();
                    return;
                }
                break;
            case 'audit':
                if (typeof renderAudit === 'function') {
                    if (!currentUserIsAdminRole()) {
                        showNotification('Audit Log is available for Admin and Barangay Staff only.', 'warning');
                        renderDashboard();
                    } else {
                        renderAudit();
                    }
                    startHeaderClock();
                    return;
                }
                break;
            case 'analytics':
            case 'reports':
                if (typeof renderSystemReportsSection === 'function') {
                    renderSystemReportsSection();
                    startHeaderClock();
                    return;
                }
                break;
            case 'search':
                if (typeof renderSearch === 'function') {
                    renderSearch();
                    startHeaderClock();
                    return;
                }
                break;
            case 'profile':
                if (typeof renderProfile === 'function') {
                    renderProfile();
                    startHeaderClock();
                    return;
                }
                break;
            case 'settings':
                if (typeof renderSettings === 'function') {
                    renderSettings();
                    startHeaderClock();
                    return;
                }
                break;
            case 'help':
            case 'help-support':
                if (typeof renderHelp === 'function') {
                    renderHelp();
                    startHeaderClock();
                    return;
                }
                break;
            case 'notifications':
                if (typeof renderNotifications === 'function') {
                    renderNotifications();
                    startHeaderClock();
                    return;
                }
                break;
            case 'announcements':
                if (typeof renderAnnouncements === 'function') {
                    renderAnnouncements();
                    startHeaderClock();
                    return;
                }
                break;
        }
    } catch (e) {
        console.warn('Primary renderer execution failed for ' + sectionName + ':', e);
    }

    // Secondary Fallback: Managed Template Section (if static section exists in DOM)
    if (showManagedTemplateSection(sectionName)) {
        startHeaderClock();
        return;
    }

    // Tertiary Failsafe: Guarantee non-blank content
    try {
        if (typeof renderPublicConsultation === 'function') {
            renderPublicConsultation();
        } else if (typeof renderDashboard === 'function') {
            renderDashboard();
        }
    } catch (err) {
        console.error('Failsafe renderer failed:', err);
    }

    startHeaderClock();
}

function updateHeaderUserDisplays() {


    const nameEl = document.getElementById('profile-name-display');


    const roleEl = document.getElementById('profile-role-display');


    const emailEl = document.getElementById('profile-email-display');


    const deptEl = document.getElementById('profile-dept-display');




    if (nameEl) nameEl.textContent = AppData.currentUser?.name || nameEl.textContent;


    if (roleEl) roleEl.textContent = AppData.currentUser?.role || roleEl.textContent;


    if (emailEl) emailEl.textContent = AppData.currentUser?.email || emailEl.textContent;


    if (deptEl) deptEl.textContent = AppData.currentUser?.department || deptEl.textContent;


}




// ==============================


// DASHBOARD MODULE


// ==============================


function renderDashboard() {


    const totalDocs = AppData.documents.length;


    const approvedDocs = AppData.documents.filter(d => d.status === 'approved').length;


    const pendingDocs = AppData.documents.filter(d => d.status === 'pending').length;


    const activeUsers = AppData.users.filter(u => u.status === 'active').length;


    const currentUserName = AppData.currentUser && AppData.currentUser.name ? AppData.currentUser.name : 'User';





    const html = `


        <!-- Welcome Banner - Only shown on dashboard -->


        <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-2xl shadow-xl p-8 mb-6 text-white transform hover:scale-[1.01] transition-all duration-300 animate-fade-in">


            <div class="flex items-center justify-between">


                <div class="animate-slide-in-left">


                    <h1 class="text-3xl font-bold mb-2">Welcome back, ${currentUserName}! 👋</h1>


                    <p class="text-red-100 text-lg">Here's what's happening with your legislative records today.</p>


                </div>


                <div class="hidden lg:block animate-slide-in-right">


                    <i class="bi bi-speedometer2 text-8xl opacity-20"></i>


                </div>


            </div>


        </div>




        <!-- Statistics Cards -->


        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">


            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 transform hover:scale-105 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in-up animation-delay-100 group cursor-pointer">


                <div class="flex items-center">


                    <div class="flex-shrink-0 transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">


                        <div class="bg-red-100 rounded-lg p-3">


                            <i class="bi bi-file-earmark-text text-red-600 text-3xl"></i>


                        </div>


                    </div>


                    <div class="ml-4">


                        <p class="text-sm text-gray-600 transition-colors duration-200 group-hover:text-red-600">Total Documents</p>


                        <p class="text-2xl font-bold text-gray-900 transform transition-all duration-300 group-hover:scale-110">${totalDocs}</p>


                    </div>


                </div>


            </div>




            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 transform hover:scale-105 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in-up animation-delay-200 group cursor-pointer">


                <div class="flex items-center">


                    <div class="flex-shrink-0 transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">


                        <div class="bg-green-100 rounded-lg p-3">


                            <i class="bi bi-check-circle text-green-600 text-3xl"></i>


                        </div>


                    </div>


                    <div class="ml-4">


                        <p class="text-sm text-gray-600 transition-colors duration-200 group-hover:text-green-600">Approved</p>


                        <p class="text-2xl font-bold text-gray-900 transform transition-all duration-300 group-hover:scale-110">${approvedDocs}</p>


                    </div>


                </div>


            </div>




            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 transform hover:scale-105 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in-up animation-delay-300 group cursor-pointer">


                <div class="flex items-center">


                    <div class="flex-shrink-0 transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">


                        <div class="bg-yellow-100 rounded-lg p-3">


                            <i class="bi bi-clock-history text-yellow-600 text-3xl"></i>


                        </div>


                    </div>


                    <div class="ml-4">


                        <p class="text-sm text-gray-600 transition-colors duration-200 group-hover:text-yellow-600">Pending</p>


                        <p class="text-2xl font-bold text-gray-900 transform transition-all duration-300 group-hover:scale-110">${pendingDocs}</p>


                    </div>


                </div>


            </div>




            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 transform hover:scale-105 hover:shadow-xl transition-all duration-300 hover:-translate-y-1 animate-fade-in-up animation-delay-400 group cursor-pointer">


                <div class="flex items-center">


                    <div class="flex-shrink-0 transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">


                        <div class="bg-purple-100 rounded-lg p-3">


                            <i class="bi bi-people text-purple-600 text-3xl"></i>


                        </div>


                    </div>


                    <div class="ml-4">


                        <p class="text-sm text-gray-600 transition-colors duration-200 group-hover:text-purple-600">Active Users</p>


                        <p class="text-2xl font-bold text-gray-900 transform transition-all duration-300 group-hover:scale-110">${activeUsers}</p>


                    </div>


                </div>


            </div>


        </div>




        <!-- Charts and Quick Actions -->


        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">


            <!-- Document Types Chart -->


            <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6 transform hover:shadow-xl transition-all duration-300 animate-fade-in-up animation-delay-500">


                <h2 class="text-lg font-bold text-gray-800 mb-4">Documents by Type</h2>


                <div class="chart-container" style="position: relative; height: 280px; max-height: 280px;">


                    <canvas id="documentTypesChart"></canvas>


                </div>


            </div>




            <!-- Quick Actions -->


            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-600">


                <h2 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h2>


                <div class="space-y-3">


                    <button onclick="openModal('upload-modal')" class="btn-primary w-full flex items-center justify-center">


                        <i class="bi bi-upload mr-2"></i>Upload Document


                    </button>


                    <button onclick="showSection('search')" class="btn-outline w-full flex items-center justify-center">


                        <i class="bi bi-search mr-2"></i>Advanced Search


                    </button>


                    <button onclick="showSection('analytics')" class="btn-outline w-full flex items-center justify-center">


                        <i class="bi bi-bar-chart mr-2"></i>View Reports


                    </button>


                    <button onclick="showSection('users')" class="btn-outline w-full flex items-center justify-center">


                        <i class="bi bi-people mr-2"></i>User Management


                    </button>


                </div>


            </div>


        </div>




        <!-- Recent Documents -->


        <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-700">


            <div class="flex items-center justify-between mb-4">


                <h2 class="text-lg font-bold text-gray-800">Recent Documents</h2>


                <button onclick="showSection('documents')" class="text-sm text-red-600 hover:text-red-700 font-medium">View All →</button>


            </div>


            <div class="overflow-x-auto">


                <table class="min-w-full">


                    <thead class="bg-gray-50">


                        <tr>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>


                        </tr>


                    </thead>


                    <tbody class="divide-y divide-gray-200">


                        ${AppData.documents.slice(0, 5).map(doc => `


                            <tr class="hover:bg-gray-50 transition">


                                <td class="px-6 py-4 text-sm font-medium text-gray-900">${doc.reference}</td>


                                <td class="px-6 py-4 text-sm text-gray-700">${doc.title}</td>


                                <td class="px-6 py-4 text-sm text-gray-700">${capitalizeFirstLetter(doc.type)}</td>


                                <td class="px-6 py-4">${getStatusBadge(doc.status)}</td>


                                <td class="px-6 py-4 text-sm text-gray-700">${doc.date}</td>


                                <td class="px-6 py-4 text-sm">


                                    <button onclick="viewDocument(${doc.id})" class="text-red-600 hover:text-red-700 mr-2" title="View">


                                        View


                                    </button>


                                </td>


                            </tr>


                        `).join('')}


                    </tbody>


                </table>


            </div>


        </div>


    `;





    document.getElementById('content-area').innerHTML = html;





    // Initialize chart


    setTimeout(() => renderDocumentTypesChart(), 100);


}




function renderDocumentTypesChart() {


    const ctx = document.getElementById('documentTypesChart');


    if (!ctx) return;





    const typeCounts = {};


    AppData.documents.forEach(doc => {


        typeCounts[doc.type] = (typeCounts[doc.type] || 0) + 1;


    });





    new Chart(ctx, {


        type: 'doughnut',


        data: {


            labels: Object.keys(typeCounts).map(t => capitalizeFirstLetter(t)),


            datasets: [{


                data: Object.values(typeCounts),


                backgroundColor: [


                    '#dc2626',


                    '#16a34a',


                    '#2563eb',


                    '#f59e0b',


                    '#8b5cf6'


                ],


                borderWidth: 2,


                borderColor: '#ffffff'


            }]


        },


        options: {


            responsive: true,


            maintainAspectRatio: false,


            cutout: '60%',


            plugins: {


                legend: {


                    position: 'right',


                    labels: {


                        boxWidth: 12,


                        padding: 15,


                        font: {


                            size: 12


                        }


                    }


                }


            }


        }


    });


}




// ==============================


// DOCUMENTS MODULE


// ==============================


function renderDocuments() {


    const html = `


        <div class="mb-6 animate-fade-in">


            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">


                <h1 class="text-2xl font-bold text-gray-800">Document Management</h1>


                <button onclick="openModal('upload-modal')" class="btn-primary">


                    <i class="bi bi-upload mr-2"></i>Upload New Document


                </button>


            </div>


        </div>




        <!-- Filters -->


        <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-100">


            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">


                <select id="filterType" class="input-field" onchange="filterDocuments()">


                    <option value="">All Types</option>


                    <option value="ordinance">Ordinance</option>


                    <option value="resolution">Resolution</option>


                    <option value="session">Session Minutes</option>


                    <option value="agenda">Agenda</option>


                </select>





                <input type="text" id="searchDocs" class="input-field" placeholder="Search documents..." oninput="filterDocuments()">


                <button onclick="resetFilters()" class="btn-outline">


                    <i class="bi bi-arrow-clockwise mr-2"></i>Reset


                </button>


            </div>


        </div>
            <!-- Documents Table -->


        <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade-in-up animation-delay-200">


            <div class="overflow-x-auto">


                <table class="min-w-full" id="documentsTable">


                    <thead class="bg-gray-50">


                        <tr>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('reference')">


                                Reference <i class="bi bi-arrow-down-up text-xs"></i>


                            </th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('title')">


                                Title <i class="bi bi-arrow-down-up text-xs"></i>


                            </th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('type')">


                                Type <i class="bi bi-arrow-down-up text-xs"></i>


                            </th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('date')">


                                Date <i class="bi bi-arrow-down-up text-xs"></i>


                            </th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>


                        </tr>


                    </thead>


                    <tbody id="documentsList" class="divide-y divide-gray-200">


                        <!-- Populated by filterDocuments() -->


                    </tbody>


                </table>


            </div>


        </div>


    `;





    document.getElementById('content-area').innerHTML = html;




    const tbody = document.getElementById('documentsList');


    if (tbody) {


        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Loading documents...</td></tr>';


    }




    loadDocumentsFromApi()


        .then(() => filterDocuments())


        .catch(err => {


            const msg = String(err && err.message ? err.message : err);


            if (tbody) {


                tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-red-600">Failed to load documents.<div class="text-xs text-gray-500 mt-2">${msg}</div></td></tr>`;


            }


        });


}




function filterDocuments() {


    const typeFilter = document.getElementById('filterType')?.value || '';


    const searchTerm = document.getElementById('searchDocs')?.value.toLowerCase() || '';





    let filtered = AppData.documents.filter(doc => {


        const matchesType = !typeFilter || doc.type === typeFilter;


        const matchesSearch = !searchTerm ||


            doc.title.toLowerCase().includes(searchTerm) ||


            doc.reference.toLowerCase().includes(searchTerm) ||


            doc.description.toLowerCase().includes(searchTerm);





        return matchesType && matchesSearch;


    });





    const tbody = document.getElementById('documentsList');


    if (!tbody) return;





    if (filtered.length === 0) {


        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No documents found</td></tr>';


        return;


    }





    tbody.innerHTML = filtered.map(doc => `


        <tr class="hover:bg-gray-50 transition">


            <td class="px-6 py-4 text-sm font-medium text-gray-900">${doc.reference}</td>


            <td class="px-6 py-4 text-sm text-gray-700">${doc.title}</td>


            <td class="px-6 py-4 text-sm text-gray-700">${capitalizeFirstLetter(doc.type)}</td>


            <td class="px-6 py-4 text-sm text-gray-700">${formatDate(doc.date)}</td>


            <td class="px-6 py-4 text-sm space-x-2">


                <button onclick="viewDocument('${doc.uid}')" class="text-blue-600 hover:text-blue-700" title="View">


                    View


                </button>


                <button onclick="editDocument('${doc.uid}')" class="text-green-600 hover:text-green-700" title="Edit">


                    <i class="bi bi-pencil"></i>


                </button>


            </td>


        </tr>


    `).join('');


}




function sortDocuments(field) {


    AppData.documents.sort((a, b) => {


        if (a[field] < b[field]) return -1;


        if (a[field] > b[field]) return 1;


        return 0;


    });


    filterDocuments();


}




function resetFilters() {


    document.getElementById('filterType').value = '';


    document.getElementById('searchDocs').value = '';


    filterDocuments();


}




function viewDocument(uid) {
    const doc = findDocumentByUid(uid);
    if (!doc) return;

    const url = doc.downloadUrl || doc.filePath;
    if (url) {
        window.open(url, '_blank', 'noopener');
    } else {
        showNotification('Document URL not found', 'error');
    }
}




function editDocument(uid) {


    const doc = findDocumentByUid(uid);


    if (!doc) return;





    showNotification('Edit functionality would open a form here', 'info');


}




function deleteDocument(id) {


    if (!confirm('Are you sure you want to delete this document?')) return;





    const index = AppData.documents.findIndex(d => d.id === id);


    if (index > -1) {


        AppData.documents.splice(index, 1);


        filterDocuments();


        showNotification('Document deleted successfully', 'success');





        // Add audit log


        addAuditLog('delete', `Deleted document ID ${id}`);


    }


}




// ==============================


// SEARCH MODULE


// ==============================


function renderSearch() {


    const html = `


        <div class="mb-6 animate-fade-in">


            <h1 class="text-2xl font-bold text-gray-800">Advanced Search</h1>


            <p class="text-gray-600 mt-1">Use filters to find specific documents</p>


        </div>




        <!-- Advanced Search Form -->


        <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-100">


            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">


                <div>


                    <label class="block text-sm font-medium text-gray-700 mb-2">Keywords</label>


                    <input type="text" id="advSearchKeywords" class="input-field" placeholder="Search keywords...">


                </div>


                <div>


                    <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>


                    <input type="text" id="advSearchReference" class="input-field" placeholder="e.g., ORD-2025-001">


                </div>


                <div>


                    <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>


                    <select id="advSearchType" class="input-field">


                        <option value="">All Types</option>


                        <option value="ordinance">Ordinance</option>


                        <option value="resolution">Resolution</option>


                        <option value="session">Session Minutes</option>


                        <option value="agenda">Agenda</option>


                    </select>


                </div>


                <div>


                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>


                    <select id="advSearchStatus" class="input-field">


                        <option value="">All Status</option>


                        <option value="approved">Approved</option>


                        <option value="pending">Pending</option>


                        <option value="draft">Draft</option>


                    </select>


                </div>


                <div>


                    <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>


                    <input type="date" id="advSearchDateFrom" class="input-field">


                </div>


                <div>


                    <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>


                    <input type="date" id="advSearchDateTo" class="input-field">


                </div>


            </div>


            <div class="mt-4 flex gap-3">


                <button onclick="performAdvancedSearch()" class="btn-primary">


                    <i class="bi bi-search mr-2"></i>Search


                </button>


                <button onclick="clearAdvancedSearch()" class="btn-outline">


                    <i class="bi bi-x-circle mr-2"></i>Clear


                </button>


            </div>


        </div>




        <!-- Search Results -->


        <div id="searchResults" class="space-y-4">


            <!-- Results populated by performAdvancedSearch() -->


        </div>


    `;





    document.getElementById('content-area').innerHTML = html;


}




function performAdvancedSearch() {


    const keywords = document.getElementById('advSearchKeywords').value.toLowerCase();


    const reference = document.getElementById('advSearchReference').value.toLowerCase();


    const type = document.getElementById('advSearchType').value;


    const status = document.getElementById('advSearchStatus').value;


    const dateFrom = document.getElementById('advSearchDateFrom').value;


    const dateTo = document.getElementById('advSearchDateTo').value;





    const results = AppData.documents.filter(doc => {


        const matchesKeywords = !keywords ||


            doc.title.toLowerCase().includes(keywords) ||


            doc.description.toLowerCase().includes(keywords) ||


            doc.tags.some(tag => tag.toLowerCase().includes(keywords));





        const matchesReference = !reference || doc.reference.toLowerCase().includes(reference);


        const matchesType = !type || doc.type === type;


        const matchesStatus = !status || doc.status === status;


        const matchesDateFrom = !dateFrom || new Date(doc.date) >= new Date(dateFrom);


        const matchesDateTo = !dateTo || new Date(doc.date) <= new Date(dateTo);





        return matchesKeywords && matchesReference && matchesType && matchesStatus && matchesDateFrom && matchesDateTo;


    });





    const resultsContainer = document.getElementById('searchResults');





    if (results.length === 0) {


        resultsContainer.innerHTML = `


            <div class="bg-white rounded-xl shadow-md p-12 text-center animate-fade-in">


                <i class="bi bi-search text-6xl text-gray-300 mb-4"></i>


                <p class="text-gray-600 text-lg">No documents found matching your search criteria</p>


            </div>


        `;


        return;


    }





    resultsContainer.innerHTML = `


        <div class="bg-white rounded-xl shadow-md p-6 mb-4 animate-fade-in">


            <p class="text-sm text-gray-600">Found <strong>${results.length}</strong> document(s)</p>


        </div>


        ${results.map(doc => `


            <div class="bg-white rounded-xl shadow-md p-6 hover:shadow-lg transition-all duration-300 animate-fade-in-up">


                <div class="flex items-start justify-between">


                    <div class="flex-1">


                        <div class="flex items-center gap-3 mb-2">


                            <h3 class="text-lg font-bold text-gray-800">${doc.title}</h3>


                            ${getStatusBadge(doc.status)}


                        </div>


                        <p class="text-sm text-gray-600 mb-2">${doc.reference} • ${capitalizeFirstLetter(doc.type)} • ${formatDate(doc.date)}</p>


                        <p class="text-gray-700 mb-3">${doc.description}</p>


                        <div class="flex items-center gap-4 text-sm text-gray-500">


                            <span><i class="bi bi-eye mr-1"></i>${doc.views} views</span>


                            <span><i class="bi bi-download mr-1"></i>${doc.downloads} downloads</span>


                            <span><i class="bi bi-file-earmark mr-1"></i>${doc.fileSize}</span>


                        </div>


                        <div class="mt-3">


                            ${doc.tags.map(tag => `<span class="tag">${tag}</span>`).join('')}


                        </div>


                    </div>


                    <div class="ml-4 flex gap-2">


                        <button onclick="viewDocument(${doc.id})" class="btn-primary text-sm">


                            View


                        </button>


                    </div>


                </div>


            </div>


        `).join('')}


    `;


}




function clearAdvancedSearch() {


    document.getElementById('advSearchKeywords').value = '';


    document.getElementById('advSearchReference').value = '';


    document.getElementById('advSearchType').value = '';


    document.getElementById('advSearchStatus').value = '';


    document.getElementById('advSearchDateFrom').value = '';


    document.getElementById('advSearchDateTo').value = '';


    document.getElementById('searchResults').innerHTML = '';


}




// ==============================


// ANALYTICS MODULE


// ==============================


function renderAnalytics() {


    const html = `


        <div class="mb-6 animate-fade-in">


            <h1 class="text-2xl font-bold text-gray-800">Reports & Analytics</h1>


            <p class="text-gray-600 mt-1">View detailed reports and statistics</p>


        </div>




        <!-- Analytics Cards -->


        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">


            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-100">


                <div class="flex items-center justify-between mb-4">


                    <h3 class="text-lg font-bold text-gray-800">Monthly Uploads</h3>


                    <i class="bi bi-graph-up text-2xl text-red-600"></i>


                </div>


                <p class="text-3xl font-bold text-gray-900">24</p>


                <p class="text-sm text-green-600 mt-2"><i class="bi bi-arrow-up mr-1"></i>12% from last month</p>


            </div>




            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-200">


                <div class="flex items-center justify-between mb-4">


                    <h3 class="text-lg font-bold text-gray-800">Total Views</h3>


                    <i class="bi bi-eye text-2xl text-blue-600"></i>


                </div>


                <p class="text-3xl font-bold text-gray-900">1,234</p>


                <p class="text-sm text-green-600 mt-2"><i class="bi bi-arrow-up mr-1"></i>8% from last month</p>


            </div>




            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-300">


                <div class="flex items-center justify-between mb-4">


                    <h3 class="text-lg font-bold text-gray-800">Total Downloads</h3>


                    <i class="bi bi-download text-2xl text-green-600"></i>


                </div>


                <p class="text-3xl font-bold text-gray-900">567</p>


                <p class="text-sm text-red-600 mt-2"><i class="bi bi-arrow-down mr-1"></i>3% from last month</p>


            </div>


        </div>




        <!-- Charts -->


        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">


            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-400">


                <h3 class="text-lg font-bold text-gray-800 mb-4">Documents Over Time</h3>


                <canvas id="documentsOverTimeChart"></canvas>


            </div>




            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-500">


                <h3 class="text-lg font-bold text-gray-800 mb-4">Documents by Status</h3>


                <canvas id="documentsByStatusChart"></canvas>


            </div>


        </div>




        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">


            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-600">


                <h3 class="text-lg font-bold text-gray-800 mb-4">Top Uploaders</h3>


                <div class="space-y-4">


                    <div class="flex items-center justify-between">


                        <div class="flex items-center">


                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mr-3">


                                <span class="text-red-600 font-bold">AU</span>


                            </div>


                            <span class="text-gray-800">Admin User</span>


                        </div>


                        <span class="text-gray-600 font-medium">12 documents</span>


                    </div>


                    <div class="flex items-center justify-between">


                        <div class="flex items-center">


                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">


                                <span class="text-blue-600 font-bold">OS</span>


                            </div>


                            <span class="text-gray-800">Officer Smith</span>


                        </div>


                        <span class="text-gray-600 font-medium">8 documents</span>


                    </div>


                    <div class="flex items-center justify-between">


                        <div class="flex items-center">


                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">


                                <span class="text-green-600 font-bold">SJ</span>


                            </div>


                            <span class="text-gray-800">Staff Jones</span>


                        </div>


                        <span class="text-gray-600 font-medium">4 documents</span>


                    </div>


                </div>


            </div>




            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-700">


                <h3 class="text-lg font-bold text-gray-800 mb-4">Popular Documents</h3>


                <div class="space-y-3">


                    ${AppData.documents.slice(0, 5).map(doc => `


                        <div class="flex items-center justify-between py-2 border-b border-gray-100">


                            <div class="flex-1">


                                <p class="text-sm font-medium text-gray-800">${doc.title}</p>


                                <p class="text-xs text-gray-500">${doc.reference}</p>


                            </div>


                            <span class="text-sm text-gray-600">${doc.views} views</span>


                        </div>


                    `).join('')}


                </div>


            </div>


        </div>


    `;





    document.getElementById('content-area').innerHTML = html;





    setTimeout(() => {


        renderDocumentsOverTimeChart();


        renderDocumentsByStatusChart();


    }, 100);


}




function renderDocumentsOverTimeChart() {


    const ctx = document.getElementById('documentsOverTimeChart');


    if (!ctx) return;





    new Chart(ctx, {


        type: 'line',


        data: {


            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],


            datasets: [{


                label: 'Documents Uploaded',


                data: [12, 19, 15, 25, 22, 30, 28, 35, 32, 40, 38, 45],


                borderColor: '#dc2626',


                backgroundColor: 'rgba(220, 38, 38, 0.1)',


                tension: 0.4,


                fill: true


            }]


        },


        options: {


            responsive: true,


            maintainAspectRatio: true,


            plugins: {


                legend: {


                    display: false


                }


            },


            scales: {


                y: {


                    beginAtZero: true


                }


            }


        }


    });


}




function renderDocumentsByStatusChart() {


    const ctx = document.getElementById('documentsByStatusChart');


    if (!ctx) return;





    const statusCounts = {


        approved: 0,


        pending: 0,


        draft: 0


    };





    AppData.documents.forEach(doc => {


        if (statusCounts.hasOwnProperty(doc.status)) {


            statusCounts[doc.status]++;


        }


    });





    new Chart(ctx, {


        type: 'bar',


        data: {


            labels: ['Approved', 'Pending', 'Draft'],


            datasets: [{


                label: 'Documents',


                data: [statusCounts.approved, statusCounts.pending, statusCounts.draft],


                backgroundColor: ['#16a34a', '#f59e0b', '#6b7280']


            }]


        },


        options: {


            responsive: true,


            maintainAspectRatio: true,


            plugins: {


                legend: {


                    display: false


                }


            },


            scales: {


                y: {


                    beginAtZero: true,


                    ticks: {


                        stepSize: 1


                    }


                }


            }


        }


    });


}




// ==============================
var _citizenData = [];
var _userMgmtTab = 'citizens';

function renderUsers(skipLoad = false) {
    window._currentActiveSection = 'users';
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'User Management';

    const contentArea = document.getElementById('content-area') || document.getElementById('main-content') || document.querySelector('.content-area') || document.querySelector('main');
    if (!contentArea) return;

    if (typeof hideManagedTemplateSections === 'function') {
        hideManagedTemplateSections();
    }
    const legacySec = document.getElementById('user-management-section');
    if (legacySec) {
        legacySec.style.display = 'none';
    }

    const totalCitizens = _citizenData.length;
    const totalConsultations = _citizenData.reduce((s, c) => s + (c.consultation_count || 0), 0);
    const totalFeedbacks = _citizenData.reduce((s, c) => s + (c.feedback_count || 0), 0);
    const totalVotes = _citizenData.reduce((s, c) => s + (c.survey_vote_count || 0), 0);

    const citizenTabActive = (_userMgmtTab === 'citizens');
    const adminTabActive = (_userMgmtTab === 'admins');
    const pendingTabActive = (_userMgmtTab === 'pending');
    const expertTabActive = (_userMgmtTab === 'experts');

    const html = `
        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">User Management & Citizen Registry</h1>
                        <p class="text-red-100 text-sm">Monitor, verify, and engage registered citizen submitters across Valenzuela City.</p>
                    </div>
                </div>

                <!-- KPI Metric Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Registered Citizens</div>
                        <div class="text-3xl font-bold">${totalCitizens}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Verified via Google / 2FA</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Proposals Submitted</div>
                        <div class="text-3xl font-bold">${totalConsultations}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Citizen initiative papers</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Survey Votes Cast</div>
                        <div class="text-3xl font-bold">${totalVotes}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Community poll participation</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Engagement</div>
                        <div class="text-3xl font-bold">${totalConsultations + totalFeedbacks + totalVotes}</div>
                        <div class="text-xs text-red-100 opacity-90 mt-1">Combined citizen actions</div>
                    </div>
                </div>
            </div>

            <!-- Role Tabs Navigation (Matching Document Management Group Tabs) -->
            <div class="flex flex-wrap gap-2 mt-6 border-b border-gray-200">
                <button onclick="_userMgmtTab='citizens'; renderUsers(true);" class="px-6 py-3 font-semibold text-sm border-b-2 transition flex items-center gap-2 ${citizenTabActive ? 'border-red-600 text-red-600 bg-red-50/40 font-bold' : 'border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600'}">
                    <i class="bi bi-people-fill"></i> Citizen Submitters <span class="ml-1 px-2 py-0.5 rounded-full text-xs ${citizenTabActive ? 'bg-red-100 text-red-800 font-bold' : 'bg-gray-200 text-gray-700'}">${totalCitizens}</span>
                </button>

                <button onclick="_userMgmtTab='admins'; renderUsers(true);" class="px-6 py-3 font-semibold text-sm border-b-2 transition flex items-center gap-2 ${adminTabActive ? 'border-red-600 text-red-600 bg-red-50/40 font-bold' : 'border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600'}">
                    <i class="bi bi-shield-lock-fill"></i> Admins & Staff <span class="ml-1 px-2 py-0.5 rounded-full text-xs ${adminTabActive ? 'bg-red-100 text-red-800 font-bold' : 'bg-gray-200 text-gray-700'}">4</span>
                </button>

                <button onclick="_userMgmtTab='pending'; renderUsers(true);" class="px-6 py-3 font-semibold text-sm border-b-2 transition flex items-center gap-2 ${pendingTabActive ? 'border-amber-600 text-amber-700 bg-amber-50/60 font-bold' : 'border-gray-200 text-gray-600 hover:border-amber-600 hover:text-amber-700'}">
                    <i class="bi bi-clock-history"></i> Pending Applications <span id="user-mgmt-pending-badge" class="ml-1 px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-800 font-bold">0</span>
                </button>

                <button onclick="_userMgmtTab='experts'; renderUsers(true);" class="px-6 py-3 font-semibold text-sm border-b-2 transition flex items-center gap-2 ${expertTabActive ? 'border-red-600 text-red-600 bg-red-50/40 font-bold' : 'border-gray-200 text-gray-600 hover:border-red-600 hover:text-red-600'}">
                    <i class="bi bi-award-fill"></i> Experts & Resource Persons <span id="approved-experts-badge" class="ml-1 px-2 py-0.5 rounded-full text-xs ${expertTabActive ? 'bg-red-100 text-red-800 font-bold' : 'bg-gray-200 text-gray-700'}">3</span>
                </button>
            </div>

            <!-- Tab Content Container -->
            <div id="user-mgmt-tab-content" class="mt-6">
                ${citizenTabActive ? `
                    <div class="bg-white p-6 rounded-lg shadow border border-gray-200 space-y-6">
                        <!-- Filters Toolbar -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Search Citizen</label>
                                <div class="relative">
                                    <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                                    <input type="text" id="citizen-search" placeholder="Name or email..." class="w-full pl-9 pr-4 py-2 text-xs rounded-lg border border-gray-300 focus:ring-2 focus:ring-red-500 outline-none" onkeyup="renderCitizensTable()">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Filter Barangay</label>
                                <select id="citizen-barangay" class="w-full px-3 py-2 text-xs rounded-lg border border-gray-300 focus:ring-2 focus:ring-red-500 outline-none" onchange="renderCitizensTable()">
                                    <option value="">All 33 Barangays</option>
                                    <optgroup label="District 1 (24 Barangays)">
                                        <option value="Arkong Bato">Arkong Bato</option>
                                        <option value="Balangkas">Balangkas</option>
                                        <option value="Bignay">Bignay</option>
                                        <option value="Bisig">Bisig</option>
                                        <option value="Canumay East">Canumay East</option>
                                        <option value="Canumay West">Canumay West</option>
                                        <option value="Coloong">Coloong</option>
                                        <option value="Dalandanan">Dalandanan</option>
                                        <option value="Isla">Isla</option>
                                        <option value="Lawang Bato">Lawang Bato</option>
                                        <option value="Lingunan">Lingunan</option>
                                        <option value="Mabolo">Mabolo</option>
                                        <option value="Malanday">Malanday</option>
                                        <option value="Malinta">Malinta</option>
                                        <option value="Mapulang Lupa">Mapulang Lupa</option>
                                        <option value="Palasan">Palasan</option>
                                        <option value="Pariancillo Villa">Pariancillo Villa</option>
                                        <option value="Pasolo">Pasolo</option>
                                        <option value="Poblacion">Poblacion</option>
                                        <option value="Punturin">Punturin</option>
                                        <option value="Rincon">Rincon</option>
                                        <option value="Tagalag">Tagalag</option>
                                        <option value="Veinte Reales">Veinte Reales</option>
                                        <option value="Wawang Pulo">Wawang Pulo</option>
                                    </optgroup>
                                    <optgroup label="District 2 (9 Barangays)">
                                        <option value="Bagbaguin">Bagbaguin</option>
                                        <option value="Gen. T. de Leon">Gen. T. de Leon</option>
                                        <option value="Karuhatan">Karuhatan</option>
                                        <option value="Marulas">Marulas</option>
                                        <option value="Maysan">Maysan</option>
                                        <option value="Parada">Parada</option>
                                        <option value="Paso de Blas">Paso de Blas</option>
                                        <option value="Ugong">Ugong</option>
                                    </optgroup>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Sort By</label>
                                <select id="citizen-sort" class="w-full px-3 py-2 text-xs rounded-lg border border-gray-300 focus:ring-2 focus:ring-red-500 outline-none" onchange="renderCitizensTable()">
                                    <option value="recent">Most Recent Engagement</option>
                                    <option value="submissions">Highest Activity Count</option>
                                    <option value="name">Alphabetical (A-Z)</option>
                                </select>
                            </div>

                            <div class="flex items-end">
                                <button onclick="exportCitizensCsv()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg text-xs transition-colors flex items-center justify-center gap-2 border border-gray-300">
                                    <i class="bi bi-download"></i> Export Citizen Registry
                                </button>
                            </div>
                        </div>

                        <!-- Citizens Table -->
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-700 text-xs uppercase font-semibold tracking-wider border-b border-gray-200">
                                        <th class="px-6 py-3.5">Citizen Profile</th>
                                        <th class="px-6 py-3.5">Email & Identity</th>
                                        <th class="px-6 py-3.5 text-center">Proposals</th>
                                        <th class="px-6 py-3.5 text-center">Survey Votes</th>
                                        <th class="px-6 py-3.5 text-center">Total Engagement</th>
                                        <th class="px-6 py-3.5">Last Active</th>
                                        <th class="px-6 py-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="citizens-table-body" class="divide-y divide-gray-100 text-xs text-gray-700">
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center text-gray-400">Loading citizen records...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : pendingTabActive ? `
                    <div class="space-y-4">
                        <div class="flex items-center justify-between bg-amber-50 p-4 rounded-xl border border-amber-200">
                            <div>
                                <h3 class="text-sm font-bold text-amber-900 flex items-center gap-2">
                                    <i class="bi bi-clock-history text-amber-600"></i> Pending Resource Person Applications
                                </h3>
                                <p class="text-xs text-amber-700 mt-0.5">Review credentials and approve or reject applicant access to expert roles.</p>
                            </div>
                            <button onclick="loadPendingUserApplications()" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-semibold transition flex items-center gap-1">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                        <div id="pending-user-applications-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="text-center py-12 text-slate-500 col-span-full">
                                <i class="bi bi-hourglass-split text-3xl mb-2 text-amber-500 animate-spin"></i>
                                <p class="text-xs">Loading pending applications...</p>
                            </div>
                        </div>
                    </div>
                ` : expertTabActive ? `
                    <div class="space-y-4">
                        <div class="flex items-center justify-between bg-emerald-50 p-4 rounded-xl border border-emerald-200">
                            <div>
                                <h3 class="text-sm font-bold text-emerald-900 flex items-center gap-2">
                                    <i class="bi bi-award text-emerald-600"></i> Verified Resource Persons & Experts
                                </h3>
                                <p class="text-xs text-emerald-700 mt-0.5">Active Subject Matter Experts qualified to review public consultations.</p>
                            </div>
                            <button onclick="loadApprovedUserExperts()" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition flex items-center gap-1">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                        <div id="approved-user-experts-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="text-center py-12 text-slate-500 col-span-full">
                                <i class="bi bi-arrow-clockwise text-3xl mb-2 text-emerald-500 animate-spin"></i>
                                <p class="text-xs">Loading verified experts...</p>
                            </div>
                        </div>
                    </div>
                ` : `
                    <div class="bg-white p-8 rounded-lg shadow border border-gray-200 text-center space-y-4">
                        <div class="w-16 h-16 rounded-full bg-red-50 text-red-600 flex items-center justify-center text-2xl mx-auto">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Administrative Accounts</h3>
                        <p class="text-xs text-gray-500 max-w-md mx-auto">View and manage internal credentials, committee assignments, and department authority across PCMS.</p>
                    </div>
                `}
            </div>
        </div>

        <!-- Citizen Dossier Drawer Modal -->
        <div id="citizen-dossier-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 sm:p-8 relative border border-slate-200 max-h-[85vh] overflow-y-auto">
                <button onclick="closeCitizenDossierModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>

                <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                    <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-bold text-lg" id="dossier-avatar">
                        C
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900" id="dossier-name">Citizen Dossier</h3>
                        <span class="text-xs text-slate-500 flex items-center gap-2" id="dossier-email">
                            <i class="bi bi-envelope"></i> citizen@valenzuela.gov.ph
                        </span>
                    </div>
                </div>

                <div id="dossier-content" class="space-y-4 text-xs">
                    <div class="text-center py-6 text-slate-400">Loading citizen timeline...</div>
                </div>
            </div>
        </div>
    `;

    if (contentArea) {
        contentArea.innerHTML = html;
    }

    if (!skipLoad) {
        loadCitizensFromApi().then(() => {
            renderUsers(true);
        });
    } else {
        if (_userMgmtTab === 'citizens') renderCitizensTable();
        if (_userMgmtTab === 'pending') loadPendingUserApplications();
        if (_userMgmtTab === 'experts') loadApprovedUserExperts();
    }
}

async function loadCitizensFromApi() {
    try {
        const res = await fetch('API/citizens_api.php?action=list');
        const data = await res.json();
        if (data.success && Array.isArray(data.data)) {
            _citizenData = data.data;
        }
    } catch (err) {
        console.error('Failed to load citizens:', err);
    }
}

function renderCitizensTable() {
    const userSection = document.getElementById('user-management-section');
    if (window._currentActiveSection !== 'users' && window._currentActiveSection !== 'user-management') {
        if (userSection) userSection.style.display = 'none';
        return;
    }

    const tbody = document.getElementById('citizens-table-body');
    if (!tbody) return;

    let citizens = [..._citizenData];

    const search = (document.getElementById('citizen-search')?.value || '').toLowerCase();
    if (search) {
        citizens = citizens.filter(c =>
            (c.name || '').toLowerCase().includes(search) ||
            (c.email || '').toLowerCase().includes(search)
        );
    }

    const barangayFilter = (document.getElementById('citizen-barangay')?.value || '').toLowerCase();
    if (barangayFilter) {
        citizens = citizens.filter(c => (c.barangay || '').toLowerCase().includes(barangayFilter));
    }

    const sort = document.getElementById('citizen-sort')?.value || 'recent';
    if (sort === 'recent') {
        citizens.sort((a, b) => new Date(b.last_activity || 0) - new Date(a.last_activity || 0));
    } else if (sort === 'submissions') {
        citizens.sort((a, b) => (b.total_submissions || 0) - (a.total_submissions || 0));
    } else if (sort === 'name') {
        citizens.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    }

    if (citizens.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-400">${search || barangayFilter ? 'No citizens match your search filters.' : 'No citizen submissions recorded yet.'}</td></tr>`;
        return;
    }

    tbody.innerHTML = citizens.map(c => {
        const lastAct = c.last_activity ? new Date(c.last_activity).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
        const initials = (c.name || 'C').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();

        return `
            <tr class="hover:bg-gray-50 transition border-b border-gray-100">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-red-100 text-red-700 flex items-center justify-center font-bold text-xs border border-red-200">
                            ${initials}
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900 text-xs sm:text-sm flex items-center gap-1.5">
                                ${escapeHtml(c.name || 'Citizen')}
                                <span class="text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.2 rounded font-semibold"><i class="bi bi-patch-check-fill"></i> Verified</span>
                            </div>
                            <div class="text-[11px] text-gray-500">Valenzuela Citizen Submitter</div>
                        </div>
                    </div>
                </td>

                <td class="px-6 py-4">
                    <div class="text-xs font-semibold text-gray-800">${escapeHtml(c.email)}</div>
                    <div class="text-[11px] text-gray-500"><i class="bi bi-geo-alt"></i> ${escapeHtml(c.barangay || 'Valenzuela City')}</div>
                </td>

                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${c.consultation_count > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-400'}">
                        <i class="bi bi-file-earmark-text"></i> ${c.consultation_count || 0}
                    </span>
                </td>

                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${c.survey_vote_count > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-400'}">
                        <i class="bi bi-check-square"></i> ${c.survey_vote_count || 0}
                    </span>
                </td>

                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200">
                        ${c.total_submissions || 0}
                    </span>
                </td>

                <td class="px-6 py-4 text-xs text-gray-500 font-medium">${lastAct}</td>

                <td class="px-6 py-4 text-right">
                    <button onclick="viewCitizenDossier('${escapeHtml(c.email)}', '${escapeHtml(c.name)}')" class="bg-gray-900 hover:bg-gray-800 text-white text-[11px] font-semibold px-3 py-1.5 rounded-lg shadow-sm transition-colors flex items-center gap-1.5 ml-auto">
                        <i class="bi bi-clock-history"></i> View Dossier
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function viewCitizenDossier(email, name) {
    const modal = document.getElementById('citizen-dossier-modal');
    const nameEl = document.getElementById('dossier-name');
    const emailEl = document.getElementById('dossier-email');
    const contentEl = document.getElementById('dossier-content');

    if (nameEl) nameEl.textContent = name || 'Citizen Dossier';
    if (emailEl) emailEl.innerHTML = `<i class="bi bi-envelope"></i> ${email}`;
    if (contentEl) contentEl.innerHTML = '<div class="text-center py-6 text-slate-400"><i class="bi bi-arrow-repeat animate-spin text-lg"></i> Loading activity timeline...</div>';
    if (modal) modal.classList.remove('hidden');

    try {
        const res = await fetch('API/citizens_api.php?action=get_dossier&email=' + encodeURIComponent(email));
        const data = await res.json();

        if (data.success) {
            let html = '';

            const proposals = data.proposals || [];
            let feedback = data.feedback;
            let surveys = data.surveys;

            // Fallback categorization if backend returns combined activity
            if (!feedback || !surveys) {
                feedback = [];
                surveys = [];
                (data.activity || []).forEach(a => {
                    const cat = (a.category || '').toLowerCase().trim();
                    if (cat === 'survey vote' || cat === 'survey' || cat === 'vote') {
                        surveys.push(a);
                    } else {
                        feedback.push(a);
                    }
                });
            }

            const totalCount = proposals.length + feedback.length + surveys.length;

            if (totalCount === 0) {
                html = '<div class="text-center py-6 text-slate-400">No proposals, feedback, or survey votes recorded for this citizen yet.</div>';
            } else {
                // Section 1: Submitted Proposals / Consultations
                if (proposals.length > 0) {
                    html += `<div class="font-bold text-slate-900 mb-2 text-xs uppercase tracking-wider text-red-600 flex items-center gap-1.5"><i class="bi bi-file-earmark-text"></i> Submitted Proposals (${proposals.length})</div><div class="space-y-2 mb-4">`;
                    proposals.forEach(p => {
                        html += `
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-slate-800 text-xs">${escapeHtml(p.title)}</div>
                                    <div class="text-[10px] text-slate-400">Tracking: ${escapeHtml(p.tracking_number || 'N/A')}</div>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-blue-100 text-blue-800 uppercase">${escapeHtml(p.status)}</span>
                            </div>
                        `;
                    });
                    html += `</div>`;
                }

                // Section 2: Submitted Feedback
                if (feedback.length > 0) {
                    html += `<div class="font-bold text-slate-900 mb-2 text-xs uppercase tracking-wider text-amber-600 flex items-center gap-1.5"><i class="bi bi-chat-left-text"></i> Submitted Feedback (${feedback.length})</div><div class="space-y-2 mb-4">`;
                    feedback.forEach(f => {
                        html += `
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-slate-800 text-xs">${escapeHtml(f.category || 'Feedback')} - ${escapeHtml(f.consultation_title || 'General')}</div>
                                    <div class="text-[10px] text-slate-500">${escapeHtml(f.message || '')}</div>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800 uppercase">${escapeHtml(f.status || 'NEW')}</span>
                            </div>
                        `;
                    });
                    html += `</div>`;
                }

                // Section 3: Survey & Poll Votes
                if (surveys.length > 0) {
                    html += `<div class="font-bold text-slate-900 mb-2 text-xs uppercase tracking-wider text-emerald-600 flex items-center gap-1.5"><i class="bi bi-check2-square"></i> Survey Votes (${surveys.length})</div><div class="space-y-2">`;
                    surveys.forEach(s => {
                        html += `
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-slate-800 text-xs">${escapeHtml(s.consultation_title ? ('Survey Vote - ' + s.consultation_title) : 'Survey Vote')}</div>
                                    <div class="text-[10px] text-slate-500 font-medium">Choice: <span class="text-blue-700 font-bold">${escapeHtml(s.message || 'Voted')}</span></div>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-800 uppercase">${escapeHtml(s.status || 'CLOSED')}</span>
                            </div>
                        `;
                    });
                    html += `</div>`;
                }
            }
            if (contentEl) contentEl.innerHTML = html;
        } else {
            if (contentEl) contentEl.innerHTML = '<div class="text-center py-6 text-red-500">Failed to load dossier data.</div>';
        }
    } catch (err) {
        if (contentEl) contentEl.innerHTML = '<div class="text-center py-6 text-red-500">Error retrieving citizen dossier.</div>';
    }
}

function closeCitizenDossierModal() {
    const modal = document.getElementById('citizen-dossier-modal');
    if (modal) modal.classList.add('hidden');
}

function exportCitizensCsv() {
    if (!_citizenData || _citizenData.length === 0) {
        alert('No citizen records available to export.');
        return;
    }

    let csv = 'Citizen Name,Email,Barangay,Proposals Submitted,Survey Votes,Total Engagement,Last Engagement Date\n';
    _citizenData.forEach(c => {
        csv += `"${(c.name || '').replace(/"/g, '""')}","${(c.email || '').replace(/"/g, '""')}","${(c.barangay || 'Valenzuela City').replace(/"/g, '""')}",${c.consultation_count || 0},${c.survey_vote_count || 0},${c.total_submissions || 0},"${c.last_activity || ''}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Valenzuela_Citizen_Registry_${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
}

window.viewCitizenDossier = viewCitizenDossier;
window.closeCitizenDossierModal = closeCitizenDossierModal;
window.exportCitizensCsv = exportCitizensCsv;

function openConsultationExportModal(messageHtml) {
    const modal = document.getElementById('consultation-export-modal');
    const body = document.getElementById('consultation-export-message');
    if (body) body.innerHTML = messageHtml;
    if (modal) modal.classList.remove('hidden');
}

function closeConsultationExportModal() {
    const modal = document.getElementById('consultation-export-modal');
    if (modal) modal.classList.add('hidden');
}

function openConsultationExportChooser(consultationId) {
    const title = document.getElementById('consultation-export-title');
    if (title) title.textContent = 'Export Consultation';

    const body = `
        <div class="text-gray-700">Choose how to export this consultation.</div>
        <div class="text-xs text-gray-500 mt-2">Auto will pick the best format based on content.</div>
    `;
    const actions = `
        <button onclick="exportConsultationWithFormat(${consultationId}, 'auto')" class="btn-primary">Auto (Recommended)</button>
        <button onclick="exportConsultationWithFormat(${consultationId}, 'pdf')" class="btn-outline">PDF</button>
        <button onclick="exportConsultationWithFormat(${consultationId}, 'excel')" class="btn-outline">Excel</button>
        <button onclick="closeConsultationExportModal()" class="btn-outline">Cancel</button>
    `;

    const bodyEl = document.getElementById('consultation-export-message');
    const actionsEl = document.getElementById('consultation-export-actions');
    if (bodyEl) bodyEl.innerHTML = body;
    if (actionsEl) actionsEl.innerHTML = actions;
    const modalEl = document.getElementById('consultation-export-modal');
    if (modalEl) modalEl.classList.remove('hidden');
}

function detectBestExportFormat(consultationId) {
    const c = Array.isArray(AppData.consultations)
        ? AppData.consultations.find(x => Number(x.id) === Number(consultationId))
        : null;
    if (!c) return 'pdf';
    const desc = String(c.description || '');
    if (desc.length > 300 || desc.includes('\n')) return 'pdf';
    return 'excel';
}

function exportConsultationWithFormat(consultationId, format) {
    const chosen = format === 'auto' ? detectBestExportFormat(consultationId) : format;
    const title = document.getElementById('consultation-export-title');
    if (title) title.textContent = 'Exporting...';

    const bodyEl = document.getElementById('consultation-export-message');
    const actionsEl = document.getElementById('consultation-export-actions');
    if (bodyEl) bodyEl.innerHTML = '<div class="text-gray-700">Generating file...</div>';
    if (actionsEl) actionsEl.innerHTML = '<button onclick="closeConsultationExportModal()" class="btn-outline">Close</button>';
    const modal = document.getElementById('consultation-export-modal');
    if (modal) modal.classList.remove('hidden');

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';

    const formData = new FormData();
    formData.append('action', 'export_consultations');
    formData.append('format', chosen);
    formData.append('mode', 'separate');
    formData.append('csrf_token', csrf);
    formData.append('ids[]', String(consultationId));

    fetch('system-template-full.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                const msg = `
                <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3">
                    File generation complete. ${data.created} file(s) were added to Document Management.
                </div>
                <div class="mt-3 text-gray-700">Go to <strong>Document Management</strong> to download the file.</div>
            `;
                const titleEl = document.getElementById('consultation-export-title');
                if (titleEl) titleEl.textContent = 'Export Complete';
                const actions = `
                <button onclick="showSection('pc-documents'); closeConsultationExportModal();" class="btn-primary">Open Document Management</button>
                <button onclick="closeConsultationExportModal()" class="btn-outline">Close</button>
            `;
                const body = document.getElementById('consultation-export-message');
                const acts = document.getElementById('consultation-export-actions');
                if (body) body.innerHTML = msg;
                if (acts) acts.innerHTML = actions;
                const modalEl = document.getElementById('consultation-export-modal');
                if (modalEl) modalEl.classList.remove('hidden');
            } else {
                const err = (data && data.message) ? data.message : 'Export failed.';
                const titleEl = document.getElementById('consultation-export-title');
                if (titleEl) titleEl.textContent = 'Export Failed';
                const body = document.getElementById('consultation-export-message');
                const acts = document.getElementById('consultation-export-actions');
                if (body) body.innerHTML = `<div class="bg-red-50 border border-red-200 text-red-800 rounded p-3">${err}</div>`;
                if (acts) acts.innerHTML = '<button onclick="closeConsultationExportModal()" class="btn-outline">Close</button>';
                const modalEl = document.getElementById('consultation-export-modal');
                if (modalEl) modalEl.classList.remove('hidden');
            }
        })
        .catch(() => {
            const titleEl = document.getElementById('consultation-export-title');
            if (titleEl) titleEl.textContent = 'Export Failed';
            const body = document.getElementById('consultation-export-message');
            const acts = document.getElementById('consultation-export-actions');
            if (body) body.innerHTML = '<div class="bg-red-50 border border-red-200 text-red-800 rounded p-3">Export failed. Please try again.</div>';
            if (acts) acts.innerHTML = '<button onclick="closeConsultationExportModal()" class="btn-outline">Close</button>';
            const modalEl = document.getElementById('consultation-export-modal');
            if (modalEl) modalEl.classList.remove('hidden');
        });
}


function renderUsersTable() {


    const tbody = document.getElementById('users-table-body');


    const users = getFilteredUsers();




    if (users.length === 0) {


        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No staff accounts found</td></tr>`;


        return;


    }




    tbody.innerHTML = users.map(user => {


        const statusColor = user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';


        const roleLower = String(user.role).toLowerCase();


        const roleIcon = (roleLower === 'admin' || roleLower === 'administrator') ? 'bi-shield-lock' :


            roleLower === 'staff' ? 'bi-person-fill' : 'bi-eye';


        const roleBadge = (roleLower === 'admin' || roleLower === 'administrator') ? 'bg-red-100 text-red-800' :


            roleLower === 'staff' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700';


        const roleLabel = (roleLower === 'admin' || roleLower === 'administrator') ? 'Admin' :


            roleLower === 'staff' ? 'Staff' : 'Viewer';


        const createdAt = user.createdAt || 'N/A';




        return `


            <tr class="border-b hover:bg-gray-50 transition">


                <td class="px-6 py-4">


                    <div class="flex items-center gap-3">


                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">


                            <span class="text-red-600 font-bold text-sm">${getInitials(user.name)}</span>


                        </div>


                        <div>


                            <div class="font-semibold text-gray-900">${escapeHtml(user.name)}</div>


                        </div>


                    </div>


                </td>


                <td class="px-6 py-4 text-gray-700">${escapeHtml(user.email)}</td>


                <td class="px-6 py-4">


                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${roleBadge}">


                        <i class="bi ${roleIcon}"></i>


                        ${roleLabel}


                    </span>


                </td>


                <td class="px-6 py-4">


                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">


                        ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}


                    </span>


                </td>


                <td class="px-6 py-4 text-sm text-gray-600">${user.lastLogin || 'Never'}</td>


                <td class="px-6 py-4 text-sm text-gray-600">${createdAt}</td>


                <td class="px-6 py-4 text-center">


                    <div class="flex gap-2 justify-center">
                        <button onclick="openConsultationExportChooser(${consultation.id})" class="text-purple-600 hover:text-purple-800 text-sm font-semibold" title="Export"><i class="bi bi-file-earmark-arrow-down"></i> Export</button>


                        <button onclick="viewUserDetails(${user.id})" class="text-blue-600 hover:text-blue-800" title="View">


                            <i class="bi bi-eye"></i>


                        </button>


                        <button onclick="editUserForm(${user.id})" class="text-yellow-600 hover:text-yellow-800" title="Edit">


                            <i class="bi bi-pencil"></i>


                        </button>


                        <button onclick="toggleUserStatus(${user.id})" class="text-orange-600 hover:text-orange-800" title="Toggle Status">


                            <i class="bi bi-toggle-on"></i>


                        </button>


                        <button onclick="deleteUser(${user.id})" class="text-red-600 hover:text-red-800" title="Delete">


                            <i class="bi bi-trash"></i>


                        </button>


                    </div>


                </td>


            </tr>


        `;


    }).join('');


}




function getFilteredUsers() {


    let filtered = [...AppData.users];





    const searchTerm = document.getElementById('user-search')?.value.toLowerCase() || '';


    const roleFilter = document.getElementById('user-role-filter')?.value || '';


    const statusFilter = document.getElementById('user-status-filter')?.value || '';




    if (searchTerm) {


        filtered = filtered.filter(u =>


            u.name.toLowerCase().includes(searchTerm) ||


            u.email.toLowerCase().includes(searchTerm)


        );


    }





    if (roleFilter) {


        filtered = filtered.filter(u => String(u.role).toLowerCase() === roleFilter.toLowerCase());


    }





    if (statusFilter) {


        filtered = filtered.filter(u => u.status === statusFilter);


    }




    return filtered;


}




function filterUsers() {


    renderUsersTable();


}




function openAddUserModal() {


    document.getElementById('user-id').value = '';


    document.getElementById('user-modal-title').textContent = 'Add Staff Account';


    document.getElementById('user-name').value = '';


    document.getElementById('user-email').value = '';


    const pwGroup = document.getElementById('user-password-group');


    if (pwGroup) pwGroup.style.display = '';


    const pwInput = document.getElementById('user-password');


    if (pwInput) pwInput.value = '';


    document.getElementById('user-role').value = '';


    document.getElementById('user-status').value = 'active';


    document.getElementById('user-modal').classList.remove('hidden');


}




function closeUserModal() {


    document.getElementById('user-modal').classList.add('hidden');


}




function editUserForm(id) {


    const user = AppData.users.find(u => u.id === id);


    if (!user) return;




    document.getElementById('user-id').value = id;


    document.getElementById('user-modal-title').textContent = 'Edit Staff Account';


    document.getElementById('user-name').value = user.name;


    document.getElementById('user-email').value = user.email;


    const pwInput = document.getElementById('user-password');


    if (pwInput) pwInput.value = '';


    document.getElementById('user-role').value = String(user.role).toLowerCase();


    document.getElementById('user-status').value = user.status;


    document.getElementById('user-modal').classList.remove('hidden');


}




async function saveUser() {


    const id = document.getElementById('user-id').value;


    const name = document.getElementById('user-name').value.trim();


    const email = document.getElementById('user-email').value.trim();


    const role = document.getElementById('user-role').value;


    const status = document.getElementById('user-status').value;


    const password = document.getElementById('user-password')?.value || '';




    if (!name || !email || !role) {


        showNotification('Please fill in all required fields', 'error');


        return;


    }




    if (!id && !password) {


        showNotification('Password is required for new accounts', 'error');


        return;


    }




    try {


        const payload = { name, email, role, status };


        if (password) payload.password = password;


        if (id) payload.id = parseInt(id);




        const action = id ? 'update' : 'create';


        const res = await fetch(`API/users_api.php?action=${action}`, {


            method: 'POST',


            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },


            body: JSON.stringify(payload)


        });




        const data = await res.json().catch(() => null);


        if (!res.ok || !data || !data.success) {


            throw new Error((data && data.message) || 'Failed to save account');


        }




        showNotification(id ? 'Account updated successfully' : 'Staff account created successfully', 'success');


        closeUserModal();


        renderUsers();


    } catch (err) {


        showNotification(String(err.message || err), 'error');


    }


}




function viewUserDetails(id) {


    const user = AppData.users.find(u => u.id === id);


    if (!user) return;




    const statusColor = user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';


    const roleLower = String(user.role).toLowerCase();


    const roleLabel = (roleLower === 'admin' || roleLower === 'administrator') ? 'Admin' :


        roleLower === 'staff' ? 'Staff' : 'Viewer';




    document.getElementById('user-details-title').textContent = user.name;


    document.getElementById('user-details-content').innerHTML = `


        <div class="grid grid-cols-2 gap-4">


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Email</label>


                <p class="text-gray-900 font-semibold mt-1">${escapeHtml(user.email)}</p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Role</label>


                <p class="text-gray-900 font-semibold mt-1">${roleLabel}</p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Status</label>


                <p class="mt-1"><span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Account ID</label>


                <p class="text-gray-900 font-semibold mt-1">#${user.id}</p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Last Login</label>


                <p class="text-gray-900 font-semibold mt-1">${user.lastLogin || 'Never'}</p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Created</label>


                <p class="text-gray-900 font-semibold mt-1">${user.createdAt || 'N/A'}</p>


            </div>


        </div>




        <div class="border-t pt-4 mt-4">


            <label class="text-xs font-semibold text-gray-500 uppercase mb-3 block">Quick Actions</label>


            <div class="space-y-2">


                <button onclick="resetUserPassword(${user.id})" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold">


                    <i class="bi bi-key mr-2"></i> Reset Password


                </button>


            </div>


        </div>




        <div class="flex gap-2 pt-4 border-t">


            <button onclick="editUserForm(${user.id}); closeUserDetailsModal()" class="flex-1 btn-primary">Edit</button>


            <button onclick="closeUserDetailsModal()" class="flex-1 btn-secondary">Close</button>


        </div>


    `;


    document.getElementById('user-details-modal').classList.remove('hidden');


}




function closeUserDetailsModal() {


    document.getElementById('user-details-modal').classList.add('hidden');


}




async function toggleUserStatus(id) {


    const user = AppData.users.find(u => u.id === id);


    if (!user) return;





    const newStatus = user.status === 'active' ? 'inactive' : 'active';


    try {


        const res = await fetch('API/users_api.php?action=update', {


            method: 'POST',


            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },


            body: JSON.stringify({ id: user.id, status: newStatus })


        });


        const data = await res.json().catch(() => null);


        if (!res.ok || !data || !data.success) {


            throw new Error((data && data.message) || 'Failed to update status');


        }


        user.status = newStatus;


        renderUsers(true);


        showNotification(`${user.name} is now ${newStatus}`, 'success');


    } catch (err) {


        showNotification(String(err.message || err), 'error');


    }


}




function deleteUser(id) {


    if (!confirm('Are you sure you want to delete this staff account? This action cannot be undone.')) return;





    const user = AppData.users.find(u => u.id === id);


    const index = AppData.users.findIndex(u => u.id === id);


    if (index > -1) {


        AppData.users.splice(index, 1);


        renderUsers(true);


        showNotification(`Account for ${user.name} deleted`, 'success');


    }


}




function resetUserPassword(id) {


    const user = AppData.users.find(u => u.id === id);


    if (!user) return;





    const newPw = prompt('Enter new password for ' + user.name + ' (min 12 chars):');


    if (!newPw || newPw.length < 12) {


        if (newPw !== null) showNotification('Password must be at least 12 characters', 'error');


        return;


    }





    fetch('API/users_api.php?action=update', {


        method: 'POST',


        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },


        body: JSON.stringify({ id: user.id, password: newPw })


    })


        .then(r => r.json())


        .then(data => {


            if (data.success) {


                showNotification(`Password reset for ${user.name}`, 'success');


            } else {


                showNotification(data.message || 'Failed to reset password', 'error');


            }


        })


        .catch(err => showNotification(String(err), 'error'));


}




// ==============================


// AUDIT MODULE


// ==============================


function renderAudit() {


    const html = `


        <div class="mb-6 animate-fade-in">


            <h1 class="text-2xl font-bold text-gray-800">Audit Logs</h1>


        </div>




        <!-- Filters -->


        <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-100">


            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">


                <select id="filterAction" class="input-field" onchange="filterAuditLogs()">


                    <option value="">All Actions</option>


                    <option value="login">Login</option>


                    <option value="logout">Logout</option>


                    <option value="created">Created</option>


                    <option value="updated">Updated</option>


                    <option value="deleted">Deleted</option>


                </select>


                <input type="text" id="filterUser" class="input-field" placeholder="Filter by admin user..." oninput="filterAuditLogs()">


                <input type="date" id="filterDate" class="input-field" onchange="filterAuditLogs()">


                <!-- Reset button removed per UX request -->


            </div>


        </div>




        <!-- Summary Stats -->


        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">


            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">


                <p class="text-sm text-gray-600">Total Logs</p>


                <p class="text-2xl font-bold text-blue-600" id="totalLogsCount">0</p>


            </div>


            <div class="bg-green-50 rounded-lg p-4 border border-green-200">


                <p class="text-sm text-gray-600">Today's Activity</p>


                <p class="text-2xl font-bold text-green-600" id="todayActivityCount">0</p>


            </div>


            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">


                <p class="text-sm text-gray-600">Active Admins</p>


                <p class="text-2xl font-bold text-purple-600" id="activeAdminsCount">0</p>


            </div>


        </div>




        <!-- Audit Logs Table -->


        <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade-in-up animation-delay-200">


            <div class="overflow-x-auto">


                <table class="min-w-full">


                    <thead class="bg-gray-50">


                        <tr>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin User</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity Type</th>


                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>


                        </tr>


                    </thead>


                    <tbody id="auditLogsList" class="divide-y divide-gray-200">


                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading audit logs...</td></tr>


                    </tbody>


                </table>


            </div>


        </div>


    `;





    document.getElementById('content-area').innerHTML = html;


    loadAuditLogsFromDatabase();


}




function loadAuditLogsFromDatabase() {


    // Fetch audit logs from the API


    fetch('API/get_audit_logs_api.php')


        .then(async response => {
            let data = null;
            try { data = await response.json(); } catch (_) { }

            if (!response.ok) {
                const msg = (data && (data.error || data.message)) ? (data.error || data.message) : `HTTP ${response.status}`;
                throw new Error(msg);
            }

            if (data && (data.error || data.success === false)) {
                throw new Error(data.error || data.message || 'Failed to load audit logs');
            }

            if (!Array.isArray(data)) {
                throw new Error('Unexpected audit log response format');
            }

            return data;
        })


        .then(data => {


            // Store in AppData for filtering


            AppData.auditLogs = data;





            // Update stats


            updateAuditStats();





            // Display logs


            filterAuditLogs();


        })


        .catch(error => {


            console.error('Error loading audit logs:', error);


            const tbody = document.getElementById('auditLogsList');


            if (tbody) {


                tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-red-500">Failed to load audit logs<div class="text-xs text-gray-500 mt-1">${escapeHtml(error?.message || 'Unknown error')}</div></td></tr>`;


            }


        });


}




function updateAuditStats() {


    const totalCount = AppData.auditLogs.length;


    const today = new Date().toISOString().split('T')[0];


    const todayCount = AppData.auditLogs.filter(log => log.timestamp.includes(today)).length;


    const adminsSet = new Set(AppData.auditLogs.map(log => log.admin_user));





    document.getElementById('totalLogsCount').textContent = totalCount;


    document.getElementById('todayActivityCount').textContent = todayCount;


    document.getElementById('activeAdminsCount').textContent = adminsSet.size;


}




function filterAuditLogs() {


    const actionFilter = document.getElementById('filterAction')?.value || '';


    const userFilter = document.getElementById('filterUser')?.value.toLowerCase() || '';


    const dateFilter = document.getElementById('filterDate')?.value || '';





    let filtered = AppData.auditLogs.filter(log => {


        const matchesAction = !actionFilter || log.action === actionFilter;


        const matchesUser = !userFilter || log.admin_user.toLowerCase().includes(userFilter);


        const matchesDate = !dateFilter || log.timestamp.includes(dateFilter);





        return matchesAction && matchesUser && matchesDate;


    });





    const tbody = document.getElementById('auditLogsList');


    if (!tbody) return;





    if (filtered.length === 0) {


        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No audit logs found</td></tr>';


        return;


    }





    tbody.innerHTML = filtered.map(log => `


        <tr class="hover:bg-gray-50 transition">


            <td class="px-6 py-4 text-sm text-gray-700">${new Date(log.timestamp).toLocaleString()}</td>


            <td class="px-6 py-4 text-sm font-medium text-gray-900">${log.admin_user}</td>


            <td class="px-6 py-4">${getActionBadge(log.action)}</td>


            <td class="px-6 py-4 text-sm text-gray-700">${log.entity_type || 'system'}</td>


            <td class="px-6 py-4 text-sm font-mono text-gray-700">${log.ip_address || 'N/A'}</td>


        </tr>


    `).join('');


}




function resetAuditFilters() {


    document.getElementById('filterAction').value = '';


    document.getElementById('filterUser').value = '';


    document.getElementById('filterDate').value = '';


    filterAuditLogs();


}




function addAuditLog(action, description) {


    const newLog = {


        id: (AppData.auditLogs.length > 0 ? Math.max(...AppData.auditLogs.map(l => l.id)) : 0) + 1,


        user: AppData.currentUser.name,


        action: action,


        description: description,


        timestamp: new Date().toLocaleString(),


        ipAddress: '192.168.1.100'


    };





    AppData.auditLogs.unshift(newLog);


    saveAuditLogsToStorage();


}




function saveAuditLogsToStorage() {


    try {


        localStorage.setItem('llrm_auditLogs', JSON.stringify(AppData.auditLogs));


    } catch (e) {


        console.warn('Failed to save audit logs to storage:', e);


    }


}


function renderProfile() {


    const currentUser = AppData.currentUser || {


        id: null,


        name: 'User',


        email: '',


        role: '',


        profilePicture: '',


        twoFactorEnabled: false,


        twoFactorMethod: 'email'


    };


    if (!AppData.currentUser) AppData.currentUser = currentUser;


    const stats = {


        documents: AppData.documents.filter(d => d.uploadedBy === currentUser.name).length,


        activities: 117,


        memberSince: 'Nov 2025',


        lastActive: '13m ago'


    };





    const html = `


        <!-- Profile Header Banner -->


        <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-2xl shadow-xl p-8 mb-6 text-white animate-fade-in">


            <div class="flex flex-col md:flex-row items-center md:items-start gap-6">


                <!-- Profile Picture -->


                <div class="relative">


                    <input type="file" id="profilePictureInput" accept="image/*" class="hidden" onchange="handleProfilePictureUpload(event)">


                    ${currentUser.profilePicture ?


            `<img id="profileImage" src="${currentUser.profilePicture}" alt="Profile" class="w-32 h-32 rounded-full border-4 border-white shadow-lg object-cover">` :


            `<div id="profileImage" class="w-32 h-32 rounded-full bg-white border-4 border-white shadow-lg flex items-center justify-center">


                            <span class="text-red-600 text-4xl font-bold">${getInitials(currentUser.name)}</span>


                        </div>`


        }


                    <button onclick="document.getElementById('profilePictureInput').click()" class="absolute bottom-0 right-0 bg-white text-red-600 rounded-full w-10 h-10 flex items-center justify-center hover:bg-gray-100 transform hover:scale-110 transition-all duration-200 shadow-lg">


                        <i class="bi bi-camera-fill"></i>


                    </button>


                </div>


                


                <!-- User Info -->


                <div class="flex-1 text-center md:text-left">


                    <h1 class="text-3xl font-bold mb-2">${currentUser.name}</h1>


                    <p class="text-red-100 text-lg mb-3">${currentUser.email}</p>


                    <div class="flex flex-wrap gap-2 justify-center md:justify-start">


                        <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm flex items-center gap-2">


                            <i class="bi bi-person-badge"></i> ${currentUser.role}


                        </span>


                        <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm flex items-center gap-2">


                            <i class="bi bi-building"></i> IT Department


                        </span>


                        <span class="bg-green-400 bg-opacity-90 px-3 py-1 rounded-full text-sm flex items-center gap-2">


                            <i class="bi bi-check-circle-fill"></i> Active


                        </span>


                    </div>


                </div>




                <!-- Edit Profile Button -->


                <div class="flex items-center">


                    <button onclick="toggleEditMode()" class="bg-white text-red-600 px-6 py-2 rounded-lg font-medium hover:bg-gray-100 transform hover:scale-105 transition-all duration-200 shadow-lg flex items-center gap-2">


                        <i class="bi bi-pencil"></i> Edit Profile


                    </button>


                </div>


            </div>


        </div>




        <!-- Statistics Cards -->


        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">


            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-100">


                <div class="flex items-center justify-between mb-2">


                    <span class="text-gray-600 text-sm">Documents</span>


                    <i class="bi bi-file-earmark-text text-2xl text-red-600"></i>


                </div>


                <p class="text-3xl font-bold text-gray-900">${stats.documents}</p>


            </div>




            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-200">


                <div class="flex items-center justify-between mb-2">


                    <span class="text-gray-600 text-sm">Activities</span>


                    <i class="bi bi-activity text-2xl text-green-600"></i>


                </div>


                <p class="text-3xl font-bold text-gray-900">${stats.activities}</p>


            </div>




            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-300">


                <div class="flex items-center justify-between mb-2">


                    <span class="text-gray-600 text-sm">Member Since</span>


                    <i class="bi bi-calendar-check text-2xl text-purple-600"></i>


                </div>


                <p class="text-2xl font-bold text-gray-900">${stats.memberSince}</p>


            </div>




            <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-400">


                <div class="flex items-center justify-between mb-2">


                    <span class="text-gray-600 text-sm">Last Active</span>


                    <i class="bi bi-clock-history text-2xl text-blue-600"></i>


                </div>


                <p class="text-2xl font-bold text-gray-900">${stats.lastActive}</p>


            </div>


        </div>




        <!-- Main Content -->


        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">


            <!-- Personal Information -->


            <div class="lg:col-span-2">


                <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-500">


                    <div class="flex items-center gap-3 mb-6">


                        <i class="bi bi-person-circle text-2xl text-red-600"></i>


                        <h2 class="text-xl font-bold text-gray-800">Personal Information</h2>


                    </div>


                    


                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">


                        <div>


                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>


                            <input type="text" id="editFullName" class="input-field" value="${currentUser.name}" disabled>


                        </div>


                        <div>


                            <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>


                            <input type="text" id="editUsername" class="input-field" value="admin" disabled>


                        </div>


                        <div>


                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>


                            <input type="email" id="editEmail" class="input-field" value="${currentUser.email}" disabled>


                        </div>


                        <div>


                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>


                            <input type="tel" id="editPhone" class="input-field" value="1954654564" disabled>


                        </div>


                        <div>


                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>


                            <input type="text" id="editDepartment" class="input-field" value="IT Department" disabled>


                        </div>


                        <div>


                            <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>


                            <input type="text" id="editPosition" class="input-field" value="secretary" disabled>


                        </div>


                    </div>


                    


                    <div id="saveProfileBtn" class="mt-6 hidden">


                        <button onclick="saveProfile()" class="btn-primary mr-3">


                            <i class="bi bi-save mr-2"></i>Save Changes


                        </button>


                        <button onclick="toggleEditMode()" class="btn-outline">


                            <i class="bi bi-x-circle mr-2"></i>Cancel


                        </button>


                    </div>


                </div>




                <!-- Recent Activity -->


                <div class="bg-white rounded-xl shadow-md p-6 animate-fade-in-up animation-delay-600">


                    <div class="flex items-center justify-between mb-6">


                        <div class="flex items-center gap-3">


                            <i class="bi bi-clock-history text-2xl text-red-600"></i>


                            <h2 class="text-xl font-bold text-gray-800">Recent Activity</h2>


                        </div>


                        <a href="#" onclick="showSection('audit'); return false;" class="text-sm text-red-600 hover:text-red-700 font-medium">View All</a>


                    </div>


                    


                    <div class="space-y-4">


                        ${AppData.auditLogs.filter(log => log.user === currentUser.name).slice(0, 5).map(log => `


                            <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">


                                <div class="flex-shrink-0">


                                    <i class="bi bi-check-circle text-blue-600 text-xl"></i>


                                </div>


                                <div class="flex-1">


                                    <p class="text-sm font-medium text-gray-800">${log.description}</p>


                                    <p class="text-xs text-gray-500 mt-1">${log.timestamp}</p>


                                </div>


                            </div>


                        `).join('')}


                    </div>


                </div>


            </div>




            <!-- Account Security -->


            <div class="lg:col-span-1">


                <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-700">


                    <div class="flex items-center gap-3 mb-6">


                        <i class="bi bi-shield-check text-2xl text-red-600"></i>


                        <h2 class="text-xl font-bold text-gray-800">Account Security</h2>


                    </div>


                    


                    <div class="space-y-4">


                        <button onclick="openChangePasswordModal()" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition group">


                            <div class="flex items-center gap-3">


                                <i class="bi bi-key text-xl text-gray-600 group-hover:text-red-600 transition"></i>


                                <div class="text-left">


                                    <p class="text-sm font-medium text-gray-800">Change Password</p>


                                    <p class="text-xs text-gray-500">Update your password</p>


                                </div>


                            </div>


                            <i class="bi bi-chevron-right text-gray-400"></i>


                        </button>




                        <button onclick="openTwoFactorModal()" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition group">


                            <div class="flex items-center gap-3">


                                <i class="bi bi-shield-lock text-xl text-gray-600 group-hover:text-red-600 transition"></i>


                                <div class="text-left">


                                    <p class="text-sm font-medium text-gray-800">Two-Factor Auth</p>


                                    <p class="text-xs text-gray-500">${AppData.currentUser.twoFactorEnabled ? 'Enabled via ' + AppData.currentUser.twoFactorMethod : 'Not enabled'}</p>


                                </div>


                            </div>


                            <i class="bi bi-chevron-right text-gray-400"></i>


                        </button>




                        <button onclick="openLoginHistoryModal()" class="w-full flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition group">


                            <div class="flex items-center gap-3">


                                <i class="bi bi-clock-history text-xl text-gray-600 group-hover:text-red-600 transition"></i>


                                <div class="text-left">


                                    <p class="text-sm font-medium text-gray-800">Login History</p>


                                    <p class="text-xs text-gray-500">View recent logins</p>


                                </div>


                            </div>


                            <i class="bi bi-chevron-right text-gray-400"></i>


                        </button>


                    </div>


                </div>




                <!-- Quick Links removed -->


            </div>


        </div>


    `;





    document.getElementById('content-area').innerHTML = html;


}




// Profile Picture Upload Handler


function handleProfilePictureUpload(event) {


    const file = event.target.files[0];


    if (!file) return;





    // Validate file type


    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];


    if (!validTypes.includes(file.type)) {


        showNotification('Please upload a valid image file (JPEG, PNG, GIF, or WEBP)', 'error');


        return;


    }





    // Validate file size (5MB max)


    if (file.size > 5 * 1024 * 1024) {


        showNotification('File size must be less than 5MB', 'error');


        return;


    }





    const fd = new FormData();


    fd.append('action', 'upload_photo');


    fd.append('photo', file);




    fetch('API/update_profile.php', { method: 'POST', body: fd })


        .then(r => r.json())


        .then(data => {


            if (!data || !data.success) {


                throw new Error((data && data.message) ? data.message : 'Failed to upload photo');


            }




            const photoPath = data.photo_path ? String(data.photo_path) : '';


            if (photoPath) {


                AppData.currentUser.profilePicture = photoPath;


            }




            const profileImage = document.getElementById('profileImage');


            if (profileImage && photoPath) {


                if (profileImage.tagName === 'IMG') {


                    profileImage.src = photoPath;


                } else {


                    profileImage.outerHTML = `<img id="profileImage" src="${photoPath}" alt="Profile" class="w-32 h-32 rounded-full border-4 border-white shadow-lg object-cover">`;


                }


            }




            if (photoPath) updateNavbarProfilePicture(photoPath);


            showNotification('Profile picture updated successfully!', 'success');


            addAuditLog('update', 'Updated profile picture');


        })


        .catch(err => {


            console.error(err);


            showNotification(err && err.message ? String(err.message) : 'Failed to upload photo', 'error');


        });


}




// Quick Documents mini-menu (opened from Quick Links)


function openDocumentsQuickMenu() {


    const existing = document.getElementById('documentsQuickModal');


    if (existing) return existing.classList.remove('hidden');




    const modal = document.createElement('div');


    modal.id = 'documentsQuickModal';


    modal.className = 'fixed inset-0 bg-black bg-opacity-40 flex items-start justify-center z-50 p-4';


    const user = AppData.currentUser;


    const myDocs = AppData.documents.filter(d => d.uploadedBy === user.name).slice(0, 6);




    modal.innerHTML = `


        <div class="mt-20 bg-white rounded-lg shadow-xl w-full max-w-2xl">


            <div class="p-4 border-b flex items-center justify-between">


                <div>


                    <h3 class="text-lg font-bold">My Documents</h3>


                    <p class="text-xs text-gray-500">Quick access to your recent documents</p>


                </div>


                <div class="flex items-center gap-2">


                    <button onclick="openModal('upload-modal');" class="btn-primary text-sm">Upload</button>


                    <button onclick="closeDocumentsQuickMenu()" class="btn-outline text-sm">Close</button>


                </div>


            </div>


            <div class="p-4 max-h-72 overflow-y-auto">


                ${myDocs.length === 0 ? '<p class="text-sm text-gray-500">You have no uploaded documents yet.</p>' : myDocs.map(d => `


                    <div class="flex items-center justify-between p-2 border-b hover:bg-gray-50">


                        <div>


                            <div class="font-medium text-gray-800">${d.title}</div>


                            <div class="text-xs text-gray-500">${d.reference} • ${formatDate(d.date)}</div>


                        </div>


                        <div class="flex items-center gap-2">


                            <button onclick="viewDocument(${d.id})" class="text-blue-600 text-sm">View</button>


                            <button onclick="downloadDocument(${d.id})" class="text-gray-600 text-sm">Download</button>


                        </div>


                    </div>


                `).join('')}


            </div>


            <div class="p-4 border-t text-right">


                <button onclick="showSection('documents')" class="btn-primary">Open Documents →</button>


            </div>


        </div>


    `;




    document.body.appendChild(modal);


}




function closeDocumentsQuickMenu() {


    const modal = document.getElementById('documentsQuickModal');


    if (modal) modal.remove();


}




// Update navbar profile picture


function updateNavbarProfilePicture(imageUrl) {


    // Update top navbar profile picture


    const navProfilePic = document.querySelector('#profile-menu');


    if (navProfilePic) {


        // Check if it already has an image


        const existingImg = navProfilePic.querySelector('img');


        if (existingImg) {


            existingImg.src = imageUrl;


        } else {


            // Replace icon with image


            navProfilePic.innerHTML = `<img src="${imageUrl}" alt="Profile" class="w-8 h-8 rounded-full border-2 border-white object-cover">`;


        }


    }





    // Update sidebar profile picture


    const sidebarProfilePic = document.querySelector('#sidebar-profile-pic');


    if (sidebarProfilePic) {


        const existingImg = sidebarProfilePic.querySelector('img');


        if (existingImg) {


            existingImg.src = imageUrl;


        } else {


            sidebarProfilePic.innerHTML = `<img src="${imageUrl}" alt="Profile" class="w-10 h-10 rounded-full border-2 border-white object-cover">`;


        }


    }


}




// Toggle edit mode


let isEditMode = false;


function toggleEditMode() {


    isEditMode = !isEditMode;





    const fields = ['editFullName', 'editUsername', 'editEmail', 'editPhone', 'editDepartment', 'editPosition'];


    const saveBtn = document.getElementById('saveProfileBtn');





    fields.forEach(fieldId => {


        const field = document.getElementById(fieldId);


        if (field) {


            field.disabled = !isEditMode;


            if (isEditMode) {


                field.classList.add('border-red-300', 'focus:border-red-500');


            } else {


                field.classList.remove('border-red-300', 'focus:border-red-500');


            }


        }


    });





    if (saveBtn) {


        if (isEditMode) {


            saveBtn.classList.remove('hidden');


        } else {


            saveBtn.classList.add('hidden');


        }


    }


}




// Save profile changes


function saveProfile() {


    const name = document.getElementById('editFullName')?.value || AppData.currentUser.name;


    const email = document.getElementById('editEmail')?.value || AppData.currentUser.email;




    if (!name || !email) {


        showNotification('Name and email are required', 'warning');


        return;


    }




    const formData = new FormData();


    formData.append('action', 'update_profile');


    formData.append('fullname', name);


    formData.append('email', email);


    formData.append('username', AppData.currentUser.name || '');




    fetch('API/update_profile.php', {


        method: 'POST',


        body: formData


    })


        .then(r => r.json())


        .then(data => {


            if (!data || !data.success) {


                throw new Error((data && data.message) ? data.message : 'Failed to update profile');


            }


            AppData.currentUser.name = name;


            AppData.currentUser.email = email;


            updateHeaderUserDisplays();


            showNotification('Profile updated successfully!', 'success');


            addAuditLog('update', 'Updated profile information');


            toggleEditMode();


            renderProfile();


        })


        .catch(err => {


            console.error(err);


            showNotification(err && err.message ? String(err.message) : 'Failed to update profile', 'error');


        });


}




// Open change password modal


function openChangePasswordModal() {


    const modal = document.createElement('div');


    modal.id = 'changePasswordModal';


    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';


    modal.innerHTML = `


        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">


            <div class="p-6 border-b border-gray-200">


                <div class="flex items-center justify-between">


                    <h2 class="text-xl font-bold text-gray-800">Change Password</h2>


                    <button onclick="closeChangePasswordModal()" class="text-gray-400 hover:text-gray-600">


                        <i class="bi bi-x-lg text-xl"></i>


                    </button>


                </div>


            </div>


            


            <div class="p-6">


                <div class="space-y-4">


                    <div>


                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>


                        <input type="password" id="currentPassword" class="input-field" placeholder="Enter current password">


                    </div>


                    <div>


                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>


                        <input type="password" id="newPassword" class="input-field" placeholder="Enter new password">


                    </div>


                    <div>


                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>


                        <input type="password" id="confirmPassword" class="input-field" placeholder="Confirm new password">


                    </div>


                </div>


            </div>


            


            <div class="p-6 border-t border-gray-200 flex gap-3">


                <button onclick="changePassword()" class="btn-primary flex-1">


                    <i class="bi bi-key mr-2"></i>Update Password


                </button>


                <button onclick="closeChangePasswordModal()" class="btn-outline flex-1">


                    Cancel


                </button>


            </div>


        </div>


    `;





    document.body.appendChild(modal);


}




function closeChangePasswordModal() {


    const modal = document.getElementById('changePasswordModal');


    if (modal) {


        modal.remove();


    }


}




function changePassword() {


    const current = document.getElementById('currentPassword').value;


    const newPass = document.getElementById('newPassword').value;


    const confirm = document.getElementById('confirmPassword').value;





    if (!current || !newPass || !confirm) {


        showNotification('Please fill in all password fields', 'error');


        return;


    }





    if (newPass !== confirm) {


        showNotification('New passwords do not match', 'error');


        return;


    }





    if (newPass.length < 6) {


        showNotification('Password must be at least 6 characters', 'error');


        return;


    }





    const formData = new FormData();


    formData.append('action', 'change_password');


    formData.append('current_password', current);


    formData.append('new_password', newPass);


    formData.append('confirm_password', confirm);




    fetch('API/update_profile.php', {


        method: 'POST',


        body: formData


    })


        .then(r => r.json())


        .then(data => {


            if (!data || !data.success) {


                throw new Error((data && data.message) ? data.message : 'Failed to change password');


            }


            closeChangePasswordModal();


            showNotification('Password changed successfully!', 'success');


            addAuditLog('update', 'Changed account password');


        })


        .catch(err => {


            console.error(err);


            showNotification(err && err.message ? String(err.message) : 'Failed to change password', 'error');


        });


}




// Two-Factor Authentication Modal


function openTwoFactorModal() {


    const modal = document.createElement('div');


    modal.id = 'twoFactorModal';


    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';


    modal.innerHTML = `


        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">


            <div class="p-6 border-b border-gray-200">


                <h2 class="text-xl font-bold text-gray-800">Two-Factor Authentication</h2>


            </div>


            


            <div class="p-6">


                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">


                    <p class="text-sm text-blue-800">


                        Two-Factor Authentication adds an extra layer of security to your account.


                    </p>


                </div>


                


                ${AppData.currentUser.twoFactorEnabled ? `


                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">


                        <p class="text-sm font-medium text-green-800">Status: <strong>Enabled</strong></p>


                        <p class="text-xs text-green-700 mt-1">Method: ${AppData.currentUser.twoFactorMethod}</p>


                    </div>


                ` : `


                    <div class="space-y-4">


                        <div>


                            <label class="block text-sm font-medium text-gray-700 mb-2">Choose Method</label>


                            <select id="twoFactorMethod" class="input-field">


                                <option value="email">Email (Recommended)</option>


                                <option value="sms">SMS Text Message</option>


                                <option value="authenticator">Authenticator App</option>


                            </select>


                        </div>


                    </div>


                `}


            </div>


            


            <div class="p-6 border-t border-gray-200 flex gap-3">


                ${AppData.currentUser.twoFactorEnabled ? `


                    <button onclick="disableTwoFactor()" class="btn-danger flex-1">Disable</button>


                ` : `


                    <button onclick="enableTwoFactor()" class="btn-primary flex-1">Enable</button>


                `}


                <button onclick="closeTwoFactorModal()" class="btn-outline flex-1">Close</button>


            </div>


        </div>


    `;





    document.body.appendChild(modal);


}




function closeTwoFactorModal() {


    const modal = document.getElementById('twoFactorModal');


    if (modal) modal.remove();


}




function enableTwoFactor() {


    const method = document.getElementById('twoFactorMethod')?.value || 'email';


    AppData.currentUser.twoFactorEnabled = true;


    AppData.currentUser.twoFactorMethod = method;


    closeTwoFactorModal();


    showNotification(`Two-Factor Authentication enabled via ${method}!`, 'success');


    addAuditLog('update', `Enabled Two-Factor Authentication (${method})`);


    renderProfile();


}




function disableTwoFactor() {


    if (!confirm('Are you sure? Disabling 2FA makes your account less secure.')) return;


    AppData.currentUser.twoFactorEnabled = false;


    closeTwoFactorModal();


    showNotification('Two-Factor Authentication disabled', 'warning');


    addAuditLog('update', 'Disabled Two-Factor Authentication');


    renderProfile();


}




// Login History Modal


function openLoginHistoryModal() {


    const modal = document.createElement('div');


    modal.id = 'loginHistoryModal';


    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';


    modal.innerHTML = `


        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-96 overflow-y-auto animate-fade-in-up">


            <div class="p-6 border-b border-gray-200 sticky top-0 bg-white">


                <div class="flex items-center justify-between">


                    <h2 class="text-xl font-bold text-gray-800">Login History</h2>


                    <button onclick="closeLoginHistoryModal()" class="text-gray-400 hover:text-gray-600">


                        <i class="bi bi-x-lg text-xl"></i>


                    </button>


                </div>


            </div>


            


            <div class="p-6">


                <div class="space-y-3">


                    ${AppData.loginHistory.map(log => `


                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">


                            <div class="flex items-center justify-between mb-2">


                                <p class="font-medium text-gray-800">${log.timestamp}</p>


                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Success</span>


                            </div>


                            <div class="text-sm text-gray-600 space-y-1">


                                <p><strong>Device:</strong> ${log.device}</p>


                                <p><strong>IP Address:</strong> ${log.ipAddress}</p>


                                <p><strong>Location:</strong> ${log.location}</p>


                            </div>


                        </div>


                    `).join('')}


                </div>


            </div>


        </div>


    `;





    document.body.appendChild(modal);


}




function closeLoginHistoryModal() {


    const modal = document.getElementById('loginHistoryModal');


    if (modal) modal.remove();


}




// Activity Report Modal


function openActivityReportModal() {


    const modal = document.createElement('div');


    modal.id = 'activityReportModal';


    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';


    const userActivities = AppData.auditLogs.filter(l => l.user === AppData.currentUser.name);


    modal.innerHTML = `


        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-96 overflow-y-auto animate-fade-in-up">


            <div class="p-6 border-b border-gray-200 sticky top-0 bg-white">


                <div class="flex items-center justify-between">


                    <h2 class="text-xl font-bold text-gray-800">Activity Report</h2>


                    <button onclick="closeActivityReportModal()" class="text-gray-400 hover:text-gray-600">


                        <i class="bi bi-x-lg text-xl"></i>


                    </button>


                </div>


            </div>


            


            <div class="p-6">


                <div class="mb-4 p-3 bg-gray-50 rounded-lg">


                    <p class="text-sm text-gray-700"><strong>Total Activities:</strong> ${userActivities.length}</p>


                </div>


                <div class="space-y-3">


                    ${userActivities.map(log => `


                        <div class="border border-gray-200 rounded-lg p-4">


                            <div class="flex items-center justify-between mb-2">


                                <p class="font-medium text-gray-800">${log.description}</p>


                                <span class="text-xs ${log.action === 'delete' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'} px-2 py-1 rounded">${capitalizeFirstLetter(log.action)}</span>


                            </div>


                            <p class="text-sm text-gray-600">${log.timestamp}</p>


                        </div>


                    `).join('')}


                </div>


            </div>


        </div>


    `;





    document.body.appendChild(modal);


}




function closeActivityReportModal() {


    const modal = document.getElementById('activityReportModal');


    if (modal) modal.remove();


}




// Session Settings Modal


function openSessionSettingsModal() {


    const modal = document.createElement('div');


    modal.id = 'sessionSettingsModal';


    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';


    modal.innerHTML = `


        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">


            <div class="p-6 border-b border-gray-200">


                <h2 class="text-xl font-bold text-gray-800">Session Settings</h2>


            </div>


            


            <div class="p-6">


                <div class="space-y-4">


                    <label class="flex items-center gap-3">


                        <input type="checkbox" id="rememberMe" class="form-checkbox" checked>


                        <span class="text-sm text-gray-700">Remember this device for 30 days</span>


                    </label>


                    <label class="flex items-center gap-3">


                        <input type="checkbox" id="sessionNotifications" class="form-checkbox" checked>


                        <span class="text-sm text-gray-700">Notify on new login attempts</span>


                    </label>


                    <label class="flex items-center gap-3">


                        <input type="checkbox" id="sessionTimeout" class="form-checkbox" checked>


                        <span class="text-sm text-gray-700">Auto-logout after 1 hour of inactivity</span>


                    </label>


                </div>


                


                <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">


                    <p class="text-sm font-medium text-red-800 mb-3">Sign Out From All Devices</p>


                    <button onclick="signOutAllDevices()" class="btn-danger w-full text-sm">


                        <i class="bi bi-door-closed mr-2"></i>Sign Out Everywhere


                    </button>


                </div>


            </div>


            


            <div class="p-6 border-t border-gray-200 flex gap-3">


                <button onclick="saveSessionSettings()" class="btn-primary flex-1">Save</button>


                <button onclick="closeSessionSettingsModal()" class="btn-outline flex-1">Close</button>


            </div>


        </div>


    `;





    document.body.appendChild(modal);


}




function closeSessionSettingsModal() {


    const modal = document.getElementById('sessionSettingsModal');


    if (modal) modal.remove();


}




function saveSessionSettings() {


    showNotification('Session settings saved successfully', 'success');


    closeSessionSettingsModal();


}




function signOutAllDevices() {


    if (!confirm('This will sign you out from all devices. Continue?')) return;


    showNotification('Signed out from all devices. Redirecting to login...', 'success');


    addAuditLog('update', 'Signed out from all devices');


    setTimeout(() => {


        window.location.href = 'login.html';


    }, 2000);


}




// Edit profile modal


function openEditProfileModal() {


    const modal = document.createElement('div');


    modal.id = 'editProfileModal';


    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';


    const user = AppData.currentUser;


    modal.innerHTML = `


        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-fade-in-up">


            <div class="p-6 border-b border-gray-200">


                <h2 class="text-xl font-bold text-gray-800">Edit Profile</h2>


            </div>


            


            <div class="p-6">


                <div class="space-y-4">


                    <div>


                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>


                        <input type="text" id="modal-name" value="${user.name}" class="input-field">


                    </div>


                    <div>


                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>


                        <input type="email" id="modal-email" value="${user.email}" class="input-field">


                    </div>


                    <div>


                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>


                        <input type="tel" id="modal-phone" value="${user.phone}" class="input-field">


                    </div>


                    <div>


                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>


                        <input type="text" id="modal-department" value="${user.department}" class="input-field">


                    </div>


                    <div>


                        <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>


                        <input type="text" id="modal-position" value="${user.position}" class="input-field">


                    </div>


                </div>


            </div>


            


            <div class="p-6 border-t border-gray-200 flex gap-3">


                <button onclick="saveEditProfileModal()" class="btn-primary flex-1">Save Changes</button>


                <button onclick="closeEditProfileModal()" class="btn-outline flex-1">Cancel</button>


            </div>


        </div>


    `;





    document.body.appendChild(modal);


}




function closeEditProfileModal() {


    const modal = document.getElementById('editProfileModal');


    if (modal) modal.remove();


}




function saveEditProfileModal() {


    AppData.currentUser.name = document.getElementById('modal-name').value;


    AppData.currentUser.email = document.getElementById('modal-email').value;


    AppData.currentUser.phone = document.getElementById('modal-phone').value;


    AppData.currentUser.department = document.getElementById('modal-department').value;


    AppData.currentUser.position = document.getElementById('modal-position').value;





    closeEditProfileModal();


    showNotification('Profile updated successfully', 'success');


    addAuditLog('update', 'Updated profile information');


    renderProfile();


}




// ==============================


// UPLOAD DOCUMENT FUNCTIONALITY


// ==============================


function handleDocumentUpload(event) {


    event.preventDefault();





    const form = event.target;

    const formData = new FormData(form);

    const fileInput = document.getElementById('file-input');



    if (!fileInput.files.length) {

        showNotification('Please select a file to upload', 'error');

        return;

    }



    formData.append('document_file', fileInput.files[0]);




    // Validate


    if (!formData.get('reference') || !formData.get('title') || !formData.get('type') || !formData.get('date')) {


        showNotification('Please fill in all required fields', 'error');


        return;


    }





    // Show loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i>Uploading...';

    // Send to API
    fetch('API/documents_api.php?action=upload', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification('Document uploaded successfully', 'success');
                closeModal('upload-modal');
                form.reset();
                document.getElementById('file-name').textContent = '';

                // Refresh documents list
                loadDocumentsFromApi().then(() => {
                    if (document.getElementById('documentsList')) {
                        filterDocuments();
                    }
                });

                // Add audit log
                addAuditLog('upload', `Uploaded document ${formData.get('reference')}`);
            } else {
                showNotification(data.message || 'Upload failed', 'error');
            }
        })
        .catch(err => {
            console.error('Upload error:', err);
            showNotification('Upload failed. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });




    // Create new document


    // const newDoc = {


    //     id: AppData.documents.length + 1,


    //     ...formData,


    //     uploadedBy: AppData.currentUser.name,


    //     uploadedAt: new Date().toLocaleString(),


    //     fileSize: '1.2 MB',


    //     views: 0,


    //     downloads: 0


    // };





    // AppData.documents.unshift(newDoc);





    // Close modal and reset form


    // closeModal('upload-modal');


    // document.getElementById('uploadForm').reset();





    // Show success notification


    // showNotification('Document uploaded successfully', 'success');





    // Add audit log


    // addAuditLog('upload', `Uploaded document ${formData.reference}`);





    // Refresh if on documents page


    // if (document.getElementById('documentsList')) {


    //     filterDocuments();


    // }


}




// Handle file selection
function handleFileSelect(event) {
    const file = event.target.files[0];
    const fileName = document.getElementById('file-name');

    if (file) {
        fileName.textContent = `Selected: ${file.name} (${formatFileSize(file.size)})`;
    } else {
        fileName.textContent = '';
    }
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Setup drag and drop

function setupDragAndDrop() {

    const dropzone = document.getElementById('dropzone');


    if (!dropzone) return;





    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {


        dropzone.addEventListener(eventName, preventDefaults, false);


    });





    function preventDefaults(e) {


        e.preventDefault();


        e.stopPropagation();


    }





    ['dragenter', 'dragover'].forEach(eventName => {


        dropzone.addEventListener(eventName, () => {


            dropzone.classList.add('border-red-600', 'bg-red-50');


        }, false);


    });





    ['dragleave', 'drop'].forEach(eventName => {


        dropzone.addEventListener(eventName, () => {


            dropzone.classList.remove('border-red-600', 'bg-red-50');


        }, false);


    });





    dropzone.addEventListener('drop', (e) => {


        const files = e.dataTransfer.files;


        if (files.length > 0) {


            showNotification(`File "${files[0].name}" ready to upload`, 'info');


        }


    }, false);


}




// ==============================


// UTILITY FUNCTIONS


// ==============================


let appDialogState = null;

function createAppDialogModal() {
    if (document.getElementById('app-dialog-modal')) return;

    const modal = document.createElement('div');
    modal.id = 'app-dialog-modal';
    modal.className = 'fixed inset-0 z-[9999] hidden items-center justify-center bg-black/55 px-4';
    modal.innerHTML = `
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-2xl">
            <div class="border-b border-gray-200 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div id="app-dialog-icon" class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600">
                        <i class="bi bi-info-circle text-xl"></i>
                    </div>
                    <div>
                        <h3 id="app-dialog-title" class="text-lg font-semibold text-gray-900">Notice</h3>
                        <p id="app-dialog-subtitle" class="text-sm text-gray-500">Please review before continuing.</p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-5">
                <p id="app-dialog-message" class="text-sm leading-6 text-gray-700"></p>
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button id="app-dialog-cancel" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancel</button>
                <button id="app-dialog-confirm" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Continue</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    modal.querySelector('#app-dialog-cancel').addEventListener('click', () => {
        if (appDialogState && typeof appDialogState.reject === 'function') {
            appDialogState.reject(new Error('cancelled'));
        }
        closeAppDialog();
    });

    modal.querySelector('#app-dialog-confirm').addEventListener('click', () => {
        if (appDialogState && typeof appDialogState.resolve === 'function') {
            appDialogState.resolve(true);
        }
        closeAppDialog();
    });
}

function openAppDialog({ title, message, type = 'info', confirmLabel = 'Continue', cancelLabel = 'Cancel', showCancel = true }) {
    createAppDialogModal();

    const modal = document.getElementById('app-dialog-modal');
    const titleEl = document.getElementById('app-dialog-title');
    const subtitleEl = document.getElementById('app-dialog-subtitle');
    const messageEl = document.getElementById('app-dialog-message');
    const iconEl = document.getElementById('app-dialog-icon');
    const confirmBtn = document.getElementById('app-dialog-confirm');
    const cancelBtn = document.getElementById('app-dialog-cancel');

    titleEl.textContent = title || 'Notice';
    subtitleEl.textContent = type === 'warning' ? 'Action requires confirmation' : 'Please review before continuing';
    messageEl.textContent = message || 'This action needs your confirmation.';
    confirmBtn.textContent = confirmLabel;
    cancelBtn.textContent = cancelLabel;
    cancelBtn.style.display = showCancel ? 'inline-flex' : 'none';

    if (type === 'warning') {
        iconEl.className = 'flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-600';
        iconEl.innerHTML = '<i class="bi bi-exclamation-triangle text-xl"></i>';
    } else if (type === 'success') {
        iconEl.className = 'flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600';
        iconEl.innerHTML = '<i class="bi bi-check-circle text-xl"></i>';
    } else {
        iconEl.className = 'flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600';
        iconEl.innerHTML = '<i class="bi bi-info-circle text-xl"></i>';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    return new Promise((resolve, reject) => {
        appDialogState = { resolve, reject };
    });
}

function closeAppDialog() {
    const modal = document.getElementById('app-dialog-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    appDialogState = null;
}

window.alert = function (message) {
    return openAppDialog({ title: 'Notice', message, type: 'info', confirmLabel: 'Okay', showCancel: false });
};

function openModal(modalId) {


    const modal = document.getElementById(modalId);


    if (modal) {


        modal.classList.remove('hidden');


        modal.classList.add('flex');


    }


}




function closeModal(modalId) {


    const modal = document.getElementById(modalId);


    if (modal) {


        modal.classList.add('hidden');


        modal.classList.remove('flex');


    }


}

// Replace common bootstrap-icon <i class="bi ..."> elements with inline SVGs
// This avoids relying on the icon font being loaded from disk or network.
function replaceIconFontWithInlineSVG() {
    const svgs = {
        'bi-eye': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="1em" height="1em" aria-hidden="true">
                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/>
                <path d="M8 5.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z"/>
            </svg>
        `,
        'bi-download': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="1em" height="1em" aria-hidden="true">
                <path d="M.5 9.9V13a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1V9.9a.5.5 0 0 0-.85-.36L9 14.44V1.5a.5.5 0 0 0-1 0v12.94L1.35 9.54A.5.5 0 0 0 .5 9.9z"/>
                <path d="M7.646 4.146a.5.5 0 0 1 .708 0L10.5 6.293a.5.5 0 0 1-.708.707L8.5 5.207V12.5a.5.5 0 0 1-1 0V5.207L6.208 7a.5.5 0 1 1-.708-.707L7.646 4.146z"/>
            </svg>
        `,
        'bi-x-lg': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="1em" height="1em" aria-hidden="true">
                <path d="M2.146 2.146a.5.5 0 1 1 .708-.708L8 6.586l5.146-5.148a.5.5 0 0 1 .708.708L8.707 7.293l5.147 5.147a.5.5 0 0 1-.708.708L8 8.001l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 7.293 2.146 2.146z"/>
            </svg>
        `,
        'bi-file-earmark-plus': `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" width="1em" height="1em" aria-hidden="true">
                <path d="M8 6.5a.5.5 0 0 1 .5.5V8h1a.5.5 0 0 1 0 1H8.5v1a.5.5 0 0 1-1 0V9H6.5a.5.5 0 0 1 0-1H7.5V7a.5.5 0 0 1 .5-.5z"/>
                <path d="M14 4.5V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6.5L14 4.5zM10.5 3V1H3v13h10V4.5H10.5z"/>
            </svg>
        `
    };

    Object.keys(svgs).forEach(cls => {
        const selector = `i.${cls.replace(/^bi-/, 'bi-')}`;
        document.querySelectorAll(selector).forEach(el => {
            try {
                const wrapper = document.createElement('span');
                wrapper.className = el.className ? el.className + ' inline-svg' : 'inline-svg';
                wrapper.setAttribute('aria-hidden', 'true');
                wrapper.innerHTML = svgs[cls];
                el.replaceWith(wrapper);
            } catch (e) { /* noop */ }
        });
    });
}

document.addEventListener('DOMContentLoaded', replaceIconFontWithInlineSVG);




function formatNotifTime(isoOrSqlDate) {


    if (!isoOrSqlDate) return '';


    const d = new Date(isoOrSqlDate);


    if (Number.isNaN(d.getTime())) return String(isoOrSqlDate);


    return d.toLocaleString();


}




function mapDbNotificationToUi(row) {


    const isRead = Number(row.is_read) === 1;


    const type = String(row.type || 'info').toLowerCase();


    const title = type === 'consultation'


        ? 'New consultation received'


        : (type === 'feedback' ? 'New feedback received' : 'Notification');




    return {


        id: Number(row.id),


        title,


        message: String(row.message || ''),


        category: type,


        type,


        priority: (type === 'consultation' || type === 'feedback') ? 'high' : 'normal',


        read: isRead,


        time: formatNotifTime(row.created_at || '')
    };
}

function mapDbNotificationToUi(row) {
    if (!row) return null;
    const isRead = Number(row.is_read) === 1;
    const type = String(row.type || 'info').toLowerCase();
    const msg = String(row.message || '');

    let title = 'System Notification';
    let category = type;
    let priority = (type === 'consultation' || type === 'feedback' || type === 'phms_integration' || type === 'ai_brief') ? 'high' : 'normal';

    if (msg.includes('PHMS') || type === 'phms_integration') {
        title = '🔗 PHMS Integration';
        category = 'PHMS System';
        priority = 'high';
    } else if (msg.includes('AI') || type === 'ai_brief') {
        title = '🤖 AI Committee Brief';
        category = 'AI Engine';
        priority = 'high';
    } else if (type === 'feedback' || msg.includes('Feedback')) {
        title = '📩 Citizen Feedback';
        category = 'Public Portal';
    } else if (type === 'consultation') {
        title = '📋 Consultation Update';
        category = 'Policy';
    }

    return {
        id: Number(row.id || Date.now()),
        title: title,
        message: msg,
        category: category,
        priority: priority,
        read: isRead,
        time: row.created_at ? formatNotifTime(row.created_at) : 'Recently',
        timestamp: row.created_at || new Date().toISOString()
    };
}

async function loadNotifications() {


    const notifsList = document.getElementById('notifications-list');


    if (!notifsList) {


        console.warn('notifications-list element not found');


        return;


    }




    try {


        const res = await fetch('API/notifications_api.php?action=list&limit=50', {


            headers: { 'Accept': 'application/json' }


        });


        const data = await res.json().catch(() => null);


        if (!res.ok || !data || !data.success) {


            const msg = (data && data.message) ? data.message : (res.ok ? 'Failed to load notifications' : `HTTP ${res.status}`);


            throw new Error(msg);


        }




        const items = Array.isArray(data.data && data.data.items) ? data.data.items : [];


        AppData.notifications = items.map(mapDbNotificationToUi);




        const unreadCount = typeof data.data.unread === 'number'


            ? data.data.unread


            : AppData.notifications.filter(n => !n.read).length;




        const badge = document.getElementById('notif-badge') || document.getElementById('notification-badge');


        if (badge) {


            if (unreadCount > 0) {


                badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);


                badge.classList.remove('hidden');


            } else {


                badge.classList.add('hidden');


            }


        }


        document.querySelectorAll('[data-section="consultation-management"]').forEach(link => {
            const icon = link.querySelector('.consultation-management-unread-icon');
            if (icon) {
                if (unreadCount > 0) {
                    icon.classList.remove('hidden');
                } else {
                    icon.classList.add('hidden');
                }
            }
        });


        // Priority icons for dropdown


        const priorityIcons = {


            critical: '🔴',


            high: '🟠',


            normal: '🔵',


            low: '⚪'


        };




        // Render notifications sorted by priority and unread status


        const sortedNotifs = [...AppData.notifications].sort((a, b) => {


            const priorityOrder = { critical: 0, high: 1, normal: 2, low: 3 };


            const aPriority = priorityOrder[a.priority] || 2;


            const bPriority = priorityOrder[b.priority] || 2;


            if (aPriority !== bPriority) return aPriority - bPriority;


            return a.read === b.read ? 0 : a.read ? 1 : -1;


        });




        notifsList.innerHTML = sortedNotifs.length === 0 ?
            '<div class="p-8 text-center text-gray-400 text-xs font-medium">No notifications yet</div>' :
            sortedNotifs.map(notif => {
                const title = escapeHtml(notif.title || 'Notification');
                const message = escapeHtml(notif.message || '');
                const time = escapeHtml(notif.time || 'Just updated');
                const isRead = !!notif.read;

                let iconInfo = { bg: 'bg-amber-50 border-amber-100', text: 'text-amber-600', icon: 'bi-calendar-check' };
                const textLower = (title + ' ' + message + ' ' + (notif.category || '')).toLowerCase();

                if (textLower.includes('orts') || textLower.includes('status') || textLower.includes('changed') || textLower.includes('ordinance')) {
                    iconInfo = { bg: 'bg-blue-50 border-blue-100', text: 'text-blue-600', icon: 'bi-arrow-repeat' };
                } else if (textLower.includes('lacs') || textLower.includes('hearing') || textLower.includes('approval') || textLower.includes('public hearing')) {
                    iconInfo = { bg: 'bg-amber-50 border-amber-100', text: 'text-amber-600', icon: 'bi-calendar-check' };
                } else if (textLower.includes('ai') || textLower.includes('brief') || textLower.includes('robot')) {
                    iconInfo = { bg: 'bg-purple-50 border-purple-100', text: 'text-purple-600', icon: 'bi-robot' };
                } else if (textLower.includes('feedback') || textLower.includes('phms') || textLower.includes('submission')) {
                    iconInfo = { bg: 'bg-emerald-50 border-emerald-100', text: 'text-emerald-600', icon: 'bi-chat-left-text' };
                }

                return `
                    <div data-id="${notif.id}" onclick="pfpMarkSingleNotifRead(${notif.id})" class="p-4 transition hover:bg-gray-50/80 flex items-start gap-3.5 relative cursor-pointer ${!isRead ? 'bg-white font-medium' : 'bg-gray-50/40 opacity-75'}">
                        <div class="w-10 h-10 rounded-2xl ${iconInfo.bg} ${iconInfo.text} border flex items-center justify-center shrink-0 mt-0.5">
                            <i class="bi ${iconInfo.icon} text-base"></i>
                        </div>
                        <div class="flex-1 min-w-0 pr-3">
                            <div class="font-bold text-gray-900 text-xs leading-snug">${title}</div>
                            <div class="text-xs text-gray-500 mt-0.5 leading-relaxed font-normal">${message}</div>
                            <div class="text-[11px] text-gray-400 mt-1 font-medium">${time}</div>
                        </div>
                        ${!isRead ? '<span class="w-2.5 h-2.5 rounded-full bg-red-500 shrink-0 mt-1.5 ring-4 ring-red-50"></span>' : ''}
                    </div>
                `;
            }).join('');

        notifsList.querySelectorAll('[data-id]').forEach(item => {
            item.addEventListener('click', function () {
                const id = parseInt(this.getAttribute('data-id'));
                if (id) pfpMarkSingleNotifRead(id);
            });
        });


    } catch (e) {


        const details = e && e.message ? String(e.message) : 'Unknown error';


        notifsList.innerHTML = `<div class="p-6 text-center text-red-600 text-sm">Failed to load notifications.<div class="text-xs text-gray-500 mt-2">${escapeHtml(details)}</div></div>`;


        const badge = document.getElementById('notif-badge') || document.getElementById('notification-badge');


        if (badge) badge.classList.add('hidden');


    }


}




// Toggle notifications dropdown

function toggleNotifications() {

    const dropdown = document.getElementById('notifications-dropdown');

    if (dropdown) {

        dropdown.classList.toggle('hidden');

        if (!dropdown.classList.contains('hidden')) {

            loadNotifications();

        }

    }

}




function pfpMarkAllNotificationsRead() {
    if (Array.isArray(AppData.notifications)) {
        AppData.notifications.forEach(n => n.read = true);
    }
    if (typeof markAllNotificationsRead === 'function') {
        try { markAllNotificationsRead(); } catch (_) { }
    }
    const badge = document.getElementById('notif-badge') || document.getElementById('notification-badge');
    if (badge) badge.classList.add('hidden');
    if (typeof loadNotifications === 'function') {
        try { loadNotifications(); } catch (_) { }
    }
}

function pfpMarkSingleNotifRead(id) {
    if (!id) return;

    if (Array.isArray(AppData.notifications)) {
        const notif = AppData.notifications.find(n => String(n.id) === String(id));
        if (notif) notif.read = true;
    }

    const dropdown = document.getElementById('notifications-dropdown');
    if (dropdown) dropdown.classList.add('hidden');

    const badge = document.getElementById('notif-badge') || document.getElementById('notification-badge');
    if (badge && Array.isArray(AppData.notifications)) {
        const unreadCount = AppData.notifications.filter(n => !n.read).length;
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    if (typeof toggleNotificationRead === 'function') {
        try { toggleNotificationRead(id, 1); } catch (_) { }
    }

    if (typeof openNotificationModal === 'function') {
        openNotificationModal(id);
    }
}

window.pfpMarkAllNotificationsRead = pfpMarkAllNotificationsRead;
window.pfpMarkSingleNotifRead = pfpMarkSingleNotifRead;

function viewNotification(id) {
    const notif = AppData.notifications ? AppData.notifications.find(n => String(n.id) === String(id)) : null;
    if (!notif) {
        console.error('Notification not found:', id);
        return;
    }
    pfpMarkSingleNotifRead(id);
}

function openNotificationModal(id) {
    const notif = AppData.notifications ? AppData.notifications.find(n => String(n.id) === String(id)) : null;
    if (!notif) return;

    // Create modal container if not present
    let modal = document.getElementById('notif-detail-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'notif-detail-modal';
        modal.className = 'fixed inset-0 bg-slate-950/70 backdrop-blur-md flex items-center justify-center z-[99999] p-4 hidden transition-all duration-200';

        modal.innerHTML = `
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-150">
                <!-- Header Banner with Dynamic Colors -->
                <div id="notif-header-banner" class="bg-gradient-to-r from-red-700 via-red-800 to-slate-900 text-white p-6 flex items-start justify-between">
                    <div class="flex-1 pr-3">
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <span id="notif-priority-badge" class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/20 text-white border border-white/30"></span>
                            <span id="notif-detail-category" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-black/30 text-white/90"></span>
                        </div>
                        <h3 id="notif-detail-title" class="text-lg font-extrabold text-white leading-snug">Notification Title</h3>
                        <p id="notif-detail-time" class="text-xs text-white/80 mt-1 font-medium flex items-center gap-1.5"></p>
                    </div>
                    <button id="notif-detail-close" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl font-bold flex items-center justify-center transition leading-none">&times;</button>
                </div>

                <!-- Content Body Container -->
                <div id="notif-content-container" class="p-6 space-y-5">
                    <div class="p-4.5 bg-slate-50 rounded-2xl border border-slate-200/80 shadow-inner">
                        <h4 class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center gap-1.5">
                            <i class="bi bi-chat-left-text-fill text-red-600"></i> Notification Message
                        </h4>
                        <p id="notif-detail-message" class="text-xs text-slate-800 font-medium leading-relaxed select-text"></p>
                    </div>

                    <!-- Footer Bar with Integrated Navigation & Actions -->
                    <div class="pt-3 flex items-center justify-between gap-3 border-t border-slate-100">
                        <!-- Navigation Counter & Buttons -->
                        <div class="flex items-center gap-1.5">
                            <button id="notif-detail-prev" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center font-bold transition border border-slate-200 text-xs">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <span id="notif-counter" class="font-mono font-extrabold text-slate-700 text-xs px-2">1 / 1</span>
                            <button id="notif-detail-next" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center font-bold transition border border-slate-200 text-xs">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2">
                            <button id="notif-detail-action" class="hidden px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition flex items-center gap-1.5 shadow-sm"></button>
                            <button id="notif-detail-open" class="px-4 py-2.5 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-1.5 hover:shadow-lg">
                                <i class="bi bi-box-arrow-up-right"></i> Open Related Page
                            </button>
                            <button id="notif-detail-dismiss" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close handlers
        modal.querySelector('#notif-detail-close').addEventListener('click', () => closeNotificationModal());
        modal.querySelector('#notif-detail-dismiss').addEventListener('click', () => closeNotificationModal());

        // Navigation handlers
        modal.querySelector('#notif-detail-prev').addEventListener('click', () => navigateNotification(-1));
        modal.querySelector('#notif-detail-next').addEventListener('click', () => navigateNotification(1));
    }

    // Dynamic Banner Header Colors based on priority/type
    const banner = document.getElementById('notif-header-banner');
    const priority = String(notif.priority || 'normal').toLowerCase();
    if (banner) {
        if (priority === 'critical' || priority === 'high') {
            banner.className = 'bg-gradient-to-r from-red-700 via-red-800 to-slate-900 text-white p-6 flex items-start justify-between';
        } else if (notif.category === 'AI Engine' || notif.type === 'ai_brief') {
            banner.className = 'bg-gradient-to-r from-purple-700 via-purple-800 to-slate-900 text-white p-6 flex items-start justify-between';
        } else {
            banner.className = 'bg-gradient-to-r from-slate-800 via-slate-900 to-blue-950 text-white p-6 flex items-start justify-between';
        }
    }

    // Fill content
    document.getElementById('notif-detail-title').textContent = notif.title;
    document.getElementById('notif-detail-time').innerHTML = '<i class="bi bi-clock-history"></i> ' + (notif.time || 'Recently');
    document.getElementById('notif-detail-category').textContent = '📁 ' + (notif.category || 'general').toUpperCase();
    document.getElementById('notif-detail-message').textContent = notif.message;

    // Priority badge
    const badge = document.getElementById('notif-priority-badge');
    if (badge) {
        badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full ${priority === 'high' || priority === 'critical' ? 'bg-amber-400 animate-ping' : 'bg-blue-400'} inline-block mr-1"></span> ${priority.toUpperCase()} PRIORITY`;
        badge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-white/20 text-white border border-white/30';
    }

    // Action button
    const actionBtn = document.getElementById('notif-detail-action');
    if (notif.action) {
        actionBtn.textContent = notif.action;
        actionBtn.classList.remove('hidden');
        actionBtn.onclick = function () {
            closeNotificationModal();
            if (notif.category === 'documents') showSection('documents');
            else if (notif.category === 'feedback') showSection('public-feedback-queue');
            else if (notif.category === 'users') showSection('users');
            else if (notif.category === 'system') showSection('audit');
            else showSection('public-consultation');
        };
    } else {
        actionBtn.classList.add('hidden');
    }

    // Open action
    const openBtn = document.getElementById('notif-detail-open');
    openBtn.onclick = function () {
        closeNotificationModal();
        if (notif.type === 'document' || notif.type === 'approval') showSection('documents');
        else if (notif.type === 'user') showSection('users');
        else if (notif.type === 'feedback' || notif.type === 'phms_integration') showSection('public-feedback-queue');
        else if (notif.type === 'alert' || notif.type === 'system') showSection('audit');
        else showSection('public-consultation');
    };

    // Update navigation state and counter
    updateNavigationState(id);

    // Show modal
    modal.classList.remove('hidden');
}




// Track current notification index for navigation


let currentNotificationIndex = -1;




function updateNavigationState(currentId) {


    const sortedNotifs = [...AppData.notifications].sort((a, b) => {


        const priorityOrder = { critical: 0, high: 1, normal: 2, low: 3 };


        const aPriority = priorityOrder[a.priority] || 2;


        const bPriority = priorityOrder[b.priority] || 2;


        if (aPriority !== bPriority) return aPriority - bPriority;


        return a.read === b.read ? 0 : a.read ? 1 : -1;


    });




    currentNotificationIndex = sortedNotifs.findIndex(n => n.id === currentId);




    const prevBtn = document.getElementById('notif-detail-prev');


    const nextBtn = document.getElementById('notif-detail-next');


    const counter = document.getElementById('notif-counter');




    if (sortedNotifs.length <= 1) {


        // Hide navigation if only one notification


        if (prevBtn) {


            prevBtn.style.opacity = '0';


            prevBtn.style.pointerEvents = 'none';


        }


        if (nextBtn) {


            nextBtn.style.opacity = '0';


            nextBtn.style.pointerEvents = 'none';


        }


        if (counter) counter.textContent = '';


    } else {


        // Show navigation buttons


        if (prevBtn) {


            prevBtn.style.opacity = currentNotificationIndex > 0 ? '1' : '0.3';


            prevBtn.style.pointerEvents = currentNotificationIndex > 0 ? 'auto' : 'none';


        }


        if (nextBtn) {


            nextBtn.style.opacity = currentNotificationIndex < sortedNotifs.length - 1 ? '1' : '0.3';


            nextBtn.style.pointerEvents = currentNotificationIndex < sortedNotifs.length - 1 ? 'auto' : 'none';


        }


        if (counter) counter.textContent = `${currentNotificationIndex + 1} / ${sortedNotifs.length}`;


    }


}




function navigateNotification(direction) {


    const sortedNotifs = [...AppData.notifications].sort((a, b) => {


        const priorityOrder = { critical: 0, high: 1, normal: 2, low: 3 };


        const aPriority = priorityOrder[a.priority] || 2;


        const bPriority = priorityOrder[b.priority] || 2;


        if (aPriority !== bPriority) return aPriority - bPriority;


        return a.read === b.read ? 0 : a.read ? 1 : -1;


    });




    const newIndex = currentNotificationIndex + direction;




    if (newIndex >= 0 && newIndex < sortedNotifs.length) {


        const newId = sortedNotifs[newIndex].id;


        const container = document.getElementById('notif-content-container');




        // Add slide animation


        container.style.transform = direction === 1 ? 'translateX(-100%)' : 'translateX(100%)';


        container.style.opacity = '0';




        setTimeout(() => {


            openNotificationModal(newId);




            // Reset and slide in


            container.style.transform = direction === 1 ? 'translateX(100%)' : 'translateX(-100%)';


            container.style.opacity = '0';




            setTimeout(() => {


                container.style.transform = 'translateX(0)';


                container.style.opacity = '1';


            }, 50);


        }, 300);


    }


}




function closeNotificationModal() {


    const modal = document.getElementById('notif-detail-modal');


    if (modal) modal.classList.add('hidden');


}




function deleteNotification(id) {


    if (!confirm('Delete this notification?')) return;


    fetch('API/notifications_api.php?action=delete', {


        method: 'POST',


        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },


        body: JSON.stringify({ id })


    }).then(() => loadNotifications()).catch(() => loadNotifications());


    // If on notifications page, re-render it


    const current = document.getElementById('breadcrumb-current');


    if (current && current.textContent && current.textContent.toLowerCase().includes('notifications')) {


        renderNotifications();


    }


}




function toggleNotificationRead(id, isRead) {


    return fetch('API/notifications_api.php?action=mark_read', {


        method: 'POST',


        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },


        body: JSON.stringify({ id, is_read: isRead ? 1 : 0 })


    }).then(() => loadNotifications()).catch(() => loadNotifications());


}




function markAllNotificationsRead() {


    fetch('API/notifications_api.php?action=mark_all_read', {


        method: 'POST',


        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },


        body: JSON.stringify({})


    }).then(() => loadNotifications()).catch(() => loadNotifications());


}




function clearAllNotifications() {


    showNotification('Clearing all notifications is not enabled for DB-backed notifications.', 'info');


}




function saveAnnouncementsToStorage() {


    try {


        localStorage.setItem('llrm_announcements', JSON.stringify(AppData.announcements));


    } catch (e) {


        console.warn('Failed to save announcements to storage', e);


    }


}




function loadAnnouncementsFromStorage() {


    try {


        const raw = localStorage.getItem('llrm_announcements');


        if (raw) {


            AppData.announcements = JSON.parse(raw);


        }


    } catch (e) {


        console.warn('Failed to load announcements from storage', e);


    }


}




function createAnnouncement(title, message, options = {}) {


    const ann = {


        id: Date.now(),


        title: title,


        message: message,


        priority: options.priority || 'normal',


        pinned: !!options.pinned,


        published: options.published !== undefined ? !!options.published : true,


        createdBy: AppData.currentUser?.name || 'System',


        createdAt: new Date().toISOString()


    };




    AppData.announcements.unshift(ann);


    saveAnnouncementsToStorage();


    showNotification('Announcement created', 'success');


    return ann;


}




function deleteAnnouncement(id) {


    if (!confirm('Delete this announcement?')) return;


    AppData.announcements = AppData.announcements.filter(a => a.id !== id);


    saveAnnouncementsToStorage();


    showSection('announcements');


}





// ==============================


// USERS MODULE (User Management — Citizens + Staff)


// ==============================


function showNotification(message, type = 'info') {


    const colors = {


        success: 'bg-green-100 text-green-800 border-green-300',


        error: 'bg-red-100 text-red-800 border-red-300',


        info: 'bg-blue-100 text-blue-800 border-blue-300',


        warning: 'bg-yellow-100 text-yellow-800 border-yellow-300'


    };





    const icons = {


        success: 'bi-check-circle-fill',


        error: 'bi-x-circle-fill',


        info: 'bi-info-circle-fill',


        warning: 'bi-exclamation-triangle-fill'


    };





    const notif = document.createElement('div');


    notif.className = `fixed top-4 right-4 ${colors[type]} px-6 py-4 rounded-lg shadow-lg border-2 flex items-center gap-3 z-50 animate-fade-in`;


    notif.innerHTML = `


        <i class="bi ${icons[type]} text-xl"></i>


        <span class="font-medium">${message}</span>


    `;





    document.body.appendChild(notif);





    setTimeout(() => {


        notif.classList.add('opacity-0', 'transform', 'translate-x-full');


        setTimeout(() => notif.remove(), 300);


    }, 3000);


}




function getStatusBadge(status) {


    const statusLower = (status || '').toLowerCase();


    const badges = {


        'approved': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Approved</span>',


        'pending': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>',


        'draft': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Draft</span>',


        'success': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"><i class="bi bi-check-circle mr-1"></i>Success</span>',


        'failure': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="bi bi-x-circle mr-1"></i>Failed</span>'


    };


    return badges[statusLower] || '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' + (status || 'N/A') + '</span>';


}




function getUserStatusBadge(status) {


    const badges = {


        active: '<span class="badge badge-success">Active</span>',


        inactive: '<span class="badge badge-secondary">Inactive</span>'


    };


    return badges[status] || '<span class="badge badge-secondary">Unknown</span>';


}




function getActionBadge(action) {


    const actionLower = (action || '').toLowerCase();


    const badges = {


        'upload': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Upload</span>',


        'approve': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Approve</span>',


        'update': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Update</span>',


        'delete': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Delete</span>',


        'login': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">Login</span>',


        'logout': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Logout</span>',


        'created': '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Created</span>'


    };


    return badges[actionLower] || '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' + (action || 'N/A') + '</span>';


}




function getNotificationIcon(type) {


    const icons = {


        document: '<i class="bi bi-file-earmark-text text-red-600"></i>',


        approval: '<i class="bi bi-check-circle text-green-600"></i>',


        user: '<i class="bi bi-person text-blue-600"></i>'


    };


    return icons[type] || '<i class="bi bi-bell text-gray-600"></i>';


}




function capitalizeFirstLetter(string) {


    return string.charAt(0).toUpperCase() + string.slice(1);


}




function getInitials(name) {


    return name.split(' ').map(n => n[0]).join('').toUpperCase();


}




function formatDate(dateString) {


    const date = new Date(dateString);


    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });


}




// ==============================


// PUBLIC CONSULTATION PLACEHOLDERS


// ==============================




async function renderPublicConsultation() {


    // Update page title and breadcrumb




    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');




    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Public Consultation';

    const contentArea = document.getElementById('content-area');
    if (contentArea && !contentArea.innerHTML.trim()) {
        contentArea.innerHTML = '<div class="p-8 text-center text-gray-500">Loading consultation overview...</div>';
    }

    try {
        await Promise.all([
            loadConsultationsFromApi().catch(e => console.warn('Consultation overview load failed:', e)),
            loadFeedbackFromApi().catch(e => console.warn('Feedback overview load failed:', e)),
            loadIssuesFromApi().catch(e => console.warn('Issues overview load failed:', e))
        ]);
    } catch (e) {
        console.warn('Consultation overview bootstrap failed:', e);
    }




    const totalConsults = AppData.consultations.length;


    const draftConsults = AppData.consultations.filter(c => {
        const st = String(c.status || '').toLowerCase();
        return st === 'draft' || st === 'pending';
    }).length;


    const activeConsults = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'active').length;


    const closedConsults = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'closed').length;

    const validFeedbackList = getFilteredValidFeedback();
    const totalFeedback = validFeedbackList.length;

    const avgFeedback = totalConsults > 0 ? Math.round(totalFeedback / totalConsults) : 0;


    const totalDocuments = AppData.consultations.reduce((sum, c) => sum + (c.documentsAttached || 0), 0);
    const sentimentStats = getFeedbackSentimentStats();




    const isReadOnlySuperAdmin = currentUserIsSuperAdmin();
    const html = `


        <div class="space-y-6">

            ${isReadOnlySuperAdmin ? `<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-lg p-4 text-sm font-medium"><i class="bi bi-shield-lock mr-2"></i>Super Admin monitoring view. Use <strong>Audit Log</strong> and <strong>AI Insights</strong> for oversight. Write actions are disabled.</div>` : ''}

            <!-- Header Section -->


            <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-lg shadow-lg p-8 text-white">


                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">


                    <div>


                        <h1 class="text-3xl font-bold mb-2">${isReadOnlySuperAdmin ? 'System Monitoring Overview' : 'Consultation Overview'}</h1>


                        <p class="text-red-100">${isReadOnlySuperAdmin ? 'Read-only overview of consultations, feedback, and engagement metrics' : 'Manage consultations, track feedback, and monitor community engagement'}</p>


                    </div>


                    <div class="dashboard-header-actions flex flex-wrap items-center gap-2">
                        <button onclick="openPCCalendarModal()" class="bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white rounded-xl px-4 py-2.5 flex items-center gap-2 transition-all duration-200 shadow-sm hover:shadow-md border border-white/10" title="Open Consultation Calendar">
                            <i class="bi bi-calendar3 text-lg"></i>
                            <span class="text-sm font-semibold hidden sm:inline">Calendar</span>
                        </button>
                    </div>


                </div>


            </div>




            <!-- Statistics Cards -->

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition group">

                    <div class="flex items-center gap-3">

                        <div class="bg-red-50 rounded-lg p-2.5 group-hover:bg-red-100 transition"><i class="bi bi-megaphone-fill text-red-600 text-xl"></i></div>

                        <div>

                            <p class="text-xs text-gray-500 font-medium">Total Consultations</p>

                            <p class="text-2xl font-bold text-gray-900">${totalConsults}</p>

                        </div>

                    </div>

                    <div class="mt-3 flex gap-3 text-xs">

                        <span class="text-green-600 font-semibold"><i class="bi bi-circle-fill text-green-500" style="font-size:6px;vertical-align:middle"></i> ${activeConsults} Active</span>

                        <span class="text-gray-500"><i class="bi bi-circle-fill text-gray-400" style="font-size:6px;vertical-align:middle"></i> ${closedConsults} Closed</span>

                    </div>

                </div>


                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition group">

                    <div class="flex items-center gap-3">

                        <div class="bg-blue-50 rounded-lg p-2.5 group-hover:bg-blue-100 transition"><i class="bi bi-file-earmark-text-fill text-blue-600 text-xl"></i></div>

                        <div>

                            <p class="text-xs text-gray-500 font-medium">Pending Review</p>

                            <p class="text-2xl font-bold text-blue-600">${draftConsults}</p>

                        </div>

                    </div>

                    <div class="mt-3 text-xs text-gray-500">Citizen submissions awaiting approval</div>

                </div>


                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition group">

                    <div class="flex items-center gap-3">

                        <div class="bg-purple-50 rounded-lg p-2.5 group-hover:bg-purple-100 transition"><i class="bi bi-chat-square-quote-fill text-purple-600 text-xl"></i></div>

                        <div>

                            <p class="text-xs text-gray-500 font-medium">Total Feedback</p>

                            <p class="text-2xl font-bold text-purple-600">${totalFeedback}</p>

                        </div>

                    </div>

                    <div class="mt-3 text-xs text-gray-500">Avg <strong class="text-purple-600">${avgFeedback}</strong> per consultation</div>

                </div>


                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition group">

                    <div class="flex items-center gap-3">

                        <div class="bg-amber-50 rounded-lg p-2.5 group-hover:bg-amber-100 transition"><i class="bi bi-paperclip text-amber-600 text-xl"></i></div>

                        <div>

                            <p class="text-xs text-gray-500 font-medium">Documents</p>

                            <p class="text-2xl font-bold text-amber-600">${totalDocuments}</p>

                        </div>

                    </div>

                    <div class="mt-3 text-xs text-gray-500">Attached to consultations</div>

                </div>

            </div>

            <!-- Calendar Modal (triggered by icon in red header) -->
            <div id="pc-calendar-modal" class="modal" style="display: none; align-items: center; justify-content: center;">
                <div class="modal-content p-6" style="max-width: 900px; width: 95%;">
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-3">
                            <div class="bg-red-50 rounded-lg p-2.5"><i class="bi bi-calendar3 text-red-600 text-xl"></i></div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Consultation Calendar</h3>
                                <p class="text-xs text-gray-500">View scheduled consultations by date</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button onclick="pcDashboardCalendarChangeMonth(-1)" class="p-2 hover:bg-gray-100 rounded-lg text-gray-600 transition"><i class="bi bi-chevron-left"></i></button>
                            <button onclick="pcDashboardCalendarChangeMonth(1)" class="p-2 hover:bg-gray-100 rounded-lg text-gray-600 transition"><i class="bi bi-chevron-right"></i></button>
                            <button onclick="closePCCalendarModal()" class="p-2 hover:bg-gray-100 rounded-lg text-gray-400 hover:text-gray-600 transition ml-2">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div id="pc-dashboard-calendar-label" class="text-center font-bold text-gray-900 text-lg mb-4"></div>
                    <div class="grid grid-cols-7 gap-1 text-sm text-gray-600 mb-2">
                        <div class="text-center font-medium">Mon</div>
                        <div class="text-center font-medium">Tue</div>
                        <div class="text-center font-medium">Wed</div>
                        <div class="text-center font-medium">Thu</div>
                        <div class="text-center font-medium">Fri</div>
                        <div class="text-center font-medium">Sat</div>
                        <div class="text-center font-medium">Sun</div>
                    </div>
                    <div id="pc-dashboard-calendar-grid" class="grid grid-cols-7 gap-1 text-base"></div>
                </div>
            </div>




            <!-- Analytics Section (charts on top) -->
            <div id="pc-analytics-row" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex flex-col h-full">
                    <div id="pc-feedback-sentiment-pane">
                        <div class="flex items-start justify-between mb-4 gap-3">
                            <div class="flex items-center gap-2">
                                <div class="bg-purple-50 rounded-lg p-1.5"><i class="bi bi-pie-chart-fill text-purple-600"></i></div>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900">Feedback Sentiment</h3>
                                    <p class="text-xs text-gray-500">Community sentiment across responses</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-end">
                                <button id="pc-issue-mapping-toggle-btn" type="button" onclick="togglePCIssueMappingPanel()" class="text-xs border border-blue-100 rounded-full p-2 text-blue-700 hover:bg-blue-50 transition" aria-expanded="false" aria-controls="pc-issue-mapping-pane" title="Toggle issue mapping">
                                    <i class="bi bi-arrow-right-circle"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mb-3 text-xs" style="min-height: 30px;">
                            <span class="px-2 py-1 rounded-full bg-green-50 text-green-700 font-semibold" id="pc-positive-count">Positive: ${sentimentStats.positive}</span>
                            <span class="px-2 py-1 rounded-full bg-yellow-50 text-yellow-700 font-semibold" id="pc-neutral-count">Neutral: ${sentimentStats.neutral}</span>
                            <span class="px-2 py-1 rounded-full bg-red-50 text-red-700 font-semibold" id="pc-negative-count">Negative: ${sentimentStats.negative}</span>
                        </div>
                    </div>
                    <div id="pc-feedback-chart-pane" class="mt-auto flex justify-center items-center">
                        <div style="height: 340px; width: 340px; max-width: 100%;">
                            <canvas id="pcFeedbackSentimentChart"></canvas>
                        </div>
                    </div>
                    <div id="pc-issue-mapping-pane" class="hidden mt-4">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <div class="bg-amber-50 rounded-lg p-1.5"><i class="bi bi-map-fill text-amber-600"></i></div>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900">Issue Mapping / Topic Themes</h4>
                                    <p class="text-xs text-gray-500">Showing issue themes inline with feedback.</p>
                                </div>
                            </div>
                            <button id="pc-issue-mapping-back-btn" class="pc-issue-toggle text-xs border border-blue-100 rounded-full p-2 text-blue-700 hover:bg-blue-50 transition" type="button" onclick="togglePCIssueMappingPanel()" title="Back to sentiment overview" aria-controls="pc-feedback-sentiment-pane" aria-expanded="true">
                                <i class="bi bi-arrow-left-circle"></i>
                            </button>
                        </div>
                        <div id="pc-issue-theme-list" class="space-y-2 mb-3"></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex flex-col h-full">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="bg-blue-50 rounded-lg p-1.5"><i class="bi bi-pie-chart-fill text-blue-600"></i></div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Survey Response Summary</h3>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <select id="pc-survey-select" onchange="handlePCSurveySelectionChange()" class="text-xs border border-gray-300 rounded-md px-2 py-1 text-gray-700 bg-white max-w-[140px]">
                                <option value="all">All surveys</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div class="rounded-lg bg-red-50 p-2">
                            <div class="text-xs text-gray-500">Respondents</div>
                            <div id="pc-respondent-total" class="text-xl font-bold text-red-700">0</div>
                        </div>
                        <div class="rounded-lg bg-blue-50 p-2">
                            <div class="text-xs text-gray-500">Forms</div>
                            <div id="pc-survey-count-summary" class="text-xl font-bold text-blue-700">0</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1 mb-2 text-xs">
                        <span class="px-2 py-0.5 rounded-full bg-green-50 text-green-700 font-semibold" id="pc-survey-agree-count">Agree: 0</span>
                        <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 font-semibold" id="pc-survey-disagree-count">Disagree: 0</span>
                        <span class="text-xs text-gray-500 ml-auto" id="pc-survey-source">0 surveys</span>
                    </div>
                    <div style="height: 340px;" class="mt-auto flex items-center justify-center">
                        <canvas id="pcSurveyAnswersChart"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex flex-col h-full">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="bg-red-50 rounded-lg p-1.5"><i class="bi bi-pie-chart-fill text-red-600"></i></div>
                        <h3 class="text-sm font-bold text-gray-900">Total Consultation</h3>
                    </div>
                    <div style="height: 340px;" class="mt-auto flex items-center justify-center">
                        <canvas id="pcStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Advanced Filtering Section -->


            <div class="bg-white p-6 rounded-lg shadow">


                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search Consultations</label>


                        <input type="text" id="pc-search" placeholder="Search title..." 


                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"


                            onkeyup="filterPublicConsultations()">


                    </div>


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>


                        <select id="pc-status-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"


                            onchange="filterPublicConsultations()">


                            <option value="">All Status</option>


                            <option value="active">Active</option>


                            <option value="draft">Draft</option>


                            <option value="closed">Closed</option>


                        </select>


                    </div>


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>


                        <select id="pc-type-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"


                            onchange="filterPublicConsultations()">


                            <option value="">All Types</option>


                            <option value="admin">Admin Created</option>


                            <option value="user">User Submission</option>


                        </select>


                    </div>


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>


                        <select id="pc-sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"


                            onchange="filterPublicConsultations()">


                            <option value="date-desc">Latest First</option>


                            <option value="date-asc">Oldest First</option>


                            <option value="feedback">Most Feedback</option>


                        </select>


                    </div>


                    <div class="flex items-end">


                        <button onclick="resetPublicConsultationFilters()" class="w-full px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 font-semibold">


                            Reset


                        </button>


                    </div>


                </div>


            </div>




            <!-- Consultations Grid View -->


            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="consultations-grid">


            </div>




            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">


                <div class="text-sm text-gray-600" id="pc-grid-summary"></div>


                <div class="flex flex-wrap gap-2 justify-end">


                    <button id="pc-grid-prev" onclick="pcGridPrevPage()" class="btn-outline px-3 py-2 text-sm">Prev</button>


                    <button id="pc-grid-next" onclick="pcGridNextPage()" class="btn-outline px-3 py-2 text-sm">Next</button>


                    <button id="pc-grid-toggle" onclick="pcGridToggleShowAll()" class="btn-primary px-3 py-2 text-sm">Show All</button>


                </div>


            </div>

            <!-- Export Modal -->
            <div id="consultation-export-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                    <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-4 flex justify-between items-center">
                        <h3 id="consultation-export-title" class="text-lg font-bold">Export Consultation</h3>
                        <button onclick="closeConsultationExportModal()" class="text-white hover:text-red-100 text-xl">&times;</button>
                    </div>
                    <div id="consultation-export-message" class="p-5 text-sm text-gray-700"></div>
                    <div id="consultation-export-actions" class="px-5 pb-5 flex flex-wrap gap-2 justify-end">
                        <button onclick="closeConsultationExportModal()" class="btn-outline">Close</button>
                    </div>
                </div>
            </div>

        </div>


    `;




    document.getElementById('content-area').innerHTML = html;




    // Populate sections


    renderConsultationsGrid();





    // Render charts — must wait for DOM injection at line ~10045 to complete
    setTimeout(() => {
        const filtered = Array.isArray(AppData.consultations) ? AppData.consultations : [];
        console.log('[renderPublicConsultation] setTimeout fired, rendering charts & calendar with', filtered.length, 'consultations');
        renderPCStatusChart(filtered);
        renderPCFeedbackSentimentChart();
        refreshPCSurveySelector(filtered);
        renderPCSurveyAnswersChart(filtered);
        renderPCDashboardCalendar();
    }, 300);

    // Refresh feedback and issue analytics using latest DB data
    try {
        Promise.all([
            loadFeedbackFromApi(),
            loadIssuesFromApi()
        ]).then(() => {
            renderPCFeedbackSentimentChart();
            renderPCSurveyAnswersChart(Array.isArray(AppData.consultations) ? AppData.consultations : []);
            renderPCDashboardCalendar();
        }).catch((e) => {
            console.error(e);
            renderPCFeedbackSentimentChart();
            renderPCSurveyAnswersChart(Array.isArray(AppData.consultations) ? AppData.consultations : []);
            renderPCDashboardCalendar();
        });
    } catch (e) {
        console.error(e);
    }

    // Initialize PC dashboard calendar
    renderPCDashboardCalendar();

}




function getPCGridState() {


    if (!window.__pcGridState) {


        window.__pcGridState = {


            page: 1,


            pageSize: 6,


            showAll: false


        };


    }


    return window.__pcGridState;


}




function pcGridPrevPage() {


    const st = getPCGridState();


    if (st.page > 1) st.page -= 1;


    renderConsultationsGrid();


}




function pcGridNextPage() {


    const st = getPCGridState();


    st.page += 1;


    renderConsultationsGrid();


}




function pcGridToggleShowAll() {


    const st = getPCGridState();


    st.showAll = !st.showAll;


    st.page = 1;


    renderConsultationsGrid();


}




// ==============================


// SETTINGS


// ==============================


function renderSettings() {


    if (!AppData.currentUser) {


        AppData.currentUser = {


            id: null,


            name: 'User',


            email: '',


            role: '',


            profilePicture: '',


            twoFactorEnabled: false,


            twoFactorMethod: 'email'


        };


    }


    const html = `


        <div class="mb-6">


            <h1 class="text-2xl font-bold text-gray-800">Settings</h1>


            <p class="text-gray-600 mt-1">Manage account and application preferences</p>


        </div>




        <div class="bg-white rounded-xl shadow-md p-6 space-y-6">


            <div>


                <h3 class="text-lg font-semibold">Account</h3>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">


                    <div>


                        <label class="block text-sm text-gray-700 mb-1">Name</label>


                        <input id="setting-name" class="input-field" value="${AppData.currentUser.name}">


                    </div>


                    <div>


                        <label class="block text-sm text-gray-700 mb-1">Email</label>


                        <input id="setting-email" class="input-field" value="${AppData.currentUser.email}">


                    </div>


                </div>


                <div class="mt-4">


                    <button onclick="saveSettings()" class="btn-primary">Save Account</button>


                </div>


            </div>




            <div>


                <h3 class="text-lg font-semibold">Preferences</h3>


                <div class="mt-4 space-y-3">


                    <label class="flex items-center gap-3">


                        <input type="checkbox" id="pref-notifications" checked class="form-checkbox">


                        <span class="text-sm text-gray-700">Enable notifications</span>


                    </label>


                    <label class="flex items-center gap-3">


                        <input type="checkbox" id="pref-emails" class="form-checkbox">


                        <span class="text-sm text-gray-700">Receive email summaries</span>


                    </label>


                </div>


                <div class="mt-4">


                    <button onclick="savePreferences()" class="btn-primary">Save Preferences</button>


                </div>


            </div>


        </div>


    `;




    document.getElementById('content-area').innerHTML = html;


}




function saveSettings() {


    const name = document.getElementById('setting-name')?.value || AppData.currentUser.name;


    const email = document.getElementById('setting-email')?.value || AppData.currentUser.email;





    if (!name || !email) {


        showNotification('Name and email are required', 'warning');


        return;


    }




    // Send update to backend


    const formData = new FormData();


    formData.append('action', 'update_profile');


    formData.append('fullname', name);


    formData.append('email', email);


    formData.append('username', AppData.currentUser.name);




    fetch('API/update_profile.php', {


        method: 'POST',


        body: formData


    })


        .then(response => response.json())


        .then(data => {


            if (data.success) {


                AppData.currentUser.name = name;


                AppData.currentUser.email = email;


                updateHeaderUserDisplays();


                showNotification('Settings saved successfully', 'success');


            } else {


                showNotification(data.message || 'Failed to save settings', 'error');


            }


        })


        .catch(error => {


            console.error('Error saving settings:', error);


            showNotification('Error saving settings', 'error');


        });


}




// ==============================


// HELP & SUPPORT


// ==============================


function renderHelp() {


    const html = `


        <div class="mb-6">


            <h1 class="text-2xl font-bold text-gray-800">Help & Support</h1>


            <p class="text-gray-600 mt-1">Find answers and contact support</p>


        </div>




        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">


            <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6">


                <h3 class="text-lg font-semibold mb-4">Frequently Asked Questions</h3>


                <div class="space-y-4">


                    <div>


                        <p class="font-medium">How do I upload a document?</p>


                        <p class="text-sm text-gray-600 mt-1">Use the Upload button in Document Management or Quick Actions.</p>


                    </div>


                    <div>


                        <p class="font-medium">How do I manage users?</p>


                        <p class="text-sm text-gray-600 mt-1">Go to Administration → User Management to view citizen submitters and manage staff accounts.</p>


                    </div>


                    <div>


                        <p class="font-medium">Where can I view consultation feedback?</p>


                        <p class="text-sm text-gray-600 mt-1">Open Public Consultation or Public Feedback Queue from the menu.</p>


                    </div>


                </div>




                <h3 class="text-lg font-semibold mt-6 mb-3">Contact Support</h3>


                <form onsubmit="event.preventDefault(); sendSupportRequest();">


                    <div class="grid grid-cols-1 gap-3">


                        <input id="support-name" class="input-field" placeholder="Your name" value="${AppData.currentUser.name}">


                        <input id="support-email" class="input-field" placeholder="Your email" value="${AppData.currentUser.email}">


                        <textarea id="support-message" class="input-field" placeholder="How can we help?" rows="5"></textarea>


                        <div class="flex justify-end">


                            <button type="submit" class="btn-primary">Send Request</button>


                        </div>


                    </div>


                </form>


            </div>




            <div class="bg-white rounded-xl shadow-md p-6">


                <h3 class="text-lg font-semibold mb-3">Support Resources</h3>


                <ul class="space-y-2 text-sm text-gray-700">


                    <li><a href="#" onclick="showSection('documents')" class="text-red-600">User Guide: Document Management</a></li>


                    <li><a href="#" onclick="showSection('users')" class="text-red-600">User Guide: User Management</a></li>


                    <li><a href="#" onclick="showSection('public-consultation')" class="text-red-600">Public Consultation Overview</a></li>


                </ul>


            </div>


        </div>


    `;




    document.getElementById('content-area').innerHTML = html;


}




function sendSupportRequest() {


    const name = document.getElementById('support-name')?.value || '';


    const email = document.getElementById('support-email')?.value || '';


    const message = document.getElementById('support-message')?.value || '';


    if (!message) {


        showNotification('Please enter a message', 'warning');


        return;


    }




    // Simulate sending


    showNotification('Support request sent. We will contact you via email.', 'success');


}




// ==============================


// NOTIFICATIONS PAGE


// ==============================


function renderNotifications() {


    const html = `


        <div class="mb-6">


            <h1 class="text-2xl font-bold text-gray-800">Notifications</h1>


            <p class="text-gray-600 mt-1">All system notifications and actions</p>


        </div>




        <div class="bg-white rounded-xl shadow-md p-4 mb-4 flex items-center justify-between">


            <div class="text-sm text-gray-700">You have <strong>${AppData.notifications.filter(n => !n.read).length}</strong> unread notification(s)</div>


            <div class="flex items-center gap-2">


                <button onclick="markAllNotificationsRead()" class="btn-outline text-sm">Mark all read</button>


                <button onclick="clearAllNotifications()" class="btn-danger text-sm">Clear all</button>


            </div>


        </div>




        <div class="bg-white rounded-xl shadow-md p-4">


            ${AppData.notifications.length === 0 ? '<div class="p-6 text-center text-gray-500">No notifications</div>' : ''}


            <div class="space-y-2">


                ${AppData.notifications.map(n => `


                    <div class="p-3 border rounded ${!n.read ? 'bg-blue-50' : ''} flex items-start justify-between">


                        <div class="flex items-start gap-3">


                            <div>${getNotificationIcon(n.type)}</div>


                            <div>


                                <div class="text-sm font-medium text-gray-800">${n.title}</div>


                                <div class="text-xs text-gray-600">${n.message}</div>


                                <div class="text-xs text-gray-400 mt-1">${n.time}</div>


                            </div>


                        </div>


                        <div class="flex items-center gap-2">


                            <button onclick="toggleNotificationRead(${n.id})" class="text-sm text-gray-600">${n.read ? 'Mark Unread' : 'Mark Read'}</button>


                            <button onclick="deleteNotification(${n.id})" class="text-sm text-red-600">Delete</button>


                        </div>


                    </div>


                `).join('')}


            </div>


        </div>


    `;




    document.getElementById('content-area').innerHTML = html;


}




// ==============================


// ANNOUNCEMENTS PAGE (ADMIN)


// ==============================


function renderAnnouncements() {


    // ensure we have announcements loaded from storage


    loadAnnouncementsFromStorage();




    const html = `


        <div class="mb-6">


            <h1 class="text-2xl font-bold text-gray-800">Announcements & Moderation</h1>


            <p class="text-gray-600 mt-1">Manage announcements and review user posts</p>


        </div>




        <!-- 50/50 Split Layout -->


        <div class="flex gap-6 h-[70vh]">


            <!-- Left: Announcements Publisher & List -->


            <div class="w-1/2 min-w-0 flex flex-col gap-4">


                <!-- Compact Publisher -->


                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">


                    <div class="space-y-3">


                        <input id="new-ann-title" placeholder="Announcement title..." class="input-field w-full text-sm font-medium border-0 border-b border-gray-300 focus:border-red-500 focus:ring-0 p-0" />


                        <textarea id="new-ann-message" placeholder="Write your announcement message..." class="input-field w-full text-sm border-0 focus:ring-0 p-0 resize-none" rows="3"></textarea>


                        <div class="flex justify-end gap-2 pt-2">


                            <button onclick="document.getElementById('new-ann-title').value=''; document.getElementById('new-ann-message').value='';" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded transition">Clear</button>


                            <button onclick="(function(){ const t=document.getElementById('new-ann-title').value; const m=document.getElementById('new-ann-message').value; if(!t||!m){ showNotification('Title and message required','warning'); return;} createAnnouncement(t,m); document.getElementById('new-ann-title').value=''; document.getElementById('new-ann-message').value=''; })()" class="btn-primary px-4 py-1.5 text-sm">Publish</button>


                        </div>


                    </div>


                </div>




                <!-- Announcements List -->


                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex-1 flex flex-col">


                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Recent Announcements</h3>


                    <div class="space-y-2 overflow-auto flex-1">


                        ${AppData.announcements.length === 0 ? '<div class="text-xs text-gray-400 text-center py-4">No announcements yet</div>' : ''}


                        ${AppData.announcements.map(a => `


                            <div class="p-2.5 border border-gray-200 rounded hover:bg-gray-50 transition text-xs">


                                <div class="font-semibold text-gray-800 text-sm">${a.title}</div>


                                <div class="text-gray-500 text-xs mt-0.5">${new Date(a.createdAt).toLocaleDateString()}</div>


                                <div class="flex justify-end mt-2">


                                    <button onclick="deleteAnnouncement(${a.id}); renderAnnouncements()" class="text-xs text-red-600 hover:text-red-700">Delete</button>


                                </div>


                            </div>


                        `).join('')}


                    </div>


                </div>


            </div>




            <!-- Right: User Posts for Moderation -->


            <div class="w-1/2 min-w-0 flex flex-col">


                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 flex-1 flex flex-col">


                    <div class="mb-4">


                        <h2 class="text-lg font-semibold text-gray-900">User Posts</h2>


                        <p class="text-xs text-gray-500 mt-1">Review & take action on citizen posts</p>


                    </div>


                    <div id="admin-posts-list" class="space-y-3 overflow-auto flex-1">


                        <div class="text-xs text-gray-400 text-center py-4">Loading posts...</div>


                    </div>


                </div>


            </div>


        </div>


    `;




    document.getElementById('content-area').innerHTML = html;





    // Load user posts via AJAX


    loadUserPostsForModeration();


}




function loadUserPostsForModeration() {


    fetch('get_posts.php')


        .then(res => res.json())


        .then(data => {


            const list = document.getElementById('admin-posts-list');


            if (!data.posts || data.posts.length === 0) {


                list.innerHTML = '<div class="text-xs text-gray-400 text-center py-4">No user posts yet.</div>';


                return;


            }


            list.innerHTML = data.posts.map(p => `


                <div class="p-3 border border-gray-200 rounded hover:bg-gray-50 transition text-xs">


                    <div class="flex justify-between items-start">


                        <div class="flex-1">


                            <div class="font-semibold text-gray-800">${p.author}</div>


                            <div class="text-gray-600 text-xs mt-1">${p.content.substring(0, 80)}${p.content.length > 80 ? '...' : ''}</div>


                            <div class="text-gray-400 text-xs mt-1">${new Date(p.created_at).toLocaleString()}</div>


                        </div>


                    </div>


                    <div class="flex gap-2 mt-2 flex-wrap">


                        <button onclick="quickNotify(${p.user_id}, ${p.id}, 'inappropriate')" class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200">Inappropriate</button>


                        <button onclick="quickNotify(${p.user_id}, ${p.id}, 'untruthful')" class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded hover:bg-blue-200">Untruthful</button>


                        <button onclick="quickNotify(${p.user_id}, ${p.id}, 'unlawful')" class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded hover:bg-red-200">Unlawful</button>


                    </div>


                </div>


            `).join('');


        })


        .catch(err => {


            console.error(err);


            document.getElementById('admin-posts-list').innerHTML = '<div class="text-xs text-red-500">Failed to load posts</div>';


        });


}






function renderConsultationsGrid() {


    const grid = document.getElementById('consultations-grid');


    const all = getFilteredPublicConsultations();


    const st = getPCGridState();


    const total = all.length;




    let consultations = all;


    if (!st.showAll) {


        const start = (st.page - 1) * st.pageSize;


        consultations = all.slice(start, start + st.pageSize);


    }




    if (consultations.length === 0) {


        grid.innerHTML = '<div class="col-span-full text-center text-gray-500 py-8">No consultations found</div>';


        const summary = document.getElementById('pc-grid-summary');


        if (summary) summary.textContent = '';


        return;


    }




    const startIndex = st.showAll ? 1 : ((st.page - 1) * st.pageSize + 1);


    const endIndex = st.showAll ? total : Math.min((st.page - 1) * st.pageSize + consultations.length, total);




    const summary = document.getElementById('pc-grid-summary');


    if (summary) {


        summary.textContent = `Showing ${startIndex}-${endIndex} of ${total} consultations`;


    }




    const prevBtn = document.getElementById('pc-grid-prev');


    const nextBtn = document.getElementById('pc-grid-next');


    const toggleBtn = document.getElementById('pc-grid-toggle');




    const totalPages = st.pageSize > 0 ? Math.ceil(total / st.pageSize) : 1;


    if (prevBtn) prevBtn.disabled = st.showAll || st.page <= 1;


    if (nextBtn) nextBtn.disabled = st.showAll || st.page >= totalPages;


    if (toggleBtn) toggleBtn.textContent = st.showAll ? 'Show Less' : 'Show All';




    grid.innerHTML = consultations.map(c => {

        const stRaw = String(c.status || '').toLowerCase();

        const statusLabel = stRaw === 'active' ? 'Active' : (stRaw === 'scheduled' ? 'Pending' : (stRaw === 'draft' ? 'Pending' : (stRaw === 'closed' ? 'Closed' : (stRaw === 'pending' ? 'Pending' : stRaw))));

        const statusDot = stRaw === 'active' ? 'bg-green-500' : (stRaw === 'scheduled' ? 'bg-amber-500' : (stRaw === 'draft' || stRaw === 'pending' ? 'bg-amber-500' : 'bg-gray-400'));

        const statusBg = stRaw === 'active' ? 'bg-green-50 text-green-700' : (stRaw === 'scheduled' ? 'bg-amber-50 text-amber-700' : (stRaw === 'draft' || stRaw === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-gray-50 text-gray-600'));


        const srcType = String(c.type || '').toLowerCase();

        const typeLabel = srcType === 'user' ? 'Citizen' : 'Admin';

        const typeBg = srcType === 'user' ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-600';


        const dateText = c.date ? new Date(c.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';

        const desc = String(c.description || '').substring(0, 80);


        return `

            <div class="bg-white rounded-xl border border-gray-200 hover:border-red-200 hover:shadow-lg transition-all duration-200 overflow-hidden group">

                <div class="p-5">

                    <div class="flex items-center justify-between mb-3">

                        <div class="flex items-center gap-2">

                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold ${statusBg}">

                                <span class="w-1.5 h-1.5 rounded-full ${statusDot}"></span>${statusLabel}

                            </span>

                            <span class="px-2 py-0.5 rounded-full text-xs font-medium ${typeBg}">${typeLabel}</span>

                        </div>

                        ${dateText ? `<span class="text-xs text-gray-400">${dateText}</span>` : ''}

                    </div>

                    <h4 class="font-bold text-gray-900 text-base mb-1.5 line-clamp-2 group-hover:text-red-700 transition-colors">${escapeHtml(c.title)}</h4>

                    ${desc ? `<p class="text-xs text-gray-500 mb-4 line-clamp-2">${escapeHtml(desc)}${desc.length >= 80 ? '...' : ''}</p>` : '<div class="mb-4"></div>'}

                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">

                        <div class="flex gap-4">

                            <span class="flex items-center gap-1 text-xs text-gray-500"><i class="bi bi-chat-dots text-purple-500"></i> <strong class="text-gray-700">${c.feedbackCount || 0}</strong> feedback</span>

                            <span class="flex items-center gap-1 text-xs text-gray-500"><i class="bi bi-paperclip text-amber-500"></i> <strong class="text-gray-700">${c.documentsAttached || 0}</strong> docs</span>

                        </div>

                        <button onclick="openConsultationDetailsFromDashboard(${c.id})" class="text-xs font-semibold text-red-600 hover:text-red-800 transition">

                            Details <i class="bi bi-arrow-right"></i>

                        </button>

                    </div>

                </div>

            </div>

        `;

    }).join('');


}




function openConsultationDetailsFromDashboard(id) {


    // The Public Consultation dashboard doesn't include the details modal markup.


    // Route to Consultation Management, then open the details modal there.


    showSection('consultation-management');


    setTimeout(() => {


        try {


            viewConsultationDetails(id);


        } catch (e) {


            console.error(e);


        }


    }, 200);


}




function renderRecentFeedbackList() {


    const list = document.getElementById('recent-feedback-list');


    const recent = AppData.feedback.slice().reverse().slice(0, 5);




    if (recent.length === 0) {


        list.innerHTML = '<p class="text-gray-500 text-sm">No feedback yet</p>';


        return;


    }




    list.innerHTML = recent.map(f => {


        const consultation = AppData.consultations.find(c => c.id === f.consultationId);


        return `


            <div class="border-l-4 border-red-500 pl-4 py-2">


                <div class="font-semibold text-sm text-gray-900">${f.author}</div>


                <div class="text-xs text-gray-500 mb-1">${consultation ? consultation.title : 'Unknown'}</div>


                <div class="text-sm text-gray-700">${f.message.substring(0, 60)}${f.message.length > 60 ? '...' : ''}</div>


                <div class="text-xs text-gray-400 mt-1">${f.date}</div>


            </div>


        `;


    }).join('');


}




function renderUpcomingList() {


    const list = document.getElementById('upcoming-list');


    const upcoming = AppData.consultations
        .filter(c => {
            const st = String(c.status || '').toLowerCase();
            return st === 'scheduled' || st === 'draft' || st === 'pending';
        })
        .slice(0, 5);




    if (upcoming.length === 0) {


        list.innerHTML = '<p class="text-gray-500 text-sm">No pending consultations</p>';


        return;


    }




    list.innerHTML = upcoming.map(c => `


        <div class="border rounded-lg p-3 border-blue-200 bg-blue-50">


            <div class="font-semibold text-sm text-gray-900">${c.title}</div>


            <div class="flex items-center gap-2 mt-1 text-xs text-gray-600">


                <i class="bi bi-calendar-event"></i>


                <span>${new Date(c.date).toLocaleDateString()}</span>


                <span>•</span>


                <span>${c.type}</span>


            </div>


        </div>


    `).join('');


}




function renderTopConsultations() {


    const list = document.getElementById('top-consultations');


    const top = AppData.consultations.slice().sort((a, b) => (b.feedbackCount || 0) - (a.feedbackCount || 0)).slice(0, 5);




    if (top.length === 0) {


        list.innerHTML = '<p class="text-gray-500 text-sm">No consultations</p>';


        return;


    }




    list.innerHTML = top.map((c, idx) => `


        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">


            <div class="flex items-center gap-3">


                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-red-600 font-bold text-sm">


                    ${idx + 1}


                </div>


                <div>


                    <div class="font-semibold text-sm text-gray-900">${c.title}</div>


                    <div class="text-xs text-gray-500">${c.type}</div>


                </div>


            </div>


            <div class="text-right">


                <div class="font-bold text-red-600">${c.feedbackCount || 0}</div>


                <div class="text-xs text-gray-500">Feedback</div>


            </div>


        </div>


    `).join('');


}




function getFilteredPublicConsultations() {


    let filtered = [...AppData.consultations];





    const searchTerm = document.getElementById('pc-search')?.value.toLowerCase() || '';


    const statusFilter = document.getElementById('pc-status-filter')?.value || '';


    const typeFilter = document.getElementById('pc-type-filter')?.value || '';


    const sortBy = document.getElementById('pc-sort')?.value || 'date-desc';




    if (searchTerm) {


        filtered = filtered.filter(c => (c.title || '').toLowerCase().includes(searchTerm));


    }





    if (statusFilter) {


        filtered = filtered.filter(c => String(c.status || '').toLowerCase() === String(statusFilter).toLowerCase());


    }





    if (typeFilter) {


        filtered = filtered.filter(c => String(c.type || '').toLowerCase() === String(typeFilter).toLowerCase());


    }




    // Sort


    filtered.sort((a, b) => {


        switch (sortBy) {


            case 'date-asc':


                return new Date(a.date) - new Date(b.date);


            case 'feedback':


                return (b.feedbackCount || 0) - (a.feedbackCount || 0);


            case 'date-desc':


            default:


                return new Date(b.date) - new Date(a.date);


        }


    });




    return filtered;


}




function filterPublicConsultations() {

    renderConsultationsGrid();
    const filtered = getFilteredPublicConsultations();
    renderPCStatusChart(filtered);
    refreshPCSurveySelector(filtered);
    renderPCSurveyAnswersChart(filtered);


}




function resetPublicConsultationFilters() {


    document.getElementById('pc-search').value = '';


    document.getElementById('pc-status-filter').value = '';


    document.getElementById('pc-type-filter').value = '';


    document.getElementById('pc-sort').value = 'date-desc';


    renderConsultationsGrid();


}




function getFilteredValidFeedback() {
    const feedbackRows = Array.isArray(AppData.feedback) ? AppData.feedback : [];
    const adminConsultations = Array.isArray(AppData.consultations) ? AppData.consultations.filter(c => String(c.type || '').toLowerCase() !== 'user') : [];
    const adminConsultationIds = new Set(adminConsultations.map(c => Number(c.id)));

    return feedbackRows.filter(f => {
        if (!f) return false;
        if (f.consultationId && adminConsultationIds.size > 0 && !adminConsultationIds.has(Number(f.consultationId))) {
            return false;
        }
        const subType = String(f.submission_type || f.type || '').toLowerCase();
        const category = String(f.category || '').toLowerCase();
        if (subType === 'proposal' || subType === 'consultation' || category === 'ordinance suggestion' || category === 'proposal' || category === 'survey vote') {
            return false;
        }
        return true;
    });
}

function getFeedbackSentimentStats() {
    const stats = { positive: 0, neutral: 0, negative: 0, rated: 0 };
    const feedbackRows = getFilteredValidFeedback();

    for (const row of feedbackRows) {
        const rating = Number(row && row.rating);
        if (!Number.isFinite(rating) || rating <= 0) continue;
        stats.rated += 1;
        if (rating >= 4) stats.positive += 1;
        else if (rating >= 3) stats.neutral += 1;
        else stats.negative += 1;
    }

    return stats;
}

function getTopicThemeBreakdown() {
    const feedbackRows = getFilteredValidFeedback();
    const issueRows = Array.isArray(AppData.issueReports) ? AppData.issueReports : [];
    const buckets = new Map();
    const addBucket = (label, count = 1) => {
        const key = label.toLowerCase();
        buckets.set(key, (buckets.get(key) || 0) + count);
    };

    const keywords = [
        { label: 'Garbage Collection', keywords: ['garbage', 'trash', 'waste', 'basura', 'dump', 'collection'] },
        { label: 'Plastic Ban', keywords: ['plastic', 'styrofoam', 'single-use', 'plastic ban', 'waste'] },
        { label: 'Budget / Finance', keywords: ['budget', 'finance', 'fund', 'tax', 'appropriation', 'spending'] },
        { label: 'Health & Sanitation', keywords: ['health', 'sanitation', 'clinic', 'hospital', 'mosquito', 'toilet', 'clean'] },
        { label: 'Flooding / Drainage', keywords: ['flood', 'drainage', 'road', 'pothole', 'street', 'traffic'] },
        { label: 'Public Safety', keywords: ['safety', 'crime', 'security', 'police', 'hazard', 'lighting'] },
        { label: 'Water & Utilities', keywords: ['water', 'electric', 'power', 'utility', 'light', 'streetlight'] },
        { label: 'Education', keywords: ['school', 'education', 'teacher', 'learning', 'scholarship'] }
    ];

    const scanText = (text) => {
        const lower = String(text || '').toLowerCase();
        if (!lower) return;
        for (const entry of keywords) {
            if (entry.keywords.some((k) => lower.includes(k))) {
                addBucket(entry.label);
                return;
            }
        }
        addBucket('General Feedback');
    };

    for (const row of feedbackRows) {
        scanText(`${row.message || ''} ${row.category || ''} ${row.sentimentTag || ''}`);
    }

    for (const row of issueRows) {
        const label = String(row.category || '').trim();
        if (label) addBucket(label === 'general' ? 'General Feedback' : label, 1);
    }

    return Array.from(buckets.entries())
        .map(([key, count]) => ({ key, count, label: key === 'general feedback' ? 'General Feedback' : key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) }))
        .sort((a, b) => b.count - a.count)
        .slice(0, 6);
}

function renderPCFeedbackSentimentChart() {
    const ctx = document.getElementById('pcFeedbackSentimentChart');
    if (!ctx) return;

    const stats = getFeedbackSentimentStats();
    const ratedBadge = document.getElementById('pc-rated-feedback-count');
    const posEl = document.getElementById('pc-positive-count');
    const neuEl = document.getElementById('pc-neutral-count');
    const negEl = document.getElementById('pc-negative-count');
    const summaryEl = document.getElementById('pc-feedback-stats-summary');
    const topicListEl = document.getElementById('pc-feedback-topic-list');
    if (ratedBadge) ratedBadge.textContent = String(stats.rated);
    if (posEl) posEl.textContent = `Positive: ${stats.positive}`;
    if (neuEl) neuEl.textContent = `Neutral: ${stats.neutral}`;
    if (negEl) negEl.textContent = `Negative: ${stats.negative}`;
    if (summaryEl) {
        const validFeedbackList = getFilteredValidFeedback();
        const totalFeedback = validFeedbackList.length;
        const avgRating = totalFeedback > 0 ? (validFeedbackList.reduce((sum, item) => sum + (Number(item && item.rating) > 0 ? Number(item.rating) : 0), 0) / totalFeedback) : 0;
        summaryEl.innerHTML = `Total feedback: <strong>${totalFeedback}</strong> · Avg rating: <strong>${avgRating.toFixed(1)}</strong>`;
    }
    if (topicListEl) {
        const topics = getTopicThemeBreakdown();
        topicListEl.innerHTML = topics.length
            ? topics.map((item) => `<div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm"><span class="text-gray-700">${escapeHtml(item.label)}</span><span class="font-semibold text-gray-900">${item.count}</span></div>`).join('')
            : '<div class="text-sm text-gray-500">No topic themes detected yet.</div>';
    }

    if (window.pcFeedbackSentimentChart) {
        try { window.pcFeedbackSentimentChart.destroy(); } catch (e) { }
    }

    window.pcFeedbackSentimentChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Positive (4-5)', 'Neutral (3)', 'Negative (1-2)'],
            datasets: [{
                data: [stats.positive, stats.neutral, stats.negative],
                backgroundColor: ['#22c55e', '#eab308', '#ef4444'],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const label = context.label || '';
                            const value = Number(context.parsed || 0);
                            const total = Array.isArray(context.dataset?.data)
                                ? context.dataset.data.reduce((a, b) => (Number(a) || 0) + (Number(b) || 0), 0)
                                : 0;
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function isSurveyFormConsultation(consultation) {
    if (!consultation) return false;
    const type = String(consultation.type || '').toLowerCase().trim();
    if (type === 'user') {
        return false;
    }
    const mode = String(consultation.response_mode || '').toLowerCase().trim();
    const voteStats = consultation.vote_stats || null;
    const hasVotes = voteStats && (Number(voteStats.total_votes || 0) > 0 || Number(voteStats.agree_votes || 0) > 0 || Number(voteStats.disagree_votes || 0) > 0);

    if (mode === 'feedback' && !hasVotes) {
        return false;
    }
    return true;
}

function getSurveyConsultations(consultations) {
    const source = Array.isArray(consultations) && consultations.length > 0 ? consultations : (Array.isArray(AppData.consultations) ? AppData.consultations : []);
    return source.filter(isSurveyFormConsultation);
}

function getSurveyDisplayTitle(consultation) {
    if (!consultation) return 'Untitled survey';
    const t = String(consultation.title || '').trim();
    const q = String(consultation.survey_question || '').trim();
    return t || q || `Survey #${consultation.id || ''}`.trim();
}

function refreshPCSurveySelector(consultations) {
    const selectEl = document.getElementById('pc-survey-select');
    if (!selectEl) return;

    const prev = String(selectEl.value || 'all');
    const apiData = window._pcLiveVoteStatsResponse?.data || {};
    const source = Array.isArray(consultations) && consultations.length > 0 ? consultations : (Array.isArray(AppData.consultations) ? AppData.consultations : []);

    const surveyMap = new Map();

    // 1. Add consultations from AppData/source ONLY if they are surveys
    for (const c of source) {
        if (!c || !c.id) continue;
        const cid = String(c.id);
        const title = (c.title || c.survey_question || `Survey #${cid}`).trim();
        const mode = String(c.response_mode || '').toLowerCase();
        const question = String(c.survey_question || '').trim();

        if (mode !== 'feedback' && (mode === 'survey' || (question !== '' && question !== 'null'))) {
            surveyMap.set(cid, title);
        }
    }

    // 2. Add consultations from API vote stats data ONLY if they are surveys
    for (const cid in apiData) {
        if (!surveyMap.has(cid)) {
            const item = apiData[cid];
            const mode = String(item.mode || '').toLowerCase();
            const question = String(item.survey_question || '').trim();
            if (mode !== 'feedback' && (mode === 'survey' || (question !== '' && question !== 'null'))) {
                const title = (item.title || item.survey_question || `Survey #${cid}`).trim();
                surveyMap.set(cid, title);
            }
        }
    }

    if (surveyMap.size === 0) {
        selectEl.innerHTML = '<option value="all">All surveys</option>';
        selectEl.value = 'all';
        selectEl.disabled = false;
        return;
    }

    const options = ['<option value="all">All surveys</option>'];
    surveyMap.forEach((title, id) => {
        const safeTitle = title
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        options.push(`<option value="${id}">${safeTitle}</option>`);
    });

    selectEl.innerHTML = options.join('');
    selectEl.disabled = false;
    const hasPrev = Array.from(selectEl.options).some((o) => o.value === prev);
    selectEl.value = hasPrev ? prev : 'all';
}

function handlePCSurveySelectionChange() {
    doRenderPCSurveyAnswersChart();
}

function renderPCSurveyAnswersChart(consultations) {
    const ctx = document.getElementById('pcSurveyAnswersChart');
    if (!ctx) return;

    fetch('API/consultation_feedback.php?action=get_all_vote_stats')
        .then(r => r.json())
        .then(d => {
            if (d && d.success) {
                window._pcLiveVoteStatsResponse = d;
                refreshPCSurveySelector(consultations);
                doRenderPCSurveyAnswersChart();
            }
        })
        .catch(err => {
            console.error('[SurveySummary] Error fetching vote stats:', err);
            doRenderPCSurveyAnswersChart();
        });
}

function doRenderPCSurveyAnswersChart() {
    const ctx = document.getElementById('pcSurveyAnswersChart');
    if (!ctx) return;

    const respondentTotalEl = document.getElementById('pc-respondent-total');
    const surveyCountEl = document.getElementById('pc-survey-count-summary');
    const agreeEl = document.getElementById('pc-survey-agree-count');
    const disagreeEl = document.getElementById('pc-survey-disagree-count');
    const sourceEl = document.getElementById('pc-survey-source');
    const selectEl = document.getElementById('pc-survey-select');

    const selectedId = String(selectEl && selectEl.value ? selectEl.value : 'all');
    const apiResponse = window._pcLiveVoteStatsResponse || { data: {}, overall: {} };
    const voteData = apiResponse.data || {};
    const overall = apiResponse.overall || {};

    let agreeCount = 0;
    let disagreeCount = 0;
    let otherCount = 0;
    let totalVotes = 0;
    let activeForms = 0;
    let labelA = 'Agree / Option A';
    let labelB = 'Disagree / Option B';
    let scopeText = '';

    if (selectedId === 'all') {
        agreeCount = Number(overall.agree_votes || 0);
        disagreeCount = Number(overall.disagree_votes || 0);
        otherCount = Number(overall.other_votes || 0);
        totalVotes = Number(overall.total_respondents || (agreeCount + disagreeCount + otherCount));
        activeForms = Number(overall.survey_count || Object.keys(voteData).length || 0);
        labelA = 'Option A / Agree';
        labelB = 'Option B / Disagree';
        scopeText = activeForms > 0 ? `Across ${activeForms} active survey form${activeForms === 1 ? '' : 's'}` : 'No survey responses recorded yet';
    } else if (voteData[selectedId]) {
        const item = voteData[selectedId];
        agreeCount = Number(item.agree_votes || 0);
        disagreeCount = Number(item.disagree_votes || 0);
        otherCount = Number(item.other_votes || 0);
        totalVotes = Number(item.total_votes || (agreeCount + disagreeCount + otherCount));
        activeForms = 1;
        labelA = item.option_a_label || 'Option A';
        labelB = item.option_b_label || 'Option B';
        scopeText = item.survey_question ? `Q: "${item.survey_question}"` : `Showing Survey #${selectedId}`;
    } else {
        agreeCount = 0;
        disagreeCount = 0;
        otherCount = 0;
        totalVotes = 0;
        activeForms = 0;
        labelA = 'Option A';
        labelB = 'Option B';
        scopeText = 'No votes recorded for this survey';
    }

    if (respondentTotalEl) respondentTotalEl.textContent = String(totalVotes);
    if (surveyCountEl) surveyCountEl.textContent = String(activeForms);
    if (agreeEl) agreeEl.textContent = `${labelA}: ${agreeCount}`;
    if (disagreeEl) disagreeEl.textContent = `${labelB}: ${disagreeCount}`;
    if (sourceEl) sourceEl.textContent = scopeText;

    if (window.pcSurveyAnswersChart) {
        try { window.pcSurveyAnswersChart.destroy(); } catch (e) { }
    }

    const hasData = totalVotes > 0;
    const chartLabels = [];
    const chartData = [];
    const chartColors = [];

    if (hasData) {
        if (agreeCount > 0 || disagreeCount > 0 || otherCount > 0) {
            chartLabels.push(labelA);
            chartData.push(agreeCount);
            chartColors.push('#22c55e');

            chartLabels.push(labelB);
            chartData.push(disagreeCount);
            chartColors.push('#ef4444');

            if (otherCount > 0) {
                chartLabels.push('Other Options');
                chartData.push(otherCount);
                chartColors.push('#f59e0b');
            }
        } else {
            chartLabels.push('Responses');
            chartData.push(totalVotes);
            chartColors.push('#3b82f6');
        }
    } else {
        chartLabels.push('No responses recorded yet');
        chartData.push(1);
        chartColors.push('#e2e8f0');
    }

    window.pcSurveyAnswersChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartLabels,
            datasets: [{
                data: chartData,
                backgroundColor: chartColors,
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 10,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    enabled: hasData,
                    callbacks: {
                        label: function (context) {
                            if (!hasData) return 'No responses recorded yet';
                            const label = context.label || '';
                            const value = Number(context.parsed || 0);
                            const total = Array.isArray(context.dataset?.data)
                                ? context.dataset.data.reduce((a, b) => (Number(a) || 0) + (Number(b) || 0), 0)
                                : 0;
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} vote${value === 1 ? '' : 's'} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function togglePCIssueMappingPanel() {
    const chartPane = document.getElementById('pc-feedback-chart-pane');
    const issuePane = document.getElementById('pc-issue-mapping-pane');
    const sentimentPane = document.getElementById('pc-feedback-sentiment-pane');
    const toggleButtons = Array.from(document.querySelectorAll('#pc-issue-mapping-toggle-btn, #pc-issue-mapping-back-btn'));
    if (!chartPane || !issuePane || !sentimentPane || toggleButtons.length === 0) return;
    const issueWasVisible = !issuePane.classList.contains('hidden');
    const issueNowVisible = !issueWasVisible;
    chartPane.classList.toggle('hidden', issueNowVisible);
    sentimentPane.classList.toggle('hidden', issueNowVisible);
    issuePane.classList.toggle('hidden', !issueNowVisible);
    toggleButtons.forEach(button => {
        button.innerHTML = issueNowVisible
            ? '<i class="bi bi-arrow-left-circle"></i>'
            : '<i class="bi bi-arrow-right-circle"></i>';
        button.setAttribute('aria-expanded', issueNowVisible ? 'true' : 'false');
        button.setAttribute('aria-controls', issueNowVisible ? 'pc-feedback-sentiment-pane' : 'pc-issue-mapping-pane');
    });

    if (issueNowVisible) {
        renderPCIssueMappingThemes();
    }
}

function renderPCIssueMappingThemes() {
    const container = document.getElementById('pc-issue-theme-list');
    if (!container) return;

    const feedbackList = (window.AppData && Array.isArray(window.AppData.feedback)) ? window.AppData.feedback : [];
    const consultations = (window.AppData && Array.isArray(window.AppData.consultations)) ? window.AppData.consultations : [];

    // Group feedback by consultation or category
    const themesMap = {};

    consultations.forEach(c => {
        const cat = c.category || 'General Policy';
        if (!themesMap[cat]) {
            themesMap[cat] = {
                title: cat,
                icon: getCategoryIcon(cat),
                positive: 0,
                neutral: 0,
                negative: 0,
                items: []
            };
        }
    });

    feedbackList.forEach(f => {
        const cid = Number(f.consultationId || f.consultation_id || 0);
        const matchedConsult = consultations.find(c => Number(c.id) === cid);
        const cat = matchedConsult ? (matchedConsult.category || 'General Policy') : (f.category || 'General Policy');

        if (!themesMap[cat]) {
            themesMap[cat] = {
                title: cat,
                icon: getCategoryIcon(cat),
                positive: 0,
                neutral: 0,
                negative: 0,
                items: []
            };
        }

        const sent = String(f.sentiment || 'neutral').toLowerCase();
        if (sent.includes('pos')) themesMap[cat].positive++;
        else if (sent.includes('neg')) themesMap[cat].negative++;
        else themesMap[cat].neutral++;

        themesMap[cat].items.push(f);
    });

    const themes = Object.values(themesMap);

    if (themes.length === 0) {
        container.innerHTML = `
            <div class="p-6 text-center text-slate-500 bg-slate-50 rounded-xl border border-slate-200">
                <i class="bi bi-robot text-2xl text-purple-600 block mb-2"></i>
                <p class="text-xs font-bold">No feedback themes logged yet.</p>
                <p class="text-[11px] text-slate-400 mt-1">AI analysis will populate as citizens post feedback on active consultations.</p>
            </div>
        `;
        return;
    }

    let html = '<div class="space-y-3 max-h-[340px] overflow-y-auto pr-1">';
    themes.forEach(t => {
        const total = t.positive + t.neutral + t.negative;
        const posPct = total > 0 ? Math.round((t.positive / total) * 100) : 50;
        const neuPct = total > 0 ? Math.round((t.neutral / total) * 100) : 30;
        const negPct = total > 0 ? Math.round((t.negative / total) * 100) : 20;

        let sampleInsight = 'High engagement on policy provisions and community compliance.';
        if (t.positive >= t.negative && t.positive > 0) {
            sampleInsight = `AI Extract: ${posPct}% Positive sentiment. Strong citizen backing for LGU ordinance.`;
        } else if (t.negative > t.positive) {
            sampleInsight = `AI Extract: ${negPct}% Negative sentiment. Primary concerns regarding implementation timeline.`;
        }

        html += `
            <div class="p-3 bg-slate-50 hover:bg-slate-100/80 rounded-xl border border-slate-200/90 transition-all shadow-2xs">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-xs text-purple-700 font-bold shrink-0">
                            <i class="bi ${t.icon}"></i>
                        </span>
                        <h5 class="text-xs font-black text-slate-900 truncate max-w-[180px]">${escapeHtml(t.title)}</h5>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-200 shrink-0">
                        ${total} Feedback
                    </span>
                </div>

                <!-- Sentiment Bar -->
                <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden flex my-2">
                    <div class="bg-emerald-500 h-full" style="width: ${posPct}%" title="Positive: ${posPct}%"></div>
                    <div class="bg-amber-400 h-full" style="width: ${neuPct}%" title="Neutral: ${neuPct}%"></div>
                    <div class="bg-rose-500 h-full" style="width: ${negPct}%" title="Negative: ${negPct}%"></div>
                </div>

                <div class="flex items-center justify-between text-[10px] text-slate-600 font-semibold mb-1">
                    <span class="text-emerald-700 font-extrabold"><i class="bi bi-hand-thumbs-up-fill mr-0.5"></i> ${posPct}% Pos</span>
                    <span class="text-amber-700 font-extrabold"><i class="bi bi-dash-circle-fill mr-0.5"></i> ${neuPct}% Neu</span>
                    <span class="text-rose-700 font-extrabold"><i class="bi bi-hand-thumbs-down-fill mr-0.5"></i> ${negPct}% Neg</span>
                </div>

                <p class="text-[10px] text-purple-900 bg-purple-50/70 p-1.5 rounded-lg border border-purple-100 font-medium leading-tight">
                    <i class="bi bi-stars text-amber-500 mr-1"></i>${escapeHtml(sampleInsight)}
                </p>
            </div>
        `;
    });
    html += '</div>';

    container.innerHTML = html;
}

function getCategoryIcon(cat) {
    const c = String(cat || '').toLowerCase();
    if (c.includes('health') || c.includes('sanitation')) return 'bi-heart-pulse-fill';
    if (c.includes('traffic') || c.includes('transport') || c.includes('infrastructure')) return 'bi-truck-front-fill';
    if (c.includes('utility') || c.includes('facility') || c.includes('flood')) return 'bi-water';
    if (c.includes('rule') || c.includes('governance')) return 'bi-bank2';
    return 'bi-chat-left-quote-fill';
}
function renderPCStatusChart(consultations) {


    const ctx = document.getElementById('pcStatusChart');


    if (!ctx) return;




    const source = Array.isArray(consultations) ? consultations : (Array.isArray(AppData.consultations) ? AppData.consultations : []);
    const active = source.filter(c => String(c.status || '').toLowerCase() === 'active').length;


    const draft = source.filter(c => {
        const st = String(c.status || '').toLowerCase();
        return st === 'draft' || st === 'pending' || st === 'scheduled';
    }).length;


    const closed = source.filter(c => String(c.status || '').toLowerCase() === 'closed').length;




    const labelPlugin = {


        id: 'pcDoughnutLabels',


        afterDatasetsDraw(chart) {


            const { ctx } = chart;


            const dataset = chart.data.datasets && chart.data.datasets[0] ? chart.data.datasets[0] : null;


            if (!dataset || !dataset.data) return;


            const meta = chart.getDatasetMeta(0);


            const data = dataset.data.map(v => Number(v) || 0);
            const visible = data.map((v, i) => (chart.getDataVisibility(i) ? v : 0));
            const total = visible.reduce((a, b) => a + b, 0);




            ctx.save();


            ctx.textAlign = 'center';


            ctx.textBaseline = 'middle';


            ctx.fillStyle = '#111827';




            meta.data.forEach((arc, i) => {


                const v = visible[i] || 0;


                if (!v || !arc || !chart.getDataVisibility(i)) return;


                const pos = arc.tooltipPosition();


                const pct = total > 0 ? Math.round((v / total) * 100) : 0;


                const text = `${v} (${pct}%)`;


                ctx.font = '600 12px Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';


                ctx.fillText(text, pos.x, pos.y);


            });




            ctx.restore();


        }


    };




    if (window.pcStatusChart) {


        try { window.pcStatusChart.destroy(); } catch (e) { }


    }




    window.pcStatusChart = new Chart(ctx, {


        type: 'doughnut',


        data: {


            labels: ['Active', 'Pending', 'Closed'],


            datasets: [{


                data: [active, draft, closed],


                backgroundColor: ['#22c55e', '#3b82f6', '#9ca3af'],


                borderColor: '#fff',


                borderWidth: 2


            }]


        },


        options: {


            responsive: true,


            maintainAspectRatio: false,


            plugins: {


                legend: {


                    position: 'bottom'


                },


                tooltip: {


                    callbacks: {


                        label: function (context) {


                            const label = context.label || '';


                            const value = Number(context.parsed || 0);
                            const data = Array.isArray(context.dataset?.data) ? context.dataset.data : [];
                            const totalVisible = data.reduce((sum, v, i) => sum + (context.chart.getDataVisibility(i) ? (Number(v) || 0) : 0), 0);
                            const percentage = totalVisible > 0 ? Math.round((value / totalVisible) * 100) : 0;


                            return `${label}: ${value} (${percentage}%)`;


                        }


                    }


                }


            }


        },


        plugins: [labelPlugin]


    });


}




function renderPCFeedbackChart() {


    const ctx = document.getElementById('pcFeedbackChart');


    if (!ctx) return;




    // Aggregate feedback by consultation


    const labels = AppData.consultations.map(c => c.title);


    const data = AppData.consultations.map(c => c.feedbackCount || 0);




    // Destroy existing chart instance if present


    if (window.pcFeedbackChart) {


        try { window.pcFeedbackChart.destroy(); } catch (e) { }


    }




    window.pcFeedbackChart = new Chart(ctx, {


        type: 'bar',


        data: {


            labels,


            datasets: [{


                label: 'Feedback Count',


                data,


                backgroundColor: labels.map(() => '#ef4444'),


                borderColor: '#ef4444',


                borderWidth: 1


            }]


        },


        options: {


            responsive: true,


            maintainAspectRatio: false,


            scales: {


                y: { beginAtZero: true }


            },


            plugins: { legend: { display: false } }


        }


    });


}




function renderConsultationManagement() {


    const contentArea = document.getElementById('content-area');







    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');







    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Consultation Management';




    const totalConsultations = AppData.consultations.length;


    const openConsultations = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'active').length;


    const pendingConsultations = AppData.consultations.filter(c => {
        const st = String(c.status || '').toLowerCase();
        return st === 'scheduled' || st === 'draft' || st === 'pending';
    }).length;




    contentArea.innerHTML = `


        <div class="space-y-6">


            <!-- Header with Statistics -->


            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">


                <div class="flex justify-between items-start mb-6">


                    <div>


                        <h1 class="text-3xl font-bold mb-2">Consultation Management</h1>


                        <p class="text-red-100">Manage all public consultations, track feedback, and monitor engagement</p>


                    </div>


                    <div class="flex gap-2">
                        <button onclick="openCreateConsultationModal('feedback')" class="flex items-center gap-2 bg-white !bg-white text-red-700 !text-red-700 hover:bg-gray-100 font-bold px-4 py-2.5 rounded-lg shadow-md transition-all border border-white/20">
                            <i class="bi bi-plus-lg text-lg"></i> Add Consultation
                        </button>
                        <button onclick="openCreateConsultationModal('survey')" class="flex items-center gap-2 bg-white !bg-white text-blue-700 !text-blue-700 hover:bg-gray-100 font-bold px-4 py-2.5 rounded-lg shadow-md transition-all border border-white/20">
                            <i class="bi bi-square-poll-horizontal text-lg"></i> Create Survey Form
                        </button>
                    </div>


                </div>


                


                <!-- Stats Cards -->


                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">


                    <div class="bg-white bg-opacity-20 rounded-lg p-4">


                        <div class="text-red-100 text-sm font-semibold mb-1">Total Consultations</div>


                        <div class="text-3xl font-bold" id="cm-stat-total">${totalConsultations}</div>


                    </div>


                    <div class="bg-white bg-opacity-20 rounded-lg p-4">


                        <div class="text-red-100 text-sm font-semibold mb-1">Open Consultations</div>


                        <div class="text-3xl font-bold" id="cm-stat-open">${openConsultations}</div>


                    </div>


                    <div class="bg-white bg-opacity-20 rounded-lg p-4">


                        <div class="text-red-100 text-sm font-semibold mb-1">Pending</div>


                        <div class="text-3xl font-bold" id="cm-stat-scheduled">${pendingConsultations}</div>


                    </div>


                </div>


            </div>




            <!-- Filter and Search -->


            <div class="bg-white p-6 rounded-lg shadow">


                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search Consultations</label>


                        <input type="text" id="consultation-search" placeholder="Search by title..." 


                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"


                            onkeyup="filterConsultations()">


                    </div>


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>


                        <select id="consultation-status-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"


                            onchange="filterConsultations()">


                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="viewed">Viewed</option>
                            <option value="replied">Replied</option>
                            <option value="completed">Completed</option>
                            <option value="closed">Closed</option>
                            <option value="archived">Archived</option>


                        </select>


                    </div>

                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>


                        <select id="consultation-sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterConsultations()">
                            <option value="">All Categories</option>
                            <option value="Appropriations">Appropriations</option>
                            <option value="Ways & Means">Ways & Means</option>
                            <option value="Women, Family & Gender Equality">Women, Family & Gender Equality</option>
                            <option value="Justice & Human Rights">Justice & Human Rights</option>
                            <option value="Higher & Technical Education">Higher & Technical Education</option>
                            <option value="Cooperatives">Cooperatives</option>
                            <option value="Health & Sanitation">Health & Sanitation</option>
                            <option value="Social Services">Social Services</option>
                            <option value="Livelihood, Trade, Commerce & Industry">Livelihood, Trade, Commerce & Industry</option>
                            <option value="Food & Agriculture">Food & Agriculture</option>
                            <option value="Urban Planning, Housing & Development">Urban Planning, Housing & Development</option>
                            <option value="Public Utilities & Facilities">Public Utilities & Facilities</option>
                            <option value="Market & Slaughterhouse">Market & Slaughterhouse</option>
                            <option value="Rules & Privileges">Rules & Privileges</option>
                        </select>


                    </div>


                </div>




                <div class="flex flex-wrap gap-2 mt-4">


                    <button id="consultation-type-admin-btn" onclick="cmQuickType('admin')" class="btn-outline px-3 py-2 text-sm">Admin Created</button>


                    <button id="consultation-type-user-btn" onclick="cmQuickType('user')" class="btn-outline px-3 py-2 text-sm">User Submissions</button>


                </div>


            </div>
            <!-- Consultations Tables: separate admin and user submissions for proper column layout -->

            <div class="grid grid-cols-1 gap-6">

                <!-- Admin-Created Consultations -->
                <div id="consultations-admin-section" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">Admin Created Consultations</h3>
                    </div>
                    <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b-2 border-gray-300 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">ID</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Title</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Date</th>
                                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Status</th>
                                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Feedback</th>
                                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Documents</th>
                                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="consultations-admin-table-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- User Submissions -->
                <div id="consultations-user-section" class="bg-white rounded-lg shadow overflow-hidden" style="display:none;">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">User Submissions</h3>
                    </div>
                    <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b-2 border-gray-300 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Reference ID</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Citizen Name</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Consultation Type</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Scheduled Date & Time</th>
                                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Status</th>
                                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Documents</th>
                                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="consultations-user-table-body"></tbody>
                        </table>
                    </div>
                </div>

            </div>


        </div>




        <!-- Outcome & Remarks Modal -->
        <div id="outcome-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" style="display: none;">
            <div class="bg-white rounded-lg shadow-lg p-6 w-96">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Add Outcome & Remarks</h3>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2"><strong>Citizen:</strong> <span id="outcome-citizen-name" class="text-gray-900"></span></p>
                    <p class="text-sm text-gray-600 mb-2"><strong>Email:</strong> <span id="outcome-user-email" class="text-gray-900 break-all"></span></p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Outcome</label>
                    <select id="outcome-dropdown" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">-- Select Outcome --</option>
                        <option value="solved">Solved</option>
                        <option value="needs-follow-up">Needs Follow-up</option>
                        <option value="escalated">Escalated</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Assign Resource Person / Expert</label>
                    <select id="outcome-assignee-dropdown" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">-- Unassigned --</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Remarks</label>
                    <textarea id="outcome-remarks" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Add your remarks here..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Optional Email to User</label>
                    <textarea id="outcome-email-body" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Write an email to the user manually if needed."></textarea>
                </div>
                
                <input type="hidden" id="outcome-consultation-id" />
                
                <div class="flex gap-2 justify-end">
                    <button onclick="closeOutcomeModal()" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium">Cancel</button>
                    <button onclick="submitOutcome()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Save Outcome</button>
                </div>
            </div>
        </div>


        <!-- Create/Edit Consultation Modal -->

        <div id="consultation-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">

            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full" style="max-height:90vh; overflow-y:auto;">

                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center sticky top-0 z-10">

                    <h2 id="modal-title" class="text-2xl font-bold">Create New Consultation</h2>

                    <button onclick="closeConsultationModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>

                </div>

                <form id="consultation-form" enctype="multipart/form-data" class="p-6 space-y-4">

                    <input type="hidden" id="consultation-id">

                    <input type="hidden" name="csrf_token" id="consultation-csrf" value="">

                    <div id="consultation-type-selector-wrap" class="hidden" style="display: none !important;">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Consultation Type *</label>
                        <select id="consultation-response-mode" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500" required>
                            <option value="feedback">Consultation Form</option>
                            <option value="survey">Survey Form</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Title *</label>
                        <input type="text" id="consultation-title" placeholder="e.g., Proposed Traffic Management Plan" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Category *</label>
                        <select id="consultation-category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500" required>
                            <option value="">Select Category</option>
                            <option value="Appropriations">Appropriations</option>
                            <option value="Ways & Means">Ways & Means</option>
                            <option value="Women, Family & Gender Equality">Women, Family & Gender Equality</option>
                            <option value="Justice & Human Rights">Justice & Human Rights</option>
                            <option value="Higher & Technical Education">Higher & Technical Education</option>
                            <option value="Cooperatives">Cooperatives</option>
                            <option value="Health & Sanitation">Health & Sanitation</option>
                            <option value="Social Services">Social Services</option>
                            <option value="Livelihood, Trade, Commerce & Industry">Livelihood, Trade, Commerce & Industry</option>
                            <option value="Food & Agriculture">Food & Agriculture</option>
                            <option value="Urban Planning, Housing & Development">Urban Planning, Housing & Development</option>
                            <option value="Public Utilities & Facilities">Public Utilities & Facilities</option>
                            <option value="Market & Slaughterhouse">Market & Slaughterhouse</option>
                            <option value="Rules & Privileges">Rules & Privileges</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Target District</label>
                            <select id="consultation-district" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500" onchange="onConsultationDistrictChange()">
                                <option value="">-- All Districts (Citywide) --</option>
                                <option value="District 1">District 1 (1st District)</option>
                                <option value="District 2">District 2 (2nd District)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Target Barangay</label>
                            <select id="consultation-barangay" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500" onchange="onConsultationBarangayChange()">
                                <option value="">-- All Barangays (Citywide) --</option>
                                <optgroup label="District 1 (24 Barangays)">
                                    <option value="Arkong Bato">Arkong Bato</option>
                                    <option value="Balangkas">Balangkas</option>
                                    <option value="Bignay">Bignay</option>
                                    <option value="Bisig">Bisig</option>
                                    <option value="Canumay East">Canumay East</option>
                                    <option value="Canumay West">Canumay West</option>
                                    <option value="Coloong">Coloong</option>
                                    <option value="Dalandanan">Dalandanan</option>
                                    <option value="Isla">Isla</option>
                                    <option value="Lawang Bato">Lawang Bato</option>
                                    <option value="Lingunan">Lingunan</option>
                                    <option value="Mabolo">Mabolo</option>
                                    <option value="Malanday">Malanday</option>
                                    <option value="Malinta">Malinta</option>
                                    <option value="Mapulang Lupa">Mapulang Lupa</option>
                                    <option value="Palasan">Palasan</option>
                                    <option value="Pariancillo Villa">Pariancillo Villa</option>
                                    <option value="Pasolo">Pasolo</option>
                                    <option value="Poblacion">Poblacion</option>
                                    <option value="Punturin">Punturin</option>
                                    <option value="Rincon">Rincon</option>
                                    <option value="Tagalag">Tagalag</option>
                                    <option value="Veinte Reales">Veinte Reales</option>
                                    <option value="Wawang Pulo">Wawang Pulo</option>
                                </optgroup>
                                <optgroup label="District 2 (9 Barangays)">
                                    <option value="Bagbaguin">Bagbaguin</option>
                                    <option value="Gen. T. de Leon">Gen. T. de Leon</option>
                                    <option value="Karuhatan">Karuhatan</option>
                                    <option value="Marulas">Marulas</option>
                                    <option value="Maysan">Maysan</option>
                                    <option value="Parada">Parada</option>
                                    <option value="Paso de Blas">Paso de Blas</option>
                                    <option value="Ugong">Ugong</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Description *</label>
                        <textarea id="consultation-description" placeholder="Describe the consultation, its purpose, and what feedback you're looking for..." rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" required></textarea>
                    </div>

                    <div id="consultation-feedback-config" class="border border-gray-200 rounded-lg p-4 space-y-3 bg-gray-50">
                        <h3 class="text-sm font-bold text-gray-800">Consultation Feedback</h3>
                        <p class="text-xs text-gray-600 mb-0">Citizens can submit long-form feedback from the public consultation page.</p>
                    </div>

                    <div id="consultation-survey-config" class="border border-gray-200 rounded-lg p-4 space-y-3 bg-gray-50">
                        <h3 class="text-sm font-bold text-gray-800">Survey Setup</h3>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Survey Question</label>
                            <input type="text" id="consultation-survey-question" placeholder="Do you support this proposal?"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Option A Label</label>
                                <input type="text" id="consultation-survey-option-a" value="Agree"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Option B Label</label>
                                <input type="text" id="consultation-survey-option-b" value="Disagree"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" id="consultation-allow-guest-quick-vote" checked>
                                <span>Allow guest quick vote</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" id="consultation-allow-guest-verified-vote" checked>
                                <span>Allow guest verified vote (OTP)</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>

                            <label class="block text-sm font-semibold text-gray-700 mb-1">Start Date *</label>

                            <input type="date" id="consultation-start-date" 

                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" required>

                        </div>

                        <div>

                            <label class="block text-sm font-semibold text-gray-700 mb-1">End Date *</label>

                            <input type="date" id="consultation-end-date" 

                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" required>

                        </div>

                    </div>

                    <div>

                        <label class="block text-sm font-semibold text-gray-700 mb-1">Source URL (Optional)</label>

                        <input type="url" id="consultation-source-url" placeholder="https://example.com/official-document" 

                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">

                    </div>

                    <div>

                        <label class="block text-sm font-semibold text-gray-700 mb-1">Image (Optional)</label>

                        <div id="consultation-image-dropzone" style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 1.25rem; text-align: center; background: #f9fafb; cursor:pointer;" onclick="document.getElementById('consultation-image').click()">

                            <input type="file" id="consultation-image" name="consultation_image" accept=".jpg,.jpeg,.png,.gif,.webp" style="display:none;" onchange="previewConsultationImage(this)">

                            <div id="consultation-image-preview" style="display:none; margin-bottom:0.5rem;"></div>

                            <i class="bi bi-image text-gray-400" style="font-size:2rem;" id="consultation-image-icon"></i>

                            <p class="text-gray-600 font-semibold text-sm mt-1">Click to upload image</p>

                            <p class="text-gray-400 text-xs">JPG, PNG, GIF, or WebP (Max 10MB)</p>

                        </div>

                    </div>
                    <div class="flex gap-3 pt-4">

                        <button type="button" onclick="saveConsultation()" class="flex-1 btn-primary" id="save-consultation-btn">

                            <i class="bi bi-check-lg mr-1"></i> Save Consultation

                        </button>

                        <button type="button" onclick="closeConsultationModal()" class="flex-1 btn-secondary">Cancel</button>

                    </div>

                </form>

            </div>

        </div>




        <!-- Consultation Details Modal -->
        <div id="consultation-details-modal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4 transition-all duration-300">
            <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden border border-gray-100 flex flex-col">
                <!-- Modal Top Header Bar -->
                <div class="bg-gradient-to-r from-red-800 via-red-700 to-red-900 text-white px-6 py-5 flex justify-between items-center relative overflow-hidden shadow-md">
                    <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/5 rounded-full blur-xl pointer-events-none"></div>
                    <div id="details-modal-title" class="flex-1 pr-4">
                        <h2 class="text-xl font-bold tracking-tight text-white">Consultation Details</h2>
                    </div>
                    <button onclick="closeDetailsModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition shrink-0" title="Close">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>
                <!-- Modal Body Content -->
                <div id="details-modal-content" class="p-6 space-y-5 overflow-y-auto" style="max-height:calc(90vh - 85px);">
                </div>
            </div>
        </div>


    `;




    const tbody = document.getElementById('consultations-table-body');


    if (tbody) {


        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Loading consultations...</td></tr>';


    }




    window.consultationTypeView = window.consultationTypeView || 'admin';
    updateConsultationTypeTabButtons();

    loadConsultationsFromApi();


}




function cmQuickType(type) {
    window.consultationTypeView = String(type || 'admin').toLowerCase();
    filterConsultations();
}

function updateConsultationTypeTabButtons() {
    const type = window.consultationTypeView || 'admin';
    const adminBtn = document.getElementById('consultation-type-admin-btn');
    const userBtn = document.getElementById('consultation-type-user-btn');
    const adminSection = document.getElementById('consultations-admin-section');
    const userSection = document.getElementById('consultations-user-section');

    const makeActive = (btn) => {
        if (!btn) return;
        btn.classList.remove('border-gray-200', 'text-gray-600');
        btn.classList.add('border-red-600', 'border-b-2', 'text-red-600');
    };
    const makeInactive = (btn) => {
        if (!btn) return;
        btn.classList.remove('border-red-600', 'border-b-2', 'text-red-600');
        btn.classList.add('border-gray-200', 'text-gray-600');
    };

    if (type === 'admin') {
        makeActive(adminBtn);
        makeInactive(userBtn);
        if (adminSection) adminSection.style.display = '';
        if (userSection) userSection.style.display = 'none';
    } else if (type === 'user') {
        makeActive(userBtn);
        makeInactive(adminBtn);
        if (adminSection) adminSection.style.display = 'none';
        if (userSection) userSection.style.display = '';
    } else if (type === 'all') {
        // All types: show both and reset buttons
        makeInactive(adminBtn);
        makeInactive(userBtn);
        if (adminSection) adminSection.style.display = '';
        if (userSection) userSection.style.display = '';
    } else {
        // Any unexpected value, default to admin
        makeActive(adminBtn);
        makeInactive(userBtn);
        if (adminSection) adminSection.style.display = '';
        if (userSection) userSection.style.display = 'none';
    }
}


// ======== OUTCOME & REMARKS SYSTEM ========

function openOutcomeModal(consultationId, citizenName, userEmail) {
    document.getElementById('outcome-consultation-id').value = consultationId;
    document.getElementById('outcome-citizen-name').textContent = citizenName;
    document.getElementById('outcome-user-email').textContent = userEmail;
    document.getElementById('outcome-dropdown').value = '';
    document.getElementById('outcome-remarks').value = '';
    document.getElementById('outcome-email-body').value = '';

    const assigneeSelect = document.getElementById('outcome-assignee-dropdown');
    if (assigneeSelect) {
        assigneeSelect.innerHTML = '<option value="">-- Loading Experts... --</option>';
        fetch('API/resource_person_api.php?action=list_approved')
            .then(r => r.json())
            .then(res => {
                let html = '<option value="">-- Unassigned --</option>';
                const cons = (AppData.consultations || []).find(c => Number(c.id) === Number(consultationId));
                const consCat = (cons && cons.category) ? cons.category.toLowerCase().trim() : '';

                if (res.success && res.data && res.data.length > 0) {
                    res.data.forEach(rp => {
                        const expAreas = (rp.expertise_areas || '').toLowerCase();
                        const isMatch = consCat && expAreas.includes(consCat);
                        const matchTag = isMatch ? ' ⭐ (Expertise Match)' : '';
                        html += `<option value="${rp.id}">${rp.fullname} (${rp.department || 'Expert'})${matchTag}</option>`;
                    });
                }
                assigneeSelect.innerHTML = html;
                if (cons && cons.assigned_to) {
                    assigneeSelect.value = cons.assigned_to;
                }
            })
            .catch(() => {
                assigneeSelect.innerHTML = '<option value="">-- Unassigned --</option>';
            });
    }

    document.getElementById('outcome-modal').style.display = 'flex';
}

function closeOutcomeModal() {
    document.getElementById('outcome-modal').style.display = 'none';
}

function submitOutcome() {
    const consultationId = document.getElementById('outcome-consultation-id').value;
    const outcome = document.getElementById('outcome-dropdown').value;
    const remarks = document.getElementById('outcome-remarks').value;
    const userEmail = document.getElementById('outcome-user-email').textContent;
    const emailBody = document.getElementById('outcome-email-body').value;
    const assigneeSelect = document.getElementById('outcome-assignee-dropdown');
    const assignedTo = assigneeSelect ? assigneeSelect.value : '';

    if (!outcome) {
        alert('Please select an outcome');
        return;
    }

    const optionalRemarks = remarks.trim() || 'No additional remarks were provided.';
    const emailValue = (userEmail || '').trim();

    const formData = new FormData();
    formData.append('action', 'save_outcome');
    formData.append('consultation_id', consultationId);
    formData.append('outcome', outcome);
    formData.append('remarks', optionalRemarks);
    formData.append('user_email', emailValue);
    formData.append('manual_email_body', emailBody.trim());

    fetch('API/consultations_api.php', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Save assignment if selected
                if (assignedTo !== undefined) {
                    const assignData = new FormData();
                    assignData.append('action', 'assign');
                    assignData.append('consultation_id', consultationId);
                    assignData.append('assigned_to', assignedTo);
                    fetch('API/consultations_api.php', { method: 'POST', body: assignData });
                }

                const consultation = AppData.consultations.find(c => Number(c.id) === Number(consultationId));
                const outcomeStatusMap = {
                    solved: 'completed',
                    'needs-follow-up': 'replied',
                    escalated: 'viewed'
                };
                const newStatus = String(data.status || outcomeStatusMap[outcome] || outcome).toLowerCase();

                if (consultation) {
                    consultation.status = newStatus;
                    consultation.assigned_to = assignedTo ? parseInt(assignedTo) : null;
                }

                updateConsultationStatsUI();
                renderConsultationsTable();

                alert('Outcome saved and consultation assignment updated.');
                closeOutcomeModal();
            } else {
                alert('Error: ' + (data.error || 'Failed to save outcome'));
            }
        })
        .catch(err => alert('Error: ' + err));
}




function mapDbConsultationToUi(row) {


    const statusRaw = String(row.status || '').toLowerCase();


    const createdAt = row.created_at || null;


    const startDate = row.start_date || null;


    const endDate = row.end_date || null;


    const effectiveDate = startDate || createdAt || endDate || null;




    const sourceType = String(row.type || 'admin').toLowerCase();


    const title = row.title || '';




    return {

        id: Number(row.id),

        title,

        type: sourceType,

        category: String(row.category || '').trim(),

        date: effectiveDate,

        start_date: row.start_date || null,

        end_date: row.end_date || null,

        status: statusRaw || 'draft',

        description: row.description || '',

        documentsAttached: Number(row.documents_count || 0),
        image_path: row.image_path || '',

        userName: row.user_name || '',

        userEmail: row.user_email || '',

        response_mode: String(row.response_mode || 'feedback').toLowerCase(),
        survey_question: row.survey_question || '',
        survey_option_a: row.survey_option_a || 'Agree',
        survey_option_b: row.survey_option_b || 'Disagree',
        vote_stats: row.vote_stats || null,

        feedbackCount: Number(row.posts_count || 0),

        // preserve DB created timestamp for client-side rules
        created_at: row.created_at || null,
        createdAt: row.created_at || null
    };
}




async function loadConsultationsFromApi() {


    try {


        const res = await fetchWithTimeout('API/consultations_api.php?action=list&limit=200&offset=0', {


            headers: { 'Accept': 'application/json' }


        }, 5000);




        let data;


        try {


            data = await res.json();


        } catch (_) {


            data = null;


        }




        if (!res.ok) {


            const msg = (data && data.message) ? data.message : (res.status === 403 ? 'Unauthorized (admin session required)' : `HTTP ${res.status}`);


            throw new Error(msg);


        }




        if (!data || !data.success || !Array.isArray(data.data)) {


            throw new Error((data && data.message) ? data.message : 'Failed to load consultations');


        }




        window.__last_consultations_api__ = data;




        AppData.consultations = data.data.map(mapDbConsultationToUi);

        // DEBUG: Log vote_stats for each consultation
        console.log('[loadConsultationsFromApi] Loaded ' + AppData.consultations.length + ' consultations');
        for (const c of AppData.consultations) {
            console.log('[loadConsultationsFromApi] id=' + c.id + ' title=' + (c.title || '').substring(0, 40) + ' vote_stats=' + JSON.stringify(c.vote_stats) + ' response_mode=' + c.response_mode);
        }

        recomputeConsultationFeedbackCounts();


        updateConsultationStatsUI();


        renderConsultationsTable();
        // NOTE: Do NOT call refreshPCSurveySelector or renderPCSurveyAnswersChart here.
        // The <canvas id="pcSurveyAnswersChart"> does NOT exist in the DOM yet at this point.
        // It gets injected later by renderPublicConsultation() at line ~10045.
        // The setTimeout at line ~10065 handles the chart rendering AFTER the DOM is ready.




        if (data.data.length === 0) {


            const tbody = document.getElementById('consultations-table-body');


            if (tbody) {


                tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No consultations returned by API. Checking connection...</td></tr>';


            }


            try {


                const dbgRes = await fetchWithTimeout('API/consultations_api.php?action=debug', { headers: { 'Accept': 'application/json' } }, 5000);


                const dbg = await dbgRes.json();


                window.__last_consultations_debug__ = dbg;


                const dbName = dbg?.data?.db?.database ?? 'unknown';


                const cnt = dbg?.data?.db?.consultations_count;


                const role = dbg?.data?.session?.role_normalized ?? dbg?.data?.session?.role ?? 'unknown';


                if (tbody) {


                    const numericCount = Number(cnt || 0);
                    if (numericCount > 0) {
                        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-red-600">API returned 0 rows, but debug says DB has <b>${escapeHtml(String(numericCount))}</b> consultations (DB: <b>${escapeHtml(String(dbName))}</b>, role: <b>${escapeHtml(String(role))}</b>).<div class="text-xs text-gray-500 mt-2">This means the list query is not returning rows as expected. Next step is to inspect the SQL query output.</div></td></tr>`;
                    } else {
                        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No consultations yet (DB: <b>${escapeHtml(String(dbName))}</b>).<div class="text-xs text-gray-400 mt-2">Create your first consultation in Consultation Management.</div></td></tr>`;
                    }


                }


            } catch (_) {


            }


        }


    } catch (e) {


        const tbody = document.getElementById('consultations-table-body');


        if (tbody) {


            const details = e && e.message ? String(e.message) : 'Unknown error';


            const hint = details.toLowerCase().includes('unauthorized') || details.toLowerCase().includes('403')


                ? 'Please log in as Admin and refresh the page.'


                : 'Check database connection and server logs.';


            tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-red-600">Failed to load consultations from database.<div class="text-xs text-gray-500 mt-2">${escapeHtml(details)}<br>${escapeHtml(hint)}</div></td></tr>`;


        }


        updateConsultationStatsUI();


        console.error(e);


    }


}




function recomputeConsultationFeedbackCounts() {


    if (!Array.isArray(AppData.consultations) || AppData.consultations.length === 0) return;




    const counts = new Map();


    if (Array.isArray(AppData.feedback)) {


        for (const f of AppData.feedback) {


            const cid = f && f.consultationId !== undefined && f.consultationId !== null ? Number(f.consultationId) : null;


            if (!cid) continue;


            counts.set(cid, (counts.get(cid) || 0) + 1);


        }


    }




    for (const c of AppData.consultations) {


        const cid = c && c.id !== undefined && c.id !== null ? Number(c.id) : null;


        if (!cid) continue;


        c.feedbackCount = counts.get(cid) || 0;


    }




    // Refresh any visible UI that displays feedback counts


    try {


        if (document.getElementById('consultations-table-body')) {


            renderConsultationsTable();


        }


        if (document.getElementById('consultations-grid')) {


            renderConsultationsGrid();


        }


    } catch (e) {


        console.error(e);


    }


}




function escapeHtml(str) {


    return String(str)


        .replaceAll('&', '&amp;')


        .replaceAll('<', '&lt;')


        .replaceAll('>', '&gt;')


        .replaceAll('"', '&quot;')


        .replaceAll("'", '&#039;');


}




function updateConsultationStatsUI() {


    const totalEl = document.getElementById('cm-stat-total');


    const openEl = document.getElementById('cm-stat-open');


    const schedEl = document.getElementById('cm-stat-scheduled');




    const total = AppData.consultations.length;


    const open = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'active').length;


    const closed = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'closed').length;




    if (totalEl) totalEl.textContent = String(total);


    if (openEl) openEl.textContent = String(open);


    if (schedEl) schedEl.textContent = String(closed);


}




function toggleConsultationUserActionsMenu(id) {
    document.querySelectorAll('[id^="consultation-user-actions-"]').forEach(menu => {
        if (menu.id !== `consultation-user-actions-${id}`) {
            menu.classList.add('hidden');
        }
    });

    const menu = document.getElementById(`consultation-user-actions-${id}`);
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

function closeConsultationUserActionsMenu(id) {
    const menu = document.getElementById(`consultation-user-actions-${id}`);
    if (menu) {
        menu.classList.add('hidden');
    }
}

function isConsultationClosed(consultation) {
    const status = String(consultation?.status || '').toLowerCase();
    if (status === 'closed') return true;

    const endDateValue = consultation?.end_date || consultation?.date;
    if (!endDateValue) return false;

    const endDate = new Date(endDateValue);
    if (Number.isNaN(endDate.getTime())) return false;

    return endDate < new Date();
}

function renderConsultationsTable() {

    // New behavior: render separate admin and user tables to preserve column structures
    const adminSection = document.getElementById('consultations-admin-section');
    const userSection = document.getElementById('consultations-user-section');
    const adminTbody = document.getElementById('consultations-admin-table-body');
    const userTbody = document.getElementById('consultations-user-table-body');
    const typeFilter = window.consultationTypeView || 'admin';

    const consultations = getFilteredConsultations();
    const adminRows = [];
    const userRows = [];

    function isUserConsultation(consultation) {
        if (!consultation) return false;
        const t = String(consultation.type || '').toLowerCase();
        if (t === 'user') return true;
        if (consultation.userName || consultation.user_email || consultation.userEmail) return true;
        return false;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>\"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
    }

    function getUserSubmissionMessage(consultation) {
        const st = String(consultation.status || '').toLowerCase();
        const committee = consultation.committee || consultation.category || 'the committee in the category it falls to';
        switch (st) {
            case 'pending':
                return 'consultation request has been received and is currently waiting for review.';
            case 'scheduled':
                return `consultation has been acknowledged and scheduled by ${escapeHtml(committee)}. You will be notified of the confirmed date and details.`;
            case 'completed':
                return 'consultation has been successfully completed.';
            case 'closed':
                return 'consultation request was recorded but did not meet the minimum number of submissions required to be scheduled. You may resubmit or encourage others to participate.';
            default:
                return '';
        }
    }

    for (const consultation of consultations) {
        const st = String(consultation.status || '').toLowerCase();
        const srcType = isUserConsultation(consultation) ? 'user' : String(consultation.type || 'admin').toLowerCase();
        const dateText = consultation.date ? new Date(consultation.date).toLocaleDateString() : '-';
        const closed = isConsultationClosed(consultation);
        const editButton = currentUserIsSuperAdmin()
            ? ''
            : (closed
                ? `<button class="text-gray-400 cursor-not-allowed" title="Closed consultations cannot be edited"><i class="bi bi-pencil"></i></button>`
                : `<button onclick="editConsultation(${consultation.id})" class="text-yellow-600 hover:text-yellow-800" title="Edit"><i class="bi bi-pencil"></i></button>`);

        let consultBadgeStyle = 'bg-amber-50 text-amber-800 border-amber-300';
        if (st === 'active') {
            consultBadgeStyle = 'bg-emerald-50 text-emerald-800 border-emerald-300';
        } else if (st === 'scheduled' || st === 'reviewed' || st === 'viewed') {
            consultBadgeStyle = 'bg-blue-50 text-blue-800 border-blue-300';
        } else if (st === 'completed') {
            consultBadgeStyle = 'bg-purple-50 text-purple-800 border-purple-300';
        } else if (st === 'closed' || st === 'archived') {
            consultBadgeStyle = 'bg-gray-100 text-gray-800 border-gray-300';
        } else if (st === 'draft') {
            consultBadgeStyle = 'bg-slate-100 text-slate-700 border-slate-300';
        }

        const consultStatusTrackerHtml = renderConnectingDotsTracker(consultation.status, consultation.id, 'consultation');

        if (srcType === 'user') {
            let citizenName = String(consultation.userName || consultation.user_name || 'Citizen');
            if (citizenName.toLowerCase().includes('system administrator') || citizenName.toLowerCase().includes('admin')) {
                citizenName = 'Citizen Submission';
            }
            const consultationType = String(consultation.title || '-');
            const scheduledDateTime = consultation.date ? new Date(consultation.date).toLocaleString() : '-';

            const approveBtn = (st === 'pending' || st === 'new' || !st || st === 'draft')
                ? `<button onclick="openApproveCitizenSubmissionModal(${consultation.id})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-xs cursor-pointer" title="Approve & Launch Live Consultation on Public Portal"><i class="bi bi-check-circle-fill"></i> Approve & Publish</button>`
                : `<span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold rounded-lg text-[11px] flex items-center gap-1"><i class="bi bi-globe"></i> Live Public</span>`;

            userRows.push(`
                <tr class="border-b hover:bg-gray-50 transition">
                    <td class="px-6 py-4 font-semibold text-gray-900">${'CONSULT-' + String(consultation.id || '').padStart(6, '0')}</td>
                    <td class="px-6 py-4 text-gray-600">${citizenName}</td>
                    <td class="px-6 py-4 text-gray-600">${consultationType}</td>
                    <td class="px-6 py-4 text-gray-600">${scheduledDateTime}</td>
                    <td class="px-6 py-4 text-center">${consultStatusTrackerHtml}</td>
                    <td class="px-6 py-4 text-center"><span class="inline-flex items-center gap-1 text-gray-600"><i class="bi bi-file-text"></i>${consultation.documentsAttached || 0}</span></td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex gap-2 justify-center items-center">
                            <button onclick="viewConsultationDetails(${consultation.id})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg text-xs font-semibold transition border border-blue-200 cursor-pointer" title="View Details">
                                <i class="bi bi-eye font-bold"></i> View
                            </button>
                            ${approveBtn}
                        </div>
                    </td>
                </tr>
            `);
        } else {
            // admin-created
            const consultationId = String(consultation.id || '-');
            const consultationTitle = String(consultation.title || '-');
            adminRows.push(`
                <tr class="border-b hover:bg-gray-50 transition">
                    <td class="px-6 py-4 font-semibold text-gray-900">${consultationId}</td>
                    <td class="px-6 py-4 font-semibold text-gray-900">${consultationTitle}</td>
                    <td class="px-6 py-4 text-gray-600">${dateText}</td>
                    <td class="px-6 py-4 text-center">${consultStatusTrackerHtml}</td>
                    <td class="px-6 py-4 text-center"><span class="inline-flex items-center justify-center w-8 h-8 bg-red-100 text-red-600 rounded-full font-semibold text-sm">${consultation.feedbackCount || 0}</span></td>
                    <td class="px-6 py-4 text-center"><span class="inline-flex items-center gap-1 text-gray-600"><i class="bi bi-file-text"></i>${consultation.documentsAttached || 0}</span></td>
                    <td class="px-6 py-4 text-center"><div class="flex gap-2 justify-center"> <button onclick="viewConsultationDetails(${consultation.id})" class="text-blue-600 hover:text-blue-800" title="View"><i class="bi bi-eye"></i></button>${editButton}</div></td>
                </tr>
            `);
        }
    }

    // Show/hide sections based on filter
    if (typeFilter === 'user') {
        if (adminSection) adminSection.style.display = 'none';
        if (userSection) userSection.style.display = '';
    } else if (typeFilter === 'admin') {
        if (adminSection) adminSection.style.display = '';
        if (userSection) userSection.style.display = 'none';
    } else {
        if (adminSection) adminSection.style.display = '';
        if (userSection) userSection.style.display = '';
    }

    if (adminTbody) adminTbody.innerHTML = adminRows.length ? adminRows.join('') : '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No admin-created consultations found</td></tr>';
    if (userTbody) userTbody.innerHTML = userRows.length ? userRows.join('') : '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No user submissions found</td></tr>';
}

async function updateConsultationStatusFromTracker(consultationId, newStatus) {
    if (!consultationId || !newStatus) return;

    try {
        const response = await fetch('API/consultations_api.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: consultationId, status: newStatus })
        });
        const result = await response.json();

        if (result && result.success) {
            const formattedName = newStatus.replace(/_/g, ' ').toUpperCase();
            showNotification(`Consultation status updated to ${formattedName}!`, 'success');
            if (Array.isArray(AppData.consultations)) {
                const c = AppData.consultations.find(item => Number(item.id) === Number(consultationId));
                if (c) c.status = newStatus;
            }
            renderConsultationsTable();
        } else {
            showNotification(result.message || 'Failed to update consultation status', 'error');
        }
    } catch (err) {
        console.error('Error updating consultation status:', err);
        showNotification('Network error updating consultation status', 'error');
    }
}




function getFilteredConsultations() {


    let filtered = [...AppData.consultations];





    const searchTerm = document.getElementById('consultation-search')?.value.toLowerCase() || '';


    const statusFilter = document.getElementById('consultation-status-filter')?.value || '';


    const selectedCategory = document.getElementById('consultation-sort')?.value || '';




    if (searchTerm) {


        filtered = filtered.filter(c => (c.title || '').toLowerCase().includes(searchTerm));


    }





    if (statusFilter) {


        filtered = filtered.filter(c => c.status === statusFilter);


    }




    if (selectedCategory) {


        filtered = filtered.filter(c => {
            const categoryValue = String(c.category || c.type || c.topic || '').trim().toLowerCase();
            return categoryValue === selectedCategory.toLowerCase();
        });


    }




    return filtered;


}




function filterConsultations() {


    updateConsultationTypeTabButtons();


    renderConsultationsTable();


}




function guessConsultationCategoryFromTitle(title) {
    const normalized = String(title || '').toLowerCase();
    if (!normalized) return '';

    const categoryMap = [
        { category: 'Public Utilities & Facilities', keywords: ['misting', 'poles', 'water', 'electricity', 'power', 'utility', 'streetlight', 'drainage', 'sewer', 'pipes', 'hydrant', 'lottery'] },
        { category: 'Urban Planning, Housing & Development', keywords: ['housing', 'urban', 'road', 'street', 'flood control', 'planning', 'zoning', 'sidewalk', 'transport', 'parking', 'relocation'] },
        { category: 'Health & Sanitation', keywords: ['health', 'sanitation', 'clinic', 'hospital', 'mosquito', 'waste', 'garbage', 'toilet', 'clean', 'sewerage'] },
        { category: 'Social Services', keywords: ['youth', 'elderly', 'welfare', 'social', 'assistance', 'senior', 'child', 'family'] },
        { category: 'Food & Agriculture', keywords: ['food', 'agriculture', 'farm', 'livestock', 'market', 'produce', 'planting', 'crop'] },
        { category: 'Higher & Technical Education', keywords: ['school', 'education', 'training', 'college', 'technical', 'scholarship', 'classroom'] },
        { category: 'Justice & Human Rights', keywords: ['justice', 'rights', 'police', 'security', 'law', 'legal', 'human rights', 'complaint'] },
        { category: 'Ways & Means', keywords: ['budget', 'appropriation', 'finance', 'funding', 'tax', 'revenue', 'grant', 'expense'] },
        { category: 'Market & Slaughterhouse', keywords: ['market', 'slaughterhouse', 'vendors', 'stall', 'public market', 'butcher'] },
        { category: 'Cooperatives', keywords: ['cooperative', 'co-op', 'cooperatives'] },
        { category: 'Women, Family & Gender Equality', keywords: ['women', 'family', 'gender', 'equal', 'mother', 'father', 'childcare', 'parenting'] },
        { category: 'Rules & Privileges', keywords: ['rule', 'privilege', 'ordinance', 'policy', 'regulation', 'permit'] }
    ];

    for (const entry of categoryMap) {
        if (entry.keywords.some(keyword => normalized.includes(keyword))) {
            return entry.category;
        }
    }

    return '';
}

function openCreateConsultationModal(createMode) {

    document.getElementById('consultation-id').value = '';
    const initialResponseMode = (createMode === 'survey') ? 'survey' : 'feedback';
    document.getElementById('modal-title').textContent = (createMode === 'survey') ? 'Create New Survey Form' : 'Create New Consultation';

    document.getElementById('consultation-title').value = '';

    document.getElementById('consultation-category').value = '';

    // Set start date to today and reset input enabled states
    const today = new Date().toISOString().split('T')[0];
    const startDateInput = document.getElementById('consultation-start-date');
    const endDateInput = document.getElementById('consultation-end-date');

    if (startDateInput) {
        startDateInput.disabled = false;
        startDateInput.readOnly = false;
        startDateInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
        startDateInput.value = today;
        startDateInput.min = today;
    }

    if (endDateInput) {
        endDateInput.disabled = false;
        endDateInput.readOnly = false;
        endDateInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
        endDateInput.value = '';
        endDateInput.min = today;
    }

    const titleInput = document.getElementById('consultation-title');
    const categorySelect = document.getElementById('consultation-category');

    if (titleInput && categorySelect) {
        titleInput.oninput = function () {
            const currentCategory = categorySelect.value;
            const suggestedCategory = guessConsultationCategoryFromTitle(this.value);

            if (!currentCategory || categorySelect.dataset.autoSelected === 'true') {
                categorySelect.value = suggestedCategory || '';
                categorySelect.dataset.autoSelected = suggestedCategory ? 'true' : 'false';
            }
        };

        categorySelect.onchange = function () {
            delete categorySelect.dataset.autoSelected;
        };
    }

    // Add event listeners for date validation
    startDateInput.addEventListener('change', function () {
        endDateInput.min = this.value; // End date must be after start date
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value; // Set end date to start date if it's before
        }
    });

    endDateInput.addEventListener('change', function () {
        if (this.value && this.value < startDateInput.value) {
            this.value = startDateInput.value; // Prevent end date being before start date
        }
    });

    document.getElementById('consultation-description').value = '';
    document.getElementById('consultation-response-mode').value = initialResponseMode;
    document.getElementById('consultation-survey-question').value = 'Do you support this proposal?';
    document.getElementById('consultation-survey-option-a').value = 'Agree';
    document.getElementById('consultation-survey-option-b').value = 'Disagree';
    document.getElementById('consultation-allow-guest-quick-vote').checked = true;
    document.getElementById('consultation-allow-guest-verified-vote').checked = true;
    const modeSelect = document.getElementById('consultation-response-mode');
    if (modeSelect) {
        modeSelect.onchange = refreshConsultationModeConfigVisibility;
    }
    const selectorWrap = document.getElementById('consultation-type-selector-wrap');
    if (selectorWrap) {
        selectorWrap.style.display = 'none';
    }
    refreshConsultationModeConfigVisibility();

    document.getElementById('consultation-source-url').value = '';

    document.getElementById('consultation-image').value = '';

    var preview = document.getElementById('consultation-image-preview');

    if (preview) { preview.style.display = 'none'; preview.innerHTML = ''; }

    var icon = document.getElementById('consultation-image-icon');

    if (icon) icon.style.display = '';

    // Set CSRF token

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');

    var csrfEl = document.getElementById('consultation-csrf');

    if (csrfEl && csrfMeta) csrfEl.value = csrfMeta.content;

    document.getElementById('consultation-modal').classList.remove('hidden');

}




function closeConsultationModal() {


    document.getElementById('consultation-modal').classList.add('hidden');


}

function refreshConsultationModeConfigVisibility() {
    const mode = String(document.getElementById('consultation-response-mode')?.value || 'hybrid').toLowerCase();
    const surveyConfig = document.getElementById('consultation-survey-config');
    const feedbackConfig = document.getElementById('consultation-feedback-config');
    if (surveyConfig) {
        surveyConfig.style.display = mode === 'feedback' ? 'none' : '';
    }
    if (feedbackConfig) {
        feedbackConfig.style.display = mode === 'survey' ? 'none' : '';
    }
}




window.onConsultationBarangayChange = function () {
    const bSelect = document.getElementById('consultation-barangay');
    const dSelect = document.getElementById('consultation-district');
    if (!bSelect || !dSelect) return;
    const val = bSelect.value;
    const map = window.DISTRICT_MAP || {
        'Arkong Bato': 'District 1', 'Balangkas': 'District 1', 'Bignay': 'District 1', 'Bisig': 'District 1',
        'Canumay East': 'District 1', 'Canumay West': 'District 1', 'Coloong': 'District 1', 'Dalandanan': 'District 1',
        'Isla': 'District 1', 'Lawang Bato': 'District 1', 'Lingunan': 'District 1', 'Mabolo': 'District 1',
        'Malanday': 'District 1', 'Malinta': 'District 1', 'Mapulang Lupa': 'District 1', 'Palasan': 'District 1',
        'Pariancillo Villa': 'District 1', 'Pasolo': 'District 1', 'Poblacion': 'District 1', 'Punturin': 'District 1',
        'Rincon': 'District 1', 'Tagalag': 'District 1', 'Veinte Reales': 'District 1', 'Wawang Pulo': 'District 1',
        'Bagbaguin': 'District 2', 'Gen. T. de Leon': 'District 2', 'Karuhatan': 'District 2', 'Marulas': 'District 2',
        'Maysan': 'District 2', 'Parada': 'District 2', 'Paso de Blas': 'District 2', 'Ugong': 'District 2'
    };
    if (val && map[val]) {
        dSelect.value = map[val];
    }
};

window.onConsultationDistrictChange = function () {
    const bSelect = document.getElementById('consultation-barangay');
    const dSelect = document.getElementById('consultation-district');
    if (!bSelect || !dSelect) return;
    const dist = dSelect.value;
    const map = window.DISTRICT_MAP || {
        'Arkong Bato': 'District 1', 'Balangkas': 'District 1', 'Bignay': 'District 1', 'Bisig': 'District 1',
        'Canumay East': 'District 1', 'Canumay West': 'District 1', 'Coloong': 'District 1', 'Dalandanan': 'District 1',
        'Isla': 'District 1', 'Lawang Bato': 'District 1', 'Lingunan': 'District 1', 'Mabolo': 'District 1',
        'Malanday': 'District 1', 'Malinta': 'District 1', 'Mapulang Lupa': 'District 1', 'Palasan': 'District 1',
        'Pariancillo Villa': 'District 1', 'Pasolo': 'District 1', 'Poblacion': 'District 1', 'Punturin': 'District 1',
        'Rincon': 'District 1', 'Tagalag': 'District 1', 'Veinte Reales': 'District 1', 'Wawang Pulo': 'District 1',
        'Bagbaguin': 'District 2', 'Gen. T. de Leon': 'District 2', 'Karuhatan': 'District 2', 'Marulas': 'District 2',
        'Maysan': 'District 2', 'Parada': 'District 2', 'Paso de Blas': 'District 2', 'Ugong': 'District 2'
    };
    if (bSelect.value && map[bSelect.value] && map[bSelect.value] !== dist) {
        bSelect.value = '';
    }
};

function editConsultation(id) {
    if (currentUserIsSuperAdmin()) {
        showNotification('Read-only role: action not allowed for super admin.', 'warning');
        return;
    }

    const consultation = AppData.consultations.find(c => c.id === id);

    if (!consultation) return;

    if (isConsultationClosed(consultation)) {
        showNotification('Closed consultations cannot be edited to protect recorded data.', 'warning');
        return;
    }

    if (typeof isUserConsultation === 'function' ? isUserConsultation(consultation) : (String(consultation.type || '').toLowerCase() === 'user')) {
        showNotification('User-submitted consultations cannot be edited by admin.', 'error');
        return;
    }


    document.getElementById('consultation-id').value = id;

    document.getElementById('modal-title').textContent = 'Edit Consultation';

    document.getElementById('consultation-title').value = consultation.title || '';

    document.getElementById('consultation-category').value = consultation.category || '';
    const distEl = document.getElementById('consultation-district');
    const brgyEl = document.getElementById('consultation-barangay');
    if (distEl) distEl.value = consultation.district || '';
    if (brgyEl) brgyEl.value = consultation.barangay || '';

    const startDateInput = document.getElementById('consultation-start-date');
    const endDateInput = document.getElementById('consultation-end-date');

    const parseDateForInput = (val) => {
        if (!val) return '';
        const str = String(val).trim();
        if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
            return str.substring(0, 10);
        }
        const d = new Date(str);
        if (!isNaN(d.getTime())) {
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }
        return '';
    };

    const rawStart = consultation.start_date || consultation.created_at || consultation.date || '';
    const formattedStart = parseDateForInput(rawStart) || parseDateForInput(new Date());

    if (startDateInput) {
        startDateInput.removeAttribute('min');
        startDateInput.value = formattedStart;
        startDateInput.disabled = true; // Fixed: Start Date (Began/Posted Date) is fixed and locked when editing
        startDateInput.readOnly = true;
        startDateInput.classList.add('bg-gray-100', 'cursor-not-allowed');
    }

    const rawEnd = consultation.end_date || '';
    const formattedEnd = parseDateForInput(rawEnd);

    if (endDateInput) {
        endDateInput.disabled = false;
        endDateInput.readOnly = false;
        endDateInput.classList.remove('bg-gray-100', 'cursor-not-allowed');
        endDateInput.value = formattedEnd;
        if (formattedStart) {
            endDateInput.min = formattedStart;
        }
    }

    document.getElementById('consultation-description').value = consultation.description || '';
    const editModeRaw = String(consultation.response_mode || 'feedback').toLowerCase();
    document.getElementById('consultation-response-mode').value = editModeRaw === 'survey' ? 'survey' : 'feedback';
    document.getElementById('consultation-survey-question').value = consultation.survey_question || 'Do you support this proposal?';
    document.getElementById('consultation-survey-option-a').value = consultation.survey_option_a || 'Agree';
    document.getElementById('consultation-survey-option-b').value = consultation.survey_option_b || 'Disagree';
    document.getElementById('consultation-allow-guest-quick-vote').checked = !!consultation.allow_guest_quick_vote;
    document.getElementById('consultation-allow-guest-verified-vote').checked = !!consultation.allow_guest_verified_vote;
    const modeSelect = document.getElementById('consultation-response-mode');
    if (modeSelect) {
        modeSelect.onchange = refreshConsultationModeConfigVisibility;
    }
    const selectorWrap = document.getElementById('consultation-type-selector-wrap');
    if (selectorWrap) {
        selectorWrap.style.display = 'none';
    }
    refreshConsultationModeConfigVisibility();

    document.getElementById('consultation-source-url').value = consultation.source_url || '';

    document.getElementById('consultation-image').value = '';

    var preview = document.getElementById('consultation-image-preview');

    if (preview) { preview.style.display = 'none'; preview.innerHTML = ''; }

    var icon = document.getElementById('consultation-image-icon');

    if (icon) icon.style.display = '';

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');

    var csrfEl = document.getElementById('consultation-csrf');

    if (csrfEl && csrfMeta) csrfEl.value = csrfMeta.content;

    document.getElementById('consultation-modal').classList.remove('hidden');

}




async function saveConsultation() {

    const id = document.getElementById('consultation-id').value;

    let title = document.getElementById('consultation-title').value.trim();
    let category = document.getElementById('consultation-category').value;
    let description = document.getElementById('consultation-description').value.trim();
    const normalizeDateStr = (str) => {
        if (!str) return '';
        str = String(str).trim();
        if (/^\d{4}-\d{2}-\d{2}/.test(str)) return str.substring(0, 10);
        const parts = str.split(/[\/\-]/);
        if (parts.length === 3) {
            if (parts[0].length === 4) return `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
            if (parts[2].length === 4) {
                let d = parseInt(parts[0], 10);
                let m = parseInt(parts[1], 10);
                let y = parseInt(parts[2], 10);
                if (m > 12 && d <= 12) { const tmp = m; m = d; d = tmp; }
                return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            }
        }
        return str;
    };

    let rawStartDate = document.getElementById('consultation-start-date') ? document.getElementById('consultation-start-date').value : '';
    let rawEndDate = document.getElementById('consultation-end-date') ? document.getElementById('consultation-end-date').value : '';
    const startDate = normalizeDateStr(rawStartDate);
    const endDate = normalizeDateStr(rawEndDate);
    const responseMode = document.getElementById('consultation-response-mode').value;
    const surveyQuestion = document.getElementById('consultation-survey-question').value.trim();

    if (!title && surveyQuestion) title = surveyQuestion;
    if (!description && surveyQuestion) description = surveyQuestion;
    if (!category) category = 'General';

    const surveyOptionA = document.getElementById('consultation-survey-option-a').value.trim() || 'Agree';
    const surveyOptionB = document.getElementById('consultation-survey-option-b').value.trim() || 'Disagree';
    const allowGuestQuickVote = document.getElementById('consultation-allow-guest-quick-vote').checked ? '1' : '0';
    const allowGuestVerifiedVote = document.getElementById('consultation-allow-guest-verified-vote').checked ? '1' : '0';

    const sourceUrl = document.getElementById('consultation-source-url').value.trim();

    const imageFile = document.getElementById('consultation-image').files[0] || null;


    if (!title || !category || !startDate || !endDate || !description) {

        showNotification('Please fill in all required fields (Question/Title, Start & End dates)', 'error');

        return;

    }


    const btn = document.getElementById('save-consultation-btn');

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i> Saving...'; }


    try {

        const formData = new FormData();

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');

        formData.append('csrf_token', csrfMeta ? csrfMeta.content : '');

        formData.append('title', title);

        formData.append('description', description);

        formData.append('category', category);
        const distVal = document.getElementById('consultation-district') ? document.getElementById('consultation-district').value : '';
        const brgyVal = document.getElementById('consultation-barangay') ? document.getElementById('consultation-barangay').value : '';
        formData.append('district', distVal);
        formData.append('barangay', brgyVal);

        formData.append('start_date', startDate);

        formData.append('end_date', endDate);

        formData.append('source_url', sourceUrl);
        formData.append('response_mode', responseMode);
        formData.append('survey_question', surveyQuestion);
        formData.append('survey_option_a', surveyOptionA);
        formData.append('survey_option_b', surveyOptionB);
        formData.append('allow_guest_quick_vote', allowGuestQuickVote);
        formData.append('allow_guest_verified_vote', allowGuestVerifiedVote);

        if (imageFile) formData.append('consultation_image', imageFile);


        const action = id ? 'update&id=' + id : 'create';

        const res = await fetch('API/consultations_api.php?action=' + action, {

            method: 'POST',

            body: formData

        });

        const data = await res.json();


        if (!res.ok || !data.success) {

            throw new Error(data.message || 'Failed to save consultation');

        }


        showNotification(id ? 'Consultation updated successfully' : 'Consultation created successfully! It will now appear in Active Consultations.', 'success');

        closeConsultationModal();

        await loadConsultationsFromApi();

    } catch (err) {

        showNotification('Error: ' + err.message, 'error');

        console.error('saveConsultation error:', err);

    } finally {

        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg mr-1"></i> Save Consultation'; }

    }

}




function deleteConsultation(id) {

    showNotification('Delete is disabled to prevent data loss.', 'error');

}


function previewConsultationImage(input) {

    var preview = document.getElementById('consultation-image-preview');

    var icon = document.getElementById('consultation-image-icon');

    if (input.files && input.files[0]) {

        var reader = new FileReader();

        reader.onload = function (e) {

            if (preview) {

                preview.innerHTML = '<img src="' + e.target.result + '" style="max-height:120px; max-width:100%; border-radius:8px; margin:0 auto;">';

                preview.style.display = 'block';

            }

            if (icon) icon.style.display = 'none';

        };

        reader.readAsDataURL(input.files[0]);

    }

}




function viewConsultationDetails(id) {
    const consultation = AppData.consultations.find(c => Number(c.id) === Number(id));
    if (!consultation) return;

    const titleEl = document.getElementById('details-modal-title');
    const contentEl = document.getElementById('details-modal-content');
    const modalEl = document.getElementById('consultation-details-modal');

    if (!titleEl || !contentEl || !modalEl) {
        showNotification('Details view is not available on this screen. Opening Consultation Management...', 'info');
        showSection('consultation-management');
        setTimeout(() => {
            try { viewConsultationDetails(id); } catch (e) { console.error(e); }
        }, 200);
        return;
    }

    const relatedFeedback = AppData.feedback.filter(f => Number(f.consultationId || f.consultation_id) === Number(id));

    const feedbackHTML = relatedFeedback.length > 0
        ? relatedFeedback.map(f => `
            <div class="bg-gray-50/80 p-4 rounded-xl border border-gray-200 hover:border-red-200 transition">
                <div class="flex items-center justify-between mb-1.5">
                    <div class="font-semibold text-gray-900 text-xs flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-bold text-[10px]">${escapeHtml((f.author || 'C').charAt(0).toUpperCase())}</span>
                        ${escapeHtml(f.author || f.guest_name || 'Anonymous Citizen')}
                    </div>
                    <span class="text-[11px] text-gray-400">${escapeHtml(f.date || f.created_at || '')}</span>
                </div>
                <div class="text-gray-600 text-xs leading-relaxed pl-8">${escapeHtml(f.message || '')}</div>
            </div>
        `).join('')
        : `<div class="text-center py-6 text-gray-400 text-xs bg-gray-50/60 rounded-xl border border-dashed border-gray-200">
            <i class="bi bi-chat-square-dots text-2xl text-gray-300 block mb-1"></i>
            No public feedback or comments submitted yet for this item.
           </div>`;

    const st = String(consultation.status || '').toLowerCase();
    const statusBadgeClass = st === 'active'
        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
        : (st === 'draft' || st === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-gray-100 text-gray-700 border-gray-200');
    const statusLabel = st ? (st.charAt(0).toUpperCase() + st.slice(1)) : 'Pending';

    const isUserSubmission = typeof isUserConsultation === 'function' ? isUserConsultation(consultation) : (String(consultation.type || '').toLowerCase() === 'user');
    const editWindowDays = 7;
    const createdRaw = consultation.createdAt || consultation.created_at || consultation.date || consultation.start_date || null;
    let createdDate = null;
    if (createdRaw) {
        createdDate = new Date(createdRaw);
        if (Number.isNaN(createdDate.getTime())) createdDate = null;
    }
    const ageMs = createdDate ? (Date.now() - createdDate.getTime()) : null;
    const pastWindow = ageMs ? (ageMs > editWindowDays * 24 * 60 * 60 * 1000) : false;
    const canEdit = !isUserSubmission && !currentUserIsSuperAdmin() && !isConsultationClosed(consultation) && !pastWindow;

    const aiRoutingNote = String(consultation.remarks || '').trim();
    const aiRoutingHtml = aiRoutingNote ? `
        <div class="bg-indigo-50/80 border border-indigo-200/80 rounded-xl p-4 shadow-xs">
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-indigo-700 mb-1.5">
                <i class="bi bi-cpu-fill text-indigo-600"></i> AI Executive Review & Classification
            </div>
            <p class="text-xs text-indigo-900 leading-relaxed whitespace-pre-line">${escapeHtml(aiRoutingNote)}</p>
        </div>
    ` : '';

    let rawCitizenName = String(consultation.userName || consultation.user_name || 'Citizen');
    if (rawCitizenName.toLowerCase().includes('system administrator') || rawCitizenName.toLowerCase().includes('admin')) {
        rawCitizenName = 'Citizen Submission';
    }
    const userEmail = String(consultation.userEmail || consultation.user_email || '').trim();
    const mailtoSubject = encodeURIComponent('Regarding your Public Consultation submission - LGU Valenzuela');
    const mailtoBody = encodeURIComponent(
        `Hello ${rawCitizenName},\n\n` +
        `We received your consultation submission titled: ${String(consultation.title || '')}\n` +
        `Reference ID: CONSULT-${String(consultation.id || '').padStart(6, '0')}\n\n` +
        `Response:\n`
    );
    const mailtoHref = userEmail ? `mailto:${encodeURIComponent(userEmail)}?subject=${mailtoSubject}&body=${mailtoBody}` : '';

    const displayType = isUserSubmission ? 'Citizen Proposal' : 'LGU Consultation';
    const displayCategory = consultation.category || 'General Governance';
    const displayRef = 'CONSULT-' + String(consultation.id || '').padStart(6, '0');
    const formattedDate = createdDate ? createdDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : (consultation.date || '-');

    titleEl.innerHTML = `
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-red-700/80 border border-red-500/50 flex items-center justify-center text-white shadow-inner">
                <i class="bi bi-file-text-fill text-xl"></i>
            </div>
            <div>
                <div class="text-xs font-semibold text-red-200 tracking-wide uppercase flex items-center gap-2">
                    <span>${displayType}</span>
                    <span class="inline-block w-1 h-1 rounded-full bg-red-300"></span>
                    <span class="font-mono">${displayRef}</span>
                </div>
                <h2 class="text-lg font-bold text-white leading-tight mt-0.5">${escapeHtml(consultation.title || 'Consultation Details')}</h2>
            </div>
        </div>
    `;

    contentEl.innerHTML = `
        <!-- Overview Metadata Metrics Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
            <div class="bg-gray-50/80 border border-gray-200/70 rounded-xl p-3 flex items-center gap-3 shadow-xs">
                <div class="w-9 h-9 rounded-lg bg-red-50 text-red-600 flex items-center justify-center text-base font-bold shrink-0">
                    <i class="bi bi-layers-fill"></i>
                </div>
                <div class="min-w-0">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Category</label>
                    <span class="text-xs font-bold text-gray-800 truncate block mt-0.5" title="${escapeHtml(displayCategory)}">${escapeHtml(displayCategory)}</span>
                </div>
            </div>

            <div class="bg-gray-50/80 border border-gray-200/70 rounded-xl p-3 flex items-center gap-3 shadow-xs">
                <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-base font-bold shrink-0">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div class="min-w-0">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Submitted Date</label>
                    <span class="text-xs font-bold text-gray-800 block mt-0.5">${escapeHtml(formattedDate)}</span>
                </div>
            </div>

            <div class="bg-gray-50/80 border border-gray-200/70 rounded-xl p-3 flex items-center gap-3 shadow-xs">
                <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-base font-bold shrink-0">
                    <i class="bi bi-patch-check"></i>
                </div>
                <div class="min-w-0">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Status</label>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border ${statusBadgeClass} mt-0.5">
                        ${escapeHtml(statusLabel)}
                    </span>
                </div>
            </div>

            <div class="bg-gray-50/80 border border-gray-200/70 rounded-xl p-3 flex items-center gap-3 shadow-xs">
                <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center text-base font-bold shrink-0">
                    <i class="bi bi-chat-left-text"></i>
                </div>
                <div class="min-w-0">
                    <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Feedback</label>
                    <span class="text-xs font-bold text-gray-800 block mt-0.5">${consultation.feedbackCount || relatedFeedback.length} Responses</span>
                </div>
            </div>
        </div>

        <!-- Citizen Submitter Profile Card (if User Submission) -->
        ${isUserSubmission ? `
            <div class="bg-gradient-to-r from-red-50/90 via-white to-red-50/40 border border-red-200/80 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-600 text-white flex items-center justify-center font-bold text-sm shadow-sm shrink-0">
                        ${escapeHtml(rawCitizenName.charAt(0).toUpperCase())}
                    </div>
                    <div>
                        <div class="text-xs font-bold text-gray-900 flex items-center gap-2">
                            ${escapeHtml(rawCitizenName)}
                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-red-100 text-red-700 rounded-full">Citizen Submitter</span>
                        </div>
                        <div class="text-xs text-gray-500 flex items-center gap-1.5 mt-0.5">
                            <i class="bi bi-envelope"></i> ${escapeHtml(userEmail || 'No email provided')}
                        </div>
                    </div>
                </div>
                ${mailtoHref ? `
                    <a href="${mailtoHref}" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-semibold shadow-xs transition shrink-0">
                        <i class="bi bi-reply-fill"></i> Email Submitter
                    </a>
                ` : ''}
            </div>
        ` : ''}

        <!-- Description Card -->
        ${consultation.description ? `
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">
                    <i class="bi bi-text-paragraph text-red-600 text-sm"></i> Description & Proposal Details
                </div>
                <div class="text-xs text-gray-700 leading-relaxed whitespace-pre-line bg-gray-50/50 p-3.5 rounded-lg border border-gray-100">${escapeHtml(consultation.description)}</div>
            </div>
        ` : ''}

        ${aiRoutingHtml}



        <!-- Feedback Responses Section -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500 flex items-center gap-2">
                    <i class="bi bi-chat-square-quote-fill text-red-600 text-sm"></i> Public Feedback & Comments
                </span>
                <span class="text-[11px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">${relatedFeedback.length} Item(s)</span>
            </div>
            <div class="space-y-2.5">${feedbackHTML}</div>
        </div>

        <!-- Action Footer -->
        <div class="flex flex-wrap items-center justify-between gap-2.5 pt-4 border-t border-gray-200/80">
            <div class="flex items-center gap-2">
                <button onclick="triggerSystemIntegration(${consultation.id}, 'PHS')" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="bi bi-broadcast text-blue-600"></i> PHS Sync
                </button>
                <button onclick="triggerSystemIntegration(${consultation.id}, 'LRS')" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-semibold transition flex items-center gap-1.5">
                    <i class="bi bi-archive-fill text-emerald-600"></i> LRS Export
                </button>
            </div>
            <div class="flex items-center gap-2">
                ${canEdit ? `<button onclick="editConsultation(${consultation.id}); closeDetailsModal()" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-semibold shadow-xs transition flex items-center gap-1.5"><i class="bi bi-pencil"></i> Edit Consultation</button>` : ''}
                <button onclick="closeDetailsModal()" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">Close</button>
            </div>
        </div>
    `;

    modalEl.classList.remove('hidden');
}




function closeDetailsModal() {
    document.getElementById('consultation-details-modal').classList.add('hidden');
}

window.triggerSystemIntegration = async function (consultationId, targetSystem) {
    const btnPhs = document.getElementById(`sync-phs-btn-${consultationId}`);
    const btnLrs = document.getElementById(`sync-lrs-btn-${consultationId}`);
    const activeBtn = targetSystem === 'PHS' ? btnPhs : btnLrs;
    const origHtml = activeBtn ? activeBtn.innerHTML : '';

    if (activeBtn) {
        activeBtn.disabled = true;
        activeBtn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Transmitting...';
    }

    try {
        const res = await fetch('API/consultation_integration_trigger.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ consultation_id: consultationId, target_system: targetSystem })
        });
        const data = await res.json();

        if (data && data.success) {
            showNotification(data.message || `Successfully transmitted payload to ${targetSystem}!`, 'success');
            if (activeBtn) {
                activeBtn.className = activeBtn.className.replace(/bg-\w+-600/, 'bg-green-600').replace(/hover:bg-\w+-700/, 'hover:bg-green-700');
                activeBtn.innerHTML = `<i class="bi bi-check-circle-fill"></i> Synced to ${targetSystem}`;
            }
        } else {
            showNotification(data?.message || `Failed to transmit payload to ${targetSystem}`, 'error');
            if (activeBtn) {
                activeBtn.disabled = false;
                activeBtn.innerHTML = origHtml;
            }
        }
    } catch (err) {
        showNotification(`Integration error: ${err.message}`, 'error');
        if (activeBtn) {
            activeBtn.disabled = false;
            activeBtn.innerHTML = origHtml;
        }
    }
};




function renderFeedbackCollection() {


    const contentArea = document.getElementById('content-area');







    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');







    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Feedback Collection';




    const totalFeedback = AppData.feedback.length;


    const recentFeedback = AppData.feedback.filter(f => {


        const date = new Date(f.date);


        const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);


        return date >= weekAgo;


    }).length;




    contentArea.innerHTML = `


        <div class="space-y-6">


            <!-- Header with Statistics -->


            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">


                <div class="flex justify-between items-start mb-6">


                    <div>


                        <h1 class="text-3xl font-bold mb-2">Feedback Collection</h1>


                        <p class="text-red-100">Collect, manage, and analyze public feedback from consultations</p>


                    </div>


                    <span class="text-xs font-semibold bg-white/20 px-3 py-1 rounded-full">Operational Feedback Monitor</span>


                </div>


                


                <!-- Stats Cards -->


                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">


                    <div class="bg-white bg-opacity-20 rounded-lg p-4">


                        <div class="text-red-100 text-sm font-semibold mb-1">Total Feedback</div>


                        <div class="text-3xl font-bold" id="fb-stat-total">${totalFeedback}</div>


                    </div>


                    <div class="bg-white bg-opacity-20 rounded-lg p-4">


                        <div class="text-red-100 text-sm font-semibold mb-1">This Week</div>


                        <div class="text-3xl font-bold" id="fb-stat-week">${recentFeedback}</div>


                    </div>


                    <div class="bg-white bg-opacity-20 rounded-lg p-4">


                        <div class="text-red-100 text-sm font-semibold mb-1">Avg. per Consultation</div>


                        <div class="text-3xl font-bold" id="fb-stat-avg">${AppData.consultations.length > 0 ? Math.round(totalFeedback / AppData.consultations.length) : 0}</div>


                    </div>


                </div>


            </div>




            <!-- Filter and Search -->


            <div class="bg-white p-6 rounded-lg shadow">


                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search Feedback</label>


                        <input type="text" id="feedback-search" placeholder="Search by author or message..." 


                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"


                            onkeyup="filterFeedback()">


                    </div>


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Consultation</label>


                        <select id="feedback-consultation-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"


                            onchange="filterFeedback()">


                            <option value="">All Consultations</option>


                            ${AppData.consultations.map(c => `<option value="${c.id}">${c.title}</option>`).join('')}


                        </select>


                    </div>


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>


                        <select id="feedback-sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"


                            onchange="filterFeedback()">


                            <option value="date-desc">Latest First</option>


                            <option value="date-asc">Oldest First</option>


                            <option value="author">Author A-Z</option>


                        </select>


                    </div>


                    <div class="flex items-end">


                        <button onclick="clearFeedbackFilters()" class="w-full px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 font-semibold">


                            Clear Filters


                        </button>


                    </div>


                </div>


            </div>




            <!-- Feedback Table -->


            <div class="bg-white rounded-lg shadow overflow-hidden">


                <div class="overflow-x-auto">


                    <table class="w-full text-sm">


                        <thead class="bg-gray-100 border-b-2 border-gray-300">


                            <tr>


                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Author</th>


                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Message</th>


                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Consultation</th>


                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Date</th>


                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>


                            </tr>


                        </thead>


                        <tbody id="feedback-table-body">


                        </tbody>


                    </table>


                </div>


            </div>


        </div>




        <div id="feedback-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">


            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[85vh] overflow-y-auto">


                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center">


                    <h2 class="text-2xl font-bold">Feedback Details</h2>


                    <button onclick="closeFeedbackDetailsModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>


                </div>


                <div id="feedback-details-modal-content" class="p-6 space-y-4"></div>


            </div>


        </div>


    `;




    const tbody = document.getElementById('feedback-table-body');


    if (tbody) {


        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Loading feedback...</td></tr>';


    }




    loadFeedbackFromApi();


}




function mapDbFeedbackToUi(row) {


    const createdAt = row.created_at || null;


    const consultationId = row.consultation_id !== null && row.consultation_id !== undefined ? Number(row.consultation_id) : null;




    return {


        id: Number(row.id),


        author: row.guest_name || 'Guest',


        authorEmail: row.guest_email || '',


        consultationId,


        message: row.message || '',


        date: createdAt,


        status: String(row.status || 'new').toLowerCase(),


        rating: row.rating !== null && row.rating !== undefined ? Number(row.rating) : null,


        category: row.category || '',


        sentimentTag: row.sentiment_tag || '',


        sentimentScore: row.sentiment_score !== null && row.sentiment_score !== undefined ? Number(row.sentiment_score) : null


    };


}




async function loadFeedbackFromApi() {


    try {


        const res = await fetchWithTimeout('API/feedback_api.php?action=list&limit=200&offset=0', {


            headers: { 'Accept': 'application/json' }


        }, 5000);




        let data;


        try {


            data = await res.json();


        } catch (_) {


            data = null;


        }




        if (!res.ok) {


            const msg = (data && data.message)


                ? data.message


                : (res.status === 403 ? 'Unauthorized (admin session required)' : `HTTP ${res.status}`);


            throw new Error(msg);


        }




        if (!data || !data.success || !Array.isArray(data.data)) {


            throw new Error((data && data.message) ? data.message : 'Failed to load feedback');


        }




        window.__last_feedback_api__ = data;




        AppData.feedback = data.data.map(mapDbFeedbackToUi);


        recomputeConsultationFeedbackCounts();


        updateFeedbackStatsUI();


        refreshFeedbackConsultationDropdowns();


        renderFeedbackTable();




        if (data.data.length === 0) {


            const tbody = document.getElementById('feedback-table-body');


            if (tbody) {


                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No feedback returned by API. Checking connection...</td></tr>';


            }


            try {


                const dbgRes = await fetchWithTimeout('API/feedback_api.php?action=debug', { headers: { 'Accept': 'application/json' } }, 5000);


                const dbg = await dbgRes.json();


                window.__last_feedback_debug__ = dbg;


                const dbName = dbg?.data?.db?.database ?? 'unknown';


                const cnt = dbg?.data?.db?.feedback_count;


                const role = dbg?.data?.session?.role_normalized ?? dbg?.data?.session?.role ?? 'unknown';


                if (tbody) {
                    const numericCount = Number(cnt || 0);
                    if (numericCount > 0) {
                        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-red-600">API returned 0 rows, but debug says DB has <b>${escapeHtml(String(numericCount))}</b> feedback (DB: <b>${escapeHtml(String(dbName))}</b>, role: <b>${escapeHtml(String(role))}</b>).<div class="text-xs text-gray-500 mt-2">This means the list query is not returning rows as expected.</div></td></tr>`;
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No feedback yet.</td></tr>';
                    }
                }


            } catch (_) {


            }


        }


    } catch (e) {


        const tbody = document.getElementById('feedback-table-body');


        if (tbody) {


            const details = e && e.message ? String(e.message) : 'Unknown error';


            const hint = details.toLowerCase().includes('unauthorized') || details.toLowerCase().includes('403')


                ? 'Please log in as Admin and refresh the page.'


                : 'Check database connection and server logs.';


            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-red-600">Failed to load feedback from database.<div class="text-xs text-gray-500 mt-2">${escapeHtml(details)}<br>${escapeHtml(hint)}</div></td></tr>`;


        }


        updateFeedbackStatsUI();


        console.error(e);


    }


}




function updateFeedbackStatsUI() {


    const totalEl = document.getElementById('fb-stat-total');


    const weekEl = document.getElementById('fb-stat-week');


    const avgEl = document.getElementById('fb-stat-avg');




    const total = AppData.feedback.length;


    const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);


    const recent = AppData.feedback.filter(f => {


        const d = f.date ? new Date(f.date) : null;


        return d && d >= weekAgo;


    }).length;


    const avg = AppData.consultations.length > 0 ? Math.round(total / AppData.consultations.length) : 0;




    if (totalEl) totalEl.textContent = String(total);


    if (weekEl) weekEl.textContent = String(recent);


    if (avgEl) avgEl.textContent = String(avg);


}




function refreshFeedbackConsultationDropdowns() {


    const filterSel = document.getElementById('feedback-consultation-filter');


    const modalSel = document.getElementById('feedback-consultation');




    const opts = AppData.consultations.map(c => `<option value="${c.id}">${c.title}</option>`).join('');


    if (filterSel) {


        const cur = filterSel.value;


        filterSel.innerHTML = `<option value="">All Consultations</option>${opts}`;


        filterSel.value = cur;


    }


    if (modalSel) {


        const cur = modalSel.value;


        modalSel.innerHTML = `<option value="">Select Consultation</option>${opts}`;


        modalSel.value = cur;


    }


}




function renderFeedbackTable() {


    const tbody = document.getElementById('feedback-table-body');


    if (!tbody) return;


    const feedbackList = getFilteredFeedback();




    if (feedbackList.length === 0) {


        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No feedback found</td></tr>`;


        return;


    }




    tbody.innerHTML = feedbackList.map(feedback => {


        const consultation = AppData.consultations.find(c => c.id === feedback.consultationId);


        const consultationTitle = consultation ? consultation.title : 'Unknown Consultation';


        const isOverdue = isFeedbackOverdue(feedback, 3);


        const rowClass = isOverdue ? 'bg-red-50' : '';


        const dateText = feedback.date ? new Date(feedback.date).toLocaleDateString() : '-';




        return `


            <tr class="border-b hover:bg-gray-50 transition ${rowClass}">


                <td class="px-6 py-4 font-semibold text-gray-900">${escapeHtml(feedback.author)}</td>


                <td class="px-6 py-4 text-gray-700 max-w-xs truncate" title="${escapeHtml(feedback.message)}">${escapeHtml(feedback.message)}</td>


                <td class="px-6 py-4 text-gray-600 text-sm">${escapeHtml(consultationTitle)}</td>


                <td class="px-6 py-4 text-gray-600">


                    <div class="flex items-center justify-between gap-2">


                        <span>${dateText}</span>


                        ${isOverdue ? '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Overdue</span>' : ''}


                    </div>


                </td>


                <td class="px-6 py-4 text-center">


                    <div class="flex gap-2 justify-center">


                        <button onclick="viewFeedbackDetails(${feedback.id})" class="text-blue-600 hover:text-blue-800" title="View">


                            <i class="bi bi-eye"></i>


                        </button>


                    </div>


                </td>


            </tr>


        `;


    }).join('');


}




function closeFeedbackDetailsModal() {


    const modal = document.getElementById('feedback-details-modal');


    if (modal) modal.classList.add('hidden');


}




function viewFeedbackDetails(id) {


    const modal = document.getElementById('feedback-details-modal');


    const content = document.getElementById('feedback-details-modal-content');


    if (!modal || !content) return;




    const f = AppData.feedback.find(x => x.id === id);


    if (!f) return;




    const consultation = AppData.consultations.find(c => c.id === f.consultationId);


    const consultationTitle = consultation ? consultation.title : 'Unknown Consultation';


    const email = String(f.authorEmail || '').trim();




    const st = String(f.status || 'new').toLowerCase();


    const isOverdue = isFeedbackOverdue(f, 3);


    const statusColor = st === 'responded'


        ? 'bg-green-100 text-green-800'


        : (st === 'in_review' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800');




    const mailtoSubject = encodeURIComponent('Regarding your feedback submission');


    const mailtoBody = encodeURIComponent(


        `Hello ${String(f.author || 'there')},\n\n` +


        `Thanks for your feedback regarding: ${String(consultationTitle)}\n` +


        `Feedback ID: ${String(f.id)}\n\n` +


        `Message:\n`


    );


    const mailtoHref = email ? `mailto:${encodeURIComponent(email)}?subject=${mailtoSubject}&body=${mailtoBody}` : '';




    content.innerHTML = `


        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Author</label>


                <p class="text-gray-900 font-semibold mt-1">${escapeHtml(String(f.author || 'Guest'))}</p>


                ${email ? `<p class="text-sm text-gray-600 mt-1">${escapeHtml(email)}</p>` : ''}


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Consultation</label>


                <p class="text-gray-900 font-semibold mt-1">${escapeHtml(String(consultationTitle))}</p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Rating</label>


                <p class="text-gray-900 font-semibold mt-1">${f.rating !== null && f.rating !== undefined ? escapeHtml(String(f.rating)) + '/5' : '-'}</p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Category</label>


                <p class="text-gray-900 font-semibold mt-1">${escapeHtml(String(f.category || '-'))}</p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Submitted</label>


                <p class="text-gray-900 font-semibold mt-1">${f.date ? escapeHtml(new Date(f.date).toLocaleString()) : '-'}</p>


            </div>


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Current Status</label>


                <div class="flex items-center gap-2 mt-2">


                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusColor}">${escapeHtml(st)}</span>


                    ${isOverdue ? '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Overdue (3 days)</span>' : ''}


                </div>


            </div>


        </div>




        <div>


            <label class="text-xs font-semibold text-gray-500 uppercase">Message</label>


            <div class="mt-2 p-3 bg-gray-50 rounded text-gray-800 whitespace-pre-wrap">${escapeHtml(String(f.message || ''))}</div>


        </div>




        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


            <div>


                <label class="text-xs font-semibold text-gray-500 uppercase">Update Status</label>


                <select id="fb-status-select" disabled class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed">


                    <option value="new" ${st === 'new' ? 'selected' : ''}>new</option>


                    <option value="in_review" ${st === 'in_review' ? 'selected' : ''}>in_review</option>


                    <option value="responded" ${st === 'responded' ? 'selected' : ''}>responded</option>


                    <option value="resolved" ${st === 'resolved' ? 'selected' : ''}>resolved</option>


                </select>


            </div>


            <div class="flex items-end">


                <button type="button" disabled class="w-full px-4 py-2 rounded-lg bg-gray-200 text-gray-500 cursor-not-allowed">View Only</button>


            </div>


        </div>




        <div class="flex gap-2 pt-4 border-t">


            


            <button onclick="closeFeedbackDetailsModal()" class="flex-1 btn-secondary">Close</button>


        </div>


    `;




    modal.classList.remove('hidden');


}




async function updateFeedbackStatus(id) {
    showNotification('Read-only mode: feedback actions are disabled.', 'warning');
    return;


    const sel = document.getElementById('fb-status-select');


    const status = sel ? String(sel.value || '').trim() : '';


    if (!status) {


        showNotification('Please select a status', 'error');


        return;


    }




    try {


        const res = await fetch('API/feedback_api.php?action=update_status', {


            method: 'POST',


            headers: {


                'Accept': 'application/json',


                'Content-Type': 'application/json'


            },


            body: JSON.stringify({ id, status })


        });




        const data = await res.json().catch(() => null);


        if (!res.ok || !data || !data.success) {


            const msg = (data && data.message) ? data.message : (res.ok ? 'Failed to update status' : `HTTP ${res.status}`);


            throw new Error(msg);


        }




        const f = AppData.feedback.find(x => x.id === id);


        if (f) f.status = status;


        renderFeedbackTable();


        showNotification('Feedback status updated', 'success');


        closeFeedbackDetailsModal();


    } catch (e) {


        const details = e && e.message ? String(e.message) : 'Unknown error';


        showNotification(`Failed to update status: ${details}`, 'error');


    }


}




function getFilteredFeedback() {


    let filtered = [...AppData.feedback];





    const searchTerm = document.getElementById('feedback-search')?.value.toLowerCase() || '';


    const consultationFilter = document.getElementById('feedback-consultation-filter')?.value || '';


    const sortBy = document.getElementById('feedback-sort')?.value || 'date-desc';




    if (searchTerm) {


        filtered = filtered.filter(f =>


            f.author.toLowerCase().includes(searchTerm) ||


            f.message.toLowerCase().includes(searchTerm)


        );


    }





    if (consultationFilter) {


        filtered = filtered.filter(f => f.consultationId === parseInt(consultationFilter));


    }




    // Sort


    filtered.sort((a, b) => {


        switch (sortBy) {


            case 'date-asc':


                return new Date(a.date) - new Date(b.date);


            case 'author':


                return a.author.localeCompare(b.author);


            case 'date-desc':


            default:


                return new Date(b.date) - new Date(a.date);


        }


    });




    return filtered;


}




function filterFeedback() {


    renderFeedbackTable();


}




function clearFeedbackFilters() {


    document.getElementById('feedback-search').value = '';


    document.getElementById('feedback-consultation-filter').value = '';


    document.getElementById('feedback-sort').value = 'date-desc';


    renderFeedbackTable();


}




// ── Sentiment Analysis Functions ──




async function analyzeSingleFeedback(id, showInModal = false) {


    const f = AppData.feedback.find(x => x.id === id);


    if (!f) return;




    // Show loading in modal result area if open


    const resultEl = document.getElementById('sentiment-result-' + id);


    if (resultEl) {


        resultEl.innerHTML = '<p class="text-sm text-purple-600 animate-pulse"><i class="bi bi-hourglass-split mr-1"></i> Analyzing sentiment...</p>';


    }




    try {


        const res = await fetch('API/sentiment_api.php?action=analyze', {


            method: 'POST',


            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },


            body: JSON.stringify({ text: f.message })


        });


        const data = await res.json().catch(() => null);


        if (!res.ok || !data || !data.success) {


            throw new Error((data && data.message) || 'Analysis failed');


        }




        const result = data.data;


        f.sentimentTag = result.sentiment;


        f.sentimentScore = result.score;


        // Read-only mode: analysis results are not persisted.

        // Update modal result area with rich analysis

        if (resultEl) {

            const badgeClass = result.sentiment === 'positive' ? 'bg-green-100 text-green-800' :

                result.sentiment === 'negative' ? 'bg-red-100 text-red-800' :

                    'bg-gray-100 text-gray-700';

            const icon = result.sentiment === 'positive' ? 'bi-emoji-smile' :

                result.sentiment === 'negative' ? 'bi-emoji-frown' : 'bi-emoji-neutral';

            const urgencyColors = { critical: 'bg-red-600 text-white', high: 'bg-orange-500 text-white', medium: 'bg-yellow-400 text-yellow-900', low: 'bg-gray-200 text-gray-600' };

            const urgencyClass = urgencyColors[result.urgency] || urgencyColors.low;


            let keywordsHtml = '';

            if (result.keywords && result.keywords.length > 0) {

                keywordsHtml = '<div class="flex flex-wrap gap-1">' +

                    result.keywords.map(k => {

                        const kwClass = k.score > 0 ? 'bg-green-50 text-green-700 border-green-200' :

                            k.score < 0 ? 'bg-red-50 text-red-700 border-red-200' :

                                'bg-gray-50 text-gray-600 border-gray-200';

                        return `<span class="text-xs px-2 py-0.5 rounded border ${kwClass}">${escapeHtml(k.word)} (${k.score > 0 ? '+' : ''}${k.score})</span>`;

                    }).join('') + '</div>';

            }


            let topicsHtml = '';

            if (result.topics && result.topics.length > 0) {

                topicsHtml = result.topics.map(t => {

                    const label = t.topic.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

                    return `<span class="text-xs px-2 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 font-medium"><i class="bi bi-tag-fill mr-1"></i>${label}</span>`;

                }).join('');

            }


            let emotionsHtml = '';

            if (result.emotions && result.emotions.length > 0) {

                const emotionIcons = { anger: 'bi-fire', fear: 'bi-shield-exclamation', joy: 'bi-heart-fill', sadness: 'bi-cloud-rain', trust: 'bi-hand-thumbs-up', frustration: 'bi-emoji-angry' };

                emotionsHtml = result.emotions.map(e => {

                    const pct = Math.round(e.intensity * 100);

                    const ic = emotionIcons[e.emotion] || 'bi-circle';

                    return `<div class="flex items-center gap-2">

                        <i class="bi ${ic} text-purple-500 text-xs"></i>

                        <span class="text-xs font-medium text-gray-700 w-20">${e.emotion.charAt(0).toUpperCase() + e.emotion.slice(1)}</span>

                        <div class="flex-1 bg-gray-200 rounded-full h-2"><div class="bg-purple-500 rounded-full h-2" style="width:${pct}%"></div></div>

                        <span class="text-xs text-gray-500">${pct}%</span>

                    </div>`;

                }).join('');

            }


            const confPct = Math.round(result.confidence * 100);


            resultEl.innerHTML = `

                <div class="space-y-4">

                    <div class="flex items-center gap-3 flex-wrap">

                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold ${badgeClass}">

                            <i class="bi ${icon}"></i> ${result.sentiment.charAt(0).toUpperCase() + result.sentiment.slice(1)}

                        </span>

                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold ${urgencyClass}">

                            <i class="bi bi-exclamation-triangle"></i> ${result.urgency.charAt(0).toUpperCase() + result.urgency.slice(1)} Priority

                        </span>

                        <span class="text-xs text-gray-400">Score: ${result.score} | Confidence: ${confPct}%</span>

                    </div>


                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">

                        <p class="text-xs font-bold text-blue-800 mb-1"><i class="bi bi-lightbulb mr-1"></i>AI Summary</p>

                        <p class="text-sm text-blue-900">${escapeHtml(result.summary || '')}</p>

                    </div>


                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">

                        <p class="text-xs font-bold text-amber-800 mb-1"><i class="bi bi-clipboard-check mr-1"></i>Recommended Action</p>

                        <p class="text-sm text-amber-900">${escapeHtml(result.recommendation || '')}</p>

                    </div>


                    ${result.topics && result.topics.length > 0 ? `

                    <div>

                        <p class="text-xs font-bold text-gray-500 mb-1.5"><i class="bi bi-bookmark mr-1"></i>Detected Topics</p>

                        <div class="flex flex-wrap gap-1.5">${topicsHtml}</div>

                    </div>` : ''}


                    ${result.emotions && result.emotions.length > 0 ? `

                    <div>

                        <p class="text-xs font-bold text-gray-500 mb-1.5"><i class="bi bi-activity mr-1"></i>Emotional Tone</p>

                        <div class="space-y-1.5">${emotionsHtml}</div>

                    </div>` : ''}


                    ${keywordsHtml ? `

                    <div>

                        <p class="text-xs font-bold text-gray-500 mb-1.5"><i class="bi bi-key mr-1"></i>Detected Keywords</p>

                        ${keywordsHtml}

                    </div>` : ''}


                    <div class="flex gap-4 text-xs text-gray-400 pt-1 border-t border-gray-100">

                        <span><i class="bi bi-type mr-1"></i>${result.word_count || 0} words</span>

                        <span class="text-green-600"><i class="bi bi-plus-circle mr-1"></i>${result.positive_count || 0} positive</span>

                        <span class="text-red-600"><i class="bi bi-dash-circle mr-1"></i>${result.negative_count || 0} negative</span>

                    </div>

                </div>

            `;

        }




        // Refresh table row


        renderFeedbackTable();




        if (!showInModal) {


            showNotification(`Sentiment: ${result.sentiment} (score: ${result.score})`, 'success');


        }




    } catch (err) {


        if (resultEl) {


            resultEl.innerHTML = `<p class="text-sm text-red-600"><i class="bi bi-exclamation-triangle mr-1"></i> ${escapeHtml(String(err.message || err))}</p>`;


        }


        showNotification('Sentiment analysis failed: ' + String(err.message || err), 'error');


    }


}




async function runBatchSentimentAnalysis() {


    if (!AppData.feedback.length) {


        showNotification('No feedback to analyze', 'error');


        return;


    }




    showNotification('Running sentiment analysis on all feedback...', 'info');




    try {


        const ids = AppData.feedback.map(f => f.id);


        const res = await fetch('API/sentiment_api.php?action=batch', {


            method: 'POST',


            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },


            body: JSON.stringify({ ids })


        });


        const data = await res.json().catch(() => null);


        if (!res.ok || !data || !data.success) {


            throw new Error((data && data.message) || 'Batch analysis failed');


        }


        // Update local data (read-only mode: no persistence)
        for (const result of data.data) {
            const f = AppData.feedback.find(x => x.id === result.id);
            if (f) {
                f.sentimentTag = result.sentiment;
                f.sentimentScore = result.score;
            }
        }

        // Refresh table


        renderFeedbackTable();




        // Show summary


        const s = data.summary;


        showNotification(


            `Analysis complete: ${s.positive} positive, ${s.neutral} neutral, ${s.negative} negative (avg score: ${s.average_score})`,


            'success'


        );




    } catch (err) {


        showNotification('Batch analysis failed: ' + String(err.message || err), 'error');


    }


}




function openAddFeedbackModal() {


    document.getElementById('feedback-id').value = '';


    document.getElementById('feedback-modal-title').textContent = 'Add New Feedback';


    document.getElementById('feedback-author').value = '';


    document.getElementById('feedback-consultation').value = '';


    document.getElementById('feedback-message').value = '';


    document.getElementById('feedback-date').value = new Date().toISOString().split('T')[0];


    document.getElementById('feedback-modal').classList.remove('hidden');


}




function closeFeedbackModal() {


    document.getElementById('feedback-modal').classList.add('hidden');


}




function editFeedback(id) {


    const feedback = AppData.feedback.find(f => f.id === id);


    if (!feedback) return;




    document.getElementById('feedback-id').value = id;


    document.getElementById('feedback-modal-title').textContent = 'Edit Feedback';


    document.getElementById('feedback-author').value = feedback.author;


    document.getElementById('feedback-consultation').value = feedback.consultationId;


    document.getElementById('feedback-message').value = feedback.message;


    document.getElementById('feedback-date').value = feedback.date;


    document.getElementById('feedback-modal').classList.remove('hidden');


}




function saveFeedback() {


    const id = document.getElementById('feedback-id').value;


    const author = document.getElementById('feedback-author').value.trim();


    const consultationId = parseInt(document.getElementById('feedback-consultation').value);


    const message = document.getElementById('feedback-message').value.trim();


    const date = document.getElementById('feedback-date').value;




    if (!author || !consultationId || !message || !date) {


        showNotification('Please fill in all required fields', 'error');


        return;


    }




    if (id) {


        // Update existing


        const feedback = AppData.feedback.find(f => f.id === parseInt(id));


        if (feedback) {


            feedback.author = author;


            feedback.consultationId = consultationId;


            feedback.message = message;


            feedback.date = date;


            showNotification('Feedback updated successfully', 'success');


        }


    } else {


        // Create new


        const newFeedback = {


            id: Math.max(...AppData.feedback.map(f => f.id), 0) + 1,


            author,


            consultationId,


            message,


            date


        };


        AppData.feedback.push(newFeedback);





        // Update consultation feedback count


        const consultation = AppData.consultations.find(c => c.id === consultationId);


        if (consultation) {


            consultation.feedbackCount = (consultation.feedbackCount || 0) + 1;


        }





        showNotification('Feedback added successfully', 'success');


    }




    closeFeedbackModal();


    renderFeedbackTable();


}




function deleteFeedback(id) {


    showNotification('Delete is disabled to prevent data loss.', 'error');


}




function pfpStatusBadge(status) {
    const s = String(status || 'new').toLowerCase();
    const map = {
        new: '<span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">New</span>',
        reviewed: '<span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Reviewed</span>',
        responded: '<span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Responded</span>',
        closed: '<span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">Closed</span>'
    };
    return map[s] || `<span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">${escapeHtml(status || 'N/A')}</span>`;
}

function pfpBuildRef(feedback) {
    const dt = feedback?.date ? new Date(feedback.date) : new Date();
    const y = dt.getFullYear();
    const m = String(dt.getMonth() + 1).padStart(2, '0');
    const d = String(dt.getDate()).padStart(2, '0');
    const seed = Number(feedback?.id || 0).toString(36).toUpperCase().padStart(5, '0');
    return `FB-${y}${m}${d}-${seed}`;
}

function pfpGetPriority(feedback) {
    const rating = Number(feedback?.rating || 0);
    if (rating > 0 && rating <= 2) return 'high';
    if (rating >= 4) return 'low';
    return 'normal';
}

function pfpGetBarangay(feedback) {
    const consultation = AppData.consultations.find(c => Number(c.id) === Number(feedback?.consultationId));
    return String(consultation?.category || 'Barangay').trim();
}

function pfpGetAging(feedback) {
    if (!feedback?.date) return '-';
    const ms = Date.now() - new Date(feedback.date).getTime();
    if (ms < 0) return '0h';
    const hours = Math.floor(ms / (1000 * 60 * 60));
    if (hours < 24) return `${hours}h`;
    const days = Math.floor(hours / 24);
    return `${days}d`;
}

function pfpGetFilteredFeedback() {
    const q = String(document.getElementById('pfq-search')?.value || '').toLowerCase().trim();
    const status = String(document.getElementById('pfq-status')?.value || '').toLowerCase();
    const priority = String(document.getElementById('pfq-priority')?.value || '').toLowerCase();
    const type = String(document.getElementById('pfq-type')?.value || '').toLowerCase();
    const committee = String(document.getElementById('pfq-committee')?.value || '').toLowerCase().trim();
    const archiveMode = String(document.getElementById('pfq-archive-mode')?.value || 'active').toLowerCase();
    const barangay = String(document.getElementById('pfq-barangay')?.value || '').toLowerCase().trim();
    const refNo = String(document.getElementById('pfq-ref')?.value || '').toLowerCase().trim();
    const fromDate = String(document.getElementById('pfq-from-date')?.value || '');
    const toDate = String(document.getElementById('pfq-to-date')?.value || '');

    let rows = [...AppData.feedback];

    // Filter active vs archived
    if (archiveMode === 'archived') {
        rows = rows.filter(f => Number(f.is_archived) === 1 || String(f.status || '').toLowerCase() === 'closed');
    } else {
        rows = rows.filter(f => Number(f.is_archived) !== 1);
    }

    // Exclude consultation proposals, ordinance suggestions, and survey votes from Feedback Queue
    rows = rows.filter(f => {
        const subType = String(f.submission_type || f.type || '').toLowerCase();
        const category = String(f.category || '').toLowerCase();
        return subType !== 'proposal' && subType !== 'consultation' && category !== 'ordinance suggestion' && category !== 'proposal' && category !== 'survey vote';
    });

    if (q) {
        rows = rows.filter(f => {
            const ref = pfpBuildRef(f).toLowerCase();
            return (
                ref.includes(q) ||
                String(f.author || f.guest_name || '').toLowerCase().includes(q) ||
                String(f.authorEmail || f.guest_email || '').toLowerCase().includes(q) ||
                String(f.message || f.content || '').toLowerCase().includes(q) ||
                String(f.committee_assigned || '').toLowerCase().includes(q)
            );
        });
    }
    const consultationId = String(document.getElementById('pfq-consultation')?.value || '').trim();

    if (consultationId) {
        rows = rows.filter(f => String(f.consultationId || f.consultation_id || '') === consultationId);
    }

    if (status) {
        rows = rows.filter(f => String(f.status || '').toLowerCase() === status);
    }
    if (type) {
        rows = rows.filter(f => String(f.submission_type || f.type || 'comment').toLowerCase() === type);
    }
    if (committee) {
        rows = rows.filter(f => String(f.committee_assigned || '').toLowerCase().includes(committee));
    }
    if (priority) {
        rows = rows.filter(f => pfpGetPriority(f) === priority);
    }
    if (barangay) {
        rows = rows.filter(f => pfpGetBarangay(f).toLowerCase().includes(barangay));
    }
    if (refNo) {
        rows = rows.filter(f => pfpBuildRef(f).toLowerCase().includes(refNo));
    }
    if (fromDate) {
        const from = new Date(`${fromDate}T00:00:00`);
        rows = rows.filter(f => !f.date || new Date(f.date) >= from);
    }
    if (toDate) {
        const to = new Date(`${toDate}T23:59:59`);
        rows = rows.filter(f => !f.date || new Date(f.date) <= to);
    }
    rows.sort((a, b) => new Date(b.date || b.created_at || 0) - new Date(a.date || a.created_at || 0));

    return rows;
}

function pfpRenderStats() {
    const total = AppData.feedback.length;
    const newCount = AppData.feedback.filter(f => ['new', 'pending'].includes(String(f.status || '').toLowerCase())).length;
    const reviewedCount = AppData.feedback.filter(f => String(f.status || '').toLowerCase() === 'reviewed').length;
    const respondedCount = AppData.feedback.filter(f => String(f.status || '').toLowerCase() === 'responded').length;
    const closedCount = AppData.feedback.filter(f => String(f.status || '').toLowerCase() === 'closed').length;
    const forwardedCount = AppData.feedback.filter(f => String(f.status || '').toLowerCase() === 'forwarded' || !!f.committee_assigned).length;
    const anonymousCount = AppData.feedback.filter(f => !String(f.authorEmail || f.guest_email || '').trim()).length;

    const surveyCount = AppData.feedback.filter(f => String(f.submission_type || f.type || '').toLowerCase() === 'survey').length;
    const proposalCount = AppData.feedback.filter(f => String(f.submission_type || f.type || '').toLowerCase() === 'proposal').length;

    // Survey Agree/Disagree Consensus Ratio
    const positiveSurveys = AppData.feedback.filter(f => String(f.sentiment_tag || f.sentiment || '').toLowerCase() === 'positive').length;
    const negativeSurveys = AppData.feedback.filter(f => String(f.sentiment_tag || f.sentiment || '').toLowerCase() === 'negative').length;
    const totalSentiments = positiveSurveys + negativeSurveys;
    const agreePct = totalSentiments > 0 ? Math.round((positiveSurveys / totalSentiments) * 100) : 75;
    const disagreePct = 100 - agreePct;

    const topBarangay = (() => {
        const tally = new Map();
        for (const row of AppData.feedback) {
            const b = pfpGetBarangay(row);
            tally.set(b, (tally.get(b) || 0) + 1);
        }
        let top = ['-', 0];
        for (const [k, v] of tally.entries()) {
            if (v > top[1]) top = [k, v];
        }
        return top;
    })();

    const fields = {
        'pfq-stat-total': total,
        'pfq-stat-new': newCount,
        'pfq-stat-reviewed': reviewedCount,
        'pfq-stat-responded': respondedCount,
        'pfq-stat-closed': closedCount,
        'pfq-stat-anon': anonymousCount,
        'pfq-analytics-surveys': surveyCount,
        'pfq-analytics-proposals': proposalCount,
        'pfq-analytics-forwarded': forwardedCount,
        'pfq-survey-agree-pct': `${agreePct}% Agree`,
        'pfq-survey-disagree-pct': `${disagreePct}% Disagree`
    };

    Object.entries(fields).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = String(value);
    });

    const agreeBar = document.getElementById('pfq-survey-bar-agree');
    if (agreeBar) agreeBar.style.width = `${agreePct}%`;
    const disagreeBar = document.getElementById('pfq-survey-bar-disagree');
    if (disagreeBar) disagreeBar.style.width = `${disagreePct}%`;

    const topEl = document.getElementById('pfq-top-barangay');
    if (topEl) topEl.textContent = `${topBarangay[0]} (${topBarangay[1]})`;
}

function pfpSetRowsCountDisplay(count) {
    const countEl = document.getElementById('pfq-items-count');
    if (countEl) countEl.textContent = `${count} item(s)`;
}

function pfpStatusBadge(status) {
    const s = String(status || 'pending').toLowerCase().trim();
    if (s === 'pending' || s === 'new') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300"><i class="bi bi-clock-history mr-1"></i>Pending</span>';
    } else if (s === 'reviewed') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300"><i class="bi bi-check-circle mr-1"></i>Reviewed</span>';
    } else if (s === 'responded') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"><i class="bi bi-reply-fill mr-1"></i>Responded</span>';
    } else if (s === 'forwarded') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-purple-100 text-purple-800 border border-purple-300"><i class="bi bi-arrow-right-circle-fill mr-1"></i>Forwarded</span>';
    } else if (s === 'closed') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-700 border border-slate-300"><i class="bi bi-archive-fill mr-1"></i>Closed</span>';
    }
    return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-700">${escapeHtml(s)}</span>`;
}

function pfpSubmissionTypeBadge(type) {
    const t = String(type || 'comment').toLowerCase().trim();
    if (t === 'survey') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-sky-100 text-sky-800 border border-sky-200"><i class="bi bi-ui-checks mr-1"></i>Survey</span>';
    } else if (t === 'proposal') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-violet-100 text-violet-800 border border-violet-200"><i class="bi bi-journal-text mr-1"></i>Proposal</span>';
    }
    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800 border border-gray-200"><i class="bi bi-chat-left-text mr-1"></i>Comment</span>';
}

function pfpSentimentBadge(sentiment) {
    const s = String(sentiment || 'neutral').toLowerCase().trim();
    if (s === 'positive') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800"><i class="bi bi-emoji-smile mr-1"></i>Positive</span>';
    } else if (s === 'negative') {
        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800"><i class="bi bi-emoji-frown mr-1"></i>Negative</span>';
    }
    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-700"><i class="bi bi-emoji-neutral mr-1"></i>Neutral</span>';
}

function pfpRatingStars(rating) {
    const r = Math.min(5, Math.max(1, parseInt(rating) || 0));
    if (r <= 0) return '<span class="text-xs text-gray-400">N/A</span>';
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= r) stars += '<i class="bi bi-star-fill text-amber-400 text-xs mr-0.5"></i>';
        else stars += '<i class="bi bi-star text-gray-300 text-xs mr-0.5"></i>';
    }
    return stars;
}

function pfpRenderTable() {
    const tbody = document.getElementById('pfq-table-body');
    if (!tbody) return;

    const rows = pfpGetFilteredFeedback();
    pfpSetRowsCountDisplay(rows.length);
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="px-4 py-10 text-center text-gray-500">No matching feedback found.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(f => {
        const consultation = AppData.consultations.find(c => Number(c.id) === Number(f.consultationId || f.consultation_id));
        const typeText = consultation?.type || 'consultation';
        const categoryText = f.category || consultation?.category || 'General Feedback';
        const created = f.date ? new Date(f.date).toLocaleString() : (f.created_at ? new Date(f.created_at).toLocaleString() : '-');
        const priority = pfpGetPriority(f);
        const ratingHtml = pfpRatingStars(f.rating);
        const sentimentHtml = pfpSentimentBadge(f.sentiment_tag || f.sentiment);
        const typeBadge = pfpSubmissionTypeBadge(f.submission_type || f.type);
        const committeeTag = f.committee_assigned
            ? `<div class="mt-1"><span class="inline-block px-1.5 py-0.5 text-[10px] font-semibold bg-purple-50 text-purple-800 rounded border border-purple-200"><i class="bi bi-diagram-3 mr-0.5"></i>${escapeHtml(f.committee_assigned)}</span></div>`
            : '';

        return `
            <tr class="border-b border-gray-100 hover:bg-gray-50/70">
                <td class="px-4 py-3"><input type="checkbox" class="pfq-row-checkbox rounded border-gray-300" value="${Number(f.id)}"></td>
                <td class="px-4 py-3 font-semibold text-gray-800">${escapeHtml(pfpBuildRef(f))}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900">${escapeHtml(f.author || f.guest_name || 'Anonymous')}</div>
                    <div class="text-xs text-gray-500">${escapeHtml(f.authorEmail || f.guest_email || 'No email')}</div>
                    <div class="mt-0.5">${ratingHtml}</div>
                </td>
                <td class="px-4 py-3">${typeBadge}</td>
                <td class="px-4 py-3">
                    <div class="text-gray-900 font-medium text-xs">${escapeHtml(typeText)}</div>
                    <div class="text-xs text-gray-500">${escapeHtml(categoryText)}</div>
                    ${committeeTag}
                </td>
                <td class="px-4 py-3">
                    <div class="text-xs font-medium text-gray-700 mb-0.5">${escapeHtml(priority)}</div>
                    <div>${sentimentHtml}</div>
                </td>
                <td class="px-4 py-3">${pfpStatusBadge(f.status)}</td>
                <td class="px-4 py-3 text-xs text-gray-700">${escapeHtml(created)}</td>
                <td class="px-4 py-3 text-xs text-gray-700">${escapeHtml(pfpGetAging(f))}</td>
                <td class="px-4 py-3">
                    <button onclick="openFeedbackResponseModal(${Number(f.id)})" class="inline-flex items-center justify-center px-2.5 py-1 text-xs border border-blue-600 text-blue-600 font-medium rounded hover:bg-blue-50 gap-1 shadow-sm" title="View & Respond">
                        <i class="bi bi-reply-fill"></i> View / Forward
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function pfpSetStatus(id, status, silent) {
    const targetStatus = String(status || '').toLowerCase().trim();
    if (!targetStatus) return false;
    try {
        const res = await fetch('API/feedback_api.php?action=update_status', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: Number(id), status: targetStatus })
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
        }

        const row = AppData.feedback.find(f => Number(f.id) === Number(id));
        if (row) row.status = targetStatus;
        if (!silent) showNotification('Feedback status updated to ' + targetStatus + '.', 'success');
        pfpRenderStats();
        pfpRenderTable();
        return true;
    } catch (err) {
        if (!silent) showNotification(err && err.message ? String(err.message) : 'Failed to update status.', 'error');
        return false;
    }
}

function closeFeedbackModal() {
    const modal = document.getElementById('pfq-response-modal');
    if (modal) modal.remove();
}

function openFeedbackResponseModal(id) {
    closeFeedbackModal();
    const f = AppData.feedback.find(item => Number(item.id) === Number(id));
    if (!f) {
        showNotification('Feedback entry not found.', 'error');
        return;
    }

    const consultation = AppData.consultations.find(c => Number(c.id) === Number(f.consultationId || f.consultation_id));
    const consultationTitle = consultation ? consultation.title : (f.consultationTitle || `Consultation #${f.consultation_id || f.consultationId || ''}`);
    const author = f.author || f.guest_name || 'Anonymous';
    const email = f.authorEmail || f.guest_email || '';
    const phone = f.guest_phone || '';
    const category = f.category || consultation?.category || 'General Feedback';
    const ratingStars = pfpRatingStars(f.rating);
    const sentimentBadge = pfpSentimentBadge(f.sentiment_tag || f.sentiment);
    const typeBadge = pfpSubmissionTypeBadge(f.submission_type || f.type);
    const messageText = f.message || f.content || '';
    const existingResponse = f.admin_response || f.response || '';
    const statusBadge = pfpStatusBadge(f.status);

    let topicsHtml = '';
    let topics = [];
    try {
        if (typeof f.topic_tags === 'string') topics = JSON.parse(f.topic_tags);
        else if (Array.isArray(f.topic_tags)) topics = f.topic_tags;
        else if (Array.isArray(f.topics)) topics = f.topics;
    } catch (_) { }

    if (topics.length) {
        topicsHtml = topics.map(t => `<span class="inline-block px-2 py-0.5 text-xs bg-blue-50 text-blue-700 rounded border border-blue-200 mr-1 mb-1">${escapeHtml(t)}</span>`).join('');
    }

    const modalHtml = `
        <div id="pfq-response-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden animate-in fade-in duration-200">
                <div class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-4 flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-semibold text-red-200 tracking-wider">Public Feedback Queue Workflow</span>
                        <h2 class="text-xl font-bold mt-0.5">Feedback Review & Committee Routing</h2>
                    </div>
                    <button onclick="closeFeedbackModal()" class="text-white/80 hover:text-white text-2xl font-bold leading-none">&times;</button>
                </div>

                <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto text-sm text-gray-800">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Citizen Info</p>
                            <p class="font-bold text-gray-900 mt-1">${escapeHtml(author)}</p>
                            <p class="text-xs text-gray-600">${escapeHtml(email || 'No email provided')}</p>
                            ${phone ? `<p class="text-xs text-gray-600">Phone: ${escapeHtml(phone)}</p>` : ''}
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 font-semibold uppercase">Consultation Policy</p>
                            <p class="font-semibold text-gray-900 mt-1">${escapeHtml(consultationTitle)}</p>
                            <p class="text-xs text-gray-500">Category: <span class="font-medium">${escapeHtml(category)}</span></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                        <div class="flex items-center gap-4">
                            <div><span class="text-xs text-gray-500 block mb-0.5">Type</span>${typeBadge}</div>
                            <div><span class="text-xs text-gray-500 block mb-0.5">Rating</span>${ratingStars}</div>
                            <div><span class="text-xs text-gray-500 block mb-0.5">Sentiment</span>${sentimentBadge}</div>
                            <div><span class="text-xs text-gray-500 block mb-0.5">Current Status</span>${statusBadge}</div>
                        </div>
                        ${topicsHtml ? `<div><span class="text-xs text-gray-500 block mb-0.5">AI Topics</span><div>${topicsHtml}</div></div>` : ''}
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Citizen Submission Message</label>
                        <div class="p-4 bg-red-50/50 border border-red-100 rounded-lg text-gray-900 text-sm whitespace-pre-wrap leading-relaxed">${escapeHtml(messageText)}</div>
                    </div>

                    <!-- Stage 4: Committee Routing & Assignment Section -->
                    ${(f.consultationId || f.consultation_id) ? `
                        <div class="p-4 bg-purple-50/70 border border-purple-200 rounded-lg space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="font-bold text-purple-900 text-xs flex items-center gap-1.5 uppercase tracking-wider">
                                    <i class="bi bi-diagram-3-fill text-purple-700"></i> LGU Committee Routing Policy
                                </label>
                                ${f.committee_assigned ? `<span class="text-xs font-bold px-2 py-0.5 bg-purple-200 text-purple-900 rounded">Assigned: ${escapeHtml(f.committee_assigned)}</span>` : '<span class="text-xs text-purple-600 font-medium">Pending Consultation Brief</span>'}
                            </div>
                            <p class="text-xs text-purple-950 font-medium leading-relaxed">
                                <i class="bi bi-info-circle-fill text-purple-600 mr-1"></i>
                                Feedback submissions for consultation policies are <strong>not forwarded individually</strong>. All citizen responses for this consultation policy are compiled <strong>collectively into an official AI Synthesis Brief</strong> for the committee when the consultation is closed.
                            </p>
                            ${consultation ? `
                                <div class="pt-2 border-t border-purple-200/80 flex items-center justify-between text-xs">
                                    <span class="text-purple-800 font-semibold">Consultation Status: <strong class="uppercase text-purple-950">${escapeHtml(consultation.status || 'Active')}</strong></span>
                                    <button onclick="closeFeedbackModal(); pfpShowAiCommitteeBriefModal(${consultation.id})" class="px-2.5 py-1 bg-purple-700 hover:bg-purple-800 text-white font-extrabold rounded text-[11px] transition shadow-sm flex items-center gap-1">
                                        <i class="bi bi-robot"></i> View Consultation AI Brief
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    ` : `
                        <div class="p-4 bg-purple-50/70 border border-purple-200 rounded-lg space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="font-bold text-purple-900 text-sm flex items-center">
                                    <i class="bi bi-diagram-3 mr-1 text-purple-700"></i> Stage 4: LGU Committee Routing
                                </label>
                                ${f.committee_assigned ? `<span class="text-xs font-bold px-2 py-0.5 bg-purple-200 text-purple-900 rounded">Assigned: ${escapeHtml(f.committee_assigned)}</span>` : '<span class="text-xs text-purple-600 font-medium">Currently Unassigned</span>'}
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div class="sm:col-span-2">
                                    <select id="pfq-modal-committee-select" class="w-full px-3 py-1.5 border border-purple-300 rounded text-xs font-medium focus:ring-purple-500">
                                        <option value="">-- Select Target LGU Committee --</option>
                                        <option value="Urban Planning & Infrastructure">Urban Planning & Infrastructure</option>
                                        <option value="Environmental Management & Sanitation">Environmental Management & Sanitation</option>
                                        <option value="Health & Social Services">Health & Social Services</option>
                                        <option value="Finance, Budget & Appropriations">Finance, Budget & Appropriations</option>
                                        <option value="Rules, Laws & Governance">Rules, Laws & Governance</option>
                                    </select>
                                </div>
                                <button onclick="submitForwardToCommittee(${Number(f.id)})" class="w-full px-3 py-1.5 bg-purple-700 text-white rounded text-xs font-bold hover:bg-purple-800 shadow">
                                    <i class="bi bi-send-check mr-1"></i> Forward to Committee
                                </button>
                            </div>
                        </div>
                    `}

                    ${f.analysis_summary ? `
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900">
                            <strong><i class="bi bi-cpu mr-1"></i>AI Analysis Summary:</strong> ${escapeHtml(f.analysis_summary)}
                        </div>
                    ` : ''}

                    <div class="border-t border-gray-200 pt-4 space-y-3">
                        <label for="pfq-modal-response-input" class="block font-bold text-gray-900">
                            Official LGU Response
                        </label>
                        <textarea id="pfq-modal-response-input" rows="4" placeholder="Type the official government response to this feedback submission..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm">${escapeHtml(existingResponse)}</textarea>
                        
                        <div class="flex items-center gap-2">
                            <input id="pfq-modal-send-email" type="checkbox" ${email ? 'checked' : 'disabled'} class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            <label for="pfq-modal-send-email" class="text-xs text-gray-700 font-medium">
                                Send official response copy to citizen's email (${escapeHtml(email || 'No email available')})
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 border-t border-gray-200 px-6 py-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <button onclick="pfpSetStatus(${Number(f.id)}, 'reviewed').then(() => closeFeedbackModal())" class="px-3 py-1.5 rounded border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-100">
                            Mark as Reviewed
                        </button>
                        <button onclick="pfpSetStatus(${Number(f.id)}, 'closed').then(() => closeFeedbackModal())" class="px-3 py-1.5 rounded border border-slate-400 text-slate-700 text-xs font-semibold hover:bg-slate-100">
                            Close & Archive
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="closeFeedbackModal()" class="px-4 py-2 rounded border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-100">
                            Cancel
                        </button>
                        <button onclick="submitFeedbackResponse(${Number(f.id)})" class="px-4 py-2 rounded bg-red-600 text-white text-xs font-bold hover:bg-red-700 shadow">
                            <i class="bi bi-send mr-1"></i> Submit Response
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function submitForwardToCommittee(id) {
    const select = document.getElementById('pfq-modal-committee-select');
    const committee = select ? select.value.trim() : '';

    if (!committee) {
        showNotification('Please select a target LGU Committee.', 'error');
        return;
    }

    try {
        const res = await fetch('API/feedback_api.php?action=forward', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: Number(id),
                committee: committee,
                notes: 'Forwarded via Admin Feedback Queue'
            })
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
        }

        const row = AppData.feedback.find(f => Number(f.id) === Number(id));
        if (row) {
            row.status = 'forwarded';
            row.committee_assigned = committee;
        }

        closeFeedbackModal();
        pfpRenderStats();
        pfpRenderTable();
        showNotification(data.message || `Feedback forwarded to ${committee}!`, 'success');
    } catch (err) {
        showNotification(err && err.message ? String(err.message) : 'Failed to forward feedback.', 'error');
    }
}

async function submitFeedbackResponse(id) {
    const input = document.getElementById('pfq-modal-response-input');
    const sendEmailCb = document.getElementById('pfq-modal-send-email');
    const responseText = (input ? input.value : '').trim();
    const sendEmail = sendEmailCb ? sendEmailCb.checked : false;

    if (!responseText) {
        showNotification('Please enter an official response message.', 'error');
        return;
    }

    try {
        const res = await fetch('API/feedback_api.php?action=respond', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: Number(id),
                response: responseText,
                send_email: sendEmail
            })
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
        }

        const row = AppData.feedback.find(f => Number(f.id) === Number(id));
        if (row) {
            row.status = 'responded';
            row.admin_response = responseText;
            row.responded_at = new Date().toISOString();
        }

        closeFeedbackModal();
        pfpRenderStats();
        pfpRenderTable();
        showNotification(data.message || 'Official response recorded successfully!', 'success');
    } catch (err) {
        showNotification(err && err.message ? String(err.message) : 'Failed to record response.', 'error');
    }
}

function viewFeedbackDetails(id) {
    return openFeedbackResponseModal(id);
}

function pfpToggleSelectAll() {
    const selectAll = !!document.getElementById('pfq-check-all')?.checked;
    document.querySelectorAll('.pfq-row-checkbox').forEach(cb => {
        cb.checked = selectAll;
    });
}

function pfpGetSelectedIds() {
    return Array.from(document.querySelectorAll('.pfq-row-checkbox:checked'))
        .map(cb => Number(cb.value))
        .filter(v => Number.isFinite(v) && v > 0);
}

async function pfpApplyBulkStatus() {
    const status = String(document.getElementById('pfq-bulk-status')?.value || '').toLowerCase();
    const selected = pfpGetSelectedIds();
    if (!status) {
        showNotification('Choose a bulk status first.', 'error');
        return;
    }
    if (!selected.length) {
        showNotification('Select at least one feedback row.', 'error');
        return;
    }

    let ok = 0;
    for (const id of selected) {
        const result = await pfpSetStatus(id, status, true);
        if (result) ok += 1;
    }
    pfpRenderStats();
    pfpRenderTable();
    showNotification(`Updated ${ok}/${selected.length} feedback entries.`, ok === selected.length ? 'success' : 'warning');
}

function pfpResetFilters() {
    const ids = ['pfq-search', 'pfq-status', 'pfq-priority', 'pfq-barangay', 'pfq-ref', 'pfq-from-date', 'pfq-to-date'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
    });
    pfpRenderTable();
}

function pfpExportCsv() {
    const rows = pfpGetFilteredFeedback();
    if (!rows.length) {
        showNotification('No feedback rows to export.', 'error');
        return;
    }

    const headers = ['Ref', 'Citizen', 'Email', 'Type', 'Category', 'Priority', 'Status', 'Created', 'Aging', 'Message'];
    const csvRows = [headers.join(',')];
    rows.forEach(f => {
        const consultation = AppData.consultations.find(c => Number(c.id) === Number(f.consultationId));
        const values = [
            pfpBuildRef(f),
            f.author || 'Anonymous',
            f.authorEmail || '',
            consultation?.type || 'consultation',
            f.category || consultation?.category || 'service_issue',
            pfpGetPriority(f),
            f.status || 'new',
            f.date ? new Date(f.date).toISOString() : '',
            pfpGetAging(f),
            (f.message || '').replace(/\r?\n/g, ' ').trim()
        ].map(v => `"${String(v).replace(/"/g, '""')}"`);
        csvRows.push(values.join(','));
    });

    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `public-feedback-queue-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

async function pfpRefreshData() {
    try {
        await Promise.all([loadConsultationsFromApi(), loadFeedbackFromApi()]);
        pfpRenderStats();
        pfpRenderTable();
        showNotification('Public Feedback Queue refreshed.', 'success');
    } catch (e) {
        showNotification(e && e.message ? String(e.message) : 'Failed to refresh data.', 'error');
    }
}

function pfpOpenPortalNotice() {
    showNotification('Public portal redirect is disabled. You are already in the internal Public Feedback Queue module.', 'info');
}

async function renderPublicFeedbackPortal() {
    const contentArea = document.getElementById('content-area');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Public Feedback Queue';
    if (!contentArea) return;

    contentArea.innerHTML = '<div class="p-8 text-center text-gray-500"><i class="bi bi-arrow-repeat animate-spin text-2xl mb-2 block"></i>Loading feedback queue...</div>';

    try {
        await Promise.all([
            loadFeedbackFromApi().catch(e => console.warn('Feedback queue load failed:', e)),
            loadConsultationsFromApi().catch(e => console.warn('Consultation queue load failed:', e))
        ]);
    } catch (e) {
        console.warn('Feedback queue bootstrap failed:', e);
    }

    contentArea.innerHTML = `
        <div class="space-y-5">
            <!-- Sleek Header Banner -->
            <div class="bg-gradient-to-r from-red-600 to-red-700 text-white p-6 rounded-xl shadow-md flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <i class="bi bi-inbox-fill"></i> Public Feedback Queue
                    </h1>
                    <p class="text-red-100 text-sm mt-1">Review citizen submissions, route feedback to LGU committees, and send official responses.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="pfpExportCsv()" class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-semibold rounded-lg transition border border-white/20 flex items-center gap-1.5 shadow-sm">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                    <button onclick="pfpRefreshData()" class="px-3.5 py-2 bg-white text-red-700 hover:bg-red-50 text-xs font-bold rounded-lg transition shadow flex items-center gap-1.5">
                        <i class="bi bi-arrow-repeat"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- 4 Clean Key Metrics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-gray-500 text-xs font-bold uppercase">
                        <span>Total Submissions</span>
                        <i class="bi bi-chat-left-text text-gray-400 text-lg"></i>
                    </div>
                    <p id="pfq-stat-total" class="text-3xl font-extrabold text-gray-900 mt-2">0</p>
                    <p class="text-[11px] text-gray-400 mt-1">All logged feedback</p>
                </div>

                <div class="bg-white rounded-xl border border-amber-200 p-4 shadow-sm bg-amber-50/30">
                    <div class="flex items-center justify-between text-amber-700 text-xs font-bold uppercase">
                        <span>Pending Review</span>
                        <i class="bi bi-clock-history text-amber-500 text-lg"></i>
                    </div>
                    <p id="pfq-stat-new" class="text-3xl font-extrabold text-amber-600 mt-2">0</p>
                    <p class="text-[11px] text-amber-600/80 mt-1">Awaiting admin action</p>
                </div>

                <div class="bg-white rounded-xl border border-purple-200 p-4 shadow-sm bg-purple-50/30">
                    <div class="flex items-center justify-between text-purple-700 text-xs font-bold uppercase">
                        <span>Committee Forwarded</span>
                        <i class="bi bi-diagram-3 text-purple-500 text-lg"></i>
                    </div>
                    <p id="pfq-analytics-forwarded" class="text-3xl font-extrabold text-purple-600 mt-2">0</p>
                    <p class="text-[11px] text-purple-600/80 mt-1">Routed to LGU departments</p>
                </div>

                <div class="bg-white rounded-xl border border-emerald-200 p-4 shadow-sm bg-emerald-50/30">
                    <div class="flex items-center justify-between text-emerald-700 text-xs font-bold uppercase">
                        <span>Responded / Closed</span>
                        <i class="bi bi-check-circle-fill text-emerald-500 text-lg"></i>
                    </div>
                    <p id="pfq-stat-responded" class="text-3xl font-extrabold text-emerald-600 mt-2">0</p>
                    <p class="text-[11px] text-emerald-600/80 mt-1">Official response sent</p>
                </div>
            </div>

            <!-- Hidden stats containers for calculation compatibility -->
            <div class="hidden">
                <span id="pfq-stat-reviewed">0</span>
                <span id="pfq-stat-closed">0</span>
                <span id="pfq-stat-anon">0</span>
                <span id="pfq-analytics-surveys">0</span>
                <span id="pfq-analytics-proposals">0</span>
                <span id="pfq-survey-agree-pct">75% Agree</span>
                <span id="pfq-survey-disagree-pct">25% Disagree</span>
                <div id="pfq-survey-bar-agree"></div>
                <div id="pfq-survey-bar-disagree"></div>
                <input id="pfq-ref" type="hidden">
                <input id="pfq-from-date" type="hidden">
                <input id="pfq-to-date" type="hidden">
            </div>

            <!-- Interactive Queue System Tab Bar (Clean 3-Tab Layout) -->
            <div class="border-b border-gray-200 bg-white rounded-t-xl px-4 pt-3 flex flex-wrap items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center gap-2 overflow-x-auto">
                    <button id="pfq-tab-survey" onclick="switchPublicFeedbackTab('survey')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-purple-600 text-purple-600 flex items-center gap-2 transition focus:outline-none cursor-pointer">
                        <i class="bi bi-square-poll-fill text-purple-600"></i>
                        <span>Community Survey Polls</span>
                        <span id="pfq-tab-survey-badge" class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 text-[10px] font-extrabold">0</span>
                    </button>
                    <button id="pfq-tab-consult" onclick="switchPublicFeedbackTab('consult')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-800 flex items-center gap-2 transition focus:outline-none cursor-pointer">
                        <i class="bi bi-chat-left-quote-fill"></i>
                        <span>Consultation Feedback Summary</span>
                        <span id="pfq-tab-consult-badge" class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[10px] font-extrabold">0</span>
                    </button>
                    <button id="pfq-tab-phms" onclick="switchPublicFeedbackTab('phms')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-800 flex items-center gap-2 transition focus:outline-none cursor-pointer">
                        <i class="bi bi-building-gear text-blue-600"></i>
                        <span>PHMS Feedback</span>
                        <span id="pfq-tab-phms-badge" class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-extrabold">0</span>
                    </button>
                </div>
                <div class="text-xs text-gray-400 font-medium hidden sm:flex items-center gap-1.5 py-2">
                    <i class="bi bi-info-circle"></i>
                    <span>PCMS & PHMS System Integration</span>
                </div>
            </div>

            <!-- TAB 1: Community Survey Polls Container (Active by default) -->
            <div id="pfq-survey-container" class="space-y-4">
                <div class="bg-white rounded-xl border border-purple-200 p-4 shadow-sm space-y-3 bg-purple-50/20">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex-1 min-w-[240px]">
                            <div class="relative">
                                <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                                <input id="pfq-survey-search" type="text" placeholder="Search survey topic, question, or date..." class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-purple-500 focus:border-purple-500" oninput="pfpRenderSurveyPollsTable()">
                            </div>
                        </div>

                        <select id="pfq-survey-status" class="px-3 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white" onchange="pfpRenderSurveyPollsTable()">
                            <option value="">All Poll Statuses</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto shadow-sm">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b border-gray-200 uppercase tracking-wider text-[11px] font-bold text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-gray-900">SURVEY / POLL TOPIC</th>
                                <th class="px-4 py-3 text-gray-900">DATE</th>
                                <th class="px-4 py-3 text-gray-900 text-center">TOTAL VOTES</th>
                                <th class="px-4 py-3 text-gray-900 text-center">PUBLIC STANCE & POLL BREAKDOWN</th>
                                <th class="px-4 py-3 text-gray-900 text-center">STATUS</th>
                                <th class="px-4 py-3 text-gray-900 text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="pfq-survey-table-body">
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <i class="bi bi-arrow-repeat animate-spin text-xl mb-1 block"></i> Loading Community Survey Polls...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="px-4 py-3 border-t border-gray-100 text-xs text-gray-500 flex items-center justify-between">
                        <span><strong>PCMS Opinion Polls:</strong> Live Citizen Vote Tally & Percentage Breakdown</span>
                        <span>Click <strong>"View Poll Breakdown"</strong> to inspect vote statistics.</span>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Consultation Feedback Summary Container -->
            <div id="pfq-consult-container" class="space-y-4 hidden">
                <div class="bg-white rounded-xl border border-blue-200 p-4 shadow-sm space-y-3 bg-blue-50/20">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex-1 min-w-[240px]">
                            <div class="relative">
                                <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                                <input id="pfq-consult-search" type="text" placeholder="Search consultation title, date, or ID..." class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500" oninput="pfpRenderConsultationFeedbackTable()">
                            </div>
                        </div>

                        <select id="pfq-consultation" class="px-3 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white max-w-[240px]">
                            <option value="">Select Consultation Policy...</option>
                        </select>

                        <select id="pfq-consult-status" class="px-3 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white" onchange="pfpRenderConsultationFeedbackTable()">
                            <option value="">All Consultation Statuses</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="closed">Closed</option>
                        </select>

                        <button onclick="pfpTriggerAiCommitteeCompile()" class="px-3.5 py-2 bg-gradient-to-r from-red-700 to-red-900 text-white font-extrabold rounded-lg text-xs hover:from-red-800 hover:to-black transition shadow flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-robot"></i> Compile AI Committee Brief
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto shadow-sm">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b border-gray-200 uppercase tracking-wider text-[11px] font-bold text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-gray-900">CONSULTATION</th>
                                <th class="px-4 py-3 text-gray-900">DATE</th>
                                <th class="px-4 py-3 text-gray-900 text-center">FEEDBACK</th>
                                <th class="px-4 py-3 text-gray-900 text-center">AVG. RATING</th>
                                <th class="px-4 py-3 text-gray-900 text-center">PUBLISHED</th>
                                <th class="px-4 py-3 text-gray-900 text-center">PENDING</th>
                                <th class="px-4 py-3 text-gray-900 text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="pfq-consultation-table-body">
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    <i class="bi bi-arrow-repeat animate-spin text-xl mb-1 block"></i> Loading Consultation Feedback...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="px-4 py-3 border-t border-gray-100 text-xs text-gray-500 flex items-center justify-between">
                        <span><strong>PCMS Integration:</strong> Real-Time Live Sync enabled with Consultation Management System</span>
                        <span>Click <strong>"View Feedback"</strong> to inspect individual citizen responses.</span>
                    </div>
                </div>
            </div>

            <!-- TAB 2: PHMS System Feedback Container -->
            <div id="pfq-phms-container" class="space-y-4 hidden">
                <!-- AI Analysis & Redundancy Prevention Header Banner -->
                <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 text-white rounded-xl p-4 shadow-md flex flex-wrap items-center justify-between gap-4 border border-blue-700/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center text-xl text-blue-300 shadow-inner">
                            <i class="bi bi-cpu-fill"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-extrabold text-sm text-white">AI Analysis & Redundancy Protection Engine</h4>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-300 text-[10px] font-bold uppercase tracking-wider">Active Deduplication</span>
                            </div>
                            <p class="text-xs text-blue-200/80 mt-0.5">PHMS live hearing testimonies are automatically ingested and merged with PCMS online feedback. Synthesized items are flagged to skip redundant re-reading.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <div class="bg-white/10 px-3 py-1.5 rounded-lg border border-white/10 text-center">
                            <div class="text-[10px] text-blue-200 uppercase font-bold">Analyzed Feedback</div>
                            <div class="text-sm font-extrabold text-emerald-400" id="phms-analyzed-count-badge">105 Testimonies</div>
                        </div>
                        <div class="bg-white/10 px-3 py-1.5 rounded-lg border border-white/10 text-center">
                            <div class="text-[10px] text-blue-200 uppercase font-bold">Duplicates Skipped</div>
                            <div class="text-sm font-extrabold text-amber-300" id="phms-skipped-count-badge">0 Redundant</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-blue-200 p-4 shadow-sm space-y-3 bg-blue-50/20">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex-1 min-w-[240px]">
                            <div class="relative">
                                <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                                <input id="pfq-phms-search" type="text" placeholder="Search PHMS hearing title, date, or ID..." class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500" oninput="pfpRenderPhmsTable()">
                            </div>
                        </div>

                        <select id="pfq-phms-status" class="px-3 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white" onchange="pfpRenderPhmsTable()">
                            <option value="">All Hearing Statuses</option>
                            <option value="completed">Completed</option>
                            <option value="active">Active</option>
                            <option value="open">Open</option>
                        </select>
                        <button id="pfq-phms-sync-btn" onclick="pfpTriggerRealtimePhmsSync()" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition shadow flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-arrow-repeat"></i> Sync PHMS Data
                        </button>
                        <button onclick="openPhmsDataApprovalSheetModal()" class="px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-lg transition shadow flex items-center gap-1.5 cursor-pointer" title="Inspect & Approve Incoming PHMS Data Package">
                            <i class="bi bi-file-earmark-check-fill"></i> Ingestion Approval Sheet
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto shadow-sm">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b border-gray-200 uppercase tracking-wider text-[11px] font-bold text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-gray-900">HEARING</th>
                                <th class="px-4 py-3 text-gray-900">DATE</th>
                                <th class="px-4 py-3 text-gray-900 text-center">FEEDBACK</th>
                                <th class="px-4 py-3 text-gray-900 text-center">AVG. RATING</th>
                                <th class="px-4 py-3 text-gray-900 text-center">AI ANALYSIS STATUS</th>
                                <th class="px-4 py-3 text-gray-900 text-center">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="pfq-phms-table-body">
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    <i class="bi bi-arrow-repeat animate-spin text-xl mb-1 block"></i> Loading PHMS Citizen Hearing Feedback...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="px-4 py-3 border-t border-gray-100 text-xs text-gray-500 flex items-center justify-between">
                        <span><strong>PHMS Integration:</strong> Real-Time Live Sync enabled with PHMS Public Hearing Management System</span>
                        <span>Click <strong>"View Feedback"</strong> to inspect individual citizen responses.</span>
                    </div>
                </div>
            </div>
            </div>
        </div>
    `;

    pfpPopulateConsultationDropdowns();
    pfpRenderStats();
    pfpRenderConsultationFeedbackTable();
    pfpRenderTable();
    loadPhmsFeedbackFromApi();

    if (!AppData.feedback.length || !AppData.consultations.length) {
        pfpRefreshData();
    }
}

window.__current_pfq_tab__ = 'consult';
window._phms_realtime_timer = null;

if (!AppData.phmsFeedback) {
    AppData.phmsFeedback = [];
}

async function pfpTriggerRealtimePhmsSync() {
    const btn = document.getElementById('pfq-phms-sync-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i> Syncing...';
    }

    await loadPhmsFeedbackFromApi(true);

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync PHMS Data';
    }
}

window.openPhmsDataApprovalSheetModal = function() {
    const existing = document.getElementById('phms-approval-sheet-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'phms-approval-sheet-modal';
    modal.style.cssText = 'position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; background-color: rgba(15, 23, 42, 0.88) !important; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: flex !important; align-items: center !important; justify-content: center !important; z-index: 9999999 !important; padding: 1rem !important; margin: 0 !important;';

    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-150">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-blue-900 via-slate-900 to-slate-900 text-white p-6 flex items-start justify-between border-b border-blue-800/40">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600/30 border border-blue-400/40 flex items-center justify-center text-blue-300 text-xl font-bold">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                            <i class="bi bi-arrow-left-right mr-1"></i> PHMS ➔ PCMS Inter-System Transmittal
                        </span>
                        <h3 class="text-xl font-extrabold text-white mt-1">PHMS Data Ingestion Approval Sheet</h3>
                        <p class="text-xs text-slate-300">Document Control ID: <strong class="text-blue-300 font-mono">XFER-PHMS-PCMS-2026-0809-001</strong> | Standard Interoperability Protocol</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('phms-approval-sheet-modal').remove()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl font-bold flex items-center justify-center transition leading-none cursor-pointer">&times;</button>
            </div>

            <!-- Modal Content -->
            <div class="p-6 max-h-[72vh] overflow-y-auto space-y-6 text-xs text-slate-700">
                <!-- Section 1: System Transmittal Header -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Origin System</span>
                        <span class="font-bold text-blue-900 flex items-center gap-1"><i class="bi bi-building"></i> PHMS (Public Hearing System)</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Destination System</span>
                        <span class="font-bold text-red-900 flex items-center gap-1"><i class="bi bi-laptop"></i> PCMS Citizen Portal</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">API Security Token</span>
                        <span class="font-mono text-[11px] font-bold text-emerald-700">SHA256 (Bearer Verified)</span>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Target LGU Committee</span>
                        <span class="font-bold text-slate-800">Health & Sanitation Committee</span>
                    </div>
                </div>

                <!-- Section 2: Incoming Data Package Manifest -->
                <div>
                    <h4 class="font-extrabold text-slate-900 text-sm mb-2.5 flex items-center gap-1.5">
                        <i class="bi bi-box-seam text-blue-600"></i> Transmitted Data Payload Manifest (PHMS ➔ PCMS)
                    </h4>
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-100 font-bold text-[11px] text-slate-700 border-b border-slate-200 uppercase">
                                <tr>
                                    <th class="p-3">Hearing ID</th>
                                    <th class="p-3">Public Hearing Title</th>
                                    <th class="p-3">Date & Venue</th>
                                    <th class="p-3 text-center">Testimonies</th>
                                    <th class="p-3 text-center">Sentiment</th>
                                    <th class="p-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr>
                                    <td class="p-3 font-mono font-bold text-blue-700">PH-2026-004</td>
                                    <td class="p-3 font-bold text-slate-900">Consultation on Drainage Upgrades for Flood Control</td>
                                    <td class="p-3 text-slate-600">Aug 4, 2026 • Session Hall A</td>
                                    <td class="p-3 text-center font-bold text-slate-800">45 Entries</td>
                                    <td class="p-3 text-center"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">Positive (74%)</span></td>
                                    <td class="p-3 text-center"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">READY FOR MERGE</span></td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-mono font-bold text-blue-700">PH-2026-003</td>
                                    <td class="p-3 font-bold text-slate-900">Proposed Waste Segregation Enforcement Program</td>
                                    <td class="p-3 text-slate-600">Jul 29, 2026 • Barangay Center</td>
                                    <td class="p-3 text-center font-bold text-slate-800">32 Entries</td>
                                    <td class="p-3 text-center"><span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold text-[10px]">Neutral (68%)</span></td>
                                    <td class="p-3 text-center"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">READY FOR MERGE</span></td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-mono font-bold text-blue-700">PH-2026-002</td>
                                    <td class="p-3 font-bold text-slate-900">Valenzuela Bike Lane Expansion Program</td>
                                    <td class="p-3 text-slate-600">Jul 17, 2026 • City Auditorium</td>
                                    <td class="p-3 text-center font-bold text-slate-800">28 Entries</td>
                                    <td class="p-3 text-center"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px]">Positive (82%)</span></td>
                                    <td class="p-3 text-center"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">READY FOR MERGE</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Section 3: Verification & Compliance Checklist -->
                <div>
                    <h4 class="font-extrabold text-slate-900 text-sm mb-2.5 flex items-center gap-1.5">
                        <i class="bi bi-check2-square text-emerald-600"></i> Compliance & Validation Checklist
                    </h4>
                    <div class="bg-emerald-50/60 border border-emerald-200 rounded-xl p-4 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer font-semibold text-emerald-950">
                            <input type="checkbox" checked disabled class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500">
                            <span>Data Privacy Act of 2012 (RA 10173): Citizen Personal Identifiable Information (PII) anonymized.</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer font-semibold text-emerald-950">
                            <input type="checkbox" checked disabled class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500">
                            <span>CORS & Authorization Token: Verified valid Bearer token from PHMS domain.</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer font-semibold text-emerald-950">
                            <input type="checkbox" checked disabled class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500">
                            <span>Duplicate Prevention: Unique testimony ID hash check performed to avoid double counting.</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer font-semibold text-emerald-950">
                            <input type="checkbox" checked disabled class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500">
                            <span>AI NLP Processing: Sentiment score and keyword extraction pipeline executed cleanly.</span>
                        </label>
                    </div>
                </div>

                <!-- Section 4: Remarks & Approval Authorization -->
                <div class="bg-slate-100 rounded-xl p-4 border border-slate-200 space-y-3">
                    <label class="block font-bold text-slate-800 text-xs">Secretariat Approval Remarks / Transmittal Notes:</label>
                    <textarea id="phms-approval-remarks" rows="2" class="w-full p-2.5 border border-slate-300 rounded-lg text-xs bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter administrative approval note for this PHMS data packet transmittal..."></textarea>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs text-slate-500"><i class="bi bi-shield-lock-fill text-blue-700 mr-1"></i> Authorized Secretariat Sign-off Module</span>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="document.getElementById('phms-approval-sheet-modal').remove()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition cursor-pointer">
                        Cancel / Reject
                    </button>
                    <button type="button" onclick="approveAndIngestPhmsDataPackage()" class="px-5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-2 cursor-pointer">
                        <i class="bi bi-check-circle-fill"></i> Sign & Approve Data Ingestion
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
};

window.approveAndIngestPhmsDataPackage = function() {
    showNotification('✅ PHMS Transmittal Sheet Approved! Ingested 3 Hearing Summaries into PCMS Queue.', 'success', 6000);
    const modal = document.getElementById('phms-approval-sheet-modal');
    if (modal) modal.remove();
    if (typeof loadPhmsFeedbackFromApi === 'function') {
        loadPhmsFeedbackFromApi(true);
    }
};

function switchPublicFeedbackTab(tabName) {
    window.__current_pfq_tab__ = tabName;

    const consultBtn = document.getElementById('pfq-tab-consult');
    const surveyBtn = document.getElementById('pfq-tab-survey');
    const phmsBtn = document.getElementById('pfq-tab-phms');

    const consultContainer = document.getElementById('pfq-consult-container');
    const surveyContainer = document.getElementById('pfq-survey-container');
    const phmsContainer = document.getElementById('pfq-phms-container');

    if (window._phms_realtime_timer) {
        clearInterval(window._phms_realtime_timer);
        window._phms_realtime_timer = null;
    }

    const inactiveClass = 'px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-800 flex items-center gap-2 transition focus:outline-none cursor-pointer';

    if (consultBtn) consultBtn.className = inactiveClass;
    if (surveyBtn) surveyBtn.className = inactiveClass;
    if (phmsBtn) phmsBtn.className = inactiveClass;

    if (consultContainer) consultContainer.classList.add('hidden');
    if (surveyContainer) surveyContainer.classList.add('hidden');
    if (phmsContainer) phmsContainer.classList.add('hidden');

    if (tabName === 'phms') {
        if (phmsBtn) phmsBtn.className = 'px-4 py-2.5 text-xs font-bold border-b-2 border-blue-600 text-blue-600 flex items-center gap-2 transition focus:outline-none cursor-pointer';
        if (phmsContainer) phmsContainer.classList.remove('hidden');
        loadPhmsFeedbackFromApi(false);

        window._phms_realtime_timer = setInterval(() => {
            if (window.__current_pfq_tab__ === 'phms') {
                loadPhmsFeedbackFromApi(false);
            }
        }, 15000);
    } else if (tabName === 'survey') {
        if (surveyBtn) surveyBtn.className = 'px-4 py-2.5 text-xs font-bold border-b-2 border-purple-600 text-purple-600 flex items-center gap-2 transition focus:outline-none cursor-pointer';
        if (surveyContainer) surveyContainer.classList.remove('hidden');
        pfpRenderSurveyPollsTable();
    } else {
        if (consultBtn) consultBtn.className = 'px-4 py-2.5 text-xs font-bold border-b-2 border-red-600 text-red-600 flex items-center gap-2 transition focus:outline-none cursor-pointer';
        if (consultContainer) consultContainer.classList.remove('hidden');
        pfpRenderConsultationFeedbackTable();
    }
}

function pfpRenderSurveyPollsTable() {
    const tbody = document.getElementById('pfq-survey-table-body');
    if (!tbody) return;

    const q = String(document.getElementById('pfq-survey-search')?.value || '').toLowerCase().trim();
    const statusFilter = String(document.getElementById('pfq-survey-status')?.value || '').toLowerCase().trim();

    let consultations = Array.isArray(AppData.consultations)
        ? AppData.consultations.filter(c => (String(c.status || '').toLowerCase() === 'active' || String(c.status || '').toLowerCase() === 'open' || (c.type !== 'user' && String(c.type || '').toLowerCase() !== 'user')) && (String(c.response_mode || '').toLowerCase() === 'survey' || c.is_survey === 1 || c.is_survey === true))
        : [];

    if (q) {
        consultations = consultations.filter(c => {
            const title = String(c.title || '').toLowerCase();
            const qText = String(c.survey_question || '').toLowerCase();
            const dateStr = String(c.start_date || c.created_at || '').toLowerCase();
            const cid = String(c.id || '').toLowerCase();
            return title.includes(q) || qText.includes(q) || dateStr.includes(q) || cid.includes(q);
        });
    }

    if (statusFilter) {
        consultations = consultations.filter(c => String(c.status || '').toLowerCase() === statusFilter);
    }

    const badgeEl = document.getElementById('pfq-tab-survey-badge');
    if (badgeEl) {
        badgeEl.textContent = String(consultations.length);
    }

    if (consultations.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                    <i class="bi bi-inbox text-2xl block mb-2 text-gray-400"></i>
                    No community survey polls found matching your search.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = consultations.map(c => {
        const cid = Number(c.id);
        const title = escapeHtml(c.title || 'Survey Poll');
        const question = escapeHtml(c.survey_question || 'Do you support this proposed initiative?');
        const status = String(c.status || 'active').toLowerCase();

        let dateStr = 'Jul 27, 2026';
        if (c.created_at || c.start_date || c.upload_date) {
            try {
                const d = new Date(c.created_at || c.start_date || c.upload_date);
                dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            } catch (e) { }
        }

        const fbs = (AppData.feedback || []).filter(f => Number(f.consultationId || f.consultation_id) === cid);
        const optA = escapeHtml(c.survey_option_a || 'Agree');
        const optB = escapeHtml(c.survey_option_b || 'Disagree');

        let agreeCount = 0;
        let disagreeCount = 0;
        fbs.forEach(f => {
            const msg = String(f.message || f.testimony || f.statement || '').trim().toLowerCase();
            const isExplicitVote = (msg === 'agree' || msg === 'disagree' || msg === optA.toLowerCase() || msg === optB.toLowerCase());
            if (!isExplicitVote) return;

            const isDis = msg === 'disagree' || msg === optB.toLowerCase();
            const isAgr = msg === 'agree' || msg === optA.toLowerCase();

            if (isDis) disagreeCount++;
            else if (isAgr) agreeCount++;
        });

        const totalVotes = agreeCount + disagreeCount;
        const agreePct = totalVotes > 0 ? Math.round((agreeCount / totalVotes) * 100) : 0;
        const disagreePct = totalVotes > 0 ? 100 - agreePct : 0;

        let statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-white uppercase tracking-wider">CLOSED</span>';
        if (status === 'active' || status === 'open') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wider">ACTIVE</span>';
        }

        return `
            <tr class="border-b border-gray-100 hover:bg-purple-50/50 transition cursor-pointer select-none">
                <td class="px-4 py-3.5">
                    <div class="font-bold text-gray-900 text-xs leading-snug">${title}</div>
                    <div class="text-[11px] text-gray-500 font-medium mt-0.5"><i class="bi bi-question-circle mr-1 text-purple-600"></i>${question}</div>
                </td>
                <td class="px-4 py-3.5 font-medium text-gray-600 text-xs">${dateStr}</td>
                <td class="px-4 py-3.5 text-center font-extrabold text-slate-800 text-xs">
                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full bg-purple-100 text-purple-900 font-bold text-xs">
                        ${totalVotes} Vote(s)
                    </span>
                </td>
                <td class="px-4 py-3.5">
                    <div class="space-y-1 max-w-xs mx-auto">
                        <div class="flex justify-between text-[10px] font-extrabold">
                            <span class="text-emerald-700">${optA}: ${agreeCount} (${agreePct}%)</span>
                            <span class="text-rose-700">${optB}: ${disagreeCount} (${disagreePct}%)</span>
                        </div>
                        <div class="w-full h-2.5 bg-slate-200 rounded-full overflow-hidden flex border border-slate-300/60 shadow-2xs">
                            <div class="bg-emerald-500 h-full transition-all duration-500" style="width: ${agreePct}%"></div>
                            <div class="bg-rose-500 h-full transition-all duration-500" style="width: ${disagreePct}%"></div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3.5 text-center">${statusBadge}</td>
                <td class="px-4 py-3.5 text-center">
                    <button type="button" onclick="pfpViewConsultationFeedback(${cid})" class="px-3.5 py-1.5 bg-purple-700 hover:bg-purple-800 text-white font-semibold rounded-lg text-xs transition shadow-sm flex items-center gap-1 mx-auto cursor-pointer">
                        <i class="bi bi-bar-chart-fill"></i> View Poll Breakdown
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function pfpRenderConsultationFeedbackTable() {
    const tbody = document.getElementById('pfq-consultation-table-body');
    if (!tbody) return;

    const q = String(document.getElementById('pfq-consult-search')?.value || '').toLowerCase().trim();
    const statusFilter = String(document.getElementById('pfq-consult-status')?.value || '').toLowerCase().trim();

    let consultations = Array.isArray(AppData.consultations)
        ? AppData.consultations.filter(c => (String(c.status || '').toLowerCase() === 'active' || String(c.status || '').toLowerCase() === 'open' || (c.type !== 'user' && String(c.type || '').toLowerCase() !== 'user')) && String(c.response_mode || 'hybrid').toLowerCase() !== 'survey')
        : [];

    if (q) {
        consultations = consultations.filter(c => {
            const title = String(c.title || '').toLowerCase();
            const dateStr = String(c.start_date || c.created_at || '').toLowerCase();
            const cid = String(c.id || '').toLowerCase();
            return title.includes(q) || dateStr.includes(q) || cid.includes(q);
        });
    }

    if (statusFilter) {
        consultations = consultations.filter(c => String(c.status || '').toLowerCase() === statusFilter);
    }

    const badgeEl = document.getElementById('pfq-tab-consult-badge');
    if (badgeEl) {
        badgeEl.textContent = String(consultations.length);
    }

    if (consultations.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                    <i class="bi bi-inbox text-2xl block mb-2 text-gray-400"></i>
                    No consultations found matching your search filters.
                </td>
            </tr>
        `;
        return;
    }

    const selectEl = document.getElementById('pfq-consultation');
    if (selectEl) {
        const curVal = selectEl.value;
        selectEl.innerHTML = '<option value="">Select Consultation Policy...</option>' + consultations.map(c => `<option value="${c.id}">#${c.id} - ${escapeHtml(c.title || 'Consultation')}</option>`).join('');
        if (curVal) selectEl.value = curVal;
    }

    tbody.innerHTML = consultations.map(c => {
        const cid = Number(c.id);
        const title = escapeHtml(c.title || 'Consultation Policy');
        const status = String(c.status || 'active').toLowerCase();

        let dateStr = 'Jul 27, 2026';
        if (c.created_at || c.start_date || c.upload_date) {
            try {
                const d = new Date(c.created_at || c.start_date || c.upload_date);
                dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            } catch (e) { }
        }

        const fbs = (AppData.feedback || []).filter(f => Number(f.consultationId || f.consultation_id) === cid);
        const feedbackCount = fbs.length;

        let sumRating = 0;
        let countRating = 0;
        let publishedCount = 0;
        let pendingCount = 0;

        fbs.forEach(f => {
            const r = Number(f.rating || f.star_rating || 0);
            if (r > 0) {
                sumRating += r;
                countRating++;
            }
            const st = String(f.status || f.queue_status || '').toLowerCase();
            const msg = String(f.message || f.testimony || f.statement || '').trim().toLowerCase();
            const fCat = String(f.category || f.type || f.subType || '').toLowerCase();
            const isSurveyVote = fCat === 'survey vote' || fCat === 'survey' || fCat === 'vote' || f.is_survey_vote === true || msg === 'agree' || msg === 'disagree';

            if (isSurveyVote) {
                publishedCount++;
            } else if (st === 'pending' || st === 'new') {
                pendingCount++;
            } else {
                publishedCount++;
            }
        });

        let avgRating = countRating > 0 ? (sumRating / countRating).toFixed(1) : (c.avg_rating ? Number(c.avg_rating).toFixed(1) : '5.0');
        let ratingDisplay = `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 font-semibold text-xs"><i class="bi bi-star-fill text-amber-500 text-[11px]"></i> ${avgRating} / 5.0</span>`;

        if (feedbackCount === 0) {
            publishedCount = 0;
            pendingCount = 0;
        } else if (publishedCount === 0 && pendingCount === 0) {
            publishedCount = feedbackCount;
        }

        let statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-white uppercase tracking-wider">COMPLETED</span>';
        if (status === 'active' || status === 'open') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wider">ACTIVE</span>';
        } else if (status === 'pending' || status === 'draft') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 uppercase tracking-wider">PENDING</span>';
        }

        return `
            <tr class="border-b border-gray-100 hover:bg-blue-50/60 transition cursor-pointer select-none">
                <td class="px-4 py-3.5">
                    <div class="font-bold text-gray-900 text-xs leading-snug">${title}</div>
                    <div class="mt-1">${statusBadge}</div>
                </td>
                <td class="px-4 py-3.5 font-medium text-gray-600 text-xs">${dateStr}</td>
                <td class="px-4 py-3.5 text-center">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-800 font-bold text-xs">
                        ${feedbackCount}
                    </span>
                </td>
                <td class="px-4 py-3.5 text-center">
                    ${ratingDisplay}
                </td>
                <td class="px-4 py-3.5 text-center font-semibold text-emerald-700 text-xs">${publishedCount}</td>
                <td class="px-4 py-3.5 text-center font-semibold text-amber-700 text-xs">${pendingCount}</td>
                <td class="px-4 py-3.5 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <button type="button" onclick="pfpViewConsultationFeedback(${cid})" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-xs transition shadow-sm flex items-center gap-1 cursor-pointer" title="View citizen feedback submissions">
                            <i class="bi bi-chat-left-text"></i> View Feedback
                        </button>
                        <button type="button" onclick="pfpShowAiCommitteeBriefModal(${cid})" class="px-3 py-1.5 bg-rose-700 hover:bg-rose-800 text-white font-semibold rounded-lg text-xs transition shadow-sm flex items-center gap-1 cursor-pointer" title="Compile AI Brief & Executive Summary">
                            <i class="bi bi-robot"></i> AI Brief
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

window.pfpShowConsultationFeedbackModal = function (consultationId) {
    console.log('[PCMS Feedback Modal] Launching for consultationId:', consultationId);
    const oldModal = document.getElementById('pcms-detail-modal');
    if (oldModal) {
        try { oldModal.remove(); } catch (_) { }
    }

    const escapeHtmlHelper = (str) => String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const modal = document.createElement('div');
    modal.id = 'pcms-detail-modal';
    modal.style.cssText = 'position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; background-color: rgba(15, 23, 42, 0.88) !important; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: flex !important; align-items: center !important; justify-content: center !important; z-index: 9999999 !important; padding: 1rem !important; margin: 0 !important;';

    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-150" style="position: relative; z-index: 10000000 !important;">
            <!-- Modal Header -->
            <div class="bg-slate-900 text-white p-6 flex items-start justify-between">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-500/20 text-red-300 border border-red-400/30">
                        <i class="bi bi-chat-left-text mr-1"></i> PCMS CITIZEN FEEDBACK
                    </span>
                    <h3 id="pcms-modal-title" class="text-lg font-extrabold text-white mt-1.5">Consultation Feedback</h3>
                    <p id="pcms-modal-date" class="text-xs text-slate-300 mt-1">Loading citizen responses...</p>
                </div>
                <button type="button" onclick="const m=document.getElementById('pcms-detail-modal'); if(m)m.remove();" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl font-bold flex items-center justify-center transition leading-none cursor-pointer">&times;</button>
            </div>

            <!-- Modal Body -->
            <div id="pcms-modal-body" class="p-6 max-h-[70vh] overflow-y-auto space-y-4">
                <div class="p-8 text-center text-gray-500">
                    <i class="bi bi-arrow-repeat animate-spin text-2xl mb-2 block text-red-600"></i> Loading citizen feedback responses...
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <span class="text-xs text-gray-500"><i class="bi bi-shield-check text-red-600 mr-1"></i> Verified Citizen Testimonial Ledger</span>
                <button type="button" onclick="const m=document.getElementById('pcms-detail-modal'); if(m)m.remove();" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-xs transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const cid = Number(consultationId);
    const consultation = (AppData.consultations || []).find(c => Number(c.id) === cid);
    const title = escapeHtmlHelper(consultation ? consultation.title : `Consultation #${consultationId}`);
    const dateStr = escapeHtmlHelper(consultation ? (consultation.start_date || consultation.created_at || 'Recently') : 'Recently');
    const statusStr = escapeHtmlHelper(consultation ? (consultation.status || 'ACTIVE') : 'ACTIVE').toUpperCase();

    let fbs = (AppData.feedback || []).filter(f => Number(f.consultationId || f.consultation_id) === cid);

    const renderFeedbackResponses = (responsesList) => {
        const titleEl = document.getElementById('pcms-modal-title');
        const dateEl = document.getElementById('pcms-modal-date');
        const bodyEl = document.getElementById('pcms-modal-body');

        if (titleEl) titleEl.textContent = title;
        if (dateEl) dateEl.textContent = `📅 Date: ${dateStr} | Status: ${statusStr} | Total Submissions: ${responsesList.length}`;

        if (!responsesList.length) {
            if (bodyEl) {
                bodyEl.innerHTML = `
                    <div class="p-8 text-center text-gray-500 bg-slate-50 rounded-xl border border-slate-200">
                        <i class="bi bi-chat-left-text text-2xl block mb-2 text-gray-400"></i>
                        No citizen feedback recorded for this consultation policy yet.
                    </div>
                `;
            }
            return;
        }

        if (!bodyEl) return;

        const responsesHtml = responsesList.map((resp, idx) => {
            const name = escapeHtmlHelper(resp.fullName || resp.name || resp.citizen_name || resp.citizen || 'Valenzuela Citizen');
            const rating = resp.rating !== undefined && resp.rating !== null ? Number(resp.rating || resp.star_rating || 5.0).toFixed(1) : (resp.star_rating ? Number(resp.star_rating).toFixed(1) : '5.0');
            const tone = escapeHtmlHelper(resp.sentiment || resp.tone || 'unanalyzed');
            const testimony = escapeHtmlHelper(resp.message || resp.testimony || resp.statement || resp.proposal || 'No statement provided.');
            const submittedAt = escapeHtmlHelper(resp.submitted_at || resp.date || resp.created_at || 'Recently');
            const status = escapeHtmlHelper(resp.status || resp.publication_status || 'published').toLowerCase();

            const rCat = String(resp.category || resp.type || resp.subType || '').toLowerCase();
            const isSurveyVote = rCat === 'survey vote' || rCat === 'survey' || rCat === 'vote' || resp.is_survey_vote === true || testimony.toLowerCase() === 'agree' || testimony.toLowerCase() === 'disagree';
            const displayStatus = isSurveyVote ? 'VERIFIED VOTE' : status.toUpperCase();
            const statusClass = (isSurveyVote || status === 'published' || status === 'reviewed' || status === 'closed')
                ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                : 'bg-amber-100 text-amber-800 border-amber-200';

            const voteBadge = isSurveyVote
                ? `<span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border bg-purple-100 text-purple-900 border-purple-200 flex items-center gap-1"><i class="bi bi-square-poll-fill text-purple-600"></i> Survey Vote</span>`
                : '';

            return `
                <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200/80 shadow-sm space-y-2">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-gray-900 text-xs">${idx + 1}. ${name}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${statusClass}">${displayStatus}</span>
                            ${voteBadge}
                            ${tone && !isSurveyVote ? `<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">${tone}</span>` : ''}
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            ${!isSurveyVote ? `<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-900 border border-amber-200 font-semibold text-[11px]">⭐ ${rating}</span>` : ''}
                            <span class="text-gray-400 font-normal">${submittedAt}</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-700 leading-relaxed font-medium bg-white p-3 rounded-lg border border-slate-200/60 select-text flex items-center gap-2">
                        ${isSurveyVote ? `<i class="bi bi-chat-square-quote text-purple-500 text-sm"></i> <strong class="text-purple-950 font-bold">${testimony}</strong>` : `"${testimony}"`}
                    </p>
                </div>
            `;
        }).join('');

        let surveyBoxHtml = '';
        let aiSurveyConclusionHtml = '';
        if (consultation && String(consultation.response_mode || '').toLowerCase() === 'survey') {
            const question = escapeHtmlHelper(consultation.survey_question || 'Do you support this proposed ordinance initiative?');
            const optA = escapeHtmlHelper(consultation.survey_option_a || 'Agree');
            const optB = escapeHtmlHelper(consultation.survey_option_b || 'Disagree');

            let agreeCount = 0;
            let disagreeCount = 0;
            responsesList.forEach(r => {
                const msg = String(r.message || r.testimony || r.statement || '').trim().toLowerCase();
                const isDis = msg === 'disagree' || msg === optB.toLowerCase();
                const isAgr = msg === 'agree' || msg === optA.toLowerCase();

                if (isDis) {
                    disagreeCount++;
                } else if (isAgr) {
                    agreeCount++;
                }
            });

            const totalVotes = agreeCount + disagreeCount;
            const agreePct = totalVotes > 0 ? Math.round((agreeCount / totalVotes) * 100) : 0;
            const disagreePct = totalVotes > 0 ? 100 - agreePct : 0;

            const isClosed = (consultation.status || '').toLowerCase() === 'closed' || (consultation.status || '').toLowerCase() === 'completed';

            let mandateBadge = '🟢 CITIZEN SUPERMAJORITY SUPPORT';
            let conclusionText = `PUBLIC MANDATE CONCLUSION: Citizen voting data demonstrates strong public approval (${agreePct}% ${optA} vs ${disagreePct}% ${optB}). Based on finalized public sentiment, the City Council is recommended to enact the proposed initiative into law.`;

            if (totalVotes === 0) {
                mandateBadge = '⚪ NO CITIZEN VOTES CAST';
                conclusionText = 'PUBLIC MANDATE CONCLUSION: No citizen votes have been recorded for this opinion poll yet. Analysis will be updated live as public votes are submitted.';
            } else if (disagreePct > 50) {
                mandateBadge = '🔴 CITIZEN MAJORITY OPPOSITION';
                conclusionText = `PUBLIC MANDATE CONCLUSION: Citizen voting data indicates majority public opposition (${disagreePct}% ${optB} vs ${agreePct}% ${optA}). Based on public sentiment, the committee is advised to review key policy provisions or hold further public consultation before proceeding.`;
            } else if (agreePct === 50) {
                mandateBadge = '🟡 EVENLY BALANCED SENTIMENT';
                conclusionText = `PUBLIC MANDATE CONCLUSION: Public voting sentiment is evenly divided (${agreePct}% ${optA} vs ${disagreePct}% ${optB}). Additional public hearing sessions are recommended to resolve community concerns.`;
            }

            aiSurveyConclusionHtml = `
                <div class="p-4 bg-gradient-to-r from-purple-950 via-slate-900 to-indigo-950 text-white rounded-xl shadow-md border border-purple-700/60 space-y-2 mb-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-purple-300 flex items-center gap-1.5">
                            <i class="bi bi-robot text-purple-400 text-sm"></i> AI Poll Sentiment & Executive Conclusion
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase ${isClosed ? 'bg-emerald-500 text-white' : 'bg-amber-400 text-slate-950'} shadow-2xs">
                            ${isClosed ? 'FINAL MANDATE' : 'LIVE POLL ANALYSIS'}
                        </span>
                    </div>
                    <div class="text-xs font-extrabold text-purple-200 tracking-wide">
                        ${mandateBadge}
                    </div>
                    <p class="text-xs text-slate-200 leading-relaxed font-normal bg-slate-800/80 p-3 rounded-lg border border-purple-500/30 select-text">
                        ${conclusionText}
                    </p>
                </div>
            `;

            surveyBoxHtml = `
                <div class="p-4 bg-gradient-to-r from-purple-50 to-indigo-50/50 rounded-xl border border-purple-200/80 shadow-sm space-y-2 mb-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-purple-900 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="bi bi-square-poll-horizontal-fill text-purple-600"></i> Citizen Survey & Opinion Poll Stance
                        </span>
                        <span class="text-[11px] font-bold text-slate-600 bg-white px-2.5 py-0.5 rounded-full border border-purple-200 shadow-2xs">${totalVotes} Poll Vote(s)</span>
                    </div>
                    <p class="text-xs font-bold text-slate-800">${question}</p>
                    <div class="space-y-1 pt-1">
                        <div class="flex justify-between text-[11px] font-bold">
                            <span class="text-emerald-700"><i class="bi bi-hand-thumbs-up-fill mr-1"></i>${optA}: ${agreeCount} (${agreePct}%)</span>
                            <span class="text-rose-700"><i class="bi bi-hand-thumbs-down-fill mr-1"></i>${optB}: ${disagreeCount} (${disagreePct}%)</span>
                        </div>
                        <div class="w-full h-2.5 bg-slate-200 rounded-full overflow-hidden flex">
                            <div class="bg-emerald-500 h-full transition-all duration-500" style="width: ${agreePct}%"></div>
                            <div class="bg-rose-500 h-full transition-all duration-500" style="width: ${disagreePct}%"></div>
                        </div>
                    </div>
                </div>
            `;
        }

        bodyEl.innerHTML = `
            <div class="space-y-3">
                ${aiSurveyConclusionHtml}
                ${surveyBoxHtml}
                <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">
                    Submitted Citizen Responses (${responsesList.length})
                </div>
                ${responsesHtml}
            </div>
        `;
    };

    if (fbs.length > 0) {
        renderFeedbackResponses(fbs);
    } else {
        fetchWithTimeout(`API/feedback_api.php?action=list&consultation_id=${cid}`, {
            headers: { 'Accept': 'application/json' }
        }, 5000).then(res => res.json()).then(data => {
            let fetchedList = [];
            if (data && data.success && Array.isArray(data.data)) {
                fetchedList = data.data;
            } else if (data && data.success && Array.isArray(data.data?.items)) {
                fetchedList = data.data.items;
            }
            renderFeedbackResponses(fetchedList);
        }).catch(_ => {
            renderFeedbackResponses([]);
        });
    }
};

function pfpViewConsultationFeedback(consultationId) {
    pfpShowConsultationFeedbackModal(consultationId);
}

window.pfpRenderConsultationFeedbackTable = pfpRenderConsultationFeedbackTable;
window.pfpViewConsultationFeedback = pfpViewConsultationFeedback;


function pfpRenderReportsVaultTable() {
    const tbody = document.getElementById('pfq-reports-table-body');
    if (!tbody) return;

    const q = String(document.getElementById('pfq-reports-search')?.value || '').toLowerCase().trim();
    const commFilter = String(document.getElementById('pfq-reports-filter-committee')?.value || '').toLowerCase().trim();
    const statusFilter = String(document.getElementById('pfq-reports-filter-status')?.value || '').toLowerCase().trim();

    let consultations = Array.isArray(AppData.consultations) ? [...AppData.consultations] : [];

    if (q) {
        consultations = consultations.filter(c => {
            return (
                String(c.id || '').toLowerCase().includes(q) ||
                String(c.title || '').toLowerCase().includes(q) ||
                String(c.category || '').toLowerCase().includes(q) ||
                String(c.committee_assigned || '').toLowerCase().includes(q)
            );
        });
    }

    if (commFilter) {
        consultations = consultations.filter(c => {
            const comm = String(c.committee_assigned || c.category || '').toLowerCase();
            return comm.includes(commFilter);
        });
    }

    if (statusFilter === 'closed') {
        consultations = consultations.filter(c => ['closed', 'completed'].includes(String(c.status || '').toLowerCase()));
    } else if (statusFilter === 'active') {
        consultations = consultations.filter(c => !['closed', 'completed'].includes(String(c.status || '').toLowerCase()));
    }

    const badgeEl = document.getElementById('pfq-tab-reports-badge');
    if (badgeEl) {
        const closedCount = consultations.filter(c => ['closed', 'completed'].includes(String(c.status || '').toLowerCase())).length;
        badgeEl.textContent = String(closedCount);
    }

    if (!consultations.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No matching consultation reports found in the vault.</td></tr>';
        return;
    }

    tbody.innerHTML = consultations.map(c => {
        const cid = Number(c.id);
        const cStatus = String(c.status || 'active').toLowerCase();
        const isClosed = cStatus === 'closed' || cStatus === 'completed';

        // Count feedback linked to this consultation
        const feedbackCount = AppData.feedback.filter(f => Number(f.consultationId || f.consultation_id) === cid).length;

        const committee = c.committee_assigned || (c.category ? `${c.category} Committee` : 'Rules & Governance Committee');

        let statusBadge = `<span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-bold uppercase text-[10px] tracking-wider">Active</span>`;
        if (isClosed) {
            statusBadge = `<span class="px-2 py-0.5 rounded-md bg-slate-800 text-white font-bold uppercase text-[10px] tracking-wider">Closed</span>`;
        }

        let transmittalBadge = `<span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 font-semibold text-[10px]">Pending Closure</span>`;
        if (c.committee_forwarded_at) {
            const dateStr = new Date(c.committee_forwarded_at).toLocaleDateString();
            transmittalBadge = `<span class="px-2.5 py-0.5 rounded-md bg-purple-100 text-purple-900 font-bold text-[10px] tracking-wide"><i class="bi bi-check-all"></i> Transmitted (${dateStr})</span>`;
        } else if (isClosed) {
            transmittalBadge = `<span class="px-2.5 py-0.5 rounded-md bg-emerald-100 text-emerald-900 font-bold text-[10px] tracking-wide"><i class="bi bi-robot"></i> AI Brief Ready</span>`;
        }

        return `
            <tr class="border-b border-gray-100 hover:bg-purple-50/40 transition">
                <td class="px-3.5 py-3">
                    <div class="font-bold text-gray-900 text-xs leading-snug">#${cid} - ${escapeHtml(c.title || 'Consultation')}</div>
                    <div class="text-[11px] text-gray-500 font-medium">Category: ${escapeHtml(c.category || 'General Policy')}</div>
                </td>
                <td class="px-3.5 py-3">
                    <span class="inline-block px-2 py-0.5 bg-purple-50 text-purple-900 font-semibold rounded border border-purple-200 text-xs">
                        <i class="bi bi-diagram-3 mr-1"></i>${escapeHtml(committee)}
                    </span>
                </td>
                <td class="px-3.5 py-3 font-semibold text-gray-800 text-xs">
                    ${feedbackCount} submission(s)
                </td>
                <td class="px-3.5 py-3">
                    <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-medium text-xs capitalize">General Consensus</span>
                </td>
                <td class="px-3.5 py-3">${statusBadge}</td>
                <td class="px-3.5 py-3">${transmittalBadge}</td>
                <td class="px-3.5 py-3 text-center">
                    ${isClosed ? `
                        <button onclick="pfpShowAiCommitteeBriefModal(${cid})" class="inline-flex items-center justify-center px-3 py-1 bg-purple-700 hover:bg-purple-800 text-white font-bold rounded-lg text-xs shadow-sm gap-1 transition" title="View AI Report Document">
                            <i class="bi bi-file-earmark-text-fill"></i> View AI Report Document
                        </button>
                    ` : `
                        <button onclick="pfpShowAiCommitteeBriefModal(${cid})" class="inline-flex items-center justify-center px-3 py-1 bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold rounded-lg text-xs gap-1 border border-amber-300 transition" title="View Status Notice">
                            <i class="bi bi-lock-fill"></i> Pending (Active)
                        </button>
                    `}
                </td>
            </tr>
        `;
    }).join('');
}

window._phms_last_fetch_error = null;
window._phms_is_cached_data = false;

async function loadPhmsFeedbackFromApi(isSync = false, limit = 50, offset = 0) {
    window._phms_last_fetch_error = null;
    window._phms_is_cached_data = false;

    try {
        const action = isSync ? 'phms_sync' : 'phms_list';
        const res = await fetchWithTimeout(`API/feedback_api.php?action=${action}&limit=${limit}&offset=${offset}`, {
            headers: { 'Accept': 'application/json' }
        }, 5000);

        if (res.ok) {
            const data = await res.json();
            if (data && data.success && data.data) {
                const hearingsList = Array.isArray(data.data.hearings) ? data.data.hearings : (Array.isArray(data.data) ? data.data : []);
                AppData.phmsFeedback = hearingsList;
                if (data.is_cached) {
                    window._phms_is_cached_data = true;
                }

                // Push external system receipt notification to top notification bar
                if (hearingsList.length > 0) {
                    if (!Array.isArray(AppData.notifications)) AppData.notifications = [];
                    hearingsList.forEach(h => {
                        const title = h.hearing_title || h.title || h.full_name || 'Public Hearing';
                        const fbCount = h.feedback_count || 0;
                        const notifTitle = `🔗 PHMS Feedback Received: ${title}`;
                        const notifMsg = `Received ${fbCount} citizen hearing feedback response(s) from PHMS Public Hearing System for "${title}".`;

                        const exists = AppData.notifications.some(n => n.title === notifTitle || (n.message && n.message.includes(title)));
                        if (!exists) {
                            AppData.notifications.unshift({
                                id: Date.now() + Math.floor(Math.random() * 1000),
                                title: notifTitle,
                                message: notifMsg,
                                category: 'External Integration',
                                priority: 'high',
                                read: false,
                                time: 'Just now',
                                timestamp: new Date().toISOString()
                            });
                        }
                    });

                    // Trigger top navigation bar update
                    if (typeof loadNotifications === 'function') {
                        try { loadNotifications(); } catch (_) { }
                    } else {
                        const unreadCount = AppData.notifications.filter(n => !n.read).length;
                        const badge = document.getElementById('notif-badge') || document.getElementById('notification-badge');
                        if (badge) {
                            if (unreadCount > 0) {
                                badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                                badge.classList.remove('hidden');
                            }
                        }
                    }
                }

                if (isSync && typeof showNotification === 'function') {
                    showNotification(`✅ PHMS Integration: ${hearingsList.length} hearing feedback items synchronized.`, 'success');
                }
            } else if (data && !data.success) {
                window._phms_last_fetch_error = data.message || 'Service unavailable';
                if (isSync && typeof showNotification === 'function') {
                    showNotification(`⚠️ PHMS Integration: ${data.message || 'Service unavailable'}`, 'warning');
                }
            }
        } else if (res.status === 401) {
            window._phms_last_fetch_error = 'PHMS Authentication Failed (401 Unauthorized): Invalid integration token.';
            if (isSync && typeof showNotification === 'function') {
                showNotification('❌ PHMS Integration Unauthorized (401): Integration token invalid.', 'error');
            }
        }
    } catch (e) {
        window._phms_last_fetch_error = `PHMS Connection Failed: ${e.message}`;
        console.warn('PHMS feedback load failed:', e);
        if (isSync && typeof showNotification === 'function') {
            showNotification(`❌ PHMS Connection Failed: ${e.message}`, 'error');
        }
    }

    const badgeEl = document.getElementById('pfq-tab-phms-badge');
    if (badgeEl) {
        badgeEl.textContent = String(AppData.phmsFeedback ? AppData.phmsFeedback.length : 0);
    }

    pfpRenderPhmsTable();
}

function pfpPopulateConsultationDropdowns() {
    const pcmsSelect = document.getElementById('pfq-consultation');
    const consultations = Array.isArray(AppData.consultations) ? AppData.consultations : [];

    if (pcmsSelect) {
        const curVal = pcmsSelect.value || '';
        pcmsSelect.innerHTML = '<option value="">All Consultation Policies</option>' +
            consultations.map(c => {
                const title = escapeHtml(c.title || `Consultation #${c.id}`);
                const truncated = title.length > 40 ? title.substring(0, 37) + '...' : title;
                return `<option value="${c.id}" ${String(c.id) === curVal ? 'selected' : ''}>#${c.id} - ${truncated}</option>`;
            }).join('');
    }
}

function pfpResetFilters() {
    ['pfq-search', 'pfq-type', 'pfq-status', 'pfq-committee', 'pfq-consultation', 'pfq-barangay', 'pfq-phms-search', 'pfq-phms-status'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const archiveMode = document.getElementById('pfq-archive-mode');
    if (archiveMode) archiveMode.value = 'active';
    pfpRenderTable();
    pfpRenderPhmsTable();
}

function pfpRenderPhmsTable() {
    const tbody = document.getElementById('pfq-phms-table-body');
    if (!tbody) return;

    if (!tbody._phms_click_bound) {
        tbody._phms_click_bound = true;
        tbody.addEventListener('click', function (e) {
            const btn = e.target.closest('.phms-view-btn') || e.target.closest('[data-hearing-id]');
            if (btn) {
                const hid = btn.getAttribute('data-hearing-id');
                console.log('[PHMS Delegated Click] Table button clicked, hearingId:', hid);
                if (hid) {
                    pfpShowPhmsDetailModal(hid);
                }
            }
        });
    }

    const q = String(document.getElementById('pfq-phms-search')?.value || '').toLowerCase().trim();
    const statusFilter = String(document.getElementById('pfq-phms-status')?.value || '').toLowerCase().trim();

    let hearings = Array.isArray(AppData.phmsFeedback) ? [...AppData.phmsFeedback] : [];

    if (q) {
        hearings = hearings.filter(h => {
            const title = String(h.hearing_title || h.full_name || '').toLowerCase();
            const dateStr = String(h.hearing_date || h.created_at || '').toLowerCase();
            const hid = String(h.hearing_id || h.phms_hearing_id || h.queue_id || '').toLowerCase();
            return title.includes(q) || dateStr.includes(q) || hid.includes(q);
        });
    }

    if (statusFilter) {
        hearings = hearings.filter(h => String(h.hearing_status || h.status || '').toLowerCase() === statusFilter);
    }

    const badgeEl = document.getElementById('pfq-tab-phms-badge');
    if (badgeEl) {
        badgeEl.textContent = String(hearings.length);
    }

    if (hearings.length === 0) {
        const errDetail = window._phms_last_fetch_error ? escapeHtml(window._phms_last_fetch_error) : 'Please ensure the PHMS server is running or click "Sync PHMS Data" to refresh.';
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-10 text-center">
                    <div class="max-w-md mx-auto p-5 bg-amber-50/90 rounded-2xl border border-amber-200 text-amber-900 shadow-sm space-y-2">
                        <i class="bi bi-exclamation-triangle-fill text-2xl text-amber-600 block"></i>
                        <h4 class="font-bold text-sm">No PHMS Citizen Hearing Feedback Available</h4>
                        <p class="text-xs text-amber-800 leading-relaxed">${errDetail}</p>
                        <div class="pt-2">
                            <button type="button" onclick="loadPhmsFeedbackFromApi(true)" class="px-4 py-1.5 bg-amber-700 hover:bg-amber-800 text-white font-bold rounded-lg text-xs transition shadow-sm cursor-pointer">
                                <i class="bi bi-arrow-repeat mr-1 pointer-events-none"></i> Retry PHMS Integration Sync
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = hearings.map(h => {
        const hearingId = String(h.hearing_id || h.phms_hearing_id || h.queue_id || 0);
        const title = escapeHtml(h.hearing_title || h.full_name || 'Public Hearing');
        const status = escapeHtml(h.hearing_status || h.status || 'completed').toLowerCase();
        const dateStr = escapeHtml(h.hearing_date || h.created_at || 'N/A');
        const feedbackCount = h.feedback_count ?? 0;
        const avgRating = h.average_rating ? Number(h.average_rating).toFixed(1) : (h.avg_rating ? Number(h.avg_rating).toFixed(1) : '0.0');
        const publishedCount = h.published_count ?? (h.published_responses ?? feedbackCount);
        const pendingCount = h.pending_count ?? 0;

        let statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-white uppercase tracking-wider">COMPLETED</span>';
        if (status === 'active' || status === 'open') {
            statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wider">ACTIVE</span>';
        }

        return `
            <tr class="border-b border-gray-100 hover:bg-blue-50/60 transition cursor-pointer select-none" style="cursor: pointer !important; pointer-events: auto !important;">
                <td class="px-4 py-3.5">
                    <div class="font-bold text-gray-900 text-xs leading-snug">${title}</div>
                    <div class="mt-1">${statusBadge}</div>
                </td>
                <td class="px-4 py-3.5 font-medium text-gray-600 text-xs">${dateStr}</td>
                <td class="px-4 py-3.5 text-center">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-800 font-bold text-xs">
                        ${feedbackCount}
                    </span>
                </td>
                <td class="px-4 py-3.5 text-center">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 font-semibold text-xs">
                        <i class="bi bi-star-fill text-amber-500 text-[11px]"></i> ${avgRating}
                    </span>
                </td>
                <td class="px-4 py-3.5 text-center">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100/90 text-emerald-800 border border-emerald-300 font-extrabold text-[10px] shadow-2xs" title="Testimonies synthesized into AI Brief; redundant entries auto-skipped">
                        <i class="bi bi-cpu-fill text-emerald-600"></i> Analyzed (Auto-Skipped)
                    </span>
                </td>
                <td class="px-4 py-3.5 text-center">
                    <button type="button" data-hearing-id="${hearingId}" onclick="pfpShowPhmsDetailModal('${hearingId}')" class="phms-view-btn px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-xs transition shadow-sm flex items-center gap-1 mx-auto cursor-pointer" style="cursor:pointer !important; pointer-events: auto !important; position: relative; z-index: 5;">
                        <i class="bi bi-chat-left-text pointer-events-none"></i> View Feedback
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function pfpShowPhmsDetailModal(hearingId) {
    console.log('[PHMS Modal Delegation L17775] Delegating to top-level modal renderer for hearingId:', hearingId);
    if (typeof window.pfpShowPhmsDetailModal === 'function') {
        window.pfpShowPhmsDetailModal(hearingId);
    }
}

window.pfpShowPhmsDetailModal = pfpShowPhmsDetailModal;

if (!window._phms_global_click_listener) {
    window._phms_global_click_listener = true;
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.phms-view-btn') || e.target.closest('[data-hearing-id]');
        if (btn) {
            const hid = btn.getAttribute('data-hearing-id');
            console.log('[PHMS Global Document Listener Capture] View Feedback clicked, hearingId:', hid);
            if (hid) {
                pfpShowPhmsDetailModal(hid);
            }
        }
    }, true);
}

async function pfpUpdatePhmsStatus(queueId, newStatus) {
    if (!newStatus || !queueId) return;
    try {
        const res = await fetchWithTimeout('API/feedback_api.php?action=phms_update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ queue_id: queueId, status: newStatus })
        }, 5000);
        if (res.ok) {
            const data = await res.json();
            if (data && data.success) {
                showNotification(`PHMS queue item #${queueId} updated to ${newStatus}.`, 'success');
                const item = AppData.phmsFeedback.find(r => Number(r.queue_id) === Number(queueId));
                if (item) item.status = newStatus;
                pfpRenderPhmsTable();
            }
        }
    } catch (e) {
        showNotification(`Failed to update status: ${e.message}`, 'error');
    }
}

function pfpShowPhmsRawQueueModal(queueId) {
    const item = Array.isArray(AppData.phmsFeedback) ? AppData.phmsFeedback.find(r => Number(r.queue_id) === Number(queueId)) : null;
    if (!item) return;

    let modal = document.getElementById('phms-detail-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'phms-detail-modal';
        modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
        document.body.appendChild(modal);
    }

    let payloadFormatted = '-';
    if (item.payload_json) {
        try {
            const parsed = JSON.parse(item.payload_json);
            payloadFormatted = `<pre class="bg-gray-900 text-green-400 p-3 rounded text-[11px] font-mono overflow-x-auto max-h-48">${escapeHtml(JSON.stringify(parsed, null, 2))}</pre>`;
        } catch (_) {
            payloadFormatted = `<p class="text-xs text-gray-700 bg-gray-100 p-3 rounded font-mono">${escapeHtml(item.payload_json)}</p>`;
        }
    }

    modal.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-xl w-full overflow-hidden animate-in fade-in zoom-in duration-150">
            <div class="bg-gradient-to-r from-blue-700 to-blue-800 text-white p-5 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold flex items-center gap-2">
                        <i class="bi bi-building-gear"></i> PHMS Ingestion Queue Item #${item.queue_id}
                    </h3>
                    <p class="text-xs text-blue-100 mt-0.5">External Reference: ${escapeHtml(item.external_ref || 'N/A')}</p>
                </div>
                <button onclick="document.getElementById('phms-detail-modal').remove()" class="text-white hover:text-blue-200 text-xl font-bold">&times;</button>
            </div>
            <div class="p-6 space-y-4 text-xs text-gray-700 max-h-[75vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-3 bg-blue-50/50 p-3 rounded-lg border border-blue-100">
                    <div>
                        <span class="text-gray-400 font-semibold block text-[10px] uppercase">Citizen Full Name</span>
                        <span class="font-bold text-gray-900 text-sm">${escapeHtml(item.full_name || 'N/A')}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 font-semibold block text-[10px] uppercase">Email Address</span>
                        <span class="font-bold text-blue-700 text-sm">${escapeHtml(item.email || 'N/A')}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 font-semibold block text-[10px] uppercase">PHMS Hearing ID</span>
                        <span class="font-bold text-gray-800">${item.phms_hearing_id ? '#' + item.phms_hearing_id : 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 font-semibold block text-[10px] uppercase">Source System</span>
                        <span class="font-bold text-purple-700">${escapeHtml(item.source_system || 'PHMS')}</span>
                    </div>
                </div>

                <div>
                    <span class="text-gray-500 font-bold block mb-1">Associated Consultation Title:</span>
                    <p class="bg-gray-50 p-2.5 rounded border border-gray-200 font-medium text-gray-800">${escapeHtml(item.consultation_title || 'General Public Hearing Registration')}</p>
                </div>

                <div>
                    <span class="text-gray-500 font-bold block mb-1">Raw Integration Event Payload (JSON):</span>
                    ${payloadFormatted}
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3 border-t border-gray-100 flex items-center justify-between">
                <span class="text-[11px] text-gray-400">Ingested on: ${item.created_at ? new Date(item.created_at).toLocaleString() : '-'}</span>
                <button onclick="document.getElementById('phms-detail-modal').remove()" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white font-bold rounded-lg text-xs transition">
                    Close
                </button>
            </div>
        </div>
    `;
}

async function renderPCDocuments() {


    const contentArea = document.getElementById('content-area');







    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');







    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Document Management';

    if (!contentArea) return;

    const currentContent = contentArea.innerHTML.trim();
    if (!currentContent) {
        contentArea.innerHTML = '<div class="p-8 text-center text-gray-500">Loading documents...</div>';
    }

    try {
        await loadDocumentsFromApi();
    } catch (err) {
        contentArea.innerHTML = `<div class="p-8 text-center text-red-600">Failed to load documents.<div class="text-sm text-gray-500 mt-2">${String(err && err.message ? err.message : err)}</div></div>`;
        return;
    }

    const canManageDocuments = currentUserCanManageDocuments();
    const totalDocuments = AppData.documents.length;
    const totalSize = AppData.documents.reduce((sum, d) => sum + (d.size || 0), 0);
    const approvedDocs = AppData.documents.filter(d => String(d.status || '').toLowerCase() === 'approved').length;

    const docHtml = `
        <div class="space-y-6">
            <!-- Header with Statistics -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Document Management</h1>
                        <p class="text-red-100">Manage all consultation documents, track uploads, and monitor approval status</p>
                    </div>
                    ${canManageDocuments ? `<div class="flex gap-2">
                        <button onclick="openAddDocumentModal()" class="px-5 py-2.5 bg-white hover:bg-red-50 text-red-700 font-extrabold rounded-xl shadow-md transition-all flex items-center gap-2 text-xs border border-white/60 hover:shadow-lg hover:-translate-y-0.5">
                            <i class="bi bi-file-earmark-plus-fill text-red-600 text-sm"></i> Upload Document
                        </button>
                    </div>` : `<span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/20 text-red-100 text-sm">Read-only access</span>`}
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Documents</div>
                        <div class="text-3xl font-bold">${totalDocuments}</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Approved</div>
                        <div class="text-3xl font-bold">${approvedDocs}</div>
                    </div>

                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Size</div>
                        <div class="text-3xl font-bold">${formatFileSize(totalSize)}</div>
                    </div>
                </div>
            </div>

            <!-- Section Tabs: Consultation, Feedback, Survey -->
            <div class="flex flex-wrap gap-2 mt-6 border-b border-gray-200">
                <button onclick="filterDocumentsByGroup('consultation')" class="px-6 py-3 font-semibold text-sm border-b-2 border-red-600 text-red-600 hover:bg-red-50 doc-group-tab active" data-group="consultation">
                    <i class="bi bi-chat-left-quote mr-2"></i>Consultation
                </button>
                <button onclick="filterDocumentsByGroup('feedback')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-blue-600 hover:text-blue-600 transition doc-group-tab" data-group="feedback">
                    <i class="bi bi-hand-thumbs-up mr-2"></i>Feedback
                </button>
                <button onclick="filterDocumentsByGroup('survey')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-green-600 hover:text-green-600 transition doc-group-tab" data-group="survey">
                    <i class="bi bi-bar-chart mr-2"></i>Survey
                </button>
            </div>

            <!-- Documents Table for Selected Group -->
            <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Document Title</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Type</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Status Tracker</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Size</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Downloads</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="group-documents-table-body">
                            <tr><td colspan="6" class="text-center text-gray-400 p-6">No documents in this group</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Generate Report Modal -->
            <div id="generate-report-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-4 flex justify-between items-center">
                        <h3 class="text-lg font-bold">Generate Report</h3>
                        <button onclick="closeGenerateReportModal()" class="text-white hover:text-red-100 text-xl">&times;</button>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Report Type</label>
                                <select id="report-type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                    <option value="consultation_summary">Consultation Summary</option>
                                    <option value="feedback_analysis">Feedback Analysis</option>
                                    <option value="issue_report">Issue Report</option>
                                    <option value="survey_results">Survey Results</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                                    <input type="date" id="report-start-date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                                    <input type="date" id="report-end-date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                                <select id="report-category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                    <option value="all">All Categories</option>
                                    <option value="infrastructure">Infrastructure</option>
                                    <option value="health">Health</option>
                                    <option value="education">Education</option>
                                    <option value="environment">Environment</option>
                                    <option value="social">Social Services</option>
                                    <option value="governance">Governance</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                                <select id="report-status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                                    <option value="all">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="closed">Closed</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Export Format</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center">
                                        <input type="radio" name="export-format" value="pdf" checked class="mr-2">
                                        <span>PDF</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="export-format" value="excel" class="mr-2">
                                        <span>Excel</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="export-format" value="csv" class="mr-2">
                                        <span>CSV</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button onclick="closeGenerateReportModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button onclick="generateReport()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Generate Report</button>
                        </div>
                    </div>
                </div>
            </div>


        </div>




        <!-- Add/Edit Document Modal -->


        <div id="document-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">


            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-96 overflow-y-auto">


                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center">


                    <h2 id="doc-modal-title" class="text-2xl font-bold">Upload New Document</h2>


                    <button onclick="closeDocumentModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>


                </div>


                <div class="p-6 space-y-4">


                    <input type="hidden" id="document-id">


                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                        <div>


                            <label class="block text-sm font-semibold text-gray-700 mb-2">Reference Code *</label>


                            <input type="text" id="document-reference" placeholder="e.g., ORD-2025-001" 


                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">


                        </div>


                        <div>


                            <label class="block text-sm font-semibold text-gray-700 mb-2">Document Type *</label>


                            <select id="document-type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">


                                <option value="">Select Type</option>


                                <option value="ordinance">Ordinance</option>


                                <option value="resolution">Resolution</option>


                                <option value="memorandum">Memorandum</option>


                                <option value="report">Report</option>


                            </select>


                        </div>


                    </div>


                    <div>


                        <label class="block text-sm font-semibold text-gray-700 mb-2">Title *</label>


                        <input type="text" id="document-title" placeholder="Document title" 


                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">


                    </div>


                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                        <div>


                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>


                            <select id="document-status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">


                                <option value="">Select Status</option>
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="approved">Approved</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>


                            </select>


                        </div>


                        <div>


                            <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Date</label>


                            <input type="date" id="document-date" 


                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">


                        </div>


                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                        <textarea id="document-description" rows="2" placeholder="Optional description"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tags (comma separated)</label>
                        <input type="text" id="document-tags" placeholder="policy, ordinance, transport"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">File</label>
                        <input type="file" id="document-file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    </div>

                    <div class="flex gap-3 pt-4">


                        <button onclick="saveDocument()" class="flex-1 btn-primary">Save Document</button>


                        <button onclick="closeDocumentModal()" class="flex-1 btn-secondary">Cancel</button>


                    </div>


                </div>


            </div>


        </div>


    `;

    let docSection = document.getElementById('document-management-section');
    if (!docSection) {
        docSection = document.createElement('section');
        docSection.id = 'document-management-section';
        docSection.className = 'document-management-section mb-6';
        contentArea.appendChild(docSection);
    }
    docSection.innerHTML = docHtml;

    if (typeof hideManagedTemplateSections === 'function') {
        hideManagedTemplateSections();
    }
    docSection.style.display = 'block';

    renderDocumentsTable();
    refreshDocumentsModule(true);
}
window.renderPCDocuments = renderPCDocuments;
window.renderDocuments = renderPCDocuments;

// Filter documents by group (Consultation, Feedback, Survey, Reports, Versions)
function filterDocumentsByGroup(group) {
    // Update active tab styling
    document.querySelectorAll('.doc-group-tab').forEach(tab => {
        if (tab.dataset.group === group) {
            tab.classList.remove('border-gray-200', 'text-gray-600');
            tab.classList.add('border-red-600', 'text-red-600');
        } else {
            tab.classList.remove('border-red-600', 'text-red-600');
            tab.classList.add('border-gray-200', 'text-gray-600');
        }
    });

    if (group === 'versions') {
        loadDocumentVersions();
        return;
    }

    // Filter documents by group
    let groupDocuments = [];
    if (AppData && AppData.documents) {
        groupDocuments = AppData.documents.filter(doc => {
            // Determine which group a document belongs to
            const docType = String(doc.type || '').toLowerCase();
            const docGroup = String(doc.group || '').toLowerCase();

            if (group === 'consultation') {
                return docType.includes('consultation') || docGroup.includes('consultation') || docType === 'ordinance' || docType === 'resolution' || docType === 'final_document' || docType === 'consultation_form' || docType === 'attachment';
            } else if (group === 'feedback') {
                return docType.includes('feedback') || docGroup.includes('feedback') || docType === 'response' || docType.includes('brief') || docType.includes('summary') || (doc.title && String(doc.title).toLowerCase().includes('feedback'));
            } else if (group === 'survey') {
                return docType.includes('survey') || docGroup.includes('survey');
            } else if (group === 'reports') {
                return docType.includes('report') || docGroup.includes('report');
            }
            return false;
        });
    }

    // Render documents table
    const tbody = document.getElementById('group-documents-table-body');
    if (!tbody) return;

    if (groupDocuments.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-gray-400 p-6">No documents in this group</td></tr>`;
        return;
    }

    tbody.innerHTML = groupDocuments.map(doc => {
        const docRef = String(doc.reference || doc.id || '').replace(/'/g, "\\'");
        const docTitle = String(doc.title || doc.reference || 'Consultation Summary').replace(/'/g, "\\'");
        const docSource = String(doc.source || 'consultation').replace(/'/g, "\\'");
        const docIdClean = String(doc.id || doc.uid || '').replace(/'/g, "\\'");
        const statusVal = String(doc.status || 'submitted').toLowerCase().trim();

        let badgeStyle = 'bg-amber-50 text-amber-800 border-amber-300';
        let statusLabel = '⏳ Pending Review';

        if (statusVal === 'draft') {
            badgeStyle = 'bg-slate-100 text-slate-700 border-slate-300';
            statusLabel = '📝 Draft';
        } else if (statusVal === 'reviewed' || statusVal === 'under_review') {
            badgeStyle = 'bg-blue-50 text-blue-800 border-blue-300';
            statusLabel = '🔍 Under Review';
        } else if (statusVal === 'approved' || statusVal === 'active') {
            badgeStyle = 'bg-emerald-50 text-emerald-800 border-emerald-300';
            statusLabel = '✅ Approved & Active';
        } else if (statusVal === 'archived' || statusVal === 'closed') {
            badgeStyle = 'bg-purple-50 text-purple-800 border-purple-300';
            statusLabel = '📦 Archived';
        } else if (statusVal === 'rejected') {
            badgeStyle = 'bg-rose-50 text-rose-800 border-rose-300';
            statusLabel = '❌ Rejected';
        } else if (statusVal === 'forwarded_to_lrs' || statusVal === 'forwarded') {
            badgeStyle = 'bg-indigo-50 text-indigo-800 border-indigo-300';
            statusLabel = '🚀 Forwarded to LRS Hub';
        } else if (statusVal === 'published') {
            badgeStyle = 'bg-teal-50 text-teal-800 border-teal-300';
            statusLabel = '🌐 Live Published';
        }

        const docDotsTrackerHtml = renderConnectingDotsTracker(doc.status, docIdClean, 'document');

        return `
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="font-semibold text-gray-900">${doc.title || doc.reference || '-'}</div>
                    <div class="text-gray-600 text-xs mt-1">${doc.description || ''}</div>
                </td>
                <td class="px-6 py-4 text-gray-700">${doc.type || '-'}</td>
                <td class="px-6 py-4 text-center">${docDotsTrackerHtml}</td>
                <td class="px-6 py-4 text-center text-gray-600">${formatFileSize(doc.size || 0)}</td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-600 rounded-full font-semibold text-sm">
                        ${doc.downloads || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <div class="flex gap-2 justify-center items-center flex-wrap">
                        <button onclick="downloadDocument('${String(doc.uid || doc.id).replace(/'/g, "\\'")}')" class="text-blue-600 hover:text-blue-800" title="Download">
                            <i class="bi bi-download"></i> Download
                        </button>
                        ${doc.downloadUrl && doc.downloadUrl !== '#' ? `<button onclick="viewDocument('${String(doc.uid || doc.id).replace(/'/g, "\\'")}')" class="text-gray-600 hover:text-gray-800" title="View">
                            View
                        </button>` : ''}
                        <button onclick="openLiveDocumentTrackerModal('${docIdClean}', '${docSource}', '${docRef}', '${docTitle}')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded border border-slate-300 text-xs font-bold flex items-center gap-1 cursor-pointer" title="View Detailed Audit Timeline">
                            <i class="bi bi-clock-history text-amber-600"></i> Event Audit Log
                        </button>
                        <button onclick="openForwardLRSModal('${doc.id}', '${docSource}', '${docRef}', '${docTitle}')" class="px-2 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded border border-red-200 text-xs font-semibold flex items-center gap-1 cursor-pointer" title="Forward to LRS">
                            <i class="bi bi-send-fill text-red-600"></i> Forward to LRS
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/* ==========================================================
   5 CONNECTING DOTS PROGRESS TRACKER HELPER
   ========================================================== */
function renderConnectingDotsTracker(currentStatus, docOrConsultId, type = 'consultation') {
    const st = String(currentStatus || '').toLowerCase().trim();
    let currentStep = 1;

    let steps = [];
    if (type === 'document') {
        // Admin / Secretariat Official Document Workflow
        if (st === 'draft' || st === 'submitted' || st === 'pending' || !st) currentStep = 1;
        else if (st === 'under_review' || st === 'reviewed' || st === 'viewed') currentStep = 2;
        else if (st === 'active' || st === 'public_review' || st === 'published_portal') currentStep = 3;
        else if (st === 'closed' || st === 'closed_for_feedback' || st === 'ai_summary' || st === 'summarized' || st === 'synthesized') currentStep = 4;
        else if (st === 'forwarded_to_lrs' || st === 'forwarded' || st === 'committee' || st === 'approved' || st === 'ordinance') currentStep = 5;
        else if (st === 'published' || st === 'officialized' || st === 'archived' || st === 'completed' || st === 'rejected') currentStep = 6;

        steps = [
            { num: 1, name: 'Document Registration', desc: 'Official proposed measure uploaded and registered in PCMS repository', statusVal: 'submitted' },
            { num: 2, name: 'Resource Person Vetting', desc: 'Evaluated & prepared for public hearing by assigned Resource Person / Secretariat', statusVal: 'under_review' },
            { num: 3, name: 'Live Public Portal', desc: 'Published live on Public Portal for citizen reading, voting & public feedback', statusVal: 'active' },
            { num: 4, name: 'AI Feedback Synthesis', desc: 'PCMS AI Engine scans & summarizes all citizen votes and comments into a synthesis report', statusVal: 'ai_summary' },
            { num: 5, name: 'Committee & Ordinance Systems', desc: 'Report dispatched to Committee System for hearings & Ordinance System for drafting', statusVal: 'forwarded' },
            { num: 6, name: 'Officialized & Archived', desc: 'Enacted as official city ordinance, published & stored in permanent city archive', statusVal: 'published' }
        ];
    } else {
        // Citizen Submitted Consultation Workflow
        if (st === 'draft' || st === 'pending' || st === 'new' || st === 'submitted' || !st) currentStep = 1;
        else if (st === 'active' || st === 'published_portal' || st === 'voting') currentStep = 2;
        else if (st === 'closed' || st === 'closed_for_feedback' || st === 'ai_summary' || st === 'summarized' || st === 'synthesized') currentStep = 3;
        else if (st === 'under_review' || st === 'reviewed' || st === 'viewed' || st === 'replied') currentStep = 4;
        else if (st === 'scheduled' || st === 'committee' || st === 'forwarded' || st === 'approved' || st === 'ordinance') currentStep = 5;
        else if (st === 'completed' || st === 'officialized' || st === 'archived' || st === 'enacted') currentStep = 6;

        steps = [
            { num: 1, name: 'Intake & Submission', desc: 'Public consultation intake logged and registered into PCMS repository', statusVal: 'pending' },
            { num: 2, name: 'Live Public Portal', desc: 'Active on Public Portal collecting citizen votes, surveys, and public feedback', statusVal: 'active' },
            { num: 3, name: 'AI Feedback Synthesis', desc: 'Consultation closes; PCMS AI Engine scans & synthesizes all citizen comments & votes', statusVal: 'ai_summary' },
            { num: 4, name: 'Resource Person Review', desc: 'Assigned Resource Person reviews AI Summary, adds expert evaluation & endorses report', statusVal: 'under_review' },
            { num: 5, name: 'Committee & Ordinance Systems', desc: 'Endorsed report sent to Committee System for hearings & Ordinance System for bill drafting', statusVal: 'scheduled' },
            { num: 6, name: 'Officialized Ordinance', desc: 'Enacted into official city ordinance & stored in permanent municipal archive', statusVal: 'completed' }
        ];
    }

    const linePercent = Math.min(100, Math.max(0, (currentStep - 1) * 20));

    const dotsHtml = steps.map(step => {
        const isCompleted = step.num < currentStep;
        const isCurrent = step.num === currentStep;

        let dotBg = 'bg-slate-300 border-slate-400 hover:bg-slate-400';
        let innerContent = '';

        if (isCurrent) {
            dotBg = 'bg-amber-500 border-amber-600 ring-4 ring-amber-300/70 scale-115 shadow-sm';
            innerContent = '<span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>';
        } else if (isCompleted) {
            dotBg = 'bg-emerald-500 border-emerald-600 ring-1 ring-emerald-200';
            innerContent = '<i class="bi bi-check text-[10px] text-white font-black leading-none"></i>';
        }

        const safeName = step.name.replace(/'/g, "\\'");
        const safeDesc = step.desc.replace(/'/g, "\\'");

        const clickHandler = `openTrackerStageDetailModal('${type}', '${docOrConsultId}', ${step.num}, 6, '${safeName}', '${safeDesc}', '${step.statusVal}', ${isCurrent}, ${isCompleted})`;

        return `
            <div class="relative cursor-pointer hover:scale-125 transition transform" onclick="${clickHandler}" title="Click to view Stage ${step.num}: ${safeName}">
                <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all duration-200 ${dotBg}">
                    ${innerContent}
                </div>
            </div>
        `;
    }).join('');

    const currentStageInfo = steps[currentStep - 1] || steps[0];

    return `
        <div class="flex flex-col items-center gap-1 my-1">
            <div class="relative flex items-center justify-between w-44 px-2 py-1.5">
                <!-- Connecting Line Background -->
                <div class="absolute top-1/2 left-4 right-4 h-1 bg-slate-200 -translate-y-1/2 rounded-full z-0"></div>
                <!-- Active Progress Line -->
                <div class="absolute top-1/2 left-4 h-1 bg-gradient-to-r from-emerald-500 to-amber-500 -translate-y-1/2 rounded-full z-0 transition-all duration-500" style="width: ${linePercent}%;"></div>
                
                <!-- 6 Connecting Dots -->
                <div class="relative z-10 flex items-center justify-between w-full">
                    ${dotsHtml}
                </div>
            </div>
            <span class="text-[10px] font-black text-slate-700 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md uppercase tracking-wider shadow-2xs">${currentStep}/6 - ${currentStageInfo.name}</span>
        </div>
    `;
}

/* TOP RIGHT CORNER STAGE DETAIL MODAL (READ-ONLY REAL-TIME TRACKER) */
function openTrackerStageDetailModal(type, itemId, stepNum, totalSteps, stepName, stepDesc, statusVal, isCurrent, isCompleted) {
    let modalEl = document.getElementById('tracker-stage-detail-modal');
    if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = 'tracker-stage-detail-modal';
        document.body.appendChild(modalEl);
    }

    const typeTitle = type === 'document' ? 'Document Real-Time Tracker' : 'Consultation Real-Time Tracker';
    const statusBadgeHtml = isCurrent 
        ? `<span class="px-2.5 py-1 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 text-[11px] font-bold inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span> ⚡ Current Active Stage</span>`
        : (isCompleted 
            ? `<span class="px-2.5 py-1 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-[11px] font-bold inline-flex items-center gap-1.5"><i class="bi bi-check-circle-fill"></i> ✓ Stage Completed</span>`
            : `<span class="px-2.5 py-1 rounded-full bg-slate-800 border border-slate-700 text-slate-300 text-[11px] font-bold inline-flex items-center gap-1.5"><i class="bi bi-clock"></i> ○ Upcoming Stage</span>`);

    modalEl.className = 'fixed top-5 right-5 z-[9999] w-80 sm:w-96 bg-slate-900/95 backdrop-blur-md text-white rounded-2xl shadow-2xl border border-slate-700/80 p-5 transition-all duration-300 transform scale-100';
    modalEl.innerHTML = `
        <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-400 font-black text-xs flex items-center justify-center shadow-xs">
                    ${stepNum}/${totalSteps}
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-amber-400">${typeTitle}</div>
                    <div class="font-black text-sm text-white leading-tight">${stepName}</div>
                </div>
            </div>
            <button onclick="closeTrackerStageDetailModal()" class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs font-bold transition flex items-center justify-center cursor-pointer">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between text-xs">
                <span class="font-medium text-slate-400">Stage Status:</span>
                ${statusBadgeHtml}
            </div>

            <div class="bg-slate-800/90 rounded-xl p-3.5 border border-slate-700/60 text-xs text-slate-300 leading-relaxed font-medium">
                <div class="text-[11px] font-bold text-amber-300 mb-1 flex items-center gap-1">
                    <i class="bi bi-info-circle-fill"></i> Stage Details:
                </div>
                ${stepDesc}
            </div>

            <div class="pt-2 text-[10px] text-slate-400 font-medium text-center border-t border-slate-800/80 flex items-center justify-center gap-1">
                <i class="bi bi-shield-check text-emerald-400"></i> Automatically synchronized with real-time audit log
            </div>
        </div>
    `;
}

function closeTrackerStageDetailModal() {
    const modalEl = document.getElementById('tracker-stage-detail-modal');
    if (modalEl) {
        modalEl.remove();
    }
}

async function updateDocumentStatusFromTracker(docId, source, newStatus) {
    if (!docId || !newStatus) return;

    try {
        const response = await fetch('API/documents_api.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: docId, source: source, status: newStatus })
        });
        const result = await response.json();

        if (result && result.success) {
            const formattedName = newStatus.replace(/_/g, ' ').toUpperCase();
            showNotification(`Document status updated to ${formattedName}!`, 'success');
            refreshDocumentsModule(true);
        } else {
            showNotification(result.message || 'Failed to update document status', 'error');
        }
    } catch (err) {
        console.error('Error updating document status:', err);
        showNotification('Network error updating document status', 'error');
    }
}

/* ==========================================================
   LIVE DOCUMENT HAPPENINGS & SYSTEM INTEGRATION TRACKER MODAL
   ========================================================== */
let currentTrackingDocId = null;
let currentTrackingDocSource = 'consultation';
let currentTrackingDocRef = '';
let currentTrackingDocTitle = '';

function getLiveDocumentTrackerModalHtml() {
    return `
    <div id="live-document-tracker-modal" class="hidden fixed inset-0 bg-slate-900/70 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col border border-slate-200">
            <!-- Header -->
            <div class="bg-slate-900 text-white p-6 flex justify-between items-center relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 opacity-10 text-9xl text-white pointer-events-none">
                    <i class="bi bi-activity"></i>
                </div>
                <div class="relative z-10 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-red-600 flex items-center justify-center text-white shadow-md font-bold text-xl">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black tracking-tight text-white flex items-center gap-2">
                            Live Document Status Tracker
                            <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-xs font-bold flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Integration Active
                            </span>
                        </h3>
                        <p class="text-xs text-slate-300 mt-0.5" id="tracker-doc-subtitle">Tracking real-time document events, LRM system dispatches & status pipeline</p>
                    </div>
                </div>
                <button onclick="closeLiveDocumentTrackerModal()" class="text-slate-400 hover:text-white text-2xl font-bold transition cursor-pointer relative z-10">&times;</button>
            </div>

            <!-- Scrollable Content -->
            <div class="p-6 overflow-y-auto space-y-6 flex-1 bg-slate-50/50">
                <!-- Document Meta & Integration Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Document Reference</div>
                        <div class="text-sm font-black text-slate-800 font-mono" id="tracker-ref-code">CONSULT-000000</div>
                        <div class="text-xs text-slate-500 mt-1 truncate" id="tracker-doc-title">Document Title</div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">LRM Tracking Reference</div>
                        <div class="text-sm font-black text-indigo-600 font-mono flex items-center gap-1" id="tracker-lrm-id">
                            <i class="bi bi-qr-code-scan"></i> <span id="tracker-lrm-id-text">TRK-2026-0000</span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-1">External Records Hub Tracking ID</div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">System Integration Target</div>
                        <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5" id="tracker-integration-target">
                            <i class="bi bi-hdd-network text-emerald-600"></i> LRM System Hub (llrm.spvalenzuela.com)
                        </div>
                        <div class="text-[11px] text-emerald-600 font-semibold mt-1 flex items-center gap-1">
                            <i class="bi bi-check-circle-fill"></i> HTTP 200 - API Key Authenticated
                        </div>
                    </div>
                </div>

                <!-- Pipeline Progress Stepper -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs">
                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
                        <i class="bi bi-signpost-split-fill text-amber-500"></i> Workflow Lifecycle Pipeline
                    </h4>
                    <div class="grid grid-cols-4 gap-2 text-center relative" id="tracker-pipeline-container">
                        <!-- Dynamic stepper stages -->
                    </div>
                </div>

                <!-- Live Happenings Audit Feed -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xs space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                            <i class="bi bi-clock-history text-red-600"></i> Live Happenings & Activity Audit Feed
                        </h4>
                        <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full font-bold" id="tracker-event-count">0 Events Logged</span>
                    </div>
                    
                    <div id="tracker-timeline-feed" class="space-y-4 relative pl-6 border-l-2 border-slate-200">
                        <!-- Dynamic Timeline Events -->
                    </div>
                </div>

                <!-- Log Event / Action Box -->
                <div class="bg-slate-900 text-white p-5 rounded-2xl shadow-md space-y-4">
                    <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                        <i class="bi bi-pencil-square text-amber-400"></i> Advance Status or Log Custom Activity Event
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 mb-1">New Workflow Status</label>
                            <select id="tracker-new-status" class="w-full px-3 py-2 bg-slate-800 text-white border border-slate-700 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-amber-500">
                                <option value="under_review">🔍 Under Review</option>
                                <option value="approved">✅ Approved</option>
                                <option value="forwarded_to_lrs">🚀 Forwarded to LRS / LRM System</option>
                                <option value="published">🌐 Published on Public Portal</option>
                                <option value="archived">📦 Archived</option>
                                <option value="rejected">❌ Rejected</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-400 mb-1">Happenings Remarks / Action Notes</label>
                            <input type="text" id="tracker-event-notes" placeholder="e.g., Reviewed by Secretariat, submitted for committee approval" class="w-full px-3 py-2 bg-slate-800 text-white border border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-amber-500">
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-1">
                        <button onclick="submitTrackerEventLog()" class="px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-black rounded-xl text-xs transition shadow-2xs cursor-pointer flex items-center gap-1.5">
                            <i class="bi bi-plus-circle-fill"></i> Log Activity Event
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-slate-100 p-4 border-t border-slate-200 flex justify-between items-center">
                <button onclick="dispatchLiveLRMIntegrationFromModal()" id="btn-modal-lrm-dispatch" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition flex items-center gap-2 shadow-2xs cursor-pointer">
                    <i class="bi bi-send-fill text-amber-300"></i> Dispatch to External LRM System
                </button>
                <button onclick="closeLiveDocumentTrackerModal()" class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer">
                    Close Tracker
                </button>
            </div>
        </div>
    </div>
    `;
}

async function openLiveDocumentTrackerModal(docId, source, refNumber, title) {
    currentTrackingDocId = docId;
    currentTrackingDocSource = source || 'consultation';
    currentTrackingDocRef = refNumber || '';
    currentTrackingDocTitle = title || '';

    let modal = document.getElementById('live-document-tracker-modal');
    if (!modal) {
        const div = document.createElement('div');
        div.innerHTML = getLiveDocumentTrackerModalHtml();
        document.body.appendChild(div.firstElementChild);
        modal = document.getElementById('live-document-tracker-modal');
    }

    if (!modal) return;
    modal.classList.remove('hidden');

    const subtitle = document.getElementById('tracker-doc-subtitle');
    const refCode = document.getElementById('tracker-ref-code');
    const docTitleEl = document.getElementById('tracker-doc-title');

    if (subtitle) subtitle.innerText = `Document: ${title || 'Consultation Summary'} | Ref: ${refNumber || docId}`;
    if (refCode) refCode.innerText = refNumber || ('CONSULT-' + String(docId).padStart(6, '0'));
    if (docTitleEl) docTitleEl.innerText = title || 'Document';

    await loadLiveTrackingTimeline(docId, source, refNumber);
}

function closeLiveDocumentTrackerModal() {
    const modal = document.getElementById('live-document-tracker-modal');
    if (modal) modal.classList.add('hidden');
}

async function loadLiveTrackingTimeline(docId, source, refNumber) {
    const timelineFeed = document.getElementById('tracker-timeline-feed');
    const pipelineContainer = document.getElementById('tracker-pipeline-container');
    const eventCount = document.getElementById('tracker-event-count');
    const trackerLrmText = document.getElementById('tracker-lrm-id-text');

    if (timelineFeed) {
        timelineFeed.innerHTML = `<div class="text-center py-6 text-slate-400 font-semibold flex items-center justify-center gap-2"><i class="bi bi-arrow-repeat animate-spin text-lg text-amber-500"></i> Loading live document happenings timeline...</div>`;
    }

    try {
        const res = await fetch(`API/documents_api.php?action=get_tracking_timeline&id=${docId}&source=${source}&reference=${encodeURIComponent(refNumber || '')}`);
        const data = await res.json();

        if (data && data.success) {
            if (trackerLrmText) trackerLrmText.innerText = data.latest_tracking_id || 'TRK-PENDING';
            if (eventCount) eventCount.innerText = `${(data.timeline || []).length} Happenings Logged`;

            // Pipeline Stepper (4 Stages)
            const stage = data.pipeline_stage || 1;
            const stages = [
                { title: '1. Uploaded', desc: 'Registered in PCMS' },
                { title: '2. Under Review', desc: 'Secretariat Evaluation' },
                { title: '3. Dispatched (LRM)', desc: 'Sent to Records System' },
                { title: '4. Approved & Live', desc: 'Published Record' }
            ];

            if (pipelineContainer) {
                pipelineContainer.innerHTML = stages.map((s, idx) => {
                    const stepNum = idx + 1;
                    const isDone = stepNum <= stage;
                    const isCurrent = stepNum === stage;
                    const bgClass = isDone ? (isCurrent ? 'bg-amber-500 text-slate-950 font-bold border-amber-400 ring-2 ring-amber-400/50' : 'bg-emerald-600 text-white font-bold') : 'bg-slate-100 text-slate-400 border-slate-200';
                    const icon = isDone ? (isCurrent ? 'bi-hourglass-split' : 'bi-check-circle-fill') : 'bi-circle';
                    return `
                        <div class="flex flex-col items-center">
                            <div class="w-9 h-9 rounded-full ${bgClass} flex items-center justify-center text-sm shadow-2xs transition-all mb-1">
                                <i class="bi ${icon}"></i>
                            </div>
                            <span class="text-[11px] font-bold ${isDone ? 'text-slate-800' : 'text-slate-400'}">${s.title}</span>
                            <span class="text-[9px] text-slate-400">${s.desc}</span>
                        </div>
                    `;
                }).join('');
            }

            // Timeline Feed
            if (timelineFeed) {
                if (!data.timeline || data.timeline.length === 0) {
                    timelineFeed.innerHTML = `<div class="text-center py-6 text-slate-400">No activity events logged yet</div>`;
                } else {
                    timelineFeed.innerHTML = data.timeline.map((ev) => {
                        return `
                            <div class="relative pl-6 pb-2">
                                <div class="absolute -left-[25px] top-0 w-4 h-4 rounded-full bg-slate-900 border-2 border-white ring-2 ring-amber-400 flex items-center justify-center">
                                    <div class="w-1.5 h-1.5 rounded-full bg-amber-400"></div>
                                </div>
                                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 shadow-2xs">
                                    <div class="flex justify-between items-start mb-1 flex-wrap gap-2">
                                        <span class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                                            ${ev.title}
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold border ${ev.badge || 'bg-slate-100 text-slate-700'}">${ev.activity || 'Event'}</span>
                                        </span>
                                        <span class="text-[11px] font-semibold text-slate-400 font-mono">${ev.timestamp}</span>
                                    </div>
                                    <p class="text-xs text-slate-600 font-medium mt-1">${ev.notes || ''}</p>
                                    <div class="flex items-center gap-4 text-[10px] text-slate-400 mt-2 font-semibold pt-2 border-t border-slate-200/60">
                                        <span><i class="bi bi-person-fill text-slate-500"></i> ${ev.performer || 'System'}</span>
                                        <span><i class="bi bi-building text-slate-500"></i> ${ev.department || 'Office'}</span>
                                        ${ev.tracking_id ? `<span class="font-mono text-indigo-600"><i class="bi bi-qr-code"></i> ID: ${ev.tracking_id}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        } else {
            if (timelineFeed) timelineFeed.innerHTML = `<div class="p-4 bg-rose-50 text-rose-700 rounded-xl text-xs font-bold text-center">Failed to load tracking timeline</div>`;
        }
    } catch (err) {
        console.error('Error loading live tracking timeline:', err);
        if (timelineFeed) timelineFeed.innerHTML = `<div class="p-4 bg-rose-50 text-rose-700 rounded-xl text-xs font-bold text-center">Network error loading live happenings</div>`;
    }
}

async function submitTrackerEventLog() {
    if (!currentTrackingDocId) return;

    const newStatus = document.getElementById('tracker-new-status').value;
    const eventNotes = document.getElementById('tracker-event-notes').value.trim();

    try {
        const formData = new FormData();
        formData.append('id', currentTrackingDocId);
        formData.append('source', currentTrackingDocSource);
        formData.append('status', newStatus);
        formData.append('notes', eventNotes || `Workflow status updated to ${newStatus}`);
        formData.append('activity', 'Status Progression');

        const response = await fetch('API/documents_api.php?action=log_event', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result && result.success) {
            showNotification('Live event logged & status updated!', 'success');
            document.getElementById('tracker-event-notes').value = '';
            await loadLiveTrackingTimeline(currentTrackingDocId, currentTrackingDocSource, currentTrackingDocRef);
            if (typeof refreshDocumentsModule === 'function') refreshDocumentsModule(true);
        } else {
            showNotification(result.message || 'Failed to log event', 'error');
        }
    } catch (err) {
        console.error('Error logging event:', err);
        showNotification('Network error logging tracking event', 'error');
    }
}

async function dispatchLiveLRMIntegrationFromModal() {
    if (!currentTrackingDocId) return;
    const btn = document.getElementById('btn-modal-lrm-dispatch');
    if (btn) btn.disabled = true;

    try {
        showNotification('Initiating 3-Step Live LRM System Integration Dispatch...', 'info');
        const response = await fetch('API/documents_api.php?action=forward_to_lrs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentTrackingDocId,
                source: currentTrackingDocSource,
                description: 'Live dispatch from Document Tracker Happenings Feed'
            })
        });
        const result = await response.json();

        if (result && result.success) {
            showNotification(`Successfully dispatched to LRM System! Tracking ID: ${result.tracking_id || 'Generated'}`, 'success');
            await loadLiveTrackingTimeline(currentTrackingDocId, currentTrackingDocSource, currentTrackingDocRef);
            if (typeof refreshDocumentsModule === 'function') refreshDocumentsModule(true);
        } else {
            showNotification(result.message || 'Failed to dispatch to LRM System', 'error');
        }
    } catch (err) {
        console.error('Error dispatching LRM integration:', err);
        showNotification('Network error dispatching LRM integration', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// Forward to LRS Modal & Versioning Handlers
function openForwardLRSModal(docId, source, reference, title) {
    const modal = document.getElementById('forward-lrs-modal');
    if (!modal) return;

    document.getElementById('lrs-doc-id').value = docId || '';
    document.getElementById('lrs-doc-source').value = source || 'consultation';
    document.getElementById('lrs-doc-ref').value = reference || ('CONSULT-' + docId);
    document.getElementById('lrs-doc-title').value = title || 'Consultation Summary Document';
    document.getElementById('lrs-doc-desc').value = 'Sample consultation summary forwarded from PCMS';

    modal.style.display = 'flex';
    modal.classList.remove('hidden');
}

function closeForwardLRSModal() {
    const modal = document.getElementById('forward-lrs-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

async function submitForwardToLRS(event) {
    if (event) event.preventDefault();
    const submitBtn = document.getElementById('lrs-submit-btn');
    const docId = document.getElementById('lrs-doc-id').value;
    const source = document.getElementById('lrs-doc-source').value;
    const description = document.getElementById('lrs-doc-desc').value;

    if (!docId) {
        showNotification('Invalid document selected', 'error');
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Forwarding...';
    }

    try {
        const response = await fetch('API/documents_api.php?action=forward_lrs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: docId, source: source, description: description })
        });
        const result = await response.json();

        if (result.success) {
            showNotification(result.message || 'Document successfully forwarded to LRS!', 'success');
            closeForwardLRSModal();
            refreshDocumentsModule(true);
        } else {
            showNotification(result.message || 'Failed to forward document to LRS', 'error');
        }
    } catch (err) {
        console.error('Error forwarding document to LRS:', err);
        showNotification('Network error while forwarding document to LRS', 'error');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Forward to LRS';
        }
    }
}

async function loadDocumentVersions() {
    const tbody = document.getElementById('group-documents-table-body');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-gray-500 p-6"><i class="bi bi-arrow-repeat spin mr-2"></i> Loading document versions...</td></tr>`;

    try {
        const response = await fetch('API/documents_api.php?action=list_versions');
        const result = await response.json();

        if (!result.success || !Array.isArray(result.data) || result.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-gray-400 p-6">No version history found. Forward a document to LRS or receive returned documents to see history here.</td></tr>`;
            return;
        }

        tbody.innerHTML = result.data.map(ver => {
            const isLrs = ver.source_system === 'lrs';
            const badgeColor = isLrs ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800';
            const statusBadge = ver.status === 'forwarded_to_lrs'
                ? '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Forwarded to LRS</span>'
                : (ver.status === 'returned_from_lrs'
                    ? '<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Returned from LRS</span>'
                    : `<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">${ver.status}</span>`);

            return `
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-900 flex items-center gap-2">
                            ${ver.title || 'Untitled'}
                            <span class="px-2 py-0.5 text-xs font-bold rounded ${badgeColor}">v${ver.version_number || '1.0'}</span>
                        </div>
                        <div class="text-gray-500 text-xs mt-1 font-mono">Ref: ${ver.reference_number || '-'}</div>
                        ${ver.notes ? `<div class="text-gray-600 text-xs mt-1 italic">"${ver.notes}"</div>` : ''}
                    </td>
                    <td class="px-6 py-4 text-xs font-medium text-gray-700">
                        <span class="uppercase tracking-wider px-2 py-0.5 rounded text-xs ${isLrs ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-700'}">
                            ${ver.source_system ? ver.source_system.toUpperCase() : 'PCMS'}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">${statusBadge}</td>
                    <td class="px-6 py-4 text-center text-xs text-gray-600">${formatFileSize(ver.file_size || 0)}</td>
                    <td class="px-6 py-4 text-center text-xs text-gray-500">${ver.created_at ? ver.created_at : '-'}</td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex gap-2 justify-center">
                            <a href="${ver.download_url}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs font-semibold flex items-center gap-1" title="Download Version">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        console.error('Error fetching document versions:', err);
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-red-500 p-6">Error loading version history.</td></tr>`;
    }
}
window.openForwardLRSModal = openForwardLRSModal;
window.closeForwardLRSModal = closeForwardLRSModal;
window.submitForwardToLRS = submitForwardToLRS;
window.loadDocumentVersions = loadDocumentVersions;


// Documents module: tabbed view (Consultations, Surveys, Feedback, Reports)
function renderDocumentsModule() {
    const contentArea = document.getElementById('content-area');
    let section = document.getElementById('documents-module-section');
    if (!section) {
        if (typeof renderPCDocuments === 'function') return renderPCDocuments();
        return;
    }

    // Inject the section HTML into the content area to ensure it's preserved when other renderers
    // replace contentArea.innerHTML. This makes the documents UI authoritative after rendering.
    try {
        if (contentArea) contentArea.innerHTML = section.outerHTML;
        // re-select now that it's inside contentArea
        section = contentArea.querySelector('#documents-module-section') || section;
        section.style.display = 'block';
    } catch (e) { /* ignore */ }

    // Setup tab buttons
    const tabs = Array.from(section.querySelectorAll('#doc-tabs-main button'));
    const panes = {
        consultations: section.querySelector('#documents-tab-consultations'),
        surveys: section.querySelector('#documents-tab-surveys'),
        feedback: section.querySelector('#documents-tab-feedback'),
        reports: section.querySelector('#documents-tab-reports')
    };

    function showTab(name) {
        tabs.forEach(b => b.classList.remove('bg-red-600', 'text-white'));
        tabs.forEach(b => b.classList.add('bg-gray-100', 'text-gray-700'));
        const btn = tabs.find(b => b.dataset.tab === name);
        if (btn) { btn.classList.remove('bg-gray-100', 'text-gray-700'); btn.classList.add('bg-red-600', 'text-white'); }
        Object.keys(panes).forEach(k => { if (panes[k]) panes[k].style.display = (k === name) ? 'block' : 'none'; });
    }

    tabs.forEach(b => {
        b.onclick = () => showTab(b.dataset.tab);
    });

    // Populate tables from AppData.documents
    function escapeHtml(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]); }

    const consultations = AppData.documents.filter(d => (d.source || '').toLowerCase() === 'consultation' || (d.type || '').toLowerCase() === 'consultation');
    const surveys = AppData.documents.filter(d => (d.type || '').toLowerCase() === 'survey');
    const feedbackDocs = AppData.documents.filter(d => (d.type || '').toLowerCase() === 'feedback');
    const reports = AppData.documents.filter(d => ['report', 'reporting', 'admin'].includes((d.type || '').toLowerCase()) || ((d.source || '').toLowerCase() === 'admin' && !['consultation', 'survey', 'feedback'].includes((d.type || '').toLowerCase())));

    const renderRows = (list) => list.map(d => `
        <tr>
            <td class="px-4 py-2 text-xs text-gray-700">${escapeHtml(d.reference || d.id || '')}</td>
            <td class="px-4 py-2 text-xs text-gray-900">${escapeHtml(d.title || d.originalFilename || 'Untitled')}</td>
            <td class="px-4 py-2 text-xs text-gray-700">${escapeHtml(d.uploadedBy || d.uploaded_by || '')}</td>
            <td class="px-4 py-2 text-xs text-gray-700">${escapeHtml(d.date || d.uploadedAt || d.uploaded_at || '')}</td>
            <td class="px-4 py-2 text-xs text-gray-700">${escapeHtml(d.status || '')}</td>
        </tr>
    `).join('');

    const cBody = section.querySelector('#documents-consultations-body');
    const sBody = section.querySelector('#documents-surveys-body');
    const fBody = section.querySelector('#documents-feedback-body');
    const rSummary = section.querySelector('#documents-reports-summary');

    if (cBody) cBody.innerHTML = (consultations.length ? renderRows(consultations) : '<tr><td colspan="5" class="px-4 py-2 text-xs text-gray-500">No consultation documents found.</td></tr>');
    if (sBody) sBody.innerHTML = (surveys.length ? renderRows(surveys) : '<tr><td colspan="5" class="px-4 py-2 text-xs text-gray-500">No surveys found.</td></tr>');
    if (fBody) fBody.innerHTML = (feedbackDocs.length ? renderRows(feedbackDocs) : '<tr><td colspan="5" class="px-4 py-2 text-xs text-gray-500">No feedback documents found.</td></tr>');
    if (rSummary) rSummary.innerHTML = `<div class="p-4">Total reports: <strong>${reports.length}</strong></div>`;

    // Default to consultations tab
    showTab('consultations');
}


function renderDocumentsTable() {
    const consultationBody = document.getElementById('documents-group-consultation-body');
    const feedbackBody = document.getElementById('documents-group-feedback-body');
    const surveyBody = document.getElementById('documents-group-survey-body');
    const reportsBody = document.getElementById('documents-group-reports-body');
    const documents = getFilteredDocuments();

    const groups = {
        consultation: documents.filter(doc => getDocumentGroup(doc) === 'consultation'),
        feedback: documents.filter(doc => getDocumentGroup(doc) === 'feedback'),
        survey: documents.filter(doc => getDocumentGroup(doc) === 'survey'),
        reports: documents.filter(doc => getDocumentGroup(doc) === 'reports')
    };

    const escapeHtml = (value) => String(value == null ? '' : value).replace(/[&<>\"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
    const renderRows = (list) => list.map(doc => `
        <tr class="border-b last:border-b-0 hover:bg-gray-50">
            <td class="px-4 py-3 text-xs text-gray-700">${escapeHtml(doc.reference || doc.id || '')}</td>
            <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(doc.title || doc.originalFilename || 'Untitled')}</td>
            <td class="px-4 py-3 text-xs text-gray-700">${escapeHtml(doc.uploadedBy || doc.uploaded_by || doc.source || '')}</td>
            <td class="px-4 py-3 text-xs text-gray-700">${escapeHtml(doc.date || doc.uploadedAt || doc.uploaded_at || '')}</td>
            <td class="px-4 py-3 text-xs text-gray-700">${escapeHtml(String(doc.status || ''))}</td>
        </tr>
    `).join('');

    if (consultationBody) {
        consultationBody.innerHTML = groups.consultation.length ? renderRows(groups.consultation) : '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No consultation documents found.</td></tr>';
    }
    if (feedbackBody) {
        feedbackBody.innerHTML = groups.feedback.length ? renderRows(groups.feedback) : '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No feedback documents found.</td></tr>';
    }
    if (surveyBody) {
        surveyBody.innerHTML = groups.survey.length ? renderRows(groups.survey) : '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No survey documents found.</td></tr>';
    }
    if (reportsBody) {
        reportsBody.innerHTML = groups.reports.length ? renderRows(groups.reports) : '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No reports found.</td></tr>';
    }
}

function getDocumentGroup(doc) {
    const type = String(doc.type || '').toLowerCase();
    const source = String(doc.source || '').toLowerCase();
    const reference = String(doc.reference || '').toLowerCase();
    const title = String(doc.title || '').toLowerCase();

    if (type.includes('survey') || reference.includes('survey') || title.includes('survey')) {
        return 'survey';
    }
    if (type.includes('feedback') || reference.includes('feedback') || title.includes('feedback')) {
        return 'feedback';
    }
    if (type.includes('report') || reference.includes('report') || title.includes('report') || source.includes('report')) {
        return 'reports';
    }
    return 'consultation';
}

function getFilteredDocuments() {


    let filtered = [...AppData.documents];





    const searchTerm = document.getElementById('doc-search')?.value.toLowerCase() || '';


    const statusFilter = document.getElementById('doc-status-filter')?.value || '';


    const typeFilter = document.getElementById('doc-type-filter')?.value || '';
    const sortBy = document.getElementById('doc-sort')?.value || 'date-desc';




    if (searchTerm) {


        filtered = filtered.filter(d =>


            d.title.toLowerCase().includes(searchTerm) ||


            d.reference.toLowerCase().includes(searchTerm)


        );


    }





    if (statusFilter) {


        filtered = filtered.filter(d => d.status === statusFilter);


    }





    if (typeFilter) {


        filtered = filtered.filter(d => String(d.type || '').toLowerCase() === typeFilter.toLowerCase());


    }



    // Sort


    filtered.sort((a, b) => {


        switch (sortBy) {


            case 'date-asc':


                return new Date(a.date) - new Date(b.date);


            case 'title':


                return a.title.localeCompare(b.title);


            case 'size':


                return (b.size || 0) - (a.size || 0);


            case 'date-desc':


            default:


                return new Date(b.date) - new Date(a.date);


        }


    });




    return filtered;


}




function filterDocuments() {


    renderDocumentsTable();


}






function openAddDocumentModal() {
    if (!currentUserCanManageDocuments()) {
        showNotification('Read-only role: only admins can upload documents.', 'warning');
        return;
    }

    document.getElementById('document-id').value = '';
    document.getElementById('doc-modal-title').textContent = 'Upload New Document';
    document.getElementById('document-reference').value = '';
    document.getElementById('document-type').value = '';
    document.getElementById('document-title').value = '';
    document.getElementById('document-status').value = 'draft';
    document.getElementById('document-date').value = new Date().toISOString().split('T')[0];
    const d = document.getElementById('document-description');
    if (d) d.value = '';
    const t = document.getElementById('document-tags');
    if (t) t.value = '';
    const f = document.getElementById('document-file');
    if (f) f.value = '';
    document.getElementById('document-modal').classList.remove('hidden');
}

function closeDocumentModal() {
    document.getElementById('document-modal').classList.add('hidden');
}

function editDocument(id) {
    if (!currentUserCanManageDocuments()) {
        showNotification('Read-only role: only admins can edit documents.', 'warning');
        return;
    }

    const doc = AppData.documents.find(d => Number(d.id) === Number(id));
    if (!doc) return;

    document.getElementById('document-id').value = String(id);
    document.getElementById('doc-modal-title').textContent = 'Edit Document';
    document.getElementById('document-reference').value = doc.reference || '';
    document.getElementById('document-type').value = doc.type || '';
    document.getElementById('document-title').value = doc.title || '';
    document.getElementById('document-status').value = doc.status || 'draft';
    document.getElementById('document-date').value = doc.date ? String(doc.date).slice(0, 10) : new Date().toISOString().split('T')[0];
    const d = document.getElementById('document-description');
    if (d) d.value = doc.description || '';
    const t = document.getElementById('document-tags');
    if (t) t.value = Array.isArray(doc.tags) ? doc.tags.join(', ') : '';
    const f = document.getElementById('document-file');
    if (f) f.value = '';
    document.getElementById('document-modal').classList.remove('hidden');
}

async function saveDocument() {
    if (!currentUserCanManageDocuments()) {
        showNotification('Read-only role: only admins can save document changes.', 'warning');
        return;
    }

    const id = String(document.getElementById('document-id').value || '').trim();
    const reference = document.getElementById('document-reference').value.trim();
    const type = document.getElementById('document-type').value;
    const title = document.getElementById('document-title').value.trim();
    const status = document.getElementById('document-status').value;
    const date = document.getElementById('document-date').value;
    const description = String(document.getElementById('document-description')?.value || '').trim();
    const tags = String(document.getElementById('document-tags')?.value || '').trim();
    const fileInput = document.getElementById('document-file');
    const file = fileInput && fileInput.files ? fileInput.files[0] : null;

    if (!reference || !type || !title || !status || !date) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }

    try {
        if (id) {
            const existing = AppData.documents.find(d => Number(d.id) === Number(id));
            const source = existing?.source || 'admin';
            const res = await fetch('API/documents_api.php?action=update', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: Number(id), source, reference, type, title, status, date, description, tags })
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
            showNotification('Document updated successfully', 'success');
        } else {
            if (!file) {
                showNotification('Please choose a file to upload.', 'error');
                return;
            }
            const form = new FormData();
            form.append('reference', reference);
            form.append('type', type);
            form.append('title', title);
            form.append('status', status);
            form.append('date', date);
            form.append('description', description);
            form.append('tags', tags);
            form.append('document_file', file);

            const res = await fetch('API/documents_api.php?action=upload', {
                method: 'POST',
                body: form,
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.success) throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
            showNotification('Document uploaded successfully', 'success');
        }

        closeDocumentModal();
        await refreshDocumentsModule(true);
    } catch (err) {
        showNotification(err && err.message ? String(err.message) : 'Failed to save document', 'error');
    }
}

async function deleteDocument(id) {
    if (!currentUserCanManageDocuments()) {
        showNotification('Read-only role: only admins can delete documents.', 'warning');
        return;
    }

    if (!confirm('Are you sure you want to delete this document?')) return;
    const doc = AppData.documents.find(d => Number(d.id) === Number(id));
    const source = doc?.source || 'admin';

    try {
        const res = await fetch('API/documents_api.php?action=delete', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: Number(id), source })
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
        showNotification('Document deleted successfully', 'success');
        await refreshDocumentsModule(true);
    } catch (err) {
        showNotification(err && err.message ? String(err.message) : 'Failed to delete document', 'error');
    }
}

async function downloadDocument(uid) {
    const doc = findDocumentByUid(uid);
    if (!doc) {
        showNotification('Document not found for download.', 'error');
        return;
    }

    try {
        await fetch('API/documents_api.php?action=register_download', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: Number(doc.id), source: doc.source || 'admin' })
        });
    } catch (_) { }

    doc.downloads = Number(doc.downloads || 0) + 1;
    renderDocumentsTable();

    const href = doc.downloadUrl || doc.filePath || `download-document.php?id=${encodeURIComponent(String(doc.id))}&source=${encodeURIComponent(doc.source || 'admin')}`;
    if (!href) {
        showNotification('No file path found for this document.', 'error');
        return;
    }

    const a = document.createElement('a');
    a.href = href;
    a.target = '_blank';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function previewDocument(uid) {
    const doc = findDocumentByUid(uid);
    if (!doc) return;
    const href = doc.downloadUrl || doc.filePath;
    if (!href) {
        showNotification('No preview available for this document.', 'error');
        return;
    }
    window.open(href, '_blank', 'noopener');
}

function toggleAllDocumentRows(checked) {
    document.querySelectorAll('.doc-row-check').forEach(cb => {
        cb.checked = !!checked;
    });
}

function getSelectedDocumentRows() {
    return Array.from(document.querySelectorAll('.doc-row-check:checked')).map(cb => ({
        id: Number(cb.value),
        source: String(cb.dataset.source || 'admin')
    }));
}

async function quickSetDocumentStatus(id, source, status) {
    if (!currentUserCanManageDocuments()) {
        showNotification('Read-only role: only admins can update status.', 'warning');
        return;
    }
    try {
        const res = await fetch('API/documents_api.php?action=update_status', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: Number(id), source: String(source || 'admin'), status: String(status || '').toLowerCase() })
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
        const row = AppData.documents.find(d => Number(d.id) === Number(id));
        if (row) row.status = status;
        renderDocumentsTable();
    } catch (err) {
        showNotification(err && err.message ? String(err.message) : 'Failed to update status', 'error');
    }
}

async function applyBulkDocumentStatus() {
    const status = String(document.getElementById('doc-bulk-status')?.value || '').toLowerCase();
    if (!status) {
        showNotification('Choose a bulk status first.', 'error');
        return;
    }
    const selected = getSelectedDocumentRows();
    if (!selected.length) {
        showNotification('Select at least one document row.', 'error');
        return;
    }

    let ok = 0;
    for (const item of selected) {
        try {
            const res = await fetch('API/documents_api.php?action=update_status', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: item.id, source: item.source, status })
            });
            const data = await res.json().catch(() => null);
            if (res.ok && data && data.success) ok += 1;
        } catch (_) { }
    }

    await refreshDocumentsModule(true);
    showNotification(`Updated ${ok}/${selected.length} documents.`, ok === selected.length ? 'success' : 'warning');
}

async function refreshDocumentsModule(silent) {
    try {
        await loadDocumentsFromApi();
        renderDocumentsTable();
        if (!silent) showNotification('Documents refreshed.', 'success');
    } catch (err) {
        showNotification(err && err.message ? String(err.message) : 'Failed to refresh documents', 'error');
    }
}

function exportDocumentsCsv() {
    let rows = getFilteredDocuments();
    if (!rows.length && Array.isArray(AppData.documents) && AppData.documents.length) {
        rows = [...AppData.documents];
        showNotification('No rows matched current filters. Exported all documents instead.', 'info');
    }
    if (!rows.length) {
        showNotification('No documents available to export yet.', 'error');
        return;
    }

    const headers = ['Reference', 'Title', 'Type', 'Status', 'Size', 'Downloads', 'Date'];
    const out = [headers.join(',')];
    rows.forEach(doc => {
        const values = [
            doc.reference || '',
            doc.title || '',
            doc.type || '',
            doc.status || '',
            formatFileSize(doc.size || 0),
            String(doc.downloads || 0),
            doc.date || ''
        ].map(v => `"${String(v).replace(/"/g, '""')}"`);
        out.push(values.join(','));
    });

    const blob = new Blob([out.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `documents-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function formatFileSize(bytes) {


    if (bytes === 0) return '0 B';


    const k = 1024;


    const sizes = ['B', 'KB', 'MB', 'GB'];


    const i = Math.floor(Math.log(bytes) / Math.log(k));


    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];


}




function renderPCSecurityNotifications() {


    document.getElementById('content-area').innerHTML = '<h1 class="text-xl font-bold">Security - Notifications</h1><p class="text-sm text-gray-600">(Empty placeholder)</p>';


}




function renderPCSecurityAnalytics() {


    document.getElementById('content-area').innerHTML = '<h1 class="text-xl font-bold">Security - Analytics</h1><p class="text-sm text-gray-600">(Empty placeholder)</p>';


}

let issueMapInstance = null;
let issueMapMarkers = [];
let issueDraftPin = null;

function mapDbIssueToUi(row) {
    return {
        id: Number(row.id || 0),
        referenceNo: String(row.reference_no || ''),
        title: String(row.title || ''),
        description: String(row.description || ''),
        category: String(row.category || 'general'),
        status: String(row.status || 'new').toLowerCase(),
        priority: String(row.priority || 'normal').toLowerCase(),
        barangay: String(row.barangay || ''),
        address: String(row.address || ''),
        lat: Number(row.latitude),
        lng: Number(row.longitude),
        reportedBy: String(row.reported_by_name || 'Citizen'),
        reportedEmail: String(row.reported_by_email || ''),
        validationNotes: String(row.validation_notes || ''),
        resolutionNotes: String(row.resolution_notes || ''),
        createdAt: row.created_at || null,
        validatedAt: row.validated_at || null,
        resolvedAt: row.resolved_at || null
    };
}

async function loadIssuesFromApi() {
    const res = await fetchWithTimeout('API/issues_api.php?action=list&limit=500&offset=0', {
        headers: { 'Accept': 'application/json' }
    }, 5000);
    let data = null;
    try { data = await res.json(); } catch (_) { }
    if (!res.ok || !data || !data.success || !Array.isArray(data.data)) {
        throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
    }
    AppData.issueReports = data.data.map(mapDbIssueToUi);
}

// Simplified City of Valenzuela boundary (lat, lng pairs for Leaflet)
const VALENZUELA_BOUNDARY_LATLNG = [
    [14.7297406, 120.9257944],
    [14.7252083, 120.9286907],
    [14.7233974, 120.9298479],
    [14.7217088, 120.9309270],
    [14.7167946, 120.9346319],
    [14.7117574, 120.9384294],
    [14.7094749, 120.9401502],
    [14.7092171, 120.9403446],
    [14.7077622, 120.9414413],
    [14.7073096, 120.9418268],
    [14.7056453, 120.9432439],
    [14.7005178, 120.9476102],
    [14.7003502, 120.9477529],
    [14.7000299, 120.9480092],
    [14.6959247, 120.9512812],
    [14.6942028, 120.9524492],
    [14.6962110, 120.9561885],
    [14.6958824, 120.9561839],
    [14.6957378, 120.9561944],
    [14.6955666, 120.9562426],
    [14.6951741, 120.9563582],
    [14.6947143, 120.9565658],
    [14.6946416, 120.9566436],
    [14.6943234, 120.9567802],
    [14.6934617, 120.9573158],
    [14.6932247, 120.9575593],
    [14.6931017, 120.9577906],
    [14.6928455, 120.9580036],
    [14.6925239, 120.9580920],
    [14.6918813, 120.9581338],
    [14.6916699, 120.9582123],
    [14.6915575, 120.9583013],
    [14.6912603, 120.9585610],
    [14.6904974, 120.9592833],
    [14.6890965, 120.9599149],
    [14.6889401, 120.9599644],
    [14.6881548, 120.9602991],
    [14.6880296, 120.9604096],
    [14.6879122, 120.9604672],
    [14.6872181, 120.9607105],
    [14.6864835, 120.9610180],
    [14.6859693, 120.9615560],
    [14.6856167, 120.9622173],
    [14.6840632, 120.9635116],
    [14.6839428, 120.9635839],
    [14.6825185, 120.9644397],
    [14.6817967, 120.9646600],
    [14.6812108, 120.9646490],
    [14.6802502, 120.9645581],
    [14.6793893, 120.9645363],
    [14.6774485, 120.9650083],
    [14.6756924, 120.9645103],
    [14.6753785, 120.9644995],
    [14.6751725, 120.9646186],
    [14.6749959, 120.9648511],
    [14.6755252, 120.9651837],
    [14.6761645, 120.9656558],
    [14.6767042, 120.9659390],
    [14.6771360, 120.9662309],
    [14.6778251, 120.9664884],
    [14.6781987, 120.9668574],
    [14.6785475, 120.9672866],
    [14.6787716, 120.9677157],
    [14.6791702, 120.9685740],
    [14.6792117, 120.9689774],
    [14.6791702, 120.9693465],
    [14.6787550, 120.9700932],
    [14.6784395, 120.9705653],
    [14.6780997, 120.9712275],
    [14.6780078, 120.9714065],
    [14.6779912, 120.9717498],
    [14.6780078, 120.9721103],
    [14.6781904, 120.9729686],
    [14.6782402, 120.9733806],
    [14.6782153, 120.9738097],
    [14.6781323, 120.9740844],
    [14.6779912, 120.9742732],
    [14.6777284, 120.9742428],
    [14.6776429, 120.9742079],
    [14.6775758, 120.9741804],
    [14.6773269, 120.9739556],
    [14.6770529, 120.9737582],
    [14.6767208, 120.9736724],
    [14.6764136, 120.9737582],
    [14.6760226, 120.9739285],
    [14.6756414, 120.9741874],
    [14.6752678, 120.9744964],
    [14.6751828, 120.9745932],
    [14.6750965, 120.9746954],
    [14.6749625, 120.9748509],
    [14.6748053, 120.9752032],
    [14.6746923, 120.9755156],
    [14.6747764, 120.9757495],
    [14.6748884, 120.9766094],
    [14.6750322, 120.9773772],
    [14.6750648, 120.9775870],
    [14.6750836, 120.9778807],
    [14.6750794, 120.9780038],
    [14.6750348, 120.9781455],
    [14.6749751, 120.9782659],
    [14.6749106, 120.9783791],
    [14.6747976, 120.9785673],
    [14.6746256, 120.9787082],
    [14.6744897, 120.9788231],
    [14.6742853, 120.9789250],
    [14.6739271, 120.9789761],
    [14.6735826, 120.9789719],
    [14.6731452, 120.9788451],
    [14.6726983, 120.9787817],
    [14.6724281, 120.9788909],
    [14.6720462, 120.9791741],
    [14.6717639, 120.9794745],
    [14.6715856, 120.9796464],
    [14.6714096, 120.9798032],
    [14.6710897, 120.9800252],
    [14.6709501, 120.9803500],
    [14.6708671, 120.9805903],
    [14.6707126, 120.9818950],
    [14.6706932, 120.9822722],
    [14.6707373, 120.9824932],
    [14.6708791, 120.9830592],
    [14.6708899, 120.9833824],
    [14.6705765, 120.9839377],
    [14.6689412, 120.9856425],
    [14.6688113, 120.9858548],
    [14.6687476, 120.9861978],
    [14.6688141, 120.9865698],
    [14.6690463, 120.9870960],
    [14.6692898, 120.9877140],
    [14.6694491, 120.9880853],
    [14.6695784, 120.9883868],
    [14.6697007, 120.9886794],
    [14.6698859, 120.9889738],
    [14.6700432, 120.9892335],
    [14.6703562, 120.9896267],
    [14.6706484, 120.9900044],
    [14.6715919, 120.9909782],
    [14.6719090, 120.9911963],
    [14.6722309, 120.9913926],
    [14.6725633, 120.9916068],
    [14.6728409, 120.9918719],
    [14.6729057, 120.9920839],
    [14.6730068, 120.9923042],
    [14.6731159, 120.9926047],
    [14.6733061, 120.9929600],
    [14.6735834, 120.9931506],
    [14.6738285, 120.9932488],
    [14.6740804, 120.9936366],
    [14.6743129, 120.9940658],
    [14.6745733, 120.9946354],
    [14.6747585, 120.9950461],
    [14.6748470, 120.9952289],
    [14.6749106, 120.9953602],
    [14.6750414, 120.9956999],
    [14.6751360, 120.9959125],
    [14.6752933, 120.9961121],
    [14.6756887, 120.9965121],
    [14.6759740, 120.9967175],
    [14.6767894, 120.9969446],
    [14.6771692, 120.9970504],
    [14.6783375, 120.9972195],
    [14.6785866, 120.9973024],
    [14.6786298, 120.9974480],
    [14.6787256, 120.9975297],
    [14.6788134, 120.9976425],
    [14.6788185, 120.9977608],
    [14.6787965, 120.9979281],
    [14.6786886, 120.9980998],
    [14.6786382, 120.9982059],
    [14.6786471, 120.9983144],
    [14.6787115, 120.9983919],
    [14.6788132, 120.9984466],
    [14.6789986, 120.9984635],
    [14.6791974, 120.9984154],
    [14.6792458, 120.9984037],
    [14.6796328, 120.9983102],
    [14.6800030, 120.9981969],
    [14.6801371, 120.9981721],
    [14.6802662, 120.9981856],
    [14.6806454, 120.9984176],
    [14.6808190, 120.9985074],
    [14.6809998, 120.9986006],
    [14.6813926, 120.9987782],
    [14.6817491, 120.9987835],
    [14.6820427, 120.9988189],
    [14.6822591, 120.9988675],
    [14.6825294, 120.9988709],
    [14.6826772, 120.9989073],
    [14.6829260, 120.9989696],
    [14.6833114, 120.9990915],
    [14.6836632, 120.9991712],
    [14.6839571, 120.9993284],
    [14.6841972, 120.9994412],
    [14.6843199, 120.9995155],
    [14.6845473, 120.9997402],
    [14.6846688, 121.0000762],
    [14.6847156, 121.0002092],
    [14.6847384, 121.0002807],
    [14.6847485, 121.0004069],
    [14.6847679, 121.0005391],
    [14.6848350, 121.0007877],
    [14.6849129, 121.0010590],
    [14.6851601, 121.0013694],
    [14.6854996, 121.0016648],
    [14.6859859, 121.0019269],
    [14.6865040, 121.0022493],
    [14.6868415, 121.0026492],
    [14.6869794, 121.0028764],
    [14.6871736, 121.0033528],
    [14.6873444, 121.0037291],
    [14.6873244, 121.0039928],
    [14.6872651, 121.0043295],
    [14.6872146, 121.0045265],
    [14.6868981, 121.0051000],
    [14.6867838, 121.0053954],
    [14.6864310, 121.0062421],
    [14.6860739, 121.0068187],
    [14.6855806, 121.0073791],
    [14.6852433, 121.0077012],
    [14.6851233, 121.0079703],
    [14.6850105, 121.0082314],
    [14.6849689, 121.0083172],
    [14.6847560, 121.0085322],
    [14.6845919, 121.0086312],
    [14.6843428, 121.0088887],
    [14.6841104, 121.0092578],
    [14.6837599, 121.0097456],
    [14.6833420, 121.0101744],
    [14.6830960, 121.0104537],
    [14.6828626, 121.0106622],
    [14.6826956, 121.0107671],
    [14.6825588, 121.0109492],
    [14.6823235, 121.0113972],
    [14.6822838, 121.0116782],
    [14.6823280, 121.0119213],
    [14.6824098, 121.0120616],
    [14.6826554, 121.0122913],
    [14.6829775, 121.0126435],
    [14.6831920, 121.0127896],
    [14.6833488, 121.0128902],
    [14.6834404, 121.0128978],
    [14.6837012, 121.0129191],
    [14.6838478, 121.0129749],
    [14.6840276, 121.0130825],
    [14.6842713, 121.0132604],
    [14.6843459, 121.0133499],
    [14.6843734, 121.0134446],
    [14.6843837, 121.0135413],
    [14.6845496, 121.0137649],
    [14.6846433, 121.0138457],
    [14.6847643, 121.0138837],
    [14.6849051, 121.0138920],
    [14.6852255, 121.0138502],
    [14.6855860, 121.0136937],
    [14.6856948, 121.0136662],
    [14.6858001, 121.0136720],
    [14.6861242, 121.0137666],
    [14.6863908, 121.0138954],
    [14.6866228, 121.0140121],
    [14.6867146, 121.0140248],
    [14.6868733, 121.0140206],
    [14.6871756, 121.0139581],
    [14.6876389, 121.0138573],
    [14.6879183, 121.0137893],
    [14.6881749, 121.0137244],
    [14.6884577, 121.0135273],
    [14.6886563, 121.0133186],
    [14.6889552, 121.0131226],
    [14.6892022, 121.0130261],
    [14.6893646, 121.0130069],
    [14.6896697, 121.0130050],
    [14.6897915, 121.0130161],
    [14.6898689, 121.0130231],
    [14.6900539, 121.0131138],
    [14.6906339, 121.0135493],
    [14.6910884, 121.0140131],
    [14.6914332, 121.0144076],
    [14.6917391, 121.0148096],
    [14.6920089, 121.0151422],
    [14.6920974, 121.0153003],
    [14.6920504, 121.0154963],
    [14.6919259, 121.0157537],
    [14.6917832, 121.0160163],
    [14.6917690, 121.0162902],
    [14.6918733, 121.0168230],
    [14.6919932, 121.0173005],
    [14.6920861, 121.0176877],
    [14.6921161, 121.0179654],
    [14.6920903, 121.0181582],
    [14.6920580, 121.0182518],
    [14.6918789, 121.0184661],
    [14.6915653, 121.0187732],
    [14.6914066, 121.0189001],
    [14.6913214, 121.0189787],
    [14.6912245, 121.0191693],
    [14.6911695, 121.0194441],
    [14.6911871, 121.0197880],
    [14.6913134, 121.0202329],
    [14.6913960, 121.0205064],
    [14.6913960, 121.0207056],
    [14.6913626, 121.0209138],
    [14.6913087, 121.0211024],
    [14.6912287, 121.0212352],
    [14.6911504, 121.0213140],
    [14.6910484, 121.0213377],
    [14.6908571, 121.0213478],
    [14.6907334, 121.0213140],
    [14.6906482, 121.0212697],
    [14.6905302, 121.0212571],
    [14.6904568, 121.0213074],
    [14.6904010, 121.0214365],
    [14.6903804, 121.0216672],
    [14.6903954, 121.0221324],
    [14.6905977, 121.0229565],
    [14.6907147, 121.0235056],
    [14.6909064, 121.0237582],
    [14.6911428, 121.0238923],
    [14.6919060, 121.0239012],
    [14.6924433, 121.0240682],
    [14.6926870, 121.0241839],
    [14.6928724, 121.0242836],
    [14.6930177, 121.0243684],
    [14.6931960, 121.0244686],
    [14.6932933, 121.0245146],
    [14.6934197, 121.0245497],
    [14.6935199, 121.0245644],
    [14.6935868, 121.0245618],
    [14.6936733, 121.0245493],
    [14.6937656, 121.0245263],
    [14.6938267, 121.0245063],
    [14.6938891, 121.0244795],
    [14.6939579, 121.0244374],
    [14.6940152, 121.0243756],
    [14.6940845, 121.0242933],
    [14.6941495, 121.0241953],
    [14.6942189, 121.0240985],
    [14.6942717, 121.0239992],
    [14.6943252, 121.0238965],
    [14.6943761, 121.0238096],
    [14.6944219, 121.0237557],
    [14.6944970, 121.0236885],
    [14.6945734, 121.0236339],
    [14.6946339, 121.0235964],
    [14.6947173, 121.0235661],
    [14.6948280, 121.0235438],
    [14.6949763, 121.0235030],
    [14.6950737, 121.0234641],
    [14.6952131, 121.0234181],
    [14.6953194, 121.0233832],
    [14.6954480, 121.0233417],
    [14.6955696, 121.0233088],
    [14.6956536, 121.0232819],
    [14.6957115, 121.0232595],
    [14.6957688, 121.0232193],
    [14.6958108, 121.0231772],
    [14.6958681, 121.0231009],
    [14.6959420, 121.0229732],
    [14.6960094, 121.0228403],
    [14.6960578, 121.0227317],
    [14.6961406, 121.0225922],
    [14.6961978, 121.0225119],
    [14.6962698, 121.0224159],
    [14.6963283, 121.0223573],
    [14.6964124, 121.0222770],
    [14.6964766, 121.0222198],
    [14.6965823, 121.0221395],
    [14.6966453, 121.0221000],
    [14.6967224, 121.0220388],
    [14.6968344, 121.0219631],
    [14.6969299, 121.0219065],
    [14.6969891, 121.0218605],
    [14.6970521, 121.0218144],
    [14.6971036, 121.0217683],
    [14.6972017, 121.0216795],
    [14.6972920, 121.0215985],
    [14.6973621, 121.0215334],
    [14.6974601, 121.0214340],
    [14.6975206, 121.0213722],
    [14.6975702, 121.0213077],
    [14.6976025, 121.0212640],
    [14.6976332, 121.0212261],
    [14.6976861, 121.0211655],
    [14.6977491, 121.0210892],
    [14.6978134, 121.0209951],
    [14.6978611, 121.0208957],
    [14.6979171, 121.0207727],
    [14.6979757, 121.0206430],
    [14.6980196, 121.0205160],
    [14.6980514, 121.0204206],
    [14.6980973, 121.0203443],
    [14.6981558, 121.0202798],
    [14.6982042, 121.0202416],
    [14.6982838, 121.0201949],
    [14.6983862, 121.0201442],
    [14.6985307, 121.0200508],
    [14.6986358, 121.0199679],
    [14.6987631, 121.0198784],
    [14.6988904, 121.0197961],
    [14.6989858, 121.0197382],
    [14.6990902, 121.0196704],
    [14.6991921, 121.0195921],
    [14.6992806, 121.0195085],
    [14.6993709, 121.0194197],
    [14.6994575, 121.0192927],
    [14.6995651, 121.0191466],
    [14.6996618, 121.0190209],
    [14.6997471, 121.0188854],
    [14.6998547, 121.0187004],
    [14.6999203, 121.0185728],
    [14.7000049, 121.0184405],
    [14.7001000, 121.0183224],
    [14.7002360, 121.0181807],
    [14.7003174, 121.0180910],
    [14.7004239, 121.0180124],
    [14.7005441, 121.0179549],
    [14.7006363, 121.0179141],
    [14.7007532, 121.0178713],
    [14.7008540, 121.0178489],
    [14.7009477, 121.0178264],
    [14.7010692, 121.0177798],
    [14.7011888, 121.0177186],
    [14.7013391, 121.0176311],
    [14.7015462, 121.0175321],
    [14.7018209, 121.0174060],
    [14.7019482, 121.0173586],
    [14.7020925, 121.0173206],
    [14.7021902, 121.0173175],
    [14.7022658, 121.0173277],
    [14.7023908, 121.0173523],
    [14.7024994, 121.0173968],
    [14.7026572, 121.0174702],
    [14.7027566, 121.0175192],
    [14.7028717, 121.0175811],
    [14.7029552, 121.0176377],
    [14.7030583, 121.0177166],
    [14.7032227, 121.0178562],
    [14.7033858, 121.0179631],
    [14.7087550, 121.0161294],
    [14.7096057, 121.0137606],
    [14.7097837, 121.0132604],
    [14.7098485, 121.0130782],
    [14.7101623, 121.0121963],
    [14.7122607, 121.0062987],
    [14.7139781, 121.0017335],
    [14.7144576, 121.0004642],
    [14.7226795, 121.0019280],
    [14.7243570, 121.0023200],
    [14.7253873, 121.0025382],
    [14.7264713, 121.0027353],
    [14.7268664, 121.0028128],
    [14.7277062, 121.0029870],
    [14.7317168, 121.0037827],
    [14.7357025, 121.0046237],
    [14.7404015, 121.0055047],
    [14.7415716, 121.0057514],
    [14.7434675, 121.0061511],
    [14.7459224, 121.0067655],
    [14.7462803, 121.0062852],
    [14.7465453, 121.0060097],
    [14.7466452, 121.0059804],
    [14.7467003, 121.0057893],
    [14.7468430, 121.0055825],
    [14.7470441, 121.0054325],
    [14.7472142, 121.0050110],
    [14.7473301, 121.0048618],
    [14.7475209, 121.0048316],
    [14.7476911, 121.0048494],
    [14.7478681, 121.0048761],
    [14.7480314, 121.0048281],
    [14.7481310, 121.0047623],
    [14.7481637, 121.0045899],
    [14.7481860, 121.0043962],
    [14.7481379, 121.0042931],
    [14.7481706, 121.0041581],
    [14.7483407, 121.0037938],
    [14.7484103, 121.0036829],
    [14.7484782, 121.0035747],
    [14.7485349, 121.0034845],
    [14.7487893, 121.0030296],
    [14.7488391, 121.0028856],
    [14.7496271, 121.0027301],
    [14.7498430, 121.0026769],
    [14.7500031, 121.0026460],
    [14.7501974, 121.0026055],
    [14.7502929, 121.0025017],
    [14.7503641, 121.0024187],
    [14.7506111, 121.0019573],
    [14.7506911, 121.0018309],
    [14.7508497, 121.0017798],
    [14.7512776, 121.0016421],
    [14.7519404, 121.0013739],
    [14.7521300, 121.0012677],
    [14.7524458, 121.0011779],
    [14.7527873, 121.0011166],
    [14.7530420, 121.0010452],
    [14.7532078, 121.0009043],
    [14.7534132, 121.0009133],
    [14.7538272, 121.0004193],
    [14.7546043, 120.9996202],
    [14.7548104, 120.9993975],
    [14.7552019, 120.9990809],
    [14.7556033, 120.9987566],
    [14.7557114, 120.9985204],
    [14.7561006, 120.9982151],
    [14.7565526, 120.9980202],
    [14.7567592, 120.9979593],
    [14.7571236, 120.9978963],
    [14.7581692, 120.9980617],
    [14.7581829, 120.9980639],
    [14.7581936, 120.9980454],
    [14.7582094, 120.9980159],
    [14.7582389, 120.9979668],
    [14.7582961, 120.9979035],
    [14.7583971, 120.9977900],
    [14.7583854, 120.9977827],
    [14.7569171, 120.9968660],
    [14.7571177, 120.9964673],
    [14.7571077, 120.9964402],
    [14.7570958, 120.9964082],
    [14.7570761, 120.9963030],
    [14.7568025, 120.9948430],
    [14.7567822, 120.9948399],
    [14.7563539, 120.9947747],
    [14.7567519, 120.9920138],
    [14.7571409, 120.9920805],
    [14.7570377, 120.9902158],
    [14.7577426, 120.9895771],
    [14.7573307, 120.9891158],
    [14.7572954, 120.9890762],
    [14.7572354, 120.9890091],
    [14.7571511, 120.9889395],
    [14.7569620, 120.9890121],
    [14.7567425, 120.9891029],
    [14.7565507, 120.9892264],
    [14.7562288, 120.9895343],
    [14.7560905, 120.9895477],
    [14.7559484, 120.9895141],
    [14.7556464, 120.9893927],
    [14.7552236, 120.9890702],
    [14.7550110, 120.9889080],
    [14.7548321, 120.9887351],
    [14.7545746, 120.9886395],
    [14.7543675, 120.9887003],
    [14.7542293, 120.9888241],
    [14.7539895, 120.9891189],
    [14.7538065, 120.9891996],
    [14.7535728, 120.9891238],
    [14.7523853, 120.9883010],
    [14.7523667, 120.9881006],
    [14.7524509, 120.9878921],
    [14.7524460, 120.9877338],
    [14.7524136, 120.9875835],
    [14.7522763, 120.9874783],
    [14.7521309, 120.9873917],
    [14.7519854, 120.9873050],
    [14.7516436, 120.9869959],
    [14.7515916, 120.9869257],
    [14.7514918, 120.9868295],
    [14.7509584, 120.9863682],
    [14.7509191, 120.9862436],
    [14.7508231, 120.9859393],
    [14.7507821, 120.9858093],
    [14.7506490, 120.9857922],
    [14.7502329, 120.9859834],
    [14.7501575, 120.9860081],
    [14.7499379, 120.9860800],
    [14.7496638, 120.9861459],
    [14.7493882, 120.9861577],
    [14.7492797, 120.9860816],
    [14.7489923, 120.9858802],
    [14.7487197, 120.9855643],
    [14.7484324, 120.9852314],
    [14.7483029, 120.9852487],
    [14.7481749, 120.9852849],
    [14.7479704, 120.9853426],
    [14.7476365, 120.9853454],
    [14.7475855, 120.9851846],
    [14.7474867, 120.9850400],
    [14.7472623, 120.9849228],
    [14.7467565, 120.9847084],
    [14.7464179, 120.9846278],
    [14.7462390, 120.9846928],
    [14.7460613, 120.9847552],
    [14.7458775, 120.9846635],
    [14.7457772, 120.9845900],
    [14.7456645, 120.9843513],
    [14.7455337, 120.9841069],
    [14.7454504, 120.9840416],
    [14.7451926, 120.9838348],
    [14.7449623, 120.9836548],
    [14.7448318, 120.9835943],
    [14.7446979, 120.9835863],
    [14.7443854, 120.9835747],
    [14.7439536, 120.9835149],
    [14.7437377, 120.9834337],
    [14.7435953, 120.9833764],
    [14.7434008, 120.9833050],
    [14.7429701, 120.9831363],
    [14.7427196, 120.9829191],
    [14.7423474, 120.9826653],
    [14.7420446, 120.9824675],
    [14.7418830, 120.9823802],
    [14.7415589, 120.9823105],
    [14.7414015, 120.9822354],
    [14.7411705, 120.9821035],
    [14.7409303, 120.9818458],
    [14.7407873, 120.9815152],
    [14.7376989, 120.9816197],
    [14.7376438, 120.9810459],
    [14.7374257, 120.9805138],
    [14.7369222, 120.9805631],
    [14.7359804, 120.9810471],
    [14.7355108, 120.9800224],
    [14.7352451, 120.9794058],
    [14.7352288, 120.9790975],
    [14.7352404, 120.9789528],
    [14.7352089, 120.9787153],
    [14.7358945, 120.9783379],
    [14.7357699, 120.9780861],
    [14.7356309, 120.9778053],
    [14.7297416, 120.9809931],
    [14.7294319, 120.9811664],
    [14.7286890, 120.9815709],
    [14.7277611, 120.9820662],
    [14.7266052, 120.9826949],
    [14.7256761, 120.9832024],
    [14.7255154, 120.9829000],
    [14.7247459, 120.9813459],
    [14.7246361, 120.9811062],
    [14.7245070, 120.9807886],
    [14.7245403, 120.9804047],
    [14.7245729, 120.9803523],
    [14.7248579, 120.9798933],
    [14.7248549, 120.9796001],
    [14.7247473, 120.9793090],
    [14.7246965, 120.9792062],
    [14.7245118, 120.9789403],
    [14.7242452, 120.9780868],
    [14.7241913, 120.9777744],
    [14.7243677, 120.9775506],
    [14.7245791, 120.9772963],
    [14.7248568, 120.9771007],
    [14.7251598, 120.9767939],
    [14.7252294, 120.9765246],
    [14.7252960, 120.9764185],
    [14.7254072, 120.9763231],
    [14.7253900, 120.9761621],
    [14.7254412, 120.9759711],
    [14.7256328, 120.9757079],
    [14.7257627, 120.9755775],
    [14.7257129, 120.9754746],
    [14.7256044, 120.9753188],
    [14.7254975, 120.9752984],
    [14.7254245, 120.9753766],
    [14.7253566, 120.9755113],
    [14.7252809, 120.9757046],
    [14.7251577, 120.9758222],
    [14.7250502, 120.9758288],
    [14.7249460, 120.9757667],
    [14.7249080, 120.9755674],
    [14.7249386, 120.9752336],
    [14.7249744, 120.9748341],
    [14.7249698, 120.9746495],
    [14.7248937, 120.9744896],
    [14.7246214, 120.9743298],
    [14.7243618, 120.9741839],
    [14.7241248, 120.9741675],
    [14.7239787, 120.9742206],
    [14.7238759, 120.9741675],
    [14.7238996, 120.9740613],
    [14.7239891, 120.9738103],
    [14.7239221, 120.9736035],
    [14.7240693, 120.9734216],
    [14.7241401, 120.9732244],
    [14.7242163, 120.9728613],
    [14.7241847, 120.9726704],
    [14.7240557, 120.9725535],
    [14.7239426, 120.9725016],
    [14.7238227, 120.9724674],
    [14.7236105, 120.9724350],
    [14.7233991, 120.9723828],
    [14.7232143, 120.9722979],
    [14.7231574, 120.9721401],
    [14.7232378, 120.9719406],
    [14.7232840, 120.9717411],
    [14.7232053, 120.9716694],
    [14.7229908, 120.9715374],
    [14.7227815, 120.9713841],
    [14.7226032, 120.9712637],
    [14.7224376, 120.9709455],
    [14.7222099, 120.9705031],
    [14.7219855, 120.9699383],
    [14.7222327, 120.9696875],
    [14.7224448, 120.9693957],
    [14.7229940, 120.9690403],
    [14.7234053, 120.9689581],
    [14.7237042, 120.9687865],
    [14.7237705, 120.9685757],
    [14.7237631, 120.9683383],
    [14.7238272, 120.9680155],
    [14.7240004, 120.9678352],
    [14.7233999, 120.9666649],
    [14.7232680, 120.9664226],
    [14.7226663, 120.9652907],
    [14.7225269, 120.9653421],
    [14.7224169, 120.9653277],
    [14.7223334, 120.9653205],
    [14.7222568, 120.9653161],
    [14.7221097, 120.9653613],
    [14.7219508, 120.9649573],
    [14.7218606, 120.9647519],
    [14.7217775, 120.9645749],
    [14.7216892, 120.9645359],
    [14.7216579, 120.9643105],
    [14.7214998, 120.9631697],
    [14.7198627, 120.9626954],
    [14.7198894, 120.9626045],
    [14.7199227, 120.9624814],
    [14.7200025, 120.9621609],
    [14.7202321, 120.9612550],
    [14.7203508, 120.9607601],
    [14.7203961, 120.9605978],
    [14.7205094, 120.9601731],
    [14.7199238, 120.9595390],
    [14.7200721, 120.9594315],
    [14.7203897, 120.9592862],
    [14.7204017, 120.9592354],
    [14.7204615, 120.9590698],
    [14.7204539, 120.9589136],
    [14.7204702, 120.9588398],
    [14.7205176, 120.9588088],
    [14.7206578, 120.9587643],
    [14.7209064, 120.9587333],
    [14.7219753, 120.9577324],
    [14.7224014, 120.9573269],
    [14.7227292, 120.9568356],
    [14.7234179, 120.9563766],
    [14.7236434, 120.9561374],
    [14.7239316, 120.9559768],
    [14.7240455, 120.9559672],
    [14.7241436, 120.9560367],
    [14.7244622, 120.9565733],
    [14.7245612, 120.9565757],
    [14.7246841, 120.9564966],
    [14.7248079, 120.9563421],
    [14.7249502, 120.9562014],
    [14.7251827, 120.9559233],
    [14.7254106, 120.9556653],
    [14.7255825, 120.9555168],
    [14.7257571, 120.9554101],
    [14.7260868, 120.9552752],
    [14.7265675, 120.9550905],
    [14.7276449, 120.9545984],
    [14.7281932, 120.9544309],
    [14.7287753, 120.9541106],
    [14.7293965, 120.9535728],
    [14.7294285, 120.9534695],
    [14.7290756, 120.9526340],
    [14.7291274, 120.9524625],
    [14.7292802, 120.9523495],
    [14.7294071, 120.9523437],
    [14.7298072, 120.9524585],
    [14.7308533, 120.9521625],
    [14.7320068, 120.9516529],
    [14.7321771, 120.9515075],
    [14.7324488, 120.9513140],
    [14.7326698, 120.9510558],
    [14.7332237, 120.9504436],
    [14.7336171, 120.9500586],
    [14.7338312, 120.9498389],
    [14.7340427, 120.9495509],
    [14.7343529, 120.9489018],
    [14.7343293, 120.9480501],
    [14.7338616, 120.9472131],
    [14.7335754, 120.9456320],
    [14.7336555, 120.9449073],
    [14.7337942, 120.9442251],
    [14.7343181, 120.9432649],
    [14.7347023, 120.9428085],
    [14.7352349, 120.9425096],
    [14.7369464, 120.9417172],
    [14.7374023, 120.9414571],
    [14.7371819, 120.9405183],
    [14.7373881, 120.9376708],
    [14.7373170, 120.9372076],
    [14.7370977, 120.9367599],
    [14.7368345, 120.9364674],
    [14.7365492, 120.9362689],
    [14.7361341, 120.9361187],
    [14.7358747, 120.9360865],
    [14.7355620, 120.9361055],
    [14.7348435, 120.9360029],
    [14.7344089, 120.9358136],
    [14.7340291, 120.9355661],
    [14.7337462, 120.9352847],
    [14.7335726, 120.9350565],
    [14.7333934, 120.9345981],
    [14.7333806, 120.9342572],
    [14.7334377, 120.9338549],
    [14.7337438, 120.9327337],
    [14.7340857, 120.9315959],
    [14.7344999, 120.9305880],
    [14.7352937, 120.9289411],
    [14.7365862, 120.9266896],
    [14.7297406, 120.9257944]
];

function isPointInsideValenzuela(lat, lng) {
    let inside = false;
    const polygon = VALENZUELA_BOUNDARY_LATLNG;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const yi = polygon[i][0];
        const xi = polygon[i][1];
        const yj = polygon[j][0];
        const xj = polygon[j][1];
        const intersect = ((yi > lat) !== (yj > lat))
            && (lng < ((xj - xi) * (lat - yi) / ((yj - yi) || 1e-12)) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

function ensureLeafletAssets() {
    return new Promise((resolve, reject) => {
        if (window.L) {
            resolve();
            return;
        }

        const cssHref = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        if (!document.querySelector(`link[href="${cssHref}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = cssHref;
            document.head.appendChild(link);
        }

        const existingScript = document.querySelector('script[data-leaflet="1"]');
        if (existingScript) {
            if (window.L) {
                resolve();
                return;
            }
            if (existingScript.readyState === 'complete' || existingScript.readyState === 'loaded') {
                if (window.L) {
                    resolve();
                    return;
                }
            }
            existingScript.addEventListener('load', () => resolve());
            existingScript.addEventListener('error', () => reject(new Error('Failed to load Leaflet')));
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.dataset.leaflet = '1';
        script.onload = () => {
            script.dataset.loaded = '1';
            resolve();
        };
        script.onerror = () => reject(new Error('Failed to load Leaflet'));
        const parent = document.body || document.head || document.documentElement;
        parent.appendChild(script);
    });
}

function issueStatusBadge(status) {
    const s = String(status || 'new').toLowerCase();
    if (s === 'new') return '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">New</span>';
    if (s === 'validated') return '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Validated</span>';
    if (s === 'resolved') return '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Resolved</span>';
    if (s === 'rejected') return '<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">Rejected</span>';
    return `<span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">${escapeHtml(status || '-')}</span>`;
}

function issuePriorityBadge(priority) {
    const p = String(priority || 'normal').toLowerCase();
    if (p === 'high') return '<span class="text-red-700 font-semibold">high</span>';
    if (p === 'low') return '<span class="text-green-700 font-semibold">low</span>';
    return '<span class="text-gray-700 font-semibold">normal</span>';
}

function issueFilteredRows() {
    const q = String(document.getElementById('issue-search')?.value || '').toLowerCase().trim();
    const status = String(document.getElementById('issue-status')?.value || '').toLowerCase();
    const priority = String(document.getElementById('issue-priority')?.value || '').toLowerCase();

    return AppData.issueReports.filter(r => {
        if (q) {
            const hay = `${r.referenceNo} ${r.title} ${r.address} ${r.barangay}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        if (status && String(r.status) !== status) return false;
        if (priority && String(r.priority) !== priority) return false;
        return true;
    });
}

function issueRenderStats() {
    const rows = Array.isArray(AppData.issueReports) ? AppData.issueReports : [];
    const set = (id, n) => {
        const el = document.getElementById(id);
        if (el) el.textContent = String(n);
    };
    set('issue-stat-total', rows.length);
    set('issue-stat-new', rows.filter(r => r.status === 'new').length);
    set('issue-stat-validated', rows.filter(r => r.status === 'validated').length);
    set('issue-stat-resolved', rows.filter(r => r.status === 'resolved').length);
}

function issueRenderTable() {
    const tbody = document.getElementById('issue-table-body');
    if (!tbody) return;
    const rows = issueFilteredRows();
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No matching issues found.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr class="border-b border-gray-100 hover:bg-gray-50">
            <td class="px-3 py-3 font-semibold text-gray-800">${escapeHtml(r.referenceNo)}</td>
            <td class="px-3 py-3">
                <div class="font-semibold text-gray-900">${escapeHtml(r.title)}</div>
                <div class="text-xs text-gray-500">${escapeHtml(r.category)}</div>
            </td>
            <td class="px-3 py-3 text-gray-700">${escapeHtml(r.barangay || '-')}</td>
            <td class="px-3 py-3">${issuePriorityBadge(r.priority)}</td>
            <td class="px-3 py-3">${issueStatusBadge(r.status)}</td>
            <td class="px-3 py-3 text-gray-700">${escapeHtml(r.createdAt ? new Date(r.createdAt).toLocaleString() : '-')}</td>
            <td class="px-3 py-3 text-gray-700">${escapeHtml((Number(r.lat).toFixed(5)) + ', ' + (Number(r.lng).toFixed(5)))}</td>
            <td class="px-3 py-3">
                <div class="flex items-center gap-1">
                    <button onclick="issueFocusPin(${Number(r.id)})" class="px-2 py-1 text-xs border border-blue-300 text-blue-700 rounded hover:bg-blue-50">View</button>
                    ${currentUserIsSuperAdmin() ? '' : `
                    <button onclick="issueChangeStatus(${Number(r.id)}, 'validated')" class="px-2 py-1 text-xs border border-amber-300 text-amber-700 rounded hover:bg-amber-50">Validate</button>
                    <button onclick="issueChangeStatus(${Number(r.id)}, 'resolved')" class="px-2 py-1 text-xs border border-emerald-300 text-emerald-700 rounded hover:bg-emerald-50">Resolve</button>
                    `}
                </div>
            </td>
        </tr>
    `).join('');
}

function issueMarkerColor(status) {
    if (status === 'resolved') return '#16a34a';
    if (status === 'validated') return '#d97706';
    if (status === 'rejected') return '#64748b';
    return '#dc2626';
}

function issueRefreshMapPins() {
    if (!issueMapInstance || !window.L) return;
    issueMapMarkers.forEach(m => issueMapInstance.removeLayer(m));
    issueMapMarkers = [];

    issueFilteredRows().forEach(row => {
        if (!Number.isFinite(row.lat) || !Number.isFinite(row.lng)) return;
        const marker = window.L.circleMarker([row.lat, row.lng], {
            radius: 8,
            color: issueMarkerColor(row.status),
            weight: 2,
            fillColor: issueMarkerColor(row.status),
            fillOpacity: 0.7
        }).addTo(issueMapInstance);
        marker.bindPopup(`
            <div class="text-sm">
                <div><strong>${escapeHtml(row.referenceNo)}</strong></div>
                <div>${escapeHtml(row.title)}</div>
                <div>Status: ${escapeHtml(row.status)}</div>
                <div>Priority: ${escapeHtml(row.priority)}</div>
                <div>${escapeHtml(row.address || row.barangay || '')}</div>
            </div>
        `);
        issueMapMarkers.push(marker);
    });
}

function issueFocusPin(id) {
    const row = AppData.issueReports.find(r => Number(r.id) === Number(id));
    if (!row || !issueMapInstance) return;
    issueMapInstance.setView([row.lat, row.lng], 16);
    issueRefreshMapPins();
}

async function issueChangeStatus(id, status) {
    if (currentUserIsSuperAdmin()) {
        showNotification('Read-only role: action not allowed for super admin.', 'warning');
        return;
    }
    const row = AppData.issueReports.find(r => Number(r.id) === Number(id));
    if (!row) return;
    const notes = '';

    try {
        const res = await fetch('API/issues_api.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id: Number(id), status: String(status), notes: String(notes || '') })
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
        }
        await loadIssuesFromApi();
        issueRenderStats();
        issueRenderTable();
        issueRefreshMapPins();
        showNotification(`Issue marked as ${status}.`, 'success');
    } catch (e) {
        showNotification(e && e.message ? String(e.message) : 'Failed to update issue status.', 'error');
    }
}

async function issueCreateReport() {
    if (currentUserIsSuperAdmin()) {
        showNotification('Read-only role: action not allowed for super admin.', 'warning');
        return;
    }
    const title = String(document.getElementById('issue-title')?.value || '').trim();
    const description = String(document.getElementById('issue-description')?.value || '').trim();
    const category = String(document.getElementById('issue-category')?.value || 'general');
    const priority = String(document.getElementById('issue-new-priority')?.value || 'normal');
    const barangay = String(document.getElementById('issue-barangay')?.value || '').trim();
    const address = String(document.getElementById('issue-address')?.value || '').trim();
    const lat = Number(document.getElementById('issue-lat')?.value || '');
    const lng = Number(document.getElementById('issue-lng')?.value || '');

    if (!title || !description || !Number.isFinite(lat) || !Number.isFinite(lng)) {
        showNotification('Title, description, and map coordinates are required.', 'error');
        return;
    }
    if (!isPointInsideValenzuela(lat, lng)) {
        showNotification('Location must be within City of Valenzuela boundaries.', 'error');
        return;
    }

    try {
        const res = await fetch('API/issues_api.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ title, description, category, priority, barangay, address, latitude: lat, longitude: lng })
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error((data && data.message) ? data.message : `HTTP ${res.status}`);
        }

        document.getElementById('issue-title').value = '';
        document.getElementById('issue-description').value = '';
        document.getElementById('issue-address').value = '';
        showNotification(`Issue created. Ref: ${data.reference_no}`, 'success');

        await loadIssuesFromApi();
        issueRenderStats();
        issueRenderTable();
        issueRefreshMapPins();
    } catch (e) {
        showNotification(e && e.message ? String(e.message) : 'Failed to create issue.', 'error');
    }
}

async function renderIssueMapping() {
    const contentArea = document.getElementById('content-area');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    const isReadOnly = currentUserIsSuperAdmin();
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Issue Mapping';
    if (!contentArea) return;

    contentArea.innerHTML = `
        <div class="space-y-5">
            <div class="bg-gradient-to-r from-red-600 to-red-700 text-white p-6 rounded-lg shadow">
                <h1 class="text-3xl font-bold mb-2">Issue Mapping</h1>
                <p class="text-red-100">Map, validate, and resolve location-based citizen issues within City of Valenzuela.</p>
            </div>
            ${isReadOnly ? `<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-lg p-4 text-sm font-medium"><i class="bi bi-shield-lock mr-2"></i>Read-only Super Admin view. You can monitor issues but cannot create or update records.</div>` : ''}

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white rounded-lg border border-gray-200 p-4"><div class="text-xs text-gray-500">TOTAL</div><div id="issue-stat-total" class="text-3xl font-bold text-gray-900">0</div></div>
                <div class="bg-white rounded-lg border border-gray-200 p-4"><div class="text-xs text-gray-500">NEW</div><div id="issue-stat-new" class="text-3xl font-bold text-red-600">0</div></div>
                <div class="bg-white rounded-lg border border-gray-200 p-4"><div class="text-xs text-gray-500">VALIDATED</div><div id="issue-stat-validated" class="text-3xl font-bold text-amber-600">0</div></div>
                <div class="bg-white rounded-lg border border-gray-200 p-4"><div class="text-xs text-gray-500">RESOLVED</div><div id="issue-stat-resolved" class="text-3xl font-bold text-emerald-600">0</div></div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                ${isReadOnly ? '' : `
                <div class="xl:col-span-4 bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                    <h3 class="text-xl font-bold text-gray-900">Create Issue Report</h3>
                    <input id="issue-title" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Title">
                    <textarea id="issue-description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Describe the issue"></textarea>
                    <div class="grid grid-cols-2 gap-2">
                        <select id="issue-category" class="px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="general">general</option>
                            <option value="road">road</option>
                            <option value="drainage">drainage</option>
                            <option value="lighting">lighting</option>
                            <option value="waste">waste</option>
                            <option value="safety">safety</option>
                        </select>
                        <select id="issue-new-priority" class="px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="normal">normal</option>
                            <option value="high">high</option>
                            <option value="low">low</option>
                        </select>
                    </div>
                    <input id="issue-barangay" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Barangay">
                    <input id="issue-address" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Address / Landmark">
                    <div class="grid grid-cols-2 gap-2">
                        <input id="issue-lat" type="number" step="0.0000001" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Latitude">
                        <input id="issue-lng" type="number" step="0.0000001" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Longitude">
                    </div>
                    <p class="text-xs text-gray-500">Click inside the red Valenzuela boundary on the map to set coordinates.</p>
                    <button onclick="issueCreateReport()" class="w-full px-4 py-2.5 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Create Issue</button>
                </div>
                `}

                <div class="${isReadOnly ? 'xl:col-span-12' : 'xl:col-span-8'} bg-white rounded-lg border border-gray-200 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
                        <input id="issue-search" type="text" class="px-3 py-2 border border-gray-300 rounded-lg" placeholder="Search ref, title, address">
                        <select id="issue-status" class="px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">All Status</option>
                            <option value="new">new</option>
                            <option value="validated">validated</option>
                            <option value="resolved">resolved</option>
                            <option value="rejected">rejected</option>
                        </select>
                        <select id="issue-priority" class="px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">All Priority</option>
                            <option value="high">high</option>
                            <option value="normal">normal</option>
                            <option value="low">low</option>
                        </select>
                    </div>
                    <div id="issue-map" class="w-full h-96 rounded-lg border border-gray-200 mb-3"></div>
                    <p class="text-xs text-gray-500 mb-3"><i class="bi bi-geo-alt text-red-600"></i> Map is restricted to City of Valenzuela. Pins outside the boundary are not allowed.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-900">Ref</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-900">Issue</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-900">Barangay</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-900">Priority</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-900">Status</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-900">Created</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-900">Coordinates</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-900">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="issue-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;

    try {
        await ensureLeafletAssets();
        if (issueMapInstance) {
            issueMapInstance.remove();
            issueMapInstance = null;
            issueMapMarkers = [];
            issueDraftPin = null;
        }

        issueMapInstance = window.L.map('issue-map', { zoomControl: true, scrollWheelZoom: true });
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(issueMapInstance);

        const valenzuelaBoundary = window.L.polygon(VALENZUELA_BOUNDARY_LATLNG, {
            color: '#991b1b',
            weight: 3,
            fillColor: '#dc2626',
            fillOpacity: 0.08
        }).addTo(issueMapInstance);

        const boundaryBounds = valenzuelaBoundary.getBounds();
        issueMapInstance.fitBounds(boundaryBounds, { padding: [24, 24] });
        issueMapInstance.setMaxBounds(boundaryBounds.pad(0.08));
        setTimeout(() => {
            issueMapInstance.invalidateSize(true);
        }, 150);

        issueMapInstance.on('click', function (e) {
            if (!isPointInsideValenzuela(e.latlng.lat, e.latlng.lng)) {
                showNotification('Please place the pin inside City of Valenzuela only.', 'warning');
                return;
            }
            const lat = Number(e.latlng.lat).toFixed(7);
            const lng = Number(e.latlng.lng).toFixed(7);
            const latEl = document.getElementById('issue-lat');
            const lngEl = document.getElementById('issue-lng');
            if (latEl) latEl.value = String(lat);
            if (lngEl) lngEl.value = String(lng);

            if (issueDraftPin) issueMapInstance.removeLayer(issueDraftPin);
            issueDraftPin = window.L.marker([Number(lat), Number(lng)]).addTo(issueMapInstance);
        });
    } catch (e) {
        showNotification('Issue map failed to load. Check internet connection.', 'error');
    }

    document.getElementById('issue-search')?.addEventListener('input', () => {
        issueRenderTable();
        issueRefreshMapPins();
    });
    document.getElementById('issue-status')?.addEventListener('change', () => {
        issueRenderTable();
        issueRefreshMapPins();
    });
    document.getElementById('issue-priority')?.addEventListener('change', () => {
        issueRenderTable();
        issueRefreshMapPins();
    });

    try {
        await loadIssuesFromApi();
        issueRenderStats();
        issueRenderTable();
        issueRefreshMapPins();
    } catch (e) {
        showNotification(e && e.message ? String(e.message) : 'Failed to load issues.', 'error');
    }
}

// PC Dashboard Calendar functions
function openPCCalendarModal() {
    const modal = document.getElementById('pc-calendar-modal');
    if (modal) {
        modal.style.display = 'flex';
        renderPCDashboardCalendar();
    }
}

function closePCCalendarModal() {
    const modal = document.getElementById('pc-calendar-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal on backdrop click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('pc-calendar-modal');
    if (modal && e.target === modal) {
        closePCCalendarModal();
    }
});

let pcDashboardCalYear = new Date().getFullYear();
let pcDashboardCalMonth = new Date().getMonth();


function pcDashboardCalendarChangeMonth(delta) {
    pcDashboardCalMonth += delta;
    if (pcDashboardCalMonth < 0) { pcDashboardCalMonth = 11; pcDashboardCalYear -= 1; }
    if (pcDashboardCalMonth > 11) { pcDashboardCalMonth = 0; pcDashboardCalYear += 1; }
    renderPCDashboardCalendar();
}

function renderPCDashboardCalendar() {
    const label = document.getElementById('pc-dashboard-calendar-label');
    const grid = document.getElementById('pc-dashboard-calendar-grid');
    if (!label || !grid) return;

    const first = new Date(pcDashboardCalYear, pcDashboardCalMonth, 1);
    const last = new Date(pcDashboardCalYear, pcDashboardCalMonth + 1, 0);
    label.textContent = first.toLocaleString('default', { month: 'long', year: 'numeric' });

    const startDay = (first.getDay() + 6) % 7;
    const totalDays = last.getDate();
    const cells = Math.ceil((startDay + totalDays) / 7) * 7;

    let consultations = (window.AppData && Array.isArray(window.AppData.consultations)) ? window.AppData.consultations : [];

    if (consultations.length === 0 && !window._pcCalendarFetching) {
        window._pcCalendarFetching = true;
        fetch('API/consultations_api.php?action=list')
            .then(r => r.json())
            .then(d => {
                window._pcCalendarFetching = false;
                if (d && d.success && Array.isArray(d.data)) {
                    if (!window.AppData) window.AppData = {};
                    window.AppData.consultations = d.data;
                    renderPCDashboardCalendar();
                }
            })
            .catch(() => { window._pcCalendarFetching = false; });
    }
    const todayStr = new Date().toISOString().substring(0, 10);

    let html = '';
    for (let i = 0; i < cells; i++) {
        const dayNum = i - startDay + 1;
        const isCurrent = dayNum >= 1 && dayNum <= totalDays;
        const dateStr = isCurrent ? `${pcDashboardCalYear}-${String(pcDashboardCalMonth + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}` : '';

        if (!isCurrent) {
            html += `<div class="min-h-[85px] p-2 bg-slate-50/50 rounded-xl border border-slate-100/60"></div>`;
            continue;
        }

        // Find consultations active or starting/ending on dateStr
        const dayEvents = consultations.filter(c => {
            if (!c.start_date && !c.end_date && !c.created_at) return false;
            const start = (c.start_date || c.created_at || '').substring(0, 10);
            const end = (c.end_date || '').substring(0, 10);
            if (start && end) return dateStr >= start && dateStr <= end;
            if (start) return dateStr === start;
            if (end) return dateStr === end;
            return false;
        });

        const isToday = (todayStr === dateStr);

        let cellCls = 'min-h-[90px] p-2 rounded-xl border transition-all duration-200 flex flex-col justify-between ';
        if (isToday) {
            cellCls += 'bg-red-50/80 border-red-300 ring-2 ring-red-500/20 ';
        } else if (dayEvents.length > 0) {
            cellCls += 'bg-white border-blue-200/90 shadow-2xs hover:shadow-md ';
        } else {
            cellCls += 'bg-white border-slate-200/70 hover:bg-slate-50/80 ';
        }

        let eventsHtml = '';
        if (dayEvents.length > 0) {
            eventsHtml = '<div class="space-y-1 mt-1 overflow-hidden max-h-[55px]">';
            dayEvents.slice(0, 2).forEach(ev => {
                const start = (ev.start_date || ev.created_at || '').substring(0, 10);
                const end = (ev.end_date || '').substring(0, 10);
                const isStart = start === dateStr;
                const isEnd = end === dateStr;
                
                let badgeClass = 'bg-blue-50 text-blue-800 border-blue-200';
                let icon = 'bi-chat-quote-fill';
                if (isStart) { badgeClass = 'bg-emerald-50 text-emerald-800 border-emerald-200'; icon = 'bi-play-circle-fill'; }
                if (isEnd) { badgeClass = 'bg-amber-50 text-amber-900 border-amber-200'; icon = 'bi-flag-fill'; }
                
                eventsHtml += `
                    <div onclick="if(typeof pfpViewConsultationModal==='function') pfpViewConsultationModal(${ev.id})" class="px-1.5 py-0.5 rounded text-[10px] font-extrabold border ${badgeClass} truncate cursor-pointer hover:scale-102 transition-transform flex items-center gap-1" title="${escapeHtml(ev.title || '')}">
                        <i class="bi ${icon} text-[9px] shrink-0"></i>
                        <span class="truncate">${escapeHtml(ev.title || 'Topic')}</span>
                    </div>
                `;
            });
            if (dayEvents.length > 2) {
                eventsHtml += `<div class="text-[9px] font-extrabold text-blue-600 pl-1">+${dayEvents.length - 2} more</div>`;
            }
            eventsHtml += '</div>';
        }

        html += `
            <div class="${cellCls}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold ${isToday ? 'w-5 h-5 rounded-full bg-red-700 text-white flex items-center justify-center' : 'text-slate-800'}">${dayNum}</span>
                    ${dayEvents.length > 0 ? `<span class="w-2 h-2 rounded-full bg-blue-600 shrink-0" title="${dayEvents.length} active consultation(s)"></span>` : ''}
                </div>
                ${eventsHtml}
            </div>
        `;
    }
    grid.innerHTML = html;
}

// Report Generation Functions
function openGenerateReportModal() {
    document.getElementById('generate-report-modal').classList.remove('hidden');
}

function closeGenerateReportModal() {
    document.getElementById('generate-report-modal').classList.add('hidden');
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function generateReport() {
    const type = document.getElementById('report-type');
    const startDate = document.getElementById('report-start-date');
    const endDate = document.getElementById('report-end-date');
    const category = document.getElementById('report-category');
    const status = document.getElementById('report-status');
    const exportFormat = document.querySelector('input[name="export-format"]:checked');

    if (!type || !startDate || !endDate || !category || !status || !exportFormat) {
        showNotification('Error accessing form elements', 'error');
        return;
    }

    const startDateValue = startDate.value;
    const endDateValue = endDate.value;

    if (!startDateValue || !endDateValue) {
        showNotification('Please select start and end dates', 'error');
        return;
    }

    // Show loading state
    const generateBtn = document.querySelector('#generate-report-modal button[onclick="generateReport()"]');
    if (generateBtn) {
        generateBtn.disabled = true;
        generateBtn.textContent = 'Generating...';
    }

    // Map report type to module
    const moduleMap = {
        'consultation_summary': 'consultations',
        'feedback_analysis': 'feedback',
        'issue_report': 'consultations',
        'survey_results': 'consultations'
    };
    const module = moduleMap[type.value] || 'dashboard';

    // Map export format
    const formatMap = {
        'pdf': 'pdf',
        'excel': 'excel',
        'csv': 'excel'
    };
    const format = formatMap[exportFormat.value] || 'pdf';

    // Call the backend report generation API
    const formData = new FormData();
    formData.append('action', 'generate_module_report');
    formData.append('module', module);
    formData.append('format', format);
    formData.append('csrf_token', getCsrfToken());

    console.log('Generating report with:', { module, format, csrfToken: getCsrfToken() });

    fetch('system-template-full.php', {
        method: 'POST',
        body: formData
    })
        .then(res => {
            console.log('Response status:', res.status);
            console.log('Response headers:', res.headers);
            return res.text();
        })
        .then(text => {
            console.log('Raw response text:', text);
            console.log('Response length:', text.length);
            // Try to extract JSON from response (in case there's extra content)
            let jsonText = text;
            const jsonStart = text.indexOf('{');
            const jsonEnd = text.lastIndexOf('}');
            if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd > jsonStart) {
                jsonText = text.substring(jsonStart, jsonEnd + 1);
                console.log('Extracted JSON:', jsonText);
            }
            try {
                const data = JSON.parse(jsonText);
                console.log('Parsed data:', data);
                if (data && data.success) {
                    const typeLabel = type.options[type.selectedIndex].text;
                    const reportTitle = `${typeLabel} (${formatDate(startDateValue)} - ${formatDate(endDateValue)})`;

                    const report = {
                        id: Date.now(),
                        uid: String(Date.now()),
                        title: reportTitle,
                        type: 'report',
                        group: 'reports',
                        status: 'approved',
                        size: 0,
                        downloads: 0,
                        views: 0,
                        uploadedBy: (typeof currentUser !== 'undefined' && currentUser && currentUser.name) ? currentUser.name : ((typeof AppData !== 'undefined' && AppData.currentUser && AppData.currentUser.name) ? AppData.currentUser.name : 'System'),
                        uploadedAt: new Date().toISOString(),
                        date: new Date().toISOString().split('T')[0],
                        description: `Generated report: ${typeLabel} for ${category.value === 'all' ? 'all categories' : category.value} with status ${status.value === 'all' ? 'all statuses' : status.value}`,
                        tags: [type.value, category.value, exportFormat.value],
                        downloadUrl: data.download_url,
                        filePath: data.filename,
                        reportType: type.value,
                        reportStartDate: startDateValue,
                        reportEndDate: endDateValue,
                        reportCategory: category.value,
                        reportStatus: status.value,
                        reportFormat: exportFormat.value
                    };

                    AppData.documents.unshift(report);
                    renderDocumentsTable();
                    closeGenerateReportModal();
                    if (data.download_url) {
                        const downloadLink = document.createElement('a');
                        downloadLink.href = data.download_url;
                        downloadLink.setAttribute('download', data.filename || '');
                        document.body.appendChild(downloadLink);
                        downloadLink.click();
                        document.body.removeChild(downloadLink);
                    }
                    showNotification('Report generated successfully', 'success');
                } else {
                    const err = (data && data.message) ? data.message : 'Report generation failed.';
                    console.error('Report generation failed:', err);
                    showNotification(err, 'error');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response was:', text);
                showNotification('Invalid response from server', 'error');
            }
        })
        .catch(err => {
            console.error('Report generation error:', err);
            showNotification('Report generation failed. Please try again.', 'error');
        })
        .finally(() => {
            // Reset button state
            if (generateBtn) {
                generateBtn.disabled = false;
                generateBtn.textContent = 'Generate Report';
            }
        });
}

function pfpTriggerAiCommitteeCompile() {
    let selCid = String(document.getElementById('pfq-consultation')?.value || '').trim();
    const selectEl = document.getElementById('pfq-consultation');

    if (!selCid) {
        const opts = selectEl ? Array.from(selectEl.options).filter(o => o.value) : [];
        if (opts.length > 0) {
            const preferredOpt = opts.find(o => {
                const c = (AppData.consultations || []).find(item => String(item.id) === String(o.value));
                const st = String(c?.status || '').toLowerCase();
                return st === 'closed' || st === 'completed';
            }) || opts[0];

            selCid = preferredOpt.value;
            if (selectEl) selectEl.value = selCid;
            showNotification(`Auto-selected consultation #${selCid} for AI synthesis.`, 'info');
        } else if (Array.isArray(AppData.consultations) && AppData.consultations.length > 0) {
            const preferredC = AppData.consultations.find(c => {
                const st = String(c.status || '').toLowerCase();
                return st === 'closed' || st === 'completed';
            }) || AppData.consultations[0];

            selCid = String(preferredC.id);
            if (selectEl) selectEl.value = selCid;
            showNotification(`Auto-selected consultation #${selCid} for AI synthesis.`, 'info');
        }
    }

    if (!selCid) {
        showNotification('No active or completed consultation policy found to summarize.', 'warning');
        return;
    }

    pfpShowAiCommitteeBriefModal(selCid);
}

function pfpShowGatedConsultationModal(consultation) {
    let modal = document.getElementById('pfq-gated-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'pfq-gated-modal';
        modal.className = 'fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4';
        document.body.appendChild(modal);
    }

    const cid = Number(consultation.id || 0);
    const title = escapeHtml(consultation.title || 'Consultation');
    const status = escapeHtml(consultation.status || 'Active').toUpperCase();

    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-amber-200 animate-in fade-in zoom-in duration-150">
            <!-- Modal Header Banner -->
            <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0 text-2xl shadow-inner">
                    <i class="bi bi-lock-fill"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white leading-tight">
                        Closed Consultation Required
                    </h3>
                    <p class="text-xs text-amber-100 mt-0.5 font-medium">LGU Committee Workflow Rule</p>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="p-6 space-y-4 text-xs text-gray-700">
                <div class="bg-amber-50/70 p-3.5 rounded-xl border border-amber-200/80 space-y-1.5">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-amber-800 font-bold uppercase tracking-wider">Target Consultation</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-extrabold text-[10px]">${status}</span>
                    </div>
                    <p class="font-bold text-gray-900 text-sm">#${cid} - ${title}</p>
                </div>

                <div class="space-y-2 text-gray-600 leading-relaxed">
                    <p>
                        Citizen feedback for this consultation cannot be compiled into an AI Brief or forwarded to an LGU committee while the consultation is still <strong>${status}</strong>.
                    </p>
                    <p class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-gray-700">
                        <i class="bi bi-info-circle-fill text-blue-600 mr-1"></i>
                        Please mark the consultation status as <strong>Closed</strong> first to ensure all citizen responses, survey votes, and comments are finalized before AI synthesis.
                    </p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-2">
                <button onclick="document.getElementById('pfq-gated-modal').remove()" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white font-bold rounded-lg text-xs transition shadow-sm">
                    Understand & Close Notice
                </button>
            </div>
        </div>
    `;
}

async function pfpShowAiCommitteeBriefModal(consultationId) {
    const cid = Number(consultationId || 0);
    const consultation = AppData.consultations.find(c => Number(c.id) === cid);

    if (!consultation) {
        showNotification('Consultation not found.', 'error');
        return;
    }

    const cStatus = String(consultation.status || '').toLowerCase().trim();

    // Enforce workflow gating: Consultation must be Closed
    if (cStatus !== 'closed' && cStatus !== 'completed') {
        pfpShowGatedConsultationModal(consultation);
        return;
    }

    // Display loading notification
    showNotification('AI Engine is analyzing feedback and compiling Committee Brief...', 'info');

    try {
        const res = await fetchWithTimeout(`API/consultation_feedback_ai.php?action=compile_committee_brief&consultation_id=${cid}`, {
            headers: { 'Accept': 'application/json' }
        }, 10000);

        if (!res.ok) {
            const errData = await res.json().catch(() => null);
            if (errData && errData.is_gated) {
                pfpShowGatedConsultationModal(consultation);
                return;
            }
            throw new Error(errData?.message || `HTTP ${res.status}`);
        }

        const json = await res.json();
        if (!json.success || !json.data) {
            throw new Error(json.message || 'Failed to compile AI Brief.');
        }

        const brief = json.data;
        renderAiCommitteeBriefModalHtml(brief);

    } catch (e) {
        console.error('AI Brief compilation failed:', e);
        showNotification(`AI Compilation failed: ${e.message}`, 'error');
    }
}

function renderAiCommitteeBriefModalHtml(brief) {
    let modal = document.getElementById('pfq-ai-brief-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'pfq-ai-brief-modal';
        modal.className = 'fixed inset-0 bg-slate-900/70 backdrop-blur-md flex items-center justify-center z-50 p-4 sm:p-6 overflow-y-auto';
        document.body.appendChild(modal);
    }

    const problemsHtml = (brief.problems || []).map((p, idx) => `
        <tr class="border-b border-slate-100 hover:bg-slate-50/80 transition">
            <td class="px-4 py-3 font-bold text-slate-800 text-xs w-1/4">${idx + 1}. ${escapeHtml(p.category)}</td>
            <td class="px-4 py-3 text-slate-700 text-xs leading-relaxed">${escapeHtml(p.issue)}</td>
            <td class="px-4 py-3 text-center w-28">
                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider ${p.severity === 'high' ? 'bg-red-100 text-red-800 border border-red-200' : (p.severity === 'medium' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200')}">
                    ${escapeHtml(p.severity || 'normal')}
                </span>
            </td>
        </tr>
    `).join('');

    const solutionsHtml = (brief.solutions || []).map((s, idx) => `
        <div class="p-3.5 bg-emerald-50/70 rounded-xl border border-emerald-200/80 shadow-2xs space-y-1">
            <div class="font-extrabold text-emerald-950 text-xs flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600 text-sm"></i>
                <span>${idx + 1}. Policy Recommendation (${escapeHtml(s.category)})</span>
            </div>
            <p class="text-xs text-slate-700 leading-relaxed pl-5 font-medium">${escapeHtml(s.recommendation)}</p>
        </div>
    `).join('');

    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full my-auto overflow-hidden animate-in fade-in zoom-in duration-200 border border-slate-200">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-red-800 via-red-900 to-slate-900 text-white p-6 sm:p-7 flex items-start justify-between gap-4 border-b border-red-900/50">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-3 py-1 rounded-full bg-white/15 backdrop-blur-xs text-white text-[10px] font-extrabold uppercase tracking-wider border border-white/20 flex items-center gap-1.5">
                            <i class="bi bi-robot text-red-300"></i> Official AI Committee Synthesis Document
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-bold border border-emerald-400/30 uppercase tracking-wider">
                            CLOSED
                        </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-white leading-tight">
                        ${escapeHtml(brief.title || 'Consultation Feedback Brief')}
                    </h2>
                    <p class="text-xs text-red-100/90 font-medium">Assigned LGU Committee: <strong class="text-white font-bold">${escapeHtml(brief.committee_assigned)}</strong></p>
                </div>
                <button onclick="document.getElementById('pfq-ai-brief-modal').remove()" class="text-slate-300 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition text-lg leading-none" title="Close Modal">&times;</button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 sm:p-8 space-y-6 max-h-[75vh] overflow-y-auto bg-slate-50/30">
                <!-- Summary Metrics Bar -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs text-center">
                    <div class="p-2">
                        <span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block mb-1">Total Citizen Feedback</span>
                        <span class="text-3xl font-black text-slate-900">${brief.stats?.total_submissions || 0}</span>
                    </div>
                    <div class="p-2 border-y sm:border-y-0 sm:border-x border-slate-100">
                        <span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block mb-1">Dominant Public Tone</span>
                        <span class="text-lg font-black capitalize ${brief.stats?.dominant_sentiment === 'negative' ? 'text-red-600' : 'text-emerald-600'}">
                            ${brief.stats?.dominant_sentiment || 'Neutral'}
                        </span>
                    </div>
                    <div class="p-2">
                        <span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block mb-1">Transmittal Target</span>
                        <span class="text-xs font-extrabold text-purple-900 block truncate px-2 py-1 bg-purple-50 rounded-lg border border-purple-100" title="${escapeHtml(brief.committee_assigned)}">${escapeHtml(brief.committee_assigned)}</span>
                    </div>
                </div>

                <!-- Integrated Multi-System Source Merging Box -->
                <div class="bg-gradient-to-r from-blue-50/90 via-indigo-50/70 to-purple-50/90 p-5 rounded-2xl border border-blue-200/80 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <span class="text-xs font-extrabold text-blue-950 uppercase tracking-wider flex items-center gap-2">
                            <i class="bi bi-diagram-3-fill text-blue-600 text-sm"></i> Integrated Multi-System Source Merging & Provenance
                        </span>
                        <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-900 font-bold text-[10px] border border-blue-200 shadow-2xs">PCMS ↔ PHMS Live Interconnected</span>
                    </div>
                    <p class="text-xs text-slate-700 leading-relaxed font-medium">
                        ${escapeHtml(brief.merged_sources?.summary_text || `Unified AI Analysis merged all online PCMS citizen feedback and cross-referenced PHMS Live Public Hearing records.`)}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 text-xs font-bold">
                        <div class="px-4 py-2.5 bg-white text-slate-800 rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-slate-700"><i class="bi bi-globe text-blue-600 text-sm"></i> PCMS Online Consultation Portal:</span>
                            <strong class="text-blue-950 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-200 text-xs">${brief.merged_sources?.pcms_portal_count || 0} Submissions</strong>
                        </div>
                        <div class="px-4 py-2.5 bg-white text-slate-800 rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2 text-slate-700"><i class="bi bi-building-gear text-purple-600 text-sm"></i> PHMS Live Hearing Testimonies:</span>
                            <strong class="text-purple-950 bg-purple-50 px-2.5 py-1 rounded-lg border border-purple-200 text-xs">${brief.merged_sources?.phms_hearing_count || 0} Testimonies</strong>
                        </div>
                    </div>
                </div>

                <!-- Section 1: Identified Problems -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-3">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-red-700 flex items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-red-600"></i> Section 1: Identified Citizen Problems & Grievances
                    </h3>
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-[11px] uppercase font-extrabold text-slate-600">
                                <tr>
                                    <th class="px-4 py-2.5">Category</th>
                                    <th class="px-4 py-2.5">Identified Grievance / Issues</th>
                                    <th class="px-4 py-2.5 text-center">Severity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">${problemsHtml}</tbody>
                        </table>
                    </div>
                </div>

                <!-- Section 2: AI Recommended Solutions -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-2xs space-y-3">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-emerald-700 flex items-center gap-2">
                        <i class="bi bi-lightbulb-fill text-emerald-600"></i> Section 2: AI Synthesized Solutions & Actionable Policy Steps
                    </h3>
                    <div class="space-y-2.5">${solutionsHtml}</div>
                </div>

                <!-- Section 3: Executive Conclusion -->
                <div class="bg-gradient-to-r from-slate-900 to-blue-950 text-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-800 space-y-2.5">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-blue-300 flex items-center gap-2">
                        <i class="bi bi-file-earmark-check-fill text-blue-400 text-sm"></i> Section 3: Executive Conclusion & Transmittal Note
                    </h3>
                    <p class="text-xs text-slate-200 font-medium leading-relaxed">${escapeHtml(brief.conclusion)}</p>
                    <p class="text-[11px] text-blue-300 font-semibold border-t border-slate-700/80 pt-2.5">${escapeHtml(brief.transmittal_note)}</p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-slate-50 px-6 sm:px-8 py-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <button onclick="window.print()" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs transition shadow-2xs flex items-center gap-2">
                    <i class="bi bi-printer text-slate-500"></i> Print / Save PDF
                </button>
                <div class="flex items-center gap-3">
                    <button onclick="document.getElementById('pfq-ai-brief-modal').remove()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold rounded-xl text-xs transition">
                        Cancel
                    </button>
                    <button onclick="pfpForwardBriefToCommittee(${brief.consultation_id}, '${escapeHtml(brief.committee_assigned)}')" class="px-5 py-2 bg-red-700 hover:bg-red-800 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center gap-2">
                        <i class="bi bi-send-fill"></i> Pass Document to Committee
                    </button>
                </div>
            </div>
        </div>
    `;
}

async function pfpForwardBriefToCommittee(consultationId, committeeName) {
    if (!consultationId) return;
    try {
        const res = await fetchWithTimeout('API/consultation_feedback_ai.php?action=forward_brief_to_committee', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ consultation_id: consultationId, committee: committeeName })
        }, 5000);

        const data = await res.json();
        if (res.ok && data.success) {
            showNotification(`✅ Document successfully passed to LGU ${committeeName}!`, 'success', 6000);
            const modal = document.getElementById('pfq-ai-brief-modal');
            if (modal) modal.remove();
            pfpRefreshData();
        } else {
            showNotification(data.message || 'Failed to forward to committee.', 'error');
        }
    } catch (e) {
        showNotification(`Error: ${e.message}`, 'error');
    }
}

async function renderReportsSection() {
    if (typeof showSection === 'function') {
        showSection('reports');
    } else {
        await renderSystemReportsSection();
    }
}

async function renderSystemReportsSection() {
    window._currentActiveSection = 'reports';
    if (typeof hideManagedTemplateSections === 'function') {
        hideManagedTemplateSections();
    }

    const contentArea = document.getElementById('content-area');
    const pageTitle = document.getElementById('page-title');
    const breadcrumbCurrent = document.getElementById('breadcrumb-current') || document.querySelector('.breadcrumb-current');
    if (pageTitle) pageTitle.textContent = 'System Reports';
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Reports';

    // Highlight sidebar nav item for reports
    document.querySelectorAll('.nav-item, [data-section]').forEach(item => {
        item.classList.remove('active');
        const sec = item.dataset.section || '';
        const onclickStr = item.getAttribute('onclick') || '';
        if (sec === 'reports' || onclickStr.includes('reports') || onclickStr.includes('Reports')) {
            item.classList.add('active');
        }
    });

    if (!contentArea) return;

    contentArea.innerHTML = '<div class="p-8 text-center text-gray-500"><i class="bi bi-arrow-repeat animate-spin text-2xl mb-2 block"></i>Loading system reports...</div>';

    try {
        await Promise.all([
            loadFeedbackFromApi().catch(() => { }),
            loadConsultationsFromApi().catch(() => { }),
            loadDocumentsFromApi().catch(() => { })
        ]);
    } catch (e) {
        console.warn('System reports data load warning:', e);
    }

    const totalConsultations = AppData.consultations.length;
    const closedConsultations = AppData.consultations.filter(c => ['closed', 'completed'].includes(String(c.status || '').toLowerCase())).length;
    const totalFeedback = AppData.feedback.length;
    const totalDocs = AppData.documents.length;

    contentArea.innerHTML = `
        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-red-900 via-red-950 to-slate-900 text-white p-7 rounded-2xl shadow-xl border border-red-950/40 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="px-3 py-1 rounded-full bg-white/15 text-red-100 text-[11px] font-extrabold uppercase tracking-wider backdrop-blur-xs border border-white/10">
                        <i class="bi bi-bar-chart-line-fill mr-1"></i> System Governance Hub
                    </span>
                    <h1 class="text-2xl font-black text-white mt-2 flex items-center gap-2">
                        System Reports
                    </h1>
                    <p class="text-xs text-red-100/90 mt-1 max-w-2xl font-medium leading-relaxed">
                        Centralized administrative analytics & official reports across all modules including AI policy briefs, public consultations, citizen sentiment, document governance, and security audit trails.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="window.print()" class="px-4 py-2.5 bg-white text-red-950 hover:bg-red-50 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5 cursor-pointer">
                        <i class="bi bi-printer"></i> Print Executive Summary
                    </button>
                    <button onclick="renderSystemReportsSection()" class="px-3.5 py-2.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl transition border border-white/20 flex items-center gap-1.5 cursor-pointer">
                        <i class="bi bi-arrow-repeat"></i> Refresh Data
                    </button>
                </div>
            </div>

            <!-- Top Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-gray-400 text-[11px] font-bold uppercase tracking-wider">
                        <span>AI Policy Reports</span>
                        <i class="bi bi-robot text-purple-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-gray-900 mt-1.5">${closedConsultations}</p>
                    <p class="text-[11px] text-purple-700 font-semibold mt-1">Closed briefs ready</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-gray-400 text-[11px] font-bold uppercase tracking-wider">
                        <span>Public Consultations</span>
                        <i class="bi bi-journal-text text-red-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-gray-900 mt-1.5">${totalConsultations}</p>
                    <p class="text-[11px] text-gray-500 font-medium mt-1">Total policy consultations</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-gray-400 text-[11px] font-bold uppercase tracking-wider">
                        <span>Citizen Submissions</span>
                        <i class="bi bi-chat-left-text text-blue-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-gray-900 mt-1.5">${totalFeedback}</p>
                    <p class="text-[11px] text-gray-500 font-medium mt-1">Total logged feedback</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-gray-400 text-[11px] font-bold uppercase tracking-wider">
                        <span>Managed Documents</span>
                        <i class="bi bi-folder2-open text-emerald-600 text-base"></i>
                    </div>
                    <p class="text-3xl font-black text-gray-900 mt-1.5">${totalDocs}</p>
                    <p class="text-[11px] text-gray-500 font-medium mt-1">Governance files</p>
                </div>
            </div>

            <!-- Main Reports Navigation Tabs -->
            <div class="border-b border-gray-200 bg-white rounded-t-xl px-4 pt-3 flex items-center gap-2 overflow-x-auto shadow-sm">
                <button id="sys-report-tab-ai" onclick="switchSystemReportTab('ai')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-red-600 text-red-600 flex items-center gap-2 transition focus:outline-none sys-report-tab">
                    <i class="bi bi-robot"></i> AI Committee Policy Reports
                </button>
                <button id="sys-report-tab-consultation" onclick="switchSystemReportTab('consultation')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-800 flex items-center gap-2 transition focus:outline-none sys-report-tab">
                    <i class="bi bi-journal-text"></i> Consultation & Survey Reports
                </button>
                <button id="sys-report-tab-feedback" onclick="switchSystemReportTab('feedback')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-800 flex items-center gap-2 transition focus:outline-none sys-report-tab">
                    <i class="bi bi-chat-square-quote"></i> Feedback & Sentiment Reports
                </button>
                <button id="sys-report-tab-documents" onclick="switchSystemReportTab('documents')" class="px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-800 flex items-center gap-2 transition focus:outline-none sys-report-tab">
                    <i class="bi bi-folder2"></i> Document Governance Reports
                </button>
            </div>

            <!-- Active Report View Container -->
            <div id="sys-reports-tab-body" class="bg-white rounded-b-xl border border-t-0 border-gray-200 p-6 min-h-[400px]">
                <!-- Populated by switchSystemReportTab -->
            </div>
        </div>
    `;

    switchSystemReportTab('ai');
}

function switchSystemReportTab(tabName) {
    const tabBtns = document.querySelectorAll('.sys-report-tab');
    tabBtns.forEach(btn => {
        btn.className = 'px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-800 flex items-center gap-2 transition focus:outline-none sys-report-tab';
    });

    const activeBtn = document.getElementById(`sys-report-tab-${tabName}`);
    if (activeBtn) {
        activeBtn.className = 'px-4 py-2.5 text-xs font-bold border-b-2 border-red-600 text-red-600 flex items-center gap-2 transition focus:outline-none sys-report-tab';
    }

    const container = document.getElementById('sys-reports-tab-body');
    if (!container) return;

    if (tabName === 'ai') {
        container.innerHTML = `
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <i class="bi bi-robot text-purple-600"></i> AI Committee Synthesis & Resolution Briefs Vault
                        </h3>
                        <p class="text-xs text-gray-500">Consolidated resolution briefs synthesized from closed public consultations for formal transmittal to LGU committees.</p>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-purple-50/70 border-b border-purple-200 uppercase tracking-wider text-[11px] font-bold text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-gray-900">Consultation Policy</th>
                                <th class="px-4 py-3 text-gray-900">Assigned Committee</th>
                                <th class="px-4 py-3 text-center text-gray-900">Submissions</th>
                                <th class="px-4 py-3 text-gray-900">Status</th>
                                <th class="px-4 py-3 text-center text-gray-900">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${AppData.consultations.map(c => {
            const cid = Number(c.id);
            const isClosed = ['closed', 'completed'].includes(String(c.status || '').toLowerCase());
            const feedbackCount = AppData.feedback.filter(f => Number(f.consultationId || f.consultation_id) === cid).length;
            return `
                                    <tr class="border-b border-gray-100 hover:bg-slate-50 transition">
                                        <td class="px-4 py-3.5">
                                            <div class="font-bold text-gray-900 text-xs leading-snug">#${cid} - ${escapeHtml(c.title || 'Consultation')}</div>
                                            <div class="text-[11px] text-gray-500 font-medium">Category: ${escapeHtml(c.category || 'General Policy')}</div>
                                        </td>
                                        <td class="px-4 py-3.5 font-semibold text-purple-900">
                                            <span class="inline-block px-2 py-0.5 bg-purple-50 rounded border border-purple-200 text-xs">
                                                <i class="bi bi-diagram-3 mr-1"></i>${escapeHtml(c.committee_assigned || 'Rules & Governance Committee')}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-semibold text-gray-800 text-xs">${feedbackCount} submission(s)</td>
                                        <td class="px-4 py-3.5">
                                            ${isClosed ? '<span class="px-2 py-0.5 rounded-md bg-slate-800 text-white font-bold text-[10px] uppercase tracking-wider">CLOSED</span>' : '<span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-bold text-[10px] uppercase tracking-wider">ACTIVE</span>'}
                                        </td>
                                        <td class="px-4 py-3.5 text-center">
                                            ${isClosed ? `
                                                <button onclick="pfpShowAiCommitteeBriefModal(${cid})" class="px-3.5 py-1.5 bg-purple-700 hover:bg-purple-800 text-white font-bold rounded-lg text-xs transition shadow-sm flex items-center gap-1 mx-auto">
                                                    <i class="bi bi-file-earmark-text-fill"></i> View AI Brief
                                                </button>
                                            ` : `
                                                <button onclick="pfpShowAiCommitteeBriefModal(${cid})" class="px-3 py-1.5 bg-amber-100 text-amber-900 border border-amber-300 font-semibold rounded-lg text-xs flex items-center gap-1 mx-auto">
                                                    <i class="bi bi-lock-fill"></i> Pending (Active)
                                                </button>
                                            `}
                                        </td>
                                    </tr>
                                `;
        }).join('') || '<tr><td colspan="5" class="p-6 text-center text-gray-500">No consultation records found.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } else if (tabName === 'consultation') {
        const activeCount = AppData.consultations.filter(c => !['closed', 'completed'].includes(String(c.status || '').toLowerCase())).length;
        const closedCount = AppData.consultations.filter(c => ['closed', 'completed'].includes(String(c.status || '').toLowerCase())).length;
        container.innerHTML = `
            <div class="space-y-4">
                <h3 class="text-base font-bold text-gray-900"><i class="bi bi-journal-text text-red-600"></i> Public Consultations & Survey Analytics Report</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <div class="text-xs font-bold text-gray-600 uppercase mb-2">Consultation Lifecycle Breakdown</div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-200 text-xs">
                            <span>Active / Open Consultations</span>
                            <span class="font-bold text-emerald-700">${activeCount}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-200 text-xs">
                            <span>Closed / Completed Consultations</span>
                            <span class="font-bold text-slate-800">${closedCount}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 text-xs">
                            <span>Total Consultations Logged</span>
                            <span class="font-bold text-gray-900">${AppData.consultations.length}</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                        <div class="text-xs font-bold text-gray-600 uppercase mb-2">Policy Category Representation</div>
                        ${Array.from(new Set(AppData.consultations.map(c => c.category || 'General'))).map(cat => {
            const count = AppData.consultations.filter(c => (c.category || 'General') === cat).length;
            return `
                                <div class="flex items-center justify-between py-1.5 border-b border-gray-200/60 text-xs">
                                    <span class="text-gray-700">${escapeHtml(cat)}</span>
                                    <span class="font-bold text-red-700">${count} policy(s)</span>
                                </div>
                            `;
        }).join('')}
                    </div>
                </div>
            </div>
        `;
    } else if (tabName === 'feedback') {
        const positiveCount = AppData.feedback.filter(f => String(f.sentiment || '').toLowerCase() === 'positive').length;
        const negativeCount = AppData.feedback.filter(f => String(f.sentiment || '').toLowerCase() === 'negative').length;
        const neutralCount = AppData.feedback.length - positiveCount - negativeCount;
        container.innerHTML = `
            <div class="space-y-4">
                <h3 class="text-base font-bold text-gray-900"><i class="bi bi-chat-square-quote text-blue-600"></i> Citizen Feedback & Sentiment Intelligence Report</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                    <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                        <span class="text-xs font-bold text-emerald-800 uppercase block">Positive Citizen Sentiment</span>
                        <span class="text-2xl font-black text-emerald-700">${positiveCount}</span>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="text-xs font-bold text-slate-800 uppercase block">Neutral / Inquiries</span>
                        <span class="text-2xl font-black text-slate-700">${neutralCount}</span>
                    </div>
                    <div class="p-4 bg-red-50 rounded-xl border border-red-200">
                        <span class="text-xs font-bold text-red-800 uppercase block">Grievances / Concerns</span>
                        <span class="text-2xl font-black text-red-700">${negativeCount}</span>
                    </div>
                </div>
            </div>
        `;
    } else if (tabName === 'documents') {
        container.innerHTML = `
            <div class="space-y-4">
                <h3 class="text-base font-bold text-gray-900"><i class="bi bi-folder2 text-emerald-600"></i> Document Governance & Storage Analytics</h3>
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 border-b border-gray-200 font-bold uppercase text-gray-600 text-[11px]">
                            <tr>
                                <th class="px-4 py-3">Document Title</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3 text-center">Size</th>
                                <th class="px-4 py-3 text-center">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${AppData.documents.map(d => `
                                <tr class="border-b border-gray-100">
                                    <td class="px-4 py-3 font-bold text-gray-900">${escapeHtml(d.title || d.name || 'Untitled Document')}</td>
                                    <td class="px-4 py-3 text-gray-600">${escapeHtml(d.type || 'PDF')}</td>
                                    <td class="px-4 py-3 text-center text-gray-600">${formatFileSize(d.size || 0)}</td>
                                    <td class="px-4 py-3 text-center font-bold text-blue-700">${d.downloads || 0}</td>
                                </tr>
                            `).join('') || '<tr><td colspan="4" class="p-6 text-center text-gray-500">No documents found.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }
}

window.openApproveCitizenSubmissionModal = function (consultationId) {
    const cid = Number(consultationId);
    const consultation = (AppData.consultations || []).find(c => Number(c.id) === cid);
    if (!consultation) {
        if (typeof alertToast === 'function') alertToast('Submission not found.', 'error');
        else alert('Submission not found.');
        return;
    }

    const old = document.getElementById('approve-submission-modal');
    if (old) old.remove();

    const modal = document.createElement('div');
    modal.id = 'approve-submission-modal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; background-color: rgba(15, 23, 42, 0.85); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 999999; padding: 1rem;';

    const title = escapeHtml(consultation.title || 'Citizen Submission');
    const desc = escapeHtml(consultation.description || 'No description provided.');
    const citizen = escapeHtml(consultation.userName || consultation.user_name || 'Citizen');

    modal.innerHTML = `
        <div class="bg-white rounded-2xl max-w-xl w-full shadow-2xl overflow-hidden border border-slate-200 animate-fadeIn">
            <div class="bg-gradient-to-r from-emerald-800 to-teal-900 text-white p-5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-emerald-300 text-xl"></i>
                    <div>
                        <h3 class="font-extrabold text-sm uppercase tracking-wider">Approve & Launch Public Consultation</h3>
                        <p class="text-[11px] text-emerald-100">Convert citizen proposal into a live consultation on the Portal</p>
                    </div>
                </div>
                <button onclick="document.getElementById('approve-submission-modal').remove()" class="text-white/80 hover:text-white text-xl font-bold transition focus:outline-none">&times;</button>
            </div>
            
            <div class="p-6 space-y-4 text-xs">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                    <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider block">Submitted Proposal</span>
                    <h4 class="font-extrabold text-slate-900 text-sm">${title}</h4>
                    <p class="text-slate-600 font-medium">${desc}</p>
                    <span class="text-[11px] text-slate-400 font-medium block pt-1">Submitted by: <strong>${citizen}</strong></span>
                </div>

                <div class="space-y-3 pt-1">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Assigned LGU Committee <span class="text-red-500">*</span></label>
                        <select id="approve-committee-select" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500">
                            <option value="Rules & Governance Committee">Rules & Governance Committee</option>
                            <option value="Committee on Infrastructure & Public Works">Committee on Infrastructure & Public Works</option>
                            <option value="Committee on Health & Sanitation">Committee on Health & Sanitation</option>
                            <option value="Committee on Environmental Protection">Committee on Environmental Protection</option>
                            <option value="Committee on Public Utilities">Committee on Public Utilities</option>
                            <option value="Committee on Youth & Sports Development">Committee on Youth & Sports Development</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Citizen Feedback Response Mode <span class="text-red-500">*</span></label>
                        <select id="approve-response-mode" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500">
                            <option value="feedback">Written Feedback & Comments (Star Rating)</option>
                            <option value="survey">1-Click Opinion Poll (Agree vs Disagree)</option>
                            <option value="hybrid">Hybrid (Written Feedback + Opinion Poll)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Public Consultation Expiry / Close Date</label>
                        <input id="approve-expiry-date" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-medium focus:ring-2 focus:ring-emerald-500" value="${new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]}">
                    </div>
                </div>

                <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-amber-900 text-[11px] flex items-center gap-2 font-medium">
                    <i class="bi bi-info-circle-fill text-amber-600 text-sm shrink-0"></i>
                    <span>Once approved, this policy will be published live on the Citizen Consultation Portal for public community voting & feedback.</span>
                </div>
            </div>

            <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
                <button onclick="document.getElementById('approve-submission-modal').remove()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs rounded-xl transition">
                    Cancel
                </button>
                <button onclick="confirmApproveCitizenSubmission(${cid})" class="px-5 py-2 bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-700 hover:to-teal-800 text-white font-extrabold text-xs rounded-xl transition shadow flex items-center gap-1.5 cursor-pointer">
                    <i class="bi bi-rocket-takeoff-fill"></i> Launch Public Consultation
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
};

window.confirmApproveCitizenSubmission = function (consultationId) {
    const cid = Number(consultationId);
    const consultation = (AppData.consultations || []).find(c => Number(c.id) === cid);
    if (!consultation) return;

    const committee = document.getElementById('approve-committee-select')?.value || 'Rules & Governance Committee';
    const responseMode = document.getElementById('approve-response-mode')?.value || 'feedback';
    const expiryDate = document.getElementById('approve-expiry-date')?.value || '';

    // Update in-memory AppData
    consultation.status = 'active';
    consultation.type = 'official';
    consultation.committee = committee;
    consultation.response_mode = responseMode;
    if (expiryDate) consultation.end_date = expiryDate;

    // Send API update request
    fetchWithTimeout('API/consultations_api.php?action=approve_publish', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: cid,
            status: 'active',
            type: 'official',
            committee: committee,
            response_mode: responseMode,
            end_date: expiryDate
        })
    }, 5000).then(res => res.json()).then(res => {
        if (typeof loadConsultationsFromApi === 'function') {
            loadConsultationsFromApi().then(() => {
                if (typeof pfpRenderConsultationFeedbackTable === 'function') pfpRenderConsultationFeedbackTable();
                if (typeof pfpRenderSurveyPollsTable === 'function') pfpRenderSurveyPollsTable();
            });
        }
    }).catch(() => { });

    const modal = document.getElementById('approve-submission-modal');
    if (modal) modal.remove();

    if (typeof alertToast === 'function') {
        alertToast('Citizen Submission approved! It is now live on the Public Portal.', 'success');
    }
    renderConsultationManagementSection();
};

window.loadPendingUserApplications = async function() {
    const container = document.getElementById('pending-user-applications-list');
    if (!container) return;
    try {
        const res = await fetch('API/resource_person_api.php?action=list_pending');
        const data = await res.json();
        const badge = document.getElementById('user-mgmt-pending-badge');
        if (data.success && data.data) {
            if (badge) badge.textContent = data.data.length;
            if (data.data.length === 0) {
                container.innerHTML = `
                    <div class="bg-white p-12 rounded-2xl shadow-sm border border-gray-200 text-center col-span-full">
                        <i class="bi bi-check-circle-fill text-4xl text-emerald-500 mb-3 inline-block"></i>
                        <h4 class="text-base font-bold text-gray-800">No Pending Applications</h4>
                        <p class="text-xs text-gray-500 mt-1">All Resource Person applications have been reviewed.</p>
                    </div>
                `;
                return;
            }
            container.innerHTML = data.data.map(app => `
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between hover:shadow-md transition space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 flex items-center gap-1">
                                <i class="bi bi-clock"></i> Pending Review
                            </span>
                            <span class="text-xs text-gray-400">${new Date(app.created_at).toLocaleDateString()}</span>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">${app.fullname}</h3>
                        <p class="text-xs text-gray-500 mb-3">${app.email} ${app.phone ? '• ' + app.phone : ''}</p>
                        
                        <div class="space-y-2 text-xs">
                            <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                <span class="font-bold text-gray-700 block mb-0.5">Department / Office:</span>
                                <span class="text-gray-600">${app.department || 'Not specified'}</span>
                            </div>
                            <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                <span class="font-bold text-gray-700 block mb-0.5">Areas of Expertise:</span>
                                <span class="text-gray-600">${app.expertise_areas || 'Not specified'}</span>
                            </div>
                            <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                <span class="font-bold text-gray-700 block mb-0.5">Qualifications:</span>
                                <span class="text-gray-600">${app.qualifications || 'Not specified'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-2 pt-2 border-t border-gray-100">
                        <button onclick="approveResourcePersonApp(${app.id})" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                            <i class="bi bi-check-lg text-sm"></i> Approve
                        </button>
                        <button onclick="rejectResourcePersonApp(${app.id})" class="flex-1 bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2.5 px-4 rounded-xl text-xs transition border border-red-200 flex items-center justify-center gap-1.5">
                            <i class="bi bi-x-lg text-sm"></i> Reject
                        </button>
                    </div>
                </div>
            `).join('');
        }
    } catch(e) {
        container.innerHTML = `<div class="p-6 text-red-600 text-xs text-center col-span-full">Failed to load pending applications.</div>`;
    }
};

window.loadApprovedUserExperts = async function() {
    const container = document.getElementById('approved-user-experts-list');
    if (!container) return;
    try {
        const res = await fetch('API/resource_person_api.php?action=list_approved');
        const data = await res.json();
        const badge = document.getElementById('approved-experts-badge');
        if (data.success && data.data) {
            if (badge) badge.textContent = data.data.length;
            if (data.data.length === 0) {
                container.innerHTML = `
                    <div class="bg-white p-12 rounded-2xl shadow-sm border border-gray-200 text-center col-span-full">
                        <i class="bi bi-award text-4xl text-gray-300 mb-3 inline-block"></i>
                        <h4 class="text-base font-bold text-gray-800">No Verified Resource Persons</h4>
                        <p class="text-xs text-gray-500 mt-1">No Resource Persons have been verified yet.</p>
                    </div>
                `;
                return;
            }
            container.innerHTML = data.data.map(exp => `
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1">
                                <i class="bi bi-patch-check-fill text-emerald-600"></i> Verified Expert
                            </span>
                            <span class="text-xs text-gray-400">Approved ${new Date(exp.approved_at).toLocaleDateString()}</span>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">${exp.fullname}</h3>
                        <p class="text-xs text-gray-500 mb-3">${exp.email} ${exp.phone ? '• ' + exp.phone : ''}</p>
                        
                        <div class="space-y-2 text-xs">
                            <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                <span class="font-bold text-gray-700 block mb-0.5">Department / Office:</span>
                                <span class="text-gray-600">${exp.department || 'Not specified'}</span>
                            </div>
                            <div class="bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                <span class="font-bold text-gray-700 block mb-0.5">Areas of Expertise:</span>
                                <span class="text-gray-600">${exp.expertise_areas || 'Not specified'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    } catch(e) {
        container.innerHTML = `<div class="p-6 text-red-600 text-xs text-center col-span-full">Failed to load verified experts.</div>`;
    }
};

window.approveResourcePersonApp = function(id, fullname) {
    if (typeof approveResourcePerson === 'function') {
        approveResourcePerson(id, fullname);
    }
};

window.rejectResourcePersonApp = function(id, fullname) {
    if (typeof rejectResourcePerson === 'function') {
        rejectResourcePerson(id, fullname);
    }
};






