<?php

declare(strict_types=1);

namespace Leek\LaravelDocsIndex\DocsIndex;

use RuntimeException;

class DocsIndexInjector
{
    private const PREAMBLE_MARKER = '[LARAVEL-DOCS-INDEX:PREAMBLE]';

    private const START_MARKER = '[LARAVEL-DOCS-INDEX:START]';

    private const END_MARKER = '[LARAVEL-DOCS-INDEX:END]';

    /**
     * Inject index content between markers in the target file.
     * Prepends to beginning of file, before any existing content.
     */
    public function inject(string $filePath, string $indexContent, string $docsDir = '.laravel-docs'): void
    {
        $fullPath = base_path($filePath);

        $directory = dirname($fullPath);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true)) {
            throw new RuntimeException("Failed to create directory: {$directory}");
        }

        $handle = @fopen($fullPath, 'c+');

        if (! $handle) {
            throw new RuntimeException("Failed to open file: {$fullPath}");
        }

        try {
            $this->acquireLockWithRetry($handle, $fullPath);

            $content = stream_get_contents($handle) ?: '';

            $preamble = self::PREAMBLE_MARKER."\n"
                ."## Local Docs: {$docsDir}\n"
                ."\n"
                ."**IMPORTANT: Your training data is likely OUTDATED for this project's package versions. ALWAYS read the relevant docs from `{$docsDir}/` using the Read tool BEFORE writing any code. The index below lists all available doc files.**\n"
                ."\n";
            $block = $preamble.self::START_MARKER."\n".$indexContent."\n".self::END_MARKER;

            // Remove any existing block — supports both old HTML comment markers and new bracket markers
            $content = $this->stripExistingBlock($content);

            $newContent = $block."\n\n".ltrim((string) $content);
            $newContent = (new BoostConflictCleaner)->clean($newContent);

            if (ftruncate($handle, 0) === false || fseek($handle, 0) === -1) {
                throw new RuntimeException("Failed to reset file pointer: {$fullPath}");
            }

            if (fwrite($handle, $newContent) === false) {
                throw new RuntimeException("Failed to write to file: {$fullPath}");
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Strip any existing docs index block, handling both old and new marker formats.
     */
    protected function stripExistingBlock(string $content): string
    {
        $formats = [
            // New bracket format
            [self::PREAMBLE_MARKER, self::START_MARKER, self::END_MARKER],
            // Old HTML comment format
            ['<!-- LARAVEL-DOCS-INDEX:PREAMBLE -->', '<!-- LARAVEL-DOCS-INDEX:START -->', '<!-- LARAVEL-DOCS-INDEX:END -->'],
        ];

        foreach ($formats as [$preamble, $start, $end]) {
            if (str_contains($content, $start) && str_contains($content, $end)) {
                $preamblePattern = '('.preg_quote($preamble, '/').'.*?)?';
                $pattern = '/\n*'.$preamblePattern.preg_quote($start, '/').'.*?'.preg_quote($end, '/').'\n*/s';
                $content = preg_replace($pattern, '', $content);
            }
        }

        return $content;
    }

    protected function acquireLockWithRetry(mixed $handle, string $filePath, int $maxRetries = 3): void
    {
        $attempts = 0;
        $delay = 100000;

        while ($attempts < $maxRetries) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return;
            }

            $attempts++;

            if ($attempts >= $maxRetries) {
                throw new RuntimeException("Failed to acquire lock on file after {$maxRetries} attempts: {$filePath}");
            }

            $jitter = random_int(0, (int) ($delay * 0.1));
            usleep($delay + $jitter);
            $delay *= 2;
        }
    }
}
