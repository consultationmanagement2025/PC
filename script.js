// LRMS System JavaScript - Updated with Mobile Support and Responsive Features

// Theme Toggle
function initThemeToggle() {
    const html = document.documentElement;
    
    // Check for saved theme preference and apply on load
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        html.classList.add('dark');
        updateThemeIcon(true);
    } else {
        updateThemeIcon(false);
    }
    // Note: Click handler is set via onclick="toggleTheme()" in HTML
}

function updateThemeIcon(isDark) {
    const darkIcon = document.querySelector('.dark-mode-icon');
    const lightIcon = document.querySelector('.light-mode-icon');
    
    if (darkIcon && lightIcon) {
        if (isDark) {
            darkIcon.classList.add('hidden');
            lightIcon.classList.remove('hidden');
        } else {
            darkIcon.classList.remove('hidden');
            lightIcon.classList.add('hidden');
        }
    }
}

// Mobile Sidebar Toggle with Animations
function initMobileSidebar() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const closeMobileSidebar = document.getElementById('close-mobile-sidebar');
    
    function openMobileSidebar() {
        // Show overlay with fade and blur
        sidebarOverlay.classList.remove('opacity-0', 'pointer-events-none');
        sidebarOverlay.classList.add('opacity-100', 'pointer-events-auto');
        
        // Slide in sidebar
        mobileSidebar.classList.remove('-translate-x-full');
        mobileSidebar.classList.add('translate-x-0');
        
        // Animate menu items with stagger effect
        const menuItems = mobileSidebar.querySelectorAll('nav a, nav > div');
        menuItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                item.style.transition = 'all 0.3s ease-out';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, 50 + (index * 30));
        });
        
        // Animate header
        const header = mobileSidebar.querySelector('.sidebar-header');
        if (header) {
            header.style.opacity = '0';
            header.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                header.style.transition = 'all 0.3s ease-out';
                header.style.opacity = '1';
                header.style.transform = 'translateY(0)';
            }, 100);
        }
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
    
    function closeMobileSidebarFn() {
        // Hide overlay with fade
        sidebarOverlay.classList.add('opacity-0', 'pointer-events-none');
        sidebarOverlay.classList.remove('opacity-100', 'pointer-events-auto');
        
        // Slide out sidebar
        mobileSidebar.classList.add('-translate-x-full');
        mobileSidebar.classList.remove('translate-x-0');
        
        // Restore body scroll
        document.body.style.overflow = '';
    }
    
    mobileMenuBtn?.addEventListener('click', openMobileSidebar);
    closeMobileSidebar?.addEventListener('click', closeMobileSidebarFn);
    sidebarOverlay?.addEventListener('click', closeMobileSidebarFn);
    
    // Close sidebar on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileSidebar && !mobileSidebar.classList.contains('-translate-x-full')) {
            closeMobileSidebarFn();
        }
    });
}

// Desktop Sidebar Toggle
function initDesktopSidebarToggle() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.flex-1.flex.flex-col.overflow-hidden');
    
    if (!sidebarToggle || !sidebar) return;
    
    // Check saved preference - default to expanded
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Ensure sidebar is visible on desktop
    if (window.innerWidth >= 768) {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        
        if (isCollapsed) {
            sidebar.classList.remove('sidebar-expanded', 'w-64');
            sidebar.classList.add('sidebar-collapsed');
            sidebarToggle.classList.add('sidebar-hidden');
        } else {
            sidebar.classList.add('sidebar-expanded', 'w-64');
            sidebar.classList.remove('sidebar-collapsed');
        }
    }
    
    sidebarToggle.addEventListener('click', function() {
        const isExpanded = sidebar.classList.contains('sidebar-expanded');
        
        // Add button press animation
        this.style.transform = 'scale(0.9)';
        setTimeout(() => {
            this.style.transform = '';
        }, 150);
        
        if (isExpanded) {
            // Collapse sidebar with animation
            sidebar.classList.remove('sidebar-expanded', 'w-64');
            sidebar.classList.add('sidebar-collapsed');
            this.classList.add('sidebar-hidden');
            localStorage.setItem('sidebarCollapsed', 'true');
            
            // Animate main content expansion
            if (mainContent) {
                mainContent.style.transform = 'scale(1.005)';
                setTimeout(() => {
                    mainContent.style.transform = '';
                }, 400);
            }
        } else {
            // Expand sidebar with animation
            sidebar.classList.remove('sidebar-collapsed');
            sidebar.classList.add('sidebar-expanded', 'w-64');
            this.classList.remove('sidebar-hidden');
            localStorage.setItem('sidebarCollapsed', 'false');
        }
    });
}

