<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['resident']);

if (!is_post()) {
    redirect('resident/my_complaints.php');
}
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$c  = row('SELECT id, reference_no, status, assigned_to FROM complaints WHERE id = ? AND user_id = ?', [$id, $u['id']]);

if (!$c) {
    flash('error', 'Complaint not found.');
    redirect('resident/my_complaints.php');
}

$st = db()->prepare('UPDATE complaints SET status = "Cancelled" WHERE id = ? AND user_id = ? AND status = "Pending"');
$st->execute([$id, $u['id']]);

if ($st->rowCount() === 0) {
    flash('error', 'Only Pending complaints can be cancelled.');
} else {
    run('INSERT INTO complaint_history (complaint_id, status, remarks, changed_by) VALUES (?, "Cancelled", ?, ?)',
        [$id, 'Complaint cancelled by resident.', $u['id']]);
    if ($c['assigned_to']) {
        notify((int)$c['assigned_to'], $id, 'Complaint ' . $c['reference_no'] . ' was cancelled by the resident.');
    } else {
        notify_admins($id, 'Complaint ' . $c['reference_no'] . ' was cancelled by the resident.');
    }
    log_activity('Cancelled complaint ' . $c['reference_no']);
    flash('success', 'Your complaint was cancelled.');
}
redirect('resident/view_complaint.php?id=' . $id);
