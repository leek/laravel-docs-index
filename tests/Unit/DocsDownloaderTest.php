<?php

use Leek\LaravelDocsIndex\DocsIndex\DocsDownloader;

it('updates shallow clones by fetching the remote branch tip and resetting the local branch to it', function (): void {
    $downloader = new class extends DocsDownloader
    {
        public array $calls = [];

        protected function run(array $command, string $cwd): void
        {
            $this->calls[] = ['run', $command, $cwd];
        }

        protected function runAndReturn(array $command, string $cwd): string
        {
            $this->calls[] = ['runAndReturn', $command, $cwd];

            return "12.x\n";
        }
    };

    $downloader->update('.laravel-docs/laravel-docs');

    expect($downloader->calls)->toBe([
        [
            'runAndReturn',
            ['git', 'symbolic-ref', '--quiet', '--short', 'HEAD'],
            base_path('.laravel-docs/laravel-docs'),
        ],
        [
            'run',
            ['git', 'fetch', '--depth=1', '--prune', 'origin', '+refs/heads/12.x:refs/remotes/origin/12.x'],
            base_path('.laravel-docs/laravel-docs'),
        ],
        [
            'run',
            ['git', 'checkout', '-B', '12.x', 'origin/12.x'],
            base_path('.laravel-docs/laravel-docs'),
        ],
    ]);
});
