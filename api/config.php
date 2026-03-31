<?php
declare(strict_types=1);

// 1. MUST happen before session_start() and before ANY output
$sessionPath = '/tmp/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}

// Only set the path if a session hasn't been started yet by another file
if (session_status() === PHP_SESSION_NONE) {
    session_save_path($sessionPath);
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/**
 * Database connection using Vercel Environment Variables
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    if (!$host || !$user || !$pass) {
        die("Database configuration missing.");
    }

    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";
    
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection error: " . $e->getMessage());
    }
}
