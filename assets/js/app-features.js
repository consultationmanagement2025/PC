// ==============================
// PCMS SYSTEM - FULL FEATURES
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

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    initializeData();
    showSection('public-consultation');
    
    // Delay notification loading slightly to ensure DOM is ready
    setTimeout(function() {
        loadNotifications();
    }, 100);
    
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
            case 'user-logs':
                renderUserLogs();
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
                        <i class="bi bi-people mr-2"></i>Manage Users
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
    filterDocuments();
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
    const pageTitle = document.querySelector('.page-title');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    
    if (pageTitle) pageTitle.textContent = 'Analytics & Reports';
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'Analytics & Reports';
    
    const contentArea = document.getElementById('content-area');
    contentArea.innerHTML = '<div class="text-center py-8"><i class="bi bi-hourglass text-4xl text-gray-400 mb-4"></i><p class="text-gray-500">Loading analytics...</p></div>';
    
    // Fetch real analytics data
    fetch('API/analytics_api.php')
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                contentArea.innerHTML = '<div class="text-red-600">Error loading analytics</div>';
                return;
            }
            
            const data = result.data;
            const users = data.users || {};
            const posts = data.posts || {};
            
            const html = `
                <div class="space-y-6">
                    <!-- Header -->
                    <div class="mb-6">
                        <h1 class="text-3xl font-bold text-gray-800">Analytics & Reports</h1>
                        <p class="text-gray-600 mt-1">System activity and engagement metrics</p>
                    </div>

                    <!-- Main Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-6 shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-blue-600 text-sm font-semibold mb-1">Total Users</p>
                                    <p class="text-3xl font-bold text-gray-900">${users.total_users || 0}</p>
                                    <p class="text-xs text-blue-600 mt-2">+${users.new_users_30d || 0} this month</p>
                                </div>
                                <i class="bi bi-people text-3xl text-blue-600 opacity-20"></i>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-6 shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-green-600 text-sm font-semibold mb-1">Total Posts</p>
                                    <p class="text-3xl font-bold text-gray-900">${posts.total_posts || 0}</p>
                                    <p class="text-xs text-green-600 mt-2">+${posts.posts_30d || 0} this month</p>
                                </div>
                                <i class="bi bi-chat-dots text-3xl text-green-600 opacity-20"></i>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg p-6 shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-yellow-600 text-sm font-semibold mb-1">Active Users</p>
                                    <p class="text-3xl font-bold text-gray-900">${users.active_users || 0}</p>
                                    <p class="text-xs text-yellow-600 mt-2">${users.total_users ? Math.round((users.active_users / users.total_users) * 100) : 0}% of total</p>
                                </div>
                                <i class="bi bi-activity text-3xl text-yellow-600 opacity-20"></i>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-6 shadow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-red-600 text-sm font-semibold mb-1">Pending Posts</p>
                                    <p class="text-3xl font-bold text-gray-900">${posts.pending_posts || 0}</p>
                                    <p class="text-xs text-red-600 mt-2">Awaiting approval</p>
                                </div>
                                <i class="bi bi-exclamation-circle text-3xl text-red-600 opacity-20"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Posts Trend -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Posts Activity (Last 30 Days)</h3>
                            <canvas id="postsChart"></canvas>
                        </div>

                        <!-- Post Status Distribution -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Posts by Status</h3>
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>

                    <!-- Additional Charts -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- User Registrations -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">User Registrations (Last 30 Days)</h3>
                            <canvas id="usersChart"></canvas>
                        </div>

                        <!-- Top Contributors -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Top 5 Contributors</h3>
                            <div class="space-y-3">
                                ${(data.top_users || []).map(user => `
                                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 last:border-0">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                                <span class="text-red-600 font-bold text-sm">${user.username.substring(0, 2).toUpperCase()}</span>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-800">${user.username}</p>
                                                <p class="text-xs text-gray-500">Post contributor</p>
                                            </div>
                                        </div>
                                        <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm font-semibold">${user.post_count}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Summary</h3>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-gray-900">${users.admins || 0}</p>
                                <p class="text-xs text-gray-600 mt-1">Administrators</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-gray-900">${users.citizens || 0}</p>
                                <p class="text-xs text-gray-600 mt-1">Citizens</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-gray-900">${posts.approved_posts || 0}</p>
                                <p class="text-xs text-gray-600 mt-1">Approved Posts</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-gray-900">${posts.rejected_posts || 0}</p>
                                <p class="text-xs text-gray-600 mt-1">Rejected Posts</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-gray-900">${posts.unique_contributors || 0}</p>
                                <p class="text-xs text-gray-600 mt-1">Contributors</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            contentArea.innerHTML = html;
            
            // Render charts after content loads
            setTimeout(() => {
                renderAnalyticsCharts(data);
            }, 100);
        })
        .catch(err => {
            console.error('Error fetching analytics:', err);
            contentArea.innerHTML = '<div class="text-red-600">Error loading analytics. Check console.</div>';
        });
}

