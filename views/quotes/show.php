<?php
/** @var array<string,mixed> $quote */
/** @var array<int,array<string,mixed>> $items */
$q = $quote;
$labels = ['draft' => 'Entwurf', 'sent' => 'Versendet', 'accepted' => 'Angenommen', 'rejected' => 'Abgelehnt'];
$badge  = ['draft' => 'draft', 'sent' => 'sent', 'accepted' => 'paid', 'rejected' => 'cancelled'];
?>
<div class="toolbar">
    <a href="/angebote" class="btn btn-secondary btn-sm">← Zurück</a>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="/angebote/<?= (int) $q['id'] ?>/pdf" class="btn btn-sm" target="_blank">PDF</a>
        <form method="post" action="/angebote/<?= (int) $q['id'] ?>/senden" data-confirm="Angebot per E-Mail an den Kunden senden?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm">✉ Per E-Mail senden</button>
        </form>
        <?php if ($q['status'] === 'draft'): ?>
            <a href="/angebote/<?= (int) $q['id'] ?>/bearbeiten" class="btn btn-sm">Bearbeiten</a>
        <?php endif; ?>
        <?php if ($q['status'] === 'draft' && empty($q['converted_invoice_id'])): ?>
            <form method="post" action="/angebote/<?= (int) $q['id'] ?>/loeschen" data-confirm="Entwurf wirklich löschen?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
            </form>
        <?php endif; ?>
        <?php if (empty($q['converted_invoice_id'])): ?>
            <form method="post" action="/angebote/<?= (int) $q['id'] ?>/in-rechnung" data-confirm="Aus diesem Angebot eine Rechnung erzeugen?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm">In Rechnung umwandeln</button>
            </form>
        <?php else: ?>
            <a href="/rechnungen/<?= (int) $q['converted_invoice_id'] ?>" class="btn btn-secondary btn-sm">Zur Rechnung</a>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 style="margin:0 0 6px;">Angebot <?= e($q['number'] ?: '(Entwurf)') ?></h2>
            <div class="muted">
                <?= e($q['company_name'] ?: $q['contact_name']) ?> · Datum <?= dt($q['quote_date']) ?>
                <?php if ($q['valid_until']): ?> · gültig bis <?= dt($q['valid_until']) ?><?php endif; ?>
            </div>
        </div>
        <span class="badge badge-<?= $badge[$q['status']] ?>"><?= e($labels[$q['status']]) ?></span>
    </div>

    <form method="post" action="/angebote/<?= (int) $q['id'] ?>/status" style="margin-top:12px; display:flex; gap:8px; align-items:center;">
        <?= csrf_field() ?>
        <label class="muted" for="status" style="font-size:13px;">Status setzen:</label>
        <select name="status" id="status" style="width:auto;">
            <?php foreach ($labels as $k => $v): ?>
                <option value="<?= $k ?>" <?= $q['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Übernehmen</button>
    </form>
</div>

<div class="panel">
    <?php if ($q['intro_text']): ?><p><?= nl2br(e($q['intro_text'])) ?></p><?php endif; ?>
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

    <div style="margin-top:14px; margin-left:auto; max-width:280px;">
        <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Netto</span><span><?= money((int) $q['net_total_cents']) ?></span></div>
        <?php if ((int) $q['is_kleinunternehmer'] !== 1): ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>USt <?= (int) $q['vat_rate'] ?> %</span><span><?= money((int) $q['vat_total_cents']) ?></span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid var(--border);font-weight:700;"><span>Gesamt</span><span><?= money((int) $q['gross_total_cents']) ?></span></div>
    </div>
    <?php if ((int) $q['is_kleinunternehmer'] === 1): ?>
        <p class="help">Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.</p>
    <?php endif; ?>
</div>
