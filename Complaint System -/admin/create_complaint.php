<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$residents = rows("SELECT id, full_name, contact_number, purok FROM users
                   WHERE role = 'resident' AND is_active = 1 ORDER BY full_name");
$staff = rows("SELECT id, full_name, role FROM users
               WHERE role IN ('admin','official') AND is_active = 1 ORDER BY full_name");
$data = [
    'user_id' => '',
    'category' => '',
    'subject' => '',
    'description' => '',
    'location' => '',
    'landmark' => '',
    'priority' => 'Medium',
    'assigned_to' => '',
];
$errors = [];

if (is_post()) {
    csrf_check();
    foreach (['user_id', 'category', 'subject', 'description', 'location', 'landmark', 'priority', 'assigned_to'] as $field) {
        $data[$field] = trim((string)($_POST[$field] ?? ''));
    }

    $residentId = (int)$data['user_id'];
    $assignedId = (int)$data['assigned_to'];
    if ($data['assigned_to'] !== '' && !ctype_digit($data['assigned_to'])) {
        $errors[] = 'Choose a valid official or Admin assignment.';
    }
    if (!$residentId || !scalar("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'resident' AND is_active = 1", [$residentId])) {
        $errors[] = 'Choose an active resident account.';
    }
    if (!in_array($data['category'], CATEGORIES, true)) {
        $errors[] = 'Choose a valid complaint category.';
    }
    if ($data['subject'] === '' || strlen($data['subject']) > 150) {
        $errors[] = 'Enter a subject of 150 characters or fewer.';
    }
    if (strlen($data['description']) < 10 || strlen($data['description']) > 10000) {
        $errors[] = 'Description must be between 10 and 10,000 characters.';
    }
    if (!in_array($data['location'], PUROKS, true)) {
        $errors[] = 'Choose a valid purok.';
    }
    if (strlen($data['landmark']) > 150) {
        $errors[] = 'Landmark must be 150 characters or fewer.';
    }
    if (!in_array($data['priority'], PRIORITIES, true)) {
        $errors[] = 'Choose a valid priority.';
    }
    if ($assignedId && !scalar("SELECT COUNT(*) FROM users WHERE id = ? AND role IN ('admin','official') AND is_active = 1", [$assignedId])) {
        $errors[] = 'Choose an active official or Admin, or leave the complaint unassigned.';
    }

    if (!$errors) {
        $resident = row('SELECT full_name FROM users WHERE id = ?', [$residentId]);
        $pdo = db();
        try {
            $pdo->beginTransaction();
            run('INSERT INTO complaints
                    (user_id, category, subject, description, location, landmark, priority, assigned_to)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$residentId, $data['category'], $data['subject'], $data['description'], $data['location'],
                 $data['landmark'] !== '' ? $data['landmark'] : null, $data['priority'], $assignedId ?: null]);
            $id = (int)$pdo->lastInsertId();
            $reference = make_reference($id);
            run('UPDATE complaints SET reference_no = ? WHERE id = ?', [$reference, $id]);
            run('INSERT INTO complaint_history (complaint_id, status, remarks, changed_by)
                 VALUES (?, "Pending", ?, ?)',
                [$id, 'Complaint recorded by Admin on behalf of ' . $resident['full_name'] . '.', $u['id']]);
            if ($assignedId) {
                notify($assignedId, $id, 'Complaint ' . $reference . ' was assigned to you.');
            } else {
                notify_admins($id, 'New complaint ' . $reference . ' recorded for ' . $resident['full_name'] . '.');
            }
            log_activity('Recorded complaint ' . $reference . ' for resident #' . $residentId);
            $pdo->commit();
            flash('success', 'Complaint recorded. Reference number: ' . $reference . '.');
            redirect('admin/view_complaint.php?id=' . $id);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin complaint creation failed: ' . $ex->getMessage());
            $errors[] = 'Could not record this complaint. Please try again.';
        }
    }
}

$pageTitle = 'Record a Complaint';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Record a Complaint</h1><a class="btn btn-light" href="<?= e(url('admin/complaints.php')) ?>">Back to complaints</a></div>
<div class="card medium">
    <p class="muted">Use this form to record a concern received in person or by phone. Select the resident so updates remain linked to the correct account.</p>
    <?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (!$residents): ?>
        <p class="alert alert-error">There are no active resident accounts. Create or restore a resident account before recording a complaint.</p>
    <?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="user_id">Resident</label>
        <select id="user_id" name="user_id" required><option value="">Select resident</option>
            <?php foreach ($residents as $resident): ?>
                <option value="<?= (int)$resident['id'] ?>" <?= (int)$data['user_id'] === (int)$resident['id'] ? 'selected' : '' ?>>
                    <?= e($resident['full_name']) ?> · <?= e($resident['purok']) ?> · <?= e($resident['contact_number']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="grid-2">
            <div><label for="category">Category</label><select id="category" name="category" required>
                <option value="">Select category</option><?php foreach (CATEGORIES as $category): ?><option value="<?= e($category) ?>" <?= $data['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?>
            </select></div>
            <div><label for="location">Purok where it happened</label><select id="location" name="location" required>
                <option value="">Select purok</option><?php foreach (PUROKS as $purok): ?><option value="<?= e($purok) ?>" <?= $data['location'] === $purok ? 'selected' : '' ?>><?= e($purok) ?></option><?php endforeach; ?>
            </select></div>
            <div><label for="subject">Subject</label><input id="subject" name="subject" maxlength="150" value="<?= e($data['subject']) ?>" required></div>
            <div><label for="landmark">Landmark (optional)</label><input id="landmark" name="landmark" maxlength="150" value="<?= e($data['landmark']) ?>"></div>
            <div><label for="priority">Priority</label><select id="priority" name="priority"><?php foreach (PRIORITIES as $priority): ?><option value="<?= e($priority) ?>" <?= $data['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option><?php endforeach; ?></select></div>
            <div><label for="assigned_to">Assign to (optional)</label><select id="assigned_to" name="assigned_to"><option value="">Leave unassigned</option>
                <?php foreach ($staff as $member): ?><option value="<?= (int)$member['id'] ?>" <?= (int)$data['assigned_to'] === (int)$member['id'] ? 'selected' : '' ?>><?= e($member['full_name']) ?> (<?= e(ucfirst($member['role'])) ?>)</option><?php endforeach; ?>
            </select></div>
        </div>
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="7" maxlength="10000" required><?= e($data['description']) ?></textarea>
        <div class="actions"><button type="submit" class="btn">Record Complaint</button><a class="btn btn-light" href="<?= e(url('admin/complaints.php')) ?>">Cancel</a></div>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
