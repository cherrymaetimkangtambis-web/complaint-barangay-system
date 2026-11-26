<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Web Developer';

$developerName = 'Cherry Mae Tambis';
$developerRole = 'Web Developer | Information Technology Student';
$developerEmail = 'Cherrymaetimkangtambis@gmail.com';
$developerPhone = '09125267412';
$developerPhoneLink = '+639125267412';

require __DIR__ . '/includes/header.php';
?>
<section class="developer-hero" aria-labelledby="developer-name">
    <div class="developer-photo-wrap">
        <img class="developer-photo"
             src="<?= e(url('assets/images/cherry-mae-tambis.jpg')) ?>"
             alt="Portrait of <?= e($developerName) ?>">
    </div>

    <div class="developer-profile">
        <p class="eyebrow">SYSTEM DEVELOPER</p>
        <h1 id="developer-name"><?= e($developerName) ?></h1>
        <p class="developer-role"><?= e($developerRole) ?></p>
        <p class="developer-description">
            A Bachelor of Science in Information Technology student at Southern Leyte State University – Tomas Oppus,
            focused on building practical, accessible technology solutions that serve people and communities.
            This Barangay Complaint Management System was developed to help residents submit concerns and follow
            their progress through a clear and organized public service portal.
        </p>

        <div class="developer-details" aria-label="Developer contact details">
            <div class="developer-detail">
                <span>Email</span>
                <strong><a href="mailto:<?= e($developerEmail) ?>"><?= e($developerEmail) ?></a></strong>
            </div>
            <div class="developer-detail">
                <span>Phone</span>
                <strong><a href="tel:<?= e($developerPhoneLink) ?>"><?= e($developerPhone) ?></a></strong>
            </div>
        </div>
    </div>
</section>

<div class="grid-2">
    <section class="card developer-card" aria-labelledby="skills-heading">
        <p class="section-kicker">TECHNICAL FOCUS</p>
        <h2 id="skills-heading">Skills and Knowledge</h2>
        <p>Web development, basic programming, databases and information management, problem-solving,
           digital literacy, and responsible use of technology.</p>
    </section>

    <section class="card developer-card" aria-labelledby="values-heading">
        <p class="section-kicker">DEVELOPMENT APPROACH</p>
        <h2 id="values-heading">Purpose and Values</h2>
        <p>Creating useful systems with patience, responsibility, empathy, and a willingness to learn while
           helping improve the way people and communities connect with services.</p>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
