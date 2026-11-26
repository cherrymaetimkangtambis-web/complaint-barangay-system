<?php
require_once __DIR__ . '/../includes/init.php';

if ($cu = current_user()) {
    redirect(home_for_role($cu['role']));
}
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$errors = [];
$old = ['full_name' => '', 'email' => '', 'contact_number' => '', 'purok' => ''];
$consentGiven = false;

if (is_post()) {
    csrf_check();
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');
    $consentGiven = ($_POST['privacy_consent'] ?? '') === '1';

    if ($old['full_name'] === '' || strlen($old['full_name']) > 100) {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 120) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!preg_match('/^09\d{9}$/', $old['contact_number'])) {
        $errors[] = 'Contact number must be 11 digits and start with 09 (example: 09123456789).';
    }
    if (!in_array($old['purok'], PUROKS, true)) {
        $errors[] = 'Please choose your purok.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$consentGiven) {
        $errors[] = 'Please read and accept the privacy notice to create an account.';
    }
    if (!$errors && scalar('SELECT COUNT(*) FROM users WHERE email = ?', [$old['email']])) {
        $errors[] = 'That email is already registered. Please login instead.';
    }

    if (!$errors) {
        try {
            run('INSERT INTO users (full_name, email, contact_number, purok, password_hash, role,
                                   privacy_consent_at, privacy_notice_version)
                 VALUES (?, ?, ?, ?, ?, "resident", NOW(), ?)',
                [$old['full_name'], $old['email'], $old['contact_number'], $old['purok'],
                 password_hash($password, PASSWORD_DEFAULT), PRIVACY_NOTICE_VERSION]);
            $newId = (int)db()->lastInsertId();
            log_activity('New resident registered: ' . $old['email'], $newId);
            flash('success', 'Account created! You can now login.');
            redirect('auth/login.php');
        } catch (PDOException $ex) {
            $errors[] = 'That email is already registered. Please login instead.';
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow">
    <h1>Create a Resident Account</h1>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>

        <label for="contact_number">Contact number</label>
        <input type="text" id="contact_number" name="contact_number" inputmode="numeric" maxlength="11"
               placeholder="09XXXXXXXXX" value="<?= e($old['contact_number']) ?>" required>

        <label for="purok">Purok</label>
        <select id="purok" name="purok" required>
            <option value="">-- Choose your purok --</option>
            <?php foreach (PUROKS as $p): ?>
                <option value="<?= e($p) ?>" <?= $old['purok'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="password">Password (at least 8 characters)</label>
        <input type="password" id="password" name="password" required>

        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <label class="consent-check">
            <input type="checkbox" name="privacy_consent" value="1" <?= $consentGiven ? 'checked' : '' ?> required>
            <span>I have read the <a href="<?= e(url('privacy.php')) ?>" target="_blank" rel="noopener">Data Privacy Act (RA 10173) Privacy Notice</a>
            and consent to the barangay using my information to manage and respond to my complaints.</span>
        </label>

        <div class="actions"><button type="submit" class="btn">Register</button></div>
    </form>
    <p class="muted">Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Login here</a>.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
