<?php
require_once __DIR__ . '/../db.php';
require_once 'includes/auth.php';
require_once 'includes/layout.php';

// Fetch dynamic user counts from database
$totalUsers = 0;
$adminCount = 0;
$citizenCount = 0;

$cRes = $conn->query("SELECT LOWER(IFNULL(role, 'citizen')) as role, COUNT(*) as cnt FROM users GROUP BY LOWER(IFNULL(role, 'citizen'))");
if ($cRes) {
    while ($cRow = $cRes->fetch_assoc()) {
        $r = strtolower(trim($cRow['role']));
        $cnt = (int)$cRow['cnt'];
        $totalUsers += $cnt;
        if (in_array($r, ['admin', 'administrator', 'superadmin', 'super admin', 'staff'], true)) {
            $adminCount += $cnt;
        } else {
            $citizenCount += $cnt;
        }
    }
}

// Fetch dynamic user list
$search = trim($_GET['search'] ?? '');
$roleFilter = strtolower(trim($_GET['role'] ?? ''));
$statusFilter = strtolower(trim($_GET['status'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(fullname LIKE ? OR name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $sParam = "%$search%";
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
    $params[] = $sParam;
    $types .= 'ssss';
}

if ($roleFilter !== '' && $roleFilter !== 'all roles') {
    if ($roleFilter === 'citizen') {
        $where[] = "(LOWER(IFNULL(role,'citizen')) IN ('citizen', 'user', 'guest', 'resident', ''))";
    } else {
        $where[] = "LOWER(IFNULL(role,'citizen')) = ?";
        $params[] = $roleFilter;
        $types .= 's';
    }
}

if ($statusFilter !== '' && $statusFilter !== 'all status') {
    $where[] = "LOWER(IFNULL(status,'active')) = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql = "SELECT id, fullname, name, username, email, role, status, verification_status, created_at FROM users";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$usersList = $stmt->get_result();

renderAdminHead('User Management', 'user-management');
?>

<h1 class="text-2xl font-bold text-slate-800 mb-6">User Management</h1>

<form method="GET" action="user-management.php" class="filter-bar">
    <div class="filter-group flex-1 min-w-[200px]">
        <label>Search Users</label>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, or username...">
    </div>
    <div class="filter-group">
        <label>Role</label>
        <select name="role" onchange="this.form.submit()">
            <option value="">All Roles</option>
            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="superadmin" <?php echo $roleFilter === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
            <option value="citizen" <?php echo $roleFilter === 'citizen' ? 'selected' : ''; ?>>Citizen</option>
            <option value="resource_person" <?php echo $roleFilter === 'resource_person' ? 'selected' : ''; ?>>Resource Person</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Status</label>
        <select name="status" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
    </div>
    <div class="filter-group">
        <label>&nbsp;</label>
        <button class="btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass mr-1"></i> Filter</button>
    </div>
</form>

<div class="stat-cards mb-6" style="grid-template-columns: repeat(3, 1fr); max-width: 600px;">
    <div class="stat-card">
        <div class="label">Total Users</div>
        <div class="value blue"><?php echo $totalUsers; ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Administrators & Staff</div>
        <div class="value purple"><?php echo $adminCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Citizens</div>
        <div class="value green"><?php echo $citizenCount; ?></div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header flex justify-between items-center">
        <span>User Accounts</span>
        <span class="text-xs text-slate-500 font-normal">Showing <?php echo $usersList ? $usersList->num_rows : 0; ?> registered account(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($usersList && $usersList->num_rows > 0): ?>
                    <?php while ($u = $usersList->fetch_assoc()): ?>
                        <?php
                            $rLower = strtolower(trim($u['role'] ?? 'citizen'));
                            $sLower = strtolower(trim($u['status'] ?? 'active'));
                            $name = !empty($u['fullname']) ? $u['fullname'] : (!empty($u['name']) ? $u['name'] : (!empty($u['username']) ? $u['username'] : 'Anonymous User'));
                        ?>
                        <tr>
                            <td class="font-medium"><?php echo htmlspecialchars($name); ?></td>
                            <td><?php echo htmlspecialchars($u['email'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (in_array($rLower, ['admin', 'administrator', 'superadmin', 'super admin'], true)): ?>
                                    <span class="badge badge-blue"><?php echo ucfirst($rLower); ?></span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f3e8ff;color:#9333ea"><?php echo ucfirst($rLower); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sLower === 'active'): ?>
                                    <span class="badge badge-green">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#fee2e2;color:#dc2626">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm text-slate-500"><?php echo !empty($u['created_at']) ? date('M d, Y g:i A', strtotime($u['created_at'])) : 'N/A'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-6 text-slate-500">No users found in database.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderAdminFoot(); ?>
