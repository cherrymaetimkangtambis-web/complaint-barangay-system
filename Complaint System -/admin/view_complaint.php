<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin', 'official']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$id = (int)($_GET['id'] ?? 0);
$isAdmin = $u['role'] === 'admin';
if ($isAdmin) {
    $c = row('SELECT c.*, r.full_name AS resident_name, r.contact_number, r.email, r.purok AS resident_purok
              FROM complaints c JOIN users r ON r.id = c.user_id WHERE c.id = ?', [$id]);
} else {
    $c = row('SELECT c.*, r.full_name AS resident_name, r.contact_number, r.email, r.purok AS resident_purok
              FROM complaints c JOIN users r ON r.id = c.user_id
              WHERE c.id = ? AND c.assigned_to = ? AND c.archived_at IS NULL', [$id, $u['id']]);
}
if (!$c) {
    flash('error', 'Complaint not found or not assigned to your account.');
    redirect('admin/complaints.php');
}
$lockAccessibleComplaint = function () use ($id, $u, $isAdmin) {
    $current = row('SELECT user_id, assigned_to, reference_no, archived_at FROM complaints WHERE id = ? FOR UPDATE', [$id]);
    if (!$current || $current['archived_at'] || (!$isAdmin && (int)$current['assigned_to'] !== (int)$u['id'])) {
        return null;
    }
    return $current;
};

$staff = $isAdmin
    ? rows("SELECT id, full_name, role FROM users WHERE role IN ('admin','official') AND is_active = 1 ORDER BY full_name")
    : [];
$staffIds = array_map('intval', array_column($staff, 'id'));
$errors = [];
$detailsErrors = [];
$details = [
    'category' => $c['category'],
    'subject' => $c['subject'],
    'description' => $c['description'],
    'location' => $c['location'],
    'landmark' => $c['landmark'] ?? '',
];
$messageError = '';
$messageText = '';
$mediationErrors = [];
$mediationPosted = false;
$mediationData = [
    'respondent_name' => '',
    'respondent_contact' => '',
    'respondent_address' => '',
    'hearing_at' => '',
    'hearing_location' => '',
    'summons_issued_at' => '',
    'summons_served_at' => '',
    'case_status' => 'Pending',
    'outcome' => '',
];
$mediation = row('SELECT * FROM mediation_cases WHERE complaint_id = ?', [$id]);
if ($mediation) {
    foreach (['respondent_name', 'respondent_contact', 'respondent_address', 'hearing_location', 'case_status', 'outcome'] as $field) {
        $mediationData[$field] = $mediation[$field] ?? '';
    }
    $mediationData['hearing_at'] = $mediation['hearing_at'] ? date('Y-m-d\TH:i', strtotime($mediation['hearing_at'])) : '';
    $mediationData['summons_issued_at'] = $mediation['summons_issued_at'] ? date('Y-m-d', strtotime($mediation['summons_issued_at'])) : '';
    $mediationData['summons_served_at'] = $mediation['summons_served_at'] ? date('Y-m-d', strtotime($mediation['summons_served_at'])) : '';
}

if (is_post()) {
    csrf_check();
    $postString = function (string $key): string {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    };
    $action = $postString('action');

    if (in_array($action, ['archive', 'restore'], true)) {
        if (!$isAdmin) {
            flash('error', 'Only an Admin can archive or restore complaints.');
            redirect('admin/complaints.php');
        }
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $locked = row('SELECT archived_at FROM complaints WHERE id = ? FOR UPDATE', [$id]);
            if (!$locked || ($action === 'archive' && $locked['archived_at']) ||
                ($action === 'restore' && !$locked['archived_at'])) {
                $pdo->rollBack();
                flash('error', 'The complaint has already changed. Refresh and try again.');
            } elseif ($action === 'archive') {
                run('UPDATE complaints SET archived_at = NOW(), archived_by = ? WHERE id = ?', [$u['id'], $id]);
                notify((int)$c['user_id'], $id, 'Your complaint ' . $c['reference_no'] . ' was archived by the barangay.');
                log_activity('Archived complaint ' . $c['reference_no']);
                $pdo->commit();
                flash('success', 'Complaint archived. Its messages, history, and mediation record were retained.');
            } else {
                run('UPDATE complaints SET archived_at = NULL, archived_by = NULL WHERE id = ?', [$id]);
                notify((int)$c['user_id'], $id, 'Your complaint ' . $c['reference_no'] . ' was restored.');
                log_activity('Restored complaint ' . $c['reference_no']);
                $pdo->commit();
                flash('success', 'Complaint restored.');
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Complaint archive action failed: ' . $ex->getMessage());
            flash('error', 'Could not update the complaint. Please try again.');
        }
        redirect('admin/view_complaint.php?id=' . $id);
    } elseif ($action === 'edit_details') {
        foreach (array_keys($details) as $field) {
            $details[$field] = $postString($field);
        }
        if (!$isAdmin) {
            $detailsErrors[] = 'Only an Admin can edit complaint details.';
        }
        if ($c['archived_at']) {
            $detailsErrors[] = 'Restore this complaint before editing it.';
        }
        if (!in_array($details['category'], CATEGORIES, true)) {
            $detailsErrors[] = 'Choose a valid category.';
        }
        if ($details['subject'] === '' || strlen($details['subject']) > 150) {
            $detailsErrors[] = 'Enter a subject of 150 characters or fewer.';
        }
        if (strlen($details['description']) < 10 || strlen($details['description']) > 10000) {
            $detailsErrors[] = 'Description must be between 10 and 10,000 characters.';
        }
        if (!in_array($details['location'], PUROKS, true)) {
            $detailsErrors[] = 'Choose a valid purok.';
        }
        if (strlen($details['landmark']) > 150) {
            $detailsErrors[] = 'Landmark must be 150 characters or fewer.';
        }
        if (!$detailsErrors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $current = $lockAccessibleComplaint();
                if (!$current) {
                    $pdo->rollBack();
                    flash('error', 'The complaint is archived or no longer available for editing.');
                    redirect('admin/complaints.php');
                }
                run('UPDATE complaints SET category = ?, subject = ?, description = ?, location = ?, landmark = ?
                     WHERE id = ? AND archived_at IS NULL',
                    [$details['category'], $details['subject'], $details['description'], $details['location'],
                     $details['landmark'] !== '' ? $details['landmark'] : null, $id]);
                log_activity('Edited complaint details for ' . $current['reference_no']);
                $pdo->commit();
                flash('success', 'Complaint details updated.');
                redirect('admin/view_complaint.php?id=' . $id);
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Complaint details update failed: ' . $ex->getMessage());
                $detailsErrors[] = 'Could not save complaint details. Please try again.';
            }
        }
    } elseif ($action === 'send_message') {
        $messageText = $postString('message');
        if ($messageText === '' || strlen($messageText) > 2000) {
            $messageError = 'Write a message of 1 to 2000 characters.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $current = $lockAccessibleComplaint();
                if (!$current) {
                    $pdo->rollBack();
                    flash('error', 'This complaint is no longer assigned to your account.');
                    redirect('admin/complaints.php');
                } else {
                    run('INSERT INTO complaint_messages (complaint_id, sender_id, message) VALUES (?, ?, ?)',
                        [$id, $u['id'], $messageText]);
                    if ($current['assigned_to']) {
                        if ((int)$current['assigned_to'] !== (int)$u['id']) {
                            notify((int)$current['assigned_to'], $id, 'A new message was added to complaint ' . $current['reference_no'] . '.');
                        }
                    } else {
                        notify_admins($id, 'A resident sent a follow-up on complaint ' . $current['reference_no'] . '.');
                    }
                    notify((int)$current['user_id'], $id, 'A message was added to complaint ' . $current['reference_no'] . '.');
                    log_activity('Sent a message on ' . $current['reference_no']);
                    $pdo->commit();
                    flash('success', 'Message sent.');
                    redirect('admin/view_complaint.php?id=' . $id);
                }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Complaint message failed: ' . $ex->getMessage());
                $messageError = 'Could not send the message. Please try again.';
            }
        }
    } elseif ($action === 'save_mediation') {
        $mediationPosted = true;
        foreach (array_keys($mediationData) as $field) {
            $mediationData[$field] = $postString($field);
        }
        if ($mediationData['respondent_name'] === '' || strlen($mediationData['respondent_name']) > 100) {
            $mediationErrors[] = 'Enter the respondent name (up to 100 characters).';
        }
        if ($mediationData['respondent_address'] === '' || strlen($mediationData['respondent_address']) > 255) {
            $mediationErrors[] = 'Enter the respondent address (up to 255 characters).';
        }
        if ($mediationData['respondent_contact'] !== '' &&
            !preg_match('/^09\d{9}$/', $mediationData['respondent_contact'])) {
            $mediationErrors[] = 'Respondent contact must be 11 digits and start with 09.';
        }
        if (!in_array($mediationData['case_status'], MEDIATION_STATUSES, true)) {
            $mediationErrors[] = 'Choose a valid mediation status.';
        }
        if (strlen($mediationData['hearing_location']) > 150 || strlen($mediationData['outcome']) > 2000) {
            $mediationErrors[] = 'Hearing location or outcome is too long.';
        }

        $hearingAt = null;
        if ($mediationData['hearing_at'] !== '') {
            $hearingDate = DateTime::createFromFormat('!Y-m-d\TH:i', $mediationData['hearing_at']);
            if (!$hearingDate || $hearingDate->format('Y-m-d\TH:i') !== $mediationData['hearing_at']) {
                $mediationErrors[] = 'Enter a valid hearing date and time.';
            } else {
                $hearingAt = $hearingDate->format('Y-m-d H:i:s');
            }
        }
        $issuedAt = null;
        $servedAt = null;
        if ($mediationData['summons_issued_at'] !== '') {
            if (!valid_date($mediationData['summons_issued_at'])) {
                $mediationErrors[] = 'Enter a valid summons issue date.';
            } else {
                $issuedAt = $mediationData['summons_issued_at'] . ' 00:00:00';
            }
        }
        if ($mediationData['summons_served_at'] !== '') {
            if (!valid_date($mediationData['summons_served_at'])) {
                $mediationErrors[] = 'Enter a valid summons service date.';
            } else {
                $servedAt = $mediationData['summons_served_at'] . ' 00:00:00';
            }
        }
        if ($servedAt && !$issuedAt) {
            $mediationErrors[] = 'Record the issue date before the service date.';
        } elseif ($servedAt && $servedAt < $issuedAt) {
            $mediationErrors[] = 'The summons service date cannot be before its issue date.';
        }
        if ($mediationData['case_status'] === 'Summons issued' && !$issuedAt) {
            $mediationErrors[] = 'Record the summons issue date before choosing Summons issued.';
        }
        if ($mediationData['case_status'] === 'Summons served' && (!$issuedAt || !$servedAt)) {
            $mediationErrors[] = 'Record both the issue and service dates before choosing Summons served.';
        }
        if ($mediationData['case_status'] === 'Scheduled' &&
            (!$hearingAt || $mediationData['hearing_location'] === '')) {
            $mediationErrors[] = 'A scheduled mediation needs a date, time, and location.';
        }

        if (!$mediationErrors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $current = $lockAccessibleComplaint();
                if (!$current) {
                    $pdo->rollBack();
                    flash('error', 'This complaint is no longer assigned to your account.');
                    redirect('admin/complaints.php');
                } else {
                run('INSERT INTO mediation_cases
                        (complaint_id, respondent_name, respondent_contact, respondent_address, hearing_at,
                         hearing_location, summons_issued_at, summons_served_at, case_status, outcome, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE respondent_name = VALUES(respondent_name),
                         respondent_contact = VALUES(respondent_contact), respondent_address = VALUES(respondent_address),
                         hearing_at = VALUES(hearing_at), hearing_location = VALUES(hearing_location),
                         summons_issued_at = VALUES(summons_issued_at), summons_served_at = VALUES(summons_served_at),
                         case_status = VALUES(case_status), outcome = VALUES(outcome), updated_by = VALUES(updated_by)',
                    [$id, $mediationData['respondent_name'], $mediationData['respondent_contact'] ?: null,
                     $mediationData['respondent_address'], $hearingAt, $mediationData['hearing_location'] ?: null,
                     $issuedAt, $servedAt, $mediationData['case_status'], $mediationData['outcome'] ?: null, $u['id']]);
                log_activity('Updated mediation record for ' . $current['reference_no']);
                $pdo->commit();
                flash('success', 'Mediation record saved.');
                redirect('admin/view_complaint.php?id=' . $id);
                }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Mediation record save failed: ' . $ex->getMessage());
                $mediationErrors[] = 'Could not save the mediation record. Please try again.';
            }
        }
    } elseif ($action === 'update_complaint') {
        $newStatus   = $postString('status');
        $newPriority = $postString('priority');
        $assigned    = $isAdmin ? (int)($_POST['assigned_to'] ?? 0) : (int)$c['assigned_to'];
        $remarks     = $postString('remarks');

        if (!in_array($newStatus, STATUSES, true))     { $errors[] = 'Invalid status.'; }
        if (!in_array($newPriority, PRIORITIES, true)) { $errors[] = 'Invalid priority.'; }
        if ($isAdmin && $assigned !== 0 && !in_array($assigned, $staffIds, true)) { $errors[] = 'Invalid staff selected.'; }
        if (strlen($remarks) > 1000) { $errors[] = 'Remarks are too long (max 1000 characters).'; }
        if ($newStatus !== $c['status'] && $remarks === '') {
            $errors[] = 'Please write a remark when you change the status, so the resident knows what is happening.';
        }

        if (!$errors) {
            $assignedVal = $assigned ?: null;
            $changedStatus   = $newStatus !== $c['status'];
            $changedPriority = $newPriority !== $c['priority'];
            $changedAssigned = $isAdmin && (int)$assignedVal !== (int)$c['assigned_to'];

            $pdo = db();
            $pdo->beginTransaction();
            try {
                $current = $lockAccessibleComplaint();
                if (!$current) {
                    $pdo->rollBack();
                    flash('error', 'This complaint is no longer assigned to your account. Refresh the page.');
                    redirect('admin/complaints.php');
                }
                run('UPDATE complaints SET status = ?, priority = ?, assigned_to = ?,
                     resolved_at = CASE WHEN ? = "Resolved" THEN COALESCE(resolved_at, NOW()) ELSE NULL END
                     WHERE id = ?',
                    [$newStatus, $newPriority, $assignedVal, $newStatus, $id]);

                if ($changedStatus || $remarks !== '') {
                    run('INSERT INTO complaint_history (complaint_id, status, remarks, changed_by) VALUES (?, ?, ?, ?)',
                        [$id, $newStatus, $remarks !== '' ? $remarks : 'Status updated.', $u['id']]);
                }
                if ($changedStatus) {
                    notify((int)$c['user_id'], $id, 'Your complaint ' . $c['reference_no'] . ' is now "' . $newStatus . '".');
                    log_activity("Changed {$c['reference_no']} status: {$c['status']} -> $newStatus");
                } elseif ($remarks !== '') {
                    notify((int)$c['user_id'], $id, 'New remark on your complaint ' . $c['reference_no'] . '.');
                    log_activity("Added remark on {$c['reference_no']}");
                }
                if ($changedPriority) {
                    log_activity("Changed {$c['reference_no']} priority: {$c['priority']} -> $newPriority");
                }
                if ($changedAssigned) {
                    log_activity("Assigned {$c['reference_no']} to " . ($assignedVal ? "user #$assignedVal" : 'nobody'));
                    if ($assignedVal && $assignedVal !== (int)$u['id']) {
                        notify($assignedVal, $id, 'Complaint ' . $c['reference_no'] . ' was assigned to you.');
                    }
                }
                $pdo->commit();
                flash('success', 'Complaint updated.');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Complaint update failed: ' . $ex->getMessage());
                flash('error', 'Could not save the changes. Please try again.');
            }
            redirect('admin/view_complaint.php?id=' . $id);
        }
    } else {
        flash('error', 'Invalid complaint action.');
        redirect('admin/view_complaint.php?id=' . $id);
    }
}

