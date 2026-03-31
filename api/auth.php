<?php
declare(strict_types=1);

// Vercel/Serverless Session Fix: Standard PHP sessions fail if the path isn't writable.
// We use /tmp because it is the only writable directory in Vercel's environment.
if (!is_dir('/tmp/sessions')) {
    mkdir('/tmp/sessions', 0700, true);
}
session_save_path('/tmp/sessions');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
