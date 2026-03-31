<?php
declare(strict_types=1);

// This must come first to start the session correctly via config
require_once __DIR__ . '/config.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$publicPages = ['login.php', 'register.php', 'index.php'];

// 1. If NOT logged in and trying to access a PRIVATE page -> go to login
if (!isset($_SESSION['user_id']) && !in_array($currentPage, $publicPages)) {
    header('Location: login.php');
    exit;
}

// 2. If ALREADY logged in and trying to access login/register -> go to dashboard
if (isset($_SESSION['user_id']) && in_array($currentPage, ['login.php', 'register.php'])) {
    header('Location: dashboard.php');
    exit;
}
