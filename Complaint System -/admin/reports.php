<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin', 'official']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$defaultFrom = date('Y-01-01');
$defaultTo   = date('Y-m-d');
$fromInput = $_GET['from'] ?? $defaultFrom;
$toInput   = $_GET['to'] ?? $defaultTo;
$from = is_string($fromInput) ? $fromInput : '';
$to   = is_string($toInput) ? $toInput : '';
$dateError = '';
if (!valid_date($from) || !valid_date($to) || $from > $to) {
    $from = $defaultFrom;
    $to = $defaultTo;
    $dateError = 'The date range was invalid. The report has been reset to the current year.';
}
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];
$complaintScope = 'archived_at IS NULL AND created_at BETWEEN ? AND ?';
if ($u['role'] === 'official') {
    $complaintScope .= ' AND assigned_to = ?';
    $range[] = $u['id'];
}

$total = (int)scalar('SELECT COUNT(*) FROM complaints WHERE ' . $complaintScope, $range);

$byStatus = array_fill_keys(STATUSES, 0);
foreach (rows('SELECT status, COUNT(*) c FROM complaints WHERE ' . $complaintScope . ' GROUP BY status', $range) as $r) {
    $byStatus[$r['status']] = (int)$r['c'];
}
$byMonth    = rows("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, DATE_FORMAT(created_at,'%M %Y') label, COUNT(*) c,
                           SUM(status='Resolved') resolved
                    FROM complaints WHERE " . $complaintScope . " GROUP BY ym, label ORDER BY ym", $range);
$byCategory = rows('SELECT category label, COUNT(*) c FROM complaints WHERE ' . $complaintScope . '
                    GROUP BY category ORDER BY c DESC', $range);
$byPurok    = rows('SELECT location label, COUNT(*) c FROM complaints WHERE ' . $complaintScope . '
                    GROUP BY location ORDER BY c DESC', $range);

$avgDays = scalar("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) / 24 FROM complaints
                   WHERE status = 'Resolved' AND resolved_at IS NOT NULL AND " . $complaintScope, $range);

function bars(array $items, int $total): void
{
    if (!$items) { echo '<p class="muted">No data for this period.</p>'; return; }
    $max = max(array_column($items, 'c'));
    foreach ($items as $i) {
        $w = $max ? round($i['c'] / $max * 100) : 0;
        echo '<div class="bar-row"><span>' . e($i['label']) . '</span><div class="bar"><span style="width:' . $w . '%"></span></div><b>'
           . (int)$i['c'] . ($total ? ' (' . round($i['c'] / $total * 100) . '%)' : '') . '</b></div>';
    }
}

function status_pie(array $counts): void
{
    $colors = [
        'Pending' => '#d79b12',
        'In Progress' => '#3978b9',
        'Resolved' => '#2a8b57',
        'Cancelled' => '#69736f',
    ];
    $total = array_sum($counts);
    if (!$total) {
        echo '<p class="muted">No data for this period.</p>';
        return;
    }

    $angle = -90.0;
    echo '<div class="chart-wrap"><svg class="pie-chart" viewBox="0 0 240 240" role="img" aria-labelledby="status-chart-title status-chart-desc">';
    echo '<title id="status-chart-title">Complaints by status</title>';
    echo '<desc id="status-chart-desc">' . (int)$total . ' complaints in the selected date range.</desc>';
    foreach ($counts as $label => $count) {
        if (!$count) {
            continue;
        }
        $color = $colors[$label] ?? '#555';
        if ($count === $total) {
            echo '<circle cx="120" cy="120" r="95" fill="' . $color . '"></circle>';
        } else {
            $nextAngle = $angle + ($count / $total * 360);
            $startX = 120 + 95 * cos(deg2rad($angle));
            $startY = 120 + 95 * sin(deg2rad($angle));
            $endX = 120 + 95 * cos(deg2rad($nextAngle));
            $endY = 120 + 95 * sin(deg2rad($nextAngle));
            $largeArc = ($nextAngle - $angle) > 180 ? 1 : 0;
            echo '<path d="M 120 120 L ' . round($startX, 2) . ' ' . round($startY, 2)
                . ' A 95 95 0 ' . $largeArc . ' 1 ' . round($endX, 2) . ' ' . round($endY, 2)
                . ' Z" fill="' . $color . '"></path>';
            $angle = $nextAngle;
        }
    }
    echo '</svg><ul class="chart-legend">';
    foreach ($counts as $label => $count) {
        echo '<li><span class="legend-swatch" style="background:' . ($colors[$label] ?? '#555') . '"></span>'
            . e($label) . ': <strong>' . (int)$count . '</strong></li>';
    }
    echo '</ul></div>';
}

function monthly_line(array $items): void
{
    if (!$items) {
        echo '<p class="muted">No data for this period.</p>';
        return;
    }
    $width = 760;
    $height = 300;
    $left = 48;
    $top = 20;
    $plotWidth = 680;
    $plotHeight = 210;
    $max = max(1, (int)max(array_column($items, 'c')));
    $last = count($items) - 1;
    $points = [];
    foreach ($items as $index => $item) {
        $x = $last ? $left + ($index / $last * $plotWidth) : $left + ($plotWidth / 2);
        $y = $top + $plotHeight - ((int)$item['c'] / $max * $plotHeight);
        $points[] = ['x' => round($x, 2), 'y' => round($y, 2), 'label' => $item['label'], 'count' => (int)$item['c']];
    }
    $labelStep = max(1, (int)ceil(count($items) / 8));

    echo '<div class="chart-wrap line-chart-wrap"><svg class="line-chart" viewBox="0 0 ' . $width . ' ' . $height
        . '" role="img" aria-labelledby="monthly-chart-title monthly-chart-desc">';
    echo '<title id="monthly-chart-title">Monthly complaints</title>';
    echo '<desc id="monthly-chart-desc">A line chart showing the complaint count for each month in the selected date range.</desc>';
    for ($tick = 0; $tick <= 2; $tick++) {
        $y = $top + ($plotHeight / 2 * $tick);
        $value = (int)round($max * (2 - $tick) / 2);
        echo '<line x1="' . $left . '" y1="' . round($y, 2) . '" x2="' . ($left + $plotWidth)
            . '" y2="' . round($y, 2) . '" class="chart-gridline"></line>';
        echo '<text x="' . ($left - 10) . '" y="' . round($y + 4, 2) . '" text-anchor="end" class="chart-label">' . $value . '</text>';
    }
    $coordinates = array_map(function ($point) {
        return $point['x'] . ',' . $point['y'];
    }, $points);
    echo '<polyline points="' . e(implode(' ', $coordinates)) . '" class="chart-line"></polyline>';
    foreach ($points as $index => $point) {
        echo '<circle cx="' . $point['x'] . '" cy="' . $point['y'] . '" r="4" class="chart-point">'
            . '<title>' . e($point['label']) . ': ' . $point['count'] . ' complaints</title></circle>';
        if ($index % $labelStep === 0 || $index === $last) {
            echo '<text x="' . $point['x'] . '" y="' . ($top + $plotHeight + 26)
                . '" text-anchor="middle" class="chart-label">' . e($point['label']) . '</text>';
        }
    }
    echo '</svg></div>';
}

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Complaint Reports</h1>
    <button class="btn btn-outline no-print" onclick="window.print()">Print / Save as PDF</button>
</div>

<?php if ($dateError): ?><div class="alert alert-error" role="alert"><?= e($dateError) ?></div><?php endif; ?>

<div class="card no-print">
    <form method="get" class="filters">
        <div><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($from) ?>" max="<?= e($to) ?>"></div>
        <div><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($to) ?>" min="<?= e($from) ?>"></div>
        <div><button class="btn" type="submit">Generate</button></div>
    </form>
</div>

<div class="card">
    <h2><?= e(BARANGAY_NAME) ?> &mdash; Complaint Summary</h2>
    <p class="muted">Period: <?= e(date('M j, Y', strtotime($from))) ?> to <?= e(date('M j, Y', strtotime($to))) ?>
       &middot; Prepared by <?= e($u['full_name']) ?> on <?= e(date('M j, Y g:i A')) ?></p>
    <div class="stats">
        <div class="stat"><b><?= $total ?></b><span>Total complaints</span></div>
        <div class="stat"><b><?= $byStatus['Pending'] ?></b><span>Pending</span></div>
        <div class="stat"><b><?= $byStatus['In Progress'] ?></b><span>In Progress</span></div>
        <div class="stat"><b><?= $byStatus['Resolved'] ?></b><span>Resolved</span></div>
        <div class="stat"><b><?= $byStatus['Cancelled'] ?></b><span>Cancelled</span></div>
        <div class="stat"><b><?= $avgDays !== null && $avgDays !== false ? round((float)$avgDays, 1) : '-' ?></b><span>Avg. days to resolve</span></div>
    </div>
</div>

<div class="grid-2 report-charts">
    <section class="card">
        <h2>Complaints by status</h2>
        <?php status_pie($byStatus); ?>
    </section>
    <section class="card">
        <h2>Monthly trend</h2>
        <?php monthly_line($byMonth); ?>
    </section>
</div>

<div class="card">
    <h2>Per month</h2>
    <?php if (!$byMonth): ?><p class="muted">No data for this period.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Month</th><th>Complaints filed</th><th>Resolved</th></tr>
        <?php foreach ($byMonth as $m): ?>
            <tr><td><?= e($m['label']) ?></td><td><?= (int)$m['c'] ?></td><td><?= (int)$m['resolved'] ?></td></tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<div class="card"><h2>Per category</h2><?php bars($byCategory, $total); ?></div>
<div class="card"><h2>Per purok</h2><?php bars($byPurok, $total); ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
