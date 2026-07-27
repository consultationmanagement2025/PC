<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower(trim($_SESSION['role'])) !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_availability':
            $adminId = (int)$_SESSION['user_id'];
            $date = trim($_POST['date'] ?? '');
            $start_time = trim($_POST['start_time'] ?? '');
            $end_time = trim($_POST['end_time'] ?? '');
            $max_bookings = max(1, intval($_POST['max_bookings'] ?? 1));
            $notes = trim($_POST['notes'] ?? '');

            if ($date === '' || $start_time === '' || $end_time === '') {
                $error = "Please fill in all required fields.";
            } elseif (strtotime($start_time) >= strtotime($end_time)) {
                $error = "End time must be after start time.";
            } elseif (date('N', strtotime($date)) >= 6) {
                $error = "Weekends are not available for government consultations.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO consultation_availability 
                    (admin_id, date, start_time, end_time, max_bookings, notes) 
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                if ($stmt) {
                    $stmt->bind_param('isssis', $adminId, $date, $start_time, $end_time, $max_bookings, $notes);
                    if ($stmt->execute()) {
                        $message = "Availability added successfully!";
                    } else {
                        $error = "Error adding availability: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
            break;

        case 'update_availability':
            $adminId = (int)$_SESSION['user_id'];
            $id = intval($_POST['id'] ?? 0);
            $is_available = isset($_POST['is_available']) ? 1 : 0;
            $max_bookings = max(1, intval($_POST['max_bookings'] ?? 1));
            $notes = trim($_POST['notes'] ?? '');

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE consultation_availability 
                    SET is_available = ?, max_bookings = ?, notes = ? 
                    WHERE id = ? AND admin_id = ?"
                );

                if ($stmt) {
                    $stmt->bind_param('iisii', $is_available, $max_bookings, $notes, $id, $adminId);
                    if ($stmt->execute()) {
                        $message = "Availability updated successfully!";
                    } else {
                        $error = "Error updating availability: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
            break;

        case 'delete_availability':
            $adminId = (int)$_SESSION['user_id'];
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare(
                    "DELETE FROM consultation_availability 
                    WHERE id = ? AND admin_id = ? AND current_bookings = 0"
                );

                if ($stmt) {
                    $stmt->bind_param('ii', $id, $adminId);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $message = "Availability deleted successfully!";
                        } else {
                            $error = "Cannot delete availability with existing bookings.";
                        }
                    } else {
                        $error = "Error deleting availability: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
            break;
    }
}

// Get current month/year for calendar display
$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('m'));

// Get availability for the month
$availability = [];
$stmt = $conn->prepare(
    "SELECT * FROM consultation_availability 
    WHERE admin_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
    ORDER BY date, start_time"
);
if ($stmt) {
    $adminId = (int)$_SESSION['user_id'];
    $stmt->bind_param('iii', $adminId, $year, $month);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $availability = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $error = "Error loading availability: " . $stmt->error;
    }
    $stmt->close();
} else {
    $error = "Database error: " . $conn->error;
}

// Get bookings for the month
$bookings = [];
$stmt = $conn->prepare(
    "SELECT cb.*, ca.date, ca.start_time, ca.end_time, c.title as consultation_title
    FROM consultation_bookings cb
    JOIN consultation_availability ca ON cb.availability_id = ca.id
    LEFT JOIN consultations c ON cb.consultation_id = c.id
    WHERE ca.admin_id = ? AND YEAR(ca.date) = ? AND MONTH(ca.date) = ?
    ORDER BY ca.date, ca.start_time"
);
if ($stmt) {
    $adminId = (int)$_SESSION['user_id'];
    $stmt->bind_param('iii', $adminId, $year, $month);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $bookings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt->close();
}

