<?php

declare(strict_types=1);

namespace App\Render;

use App\Support\Fa;

/**
 * Renders the analysis chart image (PNG): a compact header and the annotated chart
 * (zones, order blocks, liquidity, structure breaks and the projected path).
 * All numbers and explanations go in the message caption.
 */
final class Card
{
    private const W = 1600;
    private const H = 1000;
    private const M = 32;

    private const BG_TOP = '#000000';
    private const BG_BOTTOM = '#000000';
    private const PANEL = '#000000';
    private const PANEL_2 = '#000000';
    private const STROKE = '#1C1C1C';
    private const CANDLE_UP = '#FFFFFF';
    private const CANDLE_DOWN = '#FF2D2D';
    private const OB = '#4FC3F7';         // sky blue
    private const PATH_UP = '#00E000';    // strong green
    private const PATH_DOWN = '#FF1A1A';  // strong red
    private const TEXT = '#EEF2FF';
    private const TEXT_2 = '#A3ADCC';
    private const MUTED = '#5E6889';
    private const GREEN = '#17C784';
    private const RED = '#F0464F';
    private const AMBER = '#F5B94A';
    private const CYAN = '#38C6E8';
    private const VIOLET = '#9B7BFF';

    private Canvas $c;
    private array $a;
    private string $accent;
    private string $side;
    private bool $withPath = true;

    public function __construct(private array $brand = [], private string $fontDir = '')
    {
        $this->accent = $brand['accent'] ?? '#7C5CFF';
    }

    /** @param bool $withPath false = no projected path (draft image for the AI review) */
    public function render(array $analysis, string $path, bool $withPath = true): string
    {
        $this->a = $analysis;
        $this->withPath = $withPath;
        $this->c = new Canvas(self::W, self::H, 2, $this->fontDir);
        $bias = $analysis['bias'];
        $this->side = $bias === 'neutral' ? self::AMBER : ($analysis['plan']['side'] === 'long' ? self::GREEN : self::RED);

        $this->background();
        $this->header(26);
        $this->chart(126, self::H - 126 - self::M);

        $this->c->save($path);
        return $path;
    }

    // ------------------------------------------------------------ sections

    private function background(): void
    {
        $c = $this->c;
        $c->roundRect(0, 0, self::W, self::H, 0, [self::BG_TOP, self::BG_BOTTOM, 1, 1]);
    }

    private function header(float $y): void
    {
        $c = $this->c;
        $a = $this->a;
        $M = self::M;

        // coin badge
        $hue = $this->coinColor($a['base']);
        $cx = $M + 38;
        $cy = $y + 46;
        $c->circle($cx, $cy, 40, $c->color(Canvas::mix($hue, '#000000', 0.25)));
        $c->circle($cx, $cy - 3, 37, $c->color($hue));
        $c->circle($cx, $cy - 3, 37, $c->color('#FFFFFF', 0.08));
        $initials = mb_substr($a['base'], 0, strlen($a['base']) > 4 ? 3 : 4);
        $size = strlen($initials) >= 4 ? 14 : (strlen($initials) === 3 ? 17 : 21);
        $c->text($cx, $cy + $c->capHeight($size, 'Black') / 2 - 2, $initials, $size, $c->color('#FFFFFF'), 'Black', 'center');

        $x = $M + 94;
        $w = $c->text($x, $y + 44, $a['base'], 32, $c->color(self::TEXT), 'Black');
        $c->text($x + $w + 6, $y + 44, '/' . $a['quote'], 20, $c->color(self::MUTED), 'Bold');

        $chipY = $y + 58;
        $cx2 = $x;
        $cx2 += $c->pill($cx2, $chipY, strtoupper($a['timeframe']), 12, $c->color(self::TEXT), $c->color('#FFFFFF', 0.14), 'Bold', 'left', 12, 28) + 8;
        $cx2 += $c->pill($cx2, $chipY, strtoupper($a['exchange_label'] ?? 'OURBIT'), 12, $c->color(self::TEXT_2), $c->color('#FFFFFF', 0.07), 'Bold', 'left', 12, 28) + 8;
        $c->pill($cx2, $chipY, Fa::jalaliDate($a['time']), 12, $c->color(self::TEXT_2), $c->color('#FFFFFF', 0.07), 'Medium', 'left', 12, 28);

        // price + change
        $R = self::W - $M;
        $c->text($R, $y + 44, Fa::price($a['price']), 32, $c->color(self::TEXT), 'ExtraBold', 'right');
        $up = $a['change24h'] >= 0;
        $chg = Fa::pct($a['change24h']);
        $col = $up ? self::GREEN : self::RED;
        [$tw] = $c->measure($chg, 13, 'Bold');
        $pw = $tw + 50;
        $px = $R - $pw;
        $c->roundRect($px, $chipY, $pw, 28, 14, $c->color($col, 0.16));
        $c->triangle($px + 17, $chipY + 14, 10, $up, $c->color($col));
        $c->text($px + 29, $chipY + 14 + $c->capHeight(13, 'Bold') / 2, $chg, 13, $c->color($col), 'Bold');
        $c->text($px - 10, $chipY + 14 + $c->capHeight(12, 'Medium') / 2, 'تغییر ۲۴ ساعته', 12, $c->color(self::MUTED), 'Medium', 'right');
    }

