<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin']);

$q = trim((string)($_GET['q'] ?? ''));
$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE (l.action LIKE ? OR us.full_name LIKE ?)';
    $params = ["%$q%", "%$q%"];
}

$total = (int)scalar("SELECT COUNT(*) FROM activity_logs l LEFT JOIN users us ON us.id = l.user_id $where", $params);
$pg    = paginate($total, 20, (int)($_GET['page'] ?? 1));
$list  = rows("SELECT l.action, l.ip_address, l.created_at, us.full_name, us.role
               FROM activity_logs l LEFT JOIN users us ON us.id = l.user_id $where
               ORDER BY l.created_at DESC, l.id DESC LIMIT " . (int)$pg['perPage'] . ' OFFSET ' . (int)$pg['offset'], $params);

$pageTitle = 'Activity Logs';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Activity Logs</h1></div>
<div class="card">
    <form method="get" class="filters">
        <div><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Action or name"></div>
        <div><button class="btn" type="submit">Search</button></div>
    </form>
</div>
<div class="card">
    <p class="muted"><?= $total ?> record(s).</p>
    <div class="table-wrap"><table>
        <tr><th>When</th><th>Who</th><th>Action</th><th>IP address</th></tr>
        <?php foreach ($list as $l): ?>
            <tr>
                <td><?= e(fmt_date($l['created_at'])) ?></td>
                <td><?= $l['full_name'] ? e($l['full_name']) . ' <span class="muted">(' . e($l['role']) . ')</span>' : '<span class="muted">-</span>' ?></td>
                <td><?= e($l['action']) ?></td>
                <td><?= e($l['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pager($pg) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
