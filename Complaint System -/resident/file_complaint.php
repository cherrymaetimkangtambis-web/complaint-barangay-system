<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['resident']);

$errors = [];
$data = ['category' => '', 'subject' => '', 'description' => '', 'location' => $u['purok'], 'landmark' => '', 'photo' => ''];

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

    $photo = null;
    if (!$errors) {
        $photo = save_photo($_FILES['photo'] ?? [], $photoError);
        if ($photoError) {
            $errors[] = $photoError;
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            run('INSERT INTO complaints (user_id, category, subject, description, location, landmark, photo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$u['id'], $data['category'], $data['subject'], $data['description'], $data['location'],
                 $data['landmark'] !== '' ? $data['landmark'] : null, $photo]);
            $id  = (int)$pdo->lastInsertId();
            $ref = make_reference($id);
            run('UPDATE complaints SET reference_no = ? WHERE id = ?', [$ref, $id]);
            run('INSERT INTO complaint_history (complaint_id, status, remarks, changed_by) VALUES (?, "Pending", ?, ?)',
                [$id, 'Complaint submitted by resident.', $u['id']]);
            notify_admins($id, 'New complaint ' . $ref . ' from ' . $u['full_name'] . ': ' . $data['subject']);
            log_activity('Filed complaint ' . $ref);
            $pdo->commit();
            flash('success', 'Complaint submitted! Your reference number is ' . $ref . '.');
            redirect('resident/view_complaint.php?id=' . $id);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            delete_photo($photo);
            $errors[] = 'Something went wrong while saving your complaint. Please try again.';
        }
    }
}

$pageTitle = 'File a Complaint';
require __DIR__ . '/../includes/header.php';
?>
<div class="card medium">
    <h1>File a Complaint</h1>
    <?php
    $submitLabel = 'Submit Complaint';
    $cancelUrl   = 'resident/dashboard.php';
    require __DIR__ . '/../includes/complaint_form.php';
    ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
