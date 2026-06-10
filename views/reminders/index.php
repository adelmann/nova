<?php
/** @var array<int,array<string,mixed>> $overdue */
/** @var array<int,array<string,mixed>> $reminders */
use Nova\Controllers\ReminderController;
?>
<div class="panel">
    <h2>Überfällige Rechnungen</h2>
    <?php if ($overdue === []): ?>
        <p class="muted">Keine überfälligen Rechnungen. 👍</p>
    <?php else: ?>
    <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
        <table>
            <thead><tr><th>Rechnung</th><th>Kunde</th><th>Fällig seit</th><th class="num">Offen</th><th>Bisher</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($overdue as $o): ?>
                <tr>
                    <td><a href="/rechnungen/<?= (int) $o['id'] ?>"><?= e($o['number']) ?></a></td>
                    <td><?= e($o['company_name'] ?: $o['contact_name']) ?></td>
                    <td><?= dt($o['due_date']) ?></td>
                    <td class="num"><?= money((int) $o['offen']) ?></td>
                    <td><?= (int) $o['last_level'] > 0 ? e(ReminderController::levelLabel((int) $o['last_level'])) : '–' ?></td>
                    <td style="text-align:right">
                        <form method="post" action="/mahnungen" style="display:flex; gap:6px; justify-content:flex-end; align-items:center; flex-wrap:wrap;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="invoice_id" value="<?= (int) $o['id'] ?>">
                            <input type="text" name="fee" placeholder="Gebühr €" style="width:80px;" title="Mahngebühr (leer = Standard ab Stufe 2)">
                            <input type="text" name="interest" placeholder="Zinsen €" style="width:80px;" title="Verzugszinsen (leer = automatisch aus Zinssatz)">
                            <button type="submit" class="btn btn-sm"><?= e(ReminderController::levelLabel((int) $o['last_level'] + 1)) ?> erstellen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Erstellte Mahnungen</h2>
    <?php if ($reminders === []): ?>
        <p class="muted">Noch keine Mahnungen erstellt.</p>
    <?php else: ?>
    <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
        <table>
            <thead><tr><th>Datum</th><th>Stufe</th><th>Rechnung</th><th>Kunde</th><th class="num">Gebühr</th><th class="num">Zinsen</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($reminders as $r): ?>
                <tr>
                    <td><?= dt($r['reminder_date']) ?></td>
                    <td><?= e(ReminderController::levelLabel((int) $r['level'])) ?></td>
                    <td><?= e($r['invoice_number']) ?></td>
                    <td><?= e($r['company_name'] ?: $r['contact_name']) ?></td>
                    <td class="num"><?= money((int) $r['fee_cents']) ?></td>
                    <td class="num"><?= money((int) ($r['interest_cents'] ?? 0)) ?></td>
                    <td style="text-align:right; display:flex; gap:6px; justify-content:flex-end;">
                        <a href="/mahnungen/<?= (int) $r['id'] ?>/pdf?_=<?= time() ?>" class="btn btn-sm" target="_blank">PDF</a>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('mail-<?= (int) $r['id'] ?>').style.display='block'">E-Mail-Text</button>
                        <form method="post" action="/mahnungen/<?= (int) $r['id'] ?>/senden" data-confirm="Mahnung per E-Mail an den Kunden senden?" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm">✉ Senden</button>
                        </form>
                    </td>
                </tr>
                <tr id="mail-<?= (int) $r['id'] ?>" style="display:none">
                    <td colspan="7"><textarea readonly style="width:100%; min-height:160px;"><?= e($r['email_text']) ?></textarea></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
