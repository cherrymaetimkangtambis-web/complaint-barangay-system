<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['resident']);

$counts = array_fill_keys(STATUSES, 0);
foreach (rows('SELECT status, COUNT(*) AS c FROM complaints WHERE user_id = ? GROUP BY status', [$u['id']]) as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$total = array_sum($counts);

$recent = rows('SELECT id, reference_no, subject, status, created_at FROM complaints
                WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5', [$u['id']]);
$notifs = rows('SELECT id, message, created_at FROM notifications
                WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC, id DESC LIMIT 5', [$u['id']]);

$pageTitle = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Welcome, <?= e($u['full_name']) ?></h1>
    <a class="btn" href="<?= e(url('resident/file_complaint.php')) ?>">+ File a Complaint</a>
</div>

<div class="stats">
    <div class="stat"><b><?= $total ?></b><span>Total complaints</span></div>
    <div class="stat"><b><?= $counts['Pending'] ?></b><span>Pending</span></div>
    <div class="stat"><b><?= $counts['In Progress'] ?></b><span>In Progress</span></div>
    <div class="stat"><b><?= $counts['Resolved'] ?></b><span>Resolved</span></div>
</div>

<?php if ($notifs): ?>
<div class="card">
    <h2>New updates</h2>
    <?php foreach ($notifs as $n): ?>
        <a class="notif unread" href="<?= e(url('notifications.php?read=' . $n['id'])) ?>">
            <?= e($n['message']) ?><small><?= e(fmt_date($n['created_at'])) ?></small>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Recent complaints</h2>
    <?php if (!$recent): ?>
        <p class="muted">You have not filed any complaint yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Reference</th><th>Subject</th><th>Status</th><th>Filed</th><th></th></tr>
        <?php foreach ($recent as $c): ?>
            <tr>
                <td><?= e($c['reference_no']) ?></td>
                <td><?= e($c['subject']) ?></td>
                <td><?= status_badge($c['status']) ?></td>
                <td><?= e(fmt_date($c['created_at'])) ?></td>
                <td><a href="<?= e(url('resident/view_complaint.php?id=' . $c['id'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <p><a href="<?= e(url('resident/my_complaints.php')) ?>">See all my complaints &raquo;</a></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
