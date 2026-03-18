<?php

namespace MohamedSaid\LaravelMissingTranslations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \MohamedSaid\LaravelMissingTranslations\LaravelMissingTranslations
 */
class LaravelMissingTranslations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \MohamedSaid\LaravelMissingTranslations\LaravelMissingTranslations::class;
    }
}
