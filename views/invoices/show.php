<?php
/** @var array<string,mixed> $invoice */
/** @var array<int,array<string,mixed>> $items */
/** @var array<int,array<string,mixed>> $payments */
/** @var array<string,mixed>|null $original */
$inv = $invoice;
$labels = ['draft' => 'Entwurf', 'sent' => 'Versendet', 'paid' => 'Bezahlt', 'overdue' => 'Überfällig', 'cancelled' => 'Storniert'];
$locked = (int) $inv['is_locked'] === 1;
$open   = (int) $inv['gross_total_cents'] - (int) $inv['paid_total_cents'];
?>
<div class="toolbar">
    <a href="/rechnungen" class="btn btn-secondary btn-sm">← Zurück</a>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="/rechnungen/<?= (int) $inv['id'] ?>/pdf" class="btn btn-sm" target="_blank">PDF</a>
        <?php if ($locked): ?>
            <a href="/rechnungen/<?= (int) $inv['id'] ?>/xrechnung" class="btn btn-secondary btn-sm">XRechnung (XML)</a>
        <?php endif; ?>
        <?php if (can('manage_sales')): ?>
            <?php if ($locked && $inv['status'] !== 'cancelled'): ?>
                <form method="post" action="/rechnungen/<?= (int) $inv['id'] ?>/senden" data-confirm="Rechnung <?= e($inv['number']) ?> per E-Mail an den Kunden senden?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm">✉ Per E-Mail senden</button>
                </form>
            <?php endif; ?>
            <?php if (!$locked): ?>
                <a href="/rechnungen/<?= (int) $inv['id'] ?>/bearbeiten" class="btn btn-sm">Bearbeiten</a>
                <form method="post" action="/rechnungen/<?= (int) $inv['id'] ?>/finalisieren" data-confirm="Rechnung jetzt finalisieren? Danach ist sie nicht mehr änderbar und erhält eine fortlaufende Nummer.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm">Finalisieren</button>
                </form>
                <form method="post" action="/rechnungen/<?= (int) $inv['id'] ?>/loeschen" data-confirm="Entwurf wirklich löschen?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
                </form>
            <?php elseif ($inv['status'] !== 'cancelled'): ?>
                <form method="post" action="/rechnungen/<?= (int) $inv['id'] ?>/storno" data-confirm="Storno-Rechnung erstellen? Es wird eine neue Rechnung mit negativen Beträgen erzeugt.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Stornieren</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($locked): ?>
    <div class="flash flash-warn" style="margin-bottom:16px;">🔒 Finalisierte Rechnung – inhaltlich gesperrt (GoBD). Korrekturen nur per Storno.</div>
<?php endif; ?>
<?php if ($original !== null): ?>
    <div class="flash flash-warn" style="margin-bottom:16px;">Storno-Rechnung zu <a href="/rechnungen/<?= (int) $original['id'] ?>"><?= e($original['number']) ?></a>.</div>
<?php endif; ?>

<div class="panel">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
        <div>
            <?php $typeLabel = match ((string) ($inv['invoice_type'] ?? 'standard')) { 'partial' => 'Abschlagsrechnung', 'final' => 'Schlussrechnung', default => 'Rechnung' }; ?>
            <h2 style="margin:0 0 6px;"><?= e($typeLabel) ?> <?= e($inv['number'] ?: '(Entwurf)') ?></h2>
            <div class="muted">
                <?= e($inv['company_name'] ?: $inv['contact_name']) ?> · Datum <?= dt($inv['invoice_date']) ?>
                <?php if ($inv['due_date']): ?> · fällig <?= dt($inv['due_date']) ?><?php endif; ?>
            </div>
        </div>
        <span class="badge badge-<?= e($inv['status']) ?>"><?= e($labels[$inv['status']] ?? $inv['status']) ?></span>
    </div>
</div>

