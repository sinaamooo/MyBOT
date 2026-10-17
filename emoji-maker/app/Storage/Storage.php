<?php

declare(strict_types=1);

namespace EmojiMaker\Storage;

/**
 * File storage rooted outside the web root. File names are always generated
 * by the application (random hex) - user supplied names are never used to
 * build a path. Every resolved path is checked to stay inside the root.
 */
final class Storage
{
    public const CATEGORIES = ['uploads', 'generated', 'previews', 'logs', 'tmp', 'cache', 'locks', 'sessions', 'fonts'];

    private string $root;

    public function __construct(string $root)
    {
        $root = rtrim($root, '/\\');
        if ($root === '') {
            throw new \InvalidArgumentException('Storage root is empty');
        }
        if (!is_dir($root) && !@mkdir($root, 0750, true) && !is_dir($root)) {
            throw new \RuntimeException('Storage root cannot be created: ' . $root);
        }
        $real = realpath($root);
        if ($real === false) {
            throw new \RuntimeException('Storage root is not accessible');
        }
        $this->root = $real;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function ensureStructure(): void
    {
        foreach (self::CATEGORIES as $dir) {
            $path = $this->root . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0750, true);
            }
        }
        $deny = "# Deny all web access to private storage\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
        if (!is_file($this->root . '/.htaccess')) {
            @file_put_contents($this->root . '/.htaccess', $deny);
        }
        if (!is_file($this->root . '/index.html')) {
            @file_put_contents($this->root . '/index.html', '');
        }
    }

    /** Builds a new unique relative path like "generated/2026/10/ab12...ef.webm". */
    public function newRelativePath(string $category, string $extension): string
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Unknown storage category');
        }
        $extension = strtolower($extension);
        if (!preg_match('/^[a-z0-9]{1,5}$/', $extension)) {
            throw new \InvalidArgumentException('Invalid extension');
        }
        $dir = $category . '/' . date('Y') . '/' . date('m');
        $abs = $this->root . '/' . $dir;
        if (!is_dir($abs) && !@mkdir($abs, 0750, true) && !is_dir($abs)) {
            throw new \RuntimeException('Cannot create storage directory');
        }
        return $dir . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
    }

    /** Resolves a stored relative path to an absolute one (Path Traversal safe). */
    public function path(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || str_contains($relative, "\0") || str_starts_with($relative, '/')
            || preg_match('#(^|/)\.\.?(/|$)#', $relative)) {
            throw new \InvalidArgumentException('Illegal storage path');
        }
        $first = explode('/', $relative, 2)[0];
        if (!in_array($first, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Illegal storage category');
        }
        $abs = $this->root . '/' . $relative;
        $dir = realpath(dirname($abs));
        if ($dir === false || !str_starts_with($dir . '/', $this->root . '/')) {
            throw new \InvalidArgumentException('Path escapes storage root');
        }
        return $dir . '/' . basename($abs);
    }

    public function exists(string $relative): bool
    {
        try {
            return is_file($this->path($relative));
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function put(string $relative, string $contents): void
    {
        $abs = $this->path($relative);
        if (file_put_contents($abs, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write file');
        }
        @chmod($abs, 0640);
    }

    /** Moves an absolute file (e.g. from a tmp dir) into storage. */
    public function import(string $absoluteSource, string $category, string $extension): string
    {
        $rel = $this->newRelativePath($category, $extension);
        $dst = $this->path($rel);
        if (!@rename($absoluteSource, $dst)) {
            if (!@copy($absoluteSource, $dst)) {
                throw new \RuntimeException('Unable to store file');
            }
            @unlink($absoluteSource);
        }
        @chmod($dst, 0640);
        return $rel;
    }

    public function delete(string $relative): bool
    {
        try {
            $abs = $this->path($relative);
        } catch (\InvalidArgumentException) {
            return false;
        }
        return is_file($abs) && @unlink($abs);
    }

    /** Creates a private temporary working directory inside storage/tmp. */
    public function makeTempDir(string $prefix = 'job'): string
    {
        $prefix = preg_replace('/[^a-z0-9_-]/i', '', $prefix) ?: 'tmp';
        $dir = $this->root . '/tmp/' . $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6));
        if (!@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create temp dir');
        }
        return $dir;
    }

    /** Recursively removes a directory, but only if it lives inside storage. */
    public function removeDir(string $absDir): void
    {
        $real = realpath($absDir);
        if ($real === false || !str_starts_with($real . '/', $this->root . '/') || $real === $this->root) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isDir() && !$f->isLink()) {
                @rmdir($f->getPathname());
            } else {
                @unlink($f->getPathname());
            }
        }
        @rmdir($real);
    }

    /** @return array<string,int> bytes used per category */
    public function usage(): array
    {
        $out = [];
        foreach (self::CATEGORIES as $cat) {
            $out[$cat] = $this->dirSize($this->root . '/' . $cat);
        }
        return $out;
    }

    public function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isFile()) {
                $size += $f->getSize();
            }
        }
        return $size;
    }

    /**
     * Deletes files older than $maxAgeSeconds inside a category (used by cleanup).
     * @return array{files:int,bytes:int}
     */
    public function purgeOlderThan(string $category, int $maxAgeSeconds, ?callable $keep = null): array
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Unknown category');
        }
        $dir = $this->root . '/' . $category;
        $count = 0;
        $bytes = 0;
        if (!is_dir($dir)) {
            return ['files' => 0, 'bytes' => 0];
        }
        $limit = time() - $maxAgeSeconds;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            $name = $f->getFilename();
            if ($name === '.htaccess' || $name === '.gitkeep' || $name === 'index.html') {
                continue;
            }
            if ($f->isDir()) {
                @rmdir($f->getPathname()); // removes only empty dirs
                continue;
            }
            if ($f->getMTime() < $limit) {
                $rel = substr($f->getPathname(), strlen($this->root) + 1);
                if ($keep !== null && $keep($rel)) {
                    continue;
                }
                $bytes += $f->getSize();
                if (@unlink($f->getPathname())) {
                    $count++;
                }
            }
        }
        return ['files' => $count, 'bytes' => $bytes];
    }
}
