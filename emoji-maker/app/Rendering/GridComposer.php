<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Encoding\EncodingException;
use EmojiMaker\Encoding\PreviewEncoder;

/**
 * Builds the overview of a batch: an animated grid (MP4/GIF) with numbered
 * cells so users can preview many emoji at once and pick their favourites,
 * plus a static contact sheet (PNG).
 */
final class GridComposer
{
    public const CELL = 100;
    public const GAP = 6;
    public const COLS = 5;
    public const PER_PAGE = 25;

    public function __construct(private PreviewEncoder $encoder, private array $bg = [28, 39, 51])
    {
    }

    /**
     * @param array<int,string> $itemDirs item number (1-based) => directory with g%03d.jpg frames
     * @return array{animation:?string,format:?string,sheet:string}
     */
    public function compose(array $itemDirs, string $workDir, string $name, int $fps = 15): array
    {
        $numbers = array_keys($itemDirs);
        $n = count($numbers);
        $cols = min(self::COLS, max(1, $n));
        $rows = (int) ceil($n / $cols);
        $w = $cols * self::CELL + ($cols + 1) * self::GAP;
        $h = $rows * self::CELL + ($rows + 1) * self::GAP;
        $w += $w % 2;
        $h += $h % 2;
        $frameLists = [];
        $maxFrames = 1;
        foreach ($itemDirs as $num => $dir) {
            $files = glob(rtrim($dir, '/') . '/g*.jpg') ?: [];
            sort($files);
            $frameLists[$num] = $files;
            $maxFrames = max($maxFrames, count($files));
        }
        $maxFrames = min($maxFrames, 60);
        $frames = [];
        @mkdir($workDir . '/grid_' . $name, 0750, true);
        $cache = [];
        for ($f = 0; $f < $maxFrames; $f++) {
            $img = imagecreatetruecolor($w, $h);
            imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, imagecolorallocate($img, 14, 22, 33));
            foreach ($numbers as $idx => $num) {
                $list = $frameLists[$num];
                if (!$list) {
                    continue;
                }
                $file = $list[$f % count($list)];
                $cell = $cache[$file] ??= @imagecreatefromjpeg($file) ?: null;
                $x = self::GAP + ($idx % $cols) * (self::CELL + self::GAP);
                $y = self::GAP + intdiv($idx, $cols) * (self::CELL + self::GAP);
                if ($cell) {
                    imagecopy($img, $cell, $x, $y, 0, 0, self::CELL, self::CELL);
                }
                self::label($img, $x, $y, (string) $num);
            }
            if (count($cache) > 400) {
                $cache = [];
            }
            $path = sprintf('%s/grid_%s/f%04d.png', $workDir, $name, $f);
            imagepng($img, $path, 1);
            $frames[] = $path;
            if ($f === 0) {
                imagepng($img, $workDir . '/' . $name . '_sheet.png', 6);
            }
        }
        $sheet = $workDir . '/' . $name . '_sheet.png';
        if ($maxFrames <= 1) {
            return ['animation' => null, 'format' => null, 'sheet' => $sheet];
        }
        if ($this->encoder->supportsMp4()) {
            $out = $workDir . '/' . $name . '_grid.mp4';
            try {
                $this->encoder->mp4($workDir . '/grid_' . $name . '/f%04d.png', $maxFrames, $fps, $out, 2);
                return ['animation' => $out, 'format' => 'mp4', 'sheet' => $sheet];
            } catch (EncodingException) {
            }
        }
        $gif = [];
        foreach (array_slice($frames, 0, 40) as $p) {
            $gif[] = imagecreatefrompng($p);
        }
        $out = $workDir . '/' . $name . '_grid.gif';
        $this->encoder->gif($gif, $fps, $out);
        return ['animation' => $out, 'format' => 'gif', 'sheet' => $sheet];
    }

    private static function label(\GdImage $img, int $x, int $y, string $text): void
    {
        $fw = imagefontwidth(3) * strlen($text) + 8;
        $fh = imagefontheight(3) + 2;
        imagefilledrectangle($img, $x, $y, $x + $fw, $y + $fh, imagecolorallocatealpha($img, 0, 0, 0, 30));
        imagestring($img, 3, $x + 4, $y + 1, $text, imagecolorallocate($img, 255, 255, 255));
    }
}