    private function chart(float $y, float $h): void
    {
        $c = $this->c;
        $a = $this->a;
        $s = $a['series'];
        $plan = $a['plan'];
        $M = self::M;
        $pw = self::W - 2 * $M;

        $c->roundRect($M, $y, $pw, $h, 24, [self::PANEL_2, self::PANEL, 0.96, 0.96]);
        $c->roundRectStroke($M, $y, $pw, $h, 24, $c->color(self::STROKE), 1.2);

        $x0 = $M + 22;
        $x1 = $M + $pw - 104; // right axis
        $y0 = $y + 58;
        $y1 = $y + $h - 46;
        $volH = ($y1 - $y0) * 0.13;

        $n = $s->count();
        $N = min(100, $n);
        $start = $n - $N;
        $pathMax = max(array_column($plan['path'], 0));
        $F = (int) max(34, min(60, ceil($pathMax * 1.6)));
        $step = ($x1 - $x0) / ($N + $F);
        $bx = static fn (float $i) => $x0 + ($i - $start + 0.5) * $step; // candle index -> x
        $nowX = $bx($n - 1);

        // price range
        $withPathPrices = $this->withPath ? array_column($plan['path'], 1) : [];
        $lo = min(array_slice($s->l, $start));
        $hi = max(array_slice($s->h, $start));
        foreach ($withPathPrices as $p) {
            $lo = min($lo, $p);
            $hi = max($hi, $p);
        }
        $pad = ($hi - $lo) * 0.07;
        $lo -= $pad;
        $hi += $pad;
        $py = static fn (float $p) => $y1 - $volH - ($p - $lo) / ($hi - $lo) * ($y1 - $volH - $y0);
        $inView = static fn (float $p) => $p >= $lo && $p <= $hi;

        // source
        $c->text($M + $pw - 22, $y + 36, $a['symbol'] . ' · ' . strtoupper($a['timeframe']) . ' · ' . strtoupper($a['exchange_label'] ?? 'OURBIT'), 12, $c->color(self::MUTED), 'Bold', 'right');

        // grid + axis labels on round numbers
        $gridCol = $c->color('#FFFFFF', 0.05);
        $tick = self::niceStep(($hi - $lo) / 6);
        $priceY = $py($a['price']);
        for ($p = ceil($lo / $tick) * $tick; $p <= $hi; $p += $tick) {
            $gy = $py($p);
            if ($gy < $y0 - 4) {
                continue;
            }
            $c->dashed($x0, $gy, $x1, $gy, $gridCol, 1, 3, 5);
            if (abs($gy - $priceY) > 18) {
                $c->text($x1 + 12, $gy + 5, Fa::price($p), 11, $c->color(self::MUTED), 'Medium');
            }
        }
        // date labels
        for ($k = 0; $k < 4; $k++) {
            $i = $start + (int) round($N * (0.12 + 0.25 * $k));
            if ($i >= $n) {
                continue;
            }
            $ts = intdiv($s->t[$i], 1000);
            [$jy, $jm, $jd] = Fa::jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
            $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            $c->text($bx($i), $y1 + 30, Fa::digits($jd . ' ' . $months[$jm - 1]), 11, $c->color(self::MUTED), 'Medium', 'center');
        }

        // labels drawn last so candles never cover them
        $edge = [];   // right edge of the plot, stacked without overlap
        $free = [];   // fixed positions
        // key support / resistance levels (only the strong ones)
        foreach (['support' => self::GREEN, 'resistance' => self::RED] as $kind => $col) {
            foreach ($a['key_levels'][$kind] ?? [] as $k => $z) {
                if (!$inView($z['mid'])) {
                    continue;
                }
                // Fine dotted line at the zone's level
                $zy = $py($z['mid']);
                for ($dx = $x0; $dx < $x1; $dx += 7) {
                    $c->circle($dx, $zy, 1.3, $c->color($col));
                }
                $tag = ($kind === 'support' ? 'حمایت ' : 'مقاومت ') . Fa::digits((string) ($k + 1)) . '  ' . Fa::price($z['mid']);
                $edge[] = ['y' => $zy, 'text' => $tag, 'fg' => $c->color('#FFFFFF'), 'bg' => $c->color(Canvas::mix($col, '#000000', 0.2), 0.95), 'weight' => 'Bold'];
            }
        }

        // order blocks: short line at the 50% level of the block
        foreach ($a['order_blocks'] as $ob) {
            $mid = ($ob['top'] + $ob['bottom']) / 2;
            if (!$inView($mid)) {
                continue;
            }
            $col = self::OB;
            $ox = $ob['i'] >= $start ? $bx($ob['i']) - $step / 2 : $x0;
            $oxEnd = max($ox + 8 * $step, min($ox + 22 * $step, $nowX));
            $my = $py($mid);
            $c->dashed($ox, $my, $oxEnd, $my, $c->color($col), 2, 9, 5);
            $c->circle($ox, $my, 3, $c->color($col));
            $free[] = ['x' => $oxEnd, 'y' => $my - 21, 'align' => 'right', 'text' => '50% OB', 'fg' => $c->color('#001A26'), 'bg' => $c->color($col, 0.95), 'h' => 18, 'size' => 9];
        }

        // liquidity
        foreach ($a['liquidity'] as $l) {
            if (!$inView($l['price'])) {
                continue;
            }
            $ly = $py($l['price']);
            $lx = $l['i'] >= $start ? $bx($l['i']) : $x0;
            $c->dashed($lx, $ly, $x1, $ly, $c->color('#FFE04D', 0.8), 1.4, 2, 4);
            $tag = ($l['side'] === 'buy' ? 'BSL' : 'SSL') . ' $$$';
            $edge[] = ['y' => $ly, 'text' => $tag, 'fg' => $c->color('#1A1405'), 'bg' => $c->color('#FFE04D', 0.95), 'weight' => 'Black'];
        }

        // structure events
        foreach (array_slice($a['events'], -3) as $ev) {
            if ($ev['to'] < $start || !$inView($ev['level'])) {
                continue;
            }
            $ex0 = $bx(max($ev['from'], $start));
            $ex1 = $bx($ev['to']);
            $ey = $py($ev['level']);
            $col = $ev['dir'] === 'up' ? self::GREEN : self::RED;
            $c->dashed($ex0, $ey, $ex1, $ey, $c->color($col, 0.9), 1.4, 5, 4);
            $c->text(($ex0 + $ex1) / 2, $ey + ($ev['dir'] === 'up' ? -7 : 17), $ev['type'], 11, $c->color($col), 'Black', 'center');
        }

        // volume
        $vis = array_slice($s->v, $start);
        $vmax = max($vis) ?: 1;
        for ($i = $start; $i < $n; $i++) {
            $vh = $s->v[$i] / $vmax * $volH;
            $col = $s->c[$i] >= $s->o[$i] ? self::CANDLE_UP : self::CANDLE_DOWN;
            $c->rect($bx($i) - $step * 0.32, $y1 - $vh, $bx($i) + $step * 0.32, $y1, $c->color($col, 0.18));
        }

        // candles
        for ($i = $start; $i < $n; $i++) {
            $up = $s->c[$i] >= $s->o[$i];
            $col = $c->color($up ? self::CANDLE_UP : self::CANDLE_DOWN);
            $x = $bx($i);
            $c->line($x, $py($s->h[$i]), $x, $py($s->l[$i]), $col, 1.3);
            $top = $py(max($s->o[$i], $s->c[$i]));
            $bot = $py(min($s->o[$i], $s->c[$i]));
            if ($bot - $top < 1.2) {
                $bot = $top + 1.2;
            }
            $c->rect($x - $step * 0.34, $top, $x + $step * 0.34, $bot, $col);
        }

        // swing labels
        foreach ($a['labels'] as $lb) {
            if ($lb['i'] < $start + 2 || !in_array($lb['label'], ['HH', 'HL', 'LH', 'LL'], true)) {
                continue;
            }
            $isHigh = $lb['type'] === 'high';
            $c->text($bx($lb['i']), $py($lb['price']) + ($isHigh ? -9 : 20), $lb['label'], 10, $c->color(self::TEXT_2, 0.85), 'Bold', 'center');
        }

        $fx0 = $nowX + $step * 0.5;

        // projected path: strong green when bullish, strong red when bearish
        if ($this->withPath) {
            $last = count($plan['path']) - 1;
            $xScale = ($x1 - 175 - $nowX) / max(1, $plan['path'][$last][0]);
            $pts = [];
            foreach ($plan['path'] as [$bars, $p]) {
                $pts[] = [$nowX + $bars * $xScale, $py($p)];
            }
            $pathCol = $c->color($plan['side'] === 'long' ? self::PATH_UP : self::PATH_DOWN);
            $c->polyline($pts, $pathCol, 3.4);
            foreach ($pts as $k => [$px, $pyy]) {
                if ($k > 0 && $k < $last) {
                    $c->circle($px, $pyy, 5, $pathCol);
                }
            }
            $angle = atan2($pts[$last][1] - $pts[$last - 1][1], $pts[$last][0] - $pts[$last - 1][0]);
            $c->arrowHead($pts[$last][0], $pts[$last][1], $angle, 20, $pathCol);
        }

        foreach ($free as $l) {
            $c->pill($l['x'], $l['y'], $l['text'], $l['size'] ?? 10, $l['fg'], $l['bg'], 'Bold', $l['align'] ?? 'left', 8, $l['h'] ?? 20);
        }
        usort($edge, static fn ($p, $q) => $p['y'] <=> $q['y']);
        $prev = -INF;
        foreach ($edge as $l) {
            $ly = max($l['y'], $prev + 24);
            $prev = $ly;
            $w = $c->pill($x1 - 8, $ly - 10, $l['text'], 10, $l['fg'], $l['bg'], $l['weight'] ?? 'Bold', 'right', 9, 20);
            if (isset($l['border'])) {
                $c->roundRectStroke($x1 - 8 - $w, $ly - 10, $w, 20, 10, $c->color($l['border'], 0.6), 1);
            }
        }

        // current price line
        $cy = $py($a['price']);
        $c->dashed($x0, $cy, $x1, $cy, $c->color('#FFFFFF', 0.35), 1, 2, 3);

        // current price tag
        $col = $a['change24h'] >= 0 ? self::GREEN : self::RED;
        $c->roundRect($x1 + 4, $cy - 13, 112, 27, 7, $c->color($col));
        $c->text($x1 + 60, $cy + 6, Fa::price($a['price']), 12, $c->color('#FFFFFF'), 'Black', 'center');
    }

