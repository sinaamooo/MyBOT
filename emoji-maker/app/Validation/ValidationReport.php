<?php

declare(strict_types=1);

namespace EmojiMaker\Validation;

final class ValidationReport
{
    /** @var list<string> */
    public array $errors = [];
    /** @var list<string> */
    public array $warnings = [];
    /** @var array<string,mixed> */
    public array $info = [];

    public function __construct(public readonly string $format, public readonly string $file)
    {
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function status(): string
    {
        if ($this->errors) {
            return 'invalid';
        }
        return $this->warnings ? 'warning' : 'valid';
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'status' => $this->status(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'info' => $this->info,
        ];
    }

    public function summaryFa(): string
    {
        $lines = [];
        $lines[] = match ($this->status()) {
            'valid' => '✅ فایل با استاندارد تلگرام سازگار است.',
            'warning' => '⚠️ فایل قابل استفاده است ولی هشدار دارد.',
            default => '❌ فایل با استاندارد تلگرام سازگار نیست.',
        };
        foreach ($this->errors as $e) {
            $lines[] = '• ' . $e;
        }
        foreach ($this->warnings as $w) {
            $lines[] = '• ' . $w;
        }
        return implode("\n", $lines);
    }
}
