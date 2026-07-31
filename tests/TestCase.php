<?php

namespace ZarulIzham\OpenWa\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ZarulIzham\OpenWa\OpenWaServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            OpenWaServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'array');
    }
}
