<?php
// Wrapper to maintain backward-compatible login path
// Includes the real login implementation in the AUTH folder
require_once __DIR__ . '/AUTH/login.php';
exit();
?>
