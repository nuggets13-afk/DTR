<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
// ... (Your existing POST logic for registration)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | OJT Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white flex items-center justify-center min-h-screen">
    <div class="bg-gray-800 p-8 rounded-lg shadow-lg w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center">Create Account</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-500 text-white p-3 rounded mb-4">
                <?php foreach($errors as $err) echo "<div>$err</div>"; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm mb-1">Full Name</label>
                <input type="text" name="name" class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500" required>
            </div>
            <div>
                <label class="block text-sm mb-1">Email Address</label>
                <input type="email" name="email" class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500" required>
            </div>
            <div>
                <label class="block text-sm mb-1">Password</label>
                <input type="password" name="password" class="w-full p-2 rounded bg-gray-700 border border-gray-600 focus:outline-none focus:border-blue-500" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                Register
            </button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-400">
            Already have an account? <a href="login.php" class="text-blue-400 hover:underline">Login here</a>
        </p>
    </div>
</body>
</html>
