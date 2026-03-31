<?php
declare(strict_types=1);

// 1. Vercel-Specific Session Fix
// We must store sessions in /tmp for them to persist between redirects
$sessionPath = '/tmp/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}
session_save_path($sessionPath);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => true, // Vercel uses HTTPS by default
        'cookie_samesite' => 'Lax',
    ]);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    // Use getenv() for Vercel Environment Variables
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    if (!$host || !$user || !$pass) {
        die("Database configuration missing in Environment Variables.");
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
        // Output the error briefly to see if it's a connection issue vs a logic issue
        die("Database connection error: " . $e->getMessage());
    }
}
