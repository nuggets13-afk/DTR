<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Vercel Environment Variables
    $host = getenv('DB_HOST');
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $port = getenv('DB_PORT') ?: '27755'; // Updated default port to match your Aiven screenshot

    // Check if variables are missing
    if (!$host || !$name || !$user || !$pass) {
        throw new Exception("Database configuration missing in Vercel Environment Variables. Check Host, Name, User, and Pass.");
    }

    // PostgreSQL DSN for Aiven (Requires SSL)
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        // This log goes to the Vercel 'Logs' tab
        error_log("Connection failed: " . $e->getMessage());
        
        // This output helps you debug immediately on the webpage
        die("Database connection error: " . $e->getMessage());
    }
}
