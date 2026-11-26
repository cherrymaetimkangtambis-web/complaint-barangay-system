<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['resident']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$id = (int)($_GET['id'] ?? 0);
$c  = row('SELECT c.*, s.full_name AS staff_name
           FROM complaints c LEFT JOIN users s ON s.id = c.assigned_to
           WHERE c.id = ? AND c.user_id = ?', [$id, $u['id']]);
if (!$c) {
    flash('error', 'Complaint not found.');
    redirect('resident/my_complaints.php');
}

$messageError = '';
$messageText = '';
$feedbackError = '';
$feedbackText = '';
if (is_post()) {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $action = is_string($action) ? $action : '';
    $postedMessage = $_POST['message'] ?? '';
    $messageText = is_string($postedMessage) ? trim($postedMessage) : '';

    if ($action === 'send_message') {
        if ($messageText === '' || strlen($messageText) > 2000) {
            $messageError = 'Write a message of 1 to 2000 characters.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $current = row('SELECT reference_no, assigned_to FROM complaints
                                WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $u['id']]);
                if (!$current) {
                    $pdo->rollBack();
                    $messageError = 'This complaint is no longer available.';
                } else {
                    run('INSERT INTO complaint_messages (complaint_id, sender_id, message) VALUES (?, ?, ?)',
                        [$id, $u['id'], $messageText]);
                    if ($current['assigned_to']) {
                        notify((int)$current['assigned_to'], $id, 'A resident sent a message on complaint ' . $current['reference_no'] . '.');
                    } else {
                        notify_admins($id, 'A resident sent a follow-up on complaint ' . $current['reference_no'] . '.');
                    }
                    log_activity('Sent a resident follow-up on ' . $current['reference_no']);
                    $pdo->commit();
                    flash('success', 'Your message was sent to the barangay team.');
                    redirect('resident/view_complaint.php?id=' . $id);
                }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Resident complaint message failed: ' . $ex->getMessage());
                $messageError = 'Could not send your message. Please try again.';
            }
        }
    } elseif (in_array($action, ['confirm_resolution', 'reopen_complaint'], true)) {
        $postedFeedback = $_POST['feedback'] ?? '';
        $feedbackText = is_string($postedFeedback) ? trim($postedFeedback) : '';
        $ratingValue = $_POST['rating'] ?? '';
        $rating = is_string($ratingValue) && ctype_digit($ratingValue) ? (int)$ratingValue : 0;
        if (strlen($feedbackText) > 1000) {
            $feedbackError = 'Feedback must be 1000 characters or fewer.';
        } elseif ($action === 'confirm_resolution' && ($rating < 1 || $rating > 5)) {
            $feedbackError = 'Choose a rating from 1 to 5 to confirm the resolution.';
        } elseif ($action === 'reopen_complaint' && strlen($feedbackText) < 10) {
            $feedbackError = 'Explain why the complaint is still unresolved (at least 10 characters).';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $current = row('SELECT status, resolved_at, reference_no, assigned_to FROM complaints
                                WHERE id = ? AND user_id = ? FOR UPDATE', [$id, $u['id']]);
                if (!$current || $current['status'] !== 'Resolved' || !$current['resolved_at']) {
                    $pdo->rollBack();
                    $feedbackError = 'This complaint is no longer waiting for resolution feedback.';
                } else {
                    $alreadyReviewed = scalar('SELECT COUNT(*) FROM complaint_feedback
                                               WHERE complaint_id = ? AND resident_id = ? AND resolved_at = ?',
                                              [$id, $u['id'], $current['resolved_at']]);
                    if ($alreadyReviewed) {
                        $pdo->rollBack();
                        $feedbackError = 'You have already responded to this resolution.';
                    } else {
                        $decision = $action === 'confirm_resolution' ? 'confirmed' : 'reopened';
                        run('INSERT INTO complaint_feedback
                                (complaint_id, resident_id, resolved_at, decision, rating, feedback)
                             VALUES (?, ?, ?, ?, ?, ?)',
                            [$id, $u['id'], $current['resolved_at'], $decision,
                             $decision === 'confirmed' ? $rating : null,
                             $feedbackText !== '' ? $feedbackText : null]);
                        if ($decision === 'reopened') {
                            run('UPDATE complaints SET status = "In Progress", resolved_at = NULL WHERE id = ?', [$id]);
                            run('INSERT INTO complaint_history (complaint_id, status, remarks, changed_by)
                                 VALUES (?, "In Progress", ?, ?)',
                                [$id, 'Resident requested reopening: ' . $feedbackText, $u['id']]);
                        }
                        if ($current['assigned_to']) {
                            notify((int)$current['assigned_to'], $id, $decision === 'reopened'
                                ? 'The resident asked to reopen complaint ' . $current['reference_no'] . '.'
                                : 'The resident confirmed resolution of complaint ' . $current['reference_no'] . '.');
                        } else {
                            notify_admins($id, $decision === 'reopened'
                                ? 'The resident asked to reopen complaint ' . $current['reference_no'] . '.'
                                : 'The resident confirmed resolution of complaint ' . $current['reference_no'] . '.');
                        }
                        log_activity(($decision === 'reopened' ? 'Resident reopened ' : 'Resident confirmed resolution of ')
                            . $current['reference_no']);
                        $pdo->commit();
                        flash('success', $decision === 'reopened'
                            ? 'Your complaint has been reopened for follow-up.'
                            : 'Thank you for confirming the resolution.');
                        redirect('resident/view_complaint.php?id=' . $id);
                    }
                }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Resolution feedback failed: ' . $ex->getMessage());
                $feedbackError = 'Could not save your response. Please try again.';
            }
        }
    } else {
        $messageError = 'Invalid action.';
    }
}

