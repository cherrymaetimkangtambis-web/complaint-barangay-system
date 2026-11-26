<?php
// Shared header + navigation. Set $pageTitle before including.
$u      = current_user();
$cur    = basename($_SERVER['SCRIPT_NAME']);
$unread = $u ? unread_count((int)$u['id']) : 0;
$publicBarangayName = site_setting('barangay_name', BARANGAY_NAME);
$publicBarangayPlace = site_setting('barangay_place', BARANGAY_PLACE);
$logoUrl = site_asset_url('logo_image');
$title  = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME;

function nav_link(string $path, string $label, string $cur, string $extra = '', string $key = ''): string
{
    $active = (basename($path) === $cur) ? ' class="active"' : '';
    return '<a' . $active . ' href="' . e(url($path)) . '"><span data-i18n="' . e($key) . '">' . e($label) . '</span>' . $extra . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script src="<?= e(url('assets/js/accessibility.js')) ?>" defer></script>
</head>
<body data-page="<?= e($cur) ?>" class="<?= e(!$u ? 'role-public' : ($u['role'] === 'resident' ? 'role-resident' : 'role-admin')) ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= e(url($u ? home_for_role($u['role']) : 'index.php')) ?>">
            <?php if ($logoUrl !== ''): ?><img class="logo logo-image" src="<?= e($logoUrl) ?>" alt=""><?php else: ?><span class="logo">SR</span><?php endif; ?>
            <span class="brand-text"><strong><?= e($publicBarangayName) ?></strong><small><?= e($publicBarangayPlace) ?></small></span>
        </a>
        <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="main-navigation" aria-label="Open navigation menu">
            <span></span><span></span><span></span>
        </button>
        <nav class="nav" id="main-navigation" aria-label="Main navigation">
            <?php if (!$u): ?>
                <?= nav_link('index.php', 'Home', $cur, '', 'nav.home') ?>
                <?= nav_link('developer.php', 'Web Developer', $cur) ?>
                <?= nav_link('auth/login.php', 'Login', $cur, '', 'nav.login') ?>
                <a class="btn-nav<?= $cur === 'register.php' ? ' active' : '' ?>" href="<?= e(url('auth/register.php')) ?>"><span data-i18n="nav.register">Register</span></a>
            <?php elseif ($u['role'] === 'resident'): ?>
                <?= nav_link('resident/dashboard.php', 'Dashboard', $cur, '', 'nav.dashboard') ?>
                <?= nav_link('resident/file_complaint.php', 'File Complaint', $cur, '', 'nav.file_complaint') ?>
                <?= nav_link('resident/my_complaints.php', 'My Complaints', $cur, '', 'nav.my_complaints') ?>
                <?= nav_link('resident/profile.php', 'My Profile', $cur) ?>
                <?= nav_link('notifications.php', 'Notifications', $cur, $unread ? ' <span class="count">' . $unread . '</span>' : '', 'nav.notifications') ?>
                <?= nav_link('auth/change_password.php', 'Password', $cur, '', 'nav.password') ?>
                <span class="who"><?= e($u['full_name']) ?></span>
                <a class="btn-nav" href="<?= e(url('auth/logout.php')) ?>"><span data-i18n="nav.logout">Logout</span></a>
            <?php else: ?>
                <?= nav_link('admin/dashboard.php', 'Dashboard', $cur, '', 'nav.dashboard') ?>
                <?= nav_link('admin/complaints.php', 'Complaints', $cur, '', 'nav.complaints') ?>
                <?= nav_link('admin/reports.php', 'Reports', $cur, '', 'nav.reports') ?>
                <?php if ($u['role'] === 'admin'): ?>
                    <?= nav_link('admin/users.php', 'Users', $cur, '', 'nav.users') ?>
                    <?= nav_link('admin/announcements.php', 'Announcements', $cur) ?>
                    <?= nav_link('admin/site_settings.php', 'Public Site Settings', $cur) ?>
                    <?= nav_link('admin/activity_logs.php', 'Logs', $cur, '', 'nav.logs') ?>
                    <?= nav_link('admin/database_upgrade.php', 'System Upgrade', $cur) ?>
                <?php endif; ?>
                <?= nav_link('notifications.php', 'Notifications', $cur, $unread ? ' <span class="count">' . $unread . '</span>' : '', 'nav.notifications') ?>
                <?= nav_link('auth/change_password.php', 'Password', $cur, '', 'nav.password') ?>
                <span class="who"><?= e($u['full_name']) ?> <em>(<?= e(ucfirst($u['role'])) ?>)</em></span>
                <a class="btn-nav" href="<?= e(url('auth/logout.php')) ?>"><span data-i18n="nav.logout">Logout</span></a>
            <?php endif; ?>
            <button type="button" class="access-trigger" id="open-accessibility" aria-haspopup="dialog" aria-controls="accessibility-dialog">
                <span data-i18n="access.open">Help &amp; accessibility</span><span aria-hidden="true"> +</span>
            </button>
        </nav>
    </div>
</header>
<main class="container" id="main-content" tabindex="-1">
<?php foreach (get_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