$messages = rows('SELECT m.message, m.created_at, us.full_name, us.role
                 FROM complaint_messages m JOIN users us ON us.id = m.sender_id
                 WHERE m.complaint_id = ? ORDER BY m.created_at, m.id', [$id]);
$mediation = row('SELECT * FROM mediation_cases WHERE complaint_id = ?', [$id]);
if ($mediation && !$mediationPosted) {
    foreach (['respondent_name', 'respondent_contact', 'respondent_address', 'hearing_location', 'case_status', 'outcome'] as $field) {
        $mediationData[$field] = $mediation[$field] ?? '';
    }
    $mediationData['hearing_at'] = $mediation['hearing_at'] ? date('Y-m-d\TH:i', strtotime($mediation['hearing_at'])) : '';
    $mediationData['summons_issued_at'] = $mediation['summons_issued_at'] ? date('Y-m-d', strtotime($mediation['summons_issued_at'])) : '';
    $mediationData['summons_served_at'] = $mediation['summons_served_at'] ? date('Y-m-d', strtotime($mediation['summons_served_at'])) : '';
}
$history = rows('SELECT h.status, h.remarks, h.created_at, us.full_name AS staff_name
                 FROM complaint_history h LEFT JOIN users us ON us.id = h.changed_by
                 WHERE h.complaint_id = ? ORDER BY h.created_at DESC, h.id DESC', [$id]);

$pageTitle = 'Complaint ' . $c['reference_no'];
$smsNumber = preg_replace('/[^0-9+]/', '', $c['contact_number']);
$smsBody = rawurlencode(site_setting('barangay_name', BARANGAY_NAME) . ' update for complaint ' . $c['reference_no'] . ': ');
require __DIR__ . '/../includes/header.php';
?>
<p><a href="<?= e(url('admin/complaints.php' . ($c['archived_at'] ? '?archived=1' : ''))) ?>">&laquo; Back to complaints</a></p>
<div class="grid-2" style="align-items:start">
    <div>
        <div class="card">
            <h1><?= e($c['reference_no']) ?> &mdash; <?= e($c['subject']) ?></h1>
            <p><?= status_badge($c['status']) ?> <?= priority_badge($c['priority']) ?>
               <span class="muted">Filed <?= e(fmt_date($c['created_at'])) ?></span></p>
            <?php if ($c['archived_at']): ?>
                <p class="alert alert-error">Archived <?= e(fmt_date($c['archived_at'])) ?>. This record and its history are retained.</p>
            <?php endif; ?>
            <dl class="detail">
                <dt>Category</dt><dd><?= e($c['category']) ?></dd>
                <dt>Location</dt><dd><?= e($c['location']) ?><?= $c['landmark'] ? ' &mdash; ' . e($c['landmark']) : '' ?></dd>
                <dt>Description</dt><dd><?= nl2br(e($c['description'])) ?></dd>
                <?php if ($c['photo']): ?>
                    <dt>Photo</dt><dd><a href="<?= e(url('uploads/' . $c['photo'])) ?>" target="_blank" rel="noopener">
                        <img class="photo-full" src="<?= e(url('uploads/' . $c['photo'])) ?>" alt="Photo evidence"></a></dd>
                <?php endif; ?>
            </dl>
            <?php if ($isAdmin): ?>
                <div class="actions">
                    <?php if ($c['archived_at']): ?>
                        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><button class="btn" type="submit">Restore complaint</button></form>
                    <?php else: ?>
                        <form method="post" class="inline-form" onsubmit="return confirm('Archive this complaint? Its history and messages will be kept.');"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><button class="btn btn-light" type="submit">Archive complaint</button></form>
                    <?php endif; ?>
                </div>
                <?php if (!$c['archived_at']): ?>
                    <details class="edit-details" <?= $detailsErrors ? 'open' : '' ?>>
                        <summary>Edit complaint details</summary>
                        <?php if ($detailsErrors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($detailsErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?><input type="hidden" name="action" value="edit_details">
                            <label for="edit_category">Category</label><select id="edit_category" name="category"><?php foreach (CATEGORIES as $category): ?><option value="<?= e($category) ?>" <?= $details['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select>
                            <label for="edit_subject">Subject</label><input id="edit_subject" name="subject" maxlength="150" value="<?= e($details['subject']) ?>" required>
                            <label for="edit_location">Purok</label><select id="edit_location" name="location"><?php foreach (PUROKS as $purok): ?><option value="<?= e($purok) ?>" <?= $details['location'] === $purok ? 'selected' : '' ?>><?= e($purok) ?></option><?php endforeach; ?></select>
                            <label for="edit_landmark">Landmark</label><input id="edit_landmark" name="landmark" maxlength="150" value="<?= e($details['landmark']) ?>">
                            <label for="edit_description">Description</label><textarea id="edit_description" name="description" rows="5" maxlength="10000" required><?= e($details['description']) ?></textarea>
                            <div class="actions"><button type="submit" class="btn">Save details</button></div>
                        </form>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2>Resident</h2>
            <dl class="detail">
                <dt>Name</dt><dd><?= e($c['resident_name']) ?></dd>
                <dt>Contact</dt><dd><?= e($c['contact_number']) ?></dd>
                <dt>Email</dt><dd><?= e($c['email']) ?></dd>
                <dt>Home purok</dt><dd><?= e($c['resident_purok']) ?></dd>
            </dl>
            <div class="actions">
                <a class="btn btn-outline btn-sm" href="sms:<?= e($smsNumber) ?>?body=<?= e($smsBody) ?>">Compose SMS</a>
                <a class="btn btn-light btn-sm" href="mailto:<?= e($c['email']) ?>?subject=<?= e(rawurlencode('Complaint ' . $c['reference_no'])) ?>">Compose email</a>
            </div>
            <p class="muted"><small>These links open your device’s messaging or email app; nothing is sent automatically.</small></p>
        </div>
        <div class="card">
            <h2>Resident and staff messages</h2>
            <p class="muted">Messages are visible only to the resident who filed this complaint and authorized staff.</p>
            <?php if (!$messages): ?><p class="muted">No follow-up messages yet.</p><?php endif; ?>
            <?php foreach ($messages as $message): ?>
                <article class="message-item">
                    <p class="message-meta"><strong><?= e($message['full_name']) ?></strong>
                        <span class="muted">(<?= e(ucfirst($message['role'])) ?>) · <?= e(fmt_date($message['created_at'])) ?></span></p>
                    <p><?= nl2br(e($message['message'])) ?></p>
                </article>
            <?php endforeach; ?>
            <?php if ($messageError): ?><div class="alert alert-error" role="alert"><?= e($messageError) ?></div><?php endif; ?>
            <?php if (!$c['archived_at']): ?><form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_message">
                <label for="message">Send a follow-up</label>
                <textarea id="message" name="message" rows="4" maxlength="2000" required><?= e($messageText) ?></textarea>
                <div class="actions"><button type="submit" class="btn">Send Message</button></div>
            </form><?php else: ?><p class="muted">This complaint is archived, so new messages are disabled.</p><?php endif; ?>
        </div>
        <?php require __DIR__ . '/../includes/history.php'; ?>
    </div>

    <?php if ($c['archived_at']): ?>
        <div class="card">
            <h2>Archived record</h2>
            <p>This complaint is read-only while archived. Restore it to resume updates.</p>
            <?php if ($mediation): ?>
                <h3>Katarungang Pambarangay record</h3>
                <p><strong>Respondent:</strong> <?= e($mediation['respondent_name']) ?> · <?= e($mediation['case_status']) ?></p>
                <?php if ($mediation['hearing_at']): ?><p><strong>Hearing:</strong> <?= e(fmt_date($mediation['hearing_at'])) ?><?= $mediation['hearing_location'] ? ' · ' . e($mediation['hearing_location']) : '' ?></p><?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="card">
        <h2>Update complaint</h2>
        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_complaint">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (STATUSES as $s): ?>
                    <option <?= $c['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="priority">Priority</label>
            <select id="priority" name="priority">
                <?php foreach (PRIORITIES as $p): ?>
                    <option <?= $c['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($isAdmin): ?>
                <label for="assigned_to">Assigned official / staff</label>
                <select id="assigned_to" name="assigned_to">
                    <option value="0">-- Not assigned --</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)$c['assigned_to'] === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['full_name']) ?> (<?= e(ucfirst($s['role'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <p><strong>Assigned to:</strong> your account</p>
            <?php endif; ?>

            <label for="remarks">Remarks <span class="muted">(the resident will see this)</span></label>
            <textarea id="remarks" name="remarks" rows="5" maxlength="1000"></textarea>

            <div class="actions"><button class="btn" type="submit">Save Update</button></div>
        </form>

        <hr>
        <h2>Katarungang Pambarangay mediation</h2>
        <p class="muted">For matters referred for mediation only. This records case details and summons dates; it does not determine legal eligibility or create/serve a summons.</p>
        <?php if ($mediationErrors): ?>
            <div class="alert alert-error" role="alert"><ul><?php foreach ($mediationErrors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_mediation">

            <label for="respondent_name">Respondent name</label>
            <input type="text" id="respondent_name" name="respondent_name" maxlength="100" value="<?= e($mediationData['respondent_name']) ?>" required>

            <label for="respondent_contact">Respondent contact number (optional)</label>
            <input type="text" id="respondent_contact" name="respondent_contact" maxlength="11" value="<?= e($mediationData['respondent_contact']) ?>">

            <label for="respondent_address">Respondent address</label>
            <input type="text" id="respondent_address" name="respondent_address" maxlength="255" value="<?= e($mediationData['respondent_address']) ?>" required>

            <label for="case_status">Mediation status</label>
            <select id="case_status" name="case_status">
                <?php foreach (MEDIATION_STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $mediationData['case_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="hearing_at">Hearing date and time (optional)</label>
            <input type="datetime-local" id="hearing_at" name="hearing_at" value="<?= e($mediationData['hearing_at']) ?>">

            <label for="hearing_location">Hearing location</label>
            <input type="text" id="hearing_location" name="hearing_location" maxlength="150" value="<?= e($mediationData['hearing_location']) ?>">

            <div class="grid-2">
                <div>
                    <label for="summons_issued_at">Summons issue date (record only)</label>
                    <input type="date" id="summons_issued_at" name="summons_issued_at" value="<?= e($mediationData['summons_issued_at']) ?>">
                </div>
                <div>
                    <label for="summons_served_at">Summons service date (record only)</label>
                    <input type="date" id="summons_served_at" name="summons_served_at" value="<?= e($mediationData['summons_served_at']) ?>">
                </div>
            </div>

            <label for="outcome">Outcome / staff notes</label>
            <textarea id="outcome" name="outcome" rows="4" maxlength="2000"><?= e($mediationData['outcome']) ?></textarea>
            <div class="actions"><button type="submit" class="btn">Save Mediation Record</button></div>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