    private static function niceStep(float $raw): float
    {
        if ($raw <= 0) {
            return 1;
        }
        $mag = 10 ** floor(log10($raw));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($raw <= $m * $mag) {
                return $m * $mag;
            }
        }
        return 10 * $mag;
    }

    private function coinColor(string $base): string
    {
        $known = ['BTC' => '#F7931A', 'ETH' => '#627EEA', 'SOL' => '#9945FF', 'BNB' => '#F3BA2F', 'XRP' => '#23292F', 'DOGE' => '#C2A633', 'TON' => '#0098EA', 'ADA' => '#0033AD', 'AVAX' => '#E84142', 'LINK' => '#2A5ADA', 'TRX' => '#EB0029', 'DOT' => '#E6007A', 'PEPE' => '#3D9A3B', 'SHIB' => '#FFA409'];
        if (isset($known[$base])) {
            return $known[$base];
        }
        $h = crc32($base) % 360;
        // HSL(h, 65%, 55%) -> hex
        $s = 0.65;
        $l = 0.55;
        $k = static fn ($n) => fmod($n + $h / 30, 12);
        $f = static fn ($n) => $l - $s * min($l, 1 - $l) * max(-1, min($k($n) - 3, 9 - $k($n), 1));
        return sprintf('#%02x%02x%02x', (int) round($f(0) * 255), (int) round($f(8) * 255), (int) round($f(4) * 255));
    }
}
