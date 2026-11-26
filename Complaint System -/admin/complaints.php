<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin', 'official']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$showArchived = $u['role'] === 'admin' && !empty($_GET['archived']);
$status   = (string)($_GET['status'] ?? '');
$category = (string)($_GET['category'] ?? '');
$purok    = (string)($_GET['purok'] ?? '');
$priority = (string)($_GET['priority'] ?? '');
$from     = (string)($_GET['from'] ?? '');
$to       = (string)($_GET['to'] ?? '');
$q        = trim((string)($_GET['q'] ?? ''));
$sort     = (string)($_GET['sort'] ?? 'newest');

$where = [];
$params = [];
if ($u['role'] === 'official') {
    $where[] = 'c.assigned_to = ?';
    $params[] = $u['id'];
}
$where[] = $showArchived ? 'c.archived_at IS NOT NULL' : 'c.archived_at IS NULL';
if (in_array($status, STATUSES, true))     { $where[] = 'c.status = ?';   $params[] = $status; }
if (in_array($category, CATEGORIES, true)) { $where[] = 'c.category = ?'; $params[] = $category; }
if (in_array($purok, PUROKS, true))        { $where[] = 'c.location = ?'; $params[] = $purok; }
if (in_array($priority, PRIORITIES, true)) { $where[] = 'c.priority = ?'; $params[] = $priority; }
if (valid_date($from))                     { $where[] = 'c.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if (valid_date($to))                       { $where[] = 'c.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
if ($q !== '') {
    $where[] = '(c.subject LIKE ? OR c.reference_no LIKE ? OR r.full_name LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orders = [
    'newest'   => 'c.created_at DESC, c.id DESC',
    'oldest'   => 'c.created_at ASC, c.id ASC',
    'priority' => "FIELD(c.priority,'Urgent','Medium','Low'), c.created_at DESC",
    'status'   => "FIELD(c.status,'Pending','In Progress','Resolved','Cancelled'), c.created_at DESC",
];
$orderSql = $orders[$sort] ?? $orders['newest'];

$from_sql = 'FROM complaints c JOIN users r ON r.id = c.user_id LEFT JOIN users s ON s.id = c.assigned_to ' . $whereSql;
$total = (int)scalar("SELECT COUNT(*) $from_sql", $params);
$pg    = paginate($total, PER_PAGE, (int)($_GET['page'] ?? 1));
$list  = rows("SELECT c.id, c.reference_no, c.subject, c.category, c.location, c.status, c.priority, c.created_at, c.archived_at,
                      r.full_name, s.full_name AS staff_name
               $from_sql ORDER BY $orderSql LIMIT " . (int)$pg['perPage'] . ' OFFSET ' . (int)$pg['offset'], $params);

$exportQuery = http_build_query(array_filter([
    'status' => $status, 'category' => $category, 'purok' => $purok, 'priority' => $priority,
    'from' => $from, 'to' => $to, 'q' => $q, 'archived' => $showArchived ? '1' : null,
]));

$pageTitle = 'All Complaints';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1><?= $showArchived ? 'Archived Complaints' : ($u['role'] === 'official' ? 'My Assigned Complaints' : 'All Complaints') ?></h1>
    <div class="actions">
        <?php if ($u['role'] === 'admin'): ?>
            <a class="btn" href="<?= e(url('admin/create_complaint.php')) ?>">Record a Complaint</a>
            <a class="btn btn-light" href="<?= e(url('admin/complaints.php' . ($showArchived ? '' : '?archived=1'))) ?>"><?= $showArchived ? 'View active' : 'View archived' ?></a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url('admin/export_csv.php' . ($exportQuery ? '?' . $exportQuery : ''))) ?>">Export to Excel (CSV)</a>
    </div>
</div>

<div class="card">
    <form method="get" class="filters">
        <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
        <div><label for="q">Search</label>
            <input type="search" id="q" name="q" placeholder="Subject, ref no., resident" value="<?= e($q) ?>"></div>
        <div><label for="status">Status</label>
            <select id="status" name="status"><option value="">All</option>
                <?php foreach (STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select></div>
        <div><label for="category">Category</label>
            <select id="category" name="category"><option value="">All</option>
                <?php foreach (CATEGORIES as $s): ?><option <?= $category === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select></div>
        <div><label for="purok">Purok</label>
            <select id="purok" name="purok"><option value="">All</option>
                <?php foreach (PUROKS as $s): ?><option <?= $purok === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select></div>
        <div><label for="priority">Priority</label>
            <select id="priority" name="priority"><option value="">All</option>
                <?php foreach (PRIORITIES as $s): ?><option <?= $priority === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select></div>
        <div><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
        <div><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
        <div><label for="sort">Sort by</label>
            <select id="sort" name="sort">
                <option value="newest"   <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                <option value="oldest"   <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                <option value="status"   <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
            </select></div>
        <div><button class="btn" type="submit">Apply</button>
             <a class="btn btn-light" href="<?= e(url('admin/complaints.php')) ?>">Reset</a></div>
    </form>
</div>

<div class="card">
    <p class="muted"><?= $total ?> complaint(s) found.</p>
    <?php if ($list): ?>
    <div class="table-wrap"><table>
        <tr><th>Reference</th><th>Resident</th><th>Subject</th><th>Category</th><th>Purok</th>
            <th>Priority</th><th>Status</th><th>Assigned</th><th>Filed</th><th></th></tr>
        <?php foreach ($list as $c): ?>
            <tr>
                <td><?= e($c['reference_no']) ?><?= $c['archived_at'] ? '<br><span class="badge b-cancelled">Archived</span>' : '' ?></td>
                <td><?= e($c['full_name']) ?></td>
                <td><?= e($c['subject']) ?></td>
                <td><?= e($c['category']) ?></td>
                <td><?= e($c['location']) ?></td>
                <td><?= priority_badge($c['priority']) ?></td>
                <td><?= status_badge($c['status']) ?></td>
                <td><?= $c['staff_name'] ? e($c['staff_name']) : '<span class="muted">-</span>' ?></td>
                <td><?= e(fmt_date($c['created_at'])) ?></td>
                <td><a href="<?= e(url('admin/view_complaint.php?id=' . $c['id'])) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pager($pg) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
