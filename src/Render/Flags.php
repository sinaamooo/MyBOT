<?php
declare(strict_types=1);

namespace Nikto\Render;

final class Flags
{
    public static function draw(Canvas $c, string $code, float $x, float $y, float $w, float $h): void
    {
        $code = strtoupper(trim($code));
        $method = 'flag' . $code;

        $c->rect($x, $y, $w, $h, '#FFFFFF');
        if (method_exists(self::class, $method)) {
            self::$method($c, $x, $y, $w, $h);
        } else {
            self::flagGeneric($c, $code, $x, $y, $w, $h);
        }
        $c->strokeRoundRect($x, $y, $w, $h, 2, '#8B93A7', 1.1, 0.55);
    }

    private static function bandsH(Canvas $c, float $x, float $y, float $w, float $h, array $colors): void
    {
        $n = count($colors);
        $bh = $h / $n;
        foreach ($colors as $i => $color) {
            $c->rect($x, $y + $i * $bh, $w, $bh + 0.5, $color);
        }
    }

    private static function bandsV(Canvas $c, float $x, float $y, float $w, float $h, array $colors): void
    {
        $n = count($colors);
        $bw = $w / $n;
        foreach ($colors as $i => $color) {
            $c->rect($x + $i * $bw, $y, $bw + 0.5, $h, $color);
        }
    }

    private static function nordicCross(Canvas $c, float $x, float $y, float $w, float $h, string $bg, string $cross, string $inner = ''): void
    {
        $c->rect($x, $y, $w, $h, $bg);
        $t = $h * 0.22;
        $cxPos = $x + $w * 0.36;
        if ($inner !== '') {
            $c->rect($cxPos - $t * 0.85, $y, $t * 1.7, $h, $inner);
            $c->rect($x, $y + $h / 2 - $t * 0.85, $w, $t * 1.7, $inner);
        }
        $c->rect($cxPos - $t / 2, $y, $t, $h, $cross);
        $c->rect($x, $y + $h / 2 - $t / 2, $w, $t, $cross);
    }

    private static function stars(Canvas $c, float $cx, float $cy, float $radius, int $count, string $color, float $dot): void
    {
        for ($i = 0; $i < $count; $i++) {
            $ang = deg2rad(-90 + 360 * $i / $count);
            $c->circle($cx + cos($ang) * $radius, $cy + sin($ang) * $radius, $dot, $color);
        }
    }

    private static function flagUS(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $stripe = $h / 13;
        for ($i = 0; $i < 13; $i++) {
            $c->rect($x, $y + $i * $stripe, $w, $stripe + 0.4, $i % 2 === 0 ? '#B22234' : '#FFFFFF');
        }
        $cw = $w * 0.42;
        $ch = $stripe * 7;
        $c->rect($x, $y, $cw, $ch, '#3C3B6E');
        for ($r = 0; $r < 4; $r++) {
            for ($col = 0; $col < 5; $col++) {
                $c->circle(
                    $x + $cw * (0.12 + $col * 0.19),
                    $y + $ch * (0.16 + $r * 0.23),
                    max(0.6, $w * 0.018),
                    '#FFFFFF'
                );
            }
        }
    }

    private static function flagEU(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#003399');
        self::stars($c, $x + $w / 2, $y + $h / 2, min($w, $h) * 0.29, 12, '#FFCC00', max(0.8, $w * 0.026));
    }

    private static function flagGB(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#012169');
        $c->line($x, $y, $x + $w, $y + $h, '#FFFFFF', $h * 0.22);
        $c->line($x + $w, $y, $x, $y + $h, '#FFFFFF', $h * 0.22);
        $c->line($x, $y, $x + $w, $y + $h, '#C8102E', $h * 0.10);
        $c->line($x + $w, $y, $x, $y + $h, '#C8102E', $h * 0.10);
        $c->rect($x + $w * 0.40, $y, $w * 0.20, $h, '#FFFFFF');
        $c->rect($x, $y + $h * 0.35, $w, $h * 0.30, '#FFFFFF');
        $c->rect($x + $w * 0.445, $y, $w * 0.11, $h, '#C8102E');
        $c->rect($x, $y + $h * 0.41, $w, $h * 0.18, '#C8102E');
    }

