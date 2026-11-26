<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['resident']);

$errors = [];
$profile = [
    'full_name' => $u['full_name'],
    'contact_number' => $u['contact_number'],
    'purok' => $u['purok'],
];

if (is_post()) {
    csrf_check();
    foreach (array_keys($profile) as $key) {
        $profile[$key] = trim((string)($_POST[$key] ?? ''));
    }

    if ($profile['full_name'] === '' || strlen($profile['full_name']) > 100) {
        $errors[] = 'Enter your name (up to 100 characters).';
    }
    if (!preg_match('/^09\d{9}$/', $profile['contact_number'])) {
        $errors[] = 'Contact number must be 11 digits and start with 09.';
    }
    if (!in_array($profile['purok'], PUROKS, true)) {
        $errors[] = 'Choose a valid purok.';
    }

    if (!$errors) {
        try {
            run('UPDATE users SET full_name = ?, contact_number = ?, purok = ? WHERE id = ? AND role = "resident"',
                [$profile['full_name'], $profile['contact_number'], $profile['purok'], $u['id']]);
            log_activity('Updated resident profile');
            flash('success', 'Your profile was updated.');
            redirect('resident/profile.php');
        } catch (PDOException $ex) {
            error_log('Resident profile update failed: ' . $ex->getMessage());
            $errors[] = 'Could not save your profile. Please try again.';
        }
    }
}

$pageTitle = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="card narrow">
    <h1>My Profile</h1>
    <p class="muted">Update the name, contact number, or purok linked to your resident account. Your login email cannot be changed here.</p>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" maxlength="100" value="<?= e($profile['full_name']) ?>" required>

        <label for="contact_number">Contact number</label>
        <input type="text" id="contact_number" name="contact_number" inputmode="numeric" maxlength="11"
               value="<?= e($profile['contact_number']) ?>" required>

        <label for="purok">Purok</label>
        <select id="purok" name="purok" required>
            <?php foreach (PUROKS as $purok): ?>
                <option value="<?= e($purok) ?>" <?= $profile['purok'] === $purok ? 'selected' : '' ?>><?= e($purok) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="actions">
            <button type="submit" class="btn">Save Profile</button>
            <a class="btn btn-light" href="<?= e(url('resident/dashboard.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
