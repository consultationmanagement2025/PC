<?php
// Wrapper to maintain backward-compatible logout path
// Includes the real logout implementation in the AUTH folder
require_once __DIR__ . '/AUTH/logout.php';
exit();

?>