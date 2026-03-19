<?php

declare(strict_types=1);

namespace Leek\LaravelDocsIndex\DocsIndex;

use RuntimeException;
use Symfony\Component\Process\Process;

class DocsDownloader
{
    /**
     * Clone a repo with sparse-checkout for specific path(s).
     *
     * @param  string|list<string>  $sparsePaths
     */
    public function download(string $repo, string $branch, string|array $sparsePaths, string $targetDir): void
    {
        $repoUrl = "https://github.com/{$repo}.git";
        $paths = is_array($sparsePaths) ? $sparsePaths : [$sparsePaths];

        if ($paths === ['/']) {
            $this->run(
                ['git', 'clone', '--depth=1', '--single-branch', '--branch', $branch, $repoUrl, $targetDir],
                base_path()
            );
        } else {
            $this->run(
                ['git', 'clone', '--filter=blob:none', '--no-checkout', '--depth=1', '--single-branch', '--branch', $branch, $repoUrl, $targetDir],
                base_path()
            );

            $this->run(
                array_merge(['git', 'sparse-checkout', 'set'], $paths),
                base_path($targetDir)
            );

            $this->run(
                ['git', 'checkout'],
                base_path($targetDir)
            );
        }
    }

    /**
     * Fetch and reset to latest remote HEAD.
     *
     * Uses fetch + reset instead of pull to handle upstream force-pushes
     * that corrupt shallow clone history.
     */
    public function update(string $targetDir, ?string $branch = null): void
    {
        $cwd = base_path($targetDir);
        $branch ??= trim($this->runAndReturn(
            ['git', 'symbolic-ref', '--quiet', '--short', 'HEAD'],
            $cwd
        ));

        $this->run(
            ['git', 'fetch', '--depth=1', '--prune', 'origin', "+refs/heads/{$branch}:refs/remotes/origin/{$branch}"],
            $cwd
        );

        $this->run(
            ['git', 'checkout', '-B', $branch, "origin/{$branch}"],
            $cwd
        );
    }

    /**
     * @param  list<string>  $command
     */
    protected function run(array $command, string $cwd): void
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                sprintf('Command failed: %s — %s', implode(' ', $command), $process->getErrorOutput())
            );
        }
    }

    /**
     * @param  list<string>  $command
     */
    protected function runAndReturn(array $command, string $cwd): string
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                sprintf('Command failed: %s — %s', implode(' ', $command), $process->getErrorOutput())
            );
        }

        return $process->getOutput();
    }
}