<div class="panel">
    <?php if ($inv['intro_text']): ?><p><?= nl2br(e($inv['intro_text'])) ?></p><?php endif; ?>
    <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
        <table>
            <thead><tr><th>Pos.</th><th>Beschreibung</th><th class="num">Menge</th><th class="num">Einzelpreis</th><th class="num">Gesamt</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= (int) $it['position'] ?></td>
                    <td><?= e($it['description']) ?></td>
                    <td class="num"><?= e(rtrim(rtrim(number_format((float) $it['quantity'], 2, ',', '.'), '0'), ',')) ?> <?= e($it['unit']) ?></td>
                    <td class="num"><?= money((int) $it['unit_price_cents']) ?></td>
                    <td class="num"><?= money((int) $it['line_total_cents']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php $discount = (int) ($inv['discount_cents'] ?? 0); ?>
    <div style="margin-top:14px; margin-left:auto; max-width:320px;">
        <?php if ($discount > 0): ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Zwischensumme</span><span><?= money((int) $inv['net_total_cents']) ?></span></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;color:var(--success);"><span>Rabatt<?= (string) ($inv['discount_type'] ?? '') === 'percent' ? ' (' . rtrim(rtrim(number_format((int) $inv['discount_value'] / 100, 2, ',', ''), '0'), ',') . ' %)' : '' ?></span><span>−<?= money($discount) ?></span></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Netto</span><span><?= money((int) $inv['net_total_cents'] - $discount) ?></span></div>
        <?php else: ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Netto</span><span><?= money((int) $inv['net_total_cents']) ?></span></div>
        <?php endif; ?>
        <?php if ((int) $inv['is_kleinunternehmer'] !== 1): ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>USt <?= (int) $inv['vat_rate'] ?> %</span><span><?= money((int) $inv['vat_total_cents']) ?></span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid var(--border);font-weight:700;"><span>Gesamt</span><span><?= money((int) $inv['gross_total_cents']) ?></span></div>
        <?php if ($locked && $inv['status'] !== 'cancelled'): ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Bezahlt</span><span><?= money((int) $inv['paid_total_cents']) ?></span></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-weight:700;<?= $open > 0 ? 'color:var(--error);' : 'color:var(--success);' ?>"><span>Offen</span><span><?= money($open) ?></span></div>
        <?php endif; ?>
    </div>
    <?php if ((int) $inv['is_kleinunternehmer'] === 1): ?>
        <p class="help">Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.</p>
    <?php endif; ?>
    <?php if ((int) ($inv['skonto_percent_bp'] ?? 0) > 0 && (int) ($inv['skonto_days'] ?? 0) > 0): ?>
        <?php $skontoAmt = (int) round((int) $inv['gross_total_cents'] * (int) $inv['skonto_percent_bp'] / 10000); ?>
        <p class="help">Skonto: Bei Zahlung innerhalb von <?= (int) $inv['skonto_days'] ?> Tagen <?= rtrim(rtrim(number_format((int) $inv['skonto_percent_bp'] / 100, 2, ',', ''), '0'), ',') ?> % (<?= money($skontoAmt) ?>), zahlbar <?= money((int) $inv['gross_total_cents'] - $skontoAmt) ?>.</p>
    <?php endif; ?>
</div>

<?php if ($locked && $inv['status'] !== 'cancelled'): ?>
<div class="panel">
    <h2>Zahlungen</h2>
    <?php if ($payments !== []): ?>
        <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border); margin-bottom:14px;">
            <table>
                <thead><tr><th>Datum</th><th>Zahlart</th><th>Notiz</th><th class="num">Betrag</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $pmt): ?>
                    <tr>
                        <td><?= dt($pmt['paid_on']) ?></td>
                        <td><?= e($pmt['method']) ?></td>
                        <td><?= e($pmt['note']) ?></td>
                        <td class="num"><?= money((int) $pmt['amount_cents']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="muted">Noch keine Zahlungen erfasst.</p>
    <?php endif; ?>

    <?php if ($open > 0 && can('manage_sales')): ?>
    <form method="post" action="/rechnungen/<?= (int) $inv['id'] ?>/zahlung">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="amount">Zahlbetrag (€)</label>
                <input type="text" id="amount" name="amount" value="<?= e(amount($open)) ?>" inputmode="decimal">
            </div>
            <div class="field">
                <label for="paid_on">Zahldatum</label>
                <input type="date" id="paid_on" name="paid_on" value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label>Zahlart</label>
                <?= partial('partials/_method_select', ['current' => 'Überweisung']) ?>
            </div>
            <div class="field">
                <label for="note">Notiz</label>
                <input type="text" id="note" name="note">
            </div>
            <?php if ((int) ($inv['skonto_percent_bp'] ?? 0) > 0): ?>
                <div class="field full">
                    <label class="check"><input type="checkbox" name="skonto" value="1"> Restbetrag als Skonto ausgleichen (<?= rtrim(rtrim(number_format((int) $inv['skonto_percent_bp'] / 100, 2, ',', ''), '0'), ',') ?> %, sofern Zahlung innerhalb <?= (int) $inv['skonto_days'] ?> Tagen)</label>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Zahlung erfassen</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h2>Belege</h2>
    <?php if (($receipts ?? []) === []): ?>
        <p class="muted">Keine Belege zugeordnet. Belege lassen sich im <a href="/belege">Belege-Modul</a> dieser Rechnung zuordnen.</p>
    <?php else: ?>
        <ul style="margin:0; padding-left:18px; line-height:1.9;">
            <?php foreach ($receipts as $r): ?>
                <li><a href="/belege/<?= (int) $r['id'] ?>/download" target="_blank">📎 <?= e($r['original_name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
