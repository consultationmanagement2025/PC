<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

renderAdminHead('Document Management', 'document-management');
?>

<div class="page-banner">
    <h1>Document Management</h1>
    <p>Manage all consultation documents, track uploads, and monitor approval status.</p>
    <div class="page-banner-actions">
        <button class="btn-banner" type="button">
            <i class="fa-solid fa-upload"></i> Upload Document
        </button>
        <button class="btn-banner" type="button">
            <i class="fa-solid fa-file-lines"></i> Generate Report
        </button>
    </div>
    <div class="banner-stats">
        <div class="banner-stat-card">
            <div class="label">Total Documents</div>
            <div class="value">3</div>
        </div>
        <div class="banner-stat-card">
            <div class="label">Approved</div>
            <div class="value">0</div>
        </div>
        <div class="banner-stat-card">
            <div class="label">Total Size</div>
            <div class="value" style="font-size:1.1rem">5.98 KB</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="tab-bar px-4">
        <button class="tab-btn active" type="button"><i class="fa-solid fa-comments"></i> Consultation</button>
        <button class="tab-btn" type="button"><i class="fa-solid fa-comment-dots"></i> Feedback</button>
        <button class="tab-btn" type="button"><i class="fa-solid fa-square-poll-horizontal"></i> Survey</button>
        <button class="tab-btn" type="button"><i class="fa-solid fa-chart-bar"></i> Reports</button>
    </div>
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Document Title</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Downloads</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-medium">Consultation: Urban Planning and Zoning Amendments</td>
                    <td>PDF</td>
                    <td>2.1 KB</td>
                    <td>0</td>
                    <td>
                        <a href="#" class="text-blue-600 font-semibold text-sm hover:underline mr-3">View</a>
                        <button class="text-slate-500 hover:text-slate-700" type="button"><i class="fa-solid fa-download"></i></button>
                    </td>
                </tr>
                <tr>
                    <td class="font-medium">Consultation: Proposed Ordinance on Community Health</td>
                    <td>PDF</td>
                    <td>1.88 KB</td>
                    <td>0</td>
                    <td>
                        <a href="#" class="text-blue-600 font-semibold text-sm hover:underline mr-3">View</a>
                        <button class="text-slate-500 hover:text-slate-700" type="button"><i class="fa-solid fa-download"></i></button>
                    </td>
                </tr>
                <tr>
                    <td class="font-medium">Consultation: Barangay Road Expansion Project</td>
                    <td>PDF</td>
                    <td>2.0 KB</td>
                    <td>0</td>
                    <td>
                        <a href="#" class="text-blue-600 font-semibold text-sm hover:underline mr-3">View</a>
                        <button class="text-slate-500 hover:text-slate-700" type="button"><i class="fa-solid fa-download"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminFoot(); ?>
