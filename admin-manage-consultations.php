<?php
session_start();
require_once 'db.php';
require_once 'DATABASE/consultations.php';
require_once 'DATABASE/audit-log.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header('Location: login.php');
    exit();
}

// Initialize consultations table
initializeConsultationsTable();

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        if ($title && $description && $start_date && $end_date) {
            $id = createConsultation($title, $description, $category, $start_date, $end_date, $_SESSION['user_id']);
            if ($id) {
                logAction($_SESSION['user_id'], 'create_consultation', 'Created new consultation: ' . $title);
                $message = '<div class="alert alert-success">Consultation created successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error creating consultation.</div>';
            }
        } else {
            $message = '<div class="alert alert-warning">Please fill in all required fields.</div>';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        if ($id && $title && $description) {
            $success = updateConsultation($id, $title, $description, $category, $status, $start_date, $end_date);
            if ($success) {
                logAction($_SESSION['user_id'], 'update_consultation', 'Updated consultation: ' . $title);
                $message = '<div class="alert alert-success">Consultation updated successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error updating consultation.</div>';
            }
        }
    } elseif ($action === 'close') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && closeConsultation($id)) {
            logAction($_SESSION['user_id'], 'close_consultation', 'Closed consultation #' . $id);
            $message = '<div class="alert alert-success">Consultation closed successfully!</div>';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && deleteConsultation($id)) {
            logAction($_SESSION['user_id'], 'delete_consultation', 'Deleted consultation #' . $id);
            $message = '<div class="alert alert-success">Consultation deleted successfully!</div>';
        }
    }
}

