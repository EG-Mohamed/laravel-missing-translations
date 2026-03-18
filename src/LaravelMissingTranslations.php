<?php

namespace MohamedSaid\LaravelMissingTranslations;

use Symfony\Component\Finder\Finder;

class LaravelMissingTranslations
{
    public function scan(): array
    {
        $keys = [];
        $paths = config('laravelmissingtranslations.paths', []);
        $extensions = config('laravelmissingtranslations.extensions', ['php', 'blade.php']);
        $excludePaths = config('laravelmissingtranslations.exclude_paths', []);

        $finder = new Finder;
        $finder->files()->in($paths);

        foreach ($extensions as $ext) {
            $finder->name('*.'.$ext);
        }

        foreach ($excludePaths as $excludePath) {
            $finder->exclude($excludePath);
        }

        foreach ($finder as $file) {
            $keys = array_merge($keys, $this->extractKeys($file->getRealPath()));
        }

        return array_values(array_unique($keys));
    }

    public function extractKeys(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $keys = [];
        $functions = config('laravelmissingtranslations.include_functions', []);

        $phpFunctions = array_filter($functions, fn ($f) => ! str_starts_with($f, '@') && ! str_starts_with($f, 'Lang::'));
        $bladeDirectives = array_filter($functions, fn ($f) => str_starts_with($f, '@'));
        $facadeMethods = array_filter($functions, fn ($f) => str_starts_with($f, 'Lang::'));

        if (! empty($phpFunctions)) {
            $escaped = array_map(fn ($f) => preg_quote($f, '/'), $phpFunctions);
            $pattern = '/(?:'.implode('|', $escaped).')\s*\(\s*([\'"])(.+?)\1/';
            if (preg_match_all($pattern, $content, $matches)) {
                $keys = array_merge($keys, $matches[2]);
            }
        }

        if (! empty($bladeDirectives)) {
            $names = array_map(fn ($f) => preg_quote(ltrim($f, '@'), '/'), $bladeDirectives);
            $pattern = '/@(?:'.implode('|', $names).')\s*\(\s*([\'"])(.+?)\1/';
            if (preg_match_all($pattern, $content, $matches)) {
                $keys = array_merge($keys, $matches[2]);
            }
        }

        if (! empty($facadeMethods)) {
            $methods = array_map(fn ($f) => preg_quote(explode('::', $f)[1], '/'), $facadeMethods);
            $pattern = '/Lang::(?:'.implode('|', $methods).')\s*\(\s*([\'"])(.+?)\1/';
            if (preg_match_all($pattern, $content, $matches)) {
                $keys = array_merge($keys, $matches[2]);
            }
        }

        return $keys;
    }

    public function getMissingKeys(string $locale): array
    {
        $allKeys = $this->scan();
        $langFile = lang_path($locale.'.json');
        $existing = [];

        if (file_exists($langFile)) {
            $existing = json_decode(file_get_contents($langFile), true) ?? [];
        }

        $excludeDotKeys = config('laravelmissingtranslations.exclude_dot_keys', false);
        $excludePatterns = config('laravelmissingtranslations.exclude_patterns', []);

        $missing = [];

        foreach ($allKeys as $key) {
            if (array_key_exists($key, $existing)) {
                continue;
            }

            if ($excludeDotKeys && str_contains($key, '.')) {
                continue;
            }

            $excluded = false;
            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $key)) {
                    $excluded = true;
                    break;
                }
            }

            if (! $excluded) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public function writeToJson(string $locale, array $missingKeys): int
    {
        $langDir = lang_path();
        $langFile = lang_path($locale.'.json');

        if (! is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }

        $existing = [];

        if (file_exists($langFile)) {
            $existing = json_decode(file_get_contents($langFile), true) ?? [];
        }

        $newEntries = [];
        foreach ($missingKeys as $key) {
            $newEntries[$key] = '';
        }

        $merged = array_merge($existing, $newEntries);

        if (config('laravelmissingtranslations.sort_keys', true)) {
            ksort($merged);
        }

        $flags = config('laravelmissingtranslations.json_flags', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($langFile, json_encode($merged, $flags));

        return count($newEntries);
    }
}
