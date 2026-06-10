<?php
/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $items */
/** @var int $unbilledCents */
$p = $project;
$items = $items ?? [];
$unbilledCents = $unbilledCents ?? 0;
$isInternal = ($p['project_type'] ?? 'customer') === 'internal';
$labels = ['active' => 'Aktiv', 'paused' => 'Pausiert', 'done' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'];
?>
<div class="toolbar">
    <a href="/projekte" class="btn btn-secondary btn-sm">← Zurück</a>
    <div style="display:flex; gap:10px;">
        <a href="/projekte/<?= (int) $p['id'] ?>/bearbeiten" class="btn btn-sm">Bearbeiten</a>
        <form method="post" action="/projekte/<?= (int) $p['id'] ?>/loeschen" data-confirm="Projekt wirklich löschen?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
        </form>
    </div>
</div>

<div class="panel">
    <dl class="detail">
        <dt>Projekt</dt><dd><strong><?= e($p['name']) ?></strong></dd>
        <?php if (($p['project_type'] ?? 'customer') === 'internal'): ?>
            <dt>Typ</dt><dd><span class="badge badge-business">Internes Projekt</span></dd>
        <?php else: ?>
            <dt>Kunde</dt><dd><a href="/kunden/<?= (int) $p['customer_id'] ?>"><?= e($p['company_name'] ?: $p['contact_name']) ?></a></dd>
        <?php endif; ?>
        <dt>Status</dt><dd><?= e($labels[$p['status']] ?? $p['status']) ?></dd>
        <dt>Stundensatz</dt><dd><?= money((int) $p['hourly_rate_cents']) ?></dd>
        <dt>Zeitraum</dt><dd><?= dt($p['start_date']) ?: '–' ?> – <?= dt($p['end_date']) ?: 'offen' ?></dd>
        <?php if (!empty($p['description'])): ?>
            <dt>Beschreibung</dt><dd><?= nl2br(e($p['description'])) ?></dd>
        <?php endif; ?>
    </dl>
</div>

<div class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0;">Abrechenbare Leistungen</h2>
        <?php if (!$isInternal && $unbilledCents > 0): ?>
            <div style="display:flex; gap:8px;">
                <form method="post" action="/projekte/<?= (int) $p['id'] ?>/angebot">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary btn-sm">→ Angebot aus offenen Leistungen</button>
                </form>
                <form method="post" action="/projekte/<?= (int) $p['id'] ?>/rechnung">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm">→ Rechnung aus offenen Leistungen</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($items === []): ?>
        <p class="muted">Noch keine Leistungen erfasst.</p>
    <?php else: ?>
        <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border); margin:12px 0;">
            <table>
                <thead><tr><th>Datum</th><th>Leistung</th><th class="num">Menge</th><th class="num">Einzelpreis</th><th class="num">Summe</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <?php $line = (int) round((float) $it['quantity'] * (int) $it['unit_price_cents']); ?>
                    <tr>
                        <td><?= dt($it['item_date']) ?></td>
                        <td><?= e($it['description']) ?></td>
                        <td class="num"><?= e(rtrim(rtrim(number_format((float) $it['quantity'], 2, ',', '.'), '0'), ',')) ?> <?= e($it['unit']) ?></td>
                        <td class="num"><?= money((int) $it['unit_price_cents']) ?></td>
                        <td class="num"><?= money($line) ?></td>
                        <td>
                            <?php if (!empty($it['billed_doc_id'])): ?>
                                <a href="/<?= $it['billed_doc_type'] === 'invoice' ? 'rechnungen' : 'angebote' ?>/<?= (int) $it['billed_doc_id'] ?>">
                                    <span class="badge badge-paid">abgerechnet</span>
                                </a>
                            <?php else: ?>
                                <span class="badge badge-draft">offen</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right">
                            <?php if (empty($it['billed_doc_id'])): ?>
                                <form method="post" action="/projekte/<?= (int) $p['id'] ?>/leistungen/<?= (int) $it['id'] ?>/loeschen" data-confirm="Leistung löschen?" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-secondary btn-sm">✕</button>
                                </form>
                            <?php else: ?><span class="muted">🔒</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" style="text-align:right; background:#fafbfc;">Offen (noch nicht abgerechnet)</th>
                        <th class="num" style="background:#fafbfc;"><?= money($unbilledCents) ?></th>
                        <th colspan="2" style="background:#fafbfc;"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($isInternal): ?>
        <p class="help">Internes Projekt ohne Kunden – Leistungen lassen sich erfassen, aber nicht abrechnen.</p>
    <?php endif; ?>

    <h3 style="font-size:14px; margin:14px 0 8px;">Leistung erfassen</h3>
    <form method="post" action="/projekte/<?= (int) $p['id'] ?>/leistungen">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="item_date">Datum</label>
                <input type="date" id="item_date" name="item_date" value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="field full">
                <label for="description">Beschreibung</label>
                <input type="text" id="description" name="description" placeholder="z.B. Entwicklung, Beratung…" required>
            </div>
            <div class="field">
                <label for="quantity">Menge</label>
                <input type="text" id="quantity" name="quantity" value="1" inputmode="decimal">
            </div>
            <div class="field">
                <label for="unit">Einheit</label>
                <input type="text" id="unit" name="unit" value="Std">
            </div>
            <div class="field">
                <label for="unit_price">Einzelpreis (€)</label>
                <input type="text" id="unit_price" name="unit_price" inputmode="decimal" placeholder="<?= e(amount((int) $p['hourly_rate_cents'])) ?> (Stundensatz)">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-secondary">+ Leistung hinzufügen</button>
        </div>
    </form>
</div>
