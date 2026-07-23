<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

renderAdminHead('Public Feedback Queue', 'public-feedback-queue');
?>

<div class="page-banner">
    <h1>Public Feedback Queue</h1>
    <p>Review, respond, and close citizen feedback submissions.</p>
</div>

<div class="stat-cards">
    <div class="stat-card">
        <div class="label">Total</div>
        <div class="value dark">0</div>
    </div>
    <div class="stat-card">
        <div class="label">New</div>
        <div class="value red">0</div>
    </div>
    <div class="stat-card">
        <div class="label">Reviewed</div>
        <div class="value orange">0</div>
    </div>
    <div class="stat-card">
        <div class="label">Responded</div>
        <div class="value green">0</div>
    </div>
    <div class="stat-card">
        <div class="label">Closed</div>
        <div class="value blue">0</div>
    </div>
    <div class="stat-card">
        <div class="label">Anonymous</div>
        <div class="value purple">0</div>
    </div>
</div>

<div class="filter-bar">
    <div class="filter-group flex-1 min-w-[160px]">
        <label>Search</label>
        <input type="text" placeholder="Search feedback...">
    </div>
    <div class="filter-group">
        <label>Status</label>
        <select><option>All Status</option></select>
    </div>
    <div class="filter-group">
        <label>Priority</label>
        <select><option>All Priority</option></select>
    </div>
    <div class="filter-group">
        <label>Barangay</label>
        <input type="text" placeholder="Barangay">
    </div>
    <div class="filter-group">
        <label>Reference No</label>
        <input type="text" placeholder="Ref #">
    </div>
    <div class="filter-group">
        <label>From Date</label>
        <input type="date">
    </div>
    <div class="filter-group">
        <label>To Date</label>
        <input type="date">
    </div>
    <div class="filter-group">
        <label>&nbsp;</label>
        <div class="flex gap-2 flex-wrap">
            <button class="btn-primary" type="button">Apply Filters</button>
            <button class="btn-secondary" type="button">Reset</button>
            <button class="btn-secondary" type="button">Export CSV</button>
            <button class="btn-secondary" type="button">Apply to Selected</button>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Citizen</th>
                    <th>Type/Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Aging</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="8" class="text-center py-12 text-slate-400">No matching feedback found.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminFoot(); ?>