$messages = rows('SELECT m.message, m.created_at, us.full_name, us.role
                 FROM complaint_messages m JOIN users us ON us.id = m.sender_id
                 WHERE m.complaint_id = ? ORDER BY m.created_at, m.id', [$id]);
$resolutionFeedback = $c['resolved_at']
    ? row('SELECT decision, rating, feedback, created_at FROM complaint_feedback
           WHERE complaint_id = ? AND resident_id = ? AND resolved_at = ?',
          [$id, $u['id'], $c['resolved_at']])
    : null;
$history = rows('SELECT h.status, h.remarks, h.created_at,
                        CASE WHEN us.role IN ("admin","official") THEN us.full_name ELSE NULL END AS staff_name
                 FROM complaint_history h LEFT JOIN users us ON us.id = h.changed_by
                 WHERE h.complaint_id = ? ORDER BY h.created_at DESC, h.id DESC', [$id]);

$pageTitle = 'Complaint ' . $c['reference_no'];
require __DIR__ . '/../includes/header.php';
?>
<div class="medium">
    <p><a href="<?= e(url('resident/my_complaints.php')) ?>">&laquo; Back to my complaints</a></p>

    <div class="card">
        <h1><?= e($c['reference_no']) ?> &mdash; <?= e($c['subject']) ?></h1>
        <p><?= status_badge($c['status']) ?> <?= priority_badge($c['priority']) ?>
           <span class="muted">Filed <?= e(fmt_date($c['created_at'])) ?></span></p>
        <dl class="detail">
            <dt>Category</dt><dd><?= e($c['category']) ?></dd>
            <dt>Location</dt><dd><?= e($c['location']) ?><?= $c['landmark'] ? ' &mdash; ' . e($c['landmark']) : '' ?></dd>
            <dt>Description</dt><dd><?= nl2br(e($c['description'])) ?></dd>
            <dt>Assigned to</dt><dd><?= $c['staff_name'] ? e($c['staff_name']) : '<span class="muted">Not yet assigned</span>' ?></dd>
            <?php if ($c['resolved_at']): ?>
                <dt>Resolved on</dt><dd><?= e(fmt_date($c['resolved_at'])) ?></dd>
            <?php endif; ?>
            <?php if ($c['photo']): ?>
                <dt>Photo</dt><dd><img class="photo-full" src="<?= e(url('uploads/' . $c['photo'])) ?>" alt="Photo evidence"></dd>
            <?php endif; ?>
        </dl>

        <?php if ($c['status'] === 'Pending'): ?>
            <div class="actions">
                <a class="btn btn-outline" href="<?= e(url('resident/edit_complaint.php?id=' . $c['id'])) ?>">Edit</a>
                <form method="post" action="<?= e(url('resident/cancel_complaint.php')) ?>" class="inline-form"
                      onsubmit="return confirm('Cancel this complaint? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-danger" type="submit">Cancel complaint</button>
                </form>
            </div>
            <p class="muted">You can edit or cancel a complaint only while it is still Pending.</p>
        <?php endif; ?>
    </div>

    <?php if ($c['status'] === 'Resolved'): ?>
        <div class="card">
            <h2>Was this resolved?</h2>
            <p>Please confirm the outcome or ask the barangay to reopen the complaint for follow-up.</p>
            <?php if ($feedbackError): ?><div class="alert alert-error" role="alert"><?= e($feedbackError) ?></div><?php endif; ?>
            <?php if ($resolutionFeedback): ?>
                <p><?= $resolutionFeedback['decision'] === 'confirmed' ? 'You confirmed this resolution.' : 'You requested reopening.' ?>
                    <?php if ($resolutionFeedback['rating']): ?>Your rating: <?= (int)$resolutionFeedback['rating'] ?>/5.<?php endif; ?>
                </p>
                <?php if ($resolutionFeedback['feedback']): ?><p><?= nl2br(e($resolutionFeedback['feedback'])) ?></p><?php endif; ?>
            <?php else: ?>
                <form method="post" class="resolution-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="confirm_resolution">
                    <label for="rating">Rate the resolution (1 = poor, 5 = excellent)</label>
                    <select id="rating" name="rating" required>
                        <option value="">-- Choose a rating --</option>
                        <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                            <option value="<?= $rating ?>"><?= $rating ?></option>
                        <?php endfor; ?>
                    </select>
                    <label for="resolution_feedback">Optional feedback</label>
                    <textarea id="resolution_feedback" name="feedback" rows="3" maxlength="1000"></textarea>
                    <div class="actions"><button type="submit" class="btn">Confirm Resolved</button></div>
                </form>
                <hr>
                <form method="post" class="resolution-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reopen_complaint">
                    <label for="reopen_reason">If the problem remains, explain why (at least 10 characters)</label>
                    <textarea id="reopen_reason" name="feedback" rows="3" minlength="10" maxlength="1000" required><?= e($feedbackText) ?></textarea>
                    <div class="actions"><button type="submit" class="btn btn-outline">Reopen for Follow-up</button></div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Messages with barangay staff</h2>
        <p class="muted">Only you and authorized barangay staff can see these messages. Do not send passwords or unrelated sensitive information.</p>
        <?php if (!$messages): ?><p class="muted">No follow-up messages yet.</p><?php endif; ?>
        <?php foreach ($messages as $message): ?>
            <article class="message-item">
                <p class="message-meta"><strong><?= e($message['full_name']) ?></strong>
                    <span class="muted">(<?= e(ucfirst($message['role'])) ?>) · <?= e(fmt_date($message['created_at'])) ?></span></p>
                <p><?= nl2br(e($message['message'])) ?></p>
            </article>
        <?php endforeach; ?>
        <?php if ($messageError): ?><div class="alert alert-error" role="alert"><?= e($messageError) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_message">
            <label for="message">Send a question or follow-up</label>
            <textarea id="message" name="message" rows="4" maxlength="2000" required><?= e($messageText) ?></textarea>
            <div class="actions"><button type="submit" class="btn">Send Message</button></div>
        </form>
    </div>

    <?php require __DIR__ . '/../includes/history.php'; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
