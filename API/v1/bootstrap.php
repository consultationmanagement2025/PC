<?php
/**
 * PCMS Integration API bootstrap
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/db.php';

// Dynamic search for shared/integration files across deployment layouts
$sharedPaths = [
    dirname(__DIR__, 4) . '/shared/integration/common.php',
    dirname(__DIR__, 3) . '/shared/integration/common.php',
    dirname(__DIR__, 2) . '/shared/integration/common.php',
    dirname(__DIR__, 2) . '/../shared/integration/common.php',
    (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/../shared/integration/common.php' : ''),
    (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/shared/integration/common.php' : ''),
];

$commonFile = null;
foreach ($sharedPaths as $path) {
    if (!empty($path) && file_exists($path)) {
        $commonFile = $path;
        break;
    }
}

if ($commonFile) {
    require_once $commonFile;
    $httpFile = dirname($commonFile) . '/HttpClient.php';
    if (file_exists($httpFile)) {
        require_once $httpFile;
    }
} else {
    header('Content-Type: application/json', true, 500);
    echo json_encode(['success' => false, 'message' => 'Integration shared library not found']);
    exit;
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$GLOBALS['integration_request_id'] = lgu2_request_id();
$conn = dbEnsureConnection();
$GLOBALS['integration_conn'] = $conn;

lgu2_ensure_integration_schema($conn);
lgu2_seed_integration_clients($conn, 'PCMS');

lgu2_add_column_if_missing($conn, 'consultations', 'external_ref', '`external_ref` varchar(128) DEFAULT NULL');
lgu2_add_column_if_missing($conn, 'consultations', 'source_system', '`source_system` varchar(64) DEFAULT NULL');
lgu2_add_column_if_missing($conn, 'consultations', 'phms_hearing_id', '`phms_hearing_id` int(11) DEFAULT NULL');
lgu2_add_column_if_missing($conn, 'consultations', 'synced_at', '`synced_at` timestamp NULL DEFAULT NULL');

$conn->query("CREATE TABLE IF NOT EXISTS `hearing_queue` (
  `queue_id` int(11) NOT NULL AUTO_INCREMENT,
  `phms_hearing_id` int(11) DEFAULT NULL,
  `phms_registration_id` int(11) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'queued',
  `external_ref` varchar(128) DEFAULT NULL,
  `source_system` varchar(64) DEFAULT NULL,
  `consultation_id` int(11) DEFAULT NULL,
  `payload_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`queue_id`),
  KEY `idx_hearing` (`phms_hearing_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

