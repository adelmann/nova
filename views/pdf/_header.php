<?php
/**
 * Gemeinsamer PDF-Briefkopf (Logo + Firmenname + Absenderblock).
 * Wird von Rechnung, Angebot, Mahnung und EÜR identisch verwendet.
 *
 * @var array<string,mixed> $settings
 */
$s = $settings;
// Logo als Base64-Data-URI einbetten – unabhängig von Dateipfad/chroot/Remote,
// damit es in JEDER PDF zuverlässig erscheint (sonst lässt Dompdf es teils weg).
$logoSrc = '';
if (!empty($s['logo_path'])) {
    $abs = ($GLOBALS['nova_config']['paths']['logos'] ?? '') . '/' . $s['logo_path'];
    if (is_file($abs)) {
        $data = @file_get_contents($abs);
        if ($data !== false && $data !== '') {
            $info = @getimagesizefromstring($data);
            $mime = is_array($info) && !empty($info['mime']) ? $info['mime'] : 'image/png';
            $logoSrc = 'data:' . $mime . ';base64,' . base64_encode($data);
        }
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
