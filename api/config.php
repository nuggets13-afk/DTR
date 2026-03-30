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
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
$name = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$port = getenv('DB_PORT') ?: '3306';

// If these are empty, getenv() returns false, triggering the error you saw.

// Check if variables are missing
if (!$host || !$name || !$user) {
throw new Exception("Database configuration missing in Vercel Environment Variables.");
}

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

$options = [
PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES   => false,
// Critical for Serverless: Persistent connections can sometimes cause issues, 
// so we stick to standard connections here.
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
}