// Get all consultations
$all_consultations = getConsultations(null, 100, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Consultations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-red: #C41E3A;
            --dark-blue: #003DA5;
            --light-blue: #0066CC;
            --gray-light: #F5F5F5;
        }

        body {
            background-color: var(--gray-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--primary-red) 100%);
        }

        .sidebar {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 1.5rem;
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .sidebar h5 {
            color: var(--dark-blue);
            font-weight: 700;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-red);
            padding-bottom: 0.75rem;
        }

        .sidebar-link {
            display: block;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            color: #333;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .sidebar-link:hover {
            background: var(--gray-light);
            color: var(--primary-red);
            border-left: 3px solid var(--primary-red);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 8px;
        }

        .page-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-red);
        }

        .stat-card h6 {
            color: #666;
            font-size: 0.9rem;
            margin: 0 0 0.5rem 0;
            text-transform: uppercase;
            font-weight: 600;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
            margin: 0;
        }

        .form-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-card h3 {
            color: var(--dark-blue);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-red);
        }

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 0.2rem rgba(196, 30, 58, 0.25);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary-red) 0%, #A01730 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table {
            margin: 0;
        }

        .table thead {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--light-blue) 100%);
            color: white;
        }

        .table thead th {
            border: none;
            font-weight: 600;
            padding: 1rem;
            vertical-align: middle;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #eee;
        }

        .table tbody tr:hover {
            background: var(--gray-light);
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: #4CAF50;
            color: white;
        }

        .status-badge.draft {
            background: #9E9E9E;
            color: white;
        }

        .status-badge.closed {
            background: #FF9800;
            color: white;
        }

        .status-badge.archived {
            background: #757575;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-buttons button {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: var(--light-blue);
            color: white;
        }

        .btn-close {
            background: #FF9800;
            color: white;
        }

        .btn-delete {
            background: #F44336;
            color: white;
        }

        .btn-edit:hover, .btn-close:hover, .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-blue) 100%);
            color: white;
            border: none;
        }

        .modal-title {
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="system-template-full.php">
                <i class="bi bi-building"></i> Admin Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="system-template-full.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin-manage-consultations.php">Consultations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="public-consultations.php" target="_blank">View Public Site</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container-fluid py-5">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <h1><i class="bi bi-megaphone-fill"></i> Consultation Management</h1>
            <p style="margin: 0.5rem 0 0 0; opacity: 0.95;">Create, edit, and manage public consultations</p>
        </div>

        <!-- Messages -->
        <?php echo $message; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Consultations</h6>
                <p class="stat-number"><?php echo count($all_consultations); ?></p>
            </div>
            <div class="stat-card" style="border-left-color: #4CAF50;">
                <h6>Active</h6>
                <p class="stat-number" style="color: #4CAF50;">
                    <?php echo count(array_filter($all_consultations, fn($c) => $c['status'] === 'active')); ?>
                </p>
            </div>
            <div class="stat-card" style="border-left-color: #FF9800;">
                <h6>Closed</h6>
                <p class="stat-number" style="color: #FF9800;">
                    <?php echo count(array_filter($all_consultations, fn($c) => $c['status'] === 'closed')); ?>
                </p>
            </div>
            <div class="stat-card" style="border-left-color: var(--light-blue);">
                <h6>Pending Feedback</h6>
                <p class="stat-number" style="color: var(--light-blue);">
                    <?php 
                        $pending_count = 0;
                        foreach ($all_consultations as $c) {
                            $stats = getConsultationStats($c['id']);
                            $pending_count += ($stats['pending_posts'] ?? 0);
                        }
                        echo $pending_count;
                    ?>
                </p>
            </div>
        </div>

        <!-- Create/Edit Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="form-card">
                    <h3>Create New Consultation</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="create">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" placeholder="Consultation title" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Budget">Budget & Finance</option>
                                    <option value="Policy">Policy & Governance</option>
                                    <option value="Development">Development & Planning</option>
                                    <option value="Environment">Environment</option>
                                    <option value="Social">Social Services</option>
                                    <option value="Infrastructure">Infrastructure</option>
                                    <option value="Education">Education</option>
                                    <option value="Health">Health</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="5" placeholder="Detailed description of the consultation..." required></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date & Time *</label>
                                <input type="datetime-local" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Date & Time *</label>
                                <input type="datetime-local" class="form-control" name="end_date" required>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-submit">
                                <i class="bi bi-plus-lg"></i> Create Consultation
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">Clear Form</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="col-lg-4">
                <div class="form-card">
                    <h5 style="margin-top: 0;">Quick Tips</h5>
                    <div style="font-size: 0.95rem; line-height: 1.6; color: #666;">
                        <p><strong>📋 Title:</strong> Keep it clear and descriptive. This appears in the public portal.</p>
                        <p><strong>📝 Description:</strong> Provide full details about what the consultation is about.</p>
                        <p><strong>📅 Dates:</strong> Set realistic start and end dates for gathering feedback.</p>
                        <p><strong>📊 Category:</strong> Choose the most relevant category for better organization.</p>
                        <p style="margin-bottom: 0; border-top: 1px solid #eee; padding-top: 1rem; color: var(--primary-red); font-weight: 600;">
                            💡 Once created, consultations appear immediately in the public portal!
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consultations Table -->
        <div class="table-container mt-5">
            <div style="padding: 1.5rem; background: linear-gradient(135deg, var(--dark-blue) 0%, var(--light-blue) 100%); color: white;">
                <h4 style="margin: 0; font-weight: 700;">All Consultations</h4>
            </div>
            
            <?php if (empty($all_consultations)): ?>
                <div style="padding: 3rem; text-align: center; color: #999;">
                    <i style="font-size: 2.5rem; opacity: 0.5; display: block; margin-bottom: 1rem;" class="bi bi-inbox"></i>
                    <p>No consultations created yet. Create one using the form above.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Feedback</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_consultations as $consultation): 
                                $stats = getConsultationStats($consultation['id']);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(substr($consultation['title'], 0, 30)); ?></strong></td>
                                <td><?php echo htmlspecialchars($consultation['category']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $consultation['status']; ?>">
                                        <?php echo ucfirst($consultation['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($consultation['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($consultation['end_date'])); ?></td>
                                <td>
                                    <span style="background: var(--light-blue); color: white; padding: 0.35rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                        <?php echo ($stats['total_posts'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-edit" onclick="viewConsultation(<?php echo $consultation['id']; ?>)" title="View Details">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <?php if ($consultation['status'] === 'active'): ?>
                                        <button class="btn-close" onclick="closeConsultation(<?php echo $consultation['id']; ?>)" title="Close Consultation">
                                            <i class="bi bi-stop-circle"></i> Close
                                        </button>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this consultation? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $consultation['id']; ?>">
                                            <button type="submit" class="btn-delete" title="Delete Consultation">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Consultation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewConsultation(consultationId) {
            fetch(`API/consultations_api.php?action=get&id=${consultationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const c = data.data;
                        const html = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Title:</strong> ${c.title}
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong> <span class="status-badge ${c.status}">${c.status}</span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Category:</strong> ${c.category}
                                </div>
                                <div class="col-md-6">
                                    <strong>Created:</strong> ${new Date(c.created_at).toLocaleDateString()}
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong>Description:</strong>
                                <p>${c.description}</p>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Start:</strong> ${new Date(c.start_date).toLocaleString()}
                                </div>
                                <div class="col-md-6">
                                    <strong>End:</strong> ${new Date(c.end_date).toLocaleString()}
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3"><small>Total Feedback</small> <br> <strong>${c.posts_count}</strong></div>
                                <div class="col-md-3"><small>Views</small> <br> <strong>${c.views}</strong></div>
                            </div>
                        `;
                        document.getElementById('modalBody').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('detailsModal')).show();
                    }
                });
        }

        function closeConsultation(consultationId) {
            if (confirm('Are you sure you want to close this consultation?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="id" value="${consultationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
