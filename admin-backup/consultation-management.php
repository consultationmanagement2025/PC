<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

renderAdminHead('Consultation Management', 'consultation-management');
?>

<div class="page-banner">
    <h1>Consultation Management</h1>
    <p>Manage all public consultations, track feedback, and monitor engagement.</p>
    <div class="page-banner-actions">
        <button class="btn-banner" type="button">
            <i class="fa-solid fa-plus"></i> New Consultation
        </button>
    </div>
    <div class="banner-stats">
        <div class="banner-stat-card">
            <div class="label">Total Consultations</div>
            <div class="value">2</div>
        </div>
        <div class="banner-stat-card">
            <div class="label">Open Consultations</div>
            <div class="value">1</div>
        </div>
        <div class="banner-stat-card">
            <div class="label">Pending</div>
            <div class="value">0</div>
        </div>
    </div>
</div>

<div class="filter-bar">
    <div class="filter-group flex-1 min-w-[200px]">
        <label>Search Consultations</label>
        <input type="text" placeholder="Search by title...">
    </div>
    <div class="filter-group">
        <label>Status</label>
        <select>
            <option>All Status</option>
            <option>Active</option>
            <option>Closed</option>
            <option>Pending</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Category</label>
        <select>
            <option>All Categories</option>
            <option>Health</option>
            <option>Environment</option>
            <option>Infrastructure</option>
        </select>
    </div>
    <div class="filter-group">
        <label>&nbsp;</label>
        <div class="toggle-tabs">
            <button class="toggle-tab active" type="button">Admin Created</button>
            <button class="toggle-tab" type="button">User Submissions</button>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">Admin Created Consultations</div>
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Feedback</th>
                    <th>Documents</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td class="font-medium">Proposed Ordinance on Community Health and Preventive Care Programs</td>
                    <td>7/17/2026</td>
                    <td><span class="badge badge-green">Active</span></td>
                    <td><span class="count-badge">0</span></td>
                    <td><span class="count-badge">0</span></td>
                    <td>
                        <button class="action-btn view" type="button" title="View"><i class="fa-solid fa-globe text-sm"></i></button>
                        <button class="action-btn edit" type="button" title="Edit"><i class="fa-solid fa-pen text-sm"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminFoot(); ?>
