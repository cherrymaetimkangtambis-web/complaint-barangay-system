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

$data = rows("SELECT c.reference_no, c.created_at, r.full_name, r.contact_number, c.category, c.subject, c.description,
                     c.location, c.landmark, c.priority, c.status, s.full_name AS staff_name, c.resolved_at
              FROM complaints c JOIN users r ON r.id = c.user_id LEFT JOIN users s ON s.id = c.assigned_to
              $whereSql ORDER BY c.created_at DESC, c.id DESC", $params);

log_activity('Exported complaints to CSV (' . count($data) . ' rows)');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="complaints_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows characters correctly
fputcsv($out, ['Reference', 'Date Filed', 'Resident', 'Contact', 'Category', 'Subject', 'Description',
               'Purok', 'Landmark', 'Priority', 'Status', 'Assigned To', 'Date Resolved']);
foreach ($data as $d) {
    fputcsv($out, array_map('csv_safe', [
        $d['reference_no'], $d['created_at'], $d['full_name'], $d['contact_number'], $d['category'],
        $d['subject'], $d['description'], $d['location'], $d['landmark'], $d['priority'], $d['status'],
        $d['staff_name'], $d['resolved_at'],
    ]));
}
fclose($out);
exit;