// Helper function to get days in month
function getDaysInMonth($year, $month) {
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

// Helper function to get first day of month
function getFirstDayOfMonth($year, $month) {
    return date('N', strtotime("$year-$month-01"));
}

function isWeekendDate($date) {
    $timestamp = strtotime($date);
    return $timestamp !== false && date('N', $timestamp) >= 6;
}

function getBookingStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'confirmed':
            return 'success';
        case 'cancelled':
            return 'danger';
        case 'completed':
            return 'info';
        default:
            return 'secondary';
    }
}

// Group availability by date
$availability_by_date = [];
foreach ($availability as $slot) {
    $availability_by_date[$slot['date']][] = $slot;
}

// Group bookings by date
$bookings_by_date = [];
foreach ($bookings as $booking) {
    $bookings_by_date[$booking['date']][] = $booking;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Availability - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .calendar-header {
            background: linear-gradient(135deg, #991b1b, #7f1d1d);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e5e7eb;
        }
        .calendar-day-header {
            background: #f9fafb;
            padding: 1rem;
            text-align: center;
            font-weight: 700;
            color: #6b7280;
            font-size: 0.875rem;
        }
        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 0.5rem;
            position: relative;
        }
        .calendar-day-number {
            font-weight: 700;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .calendar-day.other-month {
            background: #f9fafb;
            opacity: 0.5;
        }
        .calendar-day.weekend {
            background: #f8fafc;
            color: #6b7280;
            cursor: default;
        }
        .calendar-day.weekend .calendar-day-number {
            color: #9ca3af;
        }
        .calendar-day.weekend .calendar-day-weekend-label {
            font-size: 0.72rem;
            color: #9ca3af;
            margin-top: 0.35rem;
        }
        .calendar-day.clickable:hover {
            background: #f3f4f6;
        }
        .availability-slot {
            font-size: 0.75rem;
            padding: 0.25rem;
            margin: 0.125rem 0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .availability-slot.available {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .availability-slot.full {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .availability-slot:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .booking-indicator {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #f59e0b;
        }
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }
        .modal-header {
            background: linear-gradient(135deg, #991b1b, #7f1d1d);
            color: white;
            border-radius: 12px 12px 0 0;
            border: none;
        }
        .btn-primary {
            background: #991b1b;
            border-color: #991b1b;
        }
        .btn-primary:hover {
            background: #7f1d1d;
            border-color: #7f1d1d;
        }
        .time-slots-container {
            max-height: 300px;
            overflow-y: auto;
        }
        .time-slot-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
        }
        .time-slot-item:hover {
            background: #f9fafb;
            border-color: #991b1b;
        }
    </style>
</head>
<body>
    <?php if (file_exists(__DIR__ . '/admin_header.php')) {
        include 'admin_header.php';
    } ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="bi bi-calendar-check"></i> Consultation Availability</h2>
                <p class="text-muted">Manage your available time slots for citizen consultations</p>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <div>
                            <button class="btn btn-outline-light btn-sm me-2" onclick="changeMonth(-1)">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <h4 class="d-inline">
                                <?php echo date('F Y', strtotime("$year-$month-01")); ?>
                            </h4>
                            <button class="btn btn-outline-light btn-sm ms-2" onclick="changeMonth(1)">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <button class="btn btn-light" onclick="showAddAvailabilityModal()">
                            <i class="bi bi-plus-circle"></i> Add Availability
                        </button>
                    </div>
                    
                    <div class="calendar-grid">
                        <?php
                        // Day headers
                        $dayHeaders = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        foreach ($dayHeaders as $day): ?>
                            <div class="calendar-day-header"><?php echo $day; ?></div>
                        <?php endforeach; ?>
                        
                        <?php
                        // Calendar days
                        $firstDay = getFirstDayOfMonth($year, $month);
                        $daysInMonth = getDaysInMonth($year, $month);
                        $totalCells = ceil(($firstDay + $daysInMonth - 1) / 7) * 7;
                        
                        for ($i = 0; $i < $totalCells; $i++) {
                            $dayNumber = $i - $firstDay + 2;
                            $isCurrentMonth = $dayNumber >= 1 && $dayNumber <= $daysInMonth;
                            $dateString = $isCurrentMonth ? "$year-" . str_pad($month, 2, '0') . "-" . str_pad($dayNumber, 2, '0') : '';
                            $isWeekend = $isCurrentMonth && isWeekendDate($dateString);
                            
                            // Get availability and bookings for this date
                            $dayAvailability = $availability_by_date[$dateString] ?? [];
                            $dayBookings = $bookings_by_date[$dateString] ?? [];
                            $hasBookings = !empty($dayBookings);
                            $dayClasses = 'calendar-day';
                            if (!$isCurrentMonth) {
                                $dayClasses .= ' other-month';
                            } elseif ($isWeekend) {
                                $dayClasses .= ' weekend';
                            } else {
                                $dayClasses .= ' clickable';
                            }
                            $clickAttr = ($isCurrentMonth && !$isWeekend) ? " onclick=\"showAddAvailabilityModal('{$dateString}')\"" : '';

                            echo '<div class="' . $dayClasses . '"' . $clickAttr . '>';
                            
                            if ($isCurrentMonth) {
                                echo '<div class="calendar-day-number">' . $dayNumber . '</div>';
                                
                                if ($hasBookings) {
                                    echo '<div class="booking-indicator" title="Has bookings"></div>';
                                }
                                
                                // Show availability slots
                                foreach ($dayAvailability as $slot) {
                                    $isFull = $slot['current_bookings'] >= $slot['max_bookings'];
                                    $statusClass = $isFull ? 'full' : 'available';
                                    $statusText = $isFull ? 'Full' : 'Available';
                                    
                                    echo '<div class="availability-slot ' . $statusClass . '" 
                                             onclick="editAvailability(' . $slot['id'] . ')" 
                                             title="' . htmlspecialchars($slot['start_time'] . ' - ' . $slot['end_time']) . ' - ' . $statusText . '">
                                            ' . substr($slot['start_time'], 0, 5) . ' - ' . substr($slot['end_time'], 0, 5) . '
                                            <small>(' . $slot['current_bookings'] . '/' . $slot['max_bookings'] . ')</small>
                                        </div>';
                                }
                            }
                            
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bookings Summary -->
        <div class="row mt-4">
            <div class="col-12">
                <h4><i class="bi bi-people"></i> Recent Bookings</h4>
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($bookings)): ?>
                            <p class="text-muted">No bookings for this month.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Citizen</th>
                                            <th>Consultation</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($booking['date'])); ?></td>
                                                <td><?php echo substr($booking['start_time'], 0, 5) . ' - ' . substr($booking['end_time'], 0, 5); ?></td>
                                                <td><?php echo htmlspecialchars($booking['citizen_name']); ?></td>
                                                <td><?php echo htmlspecialchars($booking['consultation_title'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getBookingStatusClass($booking['booking_status']); ?>">
                                                        <?php echo ucfirst($booking['booking_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Availability Modal -->
    <div class="modal fade" id="addAvailabilityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus"></i> Add Availability</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_availability">
                        
                        <div class="mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" name="date" id="addAvailabilityDate" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Time *</label>
                                    <input type="time" name="start_time" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Time *</label>
                                    <input type="time" name="end_time" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Max Bookings</label>
                            <input type="number" name="max_bookings" class="form-control" value="1" min="1" max="10">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes for this time slot"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Availability</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Availability Modal -->
    <div class="modal fade" id="editAvailabilityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-edit"></i> Edit Availability</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editAvailabilityForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_availability">
                        <input type="hidden" name="id" id="editAvailabilityId">
                        
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="text" id="editDate" class="form-control" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Time</label>
                                    <input type="text" id="editStartTime" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Time</label>
                                    <input type="text" id="editEndTime" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Max Bookings</label>
                            <input type="number" name="max_bookings" id="editMaxBookings" class="form-control" min="1" max="10">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_available" id="editIsAvailable">
                                <label class="form-check-label" for="editIsAvailable">
                                    Available for booking
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Current Bookings</h6>
                            <div id="currentBookings" class="time-slots-container">
                                <!-- Bookings will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="deleteAvailability()">Delete</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentAvailabilityData = {};
        
        function changeMonth(direction) {
            const currentMonth = <?php echo $month; ?>;
            const currentYear = <?php echo $year; ?>;
            
            let newMonth = currentMonth + direction;
            let newYear = currentYear;
            
            if (newMonth < 1) {
                newMonth = 12;
                newYear--;
            } else if (newMonth > 12) {
                newMonth = 1;
                newYear++;
            }
            
            window.location.href = `?year=${newYear}&month=${newMonth}`;
        }
        
        function showAddAvailabilityModal(dateValue = '') {
            const modal = new bootstrap.Modal(document.getElementById('addAvailabilityModal'));
            const dateInput = document.getElementById('addAvailabilityDate');
            if (dateInput) {
                if (dateValue) {
                    const day = new Date(dateValue).getDay();
                    if (day === 0 || day === 6) {
                        alert('Weekends are not available for government consultations.');
                        return;
                    }
                }
                dateInput.value = dateValue;
                dateInput.dispatchEvent(new Event('change'));
            }
            modal.show();
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('addAvailabilityDate');
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    const dateValue = this.value;
                    if (!dateValue) return;
                    const day = new Date(dateValue).getDay();
                    if (day === 0 || day === 6) {
                        alert('Weekends are not available for government consultations.');
                        this.value = '';
                    }
                });
            }
        });
        
        function editAvailability(id) {
            // Find availability data
            const availability = <?php echo json_encode($availability); ?>;
            const slot = availability.find(s => s.id == id);
            
            if (slot) {
                currentAvailabilityData = slot;
                
                document.getElementById('editAvailabilityId').value = slot.id;
                document.getElementById('editDate').value = slot.date;
                document.getElementById('editStartTime').value = slot.start_time;
                document.getElementById('editEndTime').value = slot.end_time;
                document.getElementById('editMaxBookings').value = slot.max_bookings;
                document.getElementById('editIsAvailable').checked = slot.is_available == 1;
                document.getElementById('editNotes').value = slot.notes || '';
                
                // Load current bookings
                loadCurrentBookings(id);
                
                const modal = new bootstrap.Modal(document.getElementById('editAvailabilityModal'));
                modal.show();
            }
        }
        
        function loadCurrentBookings(availabilityId) {
            const bookings = <?php echo json_encode($bookings); ?>;
            const slotBookings = bookings.filter(b => b.availability_id == availabilityId);
            
            const container = document.getElementById('currentBookings');
            
            if (slotBookings.length === 0) {
                container.innerHTML = '<p class="text-muted">No bookings for this time slot.</p>';
            } else {
                container.innerHTML = slotBookings.map(booking => `
                    <div class="time-slot-item">
                        <div>
                            <strong>${booking.citizen_name}</strong><br>
                            <small>${booking.citizen_email}</small>
                        </div>
                        <div>
                            <span class="badge bg-${getStatusColor(booking.booking_status)}">
                                ${booking.booking_status}
                            </span>
                        </div>
                    </div>
                `).join('');
            }
        }
        
        function getStatusColor(status) {
            switch(status) {
                case 'pending': return 'warning';
                case 'confirmed': return 'success';
                case 'cancelled': return 'danger';
                case 'completed': return 'info';
                default: return 'secondary';
            }
        }
        
        function deleteAvailability() {
            if (confirm('Are you sure you want to delete this availability? This can only be done if there are no existing bookings.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_availability">
                    <input type="hidden" name="id" value="${currentAvailabilityData.id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewBooking(bookingId) {
            // Implement booking view functionality
            alert('View booking details for booking ID: ' + bookingId);
        }
    </script>
</body>
</html>
