<?php
require_once __DIR__ . '/../includes/init.php';
$u = require_login(['admin']);
require_once __DIR__ . '/../includes/migrations.php';
require_feature_schema();

$defaults = [
    'barangay_name' => BARANGAY_NAME,
    'barangay_place' => BARANGAY_PLACE,
    'contact_number' => BARANGAY_CONTACT_NUMBER,
    'email' => BARANGAY_EMAIL,
    'office_hours' => BARANGAY_OFFICE_HOURS,
];
$data = [];
foreach ($defaults as $key => $fallback) {
    $data[$key] = site_setting($key, $fallback);
}
$officialLines = [];
foreach (site_officials() as $official) {
    if (is_array($official) && !empty($official['name'])) {
        $officialLines[] = trim((string)($official['position'] ?? 'Barangay Official')) . ' | ' . trim((string)$official['name']);
    }
}
$faqLines = [];
foreach (site_faq() as $faq) {
    if (is_array($faq) && !empty($faq['question']) && isset($faq['answer'])) {
        $faqLines[] = trim((string)$faq['question']) . ' | ' . trim((string)$faq['answer']);
    }
}
$officialsText = implode("\n", $officialLines);
$faqText = implode("\n", $faqLines);
$errors = [];

if (is_post()) {
    csrf_check();
    foreach (array_keys($data) as $key) {
        $data[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $officialsText = trim((string)($_POST['officials'] ?? ''));
    $faqText = trim((string)($_POST['faq'] ?? ''));
    if ($data['barangay_name'] === '' || strlen($data['barangay_name']) > 100) {
        $errors[] = 'Enter a barangay name of 100 characters or fewer.';
    }
    if ($data['barangay_place'] === '' || strlen($data['barangay_place']) > 150) {
        $errors[] = 'Enter a location of 150 characters or fewer.';
    }
    if ($data['contact_number'] !== '' &&
        (!preg_match('/^[0-9+() -]{7,20}$/', $data['contact_number']) ||
         strlen(preg_replace('/\D/', '', $data['contact_number'])) < 7)) {
        $errors[] = 'Enter a valid public contact number, or leave it blank.';
    }
    if ($data['email'] !== '' &&
        (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 120)) {
        $errors[] = 'Enter a valid public email address, or leave it blank.';
    }
    if (strlen($data['office_hours']) > 255) {
        $errors[] = 'Office hours must be 255 characters or fewer.';
    }

    $officials = [];
    foreach (preg_split('/\r\n|\r|\n/', $officialsText) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' ||
            strlen($parts[0]) > 80 || strlen($parts[1]) > 100) {
            $errors[] = 'Enter each official as Position | Name (up to 80 and 100 characters).';
            break;
        }
        $officials[] = ['position' => $parts[0], 'name' => $parts[1]];
    }
    if (count($officials) > 30) {
        $errors[] = 'Enter no more than 30 officials.';
    }

    $faq = [];
    foreach (preg_split('/\r\n|\r|\n/', $faqText) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' ||
            strlen($parts[0]) > 180 || strlen($parts[1]) > 1000) {
            $errors[] = 'Enter each FAQ as Question | Answer (up to 180 and 1000 characters).';
            break;
        }
        $faq[] = ['question' => $parts[0], 'answer' => $parts[1]];
    }
    if (count($faq) > 30) {
        $errors[] = 'Enter no more than 30 FAQ items.';
    }

    $removeLogo = !empty($_POST['remove_logo']);
    $removeBackground = !empty($_POST['remove_background']);
    $newImages = [];
    if (!$errors) {
        foreach (['logo_image' => 'logo', 'hero_background' => 'background'] as $settingKey => $fileKey) {
            $uploadError = null;
            $newImage = save_site_image($_FILES[$fileKey] ?? [], $uploadError);
            if ($uploadError !== null) {
                $errors[] = ucfirst($fileKey) . ': ' . $uploadError;
            } elseif ($newImage !== null) {
                $newImages[$settingKey] = $newImage;
            }
        }
    }

    if (!$errors) {
        $oldImages = [
            'logo_image' => site_setting('logo_image'),
            'hero_background' => site_setting('hero_background'),
        ];
        $values = $data;
        $values['officials_json'] = json_encode($officials, JSON_UNESCAPED_UNICODE);
        $values['faq_json'] = json_encode($faq, JSON_UNESCAPED_UNICODE);
        foreach (['logo_image' => $removeLogo, 'hero_background' => $removeBackground] as $settingKey => $remove) {
            if (isset($newImages[$settingKey])) {
                $values[$settingKey] = $newImages[$settingKey];
            } elseif ($remove) {
                $values[$settingKey] = '';
            }
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();
            foreach ($values as $key => $value) {
                run('INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
                    [$key, (string)$value, $u['id']]);
            }
            log_activity('Updated public website settings');
            $pdo->commit();
            foreach ($oldImages as $key => $oldImage) {
                if ($oldImage !== '' && isset($values[$key]) && $oldImage !== $values[$key]) {
                    delete_site_image($oldImage);
                }
            }
            flash('success', 'Public website settings saved.');
            redirect('admin/site_settings.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($newImages as $image) {
                delete_site_image($image);
            }
            error_log('Public website settings save failed: ' . $ex->getMessage());
            $errors[] = 'Could not save the settings. Please try again.';
        }
    } else {
        foreach ($newImages as $image) {
            delete_site_image($image);
        }
    }
}

