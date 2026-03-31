<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $errors[] = "Please provide a valid name, email, and password (min 6 chars).";
    } else {
        try {
            $pdo = db();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, total_required_hours) VALUES (?, ?, ?, 600)");
            $stmt->execute([$name, $email, $hash]);
            
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = (str_contains($e->getMessage(), 'unique')) ? "Email already registered." : "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | OJT Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: radial-gradient(1000px 600px at 15% -10%, #2a0a0a 0%, #0b0b0b 45%, #050505 100%); color: #f5f5f1; font-family: sans-serif; }
        .auth-card { border: 1px solid rgba(229,9,20,.28); border-radius: 14px; background: linear-gradient(180deg, #171717 0%, #101010 100%); box-shadow: 0 14px 36px rgba(0,0,0,.38); }
        .btn-red { background: #e10600; border: none; font-weight: 700; border-radius: 10px; padding: .6rem; color: white; }
        .btn-red:hover { background: #b80400; color: white; }
        .form-control { background: #111; border: 1px solid #343434; color: white; }
        .form-control:focus { background: #111; color: white; border-color: #e10600; box-shadow: none; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card auth-card p-4">
                    <h2 class="text-center mb-4"><img src="OJTTracking.png" alt="OJT Tracking" style="height:44px;"></h2>
                    <h4 class="text-center mb-4">Create Account</h4>
                    
                    <?php foreach($errors as $err): ?>
                        <div class="alert alert-danger py-2"><?= $err ?></div>
                    <?php endforeach; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-red w-100">Register</button>
                        </div>
                    </form>
                    <p class="text-center mt-4 text-secondary">
                        Already have an account? <a href="login.php" class="text-danger">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
