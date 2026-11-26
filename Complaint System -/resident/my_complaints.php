<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['resident']);

$status = (string)($_GET['status'] ?? '');
$q      = trim((string)($_GET['q'] ?? ''));

$where  = ['user_id = ?'];
$params = [$u['id']];
if (in_array($status, STATUSES, true)) {
    $where[]  = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[]  = '(subject LIKE ? OR reference_no LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$total = (int)scalar("SELECT COUNT(*) FROM complaints WHERE $whereSql", $params);
$pg    = paginate($total, PER_PAGE, (int)($_GET['page'] ?? 1));
$list  = rows("SELECT id, reference_no, subject, category, status, created_at FROM complaints
               WHERE $whereSql ORDER BY created_at DESC, id DESC
               LIMIT " . (int)$pg['perPage'] . ' OFFSET ' . (int)$pg['offset'], $params);

$pageTitle = 'My Complaints';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>My Complaints</h1>
    <a class="btn" href="<?= e(url('resident/file_complaint.php')) ?>">+ File a Complaint</a>
</div>

<div class="card">
    <form method="get" class="filters">
        <div>
            <label for="q">Search</label>
            <input type="search" id="q" name="q" placeholder="Subject or reference no." value="<?= e($q) ?>">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                <?php foreach (STATUSES as $s): ?>
                    <option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><button class="btn" type="submit">Filter</button></div>
    </form>
</div>

<div class="card">
    <?php if (!$list): ?>
        <p class="muted">No complaints found.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Reference</th><th>Subject</th><th>Category</th><th>Status</th><th>Filed</th><th></th></tr>
        <?php foreach ($list as $c): ?>
            <tr>
                <td><?= e($c['reference_no']) ?></td>
                <td><?= e($c['subject']) ?></td>
                <td><?= e($c['category']) ?></td>
                <td><?= status_badge($c['status']) ?></td>
                <td><?= e(fmt_date($c['created_at'])) ?></td>
                <td><a href="<?= e(url('resident/view_complaint.php?id=' . $c['id'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pager($pg) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
