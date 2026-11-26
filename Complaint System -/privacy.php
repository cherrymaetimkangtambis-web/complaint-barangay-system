<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Privacy Notice';
require __DIR__ . '/includes/header.php';
?>
<div class="card medium">
    <h1>Privacy Notice</h1>
    <p class="muted">Notice version <?= e(PRIVACY_NOTICE_VERSION) ?> · <?= e(BARANGAY_NAME) ?>, <?= e(BARANGAY_PLACE) ?></p>
    <div class="alert alert-info" role="note">
        This is a plain-language draft for the complaint system. The barangay’s Data Protection Officer should review and approve the notice, retention period, and contact details before public deployment. It is not a legal-compliance certification.
    </div>

    <h2>Information collected under the Data Privacy Act (RA 10173)</h2>
    <p>When you register or use the system, it may collect your name, email address, contact number, purok, complaint details and location, messages, optional photo evidence, and complaint status history.</p>

    <h2>How the information is used</h2>
    <p>Barangay personnel use the information to receive, review, assign, and respond to complaints; provide status updates; arrange mediation when appropriate; and maintain records of actions taken.</p>

    <h2>Who can access it</h2>
    <p>Your complaint information is available to authorized barangay personnel who need it to handle the report. Mediation respondent details are restricted to authorized staff and are not shown on the resident complaint page. Do not include passwords, financial account details, or unrelated sensitive information in a complaint.</p>

    <h2>Retention and your rights</h2>
    <p>Records should be retained only for an approved period consistent with barangay records and privacy requirements. For access, correction, or privacy questions, contact the barangay office or its Data Protection Officer.</p>

    <h2>Barangay contact</h2>
    <p>Office hours: <?= BARANGAY_OFFICE_HOURS !== '' ? e(BARANGAY_OFFICE_HOURS) : 'Please confirm with the barangay office.' ?><br>
       Contact number: <?= BARANGAY_CONTACT_NUMBER !== '' ? e(BARANGAY_CONTACT_NUMBER) : 'Not configured yet.' ?><br>
       Email: <?= BARANGAY_EMAIL !== '' ? e(BARANGAY_EMAIL) : 'Not configured yet.' ?></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
