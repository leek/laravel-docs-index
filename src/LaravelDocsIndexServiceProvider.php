<?php

declare(strict_types=1);

namespace Leek\LaravelDocsIndex;

use Illuminate\Support\ServiceProvider;
use Leek\LaravelDocsIndex\Console\DocsIndexCommand;

class LaravelDocsIndexServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DocsIndexCommand::class,
            ]);
        }
    }
}
