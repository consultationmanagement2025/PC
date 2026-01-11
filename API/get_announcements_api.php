<?php
/**
 * API to fetch announcements for user portal
 * Returns latest announcements as JSON
 */
session_start();
require_once __DIR__ . '/../announcements.php';

header('Content-Type: application/json');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$latest = isset($_GET['latest']) ? true : false;

// Get announcements
$announcements = getLatestAnnouncements($limit);

if (!$announcements || empty($announcements)) {
    echo json_encode([]);
    exit();
}

// Return as array of announcements
echo json_encode($announcements);
?>
