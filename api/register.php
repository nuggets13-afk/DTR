<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// If user is already logged in, send them to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$name = '';
$email = '';
$totalRequiredHours = '600'; // Default for PTC OJT

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $totalRequiredHours = trim($_POST['total_required_hours'] ?? '');

    // Validation
    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($totalRequiredHours === '' || !is_numeric($totalRequiredHours)) {
        $errors[] = 'Total required hours must be a number.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            
            // Check if email exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Email is already registered.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                // PostgreSQL Insert
                $insert = $pdo->prepare('
                    INSERT INTO users (name, email, password, total_required_hours)
                    VALUES (?, ?, ?, ?)
                ');
                
                $insert->execute([
                    $name, 
                    $email, 
                    $hashed, 
                    (float)$totalRequiredHours
                ]);

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            // This captures the exact error (e.g., missing table or column)
            error_log("Registration Error: " . $e->getMessage());
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
