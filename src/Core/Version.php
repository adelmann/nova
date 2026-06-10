<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Aktuelle Programmversion. Liegt bewusst im Code (nicht in config.php), damit
 * sie beim Update mit den Programmdateien aktualisiert wird.
 */
final class Version
{
    public const CURRENT = '0.9.15';
}
