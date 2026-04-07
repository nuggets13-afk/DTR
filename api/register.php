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
    <script>
        window.va = window.va || function () { (window.vaq = window.vaq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/insights/script.js"></script>
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(1100px 650px at 18% -12%, #2a0a0a 0%, #0b0b0b 45%, #050505 100%);
            color: #f5f5f1;
            font-family: "Netflix Sans", "Helvetica Neue", Arial, sans-serif;
        }
        .auth-card {
            border: 1px solid rgba(229,9,20,.30);
            border-radius: 14px;
            background: linear-gradient(180deg, #171717 0%, #101010 100%);
            box-shadow: 0 14px 36px rgba(0,0,0,.38);
        }
        .brand-logo { height: 44px; width: auto; max-width: 100%; filter: drop-shadow(0 0 8px rgba(225,6,0,.35)); }
        .auth-title { color: #f8fafc; letter-spacing: .2px; }
        .auth-subtext { color: #d1d5db; }
        .form-label { color: #f8fafc !important; font-weight: 600; }
        .btn-red { background: #e10600; border: none; font-weight: 700; border-radius: 10px; padding: .6rem; color: #fff; }
        .btn-red:hover { background: #b80400; color: #fff; }
        .form-control { background: #111; border: 1px solid #343434; color: #fff; }
        .form-control:focus { background: #111; color: #fff; border-color: #e10600; box-shadow: 0 0 0 .18rem rgba(225,6,0,.2); }
        .auth-foot { color: #e5e7eb !important; }
        .auth-foot a { color: #fca5a5 !important; text-decoration: none; font-weight: 700; }
        .auth-foot a:hover { color: #fecaca !important; }

        @media (max-width: 576px) {
            body { background: #050505; }
            .container { padding-left: .5rem; padding-right: .5rem; }
            .auth-card { border-radius: 12px; }
            .auth-card.p-4 { padding: 1rem !important; }
            .brand-logo { height: 34px; }
            .auth-title { font-size: 1rem; margin-bottom: .75rem !important; }
            .form-label { font-size: .84rem; margin-bottom: .35rem; }
            .form-control { font-size: .9rem; min-height: 40px; padding: .45rem .65rem; }
            .btn-red { font-size: .92rem; padding: .58rem; }
            .auth-foot { font-size: .85rem; margin-top: .95rem !important; }
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card auth-card p-4">
                    <h2 class="text-center mb-3"><img src="OJTTracking.png" alt="OJT Tracking" class="brand-logo"></h2>
                    <h4 class="text-center mb-4 auth-title">Create Account</h4>
                    
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
                    <p class="text-center mt-4 auth-foot">
                        Already have an account? <a href="login.php">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
