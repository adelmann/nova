<?php
/** @var bool $enabled */
/** @var string|null $answer */
/** @var string $prompt */
$error = $error ?? null;
?>
<?php if (!$enabled): ?>
    <div class="panel">
        <h2>KI-Assistent ist nicht aktiviert</h2>
        <p>Der Assistent nutzt die Anthropic-API (Modell <code>claude-opus-4-8</code>). Setze dafür auf dem Server die Umgebungsvariable <code>ANTHROPIC_API_KEY</code> (optional <code>NOVA_AI_MODEL</code>).</p>
        <p class="help">Ohne Schlüssel bleibt die Funktion deaktiviert – alle übrigen Module funktionieren unabhängig davon.</p>
    </div>
<?php else: ?>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <div class="panel">
        <h2>Frag den Assistenten</h2>
        <form method="post" action="/assistent">
            <?= csrf_field() ?>
            <div class="field">
                <textarea name="prompt" rows="3" placeholder="z.B. Wie lief mein Monat? Oder: Erstelle einen Vorschlag für eine Rechnung über 8 Std IT-Beratung à 95 €."><?= e($prompt) ?></textarea>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.querySelector('textarea[name=prompt]').value='Wie lief mein Monat geschäftlich? Fasse kurz zusammen.'">Wie lief mein Monat?</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.querySelector('textarea[name=prompt]').value='Fasse meine offenen Rechnungen zusammen und was ich tun sollte.'">Offene Rechnungen</button>
            </div>
            <button type="submit" class="btn">Senden</button>
        </form>
    </div>

    <?php if ($answer !== null): ?>
        <div class="panel">
            <h2>Antwort</h2>
            <div style="white-space:pre-wrap; line-height:1.6;"><?= e($answer) ?></div>
        </div>
    <?php endif; ?>
    <p class="help">Hinweis: Der Assistent ersetzt keine Steuerberatung.</p>
<?php endif; ?>
