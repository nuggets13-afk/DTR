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

    // Vercel Environment Variables (Make sure these match your Prisma Dashboard)
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '5432'; // Default Postgres port is 5432
    $name = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    // Check if variables are missing
    if (!$host || !$user || !$pass) {
        throw new Exception("Database configuration missing in Vercel Environment Variables.");
    }

    // UPDATED: Changed to pgsql and added sslmode=require for Prisma/Aiven
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // Prisma Postgres pooling works best without persistent connections
        PDO::ATTR_PERSISTENT         => false, 
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        // This will help you see the EXACT error in Vercel Logs
        error_log("Connection failed: " . $e->getMessage());
        die("Database connection error. Check Vercel logs for details.");
    }
}
