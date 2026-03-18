<?php

namespace MohamedSaid\LaravelMissingTranslations;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use MohamedSaid\LaravelMissingTranslations\Commands\LaravelMissingTranslationsCommand;

class LaravelMissingTranslationsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravelmissingtranslations')
            ->hasConfigFile()
            ->hasCommand(LaravelMissingTranslationsCommand::class);
    }
}
