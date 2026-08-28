<?php
/**
 * ORTS Documents Fetch API for PCMS
 * Proxies GET requests to https://ort.spvalenzuela.com/api/v1/documents.php
 * Filters documents eligible for public consultation (requires_public_hearing == 1)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (file_exists(__DIR__ . '/../UTILS/orts_integration_utils.php')) {
    require_once __DIR__ . '/../UTILS/orts_integration_utils.php';
} elseif (file_exists(__DIR__ . '/UTILS/orts_integration_utils.php')) {
    require_once __DIR__ . '/UTILS/orts_integration_utils.php';
}

$filters = [];
if (isset($_GET['status'])) $filters['status'] = trim($_GET['status']);
if (isset($_GET['limit'])) $filters['limit'] = (int)$_GET['limit'];
if (isset($_GET['id'])) $filters['id'] = (int)$_GET['id'];
if (isset($_GET['ref'])) $filters['ref'] = trim($_GET['ref']);

// Default to status=Committee Stage & limit=20 if no filters specified
if (empty($filters)) {
    $filters = ['status' => 'Committee Stage', 'limit' => 20];
}

$res = fetchOrtsDocuments($filters);

if (!empty($res['data']['documents']) && is_array($res['data']['documents'])) {
    // Flag or filter eligible documents requiring public hearing
    $docs = $res['data']['documents'];
    $eligible = array_values(array_filter($docs, function($doc) {
        return !isset($doc['requires_public_hearing']) || (int)$doc['requires_public_hearing'] === 1;
    }));
    $res['data']['documents_eligible_for_consultation'] = $eligible;
}

http_response_code(!empty($res['http_code']) && $res['http_code'] > 0 ? $res['http_code'] : 200);
echo json_encode($res, JSON_PRETTY_PRINT);
