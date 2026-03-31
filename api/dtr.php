<?php
require_once __DIR__ . '/auth.php';
// If they pass auth.php, they are logged in, so show dashboard
header('Location: dashboard.php');
exit;
