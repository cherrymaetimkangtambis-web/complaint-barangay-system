<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin']);
require_once __DIR__ . '/../includes/migrations.php';

$error = '';
if (is_post()) {
    csrf_check();
    try {
        upgrade_feature_schema();
        log_activity('Applied feature database upgrade ' . FEATURE_SCHEMA_VERSION);
        flash('success', 'Database upgrade completed. Existing residents and complaints were kept.');
        redirect('admin/database_upgrade.php');
    } catch (PDOException $ex) {
        error_log('Feature database upgrade failed: ' . $ex->getMessage());
        $error = 'The database upgrade failed. Check the PHP error log and confirm the database user can create tables and alter the required tables.';
    }
}

$ready = feature_schema_is_current();
$pageTitle = 'Database Upgrade';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Database Upgrade</h1>
    <a class="btn btn-light" href="<?= e(url('admin/dashboard.php')) ?>">Back to dashboard</a>
</div>
<div class="card medium">
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($ready): ?>
        <div class="alert alert-success">The feature database upgrade is already installed.</div>
    <?php else: ?>
        <p>This adds privacy-consent fields, reversible archive fields, and public-site settings needed by the system. It does not delete or rewrite existing complaints, users, announcements, or history.</p>
        <p><strong>Before continuing:</strong> make a backup of the database from phpMyAdmin.</p>
        <form method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn">Run database upgrade</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
