<?php
declare(strict_types=1);

/**
 * VERCEL SAFE SESSION ALTERNATIVE
 * We use a cookie to store the user_id since /tmp is unreliable on Vercel
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// Fallback: If PHP session fails, check our custom "Persistent" cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['app_user_id'])) {
    // Basic verification - in a real app, you'd use a secure token
    $_SESSION['user_id'] = (int)$_COOKIE['app_user_id'];
    $_SESSION['user_name'] = $_COOKIE['app_user_name'] ?? 'User';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $name = getenv('DB_NAME') ?: 'postgres';

    $dsn = "pgsql:host={$host};port=5432;dbname={$name};sslmode=require";
    
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