$logoUrl = site_asset_url('logo_image');
$backgroundUrl = site_asset_url('hero_background');
$pageTitle = 'Public Website Settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Public Website Settings</h1></div>
<div class="card">
    <p class="muted">Update the information residents see on the homepage. Changes appear immediately after saving.</p>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_SITE_IMAGE_BYTES ?>">
        <h2>Barangay information</h2>
        <div class="grid-2">
            <div><label for="barangay_name">Barangay name</label><input id="barangay_name" name="barangay_name" maxlength="100" value="<?= e($data['barangay_name']) ?>" required></div>
            <div><label for="barangay_place">Municipality and province</label><input id="barangay_place" name="barangay_place" maxlength="150" value="<?= e($data['barangay_place']) ?>" required></div>
            <div><label for="contact_number">Public contact number</label><input id="contact_number" name="contact_number" maxlength="20" value="<?= e($data['contact_number']) ?>"></div>
            <div><label for="email">Public email</label><input id="email" type="email" name="email" maxlength="120" value="<?= e($data['email']) ?>"></div>
            <div><label for="office_hours">Office hours</label><input id="office_hours" name="office_hours" maxlength="255" value="<?= e($data['office_hours']) ?>" placeholder="Monday-Friday, 8:00 AM-5:00 PM"></div>
        </div>
        <label for="officials">Barangay officials (one per line: Position | Name)</label>
        <textarea id="officials" name="officials" rows="5" placeholder="Punong Barangay | Juan dela Cruz"><?= e($officialsText) ?></textarea>
        <label for="faq">Frequently asked questions (one per line: Question | Answer)</label>
        <textarea id="faq" name="faq" rows="6" placeholder="How do I file a complaint? | Register, log in, and choose File Complaint."><?= e($faqText) ?></textarea>

        <h2>Homepage images</h2>
        <p class="muted">JPG and PNG only, maximum 5 MB each. Images are re-encoded before they are stored.</p>
        <div class="grid-2 image-slots">
            <section class="image-slot">
                <h3>Barangay logo</h3>
                <?php if ($logoUrl !== ''): ?><img class="site-image-preview site-logo-preview" src="<?= e($logoUrl) ?>" alt="Current barangay logo"><?php else: ?><p class="muted">No logo uploaded. The initials will be shown instead.</p><?php endif; ?>
                <label for="logo">Upload logo</label><input id="logo" type="file" name="logo" accept="image/jpeg,image/png">
                <?php if ($logoUrl !== ''): ?><label class="consent-check"><input type="checkbox" name="remove_logo" value="1"><span>Remove current logo</span></label><?php endif; ?>
            </section>
            <section class="image-slot">
                <h3>Homepage background</h3>
                <?php if ($backgroundUrl !== ''): ?><img class="site-image-preview site-background-preview" src="<?= e($backgroundUrl) ?>" alt="Current homepage background"><?php else: ?><p class="muted">No background uploaded. The standard color background will be used.</p><?php endif; ?>
                <label for="background">Upload background</label><input id="background" type="file" name="background" accept="image/jpeg,image/png">
                <?php if ($backgroundUrl !== ''): ?><label class="consent-check"><input type="checkbox" name="remove_background" value="1"><span>Remove current background</span></label><?php endif; ?>
            </section>
        </div>
        <div class="actions"><button class="btn" type="submit">Save public settings</button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
