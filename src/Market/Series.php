<?php

declare(strict_types=1);

namespace App\Market;

/**
 * OHLCV candles stored column-wise, oldest first. Times are unix milliseconds.
 */
final class Series
{
    /** @var int[] */
    public array $t = [];
    /** @var float[] */
    public array $o = [];
    /** @var float[] */
    public array $h = [];
    /** @var float[] */
    public array $l = [];
    /** @var float[] */
    public array $c = [];
    /** @var float[] */
    public array $v = [];

    public function __construct(public string $symbol, public string $timeframe, public string $source = '')
    {
    }

    /** @param array<int, array{0:int,1:float,2:float,3:float,4:float,5:float}> $rows */
    public static function fromRows(string $symbol, string $timeframe, string $source, array $rows): self
    {
        usort($rows, static fn ($a, $b) => $a[0] <=> $b[0]);
        $s = new self($symbol, $timeframe, $source);
        $lastT = null;
        foreach ($rows as $r) {
            if ($r[0] === $lastT) {
                continue;
            }
            $lastT = $r[0];
            $s->t[] = (int) $r[0];
            $s->o[] = (float) $r[1];
            $s->h[] = (float) $r[2];
            $s->l[] = (float) $r[3];
            $s->c[] = (float) $r[4];
            $s->v[] = (float) $r[5];
        }
        return $s;
    }

    public function count(): int
    {
        return count($this->c);
    }

    public function lastClose(): float
    {
        return $this->c[$this->count() - 1];
    }

    /** Keeps only the newest $n candles. */
    public function tail(int $n): self
    {
        $s = new self($this->symbol, $this->timeframe, $this->source);
        foreach (['t', 'o', 'h', 'l', 'c', 'v'] as $k) {
            $s->$k = array_slice($this->$k, -$n);
        }
        return $s;
    }

    public static function timeframeSeconds(string $tf): int
    {
        $n = (int) $tf;
        return match (substr($tf, -1)) {
            'm' => $n * 60,
            'h' => $n * 3600,
            'd' => $n * 86400,
            'w' => $n * 604800,
            default => 3600,
        };
    }
}
