<?php

declare(strict_types=1);

namespace App\Signals;

use App\Support\Num;

/**
 * Renders a branded PNG "signal card" for a published candidate - coin
 * symbol, side, and an S/A/B/C rank badge derived from the score - sent via
 * sendPhoto alongside the text message. Pure GD (no Imagick dependency) so
 * it works on stock shared-hosting PHP builds; degrades to "no image" if
 * the GD extension isn't available rather than failing the whole publish.
 */
final class SignalCard
{
    private const WIDTH = 1000;
    private const HEIGHT = 560;

    private const BG = [13, 20, 28];
    private const MUTED = [148, 158, 172];
    private const WHITE = [237, 242, 247];

    private const LONG_ACCENT = [22, 199, 132];
    private const SHORT_ACCENT = [234, 57, 67];

    private const RANK_COLORS = [
        'S' => [255, 200, 40],
        'A' => [22, 199, 132],
        'B' => [69, 137, 255],
        'C' => [148, 158, 172],
    ];

    /** @param array<string, mixed> $candidate */
    public static function generate(array $candidate): ?string
    {
        if (!extension_loaded('gd') || !function_exists('imagettftext')) {
            return null;
        }

        $fontBold = dirname(__DIR__, 2) . '/assets/fonts/DejaVuSans-Bold.ttf';
        $fontRegular = dirname(__DIR__, 2) . '/assets/fonts/DejaVuSans.ttf';
        if (!is_file($fontBold) || !is_file($fontRegular)) {
            return null;
        }

        $bullish = ($candidate['side'] ?? 'LONG') === 'LONG';
        $accent = $bullish ? self::LONG_ACCENT : self::SHORT_ACCENT;
        $rank = self::rank((float) ($candidate['score'] ?? 0));
        $rankColor = self::RANK_COLORS[$rank];

        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $bg = self::color($img, self::BG);
        $muted = self::color($img, self::MUTED);
        $white = self::color($img, self::WHITE);
        $accentColor = self::color($img, $accent);
        $rankColorAllocated = self::color($img, $rankColor);

        imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, $bg);
        imagefilledrectangle($img, 0, 0, self::WIDTH, 10, $accentColor);
        imagefilledrectangle($img, 0, self::HEIGHT - 10, self::WIDTH, self::HEIGHT, $accentColor);

        self::text($img, $fontRegular, 16, 40, 55, $muted, 'FUTURES SIGNAL');
        $symbolSize = self::fitSize($fontBold, (string) ($candidate['symbol'] ?? ''), 48, 22, self::WIDTH - 40 - 40 - 260);
        self::text($img, $fontBold, $symbolSize, 40, 130, $white, (string) ($candidate['symbol'] ?? ''));

        $sideLabel = $bullish ? 'LONG' : 'SHORT';
        imagefilledrectangle($img, 40, 155, 210, 200, $accentColor);
        self::textCentered($img, $fontBold, 22, 40, 210, 185, self::color($img, self::BG), $sideLabel);

        $scoreText = sprintf('Score: %.0f%%', (float) ($candidate['score'] ?? 0));
        self::text($img, $fontRegular, 20, 230, 190, $muted, $scoreText);

        $leverageText = sprintf('%dX Leverage', (int) ($candidate['leverage'] ?? 0));
        self::text($img, $fontRegular, 20, 40, 240, $muted, $leverageText);

        // Rank badge circle, right side.
        $cx = 850;
        $cy = 150;
        $radius = 95;
        imagefilledellipse($img, $cx, $cy, $radius * 2, $radius * 2, $rankColorAllocated);
        self::textCentered($img, $fontBold, 80, $cx - $radius, $cx + $radius, $cy + 28, self::color($img, self::BG), $rank);
        self::textCentered($img, $fontRegular, 16, $cx - $radius, $cx + $radius, $cy + $radius + 30, $muted, 'RANK');

        // Divider.
        imageline($img, 40, 300, self::WIDTH - 40, 300, self::color($img, [40, 50, 62]));

        $columns = [
            ['label' => 'ENTRY', 'value' => isset($candidate['entry']) ? Num::price((float) $candidate['entry']) : '-'],
            ['label' => 'STOP LOSS', 'value' => isset($candidate['stop_loss']) ? Num::price((float) $candidate['stop_loss']) : '-'],
            ['label' => 'TP1', 'value' => isset($candidate['tp1']) ? Num::price((float) $candidate['tp1']) : '-'],
            ['label' => 'R:R', 'value' => '1:' . (string) ($candidate['rr'] ?? '-')],
        ];
        $colWidth = (self::WIDTH - 80) / count($columns);
        $colInnerWidth = $colWidth - 20;
        foreach ($columns as $i => $col) {
            $x = 40 + $i * $colWidth;
            self::text($img, $fontRegular, 16, (int) $x, 345, $muted, $col['label']);
            $valueSize = self::fitSize($fontBold, $col['value'], 30, 14, $colInnerWidth);
            self::text($img, $fontBold, $valueSize, (int) $x, 400, $white, $col['value']);
        }

        $dir = dirname(__DIR__, 2) . '/data/cards';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($img);
            return null;
        }
        $path = $dir . '/' . uniqid('sig_', true) . '.png';
        $ok = imagepng($img, $path);
        imagedestroy($img);

        return $ok ? $path : null;
    }

    public static function rank(float $score): string
    {
        return match (true) {
            $score >= 90 => 'S',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            default => 'C',
        };
    }

    /** @param int[] $rgb */
    private static function color(\GdImage $img, array $rgb): int
    {
        return (int) imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }

    /** Largest font size (down to $minSize) that keeps $text within $maxWidth pixels. */
    private static function fitSize(string $font, string $text, int $maxSize, int $minSize, float $maxWidth): int
    {
        $box = imagettfbbox((float) $maxSize, 0, $font, $text);
        $width = abs($box[2] - $box[0]);
        if ($width <= $maxWidth || $width <= 0.0) {
            return $maxSize;
        }
        $scaled = (int) floor($maxSize * ($maxWidth / $width));
        return max($minSize, min($maxSize, $scaled));
    }

    private static function text(\GdImage $img, string $font, int $size, int $x, int $y, int $color, string $text): void
    {
        imagettftext($img, (float) $size, 0, $x, $y, $color, $font, $text);
    }

    /** Horizontally centers $text within [$left, $right], baseline at $y. */
    private static function textCentered(\GdImage $img, string $font, int $size, int $left, int $right, int $y, int $color, string $text): void
    {
        $box = imagettfbbox((float) $size, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
        $x = $left + (($right - $left) - $textWidth) / 2;
        imagettftext($img, (float) $size, 0, (int) $x, $y, $color, $font, $text);
    }
}
