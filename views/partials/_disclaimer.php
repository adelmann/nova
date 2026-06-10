<?php
/**
 * Zentraler Haftungsausschluss. Wird bei Installation und Updates angezeigt.
 * Optional kompakt über $compact = true.
 *
 * @var bool $compact
 */
$compact = $compact ?? false;
?>
<?php if ($compact): ?>
    <p class="help" style="margin:0">
        Nutzung auf eigenes Risiko – Nova wird ohne Gewährleistung („wie besehen") bereitgestellt.
        Vor Updates wird automatisch ein Backup erstellt; für eigene Sicherungen bist du selbst verantwortlich.
        Keine Steuer- oder Rechtsberatung.
    </p>
<?php else: ?>
    <div class="disclaimer">
        <strong>Haftungsausschluss &amp; Gewährleistung</strong>
        <p>
            Nova wird „wie besehen" („as is") ohne jegliche ausdrückliche oder stillschweigende
            Gewährleistung bereitgestellt – insbesondere ohne Gewähr für Eignung für einen bestimmten
            Zweck, Fehlerfreiheit oder ununterbrochene Verfügbarkeit. Installation und Nutzung
            erfolgen auf <strong>eigenes Risiko</strong>.
        </p>
        <p>
            Soweit gesetzlich zulässig wird keine Haftung für unmittelbare oder mittelbare Schäden,
            Datenverlust, Ausfallzeiten oder steuerliche bzw. rechtliche Folgen übernommen. Du bist
            selbst für regelmäßige Datensicherungen und die Richtigkeit deiner steuerlichen Angaben
            verantwortlich. Nova ersetzt <strong>keine Steuer- oder Rechtsberatung</strong>.
        </p>
    </div>
<?php endif; ?>
