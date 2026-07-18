document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('admin-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const clockEl = document.getElementById('admin-clock');
    const dateEl = document.getElementById('admin-date');
    const notificationToggle = document.getElementById('notification-toggle');
    const notificationPanel = document.getElementById('notification-panel');
    const profileToggle = document.getElementById('profile-toggle');
    const profilePanel = document.getElementById('profile-panel');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth <= 1024) {
                sidebar.classList.toggle('open');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
    }

    function updateDateTime() {
        if (!clockEl || !dateEl) return;
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        clockEl.textContent = `${h}:${m}:${s}`;
        dateEl.textContent = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }

    updateDateTime();
    setInterval(updateDateTime, 1000);

    function closeAllDropdowns() {
        notificationPanel?.classList.remove('open');
        profilePanel?.classList.remove('open');
    }

    notificationToggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (!notificationPanel) return;
        const isOpen = notificationPanel.classList.contains('open');
        closeAllDropdowns();
        if (!isOpen) {
            notificationPanel.classList.add('open');
        }
    });

    profileToggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (!profilePanel) return;
        const isOpen = profilePanel.classList.contains('open');
        closeAllDropdowns();
        if (!isOpen) {
            profilePanel.classList.add('open');
        }
    });

    document.addEventListener('click', () => {
        closeAllDropdowns();
    });

    const toastContainer = document.getElementById('admin-toast-container');

    function showToast(message, variant = 'info') {
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `admin-toast ${variant}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.transition = 'opacity 0.3s, transform 0.3s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(12px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function makeButtonsFunctional(selector, message) {
        document.querySelectorAll(selector).forEach((button) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                showToast(message, 'info');
            });
        });
    }

    document.querySelectorAll('a[href="#"]').forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            showToast('This action is not yet implemented.', 'warning');
        });
    });

    makeButtonsFunctional('.btn-banner', 'Banner action triggered.', 'info');
    makeButtonsFunctional('.btn-primary', 'Primary action triggered.', 'success');
    makeButtonsFunctional('.btn-secondary', 'Secondary action triggered.', 'info');
    makeButtonsFunctional('.action-btn.view', 'View action triggered.', 'info');
    makeButtonsFunctional('.action-btn.edit', 'Edit action triggered.', 'info');

    document.querySelectorAll('.toggle-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            const group = tab.parentElement;
            if (!group) return;
            group.querySelectorAll('.toggle-tab').forEach((btn) => btn.classList.remove('active'));
            tab.classList.add('active');
            showToast(`${tab.textContent.trim()} tab activated.`, 'success');
        });
    });

    document.querySelectorAll('.tab-btn').forEach((tab) => {
        tab.addEventListener('click', () => {
            const group = tab.parentElement;
            if (!group) return;
            group.querySelectorAll('.tab-btn').forEach((btn) => btn.classList.remove('active'));
            tab.classList.add('active');
            showToast(`${tab.textContent.trim()} section selected.`, 'success');
        });
    });

    document.querySelectorAll('.filter-bar .btn-secondary').forEach((button) => {
        if (button.textContent.trim().toLowerCase() === 'reset') {
            button.addEventListener('click', () => {
                const filterBar = button.closest('.filter-bar');
                if (!filterBar) return;
                filterBar.querySelectorAll('input').forEach((input) => input.value = '');
                filterBar.querySelectorAll('select').forEach((select) => select.selectedIndex = 0);
                showToast('Filters have been reset.', 'success');
            });
        }
    });

    document.querySelectorAll('.filter-bar .btn-primary').forEach((button) => {
        if (button.textContent.trim().toLowerCase().includes('apply filters')) {
            button.addEventListener('click', () => {
                showToast('Filters applied.', 'success');
            });
        }
    });

    document.querySelectorAll('.btn-secondary').forEach((button) => {
        if (button.textContent.trim().toLowerCase().includes('export')) {
            button.addEventListener('click', () => {
                showToast('Export started.', 'info');
            });
        }
    });

    document.querySelectorAll('.btn-banner').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.textContent.trim();
            showToast(`${action} clicked.`, 'info');
        });
    });

    document.querySelectorAll('.admin-dropdown-panel').forEach((panel) => {
        panel.addEventListener('click', (e) => e.stopPropagation());
    });
});
