<?php
require_once __DIR__ . '/includes/init.php';
$u = require_login();
require_once __DIR__ . '/includes/migrations.php';
require_feature_schema();

// Open one notification: mark as read, then go to the complaint
if (isset($_GET['read'])) {
    if ($u['role'] === 'official') {
        $n = row('SELECT n.id, n.complaint_id FROM notifications n
                  LEFT JOIN complaints c ON c.id = n.complaint_id
                  WHERE n.id = ? AND n.user_id = ?
                    AND (n.complaint_id IS NULL OR (c.assigned_to = ? AND c.archived_at IS NULL))',
                 [(int)$_GET['read'], $u['id'], $u['id']]);
    } else {
        $n = row('SELECT id, complaint_id FROM notifications WHERE id = ? AND user_id = ?',
                 [(int)$_GET['read'], $u['id']]);
    }
    if ($n) {
        run('UPDATE notifications SET is_read = 1 WHERE id = ?', [$n['id']]);
        if ($n['complaint_id']) {
            redirect(($u['role'] === 'resident' ? 'resident' : 'admin') . '/view_complaint.php?id=' . (int)$n['complaint_id']);
        }
    }
    redirect('notifications.php');
}

if (is_post()) {
    csrf_check();
    run('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$u['id']]);
    flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

$notificationScope = 'n.user_id = ?';
$notificationParams = [$u['id']];
if ($u['role'] === 'official') {
    $notificationScope .= ' AND (n.complaint_id IS NULL OR (c.assigned_to = ? AND c.archived_at IS NULL))';
    $notificationParams[] = $u['id'];
}
$total = (int)scalar('SELECT COUNT(*) FROM notifications n LEFT JOIN complaints c ON c.id = n.complaint_id
                     WHERE ' . $notificationScope, $notificationParams);
$pg    = paginate($total, 15, (int)($_GET['page'] ?? 1));
$list  = rows('SELECT n.* FROM notifications n LEFT JOIN complaints c ON c.id = n.complaint_id
               WHERE ' . $notificationScope . ' ORDER BY n.created_at DESC, n.id DESC LIMIT '
               . (int)$pg['perPage'] . ' OFFSET ' . (int)$pg['offset'], $notificationParams);

$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Notifications</h1>
    <?php if ($total): ?>
        <form method="post"><?= csrf_field() ?><button class="btn btn-light btn-sm" type="submit">Mark all as read</button></form>
    <?php endif; ?>
</div>
<div class="card" style="padding:0;overflow:hidden">
    <?php if (!$list): ?><p class="muted" style="padding:20px">No notifications yet.</p><?php endif; ?>
    <?php foreach ($list as $n): ?>
        <a class="notif <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= e(url('notifications.php?read=' . $n['id'])) ?>">
            <?= e($n['message']) ?>
            <small><?= e(fmt_date($n['created_at'])) ?></small>
        </a>
    <?php endforeach; ?>
</div>
<?= pager($pg) ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
