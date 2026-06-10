<?php
/** @var int $year */
/** @var array{income:int,expense:int,profit:int} $summary */
/** @var array<int,array{income:int,expense:int}> $months */
/** @var int $openInvoices */
/** @var int $openInvoicesSum */
/** @var array<int,array<string,mixed>> $overdue */
/** @var int $openExpenses */
/** @var int $missingReceipts */
/** @var int $customerCount */
/** @var bool $kuActive */
/** @var int $kuLimit */
/** @var int $kuPercent */
$monthNames = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
$maxVal = 1;
foreach ($months as $v) {
    $maxVal = max($maxVal, $v['income'], $v['expense']);
}
$me = current_user();
$needs2fa = $me !== null && (int) ($me['totp_enabled'] ?? 0) !== 1;
?>
<?php if ($needs2fa): ?>
    <div class="flash flash-warn" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <span>🔒 <strong>Konto noch ungeschützt:</strong> Aktiviere die Zwei-Faktor-Authentifizierung für mehr Sicherheit.</span>
        <a href="/konto" class="btn btn-sm">2FA aktivieren</a>
    </div>
<?php endif; ?>
<div class="cards">
    <div class="card"><div class="label">Umsatz <?= $year ?></div><div class="value"><?= money($summary['income']) ?></div></div>
    <div class="card"><div class="label">Ausgaben <?= $year ?></div><div class="value"><?= money($summary['expense']) ?></div></div>
    <div class="card"><div class="label">Gewinn <?= $year ?></div><div class="value <?= $summary['profit'] < 0 ? 'neg' : 'pos' ?>"><?= money($summary['profit']) ?></div></div>
    <div class="card"><div class="label">Offene Rechnungen</div><div class="value"><?= $openInvoices ?></div><div class="sub"><?= money($openInvoicesSum) ?> ausstehend</div></div>
</div>

<div class="panel">
    <h2>Einnahmen &amp; Ausgaben <?= $year ?></h2>
    <div class="chart">
        <?php for ($m = 1; $m <= 12; $m++): $v = $months[$m]; ?>
            <div class="chart-col">
                <div class="bars">
                    <div class="bar bar-in" style="height:<?= (int) round($v['income'] / $maxVal * 100) ?>%" title="Einnahmen <?= money($v['income']) ?>"></div>
                    <div class="bar bar-out" style="height:<?= (int) round($v['expense'] / $maxVal * 100) ?>%" title="Ausgaben <?= money($v['expense']) ?>"></div>
                </div>
                <div class="chart-label"><?= $monthNames[$m] ?></div>
            </div>
        <?php endfor; ?>
    </div>
    <div class="legend"><span class="dot dot-in"></span> Einnahmen &nbsp; <span class="dot dot-out"></span> Ausgaben</div>
</div>

<?php if ($kuActive): ?>
<div class="panel">
    <h2>Kleinunternehmer-Umsatzgrenze (§ 19 UStG)</h2>
    <div class="progress"><div class="progress-bar <?= $kuPercent >= 90 ? 'danger' : '' ?>" style="width:<?= $kuPercent ?>%"></div></div>
    <p class="help"><?= money($summary['income']) ?> von <?= money($kuLimit) ?> (<?= $kuPercent ?> %).
        <?php if ($kuPercent >= 90): ?><strong style="color:var(--error)">Achtung: Grenze nahezu erreicht – Wechsel zur Regelbesteuerung könnte anstehen.</strong><?php endif; ?>
    </p>
</div>
<?php endif; ?>

<div class="panel">
    <h2>To-do</h2>
    <ul class="todo">
        <?php if ($overdue !== []): ?>
            <li>⚠️ <strong><?= count($overdue) ?> überfällige Rechnung(en):</strong>
                <?php foreach ($overdue as $o): ?>
                    <a href="/rechnungen/<?= (int) $o['id'] ?>"><?= e($o['number']) ?></a> (<?= money((int) $o['offen']) ?>, fällig <?= dt($o['due_date']) ?>)&nbsp;
                <?php endforeach; ?>
            </li>
        <?php endif; ?>
        <li><?= $openInvoices ?> offene Rechnung(en) – Zahlungseingang prüfen</li>
        <li><?= $openExpenses ?> unbezahlte Ausgabe(n)</li>
        <?php if ($missingReceipts > 0): ?>
            <li>📎 <strong><?= $missingReceipts ?></strong> bezahlte Ausgabe(n) ohne Beleg</li>
        <?php endif; ?>
        <li><?= $customerCount ?> Kunde(n) erfasst</li>
    </ul>
</div>