    private static function flagJP(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#FFFFFF');
        $c->circle($x + $w / 2, $y + $h / 2, min($w, $h) * 0.30, '#BC002D');
    }

    private static function flagCA(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#FFFFFF');
        $c->rect($x, $y, $w * 0.25, $h, '#D80621');
        $c->rect($x + $w * 0.75, $y, $w * 0.25, $h, '#D80621');
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;
        $s = min($w, $h) * 0.34;
        $c->polygon([
            [$cx, $cy - $s], [$cx + $s * 0.28, $cy - $s * 0.30], [$cx + $s * 0.72, $cy - $s * 0.42],
            [$cx + $s * 0.46, $cy + $s * 0.06], [$cx + $s * 0.86, $cy + $s * 0.26], [$cx + $s * 0.24, $cy + $s * 0.38],
            [$cx + $s * 0.16, $cy + $s * 0.92], [$cx - $s * 0.16, $cy + $s * 0.92], [$cx - $s * 0.24, $cy + $s * 0.38],
            [$cx - $s * 0.86, $cy + $s * 0.26], [$cx - $s * 0.46, $cy + $s * 0.06], [$cx - $s * 0.72, $cy - $s * 0.42],
            [$cx - $s * 0.28, $cy - $s * 0.30],
        ], '#D80621');
    }

    private static function flagCH(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#D52B1E');
        $t = min($w, $h) * 0.20;
        $c->rect($x + $w / 2 - $t / 2, $y + $h * 0.22, $t, $h * 0.56, '#FFFFFF');
        $c->rect($x + $w * 0.22, $y + $h / 2 - $t / 2, $w * 0.56, $t, '#FFFFFF');
    }

    private static function flagCN(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#DE2910');
        $c->circle($x + $w * 0.18, $y + $h * 0.28, max(1.2, $w * 0.055), '#FFDE00');
        foreach ([[0.34, 0.14], [0.42, 0.26], [0.40, 0.44], [0.30, 0.54]] as [$sx, $sy]) {
            $c->circle($x + $w * $sx, $y + $h * $sy, max(0.7, $w * 0.022), '#FFDE00');
        }
    }

    private static function flagDE(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::bandsH($c, $x, $y, $w, $h, ['#000000', '#DD0000', '#FFCE00']);
    }

    private static function flagFR(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::bandsV($c, $x, $y, $w, $h, ['#002395', '#FFFFFF', '#ED2939']);
    }

    private static function flagIT(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::bandsV($c, $x, $y, $w, $h, ['#008C45', '#F4F5F0', '#CD212A']);
    }

    private static function flagES(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#AA151B');
        $c->rect($x, $y + $h * 0.25, $w, $h * 0.5, '#F1BF00');
    }

    private static function flagAU(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#00008B');
        self::flagGB($c, $x, $y, $w * 0.5, $h * 0.5);
        $c->circle($x + $w * 0.28, $y + $h * 0.80, max(0.9, $w * 0.035), '#FFFFFF');
        foreach ([[0.70, 0.22], [0.78, 0.46], [0.70, 0.70], [0.62, 0.46], [0.86, 0.62]] as [$sx, $sy]) {
            $c->circle($x + $w * $sx, $y + $h * $sy, max(0.7, $w * 0.024), '#FFFFFF');
        }
    }

    private static function flagNZ(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#00247D');
        self::flagGB($c, $x, $y, $w * 0.5, $h * 0.5);
        foreach ([[0.72, 0.26], [0.80, 0.48], [0.72, 0.72], [0.64, 0.48]] as [$sx, $sy]) {
            $c->circle($x + $w * $sx, $y + $h * $sy, max(0.9, $w * 0.030), '#CC142B');
        }
    }

    private static function flagKR(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#FFFFFF');
        $r = min($w, $h) * 0.22;
        $c->circle($x + $w / 2, $y + $h / 2, $r, '#CD2E3A');
        $c->ring($x + $w / 2, $y + $h / 2 + $r * 0.5, $r * 0.5, $r, '#0047A0', 0, 180, 1.0);
    }

