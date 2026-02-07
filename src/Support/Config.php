<?php

declare(strict_types=1);

namespace Leek\LaravelDocsIndex\Support;

use Illuminate\Support\Str;

class Config
{
    protected const FILE = 'docs-index.json';

    /**
     * Get the output directory for docs.
     */
    public function getOutputDir(): string
    {
        return $this->get('output_dir', '.laravel-docs');
    }

    /**
     * Set the output directory for docs.
     */
    public function setOutputDir(string $dir): void
    {
        $this->set('output_dir', $dir);
    }

    /**
     * Get the configured agent files to inject into.
     *
     * @return array<int, string>
     */
    public function getAgents(): array
    {
        return $this->get('agents', []);
    }

    /**
     * Set the agent files to inject into.
     *
     * @param  array<int, string>  $agents
     */
    public function setAgents(array $agents): void
    {
        $this->set('agents', $agents);
    }

    /**
     * Check if the config file exists and is valid.
     */
    public function isValid(): bool
    {
        $path = base_path(self::FILE);

        if (! file_exists($path)) {
            return false;
        }

        json_decode(file_get_contents($path), true);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if the config file exists.
     */
    public function exists(): bool
    {
        return file_exists(base_path(self::FILE));
    }

    /**
     * Delete the config file.
     */
    public function flush(): void
    {
        $path = base_path(self::FILE);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        $config = $this->all();

        return data_get($config, $key, $default);
    }

    protected function set(string $key, mixed $value): void
    {
        $config = array_filter($this->all(), fn ($value): bool => $value !== null && $value !== []);

        data_set($config, $key, $value);

        ksort($config);

        $path = base_path(self::FILE);

        file_put_contents($path, Str::of(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->append(PHP_EOL));
    }

    /**
     * @return array<string, mixed>
     */
    protected function all(): array
    {
        $path = base_path(self::FILE);

        if (! file_exists($path)) {
            return [];
        }

        $config = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $config ?? [];
    }
}
