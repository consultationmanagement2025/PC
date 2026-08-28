<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_SERVERS_CHECK', 1);
$_GET['action'] = 'phms_list';
$_GET['limit'] = '50';
$_GET['offset'] = '0';

require_once __DIR__ . '/../admin-side/API/feedback_api.php';
