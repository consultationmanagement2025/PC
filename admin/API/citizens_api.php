<?php
/**
 * Citizens API - Enhanced Version
 * Returns detailed citizen records aggregated from users, consultations, and feedback tables.
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_path = file_exists(__DIR__ . '/../db.php') ? (__DIR__ . '/../db.php') : (file_exists(__DIR__ . '/../../db.php') ? (__DIR__ . '/../../db.php') : (__DIR__ . '/db.php'));
if (file_exists($db_path)) {
    require_once $db_path;
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $citizens = [];

            // 1. Fetch registered users with citizen or empty roles
            if (isset($conn) && $conn) {
                $uSql = "SELECT id, fullname, username, email, role, status, created_at FROM users WHERE role IS NULL OR LOWER(role) IN ('citizen', 'user', '') OR role = ''";
                $uRes = $conn->query($uSql);
                if ($uRes) {
                    while ($uRow = $uRes->fetch_assoc()) {
                        $em = strtolower(trim($uRow['email']));
                        if (empty($em)) continue;
                        $citizens[$em] = [
                            'user_id' => (int)$uRow['id'],
                            'name' => !empty($uRow['fullname']) ? $uRow['fullname'] : (!empty($uRow['username']) ? $uRow['username'] : 'Citizen'),
                            'email' => $uRow['email'],
                            'barangay' => 'Valenzuela City',
                            'is_verified' => true,
                            'status' => !empty($uRow['status']) ? $uRow['status'] : 'active',
                            'consultation_count' => 0,
                            'feedback_count' => 0,
                            'survey_vote_count' => 0,
                            'last_activity' => $uRow['created_at']
                        ];
                    }
                }

                // 2. Aggregate from consultations table
                $sql1 = "SELECT user_email AS email, user_name AS name, COUNT(*) AS consultation_count, MAX(created_at) AS last_consultation 
                          FROM consultations 
                          WHERE user_email IS NOT NULL AND user_email != '' 
                          GROUP BY user_email";
                $r1 = $conn->query($sql1);
                if ($r1) {
                    while ($row = $r1->fetch_assoc()) {
                        $em = strtolower(trim($row['email']));
                        if (isset($citizens[$em])) {
                            $citizens[$em]['consultation_count'] = (int)$row['consultation_count'];
                            if ($row['last_consultation'] > $citizens[$em]['last_activity']) {
                                $citizens[$em]['last_activity'] = $row['last_consultation'];
                            }
                        } else {
                            $citizens[$em] = [
                                'user_id' => 0,
                                'name' => !empty($row['name']) ? $row['name'] : 'Citizen Submitter',
                                'email' => $row['email'],
                                'barangay' => 'Valenzuela City',
                                'is_verified' => true,
                                'status' => 'active',
                                'consultation_count' => (int)$row['consultation_count'],
                                'feedback_count' => 0,
                                'survey_vote_count' => 0,
                                'last_activity' => $row['last_consultation']
                            ];
                        }
                    }
                }

                // 3. Aggregate from feedback table
                $sql2 = "SELECT guest_email AS email, guest_name AS name, category, COUNT(*) AS f_count, MAX(created_at) AS last_f 
                          FROM feedback 
                          WHERE guest_email IS NOT NULL AND guest_email != '' 
                          GROUP BY guest_email, category";
                $r2 = $conn->query($sql2);
                if ($r2) {
                    while ($row = $r2->fetch_assoc()) {
                        $em = strtolower(trim($row['email']));
                        $isSurvey = (strtolower($row['category']) === 'survey vote');
                        
                        if (isset($citizens[$em])) {
                            if ($isSurvey) {
                                $citizens[$em]['survey_vote_count'] += (int)$row['f_count'];
                            } else {
                                $citizens[$em]['feedback_count'] += (int)$row['f_count'];
                            }
                            if ($row['last_f'] > $citizens[$em]['last_activity']) {
                                $citizens[$em]['last_activity'] = $row['last_f'];
                            }
                        } else {
                            $citizens[$em] = [
                                'user_id' => 0,
                                'name' => !empty($row['name']) ? $row['name'] : 'Citizen Submitter',
                                'email' => $row['email'],
                                'barangay' => 'Valenzuela City',
                                'is_verified' => true,
                                'status' => 'active',
                                'consultation_count' => 0,
                                'feedback_count' => $isSurvey ? 0 : (int)$row['f_count'],
                                'survey_vote_count' => $isSurvey ? (int)$row['f_count'] : 0,
                                'last_activity' => $row['last_f']
                            ];
                        }
                    }
                }
            }

            // Convert to list & sort by last_activity DESC
            $list = array_values($citizens);
            usort($list, function($a, $b) {
                return strtotime($b['last_activity'] ?? '2000-01-01') - strtotime($a['last_activity'] ?? '2000-01-01');
            });

            foreach ($list as $i => &$c) {
                $c['id'] = $i + 1;
                $c['total_submissions'] = $c['consultation_count'] + $c['feedback_count'] + $c['survey_vote_count'];
            }
            unset($c);

            echo json_encode(['success' => true, 'data' => $list]);
            break;

        case 'get_dossier':
            $email = trim($_GET['email'] ?? '');
            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Email required']);
                exit;
            }

            $safeEmail = $conn->real_escape_string($email);
            
            // Proposals submitted
            $proposals = [];
            $pRes = $conn->query("SELECT id, title, category, status, created_at, tracking_number FROM consultations WHERE user_email = '$safeEmail' ORDER BY created_at DESC");
            if ($pRes) {
                while ($p = $pRes->fetch_assoc()) {
                    $proposals[] = $p;
                }
            }

            // Feedback & Survey Votes
            $activity = [];
            $fRes = $conn->query("SELECT f.id, f.category, f.message, f.rating, f.status, f.created_at, f.tracking_token, c.title as consultation_title FROM feedback f LEFT JOIN consultations c ON f.consultation_id = c.id WHERE f.guest_email = '$safeEmail' ORDER BY f.created_at DESC");
            if ($fRes) {
                while ($f = $fRes->fetch_assoc()) {
                    $activity[] = $f;
                }
            }

            echo json_encode([
                'success' => true,
                'email' => $email,
                'proposals' => $proposals,
                'activity' => $activity
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
