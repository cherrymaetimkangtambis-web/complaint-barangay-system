<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login();

$errors = [];
if (is_post()) {
    csrf_check();
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $hash = scalar('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify($current, (string)$hash)) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }
    if (!$errors) {
        run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        log_activity('Changed own password');
        flash('success', 'Your password was changed.');
        redirect(home_for_role($u['role']));
    }
}

$pageTitle = 'Change Password';
require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow">
    <h1>Change Password</h1>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="new_password">New password (at least 8 characters)</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <div class="actions">
            <button type="submit" class="btn">Save Password</button>
            <a class="btn btn-light" href="<?= e(url(home_for_role($u['role']))) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
