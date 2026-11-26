<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$id = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
$target = row('SELECT id, full_name, email, contact_number, purok, role, is_active,
                      privacy_consent_at, privacy_notice_version
               FROM users WHERE id = ?', [$id]);
if (!$target) {
    flash('error', 'User not found.');
    redirect('admin/users.php');
}

$data = [
    'full_name' => $target['full_name'],
    'email' => $target['email'],
    'contact_number' => $target['contact_number'],
    'purok' => $target['purok'],
    'role' => $target['role'],
];
$errors = [];
$password = '';

if (is_post()) {
    csrf_check();
    foreach (array_keys($data) as $field) {
        $data[$field] = trim((string)($_POST[$field] ?? ''));
    }
    $password = (string)($_POST['new_password'] ?? '');
    $consentProvided = !empty($_POST['privacy_consent']);

    if ($data['full_name'] === '' || strlen($data['full_name']) > 100) {
        $errors[] = 'Enter a name of 100 characters or fewer.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 120) {
        $errors[] = 'Enter a valid email address of 120 characters or fewer.';
    }
    if (!preg_match('/^09\d{9}$/', $data['contact_number'])) {
        $errors[] = 'Contact number must look like 09XXXXXXXXX.';
    }
    if (!in_array($data['role'], ['resident', 'official', 'admin'], true)) {
        $errors[] = 'Choose a valid role.';
    }
    if ($data['role'] === 'resident' && !in_array($data['purok'], PUROKS, true)) {
        $errors[] = 'Choose a valid purok for the resident.';
    }
    if ($data['role'] === 'resident' && !$target['privacy_consent_at'] && !$consentProvided) {
        $errors[] = 'Confirm that the resident has agreed to the privacy notice.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'A new password must be at least 8 characters.';
    }
    if ($id === (int)$u['id'] && $data['role'] !== $target['role']) {
        $errors[] = 'You cannot change your own role.';
    }
    if (!$errors && scalar('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$data['email'], $id])) {
        $errors[] = 'That email address is already used.';
    }

    if (!$errors) {
        $purok = $data['role'] === 'resident' ? $data['purok'] : 'Barangay Hall';
        $consentAt = $target['privacy_consent_at'];
        $noticeVersion = $target['privacy_notice_version'];
        if ($data['role'] === 'resident' && !$consentAt) {
            $consentAt = date('Y-m-d H:i:s');
            $noticeVersion = PRIVACY_NOTICE_VERSION;
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();
            if ($target['role'] === 'admin' && $target['is_active'] && $data['role'] !== 'admin') {
                $activeAdmins = rows("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE");
                if (count($activeAdmins) <= 1) {
                    $pdo->rollBack();
                    $errors[] = 'The last active Admin account cannot be changed to another role.';
                }
            }
            if (!$errors) {
                if ($password === '') {
                    run('UPDATE users SET full_name = ?, email = ?, contact_number = ?, purok = ?, role = ?,
                                         privacy_consent_at = ?, privacy_notice_version = ? WHERE id = ?',
                        [$data['full_name'], $data['email'], $data['contact_number'], $purok, $data['role'],
                         $consentAt, $noticeVersion, $id]);
                } else {
                    run('UPDATE users SET full_name = ?, email = ?, contact_number = ?, purok = ?, role = ?,
                                         privacy_consent_at = ?, privacy_notice_version = ?, password_hash = ? WHERE id = ?',
                        [$data['full_name'], $data['email'], $data['contact_number'], $purok, $data['role'],
                         $consentAt, $noticeVersion, password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                log_activity('Updated account: ' . $target['email']);
                $pdo->commit();
                flash('success', 'Account updated. Existing complaint history has been preserved.');
                redirect('admin/users.php');
            }
        } catch (PDOException $ex) {
            error_log('Account update failed: ' . $ex->getMessage());
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not update this account. Please try again.';
        }
    }
}

$pageTitle = 'Edit User';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Edit account</h1>
    <a class="btn btn-light" href="<?= e(url('admin/users.php')) ?>">Back to users</a>
</div>
<div class="card">
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <p class="muted">Editing <?= e($target['email']) ?>. Archiving disables sign-in but keeps all linked records.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$id ?>">
        <div class="grid-2">
            <div><label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="100" value="<?= e($data['full_name']) ?>" required></div>
            <div><label for="email">Email</label><input id="email" type="email" name="email" maxlength="120" value="<?= e($data['email']) ?>" required></div>
            <div><label for="contact_number">Contact number</label><input id="contact_number" name="contact_number" maxlength="11" value="<?= e($data['contact_number']) ?>" required></div>
            <div><label for="role">Role</label>
                <select id="role" name="role">
                    <?php foreach (['resident' => 'Resident', 'official' => 'Official', 'admin' => 'Admin'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $data['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label for="purok">Purok (required for residents)</label>
                <select id="purok" name="purok"><option value="">Select a purok</option>
                    <?php foreach (PUROKS as $purok): ?><option value="<?= e($purok) ?>" <?= $data['purok'] === $purok ? 'selected' : '' ?>><?= e($purok) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label for="new_password">New password (optional; at least 8 characters)</label><input id="new_password" type="password" name="new_password" autocomplete="new-password"></div>
        </div>
        <?php if (!$target['privacy_consent_at']): ?>
            <label class="consent-check"><input type="checkbox" name="privacy_consent" value="1">
                <span>For resident accounts only: the resident has agreed to the Data Privacy notice.</span></label>
        <?php else: ?>
            <p class="muted">Privacy notice consent recorded <?= e(fmt_date($target['privacy_consent_at'])) ?> (<?= e($target['privacy_notice_version']) ?>).</p>
        <?php endif; ?>
        <div class="actions"><button class="btn" type="submit">Save changes</button>
            <a class="btn btn-light" href="<?= e(url('admin/users.php')) ?>">Cancel</a></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