function renderAnalyticsCharts(data) {
    // Posts trend chart
    const postsChartCtx = document.getElementById('postsChart');
    if (postsChartCtx) {
        const dailyPosts = data.daily_posts || [];
        new Chart(postsChartCtx, {
            type: 'line',
            data: {
                labels: dailyPosts.map(d => new Date(d.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                datasets: [{
                    label: 'Posts',
                    data: dailyPosts.map(d => d.count),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // Status breakdown chart
    const statusChartCtx = document.getElementById('statusChart');
    if (statusChartCtx) {
        const statuses = data.status_breakdown || [];
        new Chart(statusChartCtx, {
            type: 'doughnut',
            data: {
                labels: statuses.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
                datasets: [{
                    data: statuses.map(s => s.count),
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Users registration chart
    const usersChartCtx = document.getElementById('usersChart');
    if (usersChartCtx) {
        const dailyUsers = data.daily_users || [];
        new Chart(usersChartCtx, {
            type: 'bar',
            data: {
                labels: dailyUsers.map(d => new Date(d.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                datasets: [{
                    label: 'New Users',
                    data: dailyUsers.map(d => d.count),
                    backgroundColor: '#3b82f6',
                    borderColor: '#1d4ed8',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
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
// USERS MODULE
// ==============================
function renderUsers() {
    const pageTitle = document.querySelector('.page-title');
    const breadcrumbCurrent = document.querySelector('.breadcrumb-current');
    
    if (pageTitle) pageTitle.textContent = 'User Management';
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = 'User Management';

    const contentArea = document.getElementById('content-area');
    contentArea.innerHTML = '<div class="text-center py-8"><i class="bi bi-hourglass text-4xl text-gray-400 mb-4"></i><p class="text-gray-500">Loading users...</p></div>';
    
    // Fetch user data and stats
    Promise.all([
        fetch('API/users_api.php?action=list').then(r => r.json()),
        fetch('API/users_api.php?action=stats').then(r => r.json())
    ])
    .then(([usersResult, statsResult]) => {
        if (!usersResult.success || !statsResult.success) {
            contentArea.innerHTML = '<div class="text-red-600">Error loading users</div>';
            return;
        }
        
        const users = usersResult.data || [];
        const stats = statsResult.data || {};
        
        const html = `
        <div class="space-y-6">
            <!-- Header with Statistics -->
            <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-8 rounded-lg shadow-lg">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">User Management</h1>
                        <p class="text-red-100">Manage user accounts, roles, and permissions</p>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Users</div>
                        <div class="text-3xl font-bold">${stats.total_users || 0}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Active Users</div>
                        <div class="text-3xl font-bold">${stats.active_count || 0}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Administrators</div>
                        <div class="text-3xl font-bold">${stats.admin_count || 0}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Citizens</div>
                        <div class="text-3xl font-bold">${stats.citizen_count || 0}</div>
                    </div>
                </div>
            </div>

            <!-- Filter and Search -->
                <script>console.log('ASSETS/js/app-features.js loaded: categories updated 2026-07-06');</script>
                <div class="bg-white p-6 rounded-lg shadow">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Search Users</label>
                        <input type="text" id="user-search" placeholder="Search by username or email..." 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            onkeyup="loadUsersTable()">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                        <select id="user-role-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="loadUsersTable()">
                            <option value="">All Roles</option>
                            <option value="Administrator">Administrator</option>
                            <option value="Citizen">Citizen</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                        <select id="user-status-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="loadUsersTable()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 border-b-2 border-gray-300">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Username</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Email</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Role</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Last Login</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        `;
        
        contentArea.innerHTML = html;
        loadUsersTable();
    })
    .catch(err => {
        console.error('Error loading users:', err);
        contentArea.innerHTML = '<div class="text-red-600">Error loading users</div>';
    });
}

function loadUsersTable() {
    const searchTerm = document.getElementById('user-search')?.value.toLowerCase() || '';
    const roleFilter = document.getElementById('user-role-filter')?.value || '';
    const statusFilter = document.getElementById('user-status-filter')?.value || '';
    
    fetch('API/users_api.php?action=list')
        .then(r => r.json())
        .then(result => {
            if (!result.success) return;
            
            let users = result.data || [];
            
            // Apply filters
            if (searchTerm) {
                users = users.filter(u => 
                    u.username.toLowerCase().includes(searchTerm) || 
                    u.email.toLowerCase().includes(searchTerm)
                );
            }
            
            if (roleFilter) {
                users = users.filter(u => u.role === roleFilter);
            }
            
            if (statusFilter) {
                users = users.filter(u => u.status === statusFilter);
            }
            
            const tbody = document.getElementById('users-table-body');
            if (!tbody) return;
            
            if (users.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No users found</td></tr>`;
                return;
            }
            
            tbody.innerHTML = users.map(user => {
                const statusColor = user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                const roleColor = user.role === 'Administrator' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800';
                const lastLogin = user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never';
                
                return `
                    <tr class="border-b hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-semibold text-gray-900">${user.username}</td>
                        <td class="px-6 py-4 text-gray-600 text-sm">${user.email}</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold ${roleColor}">
                                ${user.role}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                                ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">${lastLogin}</td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex gap-2 justify-center">
                                <button onclick="editUser(${user.id})" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button onclick="toggleUserStatus(${user.id}, '${user.status}')" class="text-blue-600 hover:text-blue-800" title="Toggle Status">
                                    <i class="bi ${user.status === 'active' ? 'bi-toggle-on' : 'bi-toggle-off'}"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        });
}

function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    fetch('API/users_api.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: userId, status: newStatus })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showNotification(`User status changed to ${newStatus}`, 'success');
            loadUsersTable();
        } else {
            showNotification('Error updating user status', 'error');
        }
    });
}

function editUser(userId) {
    showNotification('User editing not yet implemented', 'info');
}


function renderUsersTable() {
    const tbody = document.getElementById('users-table-body');
    const users = getFilteredUsers();

    if (users.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No users found</td></tr>`;
        return;
    }

    tbody.innerHTML = users.map(user => {
        const statusColor = user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
        const roleIcon = user.role === 'Administrator' ? 'bi-shield-lock' : 
                        user.role === 'Officer' ? 'bi-briefcase' : 
                        user.role === 'Staff' ? 'bi-person-fill' : 'bi-eye';

        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                            <span class="text-red-600 font-bold text-sm">${getInitials(user.name)}</span>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900">${user.name}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-gray-700">${user.email}</td>
                <td class="px-6 py-4">
                    <span class="flex items-center gap-2">
                        <i class="bi ${roleIcon}"></i>
                        ${user.role}
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-700">${user.department}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                        ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">${user.lastLogin}</td>
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
    const deptFilter = document.getElementById('user-dept-filter')?.value || '';

    if (searchTerm) {
        filtered = filtered.filter(u => 
            u.name.toLowerCase().includes(searchTerm) || 
            u.email.toLowerCase().includes(searchTerm)
        );
    }
    
    if (roleFilter) {
        filtered = filtered.filter(u => u.role === roleFilter);
    }
    
    if (statusFilter) {
        filtered = filtered.filter(u => u.status === statusFilter);
    }
    
    if (deptFilter) {
        filtered = filtered.filter(u => u.department === deptFilter);
    }

    return filtered;
}

function filterUsers() {
    renderUsersTable();
}

function openAddUserModal() {
    document.getElementById('user-id').value = '';
    document.getElementById('user-modal-title').textContent = 'Add New User';
    document.getElementById('user-name').value = '';
    document.getElementById('user-email').value = '';
    document.getElementById('user-role').value = '';
    document.getElementById('user-department').value = '';
    document.getElementById('user-status').value = 'active';
    document.getElementById('user-lastlogin').value = '';
    document.getElementById('user-modal').classList.remove('hidden');
}

function closeUserModal() {
    document.getElementById('user-modal').classList.add('hidden');
}

function editUserForm(id) {
    const user = AppData.users.find(u => u.id === id);
    if (!user) return;

    document.getElementById('user-id').value = id;
    document.getElementById('user-modal-title').textContent = 'Edit User';
    document.getElementById('user-name').value = user.name;
    document.getElementById('user-email').value = user.email;
    document.getElementById('user-role').value = user.role;
    document.getElementById('user-department').value = user.department;
    document.getElementById('user-status').value = user.status;
    document.getElementById('user-lastlogin').value = user.lastLogin ? user.lastLogin.replace(' ', 'T') : '';
    document.getElementById('user-modal').classList.remove('hidden');
}

function saveUser() {
    const id = document.getElementById('user-id').value;
    const name = document.getElementById('user-name').value.trim();
    const email = document.getElementById('user-email').value.trim();
    const role = document.getElementById('user-role').value;
    const department = document.getElementById('user-department').value;
    const status = document.getElementById('user-status').value;
    const lastLogin = document.getElementById('user-lastlogin').value;

    if (!name || !email || !role || !department) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }

    if (id) {
        // Update existing
        const user = AppData.users.find(u => u.id === parseInt(id));
        if (user) {
            user.name = name;
            user.email = email;
            user.role = role;
            user.department = department;
            user.status = status;
            if (lastLogin) user.lastLogin = new Date(lastLogin).toLocaleString();
            showNotification('User updated successfully', 'success');
            addAuditLog('update', `Updated user ${name}`);
        }
    } else {
        // Create new
        const newUser = {
            id: Math.max(...AppData.users.map(u => u.id), 0) + 1,
            name,
            email,
            role,
            department,
            status,
            lastLogin: new Date().toLocaleString()
        };
        AppData.users.push(newUser);
        showNotification('User created successfully', 'success');
        addAuditLog('create', `Created new user ${name}`);
    }

    closeUserModal();
    renderUsers();
}

function viewUserDetails(id) {
    const user = AppData.users.find(u => u.id === id);
    if (!user) return;

    const statusColor = user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';

    document.getElementById('user-details-title').textContent = user.name;
    document.getElementById('user-details-content').innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Email</label>
                <p class="text-gray-900 font-semibold mt-1">${user.email}</p>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Role</label>
                <p class="text-gray-900 font-semibold mt-1">${user.role}</p>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Department</label>
                <p class="text-gray-900 font-semibold mt-1">${user.department}</p>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Status</label>
                <p class="mt-1"><span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></p>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">Last Login</label>
                <p class="text-gray-900 font-semibold mt-1">${user.lastLogin}</p>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 uppercase">User ID</label>
                <p class="text-gray-900 font-semibold mt-1">#${user.id}</p>
            </div>
        </div>

        <div class="border-t pt-4 mt-4">
            <label class="text-xs font-semibold text-gray-500 uppercase mb-3 block">Quick Actions</label>
            <div class="space-y-2">
                <button onclick="resetUserPassword(${user.id})" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-semibold">
                    <i class="bi bi-key mr-2"></i> Reset Password
                </button>
                <button onclick="sendUserEmail(${user.id})" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-semibold">
                    <i class="bi bi-envelope mr-2"></i> Send Email
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

function toggleUserStatus(id) {
    const user = AppData.users.find(u => u.id === id);
    if (!user) return;
    
    user.status = user.status === 'active' ? 'inactive' : 'active';
    renderUsers();
    showNotification(`User ${user.name} is now ${user.status}`, 'success');
    addAuditLog('update', `Changed status of user ${user.name} to ${user.status}`);
}

function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
    
    const user = AppData.users.find(u => u.id === id);
    const index = AppData.users.findIndex(u => u.id === id);
    if (index > -1) {
        AppData.users.splice(index, 1);
        renderUsers();
        showNotification(`User ${user.name} deleted successfully`, 'success');
        addAuditLog('delete', `Deleted user ${user.name}`);
    }
}

function resetUserPassword(id) {
    const user = AppData.users.find(u => u.id === id);
    if (!user) return;
    
    showNotification(`Password reset link sent to ${user.email}`, 'success');
    addAuditLog('security', `Reset password for user ${user.name}`);
}

function sendUserEmail(id) {
    const user = AppData.users.find(u => u.id === id);
    if (!user) return;
    
    showNotification(`Email sent to ${user.email}`, 'success');
    addAuditLog('communication', `Sent email to user ${user.name}`);
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
                <input type="text" id="filterUser" class="input-field" placeholder="Filter by user..." oninput="filterAuditLogs()">
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
                <p id="totalLogsCount" class="text-2xl font-bold text-blue-600">0</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-sm text-gray-600">Today's Activity</p>
                <p id="todayLogsCount" class="text-2xl font-bold text-green-600">0</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <p class="text-sm text-gray-600">Active Users</p>
                <p id="activeUsersCount" class="text-2xl font-bold text-purple-600">0</p>
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
                        <!-- Populated by loadAuditLogsFromDatabase() -->
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
    loadAuditLogsFromDatabase();
}

function loadAuditLogsFromDatabase() {
    fetch('API/get_audit_logs_api.php')
        .then(response => response.json())
        .then(data => {
            AppData.auditLogs = data || [];
            updateAuditStats();
            filterAuditLogs();
        })
        .catch(error => {
            console.error('Error loading audit logs:', error);
            document.getElementById('auditLogsList').innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-red-500">Error loading audit logs</td></tr>';
        });
}

function updateAuditStats() {
    const totalCount = AppData.auditLogs.length;
    const today = new Date().toISOString().split('T')[0];
    const todayCount = AppData.auditLogs.filter(log => log.timestamp.includes(today)).length;
    const activeUsers = [...new Set(AppData.auditLogs.map(log => log.admin_user))].length;
    
    document.getElementById('totalLogsCount').textContent = totalCount;
    document.getElementById('todayLogsCount').textContent = todayCount;
    document.getElementById('activeUsersCount').textContent = activeUsers;
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
            <td class="px-6 py-4 text-sm text-gray-700">${log.timestamp}</td>
            <td class="px-6 py-4 text-sm font-medium text-gray-900">${log.admin_user}</td>
            <td class="px-6 py-4">${getActionBadge(log.action)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${log.entity_type}</td>
            <td class="px-6 py-4">${getStatusBadge(log.status)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${log.ip_address || 'N/A'}</td>
        </tr>
    `).join('');
}

function resetAuditFilters() {
    document.getElementById('filterAction').value = '';
    document.getElementById('filterUser').value = '';
    document.getElementById('filterDate').value = '';
    filterAuditLogs();
}

function renderUserLogs() {
    const html = `
        <div class="mb-6 animate-fade-in">
            <h1 class="text-2xl font-bold text-gray-800">User Activity Logs</h1>
            <p class="text-gray-600 mt-1">Monitor all user actions and interactions</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6 animate-fade-in-up animation-delay-100">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <select id="filterUserAction" class="input-field" onchange="filterUserLogs()">
                    <option value="">All Actions</option>
                    <option value="submitted_post">Submitted Post</option>
                    <option value="viewed_consultation">Viewed Consultation</option>
                    <option value="login">Login</option>
                    <option value="logout">Logout</option>
                    <option value="edited_post">Edited Post</option>
                    <option value="commented">Commented</option>
                </select>
                <input type="text" id="filterUserName" class="input-field" placeholder="Filter by username..." oninput="filterUserLogs()">
                <input type="date" id="filterUserDate" class="input-field" onchange="filterUserLogs()">
                <button onclick="resetUserLogFilters()" class="btn-outline">
                    <i class="bi bi-arrow-clockwise mr-2"></i>Reset
                </button>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <p class="text-sm text-gray-600">Total Actions</p>
                <p id="totalUserActionsCount" class="text-2xl font-bold text-blue-600">0</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-sm text-gray-600">Active Users Today</p>
                <p id="todayUserActionsCount" class="text-2xl font-bold text-green-600">0</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <p class="text-sm text-gray-600">Unique Users</p>
                <p id="uniqueUsersCount" class="text-2xl font-bold text-purple-600">0</p>
            </div>
        </div>

        <!-- User Logs Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fade-in-up animation-delay-200">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="userLogsList" class="divide-y divide-gray-200">
                        <!-- Populated by loadUserLogsFromDatabase() -->
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    document.getElementById('content-area').innerHTML = html;
    loadUserLogsFromDatabase();
}

function loadUserLogsFromDatabase() {
    fetch('API/get_user_logs_api.php')
        .then(response => response.json())
        .then(data => {
            AppData.userLogs = data || [];
            updateUserLogStats();
            filterUserLogs();
        })
        .catch(error => {
            console.error('Error loading user logs:', error);
            document.getElementById('userLogsList').innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-red-500">Error loading user logs</td></tr>';
        });
}

function updateUserLogStats() {
    const totalCount = AppData.userLogs.length;
    const today = new Date().toISOString().split('T')[0];
    const todayCount = AppData.userLogs.filter(log => log.timestamp.includes(today)).length;
    const uniqueUsers = [...new Set(AppData.userLogs.map(log => log.username))].length;
    
    document.getElementById('totalUserActionsCount').textContent = totalCount;
    document.getElementById('todayUserActionsCount').textContent = todayCount;
    document.getElementById('uniqueUsersCount').textContent = uniqueUsers;
}

function filterUserLogs() {
    const actionFilter = document.getElementById('filterUserAction')?.value || '';
    const userFilter = document.getElementById('filterUserName')?.value.toLowerCase() || '';
    const dateFilter = document.getElementById('filterUserDate')?.value || '';
    
    let filtered = AppData.userLogs.filter(log => {
        const matchesAction = !actionFilter || log.action === actionFilter;
        const matchesUser = !userFilter || log.username.toLowerCase().includes(userFilter);
        const matchesDate = !dateFilter || log.timestamp.includes(dateFilter);
        
        return matchesAction && matchesUser && matchesDate;
    });
    
    const tbody = document.getElementById('userLogsList');
    if (!tbody) return;
    
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No user logs found</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtered.map(log => `
        <tr class="hover:bg-gray-50 transition">
            <td class="px-6 py-4 text-sm text-gray-700">${log.timestamp}</td>
            <td class="px-6 py-4 text-sm font-medium text-gray-900">${log.username}</td>
            <td class="px-6 py-4">${getUserActionBadge(log.action)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${log.entity_type || 'N/A'}</td>
            <td class="px-6 py-4">${getStatusBadge(log.status)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${log.ip_address || 'N/A'}</td>
        </tr>
    `).join('');
}

function resetUserLogFilters() {
    document.getElementById('filterUserAction').value = '';
    document.getElementById('filterUserName').value = '';
    document.getElementById('filterUserDate').value = '';
    filterUserLogs();
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

// ==============================
// PROFILE MODULE
// ==============================
function renderProfile() {
    const currentUser = AppData.currentUser;
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
    
    // Read and display the image
    const reader = new FileReader();
    reader.onload = function(e) {
        AppData.currentUser.profilePicture = e.target.result;
        
        // Update profile image
        const profileImage = document.getElementById('profileImage');
        if (profileImage) {
            if (profileImage.tagName === 'IMG') {
                profileImage.src = e.target.result;
            } else {
                // Replace div with img
                const img = document.createElement('img');
                img.id = 'profileImage';
                img.src = e.target.result;
                img.alt = 'Profile';
                img.className = 'w-32 h-32 rounded-full border-4 border-white shadow-lg object-cover';
                profileImage.parentNode.replaceChild(img, profileImage);
            }
        }
        
        // Update navbar profile picture
        updateNavbarProfilePicture(e.target.result);
        
        showNotification('Profile picture updated successfully!', 'success');
        addAuditLog('update', 'Updated profile picture');
    };
    reader.readAsDataURL(file);
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
    AppData.currentUser.name = document.getElementById('editFullName').value;
    AppData.currentUser.email = document.getElementById('editEmail').value;
    
    showNotification('Profile updated successfully!', 'success');
    addAuditLog('update', 'Updated profile information');
    
    toggleEditMode();
    renderProfile();
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
    
    closeChangePasswordModal();
    showNotification('Password changed successfully!', 'success');
    addAuditLog('update', 'Changed account password');
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

function loadNotifications() {
    const notifsList = document.getElementById('notifications-list');
    if (!notifsList) {
        console.warn('notifications-list element not found');
        return;
    }

    // Update badge
    const unreadCount = AppData.notifications.filter(n => !n.read).length;
    const badge = document.getElementById('notification-badge');
    
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
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
                        <h4 class="text-sm font-semibold text-gray-900">${notif.title}</h4>
                        <span class="text-xs px-2 py-0.5 bg-gray-200 text-gray-700 rounded-full flex-shrink-0">${notif.category || 'general'}</span>
                    </div>
                    <p class="text-xs text-gray-700 line-clamp-2 mb-2">${notif.message}</p>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-500">🕐 ${notif.time}</span>
                        <div class="flex gap-1">
                            <button onclick="event.stopPropagation(); toggleNotificationRead(${notif.id}); return false;" class="text-xs px-2 py-0.5 text-gray-600 hover:bg-white rounded transition">${notif.read ? 'Unread' : 'Read'}</button>
                            <button onclick="event.stopPropagation(); deleteNotification(${notif.id}); return false;" class="text-xs px-2 py-0.5 text-red-600 hover:bg-red-50 rounded transition">✕</button>
                        </div>
                    </div>
                </div>
                <i class="bi bi-chevron-right text-gray-400 flex-shrink-0" style="cursor: pointer;"></i>
            </div>
        </div>
    `).join('');
    
    // Attach click handlers to notification items
    notifsList.querySelectorAll('[data-id]').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!e.target.closest('button')) {
                const id = parseInt(this.getAttribute('data-id'));
                viewNotification(id);
            }
        });
    });
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

    // Mark as read
    notif.read = true;
    saveNotificationsToStorage();
    loadNotifications();

    // Open a detail modal for the notification
    openNotificationModal(id);
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
    AppData.notifications = AppData.notifications.filter(n => n.id !== id);
    saveNotificationsToStorage();
    loadNotifications();
    // If on notifications page, re-render it
    const current = document.getElementById('breadcrumb-current');
    if (current && current.textContent && current.textContent.toLowerCase().includes('notifications')) {
        renderNotifications();
    }
}

function toggleNotificationRead(id) {
    const notif = AppData.notifications.find(n => n.id === id);
    if (!notif) return;
    notif.read = !notif.read;
    saveNotificationsToStorage();
    loadNotifications();
}

function markAllNotificationsRead() {
    AppData.notifications.forEach(n => n.read = true);
    saveNotificationsToStorage();
    loadNotifications();
}

function clearAllNotifications() {
    if (!confirm('Clear all notifications?')) return;
    AppData.notifications = [];
    saveNotificationsToStorage();
    loadNotifications();
    const dd = document.getElementById('notifications-dropdown');
    if (dd) dd.classList.add('hidden');
}

function saveNotificationsToStorage() {
    try {
        localStorage.setItem('llrm_notifications', JSON.stringify(AppData.notifications));
    } catch (e) {
        console.warn('Failed to save notifications to storage', e);
    }
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

function previewAnnImage(input) {
    const preview = document.getElementById('ann-image-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div style="position:relative;">
                    <img src="${e.target.result}" style="max-width:100%; max-height:200px; border-radius:6px; display:block; margin:auto;">
                    <button type="button" onclick="document.getElementById('ann-image-input').value=''; document.getElementById('ann-image-preview').innerHTML='<i class=\"bi bi-image text-2xl text-gray-400\"></i><p class=\"text-xs text-gray-500 mt-1\">Click to upload image (optional)</p>';" style="position:absolute;top:5px;right:5px;background:red;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:12px;padding:0;display:flex;align-items:center;justify-content:center;"><i class="bi bi-x" style="margin:0;"></i></button>
                </div>
            `;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function createAnnouncementWithImage() {
    const title = document.getElementById('new-ann-title').value.trim();
    const content = document.getElementById('new-ann-message').value.trim();
    const imageInput = document.getElementById('ann-image-input');
    
    if (!title || !content) {
        showNotification('Title and message required', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('title', title);
    formData.append('content', content);
    if (imageInput.files && imageInput.files[0]) {
        formData.append('image', imageInput.files[0]);
    }
    
    fetch('API/create_announcement_api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(json => {
        if (json.success) {
            showNotification('✅ Announcement published!', 'success');
            document.getElementById('new-ann-title').value = '';
            document.getElementById('new-ann-message').value = '';
            document.getElementById('ann-image-input').value = '';
            document.getElementById('ann-image-preview').innerHTML = '<i class="bi bi-image text-2xl text-gray-400"></i><p class="text-xs text-gray-500 mt-1">Click to upload image (optional)</p>';
            setTimeout(loadAnnouncementsFromDatabase, 500);
        } else {
            showNotification('Error: ' + (json.error || 'Failed to create announcement'), 'error');
        }
    })
    .catch(err => {
        console.error('Error creating announcement:', err);
        showNotification('Error creating announcement', 'error');
    });
}

function loadAnnouncementsFromDatabase() {
    fetch('API/get_announcements_api.php')
        .then(res => res.json())
        .then(data => {
            AppData.announcements = data || [];
            displayAdminAnnouncements();
        })
        .catch(err => {
            console.error('Error loading announcements:', err);
            // Fallback to localStorage
            loadAnnouncementsFromStorage();
        });
}

function displayAdminAnnouncements() {
    const list = document.getElementById('admin-announcements-list');
    if (!list) return;
    
    if (!AppData.announcements || AppData.announcements.length === 0) {
        list.innerHTML = '<div class="text-xs text-gray-400 text-center py-4">No announcements yet</div>';
        return;
    }
    
    list.innerHTML = AppData.announcements.map(a => `
        <div class="p-2.5 border border-gray-200 rounded hover:bg-gray-50 transition text-xs">
            ${a.image_path ? `<div style="margin-bottom:6px;border-radius:4px;overflow:hidden;"><img src="${a.image_path}" style="width:100%;height:80px;object-fit:cover;"></div>` : ''}
            <div class="font-semibold text-gray-800 text-sm">${a.title}</div>
            <div class="text-gray-500 text-xs mt-0.5">${new Date(a.created_at).toLocaleDateString()}</div>
            <div class="flex justify-end mt-2">
                <button onclick="deleteAnnouncement(${a.id}); loadAnnouncementsFromDatabase();" class="text-xs text-red-600 hover:text-red-700">Delete</button>
            </div>
        </div>
    `).join('');
}

function deleteAnnouncement(id) {
    if (!confirm('Delete this announcement?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    fetch('API/delete_announcement.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(json => {
        if (json.success) {
            showNotification('Announcement deleted', 'success');
            loadAnnouncementsFromDatabase();
        } else {
            showNotification('Error: ' + (json.error || 'Failed to delete'), 'error');
        }
    })
    .catch(err => {
        console.error('Error deleting announcement:', err);
        showNotification('Error deleting announcement', 'error');
    });
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
    const badges = {
        approved: '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Approved</span>',
        pending: '<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Pending</span>',
        draft: '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">Draft</span>',
        success: '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Success</span>',
        failure: '<span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Failed</span>'
    };
    return badges[status] || '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">' + (status || 'Unknown') + '</span>';
}

function getUserStatusBadge(status) {
    const badges = {
        active: '<span class="badge badge-success">Active</span>',
        inactive: '<span class="badge badge-secondary">Inactive</span>'
    };
    return badges[status] || '<span class="badge badge-secondary">Unknown</span>';
}

function getActionBadge(action) {
    const badges = {
        upload: '<span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Upload</span>',
        approve: '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Approve</span>',
        update: '<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Update</span>',
        delete: '<span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Delete</span>',
        login: '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Login</span>',
        logout: '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">Logout</span>',
        created: '<span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Created</span>'
    };
    return badges[action] || '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">' + (action || 'Unknown') + '</span>';
}

function getUserActionBadge(action) {
    const badges = {
        'submitted_post': '<span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Submitted Post</span>',
        'viewed_consultation': '<span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">Viewed Consultation</span>',
        'login': '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Login</span>',
        'logout': '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">Logout</span>',
        'edited_post': '<span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">Edited Post</span>',
        'commented': '<span class="px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-xs font-medium">Commented</span>',
        'approved': '<span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Approved</span>',
        'rejected': '<span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">Rejected</span>'
    };
    return badges[action] || '<span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">' + (action || 'Unknown') + '</span>';
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
    const openConsults = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'open').length;
    const pendingConsults = AppData.consultations.filter(c => {
        const st = String(c.status || '').toLowerCase();
        return st === 'scheduled' || st === 'draft' || st === 'pending';
    }).length;
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
                        <h1 class="text-3xl font-bold mb-2">Consultation Dashboard</h1>
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
                    <div class="text-gray-500 text-xs font-semibold uppercase mb-1">Open</div>
                    <div class="text-3xl font-bold text-green-600">${openConsults}</div>
                    <div class="text-xs text-gray-400 mt-2">In progress</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow hover:shadow-md transition border-l-4 border-amber-600">
                    <div class="text-gray-500 text-xs font-semibold uppercase mb-1">Pending</div>
                    <div class="text-3xl font-bold text-amber-600">${pendingConsults}</div>
                    <div class="text-xs text-gray-400 mt-2">Awaiting review</div>
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
                            <option value="Open">Open</option>
                            <option value="Pending">Pending</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
                        <select id="pc-type-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
                            onchange="filterPublicConsultations()">
                            <option value="">All Types</option>
                            <option value="In-person">In-person</option>
                            <option value="Online">Online</option>
                            <option value="Survey">Survey</option>
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

            <!-- Recent Activity Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Feedback -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Recent Feedback</h3>
                        <span class="text-xs bg-red-100 text-red-800 px-3 py-1 rounded-full">${totalFeedback} Total</span>
                    </div>
                    <div class="space-y-3 max-h-80 overflow-y-auto" id="recent-feedback-list">
                    </div>
                </div>

                <!-- Upcoming Consultations -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Pending Items</h3>
                        <span class="text-xs bg-amber-100 text-amber-800 px-3 py-1 rounded-full">${pendingConsults} Pending</span>
                    </div>
                    <div class="space-y-3 max-h-80 overflow-y-auto" id="upcoming-list">
                    </div>
                </div>
            </div>

            <!-- Analytics Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Feedback Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Total Consultation</h3>
                    <div style="height: 300px;">
                        <canvas id="pcStatusChart"></canvas>
                    </div>
                </div>

                <!-- Engagement Metrics -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Top Consultations by Feedback</h3>
                    <div class="space-y-3" id="top-consultations">
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
    renderTopConsultations();
    
    // Render chart
    setTimeout(() => renderPCStatusChart(), 120);
}

// ==============================
// SETTINGS
// ==============================
function renderSettings() {
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
                        <p class="text-sm text-gray-600 mt-1">Go to Users → Manage Users to add or edit accounts.</p>
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
    // Load announcements from server
    loadAnnouncementsFromDatabase();

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
                        
                        <!-- Image Upload -->
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 hover:border-red-500 transition cursor-pointer" onclick="document.getElementById('ann-image-input').click()">
                            <input type="file" id="ann-image-input" accept="image/*" style="display:none;" onchange="previewAnnImage(this)">
                            <div id="ann-image-preview" style="text-align:center;">
                                <i class="bi bi-image text-2xl text-gray-400"></i>
                                <p class="text-xs text-gray-500 mt-1">Click to upload image (optional)</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-2 pt-2">
                            <button onclick="document.getElementById('new-ann-title').value=''; document.getElementById('new-ann-message').value=''; document.getElementById('ann-image-input').value=''; document.getElementById('ann-image-preview').innerHTML='<i class=\"bi bi-image text-2xl text-gray-400\"></i><p class=\"text-xs text-gray-500 mt-1\">Click to upload image (optional)</p>';" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded transition">Clear</button>
                            <button onclick="createAnnouncementWithImage()" class="btn-primary px-4 py-1.5 text-sm">Publish</button>
                        </div>
                    </div>
                </div>

                <!-- Announcements List -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex-1 flex flex-col">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Recent Announcements</h3>
                    <div id="admin-announcements-list" class="space-y-2 overflow-auto flex-1">
                        <div class="text-xs text-gray-400 text-center py-4">Loading announcements...</div>
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
    const consultations = getFilteredPublicConsultations();

    if (consultations.length === 0) {
        grid.innerHTML = '<div class="col-span-full text-center text-gray-500 py-8">No consultations found</div>';
        return;
    }

    grid.innerHTML = consultations.map(c => {
        const normalizedStatus = String(c.status || '').trim();
        const statusLabel = ['scheduled','draft','pending'].includes(normalizedStatus.toLowerCase()) ? 'Pending' : normalizedStatus;
        const statusColor = normalizedStatus.toLowerCase() === 'open' ? 'bg-green-100 text-green-800' :
                           normalizedStatus.toLowerCase() === 'closed' ? 'bg-gray-100 text-gray-800' :
                           'bg-amber-100 text-amber-800';
        const typeIcon = c.type === 'In-person' ? 'bi-person' : 
                        c.type === 'Online' ? 'bi-globe' : 'bi-clipboard';

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
                        <span>${c.type}</span>
                        <span>•</span>
                        <span>${new Date(c.date).toLocaleDateString()}</span>
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
                    <button onclick="viewConsultationDetails(${c.id})" class="w-full text-center text-red-600 hover:text-red-700 font-semibold text-sm">
                        View Details →
                    </button>
                </div>
            </div>
        `;
    }).join('');
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
    const upcoming = AppData.consultations.filter(c => {
        const st = String(c.status || '').toLowerCase();
        return st === 'scheduled' || st === 'draft' || st === 'pending';
    }).slice(0, 5);

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
        filtered = filtered.filter(c => c.title.toLowerCase().includes(searchTerm));
    }
    
    if (statusFilter) {
        filtered = filtered.filter(c => c.status === statusFilter);
    }
    
    if (typeFilter) {
        filtered = filtered.filter(c => c.type === typeFilter);
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

    const open = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'open').length;
    const pending = AppData.consultations.filter(c => {
        const st = String(c.status || '').toLowerCase();
        return st === 'scheduled' || st === 'draft' || st === 'pending';
    }).length;
    const closed = AppData.consultations.filter(c => String(c.status || '').toLowerCase() === 'closed').length;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Open', 'Pending', 'Closed'],
            datasets: [{
                data: [open, pending, closed],
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
                    position: 'bottom',
                    labels: { padding: 20, font: { size: 12, weight: 'bold' } }
                }
            }
        }
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

    contentArea.innerHTML = '<div class="text-center py-8"><i class="bi bi-hourglass text-4xl text-gray-400 mb-4"></i><p class="text-gray-500">Loading consultations...</p></div>';
    
    // Fetch real consultation data
    fetch('API/consultations_api.php?action=list')
        .then(async response => {
            let payload = null;
            try {
                payload = await response.json();
            } catch (_) {
                payload = null;
            }

            if (!response.ok) {
                const msg = (payload && payload.message)
                    ? payload.message
                    : (response.status === 403 ? 'Unauthorized (admin session required)' : `HTTP ${response.status}`);
                throw new Error(msg);
            }

            return payload;
        })
        .then(result => {
            if (!result || !result.success) {
                const msg = (result && result.message) ? result.message : 'Error loading consultations';
                contentArea.innerHTML = `<div class="text-red-600">${msg}</div>`;
                return;
            }
            
            const consultations = result.data || [];
            const totalConsultations = consultations.length;
            const activeConsultations = consultations.filter(c => c.status === 'active').length;
            const closedConsultations = consultations.filter(c => c.status === 'closed').length;

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
                        <div class="text-3xl font-bold">${totalConsultations}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Active Consultations</div>
                        <div class="text-3xl font-bold">${activeConsultations}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Closed</div>
                        <div class="text-3xl font-bold">${closedConsultations}</div>
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
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                        <select id="consultation-category-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"
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
            </div>
            <!-- Consultations Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 border-b-2 border-gray-300">
                            <tr>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Title</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Category</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Status</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Posts</th>
                                <th class="px-6 py-3 text-left font-semibold text-gray-700">Dates</th>
                                <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="consultations-table-body">
                        </tbody>
                    </table>
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

            renderConsultationsTableFromData(consultations);
        })
        .catch(err => {
            console.error('Error fetching consultations:', err);
            const details = err && err.message ? String(err.message) : 'Unknown error';
            const hint = details.toLowerCase().includes('unauthorized') || details.toLowerCase().includes('403')
                ? 'Please log in as Admin and refresh the page.'
                : 'Check database connection and server logs.';
            contentArea.innerHTML = `<div class="text-red-600">Error loading consultations<div class="text-xs text-gray-500 mt-2">${escapeHtml(details)}<br>${escapeHtml(hint)}</div></div>`;
        });
}

function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function renderConsultationsTableFromData(consultations) {
    const tbody = document.getElementById('consultations-table-body');

    if (!tbody) return;

    if (consultations.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No consultations found</td></tr>`;
        return;
    }

    tbody.innerHTML = consultations.map(consultation => {
        const statusColor = consultation.status === 'active' ? 'bg-green-100 text-green-800' : 
                           consultation.status === 'draft' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800';

        const startDate = consultation.start_date ? new Date(consultation.start_date).toLocaleDateString() : '-';
        const endDate = consultation.end_date ? new Date(consultation.end_date).toLocaleDateString() : '-';

        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-semibold text-gray-900">${consultation.title}</td>
                <td class="px-6 py-4 text-gray-600">${consultation.category || '-'}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                        ${consultation.status.charAt(0).toUpperCase() + consultation.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-red-100 text-red-600 rounded-full font-semibold text-sm">
                        ${consultation.posts_count || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600">
                    <span title="${startDate} to ${endDate}">${startDate}</span>
                </td>
                <td class="px-6 py-4 text-center">
                    <div class="flex gap-2 justify-center">
                        <button onclick="openConsultationExportChooser(${consultation.id})" class="text-purple-600 hover:text-purple-800 text-sm font-semibold" title="Export"><i class="bi bi-file-earmark-arrow-down"></i> Export</button>
                        <button onclick="viewConsultationDetails(${consultation.id})" class="text-blue-600 hover:text-blue-800" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button onclick="editConsultation(${consultation.id})" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        ${consultation.type && String(consultation.type).toLowerCase() === 'user' ? '' : `<button onclick="deleteConsultation(${consultation.id})" class="text-red-600 hover:text-red-800" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>`}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

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

function filterConsultations() {
    // Re-fetch and filter
    fetch('API/consultations_api.php?action=list')
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                renderConsultationsTableFromData(result.data);
            }
        });
<<<<<<< Updated upstream
=======


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


            '<div class="p-4 text-center text-gray-500"><p>No notifications</p><p class="text-xs mt-1">You\'re all caught up!</p></div>' :


            sortedNotifs.map(notif => `


            <div data-id="${notif.id}" class="p-3 border-b border-gray-100 transition hover:bg-gray-50 ${!notif.read ? 'bg-blue-50 border-l-3 border-l-blue-500' : 'bg-white'}" style="cursor: pointer;">


                <div class="flex items-start gap-2">


                    <div class="text-lg flex-shrink-0 mt-0.5" title="Priority: ${notif.priority}">


                        ${priorityIcons[notif.priority] || '🔵'}


                    </div>


                    <div class="flex-1 min-w-0">


                        <div class="flex items-start justify-between gap-2 mb-1">


                            <h4 class="text-xs font-semibold text-gray-900 leading-tight">${escapeHtml(notif.title)}</h4>


                            <span class="text-xs px-1.5 py-0.5 bg-gray-200 text-gray-700 rounded flex-shrink-0">${escapeHtml(notif.category || 'general')}</span>


                        </div>


                        <p class="text-xs text-gray-600 line-clamp-1 mb-1">${escapeHtml(notif.message)}</p>


                        <div class="flex items-center justify-between gap-2">


                            <span class="text-xs text-gray-400">🕐 ${escapeHtml(notif.time)}</span>


                        </div>


                    </div>


                    <i class="bi bi-chevron-right text-gray-400 text-xs flex-shrink-0 mt-1" style="cursor: pointer;"></i>


                </div>


            </div>


        `).join('');




        notifsList.querySelectorAll('[data-id]').forEach(item => {


            item.addEventListener('click', function() {


                const id = parseInt(this.getAttribute('data-id'));


                viewNotification(id);


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


            <div class="bg-white rounded-lg shadow-lg w-11/12 md:w-2/3 lg:w-1/2 p-6 relative overflow-hidden">


                <!-- Navigation Buttons -->

                <button id="notif-detail-prev" class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-90 hover:bg-opacity-100 rounded-full p-2 shadow-md transition-all duration-200 z-10 opacity-0 pointer-events-none">

                    <i class="bi bi-chevron-left text-gray-600"></i>

                </button>

                <button id="notif-detail-next" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-90 hover:bg-opacity-100 rounded-full p-2 shadow-md transition-all duration-200 z-10 opacity-0 pointer-events-none">

                    <i class="bi bi-chevron-right text-gray-600"></i>

                </button>


                <!-- Content Container -->

                <div id="notif-content-container" class="transition-transform duration-300 ease-in-out">


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


                    <div class="mt-6 flex justify-between items-center">


                        <div class="text-sm text-gray-500">

                            <span id="notif-counter"></span>

                        </div>


                        <div class="flex gap-3">


                            <button id="notif-detail-action" class="btn-primary hidden"></button>


                            <button id="notif-detail-open" class="btn-primary">Open Related Page</button>


                            <button id="notif-detail-dismiss" class="btn-outline">Close</button>


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


    openBtn.onclick = function() {


        closeNotificationModal();


        if (notif.type === 'document' || notif.type === 'approval') showSection('documents');


        else if (notif.type === 'user') showSection('users');


        else if (notif.type === 'feedback') showSection('public-feedback-queue');


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


    const totalFeedback = AppData.feedback.length;


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


                    ${isReadOnlySuperAdmin ? '' : `<div class="dashboard-header-actions flex flex-wrap items-center gap-2">
                    </div>`}


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

            <!-- Calendar Display -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-gray-700 text-lg font-semibold">Consultation Calendar</div>
                    <div class="flex gap-2">
                        <button onclick="pcDashboardCalendarChangeMonth(-1)" class="p-2 hover:bg-gray-100 rounded text-gray-600"><i class="bi bi-chevron-left"></i></button>
                        <button onclick="pcDashboardCalendarChangeMonth(1)" class="p-2 hover:bg-gray-100 rounded text-gray-600"><i class="bi bi-chevron-right"></i></button>
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


    


        // Render charts
    setTimeout(() => {
        const filtered = getFilteredPublicConsultations();
        renderPCStatusChart(filtered);
        renderPCFeedbackSentimentChart();
        refreshPCSurveySelector(filtered);
        renderPCSurveyAnswersChart(filtered);
    }, 120);

    // Refresh feedback and issue analytics using latest DB data
    try {
        Promise.all([
            loadFeedbackFromApi(),
            loadIssuesFromApi()
        ]).then(() => {
            renderPCFeedbackSentimentChart();
            renderPCSurveyAnswersChart(getFilteredPublicConsultations());
        }).catch((e) => {
            console.error(e);
            renderPCFeedbackSentimentChart();
            renderPCSurveyAnswersChart(getFilteredPublicConsultations());
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


        const dateText = c.date ? new Date(c.date).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '';

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




function getFeedbackSentimentStats() {
    const stats = { positive: 0, neutral: 0, negative: 0, rated: 0 };
    const feedbackRows = Array.isArray(AppData.feedback) ? AppData.feedback : [];

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
    const feedbackRows = Array.isArray(AppData.feedback) ? AppData.feedback : [];
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
        const totalFeedback = Array.isArray(AppData.feedback) ? AppData.feedback.length : 0;
        const avgRating = totalFeedback > 0 ? (Array.isArray(AppData.feedback) ? AppData.feedback.reduce((sum, item) => sum + (Number(item && item.rating) > 0 ? Number(item.rating) : 0), 0) / totalFeedback : 0) : 0;
        summaryEl.innerHTML = `Total feedback: <strong>${totalFeedback}</strong> · Avg rating: <strong>${avgRating.toFixed(1)}</strong>`;
    }
    if (topicListEl) {
        const topics = getTopicThemeBreakdown();
        topicListEl.innerHTML = topics.length
            ? topics.map((item) => `<div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm"><span class="text-gray-700">${escapeHtml(item.label)}</span><span class="font-semibold text-gray-900">${item.count}</span></div>`).join('')
            : '<div class="text-sm text-gray-500">No topic themes detected yet.</div>';
    }

    if (window.pcFeedbackSentimentChart) {
        try { window.pcFeedbackSentimentChart.destroy(); } catch (e) {}
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
                        label: function(context) {
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
    const mode = String(consultation && consultation.response_mode ? consultation.response_mode : '').toLowerCase().trim();
    const question = String(consultation && consultation.survey_question ? consultation.survey_question : '').trim();
    return mode === 'survey' || (question !== '' && mode !== 'feedback');
}

function getSurveyConsultations(consultations) {
    const source = Array.isArray(consultations) ? consultations : (Array.isArray(AppData.consultations) ? AppData.consultations : []);
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

    const surveys = getSurveyConsultations(consultations);
    const prev = String(selectEl.value || 'all');

    if (!surveys.length) {
        selectEl.innerHTML = '<option value="all">No surveys</option>';
        selectEl.value = 'all';
        selectEl.disabled = true;
        return;
    }

    const options = ['<option value="all">All surveys</option>'];
    for (const s of surveys) {
        const id = Number(s && s.id ? s.id : 0);
        if (!id) continue;
        const title = getSurveyDisplayTitle(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        options.push(`<option value="${id}">${title}</option>`);
    }

    selectEl.innerHTML = options.join('');
    selectEl.disabled = false;
    const hasPrev = Array.from(selectEl.options).some((o) => o.value === prev);
    selectEl.value = hasPrev ? prev : 'all';
}

function handlePCSurveySelectionChange() {
    renderPCSurveyAnswersChart(getFilteredPublicConsultations());
}

async function fetchSurveyVoteTotals(consultations) {
    const surveys = getSurveyConsultations(consultations);
    if (!surveys.length) {
        return { agree: 0, disagree: 0, total: 0, surveyCount: 0 };
    }

    const requests = surveys.map((c) => {
        const cid = Number(c && c.id ? c.id : 0);
        if (!cid) return Promise.resolve(null);
        return fetch(`API/consultation_feedback.php?action=get_vote_stats&consultation_id=${encodeURIComponent(String(cid))}`, {
            headers: { 'Accept': 'application/json' }
        })
            .then((res) => (res.ok ? res.json() : null))
            .catch(() => null);
    });

    const rows = await Promise.all(requests);
    let agree = 0;
    let disagree = 0;
    for (const row of rows) {
        if (!row || !row.success || !row.data) continue;
        agree += Number(row.data.agree_votes || 0);
        disagree += Number(row.data.disagree_votes || 0);
    }
    return { agree, disagree, total: agree + disagree, surveyCount: surveys.length };
}

async function renderPCSurveyAnswersChart(consultations) {
    const ctx = document.getElementById('pcSurveyAnswersChart');
    if (!ctx) return;

    const respondentTotalEl = document.getElementById('pc-respondent-total');
    const surveyCountEl = document.getElementById('pc-survey-count-summary');
    const respondentScopeEl = document.getElementById('pc-respondent-scope');

    const totalEl = document.getElementById('pc-survey-total-count');
    const agreeEl = document.getElementById('pc-survey-agree-count');
    const disagreeEl = document.getElementById('pc-survey-disagree-count');
    const sourceEl = document.getElementById('pc-survey-source');

    if (totalEl) totalEl.textContent = '...';
    if (agreeEl) agreeEl.textContent = 'Agree: ...';
    if (disagreeEl) disagreeEl.textContent = 'Disagree: ...';
    if (sourceEl) sourceEl.textContent = 'Loading survey scope...';

    const selectEl = document.getElementById('pc-survey-select');
    const selectedId = String(selectEl && selectEl.value ? selectEl.value : 'all');
    const surveys = getSurveyConsultations(consultations);
    const selectedSurvey = selectedId === 'all' ? null : surveys.find((s) => String(Number(s && s.id ? s.id : 0)) === selectedId);
    const scope = selectedSurvey ? [selectedSurvey] : surveys;
    const totals = await fetchSurveyVoteTotals(scope);

    if (totalEl) totalEl.textContent = String(totals.total);
    if (agreeEl) agreeEl.textContent = `Agree: ${totals.agree}`;
    if (disagreeEl) disagreeEl.textContent = `Disagree: ${totals.disagree}`;
    if (respondentTotalEl) respondentTotalEl.textContent = String(totals.total);
    if (surveyCountEl) surveyCountEl.textContent = String(totals.surveyCount || surveys.length || 0);
    if (respondentScopeEl) {
        if (selectedSurvey) {
            respondentScopeEl.textContent = `Showing respondents for ${getSurveyDisplayTitle(selectedSurvey)}`;
        } else if (surveys.length) {
            respondentScopeEl.textContent = `Across ${surveys.length} active survey forms`;
        } else {
            respondentScopeEl.textContent = 'No survey responses available yet.';
        }
    }
    if (sourceEl) {
        const count = Number(totals.surveyCount || 0);
        if (selectedSurvey) {
            sourceEl.textContent = `Showing: ${getSurveyDisplayTitle(selectedSurvey)}`;
        } else {
            sourceEl.textContent = count > 0
                ? `Across ${count} survey consultation${count === 1 ? '' : 's'}`
                : 'No survey consultations found';
        }
    }

    if (window.pcSurveyAnswersChart) {
        try { window.pcSurveyAnswersChart.destroy(); } catch (e) {}
    }

    window.pcSurveyAnswersChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Agree', 'Disagree'],
            datasets: [{
                data: [totals.agree, totals.disagree],
                backgroundColor: ['#22c55e', '#ef4444'],
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
                        label: function(context) {
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


        try { window.pcStatusChart.destroy(); } catch (e) {}


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


                        label: function(context) {


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
                        <button onclick="openCreateConsultationModal('feedback')" class="btn-primary flex items-center gap-2 bg-white text-red-600 hover:bg-red-50 font-bold px-4 py-2 rounded-lg shadow-sm">
                            <i class="bi bi-plus-lg"></i> Add Consultation
                        </button>
                        <button onclick="openCreateConsultationModal('survey')" class="btn-primary flex items-center gap-2 bg-white text-blue-600 hover:bg-blue-50 font-bold px-4 py-2 rounded-lg shadow-sm">
                            <i class="bi bi-square-poll-horizontal"></i> Create Survey Form
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
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Status</th>
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
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Status</th>
                                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Documents</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Message</th>
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

                    <div id="consultation-type-selector-wrap">
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


        <div id="consultation-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">



            <div class="bg-white rounded-2xl shadow-xl max-w-5xl w-full max-h-[85vh] overflow-hidden">

                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center">

                    <h2 id="details-modal-title" class="text-2xl font-bold">Consultation Details</h2>

                    <button onclick="closeDetailsModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>

                </div>

                <div id="details-modal-content" class="p-6 space-y-6 overflow-y-auto" style="max-height:calc(85vh - 120px);">

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
        btn.classList.remove('border-gray-200','text-gray-600');
        btn.classList.add('border-red-600','border-b-2','text-red-600');
    };
    const makeInactive = (btn) => {
        if (!btn) return;
        btn.classList.remove('border-red-600','border-b-2','text-red-600');
        btn.classList.add('border-gray-200','text-gray-600');
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
            const consultation = AppData.consultations.find(c => Number(c.id) === Number(consultationId));
            const outcomeStatusMap = {
                solved: 'completed',
                'needs-follow-up': 'replied',
                escalated: 'viewed'
            };
            const newStatus = String(data.status || outcomeStatusMap[outcome] || outcome).toLowerCase();

            if (consultation) {
                consultation.status = newStatus;
            }

            updateConsultationStatsUI();
            renderConsultationsTable();

            alert('Outcome saved and consultation status updated.');
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

        feedbackCount: Number(row.posts_count || 0),

        // preserve DB created timestamp for client-side rules
        created_at: row.created_at || null,
        createdAt: row.created_at || null,

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
>>>>>>> Stashed changes
}

function renderConsultationsTable() {
    const tbody = document.getElementById('consultations-table-body');
    const consultations = getFilteredConsultations();

    if (consultations.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No consultations found</td></tr>`;
        return;
    }

    tbody.innerHTML = consultations.map(consultation => {
        const statusColor = consultation.status === 'Open' ? 'bg-green-100 text-green-800' : 
                           consultation.status === 'Scheduled' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800';
        const typeIcon = consultation.type === 'In-person' ? 'bi-person' : 
                        consultation.type === 'Online' ? 'bi-globe' : 'bi-clipboard';

        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-semibold text-gray-900">${consultation.title}</td>
                <td class="px-6 py-4">
                    <span class="flex items-center gap-2">
                        <i class="bi ${typeIcon}"></i>
                        ${consultation.type}
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-600">${new Date(consultation.date).toLocaleDateString()}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                        ${consultation.status}
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
                        <button onclick="editConsultation(${consultation.id})" class="text-yellow-600 hover:text-yellow-800" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button onclick="deleteConsultation(${consultation.id})" class="text-red-600 hover:text-red-800" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
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
    const categoryFilter = document.getElementById('consultation-category-filter')?.value || '';
    const selectedCategory = document.getElementById('consultation-sort')?.value || '';

    if (searchTerm) {
        filtered = filtered.filter(c => (c.title || '').toLowerCase().includes(searchTerm));
    }
    
    if (statusFilter) {
        filtered = filtered.filter(c => c.status === statusFilter);
    }
    
    if (categoryFilter) {
        filtered = filtered.filter(c => (c.category || '').toLowerCase() === categoryFilter.toLowerCase());
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
    renderConsultationsTable();
}

function openCreateConsultationModal() {
    document.getElementById('consultation-id').value = '';
<<<<<<< Updated upstream
    document.getElementById('modal-title').textContent = 'Create New Consultation';
=======
    const initialResponseMode = (createMode === 'survey') ? 'survey' : 'feedback';
    document.getElementById('modal-title').textContent = (createMode === 'survey') ? 'Create New Survey Form' : 'Create New Consultation';

>>>>>>> Stashed changes
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
    if (!confirm('Are you sure you want to delete this consultation?')) return;

    const index = AppData.consultations.findIndex(c => c.id === id);
    if (index !== -1) {
        AppData.consultations.splice(index, 1);
        showNotification('Consultation deleted successfully', 'success');
        renderConsultationsTable();
    }
}

function viewConsultationDetails(id) {
    const consultation = AppData.consultations.find(c => c.id === id);
    if (!consultation) return;

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

    const statusColor = consultation.status === 'Open' ? 'bg-green-100 text-green-800' : 
                       consultation.status === 'Scheduled' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800';

    document.getElementById('details-modal-title').textContent = consultation.title;

    const isUserSubmission = String(consultation.type || '').toLowerCase() === 'user';
    const isClosed = String(consultation.status || '').toLowerCase() === 'closed' || (consultation.end_date && new Date(consultation.end_date) < new Date());
    const isSuper = (typeof window !== 'undefined' && window.__IS_SUPER_ADMIN__ === true);
    // Introduce edit-window: only allow editing within N days of creation
    const editWindowDays = 7;
    const createdRaw = consultation.createdAt || consultation.created_at || consultation.date || consultation.start_date || null;
    let createdDate = null;
    if (createdRaw) {
        createdDate = new Date(createdRaw);
        if (Number.isNaN(createdDate.getTime())) createdDate = null;
    }
    const ageMs = createdDate ? (Date.now() - createdDate.getTime()) : null;
    const pastWindow = ageMs ? (ageMs > editWindowDays * 24 * 60 * 60 * 1000) : false;
    const canEdit = !isUserSubmission && !isSuper && !isClosed && !pastWindow;

    const editButtonHtml = canEdit ? `<button onclick="editConsultation(${consultation.id}); closeDetailsModal()" class="flex-1 btn-primary">Edit</button>` : '';

    document.getElementById('details-modal-content').innerHTML = `
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
                <p class="mt-1"><span class="px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">${consultation.status}</span></p>
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
            ${editButtonHtml}
            <button onclick="closeDetailsModal()" class="flex-1 btn-secondary">Close</button>
        </div>
    `;
    document.getElementById('consultation-details-modal').classList.remove('hidden');
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
                    <span class="text-xs font-semibold bg-white/20 px-3 py-1 rounded-full">View Only</span>
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Total Feedback</div>
                        <div class="text-3xl font-bold">${totalFeedback}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">This Week</div>
                        <div class="text-3xl font-bold">${recentFeedback}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <div class="text-red-100 text-sm font-semibold mb-1">Avg. per Consultation</div>
                        <div class="text-3xl font-bold">${AppData.consultations.length > 0 ? Math.round(totalFeedback / AppData.consultations.length) : 0}</div>
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

        <!-- Add/Edit Feedback Modal -->
        <div id="feedback-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-96 overflow-y-auto">
                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white p-6 flex justify-between items-center">
                    <h2 id="feedback-modal-title" class="text-2xl font-bold">Add New Feedback</h2>
                    <button onclick="closeFeedbackModal()" class="text-white hover:text-red-100 text-2xl">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="feedback-id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Author Name *</label>
                            <input type="text" id="feedback-author" placeholder="Full name" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Consultation *</label>
                            <select id="feedback-consultation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                                <option value="">Select Consultation</option>
                                ${AppData.consultations.map(c => `<option value="${c.id}">${c.title}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Feedback Message *</label>
                        <textarea id="feedback-message" placeholder="Enter feedback message..." rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date</label>
                        <input type="date" id="feedback-date" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="button" disabled class="flex-1 px-4 py-2 rounded-lg bg-gray-200 text-gray-500 cursor-not-allowed">View Only</button>
                        <button onclick="closeFeedbackModal()" class="flex-1 btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    renderFeedbackTable();
}

function renderFeedbackTable() {
    const tbody = document.getElementById('feedback-table-body');
    const feedbackList = getFilteredFeedback();

    if (feedbackList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No feedback found</td></tr>`;
        return;
    }

    tbody.innerHTML = feedbackList.map(feedback => {
        const consultation = AppData.consultations.find(c => c.id === feedback.consultationId);
        const consultationTitle = consultation ? consultation.title : 'Unknown Consultation';

        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="px-6 py-4 font-semibold text-gray-900">${feedback.author}</td>
                <td class="px-6 py-4 text-gray-700 max-w-xs truncate" title="${feedback.message}">${feedback.message}</td>
                <td class="px-6 py-4 text-gray-600 text-sm">${consultationTitle}</td>
                <td class="px-6 py-4 text-gray-600">${new Date(feedback.date).toLocaleDateString()}</td>
                <td class="px-6 py-4 text-center">
                    <div class="flex gap-2 justify-center">
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-gray-100 text-gray-600">View Only</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
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

function openAddFeedbackModal() {
    showNotification('Read-only mode: feedback actions are disabled.', 'warning');
    return;
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
    showNotification('Read-only mode: feedback actions are disabled.', 'warning');
    return;
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
    showNotification('Read-only mode: feedback actions are disabled.', 'warning');
    return;
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
    if (!confirm('Are you sure you want to delete this feedback?')) return;

    const feedback = AppData.feedback.find(f => f.id === id);
    if (feedback) {
        const index = AppData.feedback.indexOf(feedback);
        AppData.feedback.splice(index, 1);
        
        // Update consultation feedback count
        const consultation = AppData.consultations.find(c => c.id === feedback.consultationId);
        if (consultation && consultation.feedbackCount > 0) {
            consultation.feedbackCount--;
        }
        
        showNotification('Feedback deleted successfully', 'success');
        renderFeedbackTable();
    }
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

    if (typeof window.currentDocumentGroup === 'undefined') {
        window.currentDocumentGroup = 'consultation';
    }

    const groupCounts = {
        consultation: AppData.documents.filter(doc => getDocumentGroup(doc) === 'consultation').length,
        feedback: AppData.documents.filter(doc => getDocumentGroup(doc) === 'feedback').length,
        survey: AppData.documents.filter(doc => getDocumentGroup(doc) === 'survey').length,
        reports: AppData.documents.filter(doc => getDocumentGroup(doc) === 'reports').length
    };

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

            <!-- Four Section Tabs: Consultation, Feedback, Survey, Reports -->
            <div class="flex flex-wrap gap-2 mt-6 border-b border-gray-200">
                <button onclick="showSection('consultation-management')" class="px-6 py-3 font-semibold text-sm border-b-2 border-red-600 text-red-600 hover:bg-red-50">
                    <i class="bi bi-chat-left-quote mr-2"></i>Consultation
                </button>
                <button onclick="showSection('feedback-collection')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-blue-600 hover:text-blue-600 transition">
                    <i class="bi bi-hand-thumbs-up mr-2"></i>Feedback
                </button>
                <button onclick="showSection('surveys')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-green-600 hover:text-green-600 transition">
                    <i class="bi bi-bar-chart mr-2"></i>Survey
                </button>
                <button onclick="showSection('reports')" class="px-6 py-3 font-semibold text-sm border-b-2 border-gray-200 text-gray-600 hover:border-purple-600 hover:text-purple-600 transition">
                    <i class="bi bi-file-earmark-text mr-2"></i>Reports
                </button>
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

// Filter documents by group (Consultation, Feedback, Survey, Reports)
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

    // Filter documents by group
    let groupDocuments = [];
    if (AppData && AppData.documents) {
        groupDocuments = AppData.documents.filter(doc => {
            // Determine which group a document belongs to
            const docType = String(doc.type || '').toLowerCase();
            const docGroup = String(doc.group || '').toLowerCase();
            
            if (group === 'consultation') {
                return docType.includes('consultation') || docGroup.includes('consultation') || docType === 'ordinance' || docType === 'resolution';
            } else if (group === 'feedback') {
                return docType.includes('feedback') || docGroup.includes('feedback');
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
        const statusColor = doc.status === 'approved' ? 'bg-green-100 text-green-800' : 
                           doc.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                           doc.status === 'draft' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800';
        
        return `
            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="font-semibold text-gray-900">${doc.title || doc.reference || '-'}</div>
                    <div class="text-gray-600 text-xs mt-1">${doc.description || ''}</div>
                </td>
                <td class="px-6 py-4 text-gray-700">
                    <span class="inline-flex items-center gap-1">
                        ${doc.uploaded_by ? '<i class="bi bi-person-badge text-blue-600"></i>' : '<i class="bi bi-person text-gray-400"></i>'}
                        ${doc.uploaded_by_name || (doc.uploaded_by ? 'Admin' : 'Citizen')}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                        ${(doc.status || 'draft').charAt(0).toUpperCase() + (doc.status || 'draft').slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 text-center text-gray-600">${formatFileSize(doc.size || 0)}</td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-600 rounded-full font-semibold text-sm">
                        ${doc.downloads || 0}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <div class="flex gap-2 justify-center">
                        <button onclick="downloadDocument('${(doc.reference || doc.id).toString().replace(/'/g, "\\'")}')" class="text-blue-600 hover:text-blue-800" title="Download">
                            <i class="bi bi-download"></i>
                        </button>
                        <button onclick="viewDocument('${(doc.id).toString().replace(/'/g, "\\'")}')" class="text-gray-600 hover:text-gray-800" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderDocumentsTable() {
    const tbody = document.getElementById('documents-table-body');
    const documents = getFilteredDocuments();

    if (!tbody) return;

    if (documents.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No documents found for this group.</td></tr>`;
        return;
    }

    tbody.innerHTML = documents.map(doc => {
        const statusColor = doc.status === 'approved' ? 'bg-green-100 text-green-800' : 
                           doc.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800';
        const typeIcon = doc.type === 'ordinance' ? 'bi-file-text' : 
                        doc.type === 'resolution' ? 'bi-file-earmark' : 
                        doc.type === 'memorandum' ? 'bi-envelope' : 'bi-file-pdf';
        const group = getDocumentGroup(doc);

        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="font-semibold text-gray-900">${doc.reference}</div>
                    <div class="text-gray-600 text-xs mt-1">${doc.title}</div>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center gap-2 text-gray-700">
                        <i class="bi ${typeIcon}"></i>
                        ${doc.type.charAt(0).toUpperCase() + doc.type.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold ${statusColor}">
                        ${doc.status.charAt(0).toUpperCase() + doc.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="text-sm text-gray-700 capitalize">${group}</span>
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
    const currentGroup = window.currentDocumentGroup || 'consultation';

    filtered = filtered.filter(doc => getDocumentGroup(doc) === currentGroup);

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

function setDocumentGroup(group) {
    window.currentDocumentGroup = group;
    renderPCDocuments();
}

function getDocumentGroup(doc) {
    const type = String(doc.type || '').toLowerCase();
    const reference = String(doc.reference || '').toLowerCase();
    const title = String(doc.title || '').toLowerCase();

    if (type.includes('survey') || reference.includes('survey') || title.includes('survey')) {
        return 'survey';
    }
    if (type.includes('feedback') || reference.includes('feedback') || title.includes('feedback')) {
        return 'feedback';
    }
    if (type.includes('report') || reference.includes('report') || title.includes('report')) {
        return 'reports';
    }
    return 'consultation';
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

