<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin', 'official']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$counts = array_fill_keys(STATUSES, 0);
$scope = $u['role'] === 'official' ? ' WHERE archived_at IS NULL AND assigned_to = ?' : ' WHERE archived_at IS NULL';
$scopeParams = $u['role'] === 'official' ? [$u['id']] : [];
foreach (rows('SELECT status, COUNT(*) AS c FROM complaints' . $scope . ' GROUP BY status', $scopeParams) as $r) {
    $counts[$r['status']] = (int)$r['c'];
}
$total   = array_sum($counts);
$urgentWhere = "WHERE archived_at IS NULL AND priority = 'Urgent' AND status IN ('Pending','In Progress')" .
    ($u['role'] === 'official' ? ' AND assigned_to = ?' : '');
$urgent  = (int)scalar('SELECT COUNT(*) FROM complaints ' . $urgentWhere, $scopeParams);
$mine    = (int)scalar("SELECT COUNT(*) FROM complaints WHERE archived_at IS NULL AND assigned_to = ? AND status IN ('Pending','In Progress')", [$u['id']]);
$residents = $u['role'] === 'admin' ? (int)scalar("SELECT COUNT(*) FROM users WHERE role = 'resident'") : null;

$recentWhere = $u['role'] === 'official' ? 'WHERE c.archived_at IS NULL AND c.assigned_to = ?' : 'WHERE c.archived_at IS NULL';
$recent = rows('SELECT c.id, c.reference_no, c.subject, c.status, c.priority, c.location, c.created_at, r.full_name
                FROM complaints c JOIN users r ON r.id = c.user_id ' . $recentWhere . '
                ORDER BY c.created_at DESC, c.id DESC LIMIT 8', $scopeParams);

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <a class="btn" href="<?= e(url('admin/complaints.php')) ?>"><?= $u['role'] === 'official' ? 'View My Assignments' : 'Manage Complaints' ?></a>
</div>

<div class="stats">
    <div class="stat"><b><?= $total ?></b><span>Total complaints</span></div>
    <div class="stat"><b><?= $counts['Pending'] ?></b><span>Pending</span></div>
    <div class="stat"><b><?= $counts['In Progress'] ?></b><span>In Progress</span></div>
    <div class="stat"><b><?= $counts['Resolved'] ?></b><span>Resolved</span></div>
    <div class="stat warn"><b><?= $urgent ?></b><span>Urgent (open)</span></div>
    <?php if ($u['role'] === 'official'): ?>
        <div class="stat"><b><?= $mine ?></b><span>Assigned to me</span></div>
    <?php else: ?>
        <div class="stat"><b><?= $residents ?></b><span>Registered residents</span></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Latest complaints</h2>
    <?php if (!$recent): ?>
        <p class="muted">No complaints yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Reference</th><th>Resident</th><th>Subject</th><th>Purok</th><th>Priority</th><th>Status</th><th>Filed</th><th></th></tr>
        <?php foreach ($recent as $c): ?>
            <tr>
                <td><?= e($c['reference_no']) ?></td>
                <td><?= e($c['full_name']) ?></td>
                <td><?= e($c['subject']) ?></td>
                <td><?= e($c['location']) ?></td>
                <td><?= priority_badge($c['priority']) ?></td>
                <td><?= status_badge($c['status']) ?></td>
                <td><?= e(fmt_date($c['created_at'])) ?></td>
                <td><a href="<?= e(url('admin/view_complaint.php?id=' . $c['id'])) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
