<?php

namespace Mohamed Said\LaravelMissingTranslations\Commands;

use Illuminate\Console\Command;
use Mohamed Said\LaravelMissingTranslations\LaravelMissingTranslations;

class LaravelMissingTranslationsCommand extends Command
{
    public $signature = 'missing-translations
                        {locale? : The locale to process (e.g. en, ar)}
                        {--dry-run : Show missing keys without writing to file}
                        {--all : Process all existing JSON locale files}';

    public $description = 'Scan project files and append missing translation keys to JSON locale files';

    public function handle(LaravelMissingTranslations $scanner): int
    {
        $locales = $this->resolveLocales();

        if (empty($locales)) {
            $this->error(__('No locale specified. Provide a locale argument or use --all.'));
            return self::FAILURE;
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
            return array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), $files);
        }

        $locale = $this->argument('locale');

        if (!$locale) {
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

        if (empty($missingKeys)) {
            $this->info(__('No missing translations found for locale: :locale', ['locale' => $locale]));
            return;
        }

        $rows = array_map(fn($key) => [$key], $missingKeys);
        $this->table([__('Missing Key')], $rows);

        $this->line(__('Found :count missing key(s) for locale [:locale].', [
            'count' => count($missingKeys),
            'locale' => $locale,
        ]));

        if ($this->option('dry-run')) {
            $this->warn(__('Dry run mode: no changes written.'));
            return;
        }

        $written = $scanner->writeToJson($locale, $missingKeys);

        $this->info(__('Written :count new key(s) to lang/:locale.json.', [
            'count' => $written,
            'locale' => $locale,
        ]));
    }
}
