<?php

namespace MohamedSaid\LaravelMissingTranslations\Commands;

use Illuminate\Console\Command;
use MohamedSaid\LaravelMissingTranslations\LaravelMissingTranslations;

class LaravelMissingTranslationsCommand extends Command
{
    public $signature = 'missing-translations
                        {locale? : The locale to process (e.g. en, ar)}
                        {--dry-run : Show missing keys without writing to file}
                        {--all : Process all existing JSON locale files}
                        {--remove-unused : Remove keys that exist in the JSON file but are not found in any scanned file}
                        {--json : Output results as JSON to stdout}';

    public $description = 'Scan project files and append missing translation keys to JSON locale files';

    public function handle(LaravelMissingTranslations $scanner): int
    {
        $locales = $this->resolveLocales();

        if (empty($locales)) {
            $this->error(__('No locale specified. Provide a locale argument or use --all.'));

            return self::FAILURE;
        }

        if ($this->option('json')) {
            return $this->handleJsonOutput($scanner, $locales);
        }

        foreach ($locales as $locale) {
            $this->processLocale($scanner, $locale);
        }

        return self::SUCCESS;
    }

    private function resolveLocales(): array
    {
        if ($this->option('all')) {
            $files = glob(lang_path('*.json'));

            if (empty($files)) {
                $this->error(__('No JSON locale files found. Provide a locale argument to create one.'));
                return [];
            }

            return array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), $files);
        }

        $locale = $this->argument('locale') ?? config('app.locale');

        if (! $locale) {
            return [];
        }

        return [$locale];
    }

    private function processLocale(LaravelMissingTranslations $scanner, string $locale): void
    {
        $this->info(__('Scanning for missing translations in locale: :locale', ['locale' => $locale]));

        $this->output->progressStart();
        $missingKeys = $scanner->getMissingKeys($locale);
        $this->output->progressFinish();

        $langFile = lang_path($locale . '.json');
        $existingKeys = file_exists($langFile)

            ? array_keys(json_decode(file_get_contents($langFile), true) ?? [])
            : [];

        $unusedKeys = $this->option('remove-unused') ? $scanner->getUnusedKeys($locale) : [];

        if (!empty($missingKeys)) {
            $rows = array_map(fn ($key) => [$key], $missingKeys);
            $this->table([__('Missing Key')], $rows);
        }

        if ($this->option('remove-unused') && !empty($unusedKeys)) {
            $rows = array_map(fn($key) => [$key], $unusedKeys);
            $this->table([__('Unused Key')], $rows);
        }

        $allScannedKeys = array_unique(array_merge($existingKeys, $missingKeys));

        $this->line(__('Keys scanned: :total | Existing: :existing | Missing: :missing', [
            'total' => count($allScannedKeys),
            'existing' => count($existingKeys),
            'missing' => count($missingKeys),
        ]));

        if ($this->option('remove-unused')) {
            $this->line(__('Unused keys: :count', ['count' => count($unusedKeys)]));
        }

        if ($this->option('dry-run')) {
            $this->warn(__('Dry run mode: no changes written.'));

            return;
        }

        if (!empty($missingKeys)) {
            $written = $scanner->writeToJson($locale, $missingKeys);
            $this->info(__('Written :count new key(s) to lang/:locale.json.', [
                'count' => $written,
                'locale' => $locale,
            ]));
        } else {
            $this->info(__('No missing translations found for locale: :locale', ['locale' => $locale]));
        }

        if ($this->option('remove-unused') && !empty($unusedKeys)) {
            $removed = $scanner->removeKeys($locale, $unusedKeys);
            $this->info(__('Removed :count unused key(s) from lang/:locale.json.', [
                'count' => $removed,
                'locale' => $locale,
            ]));
        }
    }

    private function handleJsonOutput(LaravelMissingTranslations $scanner, array $locales): int
    {
        $result = [];

        foreach ($locales as $locale) {
            $missingKeys = $scanner->getMissingKeys($locale);
            $langFile = lang_path($locale . '.json');
            $existingKeys = file_exists($langFile)
                ? array_keys(json_decode(file_get_contents($langFile), true) ?? [])
                : [];

            $entry = [
                'locale' => $locale,
                'existing_count' => count($existingKeys),
                'missing_count' => count($missingKeys),
                'missing_keys' => $missingKeys,
            ];

            if ($this->option('remove-unused')) {
                $unusedKeys = $scanner->getUnusedKeys($locale);
                $entry['unused_count'] = count($unusedKeys);
                $entry['unused_keys'] = $unusedKeys;
            }

            $result[] = $entry;

            if (!$this->option('dry-run') && !empty($missingKeys)) {
                $scanner->writeToJson($locale, $missingKeys);
            }

            if ($this->option('remove-unused') && !$this->option('dry-run') && !empty($entry['unused_keys'] ?? [])) {
                $scanner->removeKeys($locale, $entry['unused_keys']);
            }
        }

        $this->line(json_encode(count($result) === 1 ? $result[0] : $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
