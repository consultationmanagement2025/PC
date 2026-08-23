<?php
$_GET['action'] = 'decline_submission';
$rawInput = json_encode(['id' => 1, 'reason' => 'Testing decline action']);

// Mock php://input by defining input data
$_POST = ['id' => 1, 'reason' => 'Testing decline action'];

require_once __DIR__ . '/../API/consultations_api.php';
