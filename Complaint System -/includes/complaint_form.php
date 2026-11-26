<?php
// Shared form for filing / editing a complaint.
// Expects: $data (category, subject, description, location, landmark, photo), $errors, $submitLabel, $cancelUrl
?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <label for="category">Category</label>
    <select id="category" name="category" required>
        <option value="">-- Choose a category --</option>
        <?php foreach (CATEGORIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $data['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="subject">Subject</label>
    <input type="text" id="subject" name="subject" maxlength="150" value="<?= e($data['subject']) ?>" required>

    <label for="description">Describe the problem</label>
    <textarea id="description" name="description" rows="6" required><?= e($data['description']) ?></textarea>

    <div class="grid-2">
        <div>
            <label for="location">Purok where it happened</label>
            <select id="location" name="location" required>
                <option value="">-- Choose a purok --</option>
                <?php foreach (PUROKS as $p): ?>
                    <option value="<?= e($p) ?>" <?= $data['location'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="landmark">Specific spot / landmark <span class="muted">(optional)</span></label>
            <input type="text" id="landmark" name="landmark" maxlength="150" placeholder="e.g. near the chapel"
                   value="<?= e($data['landmark']) ?>">
        </div>
    </div>

    <label for="photo">Photo evidence <span class="muted">(optional, JPG/PNG, max 2 MB)</span></label>
    <?php if (!empty($data['photo'])): ?>
        <p class="muted">Current photo (upload a new one to replace it):</p>
        <img class="thumb" src="<?= e(url('uploads/' . $data['photo'])) ?>" alt="Current photo">
    <?php endif; ?>
    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png">

    <div class="actions">
        <button type="submit" class="btn"><?= e($submitLabel) ?></button>
        <a class="btn btn-light" href="<?= e(url($cancelUrl)) ?>">Cancel</a>
    </div>
</form>
