<?php
/**
 * Gemeinsamer PDF-Briefkopf (Logo + Firmenname + Absenderblock).
 * Wird von Rechnung, Angebot, Mahnung und EÜR identisch verwendet.
 *
 * @var array<string,mixed> $settings
 */
$s = $settings;
$logoFile = '';
if (!empty($s['logo_path'])) {
    $abs = ($GLOBALS['nova_config']['paths']['logos'] ?? '') . '/' . $s['logo_path'];
    if (is_file($abs)) {
        $logoFile = $abs;
    }
}
?>
<table class="head">
    <tr>
        <?php if ($logoFile): ?>
            <td style="vertical-align:middle; width:1px; white-space:nowrap; padding-right:12px;"><img class="logo" src="<?= e($logoFile) ?>"></td>
        <?php endif; ?>
        <td style="vertical-align:middle;"><span class="company-name"><?= e($s['company_name']) ?></span></td>
        <td class="sender">
            <?php if ($s['owner_name']): ?><?= e($s['owner_name']) ?><br><?php endif; ?>
            <?= e($s['address_line1']) ?><br>
            <?= e(trim($s['zip'] . ' ' . $s['city'])) ?>
        </td>
    </tr>
</table>
