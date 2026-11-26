<?php
require_once __DIR__ . '/../includes/init.php';

if ($cu = current_user()) {
    redirect(home_for_role($cu['role']));
}

$error = '';
$email = '';
$contact = '';

if (is_post()) {
    csrf_check();
    $email    = trim((string)($_POST['email'] ?? ''));
    $contact  = trim((string)($_POST['contact_number'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    // Limit guessing: 5 tries per 10 minutes
    $tries = $_SESSION['reset_tries'] ?? ['n' => 0, 'since' => time()];
    if (time() - $tries['since'] > 600) {
        $tries = ['n' => 0, 'since' => time()];
    }

    if ($tries['n'] >= 5) {
        $error = 'Too many attempts. Please try again after a few minutes, or ask the barangay office to reset your password.';
    } elseif (strlen($password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Only resident accounts can reset this way. Staff must ask the admin.
        $user = row('SELECT id FROM users WHERE email = ? AND contact_number = ? AND role = "resident" AND is_active = 1',
                    [$email, $contact]);
        if ($user) {
            run('UPDATE users SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            log_activity('Password reset (forgot password)', (int)$user['id']);
            unset($_SESSION['reset_tries']);
            flash('success', 'Password changed. You can now login with your new password.');
            redirect('auth/login.php');
        }
        $tries['n']++;
        $_SESSION['reset_tries'] = $tries;
        $error = 'We could not verify those details. Check your email and contact number.';
    }
    if ($error && $tries['n'] >= 5) {
        $_SESSION['reset_tries'] = $tries;
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow">
    <h1>Forgot Password</h1>
    <p class="muted">Enter the email and contact number you used when you registered, then choose a new password.
       (Barangay staff accounts: please ask the administrator to reset your password.)</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>

        <label for="contact_number">Contact number</label>
        <input type="text" id="contact_number" name="contact_number" maxlength="11" value="<?= e($contact) ?>" required>

        <label for="password">New password (at least 8 characters)</label>
        <input type="password" id="password" name="password" required>

        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <div class="actions">
            <button type="submit" class="btn">Reset Password</button>
            <a class="btn btn-light" href="<?= e(url('auth/login.php')) ?>">Back to login</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
