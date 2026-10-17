<?php

declare(strict_types=1);

namespace EmojiMaker\Validation;

/**
 * Minimal EBML / Matroska (WebM) reader used to validate video emoji without
 * external tools: doc type, tracks, codec, size, alpha mode, duration and the
 * number of frames (SimpleBlocks / Blocks of the video track).
 */
final class EbmlReader
{
    private const EBML = 0x1A45DFA3;
    private const DOCTYPE = 0x4282;
    private const SEGMENT = 0x18538067;
    private const INFO = 0x1549A966;
    private const TIMECODE_SCALE = 0x2AD7B1;
    private const DURATION = 0x4489;
    private const TRACKS = 0x1654AE6B;
    private const TRACK_ENTRY = 0xAE;
    private const TRACK_NUMBER = 0xD7;
    private const TRACK_TYPE = 0x83;
    private const CODEC_ID = 0x86;
    private const DEFAULT_DURATION = 0x23E383;
    private const VIDEO = 0xE0;
    private const PIXEL_WIDTH = 0xB0;
    private const PIXEL_HEIGHT = 0xBA;
    private const ALPHA_MODE = 0x53C0;
    private const CLUSTER = 0x1F43B675;
    private const CLUSTER_TIMECODE = 0xE7;
    private const SIMPLE_BLOCK = 0xA3;
    private const BLOCK_GROUP = 0xA0;
    private const BLOCK = 0xA1;

    private string $d;
    private int $len;

    /** @var array<string,mixed> */
    private array $result = [
        'doctype' => null,
        'timecode_scale' => 1000000,
        'duration' => null,
        'tracks' => [],
        'frames' => 0,
        'timecodes' => [],
    ];

    public function __construct(string $data)
    {
        $this->d = $data;
        $this->len = strlen($data);
    }

    /** @return array<string,mixed> */
    public function parse(): array
    {
        if ($this->len < 16 || substr($this->d, 0, 4) !== "\x1A\x45\xDF\xA3") {
            throw new \RuntimeException('Not an EBML file');
        }
        $this->walk(0, $this->len, 0);
        $r = $this->result;
        $r['duration_seconds'] = $r['duration'] !== null ? $r['duration'] * $r['timecode_scale'] / 1e9 : null;
        unset($r['timecodes']);
        $r['frame_timecodes'] = $this->result['timecodes'];
        return $r;
    }

    private function walk(int $pos, int $end, int $depth, ?array &$track = null, int $clusterTc = 0): void
    {
        while ($pos < $end && $pos < $this->len) {
            [$id, $idLen] = $this->readId($pos);
            if ($idLen === 0) {
                return;
            }
            [$size, $sizeLen] = $this->readSize($pos + $idLen);
            if ($sizeLen === 0) {
                return;
            }
            $dataPos = $pos + $idLen + $sizeLen;
            $dataEnd = $size < 0 ? $end : min($end, $dataPos + $size);
            switch ($id) {
                case self::EBML:
                    $this->walk($dataPos, $dataEnd, $depth + 1);
                    break;
                case self::DOCTYPE:
                    $this->result['doctype'] = rtrim(substr($this->d, $dataPos, $dataEnd - $dataPos), "\0");
                    break;
                case self::SEGMENT:
                case self::INFO:
                case self::TRACKS:
                    $this->walk($dataPos, $dataEnd, $depth + 1);
                    break;
                case self::TIMECODE_SCALE:
                    $this->result['timecode_scale'] = $this->uint($dataPos, $dataEnd - $dataPos);
                    break;
                case self::DURATION:
                    $this->result['duration'] = $this->float($dataPos, $dataEnd - $dataPos);
                    break;
                case self::TRACK_ENTRY:
                    $t = ['number' => 0, 'type' => 0, 'codec' => '', 'width' => 0, 'height' => 0, 'alpha_mode' => 0, 'default_duration' => 0];
                    $this->walk($dataPos, $dataEnd, $depth + 1, $t);
                    $this->result['tracks'][] = $t;
                    break;
                case self::VIDEO:
                    $this->walk($dataPos, $dataEnd, $depth + 1, $track);
                    break;
                case self::TRACK_NUMBER:
                    if ($track !== null) { $track['number'] = $this->uint($dataPos, $dataEnd - $dataPos); }
                    break;
                case self::TRACK_TYPE:
                    if ($track !== null) { $track['type'] = $this->uint($dataPos, $dataEnd - $dataPos); }
                    break;
                case self::CODEC_ID:
                    if ($track !== null) { $track['codec'] = rtrim(substr($this->d, $dataPos, $dataEnd - $dataPos), "\0"); }
                    break;
                case self::DEFAULT_DURATION:
                    if ($track !== null) { $track['default_duration'] = $this->uint($dataPos, $dataEnd - $dataPos); }
                    break;
                case self::PIXEL_WIDTH:
                    if ($track !== null) { $track['width'] = $this->uint($dataPos, $dataEnd - $dataPos); }
                    break;
                case self::PIXEL_HEIGHT:
                    if ($track !== null) { $track['height'] = $this->uint($dataPos, $dataEnd - $dataPos); }
                    break;
                case self::ALPHA_MODE:
                    if ($track !== null) { $track['alpha_mode'] = $this->uint($dataPos, $dataEnd - $dataPos); }
                    break;
                case self::CLUSTER:
                    $this->cluster($dataPos, $dataEnd);
                    break;
            }
            if ($size < 0) {
                return;
            }
            $pos = $dataPos + $size;
        }
    }

