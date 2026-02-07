<?php

declare(strict_types=1);

namespace Leek\LaravelDocsIndex\Tests;

use Leek\LaravelDocsIndex\LaravelDocsIndexServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDocsIndexServiceProvider::class,
        ];
    }
}
