<?php
declare(strict_types=1);

// 1. Define path
$sessionPath = '/tmp/sessions';

// 2. Only run session setup if headers haven't been sent and session isn't active
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }
    session_save_path($sessionPath);
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => true, 
        'cookie_samesite' => 'Lax',
    ]);
}

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
