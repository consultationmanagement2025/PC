// ==============================
// PCMP SYSTEM - FULL FEATURES
// ==============================

// Global Data Store (no seeded/sample data)
const AppData = {
    documents: [],
    users: [],
    notifications: [],
    announcements: [],
    auditLogs: [],
    loginHistory: [],
    currentUser: null
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
document.addEventListener('DOMContentLoaded', function() {
    initializeData();
    showSection('public-consultation');
    updateHeaderUserDisplays();
    
    // Pre-fetch consultations and feedback so dashboard stats are populated on first visit
    Promise.all([
        loadConsultationsFromApi().catch(e => console.warn('Initial consultations load:', e)),
        loadFeedbackFromApi().catch(e => console.warn('Initial feedback load:', e))
    ]).then(() => {
        // Re-render dashboard if still on the public-consultation section
        const breadcrumb = document.querySelector('.breadcrumb-current');
        if (breadcrumb && breadcrumb.textContent === 'Public Consultation') {
            renderPublicConsultation();
        }
    });
    
    // Delay notification loading slightly to ensure DOM is ready
    setTimeout(function() {
        loadNotifications();
    }, 100);
    
    // Poll for new notifications every 20 seconds (real-time updates)
    setInterval(function() {
        loadNotifications();
    }, 20000);
    
    // Close notifications dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notifications-dropdown');
        const btn = document.getElementById('notifications-btn');
        if (dropdown && btn && !dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
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

function mapDbDocumentToUi(row) {
    return {
        id: Number(row.id),
        reference: String(row.reference || row.ref_no || row.reference_number || ''),
        title: String(row.title || ''),
        type: String(row.type || '').toLowerCase(),
        status: String(row.status || 'draft').toLowerCase(),
        date: row.document_date || row.date || row.created_at || '',
        description: String(row.description || ''),
        uploadedBy: String(row.uploaded_by || row.uploadedBy || row.uploader || ''),
        uploadedAt: row.created_at || row.uploaded_at || '',
        fileSize: String(row.file_size || row.fileSize || ''),
        views: Number(row.views || 0),
        downloads: Number(row.downloads || 0),
        tags: Array.isArray(row.tags) ? row.tags : (row.tags ? String(row.tags).split(',').map(s => s.trim()).filter(Boolean) : [])
    };
}

async function loadDocumentsFromApi() {
    const res = await fetch('API/documents_api.php?action=list&limit=200&offset=0', {
        headers: { 'Accept': 'application/json' }
    });

    let data = null;
    try { data = await res.json(); } catch (_) {}

    if (!res.ok) {
        const msg = (data && data.message)
            ? data.message
            : (res.status === 403 ? 'Unauthorized (admin session required)' : `HTTP ${res.status}`);
        throw new Error(msg);
    }

    if (!data || !data.success || !Array.isArray(data.data)) {
        throw new Error((data && data.message) ? data.message : 'Failed to load documents');
    }

    AppData.documents = data.data.map(mapDbDocumentToUi);
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
    try { data = await res.json(); } catch (_) {}

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
function showSection(sectionName) {
    const contentArea = document.getElementById('content-area');
    
    // Safety check
    if (!contentArea) {
        console.error('Content area not found!');
        return;
    }
    
    // Update active nav item
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.section === sectionName) {
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
    
    // Load section content
    try {
        switch(sectionName) {
            case 'dashboard':
                renderDashboard();
                break;
            case 'documents':
                renderDocuments();
                break;
            case 'search':
                renderSearch();
                break;
            case 'analytics':
                renderAnalytics();
                break;
            // Public Consultation placeholders
            case 'public-consultation':
                renderPublicConsultation();
                break;
            case 'consultation-management':
                renderConsultationManagement();
                break;
            case 'feedback-collection':
            case 'feedback':
                renderFeedbackCollection();
                break;
            case 'pc-documents':
            case 'document-management':
                renderPCDocuments();
                break;
            case 'users':
                renderUsers();
                break;
            case 'audit':
                renderAudit();
                break;
            case 'profile':
                renderProfile();
                break;
            case 'settings':
                renderSettings();
                break;
            case 'help':
            case 'help-support':
                renderHelp();
                break;
            case 'notifications':
                renderNotifications();
                break;
            case 'announcements':
                renderAnnouncements();
                break;
            default:
                contentArea.innerHTML = `
                    <div class="text-center py-12">
                        <div class="text-6xl text-gray-300 mb-4">📋</div>
                        <p class="text-gray-600 text-lg font-semibold">Section not found</p>
                        <p class="text-gray-400 mt-2">The section "${sectionName}" could not be loaded.</p>
                        <p class="text-gray-400 text-sm mt-4">Please select a valid section from the menu.</p>
                        <button onclick="showSection('public-consultation')" class="mt-6 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Back to Dashboard
                        </button>
                    </div>
                `;
        }
    } catch (error) {
        console.error('Error rendering section:', error);
        contentArea.innerHTML = `
            <div class="text-center py-12">
                <div class="text-6xl text-red-300 mb-4">⚠️</div>
                <p class="text-red-600 text-lg font-semibold">Error Loading Section</p>
                <p class="text-gray-600 mt-2">${error.message}</p>
                <button onclick="showSection('public-consultation')" class="mt-6 px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Back to Dashboard
                </button>
            </div>
        `;
    }
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
                                        <i class="bi bi-eye"></i>
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
                <select id="filterStatus" class="input-field" onchange="filterDocuments()">
                    <option value="">All Status</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                    <option value="draft">Draft</option>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-100" onclick="sortDocuments('status')">
                                Status <i class="bi bi-arrow-down-up text-xs"></i>
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
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Loading documents...</td></tr>';
    }

    loadDocumentsFromApi()
        .then(() => filterDocuments())
        .catch(err => {
            const msg = String(err && err.message ? err.message : err);
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-red-600">Failed to load documents.<div class="text-xs text-gray-500 mt-2">${msg}</div></td></tr>`;
            }
        });
}

function filterDocuments() {
    const typeFilter = document.getElementById('filterType')?.value || '';
    const statusFilter = document.getElementById('filterStatus')?.value || '';
    const searchTerm = document.getElementById('searchDocs')?.value.toLowerCase() || '';
    
    let filtered = AppData.documents.filter(doc => {
        const matchesType = !typeFilter || doc.type === typeFilter;
        const matchesStatus = !statusFilter || doc.status === statusFilter;
        const matchesSearch = !searchTerm || 
            doc.title.toLowerCase().includes(searchTerm) ||
            doc.reference.toLowerCase().includes(searchTerm) ||
            doc.description.toLowerCase().includes(searchTerm);
        
        return matchesType && matchesStatus && matchesSearch;
    });
    
    const tbody = document.getElementById('documentsList');
    if (!tbody) return;
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No documents found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(doc => `
        <tr class="hover:bg-gray-50 transition">
            <td class="px-6 py-4 text-sm font-medium text-gray-900">${doc.reference}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${doc.title}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${capitalizeFirstLetter(doc.type)}</td>
            <td class="px-6 py-4">${getStatusBadge(doc.status)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${formatDate(doc.date)}</td>
            <td class="px-6 py-4 text-sm space-x-2">
                <button onclick="viewDocument(${doc.id})" class="text-blue-600 hover:text-blue-700" title="View">
                    <i class="bi bi-eye"></i>
                </button>
                <button onclick="editDocument(${doc.id})" class="text-green-600 hover:text-green-700" title="Edit">
                    <i class="bi bi-pencil"></i>
                </button>
                <button onclick="deleteDocument(${doc.id})" class="text-red-600 hover:text-red-700" title="Delete">
                    <i class="bi bi-trash"></i>
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
    document.getElementById('filterStatus').value = '';
    document.getElementById('searchDocs').value = '';
    filterDocuments();
}

function viewDocument(id) {
    const doc = AppData.documents.find(d => d.id === id);
    if (!doc) return;
    
    document.getElementById('view-title').textContent = doc.title;
    document.getElementById('view-reference').textContent = doc.reference;
    document.getElementById('view-type').textContent = capitalizeFirstLetter(doc.type);
    document.getElementById('view-status').innerHTML = getStatusBadge(doc.status);
    document.getElementById('view-date').textContent = formatDate(doc.date);
    document.getElementById('view-uploaded-by').textContent = doc.uploadedBy;
    document.getElementById('view-uploaded-at').textContent = doc.uploadedAt;
    document.getElementById('view-size').textContent = doc.fileSize;
    document.getElementById('view-views').textContent = doc.views;
    document.getElementById('view-downloads').textContent = doc.downloads;
    document.getElementById('view-description').textContent = doc.description;
    document.getElementById('view-tags').innerHTML = doc.tags.map(tag => 
        `<span class="tag">${tag}</span>`
    ).join('');
    
    openModal('view-modal');
}

function editDocument(id) {
    const doc = AppData.documents.find(d => d.id === id);
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
                            <i class="bi bi-eye mr-1"></i>View
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
// USERS MODULE (User Management — Citizens + Staff)
// ==============================
var _userMgmtTab = 'citizens'; // 'citizens' or 'staff'
var _citizenData = [];

function renderUsers(skipLoad = false) {
    const pageTitle = document.querySelector('.page-title');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    
    if (pageTitle) pageTitle.textContent = 'User Management';
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'User Management';

    const totalStaff = AppData.users.length;
    const totalCitizens = _citizenData.length;
    const totalConsultations = _citizenData.reduce((s, c) => s + (c.consultation_count || 0), 0);
    const totalFeedbacks = _citizenData.reduce((s, c) => s + (c.feedback_count || 0), 0);

    const citizenTabActive = _userMgmtTab === 'citizens';
    const staffTabActive = _userMgmtTab === 'staff';

    const html = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">User Management</h1>
                        <p class="text-red-100">Manage citizen submitters and staff accounts</p>
                    </div>
                    ${staffTabActive ? `<button onclick="openAddUserModal()" class="btn-primary flex items-center gap-2 bg-white text-red-600 hover:bg-red-50">
                        <i class="bi bi-person-plus"></i> Add Staff Account
                    </button>` : ''}
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Citizen Submitters</div>
                        <div class="text-3xl font-bold">${totalCitizens}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Consultations</div>
                        <div class="text-3xl font-bold">${totalConsultations}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Feedback</div>
                        <div class="text-3xl font-bold">${totalFeedbacks}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Staff Accounts</div>
                        <div class="text-3xl font-bold">${totalStaff}</div>
                    </div>
                </div>
            </div>

            <!-- Tab Switcher -->
            <div class="bg-white rounded-lg shadow p-1 flex gap-1">
                <button onclick="_userMgmtTab='citizens'; renderUsers(true);" class="flex-1 py-3 px-4 rounded-lg font-bold text-sm transition-all flex items-center justify-center gap-2 ${citizenTabActive ? 'bg-red-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100'}">
                    <i class="bi bi-people"></i> Citizen Submitters <span class="ml-1 px-2 py-0.5 rounded-full text-xs ${citizenTabActive ? 'bg-white bg-opacity-20' : 'bg-gray-200'}">${totalCitizens}</span>
                </button>
                <button onclick="_userMgmtTab='staff'; renderUsers(true);" class="flex-1 py-3 px-4 rounded-lg font-bold text-sm transition-all flex items-center justify-center gap-2 ${staffTabActive ? 'bg-red-600 text-white shadow' : 'text-gray-600 hover:bg-gray-100'}">
                    <i class="bi bi-shield-lock"></i> Staff Accounts <span class="ml-1 px-2 py-0.5 rounded-full text-xs ${staffTabActive ? 'bg-white bg-opacity-20' : 'bg-gray-200'}">${totalStaff}</span>
                </button>
            </div>

            ${citizenTabActive ? `
            <!-- CITIZEN SUBMITTERS TAB -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search Citizens</label>
                        <input type="text" id="citizen-search" placeholder="Search by name or email..." 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            onkeyup="renderCitizensTable()">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
                        <select id="citizen-sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="renderCitizensTable()">
                            <option value="recent">Most Recent Activity</option>
                            <option value="submissions">Most Submissions</option>
                            <option value="name">Name (A-Z)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 border-b-2 border-gray-300">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Citizen</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Email</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Consultations</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Feedback</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Total</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Last Activity</th>
                            </tr>
                        </thead>
                        <tbody id="citizens-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
            ` : `
            <!-- STAFF ACCOUNTS TAB -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search Staff</label>
                        <input type="text" id="user-search" placeholder="Search by name or email..." 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            onkeyup="filterUsers()">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                        <select id="user-role-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterUsers()">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select id="user-status-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterUsers()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 border-b-2 border-gray-300">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Name</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Email</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Role</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Last Login</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Created</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
            `}
        </div>

        <!-- Add/Edit User Modal -->
        <div id="user-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center">
                    <h2 id="user-modal-title" class="text-2xl font-bold">Add Staff Account</h2>
                    <button onclick="closeUserModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="user-id">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                        <input type="text" id="user-name" placeholder="e.g. Juan Dela Cruz" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email *</label>
                        <input type="email" id="user-email" placeholder="official@valenzuela.gov.ph" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    <div id="user-password-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password *</label>
                        <input type="password" id="user-password" placeholder="Min 12 chars, uppercase, lowercase, number, symbol" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Required for new accounts. Leave blank when editing to keep current password.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Role *</label>
                            <select id="user-role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                                <option value="viewer">Viewer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                            <select id="user-status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button onclick="saveUser()" class="flex-1 btn-primary">Save Account</button>
                        <button onclick="closeUserModal()" class="flex-1 btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Details Modal -->
        <div id="user-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center">
                    <h2 id="user-details-title" class="text-2xl font-bold">Details</h2>
                    <button onclick="closeUserDetailsModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>
                </div>
                <div id="user-details-content" class="p-6 space-y-4">
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;

    if (!skipLoad) {
        // Load both citizens and staff data
        const citizenPromise = loadCitizensFromApi();
        const staffPromise = loadUsersFromApi();
        
        Promise.all([citizenPromise, staffPromise])
            .then(() => renderUsers(true))
            .catch(err => {
                console.error('User management load error:', err);
                renderUsers(true);
            });
        return;
    }

    if (_userMgmtTab === 'citizens') {
        renderCitizensTable();
    } else {
        renderUsersTable();
    }
}

// ── Load citizen submitters from API ──
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

// ── Render citizens table ──
function renderCitizensTable() {
    const tbody = document.getElementById('citizens-table-body');
    if (!tbody) return;

    let citizens = [..._citizenData];
    
    // Search filter
    const search = (document.getElementById('citizen-search')?.value || '').toLowerCase();
    if (search) {
        citizens = citizens.filter(c => 
            (c.name || '').toLowerCase().includes(search) || 
            (c.email || '').toLowerCase().includes(search)
        );
    }

    // Sort
    const sort = document.getElementById('citizen-sort')?.value || 'recent';
    if (sort === 'recent') {
        citizens.sort((a, b) => new Date(b.last_activity || 0) - new Date(a.last_activity || 0));
    } else if (sort === 'submissions') {
        citizens.sort((a, b) => (b.total_submissions || 0) - (a.total_submissions || 0));
    } else if (sort === 'name') {
        citizens.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    }

    if (citizens.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">${search ? 'No citizens match your search' : 'No citizen submissions recorded yet'}</td></tr>`;
        return;
    }

    tbody.innerHTML = citizens.map(c => {
        const lastAct = c.last_activity ? new Date(c.last_activity).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
        const initials = (c.name || 'U').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                            <span class="text-blue-600 font-bold text-sm">${initials}</span>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">${escapeHtml(c.name || 'Unknown')}</div>
                            <div class="text-xs text-gray-500">Citizen Submitter</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-gray-700">${escapeHtml(c.email)}</td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${c.consultation_count > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'}">
                        <i class="bi bi-chat-square-text"></i> ${c.consultation_count || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${c.feedback_count > 0 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'}">
                        <i class="bi bi-chat-heart"></i> ${c.feedback_count || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">
                        ${c.total_submissions || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">${lastAct}</td>
            </tr>
        `;
    }).join('');
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
            <p class="text-gray-600 mt-1">Track all system activities and changes</p>
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
                <button onclick="resetAuditFilters()" class="btn-outline">
                    <i class="bi bi-arrow-clockwise mr-2"></i>Reset
                </button>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogsList" class="divide-y divide-gray-200">
                        <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Loading audit logs...</td></tr>
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
        .then(response => response.json())
        .then(data => {
            // Store in AppData for filtering
            AppData.auditLogs = data || [];
            
            // Update stats
            updateAuditStats();
            
            // Display logs
            filterAuditLogs();
        })
        .catch(error => {
            console.error('Error loading audit logs:', error);
            const tbody = document.getElementById('auditLogsList');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-red-500">Failed to load audit logs</td></tr>';
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
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No audit logs found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(log => `
        <tr class="hover:bg-gray-50 transition">
            <td class="px-6 py-4 text-sm text-gray-700">${new Date(log.timestamp).toLocaleString()}</td>
            <td class="px-6 py-4 text-sm font-medium text-gray-900">${log.admin_user}</td>
            <td class="px-6 py-4">${getActionBadge(log.action)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${log.entity_type || 'system'}</td>
            <td class="px-6 py-4">${getStatusBadge(log.status)}</td>
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
    
    const formData = {
        reference: document.getElementById('docReference').value,
        title: document.getElementById('docTitle').value,
        type: document.getElementById('docType').value,
        date: document.getElementById('docDate').value,
        status: document.getElementById('docStatus').value,
        description: document.getElementById('docDescription').value,
        tags: document.getElementById('docTags').value.split(',').map(t => t.trim())
    };
    
    // Validate
    if (!formData.reference || !formData.title || !formData.type || !formData.date) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }
    
    // Create new document
    const newDoc = {
        id: AppData.documents.length + 1,
        ...formData,
        uploadedBy: AppData.currentUser.name,
        uploadedAt: new Date().toLocaleString(),
        fileSize: '1.2 MB',
        views: 0,
        downloads: 0
    };
    
    AppData.documents.unshift(newDoc);
    
    // Close modal and reset form
    closeModal('upload-modal');
    document.getElementById('uploadForm').reset();
    
    // Show success notification
    showNotification('Document uploaded successfully', 'success');
    
    // Add audit log
    addAuditLog('upload', `Uploaded document ${formData.reference}`);
    
    // Refresh if on documents page
    if (document.getElementById('documentsList')) {
        filterDocuments();
    }
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

async function loadNotifications() {
    const notifsList = document.getElementById('notifications-list');
    if (!notifsList) {
        console.warn('notifications-list element not found');
        return;
    }

    try {
        const res = await fetch('API/notifications_api.php?action=list&limit=30', {
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
            '<div class="p-6 text-center text-gray-500"><p>🎉 No notifications</p><p class="text-xs mt-2">You\'re all caught up!</p></div>' :
            sortedNotifs.map(notif => `
            <div data-id="${notif.id}" class="p-4 border-b border-gray-100 transition hover:bg-gray-50 ${!notif.read ? 'bg-blue-50 border-l-4 border-l-blue-500' : ''}" style="cursor: pointer;">
                <div class="flex items-start gap-3">
                    <div class="text-2xl flex-shrink-0" title="Priority: ${notif.priority}">
                        ${priorityIcons[notif.priority] || '🔵'}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h4 class="text-sm font-semibold text-gray-900">${escapeHtml(notif.title)}</h4>
                            <span class="text-xs px-2 py-0.5 bg-gray-200 text-gray-700 rounded-full flex-shrink-0">${escapeHtml(notif.category || 'general')}</span>
                        </div>
                        <p class="text-xs text-gray-700 line-clamp-2 mb-2">${escapeHtml(notif.message)}</p>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-500">🕐 ${escapeHtml(notif.time)}</span>
                            <div class="flex gap-1">
                                <button onclick="event.stopPropagation(); toggleNotificationRead(${notif.id}, ${notif.read ? 0 : 1}); return false;" class="text-xs px-2 py-0.5 text-gray-600 hover:bg-white rounded transition">${notif.read ? 'Unread' : 'Read'}</button>
                                <button onclick="event.stopPropagation(); deleteNotification(${notif.id}); return false;" class="text-xs px-2 py-0.5 text-red-600 hover:bg-red-50 rounded transition">✕</button>
                            </div>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-gray-400 flex-shrink-0" style="cursor: pointer;"></i>
                </div>
            </div>
        `).join('');

        notifsList.querySelectorAll('[data-id]').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!e.target.closest('button')) {
                    const id = parseInt(this.getAttribute('data-id'));
                    viewNotification(id);
                }
            });
        });
    } catch (e) {
        const details = e && e.message ? String(e.message) : 'Unknown error';
        notifsList.innerHTML = `<div class="p-6 text-center text-red-600 text-sm">Failed to load notifications.<div class="text-xs text-gray-500 mt-2">${escapeHtml(details)}</div></div>`;
        const badge = document.getElementById('notif-badge') || document.getElementById('notification-badge');
        if (badge) badge.classList.add('hidden');
    }
}

function toggleNotifications() {
    const dd = document.getElementById('notifications-dropdown');
    if (!dd) {
        console.warn('Notifications dropdown not found');
        return;
    }
    
    // Toggle visibility
    const isHidden = dd.classList.contains('hidden');
    dd.classList.toggle('hidden');
    
    // If opening, reload notifications
    if (isHidden) {
        loadNotifications();
    }
}

function viewNotification(id) {
    const notif = AppData.notifications.find(n => n.id === id);
    if (!notif) {
        console.error('Notification not found:', id);
        return;
    }

    // Mark as read (best-effort)
    toggleNotificationRead(id, 1).finally(() => {
        openNotificationModal(id);
    });
}

function openNotificationModal(id) {
    const notif = AppData.notifications.find(n => n.id === id);
    if (!notif) return;

    // Create modal container if not present
    let modal = document.getElementById('notif-detail-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'notif-detail-modal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-lg w-11/12 md:w-2/3 lg:w-1/2 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 id="notif-detail-title" class="text-lg font-bold text-gray-800"></h3>
                            <span id="notif-priority-badge" class="inline-block px-2 py-1 text-xs font-semibold rounded-full"></span>
                        </div>
                        <p id="notif-detail-time" class="text-xs text-gray-500"></p>
                        <p id="notif-detail-category" class="text-xs text-gray-400 mt-1"></p>
                    </div>
                    <button id="notif-detail-close" class="text-gray-500 hover:text-gray-800 text-2xl">✕</button>
                </div>
                <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p id="notif-detail-message" class="text-gray-700 leading-relaxed"></p>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button id="notif-detail-action" class="btn-primary hidden"></button>
                    <button id="notif-detail-open" class="btn-primary">Open Related Page</button>
                    <button id="notif-detail-dismiss" class="btn-outline">Close</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close handlers
        modal.querySelector('#notif-detail-close').addEventListener('click', () => closeNotificationModal());
        modal.querySelector('#notif-detail-dismiss').addEventListener('click', () => closeNotificationModal());
    }

    // Priority badge colors
    const priorityColors = {
        critical: 'bg-red-100 text-red-800',
        high: 'bg-orange-100 text-orange-800',
        normal: 'bg-blue-100 text-blue-800',
        low: 'bg-gray-100 text-gray-800'
    };

    // Fill content
    document.getElementById('notif-detail-title').textContent = notif.title;
    document.getElementById('notif-detail-time').textContent = '📅 ' + notif.time;
    document.getElementById('notif-detail-category').textContent = '📁 Category: ' + (notif.category || 'general').toUpperCase();
    document.getElementById('notif-detail-message').textContent = notif.message;

    // Priority badge
    const badge = document.getElementById('notif-priority-badge');
    badge.textContent = (notif.priority || 'normal').toUpperCase();
    badge.className = 'inline-block px-2 py-1 text-xs font-semibold rounded-full ' + (priorityColors[notif.priority] || priorityColors.normal);

    // Action button
    const actionBtn = document.getElementById('notif-detail-action');
    if (notif.action) {
        actionBtn.textContent = notif.action;
        actionBtn.classList.remove('hidden');
        actionBtn.onclick = function() {
            closeNotificationModal();
            if (notif.category === 'documents') showSection('documents');
            else if (notif.category === 'feedback') showSection('feedback-collection');
            else if (notif.category === 'users') showSection('users');
            else if (notif.category === 'system') showSection('audit');
            else showSection('public-consultation');
        };
    } else {
        actionBtn.classList.add('hidden');
    }

    // Open action
    const openBtn = document.getElementById('notif-detail-open');
    openBtn.onclick = function() {
        closeNotificationModal();
        if (notif.type === 'document' || notif.type === 'approval') showSection('documents');
        else if (notif.type === 'user') showSection('users');
        else if (notif.type === 'feedback') showSection('feedback-collection');
        else if (notif.type === 'alert' || notif.type === 'system') showSection('audit');
        else showSection('public-consultation');
    };

    // Show modal
    modal.classList.remove('hidden');
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

function renderPublicConsultation() {
    // Update page title and breadcrumb
    const pageTitle = document.querySelector('.page-title');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    if (pageTitle) pageTitle.textContent = 'Public Consultation';
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Public Consultation';

    const totalConsults = AppData.consultations.length;
    const draftConsults = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'draft').length;
    const activeConsults = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'active').length;
    const closedConsults = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'closed').length;
    const totalFeedback = AppData.feedback.length;
    const avgFeedback = totalConsults > 0 ? Math.round(totalFeedback / totalConsults) : 0;
    const totalDocuments = AppData.consultations.reduce((sum, c) => sum + (c.documentsAttached || 0), 0);

    const html = `
        <div class="space-y-6">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-lg shadow-lg p-8 text-white">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Public Consultation Dashboard</h1>
                        <p class="text-red-100">Manage consultations, track feedback, and monitor community engagement</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="showSection('consultation-management')" class="btn-primary flex items-center gap-2 bg-white text-red-600 hover:bg-red-50">
                            <i class="bi bi-plus-lg"></i> Manage
                        </button>
                        <button onclick="showSection('feedback-collection')" class="btn-primary flex items-center gap-2 bg-white text-red-600 hover:bg-red-50">
                            <i class="bi bi-chat-left-quote"></i> Feedback
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition border-l-4 border-red-600">
                    <div class="text-gray-500 text-xs font-semibold uppercase mb-1">Total Consultations</div>
                    <div class="text-3xl font-bold text-gray-900">${totalConsults}</div>
                    <div class="text-xs text-gray-400 mt-2">All time</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition border-l-4 border-green-600">
                    <div class="text-gray-500 text-xs font-semibold uppercase mb-1">Active</div>
                    <div class="text-3xl font-bold text-green-600">${activeConsults}</div>
                    <div class="text-xs text-gray-400 mt-2">In progress</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition border-l-4 border-blue-600">
                    <div class="text-gray-500 text-xs font-semibold uppercase mb-1">Draft</div>
                    <div class="text-3xl font-bold text-blue-600">${draftConsults}</div>
                    <div class="text-xs text-gray-400 mt-2">User submissions</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition border-l-4 border-gray-600">
                    <div class="text-gray-500 text-xs font-semibold uppercase mb-1">Closed</div>
                    <div class="text-3xl font-bold text-gray-600">${closedConsults}</div>
                    <div class="text-xs text-gray-400 mt-2">Completed</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition border-l-4 border-purple-600">
                    <div class="text-gray-500 text-xs font-semibold uppercase mb-1">Total Feedback</div>
                    <div class="text-3xl font-bold text-purple-600">${totalFeedback}</div>
                    <div class="text-xs text-gray-400 mt-2">Avg: ${avgFeedback}/consult</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition border-l-4 border-orange-600">
                    <div class="text-gray-500 text-xs font-semibold uppercase mb-1">Documents</div>
                    <div class="text-3xl font-bold text-orange-600">${totalDocuments}</div>
                    <div class="text-xs text-gray-400 mt-2">Attached</div>
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

            <!-- Recent Activity + Analytics Section (3 columns) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Recent Feedback -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-gray-900">Recent Feedback</h3>
                        <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded-full">${totalFeedback} Total</span>
                    </div>
                    <div class="space-y-2 max-h-64 overflow-y-auto" id="recent-feedback-list">
                    </div>
                </div>

                <!-- Draft Submissions -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-gray-900">Draft Submissions</h3>
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">${draftConsults} Draft</span>
                    </div>
                    <div class="space-y-2 max-h-64 overflow-y-auto" id="upcoming-list">
                    </div>
                </div>

                <!-- Consultation Status Chart -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-sm font-bold text-gray-900 mb-3">Status Distribution</h3>
                    <div style="height: 240px;">
                        <canvas id="pcStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('content-area').innerHTML = html;

    // Populate sections
    renderConsultationsGrid();
    renderRecentFeedbackList();
    renderUpcomingList();
    
    // Render chart
    setTimeout(() => renderPCStatusChart(), 120);

    // Ensure Recent Feedback reflects real DB data
    try {
        const list = document.getElementById('recent-feedback-list');
        if (list) {
            list.innerHTML = '<p class="text-gray-500 text-sm">Loading feedback...</p>';
        }
        loadFeedbackFromApi().then(() => {
            renderRecentFeedbackList();
        });
    } catch (e) {
        console.error(e);
    }
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
                        <p class="text-sm text-gray-600 mt-1">Open Public Consultation or Feedback Collection from the menu.</p>
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
        const statusLabel = stRaw === 'active' ? 'Active' : (stRaw === 'draft' ? 'Draft' : (stRaw === 'closed' ? 'Closed' : stRaw));
        const statusColor = stRaw === 'active' ? 'bg-green-100 text-green-800' :
                           stRaw === 'draft' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800';

        const srcType = String(c.type || '').toLowerCase();
        const typeIcon = srcType === 'user' ? 'bi-person-badge' : 'bi-building';
        const typeLabel = srcType === 'user' ? 'User Submission' : 'Admin Created';

        const dateText = c.date ? new Date(c.date).toLocaleDateString() : '-';

        return `
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-red-500 to-red-600 h-2"></div>
                <div class="p-5">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-gray-900 flex-1">${c.title}</h4>
                        <span class="px-2 py-1 rounded text-xs font-semibold ${statusColor}">
                            ${statusLabel}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 mb-3">
                        <i class="bi ${typeIcon}"></i>
                        <span>${typeLabel}</span>
                        <span>•</span>
                        <span>${dateText}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <div class="bg-gray-50 p-2 rounded">
                            <div class="text-xs text-gray-500">Feedback</div>
                            <div class="text-lg font-bold text-red-600">${c.feedbackCount || 0}</div>
                        </div>
                        <div class="bg-gray-50 p-2 rounded">
                            <div class="text-xs text-gray-500">Documents</div>
                            <div class="text-lg font-bold text-blue-600">${c.documentsAttached || 0}</div>
                        </div>
                    </div>
                    <button onclick="openConsultationDetailsFromDashboard(${c.id})" class="w-full text-center text-red-600 hover:text-red-700 font-semibold text-sm">
                        View Details →
                    </button>
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
    const upcoming = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'draft').slice(0, 5);

    if (upcoming.length === 0) {
        list.innerHTML = '<p class="text-gray-500 text-sm">No upcoming consultations</p>';
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
        switch(sortBy) {
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
}

function resetPublicConsultationFilters() {
    document.getElementById('pc-search').value = '';
    document.getElementById('pc-status-filter').value = '';
    document.getElementById('pc-type-filter').value = '';
    document.getElementById('pc-sort').value = 'date-desc';
    renderConsultationsGrid();
}

function renderPCStatusChart() {
    const ctx = document.getElementById('pcStatusChart');
    if (!ctx) return;

    const active = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'active').length;
    const draft = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'draft').length;
    const closed = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'closed').length;

    const labelPlugin = {
        id: 'pcDoughnutLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const dataset = chart.data.datasets && chart.data.datasets[0] ? chart.data.datasets[0] : null;
            if (!dataset || !dataset.data) return;
            const meta = chart.getDatasetMeta(0);
            const data = dataset.data.map(v => Number(v) || 0);
            const total = data.reduce((a, b) => a + b, 0);

            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#111827';

            meta.data.forEach((arc, i) => {
                const v = data[i] || 0;
                if (!v || !arc) return;
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
        try { window.pcStatusChart.destroy(); } catch (e) {}
    }

    window.pcStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Draft', 'Closed'],
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
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = Array.isArray(context.dataset?.data) ? context.dataset.data.reduce((a, b) => (Number(a) || 0) + (Number(b) || 0), 0) : 0;
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
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
        try { window.pcFeedbackChart.destroy(); } catch(e) {}
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
    
    const pageTitle = document.querySelector('.page-title');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    
    if (pageTitle) pageTitle.textContent = 'Consultation Management';
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Consultation Management';

    const totalConsultations = AppData.consultations.length;
    const openConsultations = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'active').length;
    const scheduledConsultations = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'closed').length;

    contentArea.innerHTML = `
        <div class="space-y-6">
            <!-- Header with Statistics -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Consultation Management</h1>
                        <p class="text-red-100">Manage all public consultations, track feedback, and monitor engagement</p>
                    </div>
                    <button onclick="openCreateConsultationModal()" class="btn-primary flex items-center gap-2 bg-white text-red-600 hover:bg-red-50">
                        <i class="bi bi-plus-lg"></i> New Consultation
                    </button>
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
                        <div class="text-red-100 text-sm font-semibold mb-1">Scheduled</div>
                        <div class="text-3xl font-bold" id="cm-stat-scheduled">${scheduledConsultations}</div>
                    </div>
                </div>
            </div>

            <!-- Filter and Search -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                            <option value="draft">Draft (User Submission)</option>
                            <option value="active">Active</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
                        <select id="consultation-type-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterConsultations()">
                            <option value="">All Types</option>
                            <option value="admin">Admin Created</option>
                            <option value="user">User Submission</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
                        <select id="consultation-sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterConsultations()">
                            <option value="date-desc">Latest First</option>
                            <option value="date-asc">Oldest First</option>
                            <option value="feedback">Most Feedback</option>
                            <option value="title">A-Z Title</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-4">
                    <button onclick="cmQuickType('')" class="btn-outline px-3 py-2 text-sm">All</button>
                    <button onclick="cmQuickType('admin')" class="btn-outline px-3 py-2 text-sm">Admin Created</button>
                    <button onclick="cmQuickType('user')" class="btn-outline px-3 py-2 text-sm">User Submissions</button>
                </div>
            </div>

            <!-- Consultations Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 border-b-2 border-gray-300">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Title</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Date</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Feedback</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Documents</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="consultations-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create/Edit Consultation Modal -->
        <div id="consultation-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-96 overflow-y-auto">
                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center">
                    <h2 id="modal-title" class="text-2xl font-bold">Create New Consultation</h2>
                    <button onclick="closeConsultationModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="consultation-id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Title *</label>
                            <input type="text" id="consultation-title" placeholder="Consultation title" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Type *</label>
                            <select id="consultation-type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                                <option value="">Select Type</option>
                                <option value="In-person">In-person</option>
                                <option value="Online">Online</option>
                                <option value="Survey">Survey</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Date *</label>
                            <input type="date" id="consultation-date" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                            <select id="consultation-status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                                <option value="">Select Status</option>
                                <option value="Open">Open</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                        <textarea id="consultation-description" placeholder="Consultation description..." rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button onclick="saveConsultation()" class="flex-1 btn-primary">Save Consultation</button>
                        <button onclick="closeConsultationModal()" class="flex-1 btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultation Details Modal -->
        <div id="consultation-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-96 overflow-y-auto">
                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center">
                    <h2 id="details-modal-title" class="text-2xl font-bold">Consultation Details</h2>
                    <button onclick="closeDetailsModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>
                </div>
                <div id="details-modal-content" class="p-6 space-y-4">
                </div>
            </div>
        </div>
    `;

    const tbody = document.getElementById('consultations-table-body');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Loading consultations...</td></tr>';
    }

    loadConsultationsFromApi();
}

function cmQuickType(type) {
    const sel = document.getElementById('consultation-type-filter');
    if (!sel) return;
    sel.value = type;
    filterConsultations();
}

function mapDbConsultationToUi(row) {
    const statusRaw = String(row.status || '').toLowerCase();
    const createdAt = row.created_at || null;
    const startDate = row.start_date || null;
    const endDate = row.end_date || null;
    const effectiveDate = startDate || createdAt || endDate || null;

    const sourceType = statusRaw === 'draft' ? 'user' : 'admin';
    const title = row.title || '';

    return {
        id: Number(row.id),
        title,
        type: sourceType,
        date: effectiveDate,
        status: statusRaw || 'draft',
        description: row.description || '',
        category: row.category || '',
        user_name: row.user_name || '',
        user_email: row.user_email || '',
        feedbackCount: Number(row.posts_count || 0),
        documentsAttached: 0
    };
}

async function loadConsultationsFromApi() {
    try {
        const res = await fetch('API/consultations_api.php?action=list&limit=200&offset=0', {
            headers: { 'Accept': 'application/json' }
        });

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
        recomputeConsultationFeedbackCounts();
        updateConsultationStatsUI();
        renderConsultationsTable();

        if (data.data.length === 0) {
            const tbody = document.getElementById('consultations-table-body');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No consultations returned by API. Checking connection...</td></tr>';
            }
            try {
                const dbgRes = await fetch('API/consultations_api.php?action=debug', { headers: { 'Accept': 'application/json' } });
                const dbg = await dbgRes.json();
                window.__last_consultations_debug__ = dbg;
                const dbName = dbg?.data?.db?.database ?? 'unknown';
                const cnt = dbg?.data?.db?.consultations_count;
                const role = dbg?.data?.session?.role_normalized ?? dbg?.data?.session?.role ?? 'unknown';
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-red-600">API returned 0 rows, but debug says DB has <b>${escapeHtml(String(cnt))}</b> consultations (DB: <b>${escapeHtml(String(dbName))}</b>, role: <b>${escapeHtml(String(role))}</b>).<div class="text-xs text-gray-500 mt-2">This means the list query is not returning rows as expected. Next step is to inspect the SQL query output.</div></td></tr>`;
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

function renderConsultationsTable() {
    const tbody = document.getElementById('consultations-table-body');
    const consultations = getFilteredConsultations();

    if (consultations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No consultations found</td></tr>';
        return;
    }

    tbody.innerHTML = consultations.map(consultation => {
        const st = String(consultation.status || '').toLowerCase();
        const statusColor = st === 'active' ? 'bg-green-100 text-green-800' : (st === 'closed' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800');

        const srcType = String(consultation.type || '').toLowerCase();
        const dateText = consultation.date ? new Date(consultation.date).toLocaleDateString() : '-';

        const isUserSubmission = srcType === 'user';
        const userEmail = String(consultation.userEmail || '').trim();
        const mailtoSubject = encodeURIComponent('Regarding your Public Consultation submission');
        const mailtoBody = encodeURIComponent(
            `Hello ${String(consultation.userName || 'there')},\n\n` +
            `We received your consultation submission titled: ${String(consultation.title || '')}\n` +
            `Reference ID: ${String(consultation.id || '')}\n\n` +
            `Message:\n`
        );
        const mailtoHref = userEmail ? `mailto:${encodeURIComponent(userEmail)}?subject=${mailtoSubject}&body=${mailtoBody}` : '';

        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-semibold text-gray-900">${consultation.title}</td>
                <td class="px-6 py-4 text-gray-600">${dateText}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                        ${st ? (st.charAt(0).toUpperCase() + st.slice(1)) : 'Draft'}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-red-100 text-red-600 rounded-full font-semibold text-sm">
                        ${consultation.feedbackCount || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center gap-1 text-gray-600">
                        <i class="bi bi-file-text"></i>
                        ${consultation.documentsAttached || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <div class="flex gap-2 justify-center">
                        <button onclick="viewConsultationDetails(${consultation.id})" class="text-blue-600 hover:text-blue-800" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${isUserSubmission && mailtoHref ? `
                            <a href="${mailtoHref}" class="text-green-600 hover:text-green-800" title="Email User">
                                <i class="bi bi-envelope"></i>
                            </a>
                        ` : ''}
                        ${isUserSubmission ? '' : `
                            <button onclick="editConsultation(${consultation.id})" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function getFilteredConsultations() {
    let filtered = [...AppData.consultations];
    
    const searchTerm = document.getElementById('consultation-search')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('consultation-status-filter')?.value || '';
    const typeFilter = document.getElementById('consultation-type-filter')?.value || '';
    const sortBy = document.getElementById('consultation-sort')?.value || 'date-desc';

    if (searchTerm) {
        filtered = filtered.filter(c => c.title.toLowerCase().includes(searchTerm));
    }
    
    if (statusFilter) {
        filtered = filtered.filter(c => c.status === statusFilter);
    }
    
    if (typeFilter) {
        filtered = filtered.filter(c => String(c.type || '').toLowerCase() === String(typeFilter).toLowerCase());
    }

    // Sort
    filtered.sort((a, b) => {
        switch(sortBy) {
            case 'date-asc':
                return new Date(a.date || 0) - new Date(b.date || 0);
            case 'feedback':
                return (b.feedbackCount || 0) - (a.feedbackCount || 0);
            case 'title':
                return a.title.localeCompare(b.title);
            case 'date-desc':
            default:
                return new Date(b.date || 0) - new Date(a.date || 0);
        }
    });

    return filtered;
}

function filterConsultations() {
    renderConsultationsTable();
}

function openCreateConsultationModal() {
    document.getElementById('consultation-id').value = '';
    document.getElementById('modal-title').textContent = 'Create New Consultation';
    document.getElementById('consultation-title').value = '';
    document.getElementById('consultation-type').value = '';
    document.getElementById('consultation-date').value = '';
    document.getElementById('consultation-status').value = '';
    document.getElementById('consultation-description').value = '';
    document.getElementById('consultation-modal').classList.remove('hidden');
}

function closeConsultationModal() {
    document.getElementById('consultation-modal').classList.add('hidden');
}

function editConsultation(id) {
    const consultation = AppData.consultations.find(c => c.id === id);
    if (!consultation) return;

    if (String(consultation.type || '').toLowerCase() === 'user') {
        showNotification('User-submitted consultations cannot be edited by admin.', 'error');
        return;
    }

    document.getElementById('consultation-id').value = id;
    document.getElementById('modal-title').textContent = 'Edit Consultation';
    document.getElementById('consultation-title').value = consultation.title;
    document.getElementById('consultation-type').value = consultation.type;
    document.getElementById('consultation-date').value = consultation.date;
    document.getElementById('consultation-status').value = consultation.status;
    document.getElementById('consultation-description').value = consultation.description || '';
    document.getElementById('consultation-modal').classList.remove('hidden');
}

function saveConsultation() {
    const id = document.getElementById('consultation-id').value;
    const title = document.getElementById('consultation-title').value.trim();
    const type = document.getElementById('consultation-type').value;
    const date = document.getElementById('consultation-date').value;
    const status = document.getElementById('consultation-status').value;
    const description = document.getElementById('consultation-description').value.trim();

    if (!title || !type || !date || !status) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }

    if (id) {
        // Update existing
        const index = AppData.consultations.findIndex(c => c.id === parseInt(id));
        if (index !== -1) {
            AppData.consultations[index] = {
                ...AppData.consultations[index],
                title,
                type,
                date,
                status,
                description
            };
            showNotification('Consultation updated successfully', 'success');
        }
    } else {
        // Create new
        const newConsultation = {
            id: Math.max(...AppData.consultations.map(c => c.id), 0) + 1,
            title,
            type,
            date,
            status,
            description,
            feedbackCount: 0,
            documentsAttached: 0
        };
        AppData.consultations.push(newConsultation);
        showNotification('Consultation created successfully', 'success');
    }

    closeConsultationModal();
    renderConsultationsTable();
}

function deleteConsultation(id) {
    showNotification('Delete is disabled to prevent data loss.', 'error');
}

function viewConsultationDetails(id) {
    const consultation = AppData.consultations.find(c => c.id === id);
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

    const relatedFeedback = AppData.feedback.filter(f => f.consultationId === id);
    const feedbackHTML = relatedFeedback.length > 0 
        ? relatedFeedback.map(f => `
            <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-red-500">
                <div class="font-semibold text-gray-900">${f.author}</div>
                <div class="text-gray-600 text-sm mt-1">${f.message}</div>
                <div class="text-gray-400 text-xs mt-2">${f.date}</div>
            </div>
        `).join('')
        : '<p class="text-gray-500">No feedback yet</p>';

    const st = String(consultation.status || '').toLowerCase();
    const statusColor = st === 'active' ? 'bg-green-100 text-green-800' : (st === 'draft' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800');
    const statusLabel = st ? (st.charAt(0).toUpperCase() + st.slice(1)) : 'Draft';

    const isUserSubmission = String(consultation.type || '').toLowerCase() === 'user';
    const userEmail = String(consultation.userEmail || '').trim();
    const mailtoSubject = encodeURIComponent('Regarding your Public Consultation submission');
    const mailtoBody = encodeURIComponent(
        `Hello ${String(consultation.userName || 'there')},\n\n` +
        `We received your consultation submission titled: ${String(consultation.title || '')}\n` +
        `Reference ID: ${String(consultation.id || '')}\n\n` +
        `Message:\n`
    );
    const mailtoHref = userEmail ? `mailto:${encodeURIComponent(userEmail)}?subject=${mailtoSubject}&body=${mailtoBody}` : '';

    titleEl.textContent = consultation.title;
    contentEl.innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Type</label>
                <p class="text-gray-900 font-semibold mt-1">${consultation.type}</p>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Date</label>
                <p class="text-gray-900 font-semibold mt-1">${new Date(consultation.date).toLocaleDateString()}</p>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Status</label>
                <p class="mt-1"><span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">${statusLabel}</span></p>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Feedback Count</label>
                <p class="text-gray-900 font-semibold mt-1">${consultation.feedbackCount || 0}</p>
            </div>
        </div>

        ${consultation.description ? `
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Description</label>
                <p class="text-gray-700 mt-2">${consultation.description}</p>
            </div>
        ` : ''}

        <div>
            <label class="text-xs font-semibold text-gray-500 uppercase mb-3 block">Feedback Responses</label>
            <div class="space-y-3">${feedbackHTML}</div>
        </div>

        <div class="flex gap-2 pt-4 border-t">
            ${isUserSubmission ? '' : `<button onclick="editConsultation(${consultation.id}); closeDetailsModal()" class="flex-1 btn-primary">Edit</button>`}
            ${isUserSubmission && mailtoHref ? `<a href="${mailtoHref}" class="flex-1 btn-primary text-center">Email User</a>` : ''}
            <button onclick="closeDetailsModal()" class="flex-1 btn-secondary">Close</button>
        </div>
    `;
    modalEl.classList.remove('hidden');
}

function closeDetailsModal() {
    document.getElementById('consultation-details-modal').classList.add('hidden');
}

function renderFeedbackCollection() {
    const contentArea = document.getElementById('content-area');
    
    const pageTitle = document.querySelector('.page-title');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    
    if (pageTitle) pageTitle.textContent = 'Feedback Collection';
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
                    <button onclick="runBatchSentimentAnalysis()" class="btn-primary flex items-center gap-2 bg-white text-red-600 hover:bg-red-50 text-sm">
                        <i class="bi bi-cpu"></i> Analyze All Sentiment
                    </button>
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
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Sentiment</th>
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
        const res = await fetch('API/feedback_api.php?action=list&limit=200&offset=0', {
            headers: { 'Accept': 'application/json' }
        });

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
                const dbgRes = await fetch('API/feedback_api.php?action=debug', { headers: { 'Accept': 'application/json' } });
                const dbg = await dbgRes.json();
                window.__last_feedback_debug__ = dbg;
                const dbName = dbg?.data?.db?.database ?? 'unknown';
                const cnt = dbg?.data?.db?.feedback_count;
                const role = dbg?.data?.session?.role_normalized ?? dbg?.data?.session?.role ?? 'unknown';
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-red-600">API returned 0 rows, but debug says DB has <b>${escapeHtml(String(cnt))}</b> feedback (DB: <b>${escapeHtml(String(dbName))}</b>, role: <b>${escapeHtml(String(role))}</b>).<div class="text-xs text-gray-500 mt-2">This means the list query is not returning rows as expected.</div></td></tr>`;
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
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No feedback found</td></tr>`;
        return;
    }

    tbody.innerHTML = feedbackList.map(feedback => {
        const consultation = AppData.consultations.find(c => c.id === feedback.consultationId);
        const consultationTitle = consultation ? consultation.title : 'Unknown Consultation';
        const isOverdue = isFeedbackOverdue(feedback, 3);
        const rowClass = isOverdue ? 'bg-red-50' : '';
        const dateText = feedback.date ? new Date(feedback.date).toLocaleDateString() : '-';

        // Sentiment badge
        const sent = feedback.sentimentTag || '';
        let sentimentBadge = '<span class="text-xs text-gray-400 italic">Not analyzed</span>';
        if (sent === 'positive') {
            sentimentBadge = '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-emoji-smile"></i> Positive</span>';
        } else if (sent === 'negative') {
            sentimentBadge = '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800"><i class="bi bi-emoji-frown"></i> Negative</span>';
        } else if (sent === 'neutral') {
            sentimentBadge = '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700"><i class="bi bi-emoji-neutral"></i> Neutral</span>';
        }

        return `
            <tr class="border-b hover:bg-gray-50 transition ${rowClass}">
                <td class="px-6 py-4 font-semibold text-gray-900">${escapeHtml(feedback.author)}</td>
                <td class="px-6 py-4 text-gray-700 max-w-xs truncate" title="${escapeHtml(feedback.message)}">${escapeHtml(feedback.message)}</td>
                <td class="px-6 py-4">${sentimentBadge}</td>
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
                        <button onclick="analyzeSingleFeedback(${feedback.id})" class="text-purple-600 hover:text-purple-800" title="Analyze Sentiment">
                            <i class="bi bi-cpu"></i>
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

        <!-- Sentiment Analysis Section -->
        <div class="border-t pt-4">
            <div class="flex items-center justify-between mb-3">
                <label class="text-xs font-semibold text-gray-500 uppercase flex items-center gap-2">
                    <i class="bi bi-cpu text-purple-600"></i> AI Sentiment Analysis
                </label>
                <button onclick="analyzeSingleFeedback(${f.id}, true)" class="text-xs px-3 py-1 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-semibold">
                    <i class="bi bi-arrow-repeat mr-1"></i> Analyze
                </button>
            </div>
            <div id="sentiment-result-${f.id}" class="p-3 bg-purple-50 rounded-lg border border-purple-100">
                ${f.sentimentTag
                    ? `<div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold ${
                            f.sentimentTag === 'positive' ? 'bg-green-100 text-green-800' :
                            f.sentimentTag === 'negative' ? 'bg-red-100 text-red-800' :
                            'bg-gray-100 text-gray-700'
                        }">
                            <i class="bi ${f.sentimentTag === 'positive' ? 'bi-emoji-smile' : f.sentimentTag === 'negative' ? 'bi-emoji-frown' : 'bi-emoji-neutral'}"></i>
                            ${f.sentimentTag.charAt(0).toUpperCase() + f.sentimentTag.slice(1)}
                        </span>
                        ${f.sentimentScore !== null ? '<span class="text-xs text-gray-500">Score: ' + f.sentimentScore + '</span>' : ''}
                    </div>`
                    : '<p class="text-sm text-purple-700 italic">Click "Analyze" to run sentiment analysis on this feedback.</p>'
                }
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Update Status</label>
                <select id="fb-status-select" class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                    <option value="new" ${st === 'new' ? 'selected' : ''}>new</option>
                    <option value="in_review" ${st === 'in_review' ? 'selected' : ''}>in_review</option>
                    <option value="responded" ${st === 'responded' ? 'selected' : ''}>responded</option>
                    <option value="resolved" ${st === 'resolved' ? 'selected' : ''}>resolved</option>
                </select>
            </div>
            <div class="flex items-end">
                <button onclick="updateFeedbackStatus(${f.id})" class="w-full btn-primary">Save Status</button>
            </div>
        </div>

        <div class="flex gap-2 pt-4 border-t">
            ${mailtoHref ? `<a href="${mailtoHref}" class="flex-1 btn-primary text-center">Email User</a>` : ''}
            <button onclick="closeFeedbackDetailsModal()" class="flex-1 btn-secondary">Close</button>
        </div>
    `;

    modal.classList.remove('hidden');
}

async function updateFeedbackStatus(id) {
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
        switch(sortBy) {
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

        // Save to DB
        fetch('API/sentiment_api.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ feedback_id: id, sentiment: result.sentiment, score: result.score })
        }).catch(() => {});

        // Update modal result area
        if (resultEl) {
            const badgeClass = result.sentiment === 'positive' ? 'bg-green-100 text-green-800' :
                              result.sentiment === 'negative' ? 'bg-red-100 text-red-800' :
                              'bg-gray-100 text-gray-700';
            const icon = result.sentiment === 'positive' ? 'bi-emoji-smile' :
                        result.sentiment === 'negative' ? 'bi-emoji-frown' : 'bi-emoji-neutral';

            let keywordsHtml = '';
            if (result.keywords && result.keywords.length > 0) {
                keywordsHtml = '<div class="mt-2 flex flex-wrap gap-1">' +
                    result.keywords.map(k => {
                        const kwClass = k.score > 0 ? 'bg-green-50 text-green-700 border-green-200' :
                                       k.score < 0 ? 'bg-red-50 text-red-700 border-red-200' :
                                       'bg-gray-50 text-gray-600 border-gray-200';
                        return `<span class="text-xs px-2 py-0.5 rounded border ${kwClass}">${escapeHtml(k.word)} (${k.score > 0 ? '+' : ''}${k.score})</span>`;
                    }).join('') +
                    '</div>';
            }

            resultEl.innerHTML = `
                <div class="flex items-center gap-3 mb-2">
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold ${badgeClass}">
                        <i class="bi ${icon}"></i>
                        ${result.sentiment.charAt(0).toUpperCase() + result.sentiment.slice(1)}
                    </span>
                    <span class="text-xs text-gray-500">Score: ${result.score}</span>
                    <span class="text-xs text-gray-500">Confidence: ${Math.round(result.confidence * 100)}%</span>
                </div>
                ${keywordsHtml ? '<label class="text-xs font-semibold text-gray-500">Detected Keywords:</label>' + keywordsHtml : ''}
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

        // Update local data and save each to DB
        let savePromises = [];
        for (const result of data.data) {
            const f = AppData.feedback.find(x => x.id === result.id);
            if (f) {
                f.sentimentTag = result.sentiment;
                f.sentimentScore = result.score;
            }
            savePromises.push(
                fetch('API/sentiment_api.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ feedback_id: result.id, sentiment: result.sentiment, score: result.score })
                }).catch(() => {})
            );
        }

        // Wait for all saves
        await Promise.all(savePromises);

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

function renderPCDocuments() {
    const contentArea = document.getElementById('content-area');
    
    const pageTitle = document.querySelector('.page-title');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    
    if (pageTitle) pageTitle.textContent = 'Document Management';
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Document Management';

    const totalDocuments = AppData.documents.length;
    const totalSize = AppData.documents.reduce((sum, d) => sum + (d.size || 0), 0);
    const approvedDocs = AppData.documents.filter(d => d.status.toLowerCase() === 'approved').length;

    contentArea.innerHTML = `
        <div class="space-y-6">
            <!-- Header with Statistics -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Document Management</h1>
                        <p class="text-red-100">Manage all consultation documents, track uploads, and monitor approval status</p>
                    </div>
                    <button onclick="openAddDocumentModal()" class="btn-primary flex items-center gap-2 bg-white text-red-600 hover:bg-red-50">
                        <i class="bi bi-file-earmark-plus"></i> Upload Document
                    </button>
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

            <!-- Filter and Search -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search Documents</label>
                        <input type="text" id="doc-search" placeholder="Search by title or reference..." 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            onkeyup="filterDocuments()">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select id="doc-status-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterDocuments()">
                            <option value="">All Status</option>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
                        <select id="doc-type-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterDocuments()">
                            <option value="">All Types</option>
                            <option value="ordinance">Ordinance</option>
                            <option value="resolution">Resolution</option>
                            <option value="memorandum">Memorandum</option>
                            <option value="report">Report</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
                        <select id="doc-sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterDocuments()">
                            <option value="date-desc">Latest First</option>
                            <option value="date-asc">Oldest First</option>
                            <option value="title">A-Z Title</option>
                            <option value="size">Largest First</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Documents Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 border-b-2 border-gray-300">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Reference/Title</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Type</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Size</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Downloads</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Uploaded By</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="documents-table-body">
                        </tbody>
                    </table>
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
                    <div class="flex gap-3 pt-4">
                        <button onclick="saveDocument()" class="flex-1 btn-primary">Save Document</button>
                        <button onclick="closeDocumentModal()" class="flex-1 btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    renderDocumentsTable();
}

function renderDocumentsTable() {
    const tbody = document.getElementById('documents-table-body');
    const documents = getFilteredDocuments();

    if (documents.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No documents found</td></tr>`;
        return;
    }

    tbody.innerHTML = documents.map(doc => {
        const statusColor = doc.status === 'approved' ? 'bg-green-100 text-green-800' : 
                           doc.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800';
        const typeIcon = doc.type === 'ordinance' ? 'bi-file-text' : 
                        doc.type === 'resolution' ? 'bi-file-earmark' : 
                        doc.type === 'memorandum' ? 'bi-envelope' : 'bi-file-pdf';

        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="font-semibold text-gray-900">${doc.reference}</div>
                    <div class="text-gray-600 text-xs mt-1">${doc.title}</div>
                </td>
                <td class="px-6 py-4">
                    <span class="flex items-center gap-2">
                        <i class="bi ${typeIcon}"></i>
                        ${doc.type.charAt(0).toUpperCase() + doc.type.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                        ${doc.status.charAt(0).toUpperCase() + doc.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 text-center text-gray-600">${formatFileSize(doc.size || 0)}</td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-600 rounded-full font-semibold text-sm">
                        ${doc.downloads || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-600 text-sm">${doc.uploadedBy}</td>
                <td class="px-6 py-4 text-center">
                    <div class="flex gap-2 justify-center">
                        <button onclick="downloadDocument('${doc.reference}')" class="text-blue-600 hover:text-blue-800" title="Download">
                            <i class="bi bi-download"></i>
                        </button>
                        <button onclick="editDocument(${doc.id})" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button onclick="deleteDocument(${doc.id})" class="text-red-600 hover:text-red-800" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
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
        filtered = filtered.filter(d => d.type === typeFilter);
    }

    // Sort
    filtered.sort((a, b) => {
        switch(sortBy) {
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
    document.getElementById('document-id').value = '';
    document.getElementById('doc-modal-title').textContent = 'Upload New Document';
    document.getElementById('document-reference').value = '';
    document.getElementById('document-type').value = '';
    document.getElementById('document-title').value = '';
    document.getElementById('document-status').value = '';
    document.getElementById('document-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('document-modal').classList.remove('hidden');
}

function closeDocumentModal() {
    document.getElementById('document-modal').classList.add('hidden');
}

function editDocument(id) {
    const doc = AppData.documents.find(d => d.id === id);
    if (!doc) return;

    document.getElementById('document-id').value = id;
    document.getElementById('doc-modal-title').textContent = 'Edit Document';
    document.getElementById('document-reference').value = doc.reference;
    document.getElementById('document-type').value = doc.type;
    document.getElementById('document-title').value = doc.title;
    document.getElementById('document-status').value = doc.status;
    document.getElementById('document-date').value = doc.date;
    document.getElementById('document-modal').classList.remove('hidden');
}

function saveDocument() {
    const id = document.getElementById('document-id').value;
    const reference = document.getElementById('document-reference').value.trim();
    const type = document.getElementById('document-type').value;
    const title = document.getElementById('document-title').value.trim();
    const status = document.getElementById('document-status').value;
    const date = document.getElementById('document-date').value;

    if (!reference || !type || !title || !status || !date) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }

    if (id) {
        // Update existing
        const doc = AppData.documents.find(d => d.id === parseInt(id));
        if (doc) {
            doc.reference = reference;
            doc.type = type;
            doc.title = title;
            doc.status = status;
            doc.date = date;
            showNotification('Document updated successfully', 'success');
        }
    } else {
        // Create new
        const newDoc = {
            id: Math.max(...AppData.documents.map(d => d.id), 0) + 1,
            reference,
            title,
            type,
            status,
            date,
            uploadedBy: AppData.currentUser.name,
            size: Math.floor(Math.random() * 5) + 1 * 1024 * 1024,
            downloads: 0
        };
        AppData.documents.push(newDoc);
        showNotification('Document uploaded successfully', 'success');
    }

    closeDocumentModal();
    renderDocumentsTable();
}

function deleteDocument(id) {
    if (!confirm('Are you sure you want to delete this document?')) return;

    const index = AppData.documents.findIndex(d => d.id === id);
    if (index !== -1) {
        AppData.documents.splice(index, 1);
        showNotification('Document deleted successfully', 'success');
        renderDocumentsTable();
    }
}

function downloadDocument(reference) {
    const doc = AppData.documents.find(d => d.reference === reference);
    if (doc) {
        doc.downloads = (doc.downloads || 0) + 1;
        showNotification(`Document "${doc.reference}" downloaded successfully`, 'success');
        addAuditLog('download', `Downloaded document ${reference}`);
        renderDocumentsTable();
    }
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
