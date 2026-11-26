<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['resident']);

$id = (int)($_GET['id'] ?? 0);
$c  = row('SELECT * FROM complaints WHERE id = ? AND user_id = ?', [$id, $u['id']]);
if (!$c) {
    flash('error', 'Complaint not found.');
    redirect('resident/my_complaints.php');
}
if ($c['status'] !== 'Pending') {
    flash('error', 'Only Pending complaints can be edited.');
    redirect('resident/view_complaint.php?id=' . $id);
}

$errors = [];
$data = [
    'category'    => $c['category'],
    'subject'     => $c['subject'],
    'description' => $c['description'],
    'location'    => $c['location'],
    'landmark'    => (string)$c['landmark'],
    'photo'       => (string)$c['photo'],
];

if (is_post()) {
    csrf_check();
    foreach (['category', 'subject', 'description', 'location', 'landmark'] as $k) {
        $data[$k] = trim((string)($_POST[$k] ?? ''));
    }
    if (!in_array($data['category'], CATEGORIES, true)) {
        $errors[] = 'Please choose a category.';
    }
    if ($data['subject'] === '' || strlen($data['subject']) > 150) {
        $errors[] = 'Please enter a subject (up to 150 characters).';
    }
    if (strlen($data['description']) < 10) {
        $errors[] = 'Please describe the problem (at least 10 characters).';
    }
    if (!in_array($data['location'], PUROKS, true)) {
        $errors[] = 'Please choose the purok where it happened.';
    }
    if (strlen($data['landmark']) > 150) {
        $errors[] = 'Landmark is too long (max 150 characters).';
    }

    $newPhoto = null;
    if (!$errors) {
        $newPhoto = save_photo($_FILES['photo'] ?? [], $photoError);
        if ($photoError) {
            $errors[] = $photoError;
        }
    }

    if (!$errors) {
        // Re-check status inside the update so a complaint already picked up by staff cannot be changed
        $photoToSave = $newPhoto ?: $c['photo'];
        $st = db()->prepare('UPDATE complaints SET category = ?, subject = ?, description = ?, location = ?,
                             landmark = ?, photo = ? WHERE id = ? AND user_id = ? AND status = "Pending"');
        $st->execute([$data['category'], $data['subject'], $data['description'], $data['location'],
                      $data['landmark'] !== '' ? $data['landmark'] : null, $photoToSave, $id, $u['id']]);
        if ($st->rowCount() === 0 && $newPhoto) {
            delete_photo($newPhoto);
        }
        if ($newPhoto && $c['photo']) {
            delete_photo($c['photo']);
        }
        run('INSERT INTO complaint_history (complaint_id, status, remarks, changed_by) VALUES (?, "Pending", ?, ?)',
            [$id, 'Complaint details edited by resident.', $u['id']]);
        log_activity('Edited complaint ' . $c['reference_no']);
        flash('success', 'Your complaint was updated.');
        redirect('resident/view_complaint.php?id=' . $id);
    }
}

$pageTitle = 'Edit Complaint';
require __DIR__ . '/../includes/header.php';
?>
<div class="card medium">
    <h1>Edit Complaint <?= e($c['reference_no']) ?></h1>
    <?php
    $submitLabel = 'Save Changes';
    $cancelUrl   = 'resident/view_complaint.php?id=' . $id;
    require __DIR__ . '/../includes/complaint_form.php';
    ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