    private function cluster(int $pos, int $end): void
    {
        $tc = 0;
        while ($pos < $end) {
            [$id, $idLen] = $this->readId($pos);
            [$size, $sizeLen] = $this->readSize($pos + $idLen);
            if ($idLen === 0 || $sizeLen === 0 || $size < 0) {
                return;
            }
            $dataPos = $pos + $idLen + $sizeLen;
            if ($id === self::CLUSTER_TIMECODE) {
                $tc = $this->uint($dataPos, $size);
            } elseif ($id === self::SIMPLE_BLOCK) {
                $this->block($dataPos, $tc);
            } elseif ($id === self::BLOCK_GROUP) {
                $p = $dataPos;
                $gEnd = $dataPos + $size;
                while ($p < $gEnd) {
                    [$cid, $cl] = $this->readId($p);
                    [$cs, $csl] = $this->readSize($p + $cl);
                    if ($cl === 0 || $csl === 0 || $cs < 0) {
                        break;
                    }
                    if ($cid === self::BLOCK) {
                        $this->block($p + $cl + $csl, $tc);
                    }
                    $p += $cl + $csl + $cs;
                }
            }
            $pos = $dataPos + $size;
        }
    }

    private function block(int $pos, int $clusterTc): void
    {
        [$trackNum, $l] = $this->readSize($pos);
        $rel = (ord($this->d[$pos + $l] ?? "\0") << 8) | ord($this->d[$pos + $l + 1] ?? "\0");
        if ($rel >= 0x8000) {
            $rel -= 0x10000;
        }
        foreach ($this->result['tracks'] as $t) {
            if ($t['number'] === $trackNum && $t['type'] === 1) {
                $this->result['frames']++;
                if (count($this->result['timecodes']) < 2000) {
                    $this->result['timecodes'][] = $clusterTc + $rel;
                }
                return;
            }
        }
    }

    /** @return array{0:int,1:int} id (with marker) and length */
    private function readId(int $pos): array
    {
        $b = ord($this->d[$pos] ?? "\0");
        $len = match (true) {
            ($b & 0x80) !== 0 => 1,
            ($b & 0x40) !== 0 => 2,
            ($b & 0x20) !== 0 => 3,
            ($b & 0x10) !== 0 => 4,
            default => 0,
        };
        if ($len === 0 || $pos + $len > $this->len) {
            return [0, 0];
        }
        $id = 0;
        for ($i = 0; $i < $len; $i++) {
            $id = ($id << 8) | ord($this->d[$pos + $i]);
        }
        return [$id, $len];
    }

    /** @return array{0:int,1:int} size (-1 = unknown) and length */
    private function readSize(int $pos): array
    {
        $b = ord($this->d[$pos] ?? "\0");
        $len = 0;
        for ($i = 0; $i < 8; $i++) {
            if ($b & (0x80 >> $i)) {
                $len = $i + 1;
                break;
            }
        }
        if ($len === 0 || $pos + $len > $this->len) {
            return [0, 0];
        }
        $value = $b & (0xFF >> $len);
        $allOnes = $value === (0xFF >> $len);
        for ($i = 1; $i < $len; $i++) {
            $byte = ord($this->d[$pos + $i]);
            $value = ($value << 8) | $byte;
            if ($byte !== 0xFF) {
                $allOnes = false;
            }
        }
        return [$allOnes ? -1 : $value, $len];
    }

    private function uint(int $pos, int $len): int
    {
        $v = 0;
        for ($i = 0; $i < $len && $i < 8; $i++) {
            $v = ($v << 8) | ord($this->d[$pos + $i] ?? "\0");
        }
        return $v;
    }

    private function float(int $pos, int $len): float
    {
        $bytes = substr($this->d, $pos, $len);
        if ($len === 4) {
            return (float) unpack('G', $bytes)[1];
        }
        if ($len === 8) {
            return (float) unpack('E', $bytes)[1];
        }
        return 0.0;
    }
}
