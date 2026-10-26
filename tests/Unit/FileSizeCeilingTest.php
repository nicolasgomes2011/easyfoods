<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileSizeCeilingTest extends TestCase
{
    private const MAX_LINES = 350;

    private const SCANNED_DIRS = ['app', 'resources', 'routes', 'database'];

    private const EXTENSIONS = ['php', 'js'];

    // Adoption baseline: files already over MAX_LINES when the gate was introduced.
    // An entry may only shrink. Remove it once its file is back under MAX_LINES.
    private const BASELINE = [
        'resources/views/livewire/store/checkout.blade.php' => 428,
        'resources/views/livewire/catalog/addons.blade.php' => 410,
        'resources/views/livewire/orders/show.blade.php' => 352,
    ];

    public function test_files_stay_under_the_line_ceiling(): void
    {
        $offenders = array_diff_key($this->oversizedFiles(), self::BASELINE);

        $this->assertSame([], $offenders, sprintf(
            "Files over %d lines (split them; do not raise the ceiling):\n%s",
            self::MAX_LINES,
            $this->format($offenders),
        ));
    }

    public function test_baselined_files_do_not_grow(): void
    {
        $grown = [];

        foreach (self::BASELINE as $path => $baseline) {
            $lines = $this->countLines($this->root().'/'.$path);

            if ($lines > $baseline) {
                $grown[$path] = "{$lines} (baseline {$baseline})";
            }
        }

        $this->assertSame([], $grown, "Baselined files grew past their recorded size:\n".$this->format($grown));
    }

    public function test_baseline_has_no_stale_entries(): void
    {
        $stale = [];

        foreach (array_keys(self::BASELINE) as $path) {
            $file = $this->root().'/'.$path;

            if (! is_file($file) || $this->countLines($file) <= self::MAX_LINES) {
                $stale[] = $path;
            }
        }

        $this->assertSame([], $stale, "Remove these entries from BASELINE:\n".implode("\n", $stale));
    }

    /**
     * @return array<string, int> relative path => line count, largest first
     */
    private function oversizedFiles(): array
    {
        $oversized = [];

        foreach (self::SCANNED_DIRS as $dir) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root().'/'.$dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! in_array($file->getExtension(), self::EXTENSIONS, true)) {
                    continue;
                }

                $lines = $this->countLines($file->getPathname());

                if ($lines > self::MAX_LINES) {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->root()) + 1));
                    $oversized[$relative] = $lines;
                }
            }
        }

        arsort($oversized);

        return $oversized;
    }

    private function countLines(string $file): int
    {
        $contents = file_get_contents($file);

        if ($contents === '') {
            return 0;
        }

        return substr_count($contents, "\n") + (str_ends_with($contents, "\n") ? 0 : 1);
    }

    private function format(array $entries): string
    {
        return implode("\n", array_map(
            fn ($path, $value) => "  {$value}  {$path}",
            array_keys($entries),
            $entries,
        ));
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
