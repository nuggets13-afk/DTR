<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

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
        $pdo = db();
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
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - OJT Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(1000px 600px at 15% -10%, #2a0a0a 0%, #0b0b0b 45%, #050505 100%);
            color: #f5f5f1;
            font-family: "Netflix Sans", "Helvetica Neue", "Segoe UI", Arial, sans-serif;
        }
        .auth-card {
            border: 1px solid rgba(229,9,20,.28);
            border-radius: 14px;
            background: linear-gradient(180deg, #171717 0%, #101010 100%);
            box-shadow: 0 14px 36px rgba(0,0,0,.38);
        }
        .brand {
            margin: 0;
            line-height: 1;
            text-align: center;
        }
        .brand-logo {
            display: inline-block;
            height: 44px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }
        .sub {
            color: #b6b6b6;
            font-size: .84rem;
            text-align: center;
        }
        .form-label {
            font-size: .84rem;
            color: #d4d4d4;
        }
        .form-control {
            background: #111;
            border: 1px solid #343434;
            color: #f5f5f1;
            font-size: .9rem;
        }
        .form-control:focus {
            background: #111;
            color: #f5f5f1;
            border-color: #e10600;
            box-shadow: 0 0 0 .18rem rgba(225,6,0,.2);
        }
        .btn-login {
            background: #e10600;
            border-color: #e10600;
            font-weight: 700;
            border-radius: 10px;
            padding: .55rem;
            font-size: .92rem;
        }
        .btn-login:hover {
            background: #b80400;
            border-color: #b80400;
        }
        a { color: #fca5a5; }
        a:hover { color: #fecaca; }
        .auth-foot {
            color: #e5e7eb;
            font-size: .88rem;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center align-items-center" style="min-height: 90vh;">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card auth-card">
                <div class="card-body p-4 p-md-5">
                    <h2 class="brand mb-1"><img src="OJTTracking.png" alt="OJT Tracking" class="brand-logo"></h2>
                    <p class="sub mb-4">Welcome back. Please sign in to continue.</p>

                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success">Registration successful. Please login.</div>
                    <?php endif; ?>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-login text-white w-100" type="submit">Login</button>
                    </form>

                    <p class="mt-4 mb-0 text-center auth-foot">
                        No account yet? <a href="register.php" class="fw-semibold">Create one</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
