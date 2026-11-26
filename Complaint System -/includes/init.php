<?php
// Loaded at the top of every page.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Manila');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/functions.php';

// Session timeout: auto logout after SESSION_TIMEOUT seconds of inactivity
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        flash('error', 'Your session expired because of inactivity. Please login again.');
        redirect('auth/login.php');
    }
    $_SESSION['last_activity'] = time();
}
