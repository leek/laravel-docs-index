<?php

use Leek\LaravelDocsIndex\DocsIndex\DocsDownloader;

beforeEach(function (): void {
    $this->configPath = base_path('docs-index.json');
    $this->lockPath = base_path('composer.lock');
    $this->agentPath = base_path('CLAUDE.md');
    $this->docsPath = base_path('.laravel-docs');

    file_put_contents($this->configPath, json_encode([
        'agents' => ['CLAUDE.md'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    file_put_contents($this->lockPath, json_encode([
        'packages' => [
            [
                'name' => 'laravel/framework',
                'version' => 'v12.0.0',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
});

afterEach(function (): void {
    foreach ([$this->configPath, $this->lockPath, $this->agentPath] as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    if (! is_dir($this->docsPath)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->docsPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
    }

    @rmdir($this->docsPath);
});

it('re-clones a broken existing checkout instead of surfacing the update failure', function (): void {
    @mkdir($this->docsPath.'/laravel-docs', 0755, true);
    file_put_contents($this->docsPath.'/laravel-docs/stale.md', '# stale');

    $this->app->instance('docs-downloader.calls', []);
    $this->app->instance(DocsDownloader::class, new class($this->app) extends DocsDownloader
    {
        public function __construct(private $app) {}

        public function update(string $targetDir, ?string $branch = null): void
        {
            $calls = $this->app->make('docs-downloader.calls');
            $calls[] = ['update', $targetDir, $branch];
            $this->app->instance('docs-downloader.calls', $calls);

            throw new RuntimeException('simulated corrupt shallow clone');
        }

        public function download(string $repo, string $branch, string|array $sparsePaths, string $targetDir): void
        {
            $calls = $this->app->make('docs-downloader.calls');
            $calls[] = ['download', $repo, $branch, $sparsePaths, $targetDir];
            $this->app->instance('docs-downloader.calls', $calls);

            @mkdir(base_path($targetDir), 0755, true);
            file_put_contents(base_path($targetDir.'/fresh.md'), '# fresh');
        }
    });

    $this->artisan('docs:index')
        ->expectsOutput('Downloading documentation...')
        ->expectsOutput('  laravel/docs → laravel/docs@12.x')
        ->expectsOutput('    Update failed (corrupt clone?) — re-cloning...')
        ->expectsOutput('    Re-cloned')
        ->doesntExpectOutput('    Updated (git pull)')
        ->expectsOutput('Generating index...')
        ->expectsOutput('Injected index into CLAUDE.md')
        ->expectsOutput('Done.')
        ->assertSuccessful();

    expect($this->app->make('docs-downloader.calls'))->toBe([
        ['update', '.laravel-docs/laravel-docs', '12.x'],
        ['download', 'laravel/docs', '12.x', '/', '.laravel-docs/laravel-docs'],
    ]);

    expect(file_get_contents($this->agentPath))
        ->toContain('[LARAVEL-DOCS-INDEX:START]')
        ->toContain('fresh.md')
        ->not->toContain('stale.md');
});
