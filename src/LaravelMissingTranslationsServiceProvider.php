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
            ->name('laravelmissingtranslations')
            ->hasConfigFile()
            ->hasCommand(LaravelMissingTranslationsCommand::class);
    }

    public function packageBooted(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravelmissingtranslations.php' => config_path('laravelmissingtranslations.php'),
        ], 'missing-translations-config');
    }
}
