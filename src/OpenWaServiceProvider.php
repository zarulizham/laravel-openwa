<?php

namespace ZarulIzham\OpenWa;

use Illuminate\Notifications\ChannelManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use ZarulIzham\OpenWa\Notifications\Channels\WhatsAppChannel;

class OpenWaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('openwa')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OpenWaClient::class, function ($app) {
            return new OpenWaClient(
                baseUrl: (string) $app['config']->get('openwa.base_url'),
                apiKey: (string) $app['config']->get('openwa.api_key'),
                timeout: (int) $app['config']->get('openwa.timeout', 30),
                sessionName: $app['config']->get('openwa.session_name'),
            );
        });
    }

    public function packageBooted(): void
    {
        $this->app->make(ChannelManager::class)->extend('openwa', function ($app) {
            return $app->make(WhatsAppChannel::class);
        });
    }
}
