<?php

namespace MohamedSaid\LaravelMissingTranslations;

use MohamedSaid\LaravelMissingTranslations\Commands\LaravelMissingTranslationsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMissingTranslationsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-missing-translations')
            ->hasConfigFile()
            ->hasCommand(LaravelMissingTranslationsCommand::class);
    }

    public function packageBooted(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-missing-translations.php' => config_path('laravel-missing-translations.php'),
        ], 'missing-translations-config');
    }
}
