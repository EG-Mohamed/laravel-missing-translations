<?php

namespace Mohamed Said\LaravelMissingTranslations;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Mohamed Said\LaravelMissingTranslations\Commands\LaravelMissingTranslationsCommand;

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
