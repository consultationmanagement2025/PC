<?php
require_once 'includes/auth.php';
require_once 'includes/layout.php';

renderAdminHead('Audit Log', 'audit-log');
?>

<h1 class="text-2xl font-bold text-slate-800 mb-6">Audit Logs</h1>

<div class="filter-bar">
    <div class="filter-group">
        <label>Action</label>
        <select>
            <option>All Actions</option>
            <option>User Login</option>
            <option>User Logout</option>
            <option>Create</option>
            <option>Update</option>
            <option>Delete</option>
        </select>
    </div>
    <div class="filter-group flex-1 min-w-[200px]">
        <label>Admin User</label>
        <input type="text" placeholder="Filter by admin user...">
    </div>
    <div class="filter-group">
        <label>Date</label>
        <input type="date">
    </div>
</div>

<div class="stat-cards" style="grid-template-columns: repeat(3, 1fr); max-width: 600px;">
    <div class="stat-card" style="background:#eff6ff;border-color:#bfdbfe">
        <div class="label">Total Logs</div>
        <div class="value blue">6</div>
    </div>
    <div class="stat-card" style="background:#f0fdf4;border-color:#bbf7d0">
        <div class="label">Today's Activity</div>
        <div class="value green">4</div>
    </div>
    <div class="stat-card" style="background:#faf5ff;border-color:#e9d5ff">
        <div class="label">Active Admins</div>
        <div class="value purple">1</div>
    </div>
</div>

<div class="admin-card">
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Admin User</th>
                    <th>Action</th>
                    <th>Entity Type</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = [
                    ['2026-07-18 19:45:12', 'System Administrator', 'User Login', 'user', '::1'],
                    ['2026-07-18 14:22:08', 'System Administrator', 'User Login', 'user', '::1'],
                    ['2026-07-18 09:15:33', 'System Administrator', 'User Login', 'user', '::1'],
                    ['2026-07-18 08:02:17', 'System Administrator', 'User Login', 'user', '::1'],
                    ['2026-07-17 16:30:45', 'System Administrator', 'User Login', 'user', '::1'],
                    ['2026-07-17 10:12:00', 'System Administrator', 'User Login', 'user', '::1'],
                ];
                foreach ($logs as $log):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($log[0]); ?></td>
                    <td class="font-semibold"><?php echo htmlspecialchars($log[1]); ?></td>
                    <td><span class="badge badge-blue"><?php echo htmlspecialchars($log[2]); ?></span></td>
                    <td><?php echo htmlspecialchars($log[3]); ?></td>
                    <td class="text-slate-500"><?php echo htmlspecialchars($log[4]); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminFoot(); ?>
