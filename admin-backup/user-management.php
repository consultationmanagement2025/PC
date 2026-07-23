<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

renderAdminHead('User Management', 'user-management');
?>

<h1 class="text-2xl font-bold text-slate-800 mb-6">User Management</h1>

<div class="filter-bar">
    <div class="filter-group flex-1 min-w-[200px]">
        <label>Search Users</label>
        <input type="text" placeholder="Search by name or email...">
    </div>
    <div class="filter-group">
        <label>Role</label>
        <select>
            <option>All Roles</option>
            <option>Superadmin</option>
            <option>Admin</option>
            <option>Citizen</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Status</label>
        <select>
            <option>All Status</option>
            <option>Active</option>
            <option>Inactive</option>
        </select>
    </div>
    <div class="filter-group">
        <label>&nbsp;</label>
        <button class="btn-primary" type="button"><i class="fa-solid fa-plus mr-1"></i> Add User</button>
    </div>
</div>

<div class="stat-cards" style="grid-template-columns: repeat(3, 1fr); max-width: 600px;">
    <div class="stat-card">
        <div class="label">Total Users</div>
        <div class="value blue">3</div>
    </div>
    <div class="stat-card">
        <div class="label">Administrators</div>
        <div class="value purple">2</div>
    </div>
    <div class="stat-card">
        <div class="label">Citizens</div>
        <div class="value green">1</div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">User Accounts</div>
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-medium">Super Administrator</td>
                    <td>superadmin@valenzuela.gov.ph</td>
                    <td><span class="badge badge-blue">Superadmin</span></td>
                    <td><span class="badge badge-green">Active</span></td>
                    <td>
                        <button class="action-btn edit" type="button" title="Edit"><i class="fa-solid fa-pen text-sm"></i></button>
                    </td>
                </tr>
                <tr>
                    <td class="font-medium">Administrator</td>
                    <td>admin@valenzuela.gov.ph</td>
                    <td><span class="badge badge-blue">Admin</span></td>
                    <td><span class="badge badge-green">Active</span></td>
                    <td>
                        <button class="action-btn edit" type="button" title="Edit"><i class="fa-solid fa-pen text-sm"></i></button>
                    </td>
                </tr>
                <tr>
                    <td class="font-medium">Juan Dela Cruz</td>
                    <td>juan@example.com</td>
                    <td><span class="badge" style="background:#f3e8ff;color:#9333ea">Citizen</span></td>
                    <td><span class="badge badge-green">Active</span></td>
                    <td>
                        <button class="action-btn edit" type="button" title="Edit"><i class="fa-solid fa-pen text-sm"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminFoot(); ?>
