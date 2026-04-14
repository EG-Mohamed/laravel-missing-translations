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
        $functions = config('laravelmissingtranslations.include_functions', []);

        $finder = new Finder;
        $finder->files()->in($paths);

        foreach ($extensions as $ext) {
            $finder->name('*.'.$ext);
        }

        foreach ($excludePaths as $excludePath) {
            $finder->exclude($excludePath);
        }

        foreach ($finder as $file) {
            $keys = array_merge($keys, $this->extractKeys($file->getRealPath(), $functions));
        }

        return array_values(array_unique($keys));
    }

    public function extractKeys(string $filePath, array $functions = []): array
    {
        $content = file_get_contents($filePath);
        $keys = [];

        if (empty($functions)) {
            $functions = config('laravelmissingtranslations.include_functions', []);
        }

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

        if (config('laravelmissingtranslations.filament.enabled', false)) {
            $keys = array_merge($keys, $this->extractFilamentKeys($content));
        }

        return $keys;
    }

    private function extractFilamentKeys(string $content): array
    {
        $keys = [];

        $methods = config('laravelmissingtranslations.filament.methods', []);

        if (! empty($methods)) {
            $escaped = array_map(fn ($m) => preg_quote($m, '/'), $methods);
            $pattern = '/->(?:'.implode('|', $escaped).')\s*\(\s*([\'"])(.+?)\1\s*\)/';
            if (preg_match_all($pattern, $content, $matches)) {
                $keys = array_merge($keys, $matches[2]);
            }
        }

        $staticClasses = config('laravelmissingtranslations.filament.static_methods', []);

        if (! empty($staticClasses)) {
            $escaped = array_map(fn ($c) => preg_quote($c, '/'), $staticClasses);
            $pattern = '/(?:'.implode('|', $escaped).')::make\s*\(\s*([\'"])(.+?)\1\s*\)/';
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[2] as $value) {
                    if (str_contains($value, ' ') || preg_match('/^[A-Z][a-z]/', $value)) {
                        $keys[] = $value;
                    }
                }
            }
        }

        $autoLabelFields = config('laravelmissingtranslations.filament.auto_label_fields', []);
        $autoLabelColumns = config('laravelmissingtranslations.filament.auto_label_columns', []);

        $allAutoComponents = array_merge(
            array_map(fn ($c) => [$c, 'field'], $autoLabelFields),
            array_map(fn ($c) => [$c, 'column'], $autoLabelColumns),
        );

        if (! empty($allAutoComponents)) {
            $allNames = array_map(fn ($item) => preg_quote($item[0], '/'), $allAutoComponents);
            $pattern = '/(?:'.implode('|', $allNames).')::make\s*\(\s*([\'"])([A-Za-z_][A-Za-z0-9_.]*)\1\s*\)/';

            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $i => $fullMatch) {
                    $componentName = preg_replace('/::make.*/', '', $fullMatch[0]);
                    $fieldName = $matches[2][$i][0];
                    $startOffset = $fullMatch[1] + strlen($fullMatch[0]);
                    $endOffset = isset($matches[0][$i + 1])
                        ? $matches[0][$i + 1][1]
                        : min(strlen($content), $startOffset + 2000);

                    $chain = substr($content, $startOffset, $endOffset - $startOffset);

                    if (preg_match('/->\s*(?:label|translateLabel)\s*\(/', $chain)) {
                        continue;
                    }

                    $isColumn = in_array($componentName, $autoLabelColumns);
                    $keys[] = $isColumn
                        ? $this->humanizeColumnName($fieldName)
                        : $this->humanizeFieldName($fieldName);
                }
            }
        }

        return $keys;
    }

    private function humanizeFieldName(string $name): string
    {
        if (str_contains($name, '.')) {
            $name = substr($name, strrpos($name, '.') + 1);
        }

        $name = preg_replace('/(?<=[a-z])(?=[A-Z])/', '-', $name);
        $name = preg_replace('/[_-]+/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return ucfirst(strtolower($name));
    }

    private function humanizeColumnName(string $name): string
    {
        if (str_contains($name, '.')) {
            $lastDot = strrpos($name, '.');
            $beforeLast = substr($name, 0, $lastDot);
            $secondLastDot = strrpos($beforeLast, '.');
            $name = $secondLastDot !== false
                ? substr($beforeLast, $secondLastDot + 1)
                : $beforeLast;
        }

        $name = preg_replace('/(?<=[a-z])(?=[A-Z])/', '-', $name);
        $name = preg_replace('/[_-]+/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return ucfirst(strtolower($name));
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

    public function getUnusedKeys(string $locale): array
    {
        $langFile = lang_path($locale.'.json');

        if (! file_exists($langFile)) {
            return [];
        }

        $existing = json_decode(file_get_contents($langFile), true) ?? [];
        $allKeys = $this->scan();

        return array_values(array_diff(array_keys($existing), $allKeys));
    }

    public function writeToJson(string $locale, array $missingKeys): int
    {
        $langDir = lang_path();
        $langFile = lang_path($locale.'.json');

        if (! is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }

        $fp = fopen($langFile, file_exists($langFile) ? 'r+' : 'w+');
        flock($fp, LOCK_EX);

        $existing = [];
        $size = fstat($fp)['size'] ?? 0;

        if ($size > 0) {
            $raw = fread($fp, $size);
            $existing = json_decode($raw, true) ?? [];
        }

        $newEntries = [];
        foreach ($missingKeys as $key) {
            if (! array_key_exists($key, $existing)) {
                $newEntries[$key] = '';
            }
        }

        $merged = array_merge($existing, $newEntries);

        if (config('laravelmissingtranslations.sort_keys', true)) {
            ksort($merged);
        }

        $flags = config('laravelmissingtranslations.json_flags', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $json = json_encode($merged, $flags);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json);
        flock($fp, LOCK_UN);
        fclose($fp);

        return count($newEntries);
    }

    public function removeKeys(string $locale, array $keys): int
    {
        $langFile = lang_path($locale.'.json');

        if (! file_exists($langFile) || empty($keys)) {
            return 0;
        }

        $fp = fopen($langFile, 'r+');
        flock($fp, LOCK_EX);

        $size = fstat($fp)['size'] ?? 0;
        $existing = [];

        if ($size > 0) {
            $raw = fread($fp, $size);
            $existing = json_decode($raw, true) ?? [];
        }

        $removed = 0;
        foreach ($keys as $key) {
            if (array_key_exists($key, $existing)) {
                unset($existing[$key]);
                $removed++;
            }
        }

        if ($removed > 0) {
            if (config('laravelmissingtranslations.sort_keys', true)) {
                ksort($existing);
            }

            $flags = config('laravelmissingtranslations.json_flags', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $json = json_encode($existing, $flags);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json);
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        return $removed;
    }
}
