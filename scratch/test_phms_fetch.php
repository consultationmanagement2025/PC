<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/feedback.php';

$res = fetchPhmsFeedbackFromApi();
echo "fetchPhmsFeedbackFromApi output:\n";
print_r($res);
