<?php

declare(strict_types=1);

namespace Leek\LaravelDocsIndex\Console;

use Illuminate\Console\Command;
use Leek\LaravelDocsIndex\DocsIndex\AgentDetector;
use Leek\LaravelDocsIndex\DocsIndex\DocsDownloader;
use Leek\LaravelDocsIndex\DocsIndex\DocsIndexInjector;
use Leek\LaravelDocsIndex\DocsIndex\DocsRegistry;
use Leek\LaravelDocsIndex\DocsIndex\IndexGenerator;
use Leek\LaravelDocsIndex\Support\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

#[AsCommand('docs:index', 'Download package docs locally and generate index in guidelines files')]
class DocsIndexCommand extends Command
{
    protected $signature = 'docs:index
        {--force : Force re-clone even if docs already exist}
        {--package=* : Only process specific packages}';

    public function handle(
        Config $config,
        AgentDetector $agentDetector,
        DocsDownloader $downloader,
        IndexGenerator $indexer,
        DocsIndexInjector $injector,
    ): int {
        $outputDir = $config->getOutputDir();
        $installedPackages = $this->getInstalledPackages();
        $packageFilter = $this->option('package');
        $repoPaths = [];

        // First run setup
        if (! $config->exists()) {
            $this->setupConfig($config, $agentDetector);
        }

        $this->info('Downloading documentation...');
        $this->newLine();

        // Process symlinks
        foreach (DocsRegistry::symlinks() as $name => $symlinkConfig) {
            if (! isset($installedPackages[$symlinkConfig['package']])) {
                $this->line("  <comment>Skipping {$name}</comment> — {$symlinkConfig['package']} not installed");

                continue;
            }

            $sourcePath = base_path($symlinkConfig['source']);
            $linkPath = base_path($outputDir.'/'.$name);

            if (! is_dir($sourcePath)) {
                $this->line("  <comment>Skipping {$name}</comment> — source path missing");

                continue;
            }

            if (is_link($linkPath)) {
                $this->line("  <info>{$name}</info> → symlink exists");
            } elseif (is_dir($linkPath)) {
                $this->line("  <info>{$name}</info> → directory exists (not a symlink)");
            } else {
                $targetDir = dirname($linkPath);

                if (! is_dir($targetDir)) {
                    if (! @mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
                        $this->error("  Failed to create directory: {$targetDir}");
                        continue;
                    }
                }

                symlink($sourcePath, $linkPath);
                $this->line("  <info>{$name}</info> → symlinked");
            }

            $repoPaths[$name] = '/';
        }

        // Process repos
        $repos = DocsRegistry::installedRepos($installedPackages);

        foreach ($repos as $key => $repoConfig) {
            if (! empty($packageFilter) && ! in_array($key, $packageFilter, true)) {
                continue;
            }

            $version = $installedPackages[$repoConfig['version_from']];
            $majorVersion = explode('.', $version)[0];
            $branch = str_replace('{major}', $majorVersion, $repoConfig['branch']);
            $repo = $repoConfig['repo'];
            $sparsePath = $repoConfig['path'];

            $repoSubDir = str_replace('/', '-', $repo);
            $targetSubDir = $outputDir.'/'.$repoSubDir;

            $repoPaths[$repoSubDir] = $sparsePath;

            $this->line("  <info>{$key}</info> → {$repo}@{$branch}");

            try {
                if (is_dir(base_path($targetSubDir)) && ! $this->option('force')) {
                    $downloader->update($targetSubDir);
                    $this->line('    Updated (git pull)');
                } else {
                    if (is_dir(base_path($targetSubDir))) {
                        $this->deleteDirectory(base_path($targetSubDir));
                    }

                    $downloader->download($repo, $branch, $sparsePath, $targetSubDir);
                    $this->line('    Cloned');
                }
            } catch (Throwable $e) {
                $this->error("    Failed: {$e->getMessage()}");

                continue;
            }
        }

        // Generate index
        $this->newLine();
        $this->line('Generating index...');
        $index = $indexer->generate($outputDir, $repoPaths);

        if (empty($index)) {
            $this->warn('No docs found — skipping injection.');

            return self::SUCCESS;
        }

        // Inject into each configured agent file
        $agents = $config->getAgents();

        if (empty($agents)) {
            $this->warn('No agent files configured — skipping injection.');
            $this->line('Run <info>php artisan docs:index</info> again after adding agent files to docs-index.json');

            return self::SUCCESS;
        }

        foreach ($agents as $filePath) {
            $injector->inject($filePath, $index);
            $this->line("Injected index into <info>{$filePath}</info>");
        }

        $this->ensureGitignore($outputDir);

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function setupConfig(Config $config, AgentDetector $agentDetector): void
    {
        $this->info('First-time setup...');
        $this->newLine();

        // Detect existing agent files
        $detected = $agentDetector->detect();

        if (empty($detected)) {
            $this->warn('No agent guidelines files detected.');

            $create = confirm(
                label: 'Would you like to create a CLAUDE.md file?',
                default: true
            );

            if ($create) {
                $detected = ['CLAUDE.md'];
            }
        } else {
            $this->line('Detected agent files:');
            foreach ($detected as $file) {
                $this->line("  - {$file}");
            }
            $this->newLine();
        }

        // Let user select which files to inject into
        if (count($detected) > 1) {
            $selected = multiselect(
                label: 'Which files should receive the docs index?',
                options: array_combine($detected, $detected),
                default: $detected,
                required: true
            );
            $agents = array_values($selected);
        } else {
            $agents = $detected;
        }

        $config->setAgents($agents);

        $this->line('Configuration saved to <info>docs-index.json</info>');
        $this->newLine();
    }

    /**
     * @return array<string, string>
     */
    private function getInstalledPackages(): array
    {
        $lockPath = base_path('composer.lock');

        if (! file_exists($lockPath)) {
            return [];
        }

        $lock = json_decode(file_get_contents($lockPath), true);
        $packages = [];

        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $pkg) {
            $packages[$pkg['name']] = ltrim($pkg['version'], 'v');
        }

        return $packages;
    }

    private function ensureGitignore(string $outputDir): void
    {
        $gitignorePath = base_path('.gitignore');
        $entry = '/'.$outputDir;

        if (! file_exists($gitignorePath)) {
            return;
        }

        $content = file_get_contents($gitignorePath);

        if (str_contains($content, $entry)) {
            return;
        }

        file_put_contents($gitignorePath, "\n# Local docs for AI agents\n{$entry}\n", FILE_APPEND);
        $this->line("Added <info>{$entry}</info> to .gitignore");
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        @rmdir($dir);
    }
}
