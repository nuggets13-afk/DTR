<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Logic for sending reset link would go here
        // For now, we provide a UI feedback message
        $message = "If an account exists for $email, you will receive a password reset link shortly.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Forgot Password - OJT Tracking</title>
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
        .form-label { 
            color: #f5f5f1 !important; 
            font-weight: 500;
        }
        .btn-primary-red { 
            background: #e10600; 
            border: none; 
            font-weight: 700; 
            border-radius: 10px; 
            padding: .75rem; 
            color: #fff; 
        }
        .btn-primary-red:hover { background: #b80400; color: #fff; }
        
        .form-control { background: #111; border: 1px solid #343434; color: #fff; }
        .form-control:focus { background: #111; color: #fff; border-color: #e10600; box-shadow: 0 0 0 .18rem rgba(225,6,0,.2); }
        
        .brand-logo { height: 60px; filter: drop-shadow(0 0 8px rgba(225,6,0,0.4)); }
        
        .auth-foot a { color: #fca5a5 !important; text-decoration: none; font-weight: bold; }
        .auth-foot a:hover { color: #fecaca !important; }

        @media (max-width: 576px) {
            body { background: #050505; }
            .auth-card { border: none; background: transparent; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-12 col-sm-10 col-md-7 col-lg-5">
            <div class="card auth-card">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <a href="login.php">
                            <img src="OJTTracking.png" alt="Logo" class="brand-logo mb-3">
                        </a>
                        <h2 class="h4 fw-bold">Forgot Password?</h2>
                        <p class="text-secondary small">Enter your email and we'll send you a link to reset your password.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger small"><?= $error ?></div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="alert alert-success small"><?= $message ?></div>
                    <?php else: ?>
                        <form method="post" novalidate>
                            <div class="mb-4">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control form-control-lg" placeholder="name@example.com" required>
                            </div>
                            <button class="btn btn-primary-red w-100 mb-3" type="submit">Email Me</button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-3 auth-foot">
                        <p class="small text-secondary">
                            Remember your password? <a href="login.php">Sign In</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
