<?php
// Status history list. Expects $history (rows with status, remarks, created_at, staff_name).
?>
<div class="card">
    <h2>Status History</h2>
    <?php if (!$history): ?>
        <p class="muted">No history yet.</p>
    <?php endif; ?>
    <?php foreach ($history as $h): ?>
        <div class="history-item">
            <?= status_badge($h['status']) ?>
            <span class="muted"><?= e(fmt_date($h['created_at'])) ?></span>
            <?php if (!empty($h['staff_name'])): ?>
                <span class="muted">&middot; by <?= e($h['staff_name']) ?></span>
            <?php endif; ?>
            <div><?= nl2br(e($h['remarks'])) ?></div>
        </div>
    <?php endforeach; ?>
</div>
