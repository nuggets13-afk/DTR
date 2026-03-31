<?php
require_once __DIR__ . '/config.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$publicPages = ['login.php', 'register.php'];

// Use the session (which is now backed by our cookies in config.php)
if (!isset($_SESSION['user_id']) && !in_array($currentPage, $publicPages)) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_id']) && in_array($currentPage, $publicPages)) {
    header('Location: dashboard.php');
    exit;
}
