<?php
/**
 * Gemeinsamer PDF-Briefkopf (Logo + Firmenname + Absenderblock).
 * Wird von Rechnung, Angebot, Mahnung und EÜR identisch verwendet.
 *
 * @var array<string,mixed> $settings
 */
$s = $settings;
// Logo als Base64-Data-URI einbetten (mit Alpha-Flattening gegen Weiß), damit es
// in JEDER PDF und in jedem Viewer – auch mobil – zuverlässig erscheint.
$logoSrc = '';
if (!empty($s['logo_path'])) {
    $abs = ($GLOBALS['nova_config']['paths']['logos'] ?? '') . '/' . $s['logo_path'];
    if (is_file($abs)) {
        $logoSrc = \Nova\Services\PdfService::logoDataUri($abs);
    }
}
?>
<table class="head">
    <tr>
        <?php if ($logoSrc !== ''): ?>
            <td style="vertical-align:middle; width:1px; white-space:nowrap; padding-right:12px;"><img class="logo" src="<?= $logoSrc ?>"></td>
        <?php endif; ?>
        <td style="vertical-align:middle;"><span class="company-name"><?= e($s['company_name']) ?></span></td>
        <td class="sender">
            <?php if ($s['owner_name']): ?><?= e($s['owner_name']) ?><br><?php endif; ?>
            <?= e($s['address_line1']) ?><br>
            <?= e(trim($s['zip'] . ' ' . $s['city'])) ?>
        </td>
    </tr>
</table>
