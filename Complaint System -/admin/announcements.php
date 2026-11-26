<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$errors = [];
$showArchived = !empty($_GET['archived']);
$editId = (int)($_GET['edit'] ?? 0);
$data = ['title' => '', 'body' => '', 'publish_until' => ''];
$editing = $editId ? row('SELECT * FROM announcements WHERE id = ?', [$editId]) : null;
if ($editing) {
    $data['title'] = $editing['title'];
    $data['body'] = $editing['body'];
    $data['publish_until'] = $editing['publish_until'] ? date('Y-m-d\TH:i', strtotime($editing['publish_until'])) : '';
}
if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if (in_array($action, ['create', 'edit'], true)) {
        $editId = $action === 'edit' ? (int)($_POST['id'] ?? 0) : 0;
        $editing = $editId ? row('SELECT * FROM announcements WHERE id = ?', [$editId]) : null;
        if ($action === 'edit' && !$editing) {
            $errors[] = 'Announcement not found.';
        }
        $data['title'] = trim((string)($_POST['title'] ?? ''));
        $data['body'] = trim((string)($_POST['body'] ?? ''));
        $data['publish_until'] = trim((string)($_POST['publish_until'] ?? ''));
        $publish = isset($_POST['publish']) && (!$editing || !$editing['archived_at']);

        if ($data['title'] === '' || strlen($data['title']) > 150) {
            $errors[] = 'Enter a title of 150 characters or fewer.';
        }
        if ($data['body'] === '' || strlen($data['body']) > 5000) {
            $errors[] = 'Enter announcement text of 5000 characters or fewer.';
        }
        $publishUntil = null;
        if ($data['publish_until'] !== '') {
            $expiry = DateTime::createFromFormat('!Y-m-d\TH:i', $data['publish_until']);
            if (!$expiry || $expiry->format('Y-m-d\TH:i') !== $data['publish_until']) {
                $errors[] = 'Enter a valid publication end date and time.';
            } else {
                $publishUntil = $expiry->format('Y-m-d H:i:s');
            }
        }
        if (!$errors) {
            try {
                if ($action === 'edit') {
                    run('UPDATE announcements SET title = ?, body = ?, is_published = ?, publish_until = ? WHERE id = ?',
                        [$data['title'], $data['body'], $publish ? 1 : 0, $publishUntil, $editId]);
                    log_activity('Edited announcement #' . $editId);
                } else {
                    run('INSERT INTO announcements (title, body, is_published, publish_until, created_by)
                         VALUES (?, ?, ?, ?, ?)',
                        [$data['title'], $data['body'], $publish ? 1 : 0, $publishUntil, $u['id']]);
                    $announcementId = (int)db()->lastInsertId();
                    log_activity(($publish ? 'Published' : 'Drafted') . ' announcement #' . $announcementId);
                }
                flash('success', $action === 'edit' ? 'Announcement updated.' : ($publish ? 'Announcement published.' : 'Announcement saved as a draft.'));
                redirect('admin/announcements.php');
            } catch (PDOException $ex) {
                error_log('Announcement save failed: ' . $ex->getMessage());
                $errors[] = 'Could not save this announcement. Please try again.';
            }
        }
    } elseif (in_array($action, ['toggle', 'archive', 'restore'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $announcement = row('SELECT id, title, is_published, archived_at FROM announcements WHERE id = ?', [$id]);
        if (!$announcement) {
            flash('error', 'Announcement not found.');
        } else {
            try {
                if ($action === 'toggle') {
                    if ($announcement['archived_at']) {
                        flash('error', 'Restore the announcement before changing its publication status.');
                        redirect('admin/announcements.php?archived=1');
                    }
                    $published = (int)!$announcement['is_published'];
                    run('UPDATE announcements SET is_published = ? WHERE id = ?', [$published, $id]);
                    log_activity(($published ? 'Published' : 'Unpublished') . ' announcement #' . $id);
                    flash('success', $published ? 'Announcement published.' : 'Announcement unpublished.');
                } elseif ($action === 'archive') {
                    run('UPDATE announcements SET archived_at = NOW(), archived_by = ?, is_published = 0 WHERE id = ?',
                        [$u['id'], $id]);
                    log_activity('Archived announcement #' . $id);
                    flash('success', 'Announcement archived. It is no longer visible on the public homepage.');
                } else {
                    run('UPDATE announcements SET archived_at = NULL, archived_by = NULL, is_published = 0 WHERE id = ?', [$id]);
                    log_activity('Restored announcement #' . $id);
                    flash('success', 'Announcement restored as a draft.');
                }
            } catch (PDOException $ex) {
                error_log('Announcement action failed: ' . $ex->getMessage());
                flash('error', 'Could not update that announcement. Please try again.');
            }
        }
        redirect('admin/announcements.php' . ($showArchived ? '?archived=1' : ''));
    } else {
        $errors[] = 'Invalid action.';
    }
}

$announcements = rows('SELECT a.*, u.full_name AS author FROM announcements a
                       JOIN users u ON u.id = a.created_by
                       WHERE a.archived_at IS ' . ($showArchived ? 'NOT NULL' : 'NULL') . '
                       ORDER BY a.created_at DESC, a.id DESC');
$pageTitle = 'Manage Announcements';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Manage Announcements</h1></div>
<div class="card">
    <h2><?= $editing ? 'Edit announcement' : 'Post an announcement' ?></h2>
    <p class="muted">Published announcements appear on the public homepage. An optional end time hides the post after that time.</p>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <label for="title">Title</label>
        <input type="text" id="title" name="title" maxlength="150" value="<?= e($data['title']) ?>" required>

        <label for="body">Announcement</label>
        <textarea id="body" name="body" rows="5" maxlength="5000" required><?= e($data['body']) ?></textarea>

        <label for="publish_until">Hide after (optional)</label>
        <input type="datetime-local" id="publish_until" name="publish_until" value="<?= e($data['publish_until']) ?>">

        <label class="consent-check"><input type="checkbox" name="publish" value="1" <?= $editing && $editing['is_published'] ? 'checked' : '' ?>>
            <span>Publish immediately. Leave unchecked to save as a draft.</span></label>
        <div class="actions"><button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Save Announcement' ?></button>
            <?php if ($editing): ?><a class="btn btn-light" href="<?= e(url('admin/announcements.php' . ($showArchived ? '?archived=1' : ''))) ?>">Cancel</a><?php endif; ?></div>
    </form>
</div>

<div class="card">
    <div class="page-head"><h2><?= $showArchived ? 'Archived announcements' : 'Active announcements' ?></h2>
        <a class="btn btn-outline" href="<?= e(url('admin/announcements.php' . ($showArchived ? '' : '?archived=1'))) ?>"><?= $showArchived ? 'View active' : 'View archived' ?></a></div>
    <?php if (!$announcements): ?>
        <p class="muted">No announcements yet.</p>
    <?php else: ?>
        <?php foreach ($announcements as $announcement): ?>
            <article class="announcement">
                <h3><?= e($announcement['title']) ?>
                    <span class="badge <?= $announcement['is_published'] ? 'b-resolved' : 'b-cancelled' ?>">
                        <?= $announcement['is_published'] ? 'Published' : 'Draft' ?>
                    </span>
                </h3>
                <p><?= nl2br(e($announcement['body'])) ?></p>
                <p class="muted"><small>By <?= e($announcement['author']) ?> · <?= e(fmt_date($announcement['created_at'])) ?>
                    <?= $announcement['publish_until'] ? ' · Until ' . e(fmt_date($announcement['publish_until'])) : '' ?></small></p>
                <div class="actions">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('admin/announcements.php?edit=' . (int)$announcement['id'] . ($showArchived ? '&archived=1' : ''))) ?>">Edit</a>
                    <?php if (!$announcement['archived_at']): ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$announcement['id'] ?>">
                        <button class="btn btn-light btn-sm" type="submit"><?= $announcement['is_published'] ? 'Unpublish' : 'Publish' ?></button>
                    </form>
                    <form method="post" class="inline-form" onsubmit="return confirm('Archive this announcement?');">
                        <?= csrf_field() ?><input type="hidden" name="action" value="archive">
                        <input type="hidden" name="id" value="<?= (int)$announcement['id'] ?>">
                        <button class="btn btn-light btn-sm" type="submit">Archive</button>
                    </form>
                    <?php else: ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="restore">
                        <input type="hidden" name="id" value="<?= (int)$announcement['id'] ?>">
                        <button class="btn btn-light btn-sm" type="submit">Restore</button>
                    </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