// Dropdown Toggle (Updated to handle multiple dropdowns)
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    const icon = document.getElementById(dropdownId + '-icon');
    
    // Close all other dropdowns
    document.querySelectorAll('[id$="-dropdown"]').forEach(d => {
        if (d.id !== dropdownId && d.classList.contains('show')) {
            d.classList.remove('show');
            d.classList.add('hidden');
        }
    });
    
    if (dropdown) {
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            dropdown.classList.add('hidden');
            if (icon) icon.classList.remove('rotate');
        } else {
            dropdown.classList.remove('hidden');
            dropdown.classList.add('show');
            if (icon) icon.classList.add('rotate');
        }
    }
}

// Mobile Sidebar Toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar && overlay) {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
}

// Clear All Notifications
function clearAllNotifications() {
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    const notificationItems = notificationsDropdown?.querySelectorAll('[data-notification-id]');
    notificationItems?.forEach(item => item.remove());
    showToast('All notifications cleared', 'success');
    toggleDropdown('notifications-dropdown');
}

// Focus Search
function focusSearch() {
    const searchInput = document.getElementById('quick-search');
    if (searchInput) {
        searchInput.focus();
        searchInput.select();
    }
}

// Notifications Dropdown
function initNotificationsDropdown() {
    const notifBtn = document.getElementById('notifications-btn');
    const notifDropdown = document.getElementById('notifications-dropdown');
    
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('hidden');
            // Close profile dropdown if open
            const profileDropdown = document.getElementById('profile-dropdown');
            if (profileDropdown) {
                profileDropdown.classList.add('hidden');
            }
        });
    }
}

// Profile Dropdown
function initProfileDropdown() {
    const profileBtn = document.getElementById('profile-btn');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            // Close notifications dropdown if open
            const notifDropdown = document.getElementById('notifications-dropdown');
            if (notifDropdown) {
                notifDropdown.classList.add('hidden');
            }
        });
    }
}

// Close dropdowns when clicking outside
function initClickOutside() {
    document.addEventListener('click', (e) => {
        const notifDropdown = document.getElementById('notifications-dropdown');
        const profileDropdown = document.getElementById('profile-dropdown');
        const notifBtn = document.getElementById('notifications-btn');
        const profileBtn = document.getElementById('profile-btn');
        
        if (notifDropdown && !notifBtn.contains(e.target)) {
            notifDropdown.classList.add('hidden');
        }
        
        if (profileDropdown && !profileBtn.contains(e.target)) {
            profileDropdown.classList.add('hidden');
        }
    });
}

// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

// Open Notify Modal and prefill user id
function openNotifyModal(userId, postId) {
    const uid = document.getElementById('notify-user-id');
    const pid = document.getElementById('notify-post-id');
    const msg = document.getElementById('notify-message');
    const type = document.getElementById('notify-type');
    if (uid) uid.value = userId || '';
    if (pid) pid.value = postId || '';
    if (type) type.value = 'inappropriate';
    if (msg) msg.value = 'Dear user, your post has been flagged as inappropriate. Please review our community guidelines.';
    openModal('notify-modal');
}

