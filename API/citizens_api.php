<?php
/**
 * Citizens API
 * Returns a list of unique citizen submitters aggregated from consultations and feedback tables.
 * Each citizen record includes: name, email, consultation count, feedback count, last activity date.
 */
header('Content-Type: application/json');
session_start();
require_once '../db.php';

// Check admin role
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if ($current_role !== 'admin' && $current_role !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Gather unique citizen emails from consultations and feedback
            $citizens = [];

            // From consultations
            $sql1 = "SELECT user_email AS email, user_name AS name, COUNT(*) AS consultation_count, MAX(created_at) AS last_consultation 
                      FROM consultations 
                      WHERE user_email IS NOT NULL AND user_email != '' 
                      GROUP BY user_email";
            $r1 = $conn->query($sql1);
            if ($r1) {
                while ($row = $r1->fetch_assoc()) {
                    $em = strtolower(trim($row['email']));
                    $citizens[$em] = [
                        'email' => $row['email'],
                        'name' => $row['name'] ?: 'Unknown',
                        'consultation_count' => (int)$row['consultation_count'],
                        'feedback_count' => 0,
                        'last_consultation' => $row['last_consultation'],
                        'last_feedback' => null,
                        'last_activity' => $row['last_consultation']
                    ];
                }
            }

            // From feedback
            $sql2 = "SELECT guest_email AS email, guest_name AS name, COUNT(*) AS feedback_count, MAX(created_at) AS last_feedback 
                      FROM feedback 
                      WHERE guest_email IS NOT NULL AND guest_email != '' 
                      GROUP BY guest_email";
            $r2 = $conn->query($sql2);
            if ($r2) {
                while ($row = $r2->fetch_assoc()) {
                    $em = strtolower(trim($row['email']));
                    if (isset($citizens[$em])) {
                        $citizens[$em]['feedback_count'] = (int)$row['feedback_count'];
                        $citizens[$em]['last_feedback'] = $row['last_feedback'];
                        // Update name if current is Unknown
                        if ($citizens[$em]['name'] === 'Unknown' && $row['name']) {
                            $citizens[$em]['name'] = $row['name'];
                        }
                        // Update last_activity
                        if ($row['last_feedback'] > $citizens[$em]['last_activity']) {
                            $citizens[$em]['last_activity'] = $row['last_feedback'];
                        }
                    } else {
                        $citizens[$em] = [
                            'email' => $row['email'],
                            'name' => $row['name'] ?: 'Unknown',
                            'consultation_count' => 0,
                            'feedback_count' => (int)$row['feedback_count'],
                            'last_consultation' => null,
                            'last_feedback' => $row['last_feedback'],
                            'last_activity' => $row['last_feedback']
                        ];
                    }
                }
            }

            // Convert to indexed array and sort by last_activity desc
            $list = array_values($citizens);
            usort($list, function($a, $b) {
                return strtotime($b['last_activity'] ?? '2000-01-01') - strtotime($a['last_activity'] ?? '2000-01-01');
            });

            // Add an ID for frontend use
            foreach ($list as $i => &$c) {
                $c['id'] = $i + 1;
                $c['total_submissions'] = $c['consultation_count'] + $c['feedback_count'];
            }
            unset($c);

            echo json_encode(['success' => true, 'data' => $list]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
