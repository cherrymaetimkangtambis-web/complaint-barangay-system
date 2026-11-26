<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$errors = [];
$new = ['full_name' => '', 'email' => '', 'contact_number' => '', 'purok' => '', 'role' => 'official'];

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        foreach (['full_name', 'email', 'contact_number', 'purok'] as $k) {
            $new[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $new['role'] = (string)($_POST['role'] ?? 'official');
        $pw = (string)($_POST['password'] ?? '');

        if ($new['full_name'] === '' || strlen($new['full_name']) > 100) { $errors[] = 'Enter a name of 100 characters or fewer.'; }
        if (!filter_var($new['email'], FILTER_VALIDATE_EMAIL) || strlen($new['email']) > 120) { $errors[] = 'Enter a valid email of 120 characters or fewer.'; }
        if (!preg_match('/^09\d{9}$/', $new['contact_number'])) { $errors[] = 'Contact number must look like 09XXXXXXXXX.'; }
        if (!in_array($new['role'], ['resident', 'official', 'admin'], true)) { $errors[] = 'Invalid role.'; }
        if ($new['role'] === 'resident' && !in_array($new['purok'], PUROKS, true)) { $errors[] = 'Choose a valid purok for the resident.'; }
        if ($new['role'] === 'resident' && empty($_POST['privacy_consent'])) { $errors[] = 'Confirm that the resident has agreed to the privacy notice.'; }
        if (strlen($pw) < 8)                                   { $errors[] = 'Password must be at least 8 characters.'; }
        if (!$errors && scalar('SELECT COUNT(*) FROM users WHERE email = ?', [$new['email']])) {
            $errors[] = 'That email is already used.';
        }
        if (!$errors) {
            $purok = $new['role'] === 'resident' ? $new['purok'] : 'Barangay Hall';
            $consentedAt = $new['role'] === 'resident' ? date('Y-m-d H:i:s') : null;
            try {
                run('INSERT INTO users
                        (full_name, email, contact_number, purok, password_hash, role, privacy_consent_at, privacy_notice_version)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$new['full_name'], $new['email'], $new['contact_number'], $purok,
                     password_hash($pw, PASSWORD_DEFAULT), $new['role'], $consentedAt,
                     $consentedAt ? PRIVACY_NOTICE_VERSION : null]);
                log_activity('Created ' . $new['role'] . ' account: ' . $new['email']);
                flash('success', ucfirst($new['role']) . ' account created.');
                redirect('admin/users.php');
            } catch (PDOException $ex) {
                error_log('Account creation failed: ' . $ex->getMessage());
                $errors[] = 'Could not create this account. Check that the email is not already used and try again.';
            }
        }
    } else {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $target   = row('SELECT id, full_name, email, role, is_active FROM users WHERE id = ?', [$targetId]);

        if (!$target) {
            flash('error', 'User not found.');
        } elseif ($action === 'toggle') {
            if ($targetId === (int)$u['id']) {
                flash('error', 'You cannot deactivate your own account.');
            } else {
                $pdo = db();
                try {
                    $pdo->beginTransaction();
                    if ($target['role'] === 'admin' && $target['is_active']) {
                        $activeAdmins = rows("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE");
                        if (count($activeAdmins) <= 1) {
                            $pdo->rollBack();
                            flash('error', 'The last active Admin account cannot be archived.');
                            redirect('admin/users.php');
                        }
                    }
                    $newState = $target['is_active'] ? 0 : 1;
                    run('UPDATE users SET is_active = ? WHERE id = ?', [$newState, $targetId]);
                    log_activity(($newState ? 'Restored' : 'Archived') . ' account: ' . $target['email']);
                    $pdo->commit();
                    flash('success', 'Account ' . ($newState ? 'restored' : 'archived') . '.');
                } catch (PDOException $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Account archive action failed: ' . $ex->getMessage());
                    flash('error', 'Could not update this account. Please try again.');
                }
            }
        } elseif ($action === 'reset') {
            $pw = (string)($_POST['new_password'] ?? '');
            if (strlen($pw) < 8) {
                flash('error', 'New password must be at least 8 characters.');
            } else {
                run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $targetId]);
                log_activity('Reset password of: ' . $target['email']);
                flash('success', 'Password reset for ' . $target['full_name'] . '.');
            }
        }
        if (!$errors) {
            redirect('admin/users.php' . (!empty($_GET['q']) ? '?q=' . urlencode((string)$_GET['q']) : ''));
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$roleFilter = (string)($_GET['role'] ?? '');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ?)';
    array_push($params, "%$q%", "%$q%");
}
if (in_array($roleFilter, ['resident', 'official', 'admin'], true)) {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = (int)scalar("SELECT COUNT(*) FROM users $whereSql", $params);
$pg    = paginate($total, PER_PAGE, (int)($_GET['page'] ?? 1));
$list  = rows("SELECT id, full_name, email, contact_number, purok, role, is_active, created_at FROM users $whereSql
               ORDER BY FIELD(role,'admin','official','resident'), full_name
               LIMIT " . (int)$pg['perPage'] . ' OFFSET ' . (int)$pg['offset'], $params);

$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Manage Users</h1></div>

<div class="card">
    <h2>Add account</h2>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="grid-2">
            <div><label>Full name</label><input type="text" name="full_name" value="<?= e($new['full_name']) ?>" required></div>
            <div><label>Email</label><input type="email" name="email" value="<?= e($new['email']) ?>" required></div>
            <div><label>Contact number</label><input type="text" name="contact_number" maxlength="11" value="<?= e($new['contact_number']) ?>" required></div>
            <div><label>Role</label>
                <select name="role">
                    <option value="resident" <?= $new['role'] === 'resident' ? 'selected' : '' ?>>Resident</option>
                    <option value="official" <?= $new['role'] === 'official' ? 'selected' : '' ?>>Official (assigned complaints)</option>
                    <option value="admin" <?= $new['role'] === 'admin' ? 'selected' : '' ?>>Admin (full access)</option>
                </select></div>
            <div><label for="purok">Purok (required for residents)</label>
                <select id="purok" name="purok"><option value="">Select a purok</option>
                    <?php foreach (PUROKS as $purok): ?><option value="<?= e($purok) ?>" <?= $new['purok'] === $purok ? 'selected' : '' ?>><?= e($purok) ?></option><?php endforeach; ?>
                </select></div>
            <div><label>Temporary password (min 8 characters)</label><input type="password" name="password" required></div>
        </div>
        <label class="consent-check"><input type="checkbox" name="privacy_consent" value="1">
            <span>For resident accounts only: the resident has agreed to the Data Privacy notice.</span></label>
        <div class="actions"><button class="btn" type="submit">Create account</button></div>
    </form>
</div>

<div class="card">
    <form method="get" class="filters">
        <div><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name or email"></div>
        <div><label for="role">Role</label>
            <select id="role" name="role"><option value="">All</option>
                <?php foreach (['resident', 'official', 'admin'] as $r): ?>
                    <option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><button class="btn" type="submit">Filter</button></div>
    </form>
</div>

<div class="card">
    <p class="muted"><?= $total ?> user(s).</p>
    <div class="table-wrap"><table>
        <tr><th>Name</th><th>Email</th><th>Contact</th><th>Purok</th><th>Role</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($list as $r): $self = (int)$r['id'] === (int)$u['id']; ?>
            <tr>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e($r['contact_number']) ?></td>
                <td><?= e($r['purok']) ?></td>
                <td><?= e(ucfirst($r['role'])) ?><?= $self ? ' (you)' : '' ?></td>
                <td><?= $r['is_active'] ? '<span class="badge b-resolved">Active</span>' : '<span class="badge b-cancelled">Archived</span>' ?></td>
                <td>
                    <a class="btn btn-sm btn-outline" href="<?= e(url('admin/edit_user.php?id=' . (int)$r['id'])) ?>">Edit</a>
                    <?php if (!$self): ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-light" type="submit"><?= $r['is_active'] ? 'Archive' : 'Restore' ?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pager($pg) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
