<?php
require_once __DIR__ . '/../includes/init.php';

if ($cu = current_user()) {
    redirect(home_for_role($cu['role']));
}

$error = '';
$email = '';

if (is_post()) {
    csrf_check();
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Simple brute-force protection: 5 wrong tries = locked for 60 seconds
    if (($_SESSION['login_lock'] ?? 0) > time()) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } else {
        $user = row('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user && password_verify($password, $user['password_hash'])) {
            if (!(int)$user['is_active']) {
                $error = 'Your account is deactivated. Please contact the barangay office.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']       = (int)$user['id'];
                $_SESSION['last_activity'] = time();
                unset($_SESSION['login_attempts'], $_SESSION['login_lock']);
                log_activity('Logged in', (int)$user['id']);
                redirect(home_for_role($user['role']));
            }
        } else {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_lock']     = time() + 60;
                $_SESSION['login_attempts'] = 0;
            }
            $error = 'Incorrect email or password.';
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-shell">
<aside class="auth-visual" aria-hidden="true">
    <p class="eyebrow">BARANGAY SERVICE PORTAL</p>
    <h2>One place for clearer community action.</h2>
    <p>Sign in to file concerns, follow updates, or manage barangay operations.</p>
</aside>
<div class="card auth-card">
    <p class="eyebrow">WELCOME BACK</p>
    <h1>Sign in to your account</h1>
    <p class="muted">Residents, Officials, and Admins all sign in here. Your account role opens the correct workspace.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <div class="actions"><button type="submit" class="btn">Login</button></div>
    </form>
    <p class="muted">
        No account yet? <a href="<?= e(url('auth/register.php')) ?>">Register here</a>.<br>
        <a href="<?= e(url('auth/forgot_password.php')) ?>">Forgot password?</a>
    </p>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
