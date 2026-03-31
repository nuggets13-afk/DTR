<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// 1. Clear the Session array
$_SESSION = [];

// 2. Delete the standard PHP Session Cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000, 
        $params['path'], 
        $params['domain'], 
        $params['secure'], 
        $params['httponly']
    );
}

// 3. Delete the CUSTOM Vercel Persistence Cookies
// We set the time to the past (time() - 3600) to force the browser to remove them
setcookie('app_user_id', '', time() - 3600, '/');
setcookie('app_user_name', '', time() - 3600, '/');

// 4. Destroy the session on the server
session_destroy();

// 5. Redirect to login
header('Location: login.php');
exit;
