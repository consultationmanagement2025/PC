<?php
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['fullname'] = 'Sample Resource Person Staff';
$_SESSION['email'] = 'samplestaff01@gmail.com';
$_SESSION['role'] = 'resource_person';

require_once __DIR__ . '/../resource_person_dashboard.php';
