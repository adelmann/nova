<?php
/** @var string $active */
$active = $active ?? '';
$tabs = [
    'company'   => ['/einstellungen', 'Unternehmen'],
    'invoicing' => ['/einstellungen/rechnungen', 'Rechnungen & Steuer'],
    'email'     => ['/einstellungen/email', 'E-Mail'],
    'payments'  => ['/einstellungen/zahlung', 'Online-Zahlung'],
    'backup'    => ['/einstellungen/datensicherung', 'Datensicherung'],
    'system'    => ['/einstellungen/system', 'System'],
];
?>
<div class="subnav">
    <?php foreach ($tabs as $key => [$url, $label]): ?>
        <a href="<?= e($url) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
