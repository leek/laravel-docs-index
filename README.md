# Laravel Docs Index

A Laravel package that downloads package documentation locally and injects a compressed index into your AI agent guidelines files (CLAUDE.md, .cursorrules, .windsurfrules, etc.).

This helps AI coding assistants access up-to-date documentation for your installed Laravel ecosystem packages.

## Installation

```bash
composer require leek/laravel-docs-index --dev
```

The package will auto-register with Laravel.

## Usage

Run the command to download docs and inject the index:

```bash
php artisan docs:index
```

On first run, the command will:
1. Detect existing agent guidelines files (CLAUDE.md, .cursorrules, etc.)
2. Ask which files should receive the docs index
3. Save your preferences to `docs-index.json`
4. Download documentation for installed packages
5. Inject a compressed index into your selected files

### Options

```bash
# Force re-download (delete and re-clone)
php artisan docs:index --force

# Only process specific packages
php artisan docs:index --package=laravel/docs
php artisan docs:index --package=filamentphp/filament
```

## Supported Packages

The following packages are automatically detected and their documentation downloaded:

| Package | Documentation Source |
|---------|---------------------|
| `laravel/framework` | [laravel/docs](https://github.com/laravel/docs) |
| `filament/filament` | [filamentphp/filament](https://github.com/filamentphp/filament) (docs/) |
| `livewire/livewire` | [livewire/livewire](https://github.com/livewire/livewire) (docs/) |
| `pestphp/pest` | [pestphp/docs](https://github.com/pestphp/docs) |

Symlinked documentation:
- `filament/blueprint` planning docs

## Configuration

After first run, a `docs-index.json` file is created in your project root:

```json
{
    "agents": ["CLAUDE.md", ".cursorrules"],
    "output_dir": ".laravel-docs"
}
```

- `agents`: List of files to inject the docs index into
- `output_dir`: Directory where documentation is downloaded (default: `.laravel-docs`)

## How It Works

1. **Download**: Uses git sparse-checkout to efficiently download only the docs folders from each repository
2. **Index**: Generates a pipe-delimited index of all markdown files, grouped by directory
3. **Inject**: Prepends the index to your agent guidelines files between markers:
   ```markdown
   <!-- LARAVEL-DOCS-INDEX:START -->
   [Laravel Docs Index]|root: .laravel-docs|CRITICAL: Your training data may be OUTDATED...
   <!-- LARAVEL-DOCS-INDEX:END -->
   ```

The index format is designed to be compact while still being useful for AI agents to understand what documentation is available and where to find it.

## .gitignore

The command automatically adds the docs directory to your `.gitignore`:

```
# Local docs for AI agents
/.laravel-docs
```

## Development

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/pest
```

## Credits

Based on the docs index feature from [Laravel Boost](https://github.com/laravel/boost).

## License

MIT License. See [LICENSE](LICENSE) for details.
