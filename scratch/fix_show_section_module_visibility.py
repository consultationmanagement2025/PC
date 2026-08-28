import os

target_files = [
    r"c:\xampp\htdocs\CAP101\PC\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin-side\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\ASSETS\js\app-features.js",
    r"c:\xampp\htdocs\CAP101\PC\admin\app-features.js"
]

new_show_section_func = """function showSection(sectionName) {
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

    try {
        if (document && document.body) {
            document.body.classList.toggle('section-public-consultation', sectionName === 'public-consultation');
        }
    } catch (e) {}

    if (!contentArea) {
        console.error('Content area not found!');
        return;
    }

    // Update active nav item styling
    document.querySelectorAll('.nav-item, [data-section]').forEach(item => {
        item.classList.remove('active');
        const sec = item.dataset.section || '';
        const onclickStr = item.getAttribute('onclick') || '';
        if (sec === sectionName || onclickStr.includes(`'${sectionName}'`) || (sectionName === 'reports' && (sec === 'reports' || onclickStr.includes('reports') || onclickStr.includes('Reports')))) {
            item.classList.add('active');
        }
    });

    // Close mobile menu
    if (window.innerWidth < 768) {
        const toggleBtn = document.getElementById('mobile-menu-btn');
        if (toggleBtn) {
            try { toggleBtn.click(); } catch(e){}
        }
    }

    window._currentActiveSection = sectionName;

    // Show managed template section if present in DOM
    const hasTemplateSection = showManagedTemplateSection(sectionName);

    // Call dynamic section renderer
    try {
        switch (sectionName) {
            case 'public-consultation':
            case 'consultation-dashboard':
            case 'dashboard':
                if (typeof renderPublicConsultation === 'function') {
                    renderPublicConsultation();
                } else if (typeof renderDashboard === 'function') {
                    renderDashboard();
                }
                break;

            case 'consultation-management':
                if (typeof renderConsultationManagement === 'function') {
                    renderConsultationManagement();
                }
                break;

            case 'public-feedback-queue':
            case 'public-feedback-portal':
            case 'feedback':
                if (typeof renderPublicFeedbackPortal === 'function') {
                    renderPublicFeedbackPortal();
                }
                break;

            case 'pc-documents':
            case 'document-management':
            case 'documents':
                if (typeof renderPCDocuments === 'function') {
                    renderPCDocuments();
                }
                break;

            case 'users':
            case 'user-management':
                if (typeof renderUsers === 'function') {
                    renderUsers();
                }
                break;

            case 'audit':
                if (typeof renderAudit === 'function') {
                    renderAudit();
                } else if (typeof loadAuditLogsFromDatabase === 'function') {
                    loadAuditLogsFromDatabase();
                }
                break;

            case 'analytics':
            case 'reports':
                if (typeof renderSystemReportsSection === 'function') {
                    renderSystemReportsSection();
                }
                break;

            case 'search':
                if (typeof renderSearch === 'function') {
                    renderSearch();
                }
                break;
        }
    } catch (err) {
        console.error('Error rendering section:', err);
    }

    if (typeof startHeaderClock === 'function') {
        startHeaderClock();
    }
}"""

for fpath in target_files:
    if os.path.exists(fpath):
        with open(fpath, 'r', encoding='utf-8') as f:
            content = f.read()

        start_idx = content.find("function showSection(sectionName) {")
        if start_idx != -1:
            end_idx = content.find("window.showSection = showSection;", start_idx)
            if end_idx == -1:
                end_idx = content.find("function openProfileModal", start_idx)
            
            if end_idx != -1:
                content_updated = content[:start_idx] + new_show_section_func + "\n\n" + content[end_idx:]
                with open(fpath, 'w', encoding='utf-8') as f:
                    f.write(content_updated)
                print(f"Updated showSection in {fpath}")
            else:
                print(f"Could not find end boundary in {fpath}")
        else:
            print(f"showSection function not found in {fpath}")
