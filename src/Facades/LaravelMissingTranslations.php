<?php

namespace MohamedSaid\LaravelMissingTranslations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mohamed Said\LaravelMissingTranslations\LaravelMissingTranslations
 */
class LaravelMissingTranslations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Mohamed Said\LaravelMissingTranslations\LaravelMissingTranslations::class;
    }
}
