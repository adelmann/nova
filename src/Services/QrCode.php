<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Minimaler, eigenständiger QR-Code-Generator (Byte-Modus, Fehlerkorrektur-
 * Level L, Versionen 1–10) – ohne Fremdbibliothek und ohne externen Dienst.
 * Ausgabe als Inline-SVG. Reicht für otpauth://-URIs zur 2FA-Einrichtung.
 */
final class QrCode
{
    /** EC-Level L je Version: [EC-Codewörter/Block, [[Blöcke, Daten-CW/Block], …]]. */
    private const EC = [
        1  => [7,  [[1, 19]]],
        2  => [10, [[1, 34]]],
        3  => [15, [[1, 55]]],
        4  => [20, [[1, 80]]],
        5  => [26, [[1, 108]]],
        6  => [18, [[2, 68]]],
        7  => [20, [[2, 78]]],
        8  => [24, [[2, 97]]],
        9  => [30, [[2, 116]]],
        10 => [18, [[2, 68], [2, 69]]],
    ];

    /** Ausrichtungs-Muster-Zentren je Version. */
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** Restbits je Version. */
    private const REMAINDER = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0];

    /** @var array<int,int> */
    private static array $expTable = [];
    /** @var array<int,int> */
    private static array $logTable = [];

    public static function svg(string $text, int $scale = 5, int $margin = 4): string
    {
        self::initGf();
        [$matrix, $size] = self::build($text);

        $dim = ($size + 2 * $margin) * $scale;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" '
            . 'viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges" role="img" aria-label="QR-Code">';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
        $svg .= '<path fill="#000000" d="';
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix[$y][$x] === 1) {
                    $px = ($x + $margin) * $scale;
                    $py = ($y + $margin) * $scale;
                    $svg .= 'M' . $px . ',' . $py . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
                }
            }
        }
        $svg .= '"/></svg>';
        return $svg;
    }

    /** @return array{0:array<int,array<int,int>>,1:int} */
    private static function build(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $len   = count($bytes);

        $version = self::pickVersion($len);
        [$ecPerBlock, $groups] = self::EC[$version];
        $totalDataCw = 0;
        foreach ($groups as [$nb, $dpb]) {
            $totalDataCw += $nb * $dpb;
        }

        // --- Bitstrom: Modus (Byte=0100) + Längenfeld + Daten ---
        $countBits = $version < 10 ? 8 : 16;
        $bits = '0100' . str_pad(decbin($len), $countBits, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        // Terminator (max 4 Bit) und Auffüllen auf Byte-Grenze.
        $capacityBits = $totalDataCw * 8;
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }
        // Pad-Bytes.
        $pad = ['11101100', '00010001'];
        $i = 0;
        while (strlen($bits) < $capacityBits) {
            $bits .= $pad[$i % 2];
            $i++;
        }

        // In Daten-Codewörter zerlegen.
        $dataCw = [];
        for ($j = 0; $j < $capacityBits; $j += 8) {
            $dataCw[] = bindec(substr($bits, $j, 8));
        }

        // --- In Blöcke aufteilen, EC berechnen, verschachteln ---
        $blocksData = [];
        $blocksEc   = [];
        $pos = 0;
        foreach ($groups as [$nb, $dpb]) {
            for ($b = 0; $b < $nb; $b++) {
                $block = array_slice($dataCw, $pos, $dpb);
                $pos += $dpb;
                $blocksData[] = $block;
                $blocksEc[]   = self::rsEncode($block, $ecPerBlock);
            }
        }

        $final = [];
        $maxData = max(array_map('count', $blocksData));
        for ($c = 0; $c < $maxData; $c++) {
            foreach ($blocksData as $blk) {
                if (isset($blk[$c])) {
                    $final[] = $blk[$c];
                }
            }
        }
        for ($c = 0; $c < $ecPerBlock; $c++) {
            foreach ($blocksEc as $blk) {
                $final[] = $blk[$c];
            }
        }

        // Codewörter -> finaler Bitstrom inkl. Restbits.
        $finalBits = '';
        foreach ($final as $cw) {
            $finalBits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }
        $finalBits .= str_repeat('0', self::REMAINDER[$version]);

        // --- Matrix aufbauen ---
        $size = 17 + 4 * $version;
        return [self::placeAndMask($finalBits, $version, $size), $size];
    }

    private static function pickVersion(int $len): int
    {
        foreach (array_keys(self::EC) as $v) {
            [, $groups] = self::EC[$v];
            $dataCw = 0;
            foreach ($groups as [$nb, $dpb]) {
                $dataCw += $nb * $dpb;
            }
            $countBits = $v < 10 ? 8 : 16;
            $needed    = 4 + $countBits + 8 * $len;
            if ($dataCw * 8 >= $needed) {
                return $v;
            }
        }
        throw new \RuntimeException('Daten zu lang für QR-Code (max. Version 10).');
    }

    // ---- Galois-Feld GF(256) ----------------------------------------------
    private static function initGf(): void
    {
        if (self::$expTable !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$expTable[$i] = $x;
            self::$logTable[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$expTable[$i] = self::$expTable[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    /**
     * @param array<int,int> $data
     * @return array<int,int>
     */
    private static function rsEncode(array $data, int $ecLen): array
    {
        // Generatorpolynom.
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j]     ^= self::gfMul($coef, 1);
                $next[$j + 1] ^= self::gfMul($coef, self::$expTable[$i]);
            }
            $gen = $next;
        }

        $rem = array_merge($data, array_fill(0, $ecLen, 0));
        for ($i = 0; $i < count($data); $i++) {
            $factor = $rem[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($gen as $j => $coef) {
                $rem[$i + $j] ^= self::gfMul($coef, $factor);
            }
        }
        return array_slice($rem, count($data));
    }

    // ---- Matrix, Funktionsmuster, Maskierung -------------------------------
    /**
     * @return array<int,array<int,int>>
     */
    private static function placeAndMask(string $bits, int $version, int $size): array
    {
        $m   = array_fill(0, $size, array_fill(0, $size, 0)); // Module
        $res = array_fill(0, $size, array_fill(0, $size, 0)); // reserviert/Funktion?

        $set = static function (int $y, int $x, int $val) use (&$m, &$res): void {
            $m[$y][$x]   = $val;
            $res[$y][$x] = 1;
        };

        // Finder + Separatoren.
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$fy, $fx]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $yy = $fy + $y;
                    $xx = $fx + $x;
                    if ($yy < 0 || $yy >= $size || $xx < 0 || $xx >= $size) {
                        continue;
                    }
                    $inFinder = ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6))
                        || ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6))
                        || ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4);
                    $set($yy, $xx, $inFinder ? 1 : 0);
                }
            }
        }

        // Timing.
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            $set(6, $i, $bit);
            $set($i, 6, $bit);
        }

        // Ausrichtungsmuster.
        $centers = self::ALIGN[$version];
        foreach ($centers as $cy) {
            foreach ($centers as $cx) {
                // nicht über Findern.
                if (($cy <= 7 && $cx <= 7) || ($cy <= 7 && $cx >= $size - 8) || ($cy >= $size - 8 && $cx <= 7)) {
                    continue;
                }
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $ring = max(abs($x), abs($y));
                        $set($cy + $y, $cx + $x, ($ring === 1) ? 0 : 1);
                    }
                }
            }
        }

        // Dunkles Modul.
        $set($size - 8, 8, 1);

        // Format-Bereiche reservieren.
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $res[8][$i] = 1;
                $res[$i][8] = 1;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $res[8][$size - 1 - $i] = 1;
            $res[$size - 1 - $i][8] = 1;
        }

        // Versionsinfo-Bereiche reservieren (v >= 7).
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $res[$i][$size - 11 + $j] = 1;
                    $res[$size - 11 + $j][$i] = 1;
                }
            }
        }

        // Datenbits platzieren (Zickzack von unten rechts).
        $dirUp = true;
        $bitIdx = 0;
        $bitLen = strlen($bits);
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col = 5;
            }
            for ($r = 0; $r < $size; $r++) {
                $row = $dirUp ? ($size - 1 - $r) : $r;
                for ($c = 0; $c < 2; $c++) {
                    $x = $col - $c;
                    if ($res[$row][$x] === 1) {
                        continue;
                    }
                    $bit = ($bitIdx < $bitLen) ? (int) $bits[$bitIdx] : 0;
                    $bitIdx++;
                    $m[$row][$x] = $bit;
                }
            }
            $dirUp = !$dirUp;
        }

        // Beste Maske wählen.
        $best = null;
        $bestScore = PHP_INT_MAX;
        $bestMask = 0;
        for ($mask = 0; $mask < 8; $mask++) {
            $cand = self::applyMask($m, $res, $size, $mask);
            self::placeFormat($cand, $size, $mask);
            if ($version >= 7) {
                self::placeVersion($cand, $size, $version);
            }
            $score = self::penalty($cand, $size);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $cand;
                $bestMask = $mask;
            }
        }
        return $best;
    }

    /**
     * @param array<int,array<int,int>> $m
     * @param array<int,array<int,int>> $res
     * @return array<int,array<int,int>>
     */
    private static function applyMask(array $m, array $res, int $size, int $mask): array
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($res[$y][$x] === 1) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => (($x * $y) % 2 + ($x * $y) % 3) === 0,
                    6 => ((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0,
                    7 => ((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0,
                    default => false,
                };
                if ($flip) {
                    $m[$y][$x] ^= 1;
                }
            }
        }
        return $m;
    }

    /** @param array<int,array<int,int>> $m */
    private static function placeFormat(array &$m, int $size, int $mask): void
    {
        // EC-Level L = 01; 5-Bit-Daten = (01 << 3) | mask.
        $data = (0b01 << 3) | $mask;
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0b10100110111);
        }
        $bitsVal = (($data << 10) | $rem) ^ 0b101010000010010;

        $bitsArr = [];
        for ($i = 14; $i >= 0; $i--) {
            $bitsArr[] = ($bitsVal >> $i) & 1;
        }

        // Erste Kopie um oben-links.
        $coords1 = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
        foreach ($coords1 as $i => [$y, $x]) {
            $m[$y][$x] = $bitsArr[$i];
        }
        // Zweite Kopie (gespiegelt).
        $coords2 = [];
        for ($i = 0; $i < 7; $i++) {
            $coords2[] = [$size - 1 - $i, 8];
        }
        for ($i = 7; $i < 15; $i++) {
            $coords2[] = [8, $size - 15 + $i];
        }
        foreach ($coords2 as $i => [$y, $x]) {
            $m[$y][$x] = $bitsArr[$i];
        }
    }

    /** @param array<int,array<int,int>> $m */
    private static function placeVersion(array &$m, int $size, int $version): void
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0b1111100100101);
        }
        $bitsVal = ($version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = ($bitsVal >> $i) & 1;
            $a = intdiv($i, 3);
            $b = $i % 3;
            $m[$a][$size - 11 + $b] = $bit;
            $m[$size - 11 + $b][$a] = $bit;
        }
    }

    /** @param array<int,array<int,int>> $m */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;

        // Regel 1: ≥5 gleiche Module in Reihe (Zeilen & Spalten).
        for ($y = 0; $y < $size; $y++) {
            $runC = 1; $runR = 1;
            for ($x = 1; $x < $size; $x++) {
                $runC = ($m[$y][$x] === $m[$y][$x - 1]) ? $runC + 1 : 1;
                if ($runC === 5) { $score += 3; } elseif ($runC > 5) { $score += 1; }
                $runR = ($m[$x][$y] === $m[$x - 1][$y]) ? $runR + 1 : 1;
                if ($runR === 5) { $score += 3; } elseif ($runR > 5) { $score += 1; }
            }
        }

        // Regel 2: 2x2-Blöcke gleicher Farbe.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $v = $m[$y][$x];
                if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        // Regel 3: Finder-ähnliche Muster 1:1:3:1:1.
        $pat1 = [1,0,1,1,1,0,1,0,0,0,0];
        $pat2 = [0,0,0,0,1,0,1,1,1,0,1];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x <= $size - 11; $x++) {
                $rowSlice = []; $colSlice = [];
                for ($k = 0; $k < 11; $k++) {
                    $rowSlice[] = $m[$y][$x + $k];
                    $colSlice[] = $m[$x + $k][$y];
                }
                if ($rowSlice === $pat1 || $rowSlice === $pat2) { $score += 40; }
                if ($colSlice === $pat1 || $colSlice === $pat2) { $score += 40; }
            }
        }

        // Regel 4: Verhältnis dunkler Module.
        $dark = 0;
        foreach ($m as $row) {
            $dark += array_sum($row);
        }
        $percent = $dark * 100 / ($size * $size);
        $k = (int) (abs($percent - 50) / 5);
        $score += $k * 10;

        return $score;
    }
}
