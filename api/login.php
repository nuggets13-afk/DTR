<?php
declare(strict_types=1);

// 1. Include config which now handles the session/cookie restoration logic
require_once __DIR__ . '/config.php';

// 2. If already recognized by session or our persistent cookie, go to dashboard
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
            $stmt = $pdo->prepare("SELECT id, name, password FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid email or password.';
            } else {
                // 3. Set Standard Session Variables
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];

                // 4. VERCEL PERSISTENCE FIX: Set an explicit cookie.
                $cookieExpiry = time() + (86400 * 7); // 7 days
                setcookie('app_user_id', (string)$user['id'], [
                    'expires' => $cookieExpiry,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                
                setcookie('app_user_name', $user['name'], [
                    'expires' => $cookieExpiry,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                // Force write and redirect
                session_write_close();
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "Database connection failed.";
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
            font-family: "Helvetica Neue", Arial, sans-serif;
        }
        .auth-card {
            border: 1px solid rgba(229,9,20,.28);
            border-radius: 14px;
            background: linear-gradient(180deg, #171717 0%, #101010 100%);
            box-shadow: 0 14px 36px rgba(0,0,0,.38);
        }
        /* Fixes the dark label visibility */
        .form-label { 
            color: #f5f5f1 !important; 
            font-weight: 500;
        }
        .btn-login { background: #e10600; border: none; font-weight: 700; border-radius: 10px; padding: .55rem; color: #fff; }
        .btn-login:hover { background: #b80400; }
        .form-control { background: #111; border: 1px solid #343434; color: #fff; }
        .form-control:focus { background: #111; color: #fff; border-color: #e10600; box-shadow: 0 0 0 .18rem rgba(225,6,0,.2); }
        .brand-logo { height: 60px; width: auto; filter: drop-shadow(0 0 8px rgba(225,6,0,0.4)); }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img src="OJTTracking.png" alt="OJT Tracking Logo" class="brand-logo mb-2">
                        <p class="text-secondary small">Please sign in to continue.</p>
                    </div>

                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success small">Registration successful. Please login.</div>
                    <?php endif; ?>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger small">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-login w-100" type="submit">Sign In</button>
                    </form>

                    <p class="mt-4 mb-0 text-center small text-secondary">
                        New here? <a href="register.php" class="text-danger text-decoration-none fw-bold">Create an account</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