    private static function flagIN(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::bandsH($c, $x, $y, $w, $h, ['#FF9933', '#FFFFFF', '#138808']);
        $c->circle($x + $w / 2, $y + $h / 2, $h * 0.12, '#000080');
    }

    private static function flagBR(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#009C3B');
        $c->polygon([
            [$x + $w / 2, $y + $h * 0.12], [$x + $w * 0.88, $y + $h / 2],
            [$x + $w / 2, $y + $h * 0.88], [$x + $w * 0.12, $y + $h / 2],
        ], '#FFDF00');
        $c->circle($x + $w / 2, $y + $h / 2, $h * 0.17, '#002776');
    }

    private static function flagMX(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::bandsV($c, $x, $y, $w, $h, ['#006847', '#FFFFFF', '#CE1126']);
        $c->circle($x + $w / 2, $y + $h / 2, $h * 0.14, '#8B5E3C');
    }

    private static function flagZA(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::bandsH($c, $x, $y, $w, $h, ['#E03C31', '#FFFFFF', '#007A4D']);
        $c->polygon([[$x, $y], [$x + $w * 0.42, $y + $h / 2], [$x, $y + $h]], '#001489');
        $c->polygon([[$x, $y + $h * 0.16], [$x + $w * 0.30, $y + $h / 2], [$x, $y + $h * 0.84]], '#FFB612');
    }

    private static function flagRU(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::bandsH($c, $x, $y, $w, $h, ['#FFFFFF', '#0039A6', '#D52B1E']);
    }

    private static function flagTR(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#E30A17');
        $r = $h * 0.26;
        $c->circle($x + $w * 0.38, $y + $h / 2, $r, '#FFFFFF');
        $c->circle($x + $w * 0.45, $y + $h / 2, $r * 0.82, '#E30A17');
        $c->circle($x + $w * 0.60, $y + $h / 2, $r * 0.34, '#FFFFFF');
    }

    private static function flagSE(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::nordicCross($c, $x, $y, $w, $h, '#006AA7', '#FECC00');
    }

    private static function flagNO(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::nordicCross($c, $x, $y, $w, $h, '#BA0C2F', '#00205B', '#FFFFFF');
    }

    private static function flagDK(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::nordicCross($c, $x, $y, $w, $h, '#C8102E', '#FFFFFF');
    }

    private static function flagPL(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        self::bandsH($c, $x, $y, $w, $h, ['#FFFFFF', '#DC143C']);
    }

    private static function flagIL(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#FFFFFF');
        $c->rect($x, $y + $h * 0.12, $w, $h * 0.13, '#0038B8');
        $c->rect($x, $y + $h * 0.75, $w, $h * 0.13, '#0038B8');
        $s = $h * 0.22;
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;
        $c->polygon([[$cx, $cy - $s], [$cx + $s * 0.87, $cy + $s * 0.5], [$cx - $s * 0.87, $cy + $s * 0.5]], '#0038B8');
        $c->polygon([[$cx, $cy + $s], [$cx + $s * 0.87, $cy - $s * 0.5], [$cx - $s * 0.87, $cy - $s * 0.5]], '#0038B8');
    }

    private static function flagWW(Canvas $c, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#2F3B54');
        $c->ring($x + $w / 2, $y + $h / 2, min($w, $h) * 0.28, 1.6, '#9FB3D9', 0, 360, 0.9);
        $c->line($x + $w * 0.20, $y + $h / 2, $x + $w * 0.80, $y + $h / 2, '#9FB3D9', 1.4, 0.9);
    }

    private static function flagGeneric(Canvas $c, string $code, float $x, float $y, float $w, float $h): void
    {
        $c->rect($x, $y, $w, $h, '#E7EBF3');
        $c->text(substr($code, 0, 2), $x + $w / 2, $y + $h / 2 - $h * 0.32, $h * 0.55, '#4A5675', Canvas::W_BOLD, 'center');
    }
}
