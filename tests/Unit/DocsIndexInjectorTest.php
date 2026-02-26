<?php

use Leek\LaravelDocsIndex\DocsIndex\DocsIndexInjector;

beforeEach(function (): void {
    $this->testFile = 'test-inject-'.uniqid().'.md';
    $this->testFilePath = base_path($this->testFile);
});

afterEach(function (): void {
    if (file_exists($this->testFilePath)) {
        @unlink($this->testFilePath);
    }
});

it('injects into a new file', function (): void {
    $injector = new DocsIndexInjector;
    $injector->inject($this->testFile, 'test-index-content');

    $content = file_get_contents($this->testFilePath);

    expect($content)
        ->toContain('[LARAVEL-DOCS-INDEX:PREAMBLE]')
        ->toContain('## Local Docs:')
        ->toContain('IMPORTANT: Your training data is likely OUTDATED')
        ->toContain('[LARAVEL-DOCS-INDEX:START]')
        ->toContain('test-index-content')
        ->toContain('[LARAVEL-DOCS-INDEX:END]');
});

it('places preamble before the index markers', function (): void {
    $injector = new DocsIndexInjector;
    $injector->inject($this->testFile, 'test-index-content');

    $content = file_get_contents($this->testFilePath);

    $preamblePos = strpos($content, '[LARAVEL-DOCS-INDEX:PREAMBLE]');
    $startPos = strpos($content, '[LARAVEL-DOCS-INDEX:START]');
    $indexPos = strpos($content, 'test-index-content');

    expect($preamblePos)->toBeLessThan($startPos);
    expect($startPos)->toBeLessThan($indexPos);
});

it('prepends before existing content', function (): void {
    file_put_contents($this->testFilePath, 'existing content here');

    $injector = new DocsIndexInjector;
    $injector->inject($this->testFile, 'index-content');

    $content = file_get_contents($this->testFilePath);

    expect($content)
        ->toStartWith('[LARAVEL-DOCS-INDEX:PREAMBLE]')
        ->toContain('existing content here');

    // Ensure index comes before existing content
    $indexPos = strpos($content, 'index-content');
    $existingPos = strpos($content, 'existing content here');
    expect($indexPos)->toBeLessThan($existingPos);
});

it('replaces existing block in-place (idempotent)', function (): void {
    $injector = new DocsIndexInjector;

    $injector->inject($this->testFile, 'first-index');
    $injector->inject($this->testFile, 'second-index');

    $content = file_get_contents($this->testFilePath);

    expect($content)
        ->toContain('second-index')
        ->not->toContain('first-index');

    // Only one PREAMBLE marker should exist
    expect(substr_count($content, '[LARAVEL-DOCS-INDEX:PREAMBLE]'))->toBe(1);
    // Only one START marker should exist
    expect(substr_count($content, '[LARAVEL-DOCS-INDEX:START]'))->toBe(1);
});

it('preserves content after block', function (): void {
    file_put_contents($this->testFilePath, "# My Guidelines\n\nsome guidelines\n");

    $injector = new DocsIndexInjector;
    $injector->inject($this->testFile, 'index-content');

    $content = file_get_contents($this->testFilePath);

    expect($content)
        ->toContain('index-content')
        ->toContain('# My Guidelines');
});

it('replaces old HTML comment format on upgrade', function (): void {
    // Simulate old format (HTML comments, no preamble marker)
    $oldBlock = "<!-- LARAVEL-DOCS-INDEX:START -->\nold-index\n<!-- LARAVEL-DOCS-INDEX:END -->";
    file_put_contents($this->testFilePath, $oldBlock."\n\n# My Guidelines\n");

    $injector = new DocsIndexInjector;
    $injector->inject($this->testFile, 'new-index');

    $content = file_get_contents($this->testFilePath);

    expect($content)
        ->toContain('[LARAVEL-DOCS-INDEX:PREAMBLE]')
        ->toContain('new-index')
        ->not->toContain('old-index')
        ->toContain('# My Guidelines');

    expect(substr_count($content, '[LARAVEL-DOCS-INDEX:START]'))->toBe(1);
});

it('uses custom docsDir in preamble', function (): void {
    $injector = new DocsIndexInjector;
    $injector->inject($this->testFile, 'test-index', 'custom-docs');

    $content = file_get_contents($this->testFilePath);

    expect($content)
        ->toContain('## Local Docs: custom-docs')
        ->toContain('`custom-docs/`');
});
