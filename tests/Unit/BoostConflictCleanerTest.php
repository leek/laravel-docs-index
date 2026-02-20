<?php

use Leek\LaravelDocsIndex\DocsIndex\BoostConflictCleaner;

it('removes the searching documentation section', function (): void {
    $content = <<<'MD'
        === boost rules ===

        # Laravel Boost

        - Laravel Boost is an MCP server.

        ## Artisan

        - Use the `list-artisan-commands` tool.

        ## Searching Documentation (Critically Important)

        - Boost comes with a powerful `search-docs` tool you should use before trying other approaches.
        - Search the documentation before making code changes.
        - Use multiple, broad, simple, topic-based queries at once.
        - Do not add package names to queries.

        ### Available Search Syntax

        1. Simple Word Searches with auto-stemming.
        2. Multiple Words (AND Logic).
        3. Quoted Phrases (Exact Position).
        4. Mixed Queries.
        5. Multiple Queries.

        === php rules ===

        # PHP

        - Always use curly braces.
        MD;

    $cleaned = (new BoostConflictCleaner)->clean($content);

    expect($cleaned)
        ->not->toContain('Searching Documentation')
        ->not->toContain('Available Search Syntax')
        ->not->toContain('auto-stemming')
        ->toContain('# Laravel Boost')
        ->toContain('## Artisan')
        ->toContain('=== php rules ===')
        ->toContain('Always use curly braces');
});

it('removes individual search-docs reference lines', function (): void {
    $content = <<<'MD'
        === mcp/core rules ===

        # Laravel MCP

        - Laravel MCP allows you to rapidly build MCP servers.
        - IMPORTANT: laravel/mcp is very new. Always use the `search-docs` tool for authoritative documentation.
        - IMPORTANT: Activate `mcp-development` every time.

        === tailwindcss/core rules ===

        # Tailwind CSS

        - Always use existing Tailwind conventions.
        - IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation.
        - IMPORTANT: Activate `tailwindcss-development` every time.
        MD;

    $cleaned = (new BoostConflictCleaner)->clean($content);

    expect($cleaned)
        ->not->toContain('search-docs')
        ->toContain('Laravel MCP allows you to rapidly build MCP servers')
        ->toContain('Activate `mcp-development`')
        ->toContain('Always use existing Tailwind conventions')
        ->toContain('Activate `tailwindcss-development`');
});

it('handles content with no boost conflicts', function (): void {
    $content = <<<'MD'
        # My Project

        Some guidelines here.

        ## Rules

        - Follow conventions.
        MD;

    $cleaned = (new BoostConflictCleaner)->clean($content);

    expect($cleaned)->toBe($content);
});

it('handles all conflicts together', function (): void {
    $content = <<<'MD'
        <!-- LARAVEL-DOCS-INDEX:START -->
        [Laravel Docs Index]|root: .laravel-docs|CRITICAL: Read local docs.
        <!-- LARAVEL-DOCS-INDEX:END -->

        <laravel-boost-guidelines>

        ## Artisan

        - Use the `list-artisan-commands` tool.

        ## Searching Documentation (Critically Important)

        - Boost comes with a powerful `search-docs` tool you should use.
        - Search the documentation before making code changes.

        ### Available Search Syntax

        1. Simple Word Searches.
        2. Multiple Words.

        === mcp/core rules ===

        - Laravel MCP allows you to build MCP servers.
        - IMPORTANT: Always use the `search-docs` tool for authoritative documentation.
        - IMPORTANT: Activate `mcp-development` every time.

        === tailwindcss/core rules ===

        - Always use existing Tailwind conventions.
        - IMPORTANT: Always use `search-docs` tool for version-specific docs.
        - IMPORTANT: Activate `tailwindcss-development` every time.

        </laravel-boost-guidelines>
        MD;

    $cleaned = (new BoostConflictCleaner)->clean($content);

    expect($cleaned)
        ->not->toContain('Searching Documentation')
        ->not->toContain('Available Search Syntax')
        ->not->toContain('search-docs')
        ->toContain('LARAVEL-DOCS-INDEX:START')
        ->toContain('## Artisan')
        ->toContain('list-artisan-commands')
        ->toContain('Activate `mcp-development`')
        ->toContain('Activate `tailwindcss-development`')
        ->toContain('laravel-boost-guidelines');
});

it('collapses excessive blank lines after removal', function (): void {
    $content = "line one\n\n\n\n\nline two";

    $cleaned = (new BoostConflictCleaner)->clean($content);

    expect($cleaned)->toBe("line one\n\nline two");
});
