<?php

declare(strict_types=1);

namespace EmojiMaker\Encoding;

/**
 * Pure PHP animated GIF writer (GIF89a + NETSCAPE loop extension).
 * Each frame is converted to a palette image by GD; the LZW data produced by
 * imagegif() is re-packed into a single animated file. Used for previews on
 * hosts without FFmpeg.
 */
final class GifEncoder
{
    /**
     * @param list<\GdImage> $frames truecolor frames (same size, opaque background recommended)
     * @param int $delayCs delay per frame in 1/100 s
     */
    public static function encode(array $frames, int $delayCs = 4, int $loops = 0): string
    {
        if (!$frames) {
            throw new EncodingException('No frames');
        }
        $w = imagesx($frames[0]);
        $h = imagesy($frames[0]);
        $out = 'GIF89a' . pack('v', $w) . pack('v', $h) . "\x00\x00\x00"; // no global colour table
        $out .= "\x21\xFF\x0B" . 'NETSCAPE2.0' . "\x03\x01" . pack('v', $loops) . "\x00";
        foreach ($frames as $frame) {
            $copy = imagecreatetruecolor($w, $h);
            imagecopy($copy, $frame, 0, 0, 0, 0, $w, $h);
            imagetruecolortopalette($copy, true, 255);
            ob_start();
            imagegif($copy);
            $gif = (string) ob_get_clean();
            imagedestroy($copy);
            $out .= self::frameBlock($gif, $delayCs);
        }
        return $out . "\x3B";
    }

    private static function frameBlock(string $gif, int $delay): string
    {
        if (!str_starts_with($gif, 'GIF8')) {
            throw new EncodingException('GD produced an invalid GIF frame');
        }
        $flags = ord($gif[10]);
        $hasGct = ($flags & 0x80) !== 0;
        $gctSize = $hasGct ? 3 * (2 ** (($flags & 0x07) + 1)) : 0;
        $p = 13;
        $gct = substr($gif, $p, $gctSize);
        $p += $gctSize;
        $len = strlen($gif);
        // skip extensions until the image descriptor
        while ($p < $len && $gif[$p] !== "\x2C") {
            if ($gif[$p] === "\x21") {
                $p += 2;
                while ($p < $len) {
                    $size = ord($gif[$p]);
                    $p += $size + 1;
                    if ($size === 0) {
                        break;
                    }
                }
            } else {
                $p++;
            }
        }
        if ($p >= $len) {
            throw new EncodingException('GIF frame without image data');
        }
        $desc = substr($gif, $p, 10);
        $p += 10;
        $descFlags = ord($desc[9]);
        $lct = '';
        if ($descFlags & 0x80) {
            $lctSize = 3 * (2 ** (($descFlags & 0x07) + 1));
            $lct = substr($gif, $p, $lctSize);
            $p += $lctSize;
        } elseif ($hasGct) {
            // move the global table into a local table
            $lct = $gct;
            $descFlags = ($descFlags & 0x40) | 0x80 | ($flags & 0x07);
        }
        $desc[9] = chr($descFlags);
        $data = substr($gif, $p, $len - $p - 1); // without trailer
        $gce = "\x21\xF9\x04" . chr(0x08) . pack('v', max(2, $delay)) . "\x00\x00"; // dispose = restore background
        return $gce . $desc . $lct . $data;
    }
}
