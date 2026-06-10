<?php
/** @var array<int,array<string,mixed>> $users */
/** @var array<string,string> $roles */
?>
<div class="toolbar">
    <h2 style="margin:0; font-size:16px;">Benutzer &amp; Rollen</h2>
    <a href="/benutzer/neu" class="btn">+ Neuer Benutzer</a>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>2FA</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr onclick="location.href='/benutzer/<?= (int) $u['id'] ?>/bearbeiten'" style="cursor:pointer">
                <td><strong><?= e($u['name']) ?></strong></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($roles[$u['role']] ?? $u['role']) ?></td>
                <td><?= (int) ($u['totp_enabled'] ?? 0) === 1 ? '🔒' : '<span class="muted">–</span>' ?></td>
                <td>
                    <?php if ((int) $u['is_active'] === 1): ?>
                        <span class="badge badge-paid">aktiv</span>
                    <?php else: ?>
                        <span class="badge badge-draft">deaktiviert</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
