<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// 1. If user is already logged in, send them to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Simple validation
    if (!$name || !$email || !$password) {
        $errors[] = "All fields are required.";
    } else {
        // Hash password and insert
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, total_required_hours) VALUES (?, ?, ?, 600)");
            $stmt->execute([$name, $email, $hash]);
            
            // Auto-login after registration
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;
            
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Register</title></head>
<body>
    <?php foreach($errors as $err) echo "<p style='color:red'>$err</p>"; ?>
    <form method="POST">
        <input type="text" name="name" placeholder="Name" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Register</button>
    </form>
</body>
</html>
