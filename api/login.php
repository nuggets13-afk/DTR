<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Start session if not already started (config.php does this, but safety first)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($password === '') $errors[] = 'Password is required.';

    if (!$errors) {
        try {
            $pdo = db();
            // We select 'name' because your session logic uses $_SESSION['user_name'] = $user['name']
            $stmt = $pdo->prepare("SELECT id, name, password FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid email or password.';
            } else {
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                header('Location: dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            $errors[] = "Connection error: " . $e->getMessage();
        }
    }
}
?>
