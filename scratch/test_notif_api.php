<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['email'] = 'admin@valenzuela.gov.ph';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/notifications.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$rows = getUserNotifications($uid, 50);
$unread = getUnreadNotificationsCount($uid);

echo json_encode(['success' => true, 'data' => ['items' => $rows, 'unread' => $unread]], JSON_PRETTY_PRINT);