// Quick notify with predefined reason and message
async function quickNotify(userId, postId, reason) {
    const templates = {
        inappropriate: 'Dear user, your post has been flagged as inappropriate. Please review our community guidelines.',
        untruthful: 'Dear user, your post contains information that appears untruthful. Please provide sources or correct the statement.',
        unlawful: 'Dear user, your post may contain unlawful content. This has been escalated for further review.'
    };
    const message = templates[reason] || 'Dear user, your post has been reviewed by the administration.';
    const data = new FormData();
    data.append('user_id', userId);
    data.append('post_id', postId);
    data.append('type', reason);
    data.append('message', message);
    try {
        const res = await fetch('send_notification.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            showToast('Notification sent', 'success');
        } else {
            showToast(json.error || 'Failed to send notification', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Network error sending notification', 'error');
    }
}

async function submitNotification(e) {
    if (e) e.preventDefault();
    const form = document.getElementById('notify-form');
    if (!form) return;
    const data = new FormData(form);
    try {
        const res = await fetch('send_notification.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            showToast('Notification sent', 'success');
            closeModal('notify-modal');
        } else {
            showToast(json.error || 'Failed to send notification', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Network error sending notification', 'error');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// Announcement Functions
async function submitAnnouncement(e) {
    if (e) e.preventDefault();
    const form = document.getElementById('announcement-form');
    if (!form) return;
    const data = new FormData(form);
    try {
        const res = await fetch('create_announcement.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            form.reset();
            showToast('Announcement published', 'success');
            location.reload();
        } else {
            showToast(json.error || 'Failed to publish announcement', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Network error publishing announcement', 'error');
    }
}

async function openAnnouncementDetail(annId) {
    try {
        const res = await fetch('get_announcement.php?id=' + annId);
        const json = await res.json();
        if (json.success) {
            const ann = json.announcement;
            const likes = json.likes || 0;
            const saves = json.saves || 0;
            const userLiked = json.userLiked || false;
            const userSaved = json.userSaved || false;
            
            document.getElementById('ann-detail-title').textContent = ann.title;
            document.getElementById('ann-detail-content').innerHTML = ann.content.replace(/\n/g, '<br>');
            document.getElementById('ann-detail-meta').textContent = 'Posted by ' + ann.admin_user + ' · ' + new Date(ann.created_at).toLocaleDateString();
            document.getElementById('ann-like-count').textContent = likes;
            document.getElementById('ann-save-count').textContent = saves;
            
            const likeBtn = document.getElementById('ann-like-btn');
            const saveBtn = document.getElementById('ann-save-btn');
            if (userLiked) likeBtn.classList.add('text-red-600');
            if (userSaved) saveBtn.classList.add('text-blue-600');
            
            likeBtn.onclick = (e) => toggleAnnouncementAction(e, annId, 'like');
            saveBtn.onclick = (e) => toggleAnnouncementAction(e, annId, 'save');
            
            // Load user posts
            const postsRes = await fetch('get_posts.php');
            const postsJson = await postsRes.json();
            const postsList = document.getElementById('ann-user-posts');
            if (postsJson.posts && postsJson.posts.length > 0) {
                postsList.innerHTML = postsJson.posts.slice(0, 5).map(p => `
                    <div class="p-2 border rounded text-sm">
                        <div class="font-medium">${p.author}</div>
                        <div class="text-gray-600 mt-1">${p.content.substring(0, 150)}...</div>
                    </div>
                `).join('');
            } else {
                postsList.innerHTML = '<div class="text-gray-500">No user posts yet.</div>';
            }
            
            openModal('announcement-detail-modal');
        } else {
            showToast(json.error || 'Failed to load announcement', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Error loading announcement', 'error');
    }
}

async function toggleAnnouncementAction(e, annId, action) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    // Get annId from modal if not provided
    if (!annId && document.getElementById('announcement-detail-modal').classList.contains('show')) {
        // Extract from title or use data attribute (simplified for now)
        annId = parseInt(window.currentAnnouncementId || 0);
    }
    if (!annId) return;
    
    const data = new FormData();
    data.append('ann_id', annId);
    data.append('action', action);
    try {
        const res = await fetch('toggle_announcement_action.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            const btn = e?.target.closest('button');
            if (action === 'like' && btn) {
                btn.classList.toggle('text-red-600');
                btn.querySelector('span').textContent = json.likes;
            } else if (action === 'save' && btn) {
                btn.classList.toggle('text-blue-600');
                btn.querySelector('span').textContent = json.saves;
            }
            showToast(action === 'like' ? 'Announcement liked' : 'Announcement saved', 'success');
        } else {
            showToast(json.error || 'Failed to update', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Error updating announcement', 'error');
    }
}

// Toast Notification (Updated)
// Note: showToast function has been moved and updated above in the DOMContentLoaded section
// The function below is kept for reference but the main one is used

function getToastIcon(type) {
    switch(type) {
        case 'success': return 'check-circle-fill';
        case 'error': return 'x-circle-fill';
        case 'warning': return 'exclamation-triangle-fill';
        case 'info': return 'info-circle-fill';
        default: return 'info-circle-fill';
    }
}

// Document Upload with improved feedback
function handleDocumentUpload(event) {
    if (event) event.preventDefault();
    showToast('Document uploaded successfully!', 'success');
    closeModal('upload-modal');
}

// File select handler
function handleFileSelect(event) {
    const file = event.target.files[0];
    const fileName = document.getElementById('file-name');
    if (fileName && file) {
        fileName.textContent = file.name;
        fileName.classList.add('text-green-600', 'font-medium');
    }
}

// Search Functionality
function initSearch() {
    const searchInput = document.getElementById('quick-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            console.log('Searching for:', query);
            // Implement search logic here
        });
    }
}

// Delete Confirmation
function confirmDelete(itemName) {
    if (confirm(`Are you sure you want to delete "${itemName}"?`)) {
        showToast(`"${itemName}" has been deleted.`, 'success');
        return true;
    }
    return false;
}

// Edit Item
function editItem(itemId) {
    console.log('Editing item:', itemId);
    showToast('Edit functionality would open here', 'info');
}

// View Item
function viewItem(itemId) {
    console.log('Viewing item:', itemId);
    showToast('View functionality would open here', 'info');
}

// Navigation
function navigateTo(section) {
    console.log('Navigating to:', section);
    
    // Remove active class from all nav items
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to clicked item
    event.target.closest('.nav-item').classList.add('active');
    
    // Hide all sections
    document.querySelectorAll('[id$="-section"]').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Show selected section
    const targetSection = document.getElementById(section + '-section');
    if (targetSection) {
        targetSection.classList.remove('hidden');
    }
}

// Filter Table
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (input && table) {
        input.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            }
        });
    }
}

// Sort Table
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        return aText.localeCompare(bText);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Export Data
function exportData(format) {
    console.log('Exporting data as:', format);
    showToast(`Exporting data as ${format.toUpperCase()}...`, 'info');
    setTimeout(() => {
        showToast(`Data exported successfully as ${format.toUpperCase()}!`, 'success');
    }, 2000);
}

// Mark Notification as Read
function markAsRead(notificationId) {
    console.log('Marking notification as read:', notificationId);
    const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
    if (notifElement) {
        notifElement.classList.remove('unread');
        notifElement.style.opacity = '0.6';
    }
}

// Clear All Notifications
function clearAllNotifications() {
    if (confirm('Are you sure you want to clear all notifications?')) {
        const notifList = document.querySelector('#notifications-dropdown .space-y-2');
        if (notifList) {
            notifList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No notifications</p>';
        }
        showToast('All notifications cleared', 'success');
    }
}

// Initialize Chart (using Chart.js if available)
function initCharts() {
    // Example chart initialization
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('activity-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Documents',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }
}

// Auto-expand dropdown if a sub-item is active
function autoExpandActiveDropdown() {
    const activeSubItem = document.querySelector('.nav-item-sub.active');
    if (activeSubItem) {
        const dropdown = activeSubItem.closest('.dropdown-content');
        if (dropdown) {
            dropdown.classList.remove('hidden');
            dropdown.classList.add('show');
            const dropdownId = dropdown.id;
            const icon = document.getElementById(dropdownId + '-icon');
            if (icon) {
                icon.classList.add('rotate');
            }
        }
    }
}

// Show Section - Navigate between sections
function showSection(sectionId) {
    console.log('Showing section:', sectionId);
    
    // Update page title
    const pageTitle = document.querySelector('nav h2');
    if (pageTitle) {
        const titles = {
            'dashboard': 'Dashboard',
            'documents': 'Document Management',
            'search': 'Advanced Search',
            'analytics': 'Reports & Analytics',
            'users': 'User Management',
            'announcements': 'Announcements',
            'audit': 'Audit Logs',
            'profile': 'My Profile',
            'settings': 'Settings',
            'help': 'Help & Support'
        };
        pageTitle.textContent = titles[sectionId] || 'Dashboard';
    }
    
    // Update active nav item on desktop sidebar
    document.querySelectorAll('#sidebar .nav-item').forEach(item => {
        item.classList.remove('active', 'bg-red-700');
        if (item.getAttribute('data-section') === sectionId) {
            item.classList.add('active');
        }
    });
    
    // Update active nav item on mobile sidebar
    document.querySelectorAll('#mobile-sidebar nav a').forEach(item => {
        item.classList.remove('bg-red-700');
    });
    const mobileActiveItem = document.querySelector(`#mobile-sidebar nav a[onclick*="${sectionId}"]`);
    if (mobileActiveItem) {
        mobileActiveItem.classList.add('bg-red-700');
    }
    
    // Close mobile sidebar if open
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    if (mobileSidebar && !mobileSidebar.classList.contains('-translate-x-full')) {
        mobileSidebar.classList.add('-translate-x-full');
        mobileSidebar.classList.remove('translate-x-0');
        sidebarOverlay?.classList.add('opacity-0', 'pointer-events-none');
        sidebarOverlay?.classList.remove('opacity-100', 'pointer-events-auto');
        document.body.style.overflow = '';
    }
    
    // Show toast for demo purposes
    showToast(`Navigated to ${sectionId.charAt(0).toUpperCase() + sectionId.slice(1)}`, 'info');
    
    return false;
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        showToast('Logging out...', 'info');
        
        // Clear any stored session data
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('currentUser');
        sessionStorage.clear();
        
        // Redirect to login page with logout success message
        setTimeout(() => {
            window.location.href = 'login.html?logout=success';
        }, 1000);
    }
    return false;
}

// Toggle Notifications Dropdown
function toggleNotifications() {
    const notifDropdown = document.getElementById('notifications-dropdown');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    // Close profile dropdown if open
    if (profileDropdown) {
        profileDropdown.classList.add('hidden');
    }
    
    // Toggle notifications dropdown
    if (notifDropdown) {
        notifDropdown.classList.toggle('hidden');
    }
}

// Toggle Profile Menu
function toggleProfileMenu() {
    const notifDropdown = document.getElementById('notifications-dropdown');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    // Close notifications dropdown if open
    if (notifDropdown) {
        notifDropdown.classList.add('hidden');
    }
    
    // Toggle profile dropdown
    if (profileDropdown) {
        profileDropdown.classList.toggle('hidden');
    }
}

// Toggle Theme (for onclick attribute)
function toggleTheme() {
    const html = document.documentElement;
    html.classList.toggle('dark');
    const isDark = html.classList.contains('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateThemeIcon(isDark);
}

// ========================================
// Initialize all functions on DOM ready
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme toggle
    initThemeToggle();
    
    // Initialize mobile sidebar
    initMobileSidebar();
    
    // Initialize notifications dropdown
    initNotificationsDropdown();
    
    // Initialize profile dropdown
    initProfileDropdown();
    
    // Initialize click outside handler
    initClickOutside();
    
    console.log('All initializations complete');
});

// ==========================================
// Audit Log Functions
// ==========================================

function showAuditDetails(logData) {
    const modal = document.getElementById('audit-modal');
    const detailsContainer = document.getElementById('audit-details');
    
    if (!modal || !detailsContainer) return;
    
    const formatDate = (dateStr) => {
        const date = new Date(dateStr);
        return date.toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
    };
    
    const statusColor = logData.status === 'success' ? 'text-green-700' : 'text-red-700';
    const statusBg = logData.status === 'success' ? 'bg-green-50' : 'bg-red-50';
    
    detailsContainer.innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Admin User</label>
                <p class="text-gray-900 font-medium">${escapeHtml(logData.admin_user)}</p>
            </div>
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Admin ID</label>
                <p class="text-gray-900 font-medium">${logData.admin_id || '-'}</p>
            </div>
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Action</label>
                <p class="text-gray-900 font-medium">${escapeHtml(logData.action)}</p>
            </div>
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Entity Type</label>
                <p class="text-gray-900 font-medium">${escapeHtml(logData.entity_type || 'N/A')}</p>
            </div>
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Entity ID</label>
                <p class="text-gray-900 font-medium">${logData.entity_id || '-'}</p>
            </div>
            <div class="border border-gray-200 rounded-lg p-4">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">IP Address</label>
                <p class="text-gray-900 font-mono text-sm">${escapeHtml(logData.ip_address || '-')}</p>
            </div>
            <div class="border border-gray-200 rounded-lg p-4 col-span-2">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Timestamp</label>
                <p class="text-gray-900 font-medium">${formatDate(logData.timestamp)}</p>
            </div>
            <div class="border border-gray-200 rounded-lg p-4 col-span-2">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Status</label>
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium ${statusBg} ${statusColor}">
                    <i class="bi bi-${logData.status === 'success' ? 'check-circle-fill' : 'x-circle-fill'}"></i>
                    ${logData.status.charAt(0).toUpperCase() + logData.status.slice(1)}
                </span>
            </div>
            ${logData.old_value ? `
                <div class="border border-gray-200 rounded-lg p-4 col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Old Value</label>
                    <p class="text-gray-700 bg-gray-50 p-2 rounded text-sm font-mono max-h-32 overflow-y-auto">${escapeHtml(logData.old_value)}</p>
                </div>
            ` : ''}
            ${logData.new_value ? `
                <div class="border border-gray-200 rounded-lg p-4 col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">New Value</label>
                    <p class="text-gray-700 bg-gray-50 p-2 rounded text-sm font-mono max-h-32 overflow-y-auto">${escapeHtml(logData.new_value)}</p>
                </div>
            ` : ''}
            ${logData.details ? `
                <div class="border border-gray-200 rounded-lg p-4 col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Additional Details</label>
                    <p class="text-gray-700 bg-gray-50 p-2 rounded text-sm max-h-32 overflow-y-auto">${escapeHtml(logData.details)}</p>
                </div>
            ` : ''}
        </div>
    `;
    
    openModal('audit-modal');
}

function exportAuditLogs() {
    const url = new URL(window.location.href);
    url.searchParams.set('export', 'csv');
    window.location.href = url.toString();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Open modal function (if not already defined)
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

