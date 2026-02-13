<?php
session_start();
require_once 'db.php';
require_once 'DATABASE/consultations.php';
require_once 'DATABASE/user-logs.php';

// Initialize consultations table
initializeConsultationsTable();

// Get all active consultations for public display
$active_consultations = getConsultations('active', 100, 0);

// Get consultation stats
$consultation_stats = [];
foreach ($active_consultations as &$consultation) {
    $consultation_stats[$consultation['id']] = getConsultationStats($consultation['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Consultations - City of Valenzuela</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--gray-light);
        }

        .navbar {
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--primary-red) 100%);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 3rem;
        }

        .hero-section h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero-section p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .consultation-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .consultation-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .consultation-header {
            background: linear-gradient(135deg, var(--primary-red) 0%, #A01730 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .consultation-header h3 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .status-badge.active {
            background: #4CAF50;
            border-color: #4CAF50;
        }

        .status-badge.closed {
            background: #FF9800;
            border-color: #FF9800;
        }

        .consultation-body {
            padding: 1.5rem;
        }

        .consultation-description {
            color: #333;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .consultation-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--gray-light);
            border-radius: 8px;
        }

        .detail-item {
            text-align: center;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-red);
        }

        .event-schedule {
            background: var(--gray-light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .event-schedule h5 {
            color: var(--dark-blue);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .event-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: white;
            border-radius: 6px;
            border-left: 4px solid var(--primary-red);
        }

        .event-item:last-child {
            margin-bottom: 0;
        }

        .event-icon {
            margin-right: 1rem;
            font-size: 1.5rem;
            color: var(--primary-red);
            min-width: 30px;
        }

        .event-details {
            flex: 1;
        }

        .event-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .event-info {
            font-size: 0.9rem;
            color: #666;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-participate {
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-participate:hover {
            background: #A01730;
            transform: translateY(-2px);
        }

        .btn-learn-more {
            background: transparent;
            color: var(--primary-red);
            border: 2px solid var(--primary-red);
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-learn-more:hover {
            background: var(--primary-red);
            color: white;
        }

        .filter-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .filter-section h5 {
            color: var(--dark-blue);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-blue) 100%);
            color: white;
            border: none;
        }

        .modal-title {
            font-weight: 700;
        }

        .btn-close {
            filter: brightness(0) invert(1);
        }

        .qr-code-section {
            text-align: center;
            padding: 1.5rem;
            background: var(--gray-light);
            border-radius: 8px;
            margin: 1.5rem 0;
        }

        .qr-code-section p {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .qr-code-section img {
            max-width: 200px;
            height: auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .timeline {
            position: relative;
            padding: 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary-red);
        }

        .timeline-item {
            margin-left: 30px;
            padding-bottom: 2rem;
            position: relative;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -37px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--primary-red);
        }

        .timeline-date {
            font-weight: 700;
            color: var(--primary-red);
            margin-bottom: 0.5rem;
        }

        .timeline-content {
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 1.8rem;
            }

            .consultation-details {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn-participate,
            .action-buttons .btn-learn-more {
                width: 100%;
                justify-content: center;
            }
        }

        .feedback-badge {
            background: var(--dark-blue);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-building"></i> City of Valenzuela
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="public-consultations.php">Public Consultations</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="user-portal.php">My Portal</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="AUTH/register.php">Register</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1><i class="bi bi-megaphone"></i> Public Consultations</h1>
            <p>Be HEARD. Take part in the Public Consultation!</p>
            <p style="font-size: 0.95rem; margin-top: 1rem;">Gather feedback, clarify concerns, and present proposed data prior to submission to government agencies for review and approval.</p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container pb-5">
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row g-3">
                <div class="col-md-6">
                    <h5><i class="bi bi-funnel"></i> Filter Consultations</h5>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">Total Active Consultations: <strong><?php echo count($active_consultations); ?></strong></small>
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchFilter" placeholder="Search consultations...">
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="Budget">Budget</option>
                        <option value="Policy">Policy</option>
                        <option value="Development">Development</option>
                        <option value="Environment">Environment</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" onclick="resetFilters()">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Consultations List -->
        <div id="consultationsContainer">
            <?php if (empty($active_consultations)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h4>No Active Consultations</h4>
                    <p>Check back soon for upcoming public consultations.</p>
                </div>
            <?php else: ?>
                <?php foreach ($active_consultations as $consultation): 
                    $stats = $consultation_stats[$consultation['id']] ?? null;
                    $is_closed = strtotime($consultation['end_date']) < time();
                ?>
                <div class="consultation-card" data-title="<?php echo htmlspecialchars($consultation['title']); ?>" data-category="<?php echo htmlspecialchars($consultation['category']); ?>">
                    <div class="consultation-header">
                        <div>
                            <h3><?php echo htmlspecialchars($consultation['title']); ?></h3>
                            <small style="opacity: 0.9;"><?php echo htmlspecialchars($consultation['category']); ?></small>
                        </div>
                        <span class="status-badge <?php echo ($is_closed ? 'closed' : 'active'); ?>">
                            <?php echo $is_closed ? 'Closed' : 'Active'; ?>
                        </span>
                    </div>

                    <div class="consultation-body">
                        <div class="consultation-description">
                            <?php echo nl2br(htmlspecialchars(substr($consultation['description'], 0, 300))); ?>
                            <?php if (strlen($consultation['description']) > 300): ?>
                                ...
                            <?php endif; ?>
                        </div>

                        <!-- Event Schedule -->
                        <div class="event-schedule">
                            <h5><i class="bi bi-calendar-event"></i> Consultation Schedule</h5>
                            <div class="event-item">
                                <div class="event-icon"><i class="bi bi-calendar-check"></i></div>
                                <div class="event-details">
                                    <div class="event-title">Start Date</div>
                                    <div class="event-info"><?php echo date('F d, Y - h:i A', strtotime($consultation['start_date'])); ?></div>
                                </div>
                            </div>
                            <div class="event-item">
                                <div class="event-icon"><i class="bi bi-calendar-x"></i></div>
                                <div class="event-details">
                                    <div class="event-title">End Date</div>
                                    <div class="event-info"><?php echo date('F d, Y - h:i A', strtotime($consultation['end_date'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Consultation Details -->
                        <div class="consultation-details">
                            <div class="detail-item">
                                <div class="detail-label">Feedback</div>
                                <div class="detail-value" style="color: var(--dark-blue);">
                                    <?php echo ($stats['total_posts'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Approved</div>
                                <div class="detail-value" style="color: #4CAF50;">
                                    <?php echo ($stats['approved_posts'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Pending</div>
                                <div class="detail-value" style="color: #FF9800;">
                                    <?php echo ($stats['pending_posts'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Contributors</div>
                                <div class="detail-value" style="color: var(--light-blue);">
                                    <?php echo ($stats['unique_contributors'] ?? 0); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button class="btn-participate" onclick="viewConsultationDetails(<?php echo $consultation['id']; ?>)">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                            <?php if (!$is_closed && isset($_SESSION['user_id'])): ?>
                                <button class="btn-participate" onclick="submitFeedback(<?php echo $consultation['id']; ?>)" style="background: var(--dark-blue);">
                                    <i class="bi bi-chat-left-text"></i> Submit Feedback
                                </button>
                            <?php endif; ?>
                            <button class="btn-learn-more" onclick="viewConsultationDetails(<?php echo $consultation['id']; ?>)">
                                <i class="bi bi-arrow-right"></i> Learn More
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Consultation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-info">
                            <strong>Please login</strong> to submit feedback.
                            <a href="login.php" class="alert-link">Login here</a> or 
                            <a href="AUTH/register.php" class="alert-link">Register</a>
                        </div>
                    <?php else: ?>
                        <form id="feedbackForm">
                            <input type="hidden" id="feedbackConsultationId">
                            <div class="mb-3">
                                <label class="form-label">Your Feedback *</label>
                                <textarea class="form-control" id="feedbackMessage" rows="5" placeholder="Please share your feedback..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="feedbackCategory">
                                    <option value="General">General Feedback</option>
                                    <option value="Concern">Concern</option>
                                    <option value="Suggestion">Suggestion</option>
                                    <option value="Support">Support</option>
                                    <option value="Question">Question</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-send"></i> Submit Feedback
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: var(--dark-blue); color: white; padding: 2rem 0; margin-top: 4rem;">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>City of Valenzuela</h5>
                    <p>Public Consultation Management Portal</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p style="margin: 0;">© <?php echo date('Y'); ?> City Government of Valenzuela</p>
                    <small>For inquiries: contact@valenzuela.gov.ph</small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentConsultationId = null;

        function viewConsultationDetails(consultationId) {
            currentConsultationId = consultationId;
            
            fetch(`API/consultations_api.php?action=get&id=${consultationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const consultation = data.data;
                        const isClosed = new Date(consultation.end_date) < new Date();
                        
                        let html = `
                            <h4 style="color: var(--primary-red); margin-bottom: 1rem;">
                                ${consultation.title}
                            </h4>
                            <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                                <p style="margin: 0; color: #666;">${consultation.description}</p>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                                <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px;">
                                    <small style="color: #666; font-weight: 600;">Category</small>
                                    <p style="margin: 0.5rem 0 0 0; font-weight: 700;">${consultation.category}</p>
                                </div>
                                <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px;">
                                    <small style="color: #666; font-weight: 600;">Status</small>
                                    <p style="margin: 0.5rem 0 0 0; font-weight: 700; color: ${isClosed ? '#FF9800' : '#4CAF50'};">
                                        ${isClosed ? 'Closed' : 'Active'}
                                    </p>
                                </div>
                                <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px;">
                                    <small style="color: #666; font-weight: 600;">Start Date</small>
                                    <p style="margin: 0.5rem 0 0 0; font-weight: 700;">${new Date(consultation.start_date).toLocaleDateString()}</p>
                                </div>
                                <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px;">
                                    <small style="color: #666; font-weight: 600;">End Date</small>
                                    <p style="margin: 0.5rem 0 0 0; font-weight: 700;">${new Date(consultation.end_date).toLocaleDateString()}</p>
                                </div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <h6 style="color: var(--dark-blue); margin-bottom: 1rem;">Engagement Statistics</h6>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                                    <div style="text-align: center; padding: 1rem; background: #f5f5f5; border-radius: 8px;">
                                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-red);">${consultation.posts_count}</div>
                                        <small style="color: #666;">Total Feedback</small>
                                    </div>
                                    <div style="text-align: center; padding: 1rem; background: #f5f5f5; border-radius: 8px;">
                                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--light-blue);">${consultation.views}</div>
                                        <small style="color: #666;">Views</small>
                                    </div>
                                    <div style="text-align: center; padding: 1rem; background: #f5f5f5; border-radius: 8px;">
                                        <div style="font-size: 1.5rem; font-weight: 700; color: #4CAF50;">${new Date(consultation.created_at).toLocaleDateString()}</div>
                                        <small style="color: #666;">Posted</small>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('modalTitle').textContent = consultation.title;
                        document.getElementById('modalBody').innerHTML = html;
                        
                        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
                        modal.show();
                    }
                });
        }

        function submitFeedback(consultationId) {
            currentConsultationId = consultationId;
            <?php if (!isset($_SESSION['user_id'])): ?>
                alert('Please login to submit feedback.');
                window.location.href = 'login.php';
            <?php else: ?>
                document.getElementById('feedbackConsultationId').value = consultationId;
                const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
                modal.show();
            <?php endif; ?>
        }

        document.getElementById('feedbackForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const consultationId = document.getElementById('feedbackConsultationId').value;
            const message = document.getElementById('feedbackMessage').value;
            const category = document.getElementById('feedbackCategory').value;

            if (!message.trim()) {
                alert('Please enter feedback message');
                return;
            }

            // Submit feedback using the dedicated consultation feedback API
            fetch('API/consultation_feedback.php?action=submit_feedback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    consultation_id: consultationId,
                    message: message,
                    category: category
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Thank you! Your feedback has been submitted successfully.');
                    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('feedbackModal'));
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    document.getElementById('feedbackForm').reset();
                } else {
                    alert('Error submitting feedback: ' + (data.message || data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Error submitting feedback');
            });
        });

        function resetFilters() {
            document.getElementById('searchFilter').value = '';
            document.getElementById('categoryFilter').value = '';
            filterConsultations();
        }

        function filterConsultations() {
            const searchTerm = document.getElementById('searchFilter').value.toLowerCase();
            const category = document.getElementById('categoryFilter').value;
            const cards = document.querySelectorAll('.consultation-card');

            cards.forEach(card => {
                const title = card.dataset.title.toLowerCase();
                const cardCategory = card.dataset.category;
                
                const matchesSearch = title.includes(searchTerm);
                const matchesCategory = !category || cardCategory === category;
                
                card.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
            });
        }

        document.getElementById('searchFilter')?.addEventListener('keyup', filterConsultations);
        document.getElementById('categoryFilter')?.addEventListener('change', filterConsultations);
    </script>
</body>
</html>
