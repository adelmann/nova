<?php
/** @var array<string,mixed> $settings */
/** @var array<string,mixed>|null $update */
$update = $update ?? null;
?>
<?= partial('settings/_nav', ['active' => 'system']) ?>

<div class="panel">
    <h2>Aktualisierung</h2>
    <p style="margin-top:0">Installierte Version: <strong>Nova <?= e(\Nova\Core\Version::CURRENT) ?></strong>
        <?php if (!empty($update['checked_at'])): ?>
            <span class="muted">· zuletzt geprüft: <?= e(date('d.m.Y H:i', (int) $update['checked_at'])) ?></span>
        <?php endif; ?>
    </p>

    <?php if (!empty($update['has_update'])): ?>
        <div class="flash flash-warn">
            <strong>Neue Version verfügbar: Nova <?= e($update['latest']) ?></strong>
            <?php if (!empty($update['url'])): ?> · <a href="<?= e($update['url']) ?>" target="_blank" rel="noopener">Release-Notes</a><?php endif; ?>
            <?php if (!empty($update['notes'])): ?>
                <div class="muted" style="white-space:pre-wrap; margin-top:6px; max-height:120px; overflow:auto;"><?= e($update['notes']) ?></div>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($update['error'])): ?>
        <p class="muted">Update-Prüfung derzeit nicht möglich (<?= e($update['error']) ?>).</p>
    <?php else: ?>
        <p class="muted">Nova ist aktuell.</p>
    <?php endif; ?>

    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <form method="post" action="/einstellungen/update-pruefen">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary">Jetzt auf Updates prüfen</button>
        </form>
        <?php if (!empty($update['has_update'])): ?>
            <form method="post" action="/einstellungen/update-installieren" data-confirm="Update auf Nova <?= e($update['latest']) ?> jetzt installieren? Die Installation erfolgt auf eigenes Risiko und ohne Gewährleistung. Es wird vorher automatisch ein Backup angelegt.">
                <?= csrf_field() ?>
                <button type="submit" class="btn">Update installieren (Nova <?= e($update['latest']) ?>)</button>
            </form>
        <?php endif; ?>
    </div>
    <p class="help">Vor jedem Update wird automatisch ein Backup erstellt. <code>storage/</code>, <code>config.php</code> und die Datenbank bleiben dabei unangetastet.</p>
    <div style="margin-top:6px;"><?= partial('partials/_disclaimer', ['compact' => true]) ?></div>
</div>
