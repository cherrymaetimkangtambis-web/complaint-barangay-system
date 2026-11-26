<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Home';
$user = current_user();
$publicBarangayName = site_setting('barangay_name', BARANGAY_NAME);
$publicBarangayPlace = site_setting('barangay_place', BARANGAY_PLACE);
$publicContactNumber = site_setting('contact_number', BARANGAY_CONTACT_NUMBER);
$publicEmail = site_setting('email', BARANGAY_EMAIL);
$publicOfficeHours = site_setting('office_hours', BARANGAY_OFFICE_HOURS);
$heroBackground = site_asset_url('hero_background');
$heroStyle = $heroBackground !== ''
    ? 'background-image: linear-gradient(90deg, rgba(248, 251, 249, .97), rgba(248, 251, 249, .82)), url("' . e($heroBackground) . '");'
    : 'background-image: linear-gradient(90deg, rgba(248, 251, 249, .97), rgba(248, 251, 249, .78)), url("https://images.unsplash.com/photo-1520857014576-2c4f4c972b57?auto=format&fit=crop&w=1800&q=88");';
$announcements = [];
if (scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
           ['announcements'])) {
    $archiveColumn = (int)scalar(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['announcements', 'archived_at']
    );
    $archiveFilter = $archiveColumn ? ' AND archived_at IS NULL' : '';
    $announcements = rows('SELECT title, body, created_at FROM announcements
                           WHERE is_published = 1' . $archiveFilter . '
                             AND (publish_until IS NULL OR publish_until >= NOW())
                           ORDER BY created_at DESC, id DESC LIMIT 5');
}
require __DIR__ . '/includes/header.php';
?>
<section class="home-hero" aria-labelledby="home-title"<?= $heroStyle !== '' ? ' style="' . $heroStyle . '"' : '' ?>>
    <div class="home-hero-copy">
        <p class="eyebrow">COMMUNITY SERVICE PORTAL</p>
        <p class="hero-location"><?= e($publicBarangayName) ?> <span aria-hidden="true">/</span> <?= e($publicBarangayPlace) ?></p>
        <h1 id="home-title">A clearer way to raise community concerns.</h1>
        <p class="hero-lead">File a concern, follow its progress, and stay in touch with your barangay team from one simple place.</p>
        <div class="actions hero-actions">
            <?php if ($user): ?>
                <a class="btn" href="<?= e(url(home_for_role($user['role']))) ?>">Go to my dashboard</a>
            <?php else: ?>
                <a class="btn" href="<?= e(url('auth/register.php')) ?>">Create a Resident Account</a>
                <a class="btn btn-outline" href="<?= e(url('auth/login.php')) ?>">Resident / Official / Admin Login</a>
            <?php endif; ?>
        </div>
        <p class="hero-footnote">For urgent or emergency situations, contact the appropriate local authorities directly.</p>
    </div>

    <aside class="service-panel" aria-labelledby="service-panel-title">
        <p class="service-kicker">HOW IT WORKS</p>
        <h2 id="service-panel-title">Three simple steps</h2>
        <ol class="service-steps">
            <li>
                <span class="step-number" aria-hidden="true">01</span>
                <div><strong>Create your account</strong><p>Use your name, contact number, and purok.</p></div>
            </li>
            <li>
                <span class="step-number" aria-hidden="true">02</span>
                <div><strong>Describe the concern</strong><p>Share where it happened and add useful details.</p></div>
            </li>
            <li>
                <span class="step-number" aria-hidden="true">03</span>
                <div><strong>Follow the updates</strong><p>Check staff replies, status changes, and next steps.</p></div>
            </li>
        </ol>
        <p class="service-panel-note">Need help using the website? Open <strong>Help &amp; accessibility</strong> in the top menu.</p>
    </aside>
</section>

<section class="card about-card" aria-labelledby="about-heading">
    <div class="section-heading">
        <p class="section-kicker">ABOUT THE SERVICE</p>
        <h2 id="about-heading">A more organized way to work together</h2>
    </div>
    <p>This website helps residents of <?= e($publicBarangayName) ?>, <?= e($publicBarangayPlace) ?> submit community concerns and follow their progress. Authorized barangay staff can review reports, share updates, and keep the case history in one place.</p>
</section>

<section class="card" aria-labelledby="announcements-heading">
    <h2 id="announcements-heading">Barangay Announcements</h2>
    <?php if (!$announcements): ?>
        <p class="muted">There are no current announcements.</p>
    <?php else: ?>
        <?php foreach ($announcements as $announcement): ?>
            <article class="announcement">
                <h3><?= e($announcement['title']) ?></h3>
                <p><?= nl2br(e($announcement['body'])) ?></p>
                <p class="muted"><small>Posted <?= e(fmt_date($announcement['created_at'])) ?></small></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<div class="grid-2 public-info">
    <section class="card public-info-card" aria-labelledby="officials-heading">
        <p class="section-kicker">YOUR BARANGAY TEAM</p>
        <h2 id="officials-heading">Barangay Officials</h2>
        <?php $publishedOfficials = array_filter(site_officials(), function ($official) { return is_array($official) && !empty($official['name']); }); ?>
        <?php if (!$publishedOfficials): ?>
            <p class="muted">Verified official information will be posted here.</p>
        <?php else: ?>
            <?php foreach ($publishedOfficials as $official): ?>
                <p><strong><?= e($official['position'] ?? 'Barangay Official') ?>:</strong> <?= e($official['name']) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <section class="card public-info-card" aria-labelledby="contact-heading">
        <p class="section-kicker">VISIT OR CONTACT US</p>
        <h2 id="contact-heading">Office and Contact</h2>
        <p><strong>Office hours:</strong> <?= $publicOfficeHours !== '' ? e($publicOfficeHours) : 'Please confirm with the barangay office.' ?></p>
        <?php if ($publicContactNumber !== ''): ?>
            <?php $phoneLink = preg_replace('/[^0-9+]/', '', $publicContactNumber); ?>
            <p><strong>Phone:</strong> <a href="tel:<?= e($phoneLink) ?>"><?= e($publicContactNumber) ?></a></p>
        <?php endif; ?>
        <?php if ($publicEmail !== ''): ?>
            <p><strong>Email:</strong> <a href="mailto:<?= e($publicEmail) ?>"><?= e($publicEmail) ?></a></p>
        <?php endif; ?>
        <?php if ($publicContactNumber === '' && $publicEmail === ''): ?>
            <p class="muted">Contact details are not posted yet. Please visit the barangay hall.</p>
        <?php endif; ?>
    </section>
</div>

<section class="card" aria-labelledby="faq-heading">
    <h2 id="faq-heading">Frequently Asked Questions</h2>
    <?php foreach (site_faq() as $faq): ?>
        <details class="faq-item">
            <summary><?= e($faq['question']) ?></summary>
            <p><?= e($faq['answer']) ?></p>
        </details>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
