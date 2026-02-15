<?php
session_start();

require_once __DIR__ . '/UTILS/security-headers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/DATABASE/consultations.php';

header('X-Content-Type-Options: nosniff');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = isset($_GET['t']) ? trim((string)$_GET['t']) : '';

if ($id <= 0 || $token === '') {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

// Basic token format check (hex)
if (!preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
    http_response_code(400);
    echo 'Invalid token.';
    exit;
}

try {
    dbEnsureConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$consultation = getConsultationById($id);
if (!$consultation) {
    http_response_code(404);
    echo 'Consultation not found.';
    exit;
}

$tokRow = null;
$stmt = $conn->prepare('SELECT summary_token, summary_token_expires FROM consultations WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $tokRow = $res ? $res->fetch_assoc() : null;
    }
    $stmt->close();
}

$dbToken = $tokRow && isset($tokRow['summary_token']) ? (string)$tokRow['summary_token'] : '';
$dbExp = $tokRow && isset($tokRow['summary_token_expires']) ? (string)$tokRow['summary_token_expires'] : '';

if ($dbToken === '' || !hash_equals($dbToken, $token)) {
    http_response_code(403);
    echo 'Unauthorized.';
    exit;
}

if ($dbExp) {
    $expTs = strtotime($dbExp);
    if ($expTs !== false && time() > $expTs) {
        http_response_code(410);
        echo 'This download link has expired.';
        exit;
    }
}

$title = (string)($consultation['title'] ?? '');
$desc = (string)($consultation['description'] ?? '');
$status = (string)($consultation['status'] ?? 'draft');
$userName = (string)($consultation['user_name'] ?? '');
$userEmail = (string)($consultation['user_email'] ?? '');
$createdAt = (string)($consultation['created_at'] ?? '');

$download = isset($_GET['download']) && (string)$_GET['download'] === '1';
if ($download) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="consultation-summary-' . $id . '.html"');
} else {
    header('Content-Type: text/html; charset=UTF-8');
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$createdText = $createdAt ? date('F j, Y \\a\\t g:i A', strtotime($createdAt)) : '';

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consultation Summary</title>
    <style>
        body{font-family:Arial, sans-serif; color:#111827; margin:24px;}
        .card{border:1px solid #e5e7eb; border-radius:10px; padding:18px; max-width:900px; margin:0 auto;}
        .row{display:flex; gap:18px; flex-wrap:wrap;}
        .col{flex:1; min-width:260px;}
        .label{font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;}
        .value{font-size:14px; color:#111827;}
        pre{white-space:pre-wrap; background:#f9fafb; border:1px solid #e5e7eb; padding:12px; border-radius:8px; font-family:inherit; font-size:14px;}
        .actions{display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;}
        .btn{display:inline-block; padding:10px 14px; border-radius:8px; text-decoration:none; font-weight:700; font-size:14px;}
        .btn-primary{background:#991b1b; color:#fff;}
        .btn-secondary{background:#f3f4f6; color:#111827;}
        @media print{.actions{display:none;} body{margin:0;} .card{border:none;}}
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin:0 0 12px 0;">Consultation Summary</h2>

        <div class="row">
            <div class="col">
                <div class="label">Reference ID</div>
                <div class="value"><?php echo h($id); ?></div>
            </div>
            <div class="col">
                <div class="label">Submitted</div>
                <div class="value"><?php echo h($createdText); ?></div>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e5e7eb; margin:14px 0;">

        <div class="row">
            <div class="col">
                <div class="label">Topic</div>
                <div class="value"><?php echo h($title); ?></div>
            </div>
            <div class="col">
                <div class="label">Status</div>
                <div class="value"><?php echo h(ucfirst(strtolower($status))); ?></div>
            </div>
        </div>

        <div class="row" style="margin-top:12px;">
            <div class="col">
                <div class="label">Name</div>
                <div class="value"><?php echo h($userName); ?></div>
            </div>
            <div class="col">
                <div class="label">Email</div>
                <div class="value"><?php echo h($userEmail); ?></div>
            </div>
        </div>

        <div style="margin-top:14px;">
            <div class="label">Details</div>
            <pre><?php echo h($desc); ?></pre>
        </div>

        <div class="actions">
            <a class="btn btn-primary" href="#" onclick="window.print(); return false;">Print / Save as PDF</a>
            <a class="btn btn-secondary" href="?id=<?php echo urlencode((string)$id); ?>&t=<?php echo urlencode($token); ?>&download=1">Download HTML</a>
        </div>
    </div>
</body>
</html>
