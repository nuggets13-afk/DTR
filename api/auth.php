<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Get the current script name (e.g., 'login.php' or 'dashboard.php')
$currentPage = basename($_SERVER['PHP_SELF']);

// Define pages that DON'T require a login
$publicPages = ['login.php', 'register.php', 'index.php'];

// Redirect to login ONLY if:
// 1. The user is NOT logged in
// 2. The current page is NOT in the public list
if (!isset($_SESSION['user_id']) && !in_array($currentPage, $publicPages)) {
    header('Location: login.php');
    exit;
}

// Redirect to dashboard if:
// 1. The user IS already logged in
// 2. They are trying to access login or register
if (isset($_SESSION['user_id']) && in_array($currentPage, ['login.php', 'register.php'])) {
    header('Location: dashboard.php');
    exit;
}
