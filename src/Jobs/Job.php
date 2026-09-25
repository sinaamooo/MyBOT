<?php
declare(strict_types=1);

namespace Nikto\Jobs;

use Nikto\Core\Db;
use Nikto\Core\Settings;
use Nikto\Render\Card;
use Nikto\Render\Theme;

abstract class Job
{
    public const KEY = '';

    protected array $row;

    public function __construct()
    {
        $this->row = $this->loadRow();
    }

    abstract public function title(): string;

    abstract public function icon(): string;

    abstract public function description(): string;

    abstract public function fetch(): mixed;

    abstract public function card(mixed $data): Card;

    abstract public function summary(mixed $data): string;

    public function defaultOptions(): array
    {
        return [];
    }

    public function defaultTheme(): string
    {
        return Theme::DEFAULT;
    }

    public function defaultCaption(): string
    {
        return "{summary}\n\n🗓 {date} — ⏰ {time}\n{link}";
    }

    public function key(): string
    {
        return static::KEY;
    }

    public function enabled(): bool
    {
        return (int) $this->row['enabled'] === 1;
    }

    public function theme(): string
    {
        $theme = (string) $this->row['theme'];

        return isset(Theme::PALETTES[$theme]) ? $theme : Theme::DEFAULT;
    }

    public function caption(): string
    {
        return (string) $this->row['caption'];
    }

    public function options(): array
    {
        $opts = json_decode((string) $this->row['options'], true);

        return array_replace($this->defaultOptions(), is_array($opts) ? $opts : []);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options()[$key] ?? $default;
    }

    public function setOption(string $key, mixed $value): void
    {
        $opts = $this->options();
        $opts[$key] = $value;
        $this->update(['options' => json_encode($opts, JSON_UNESCAPED_UNICODE)]);
    }

    public function setEnabled(bool $on): void
    {
        $this->update(['enabled' => $on ? 1 : 0]);
    }

    public function setTheme(string $theme): void
    {
        $this->update(['theme' => $theme]);
    }

    public function setCaption(string $caption): void
    {
        $this->update(['caption' => $caption]);
    }

    protected function update(array $fields): void
    {
        $sets = [];
        $params = [':k' => static::KEY, ':t' => time()];
        foreach ($fields as $name => $value) {
            $sets[] = "$name = :$name";
            $params[':' . $name] = $value;
        }
        $sets[] = 'updated_at = :t';
        Db::exec('UPDATE jobs SET ' . implode(', ', $sets) . ' WHERE key = :k', $params);
        $this->row = $this->loadRow();
    }

    public function channels(): array
    {
        return Db::all(
            'SELECT c.* FROM channels c
             INNER JOIN job_channels jc ON jc.channel_id = c.id
             WHERE jc.job_key = :k AND c.active = 1
             ORDER BY c.id',
            [':k' => static::KEY]
        );
    }

    public function hasChannel(int $channelId): bool
    {
        return Db::one(
            'SELECT 1 FROM job_channels WHERE job_key = :k AND channel_id = :c',
            [':k' => static::KEY, ':c' => $channelId]
        ) !== null;
    }

    public function toggleChannel(int $channelId): bool
    {
        if ($this->hasChannel($channelId)) {
            Db::exec('DELETE FROM job_channels WHERE job_key = :k AND channel_id = :c', [':k' => static::KEY, ':c' => $channelId]);
            return false;
        }
        Db::exec('INSERT INTO job_channels(job_key, channel_id) VALUES(:k, :c)', [':k' => static::KEY, ':c' => $channelId]);

        return true;
    }

    public function schedules(): array
    {
        return Db::all('SELECT * FROM schedules WHERE job_key = :k ORDER BY at_time', [':k' => static::KEY]);
    }

    public function renderCaption(mixed $data): string
    {
        $now = Settings::now();

        $replace = [
            '{summary}' => $this->summary($data),
            '{date}'    => \Nikto\Core\Jalali::format($now, 'l j F Y'),
            '{time}'    => $now->format('H:i'),
            '{brand}'   => Settings::get('brand'),
            '{link}'    => Settings::get('brand_link'),
        ];
        if (Settings::get('digits') === 'fa') {
            $replace['{date}'] = \Nikto\Text\Persian::faDigits($replace['{date}']);
            $replace['{time}'] = \Nikto\Text\Persian::faDigits($replace['{time}']);
        }

        $caption = strtr($this->caption(), $replace);
        $caption = preg_replace("/\n{3,}/", "\n\n", $caption) ?? $caption;

        return trim($caption);
    }

    private function loadRow(): array
    {
        $row = Db::one('SELECT * FROM jobs WHERE key = :k', [':k' => static::KEY]);
        if ($row === null) {
            Db::exec(
                'INSERT INTO jobs(key, title, enabled, theme, caption, options, updated_at)
                 VALUES(:k, :t, 0, :th, :c, :o, :u)',
                [
                    ':k'  => static::KEY,
                    ':t'  => $this->title(),
                    ':th' => $this->defaultTheme(),
                    ':c'  => $this->defaultCaption(),
                    ':o'  => json_encode($this->defaultOptions(), JSON_UNESCAPED_UNICODE),
                    ':u'  => time(),
                ]
            );
            $row = Db::one('SELECT * FROM jobs WHERE key = :k', [':k' => static::KEY]);
        }

        return $row ?? [];
    }
}
