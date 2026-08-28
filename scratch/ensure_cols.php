<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../DATABASE/feedback.php';

if (function_exists('ensureFeedbackTableColumns')) {
    ensureFeedbackTableColumns($conn);
    echo "Executed ensureFeedbackTableColumns successfully.\n";
}
