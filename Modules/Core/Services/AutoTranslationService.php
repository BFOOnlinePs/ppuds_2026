<?php

namespace Modules\Core\Services;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Locale;
use Modules\Core\Ai\AutoTranslationAgent;
use Modules\Core\Settings\AutoTranslationSettings;
use Throwable;

/**
 * Fills the other languages of a translatable record from the one a person
 * just wrote, e.g. a company name typed in English gets its Arabic and
 * Hebrew values.
 *
 * A value is only ever written when it is empty, or when the AI wrote it
 * itself and nobody has edited it since (tracked in ai_translations). Text a
 * person typed is never overwritten.
 */
class AutoTranslationService
{
    private const TABLE = 'ai_translations';

    /**
     * The translated attributes the save that just finished wrote, keyed to
     * the locale they were written in. Astrotomic saves the translations in
     * its own saved listener, which runs before this is called.
     *
     * @return array<string, string>
     */
    public function changedSources(Model $model): array
    {
        if (! $model instanceof TranslatableContract || ! $model->relationLoaded('translations')) {
            return [];
        }

        if (in_array($model::class, (array) config('ai.auto_translation.except', []), true)) {
            return [];
        }

        $attributes = (array) ($model->translatedAttributes ?? []);
        $sources = [];

        foreach ($model->translations as $translation) {
            $changed = $translation->wasRecentlyCreated
                ? $attributes
                : array_intersect($attributes, array_keys($translation->getChanges()));

            foreach ($changed as $attribute) {
                if ($this->isTranslatableText($translation->getAttribute($attribute))) {
                    $sources[$attribute] = (string) $translation->getAttribute($model->getLocaleKey());
                }
            }
        }

        return $sources;
    }

    /** Whether a save made by the current process should be translated. */
    public function acceptsSaves(): bool
    {
        $settings = $this->settings();

        if (! $settings?->enabled || ! $this->aiIsConfigured($settings)) {
            return false;
        }

        return ! app()->runningInConsole() || $settings->translate_background_saves;
    }

    /** Whether a provider has credentials, for the settings screen. Blank means the configured default. */
    public function isReady(?string $provider = null): bool
    {
        return $this->providerIsConfigured(filled($provider)
            ? $provider
            : (config('ai.auto_translation.provider') ?: config('ai.default')));
    }

    /**
     * Every content locale, labelled in the interface language.
     *
     * @return array<string, string>
     */
    public function localeOptions(): array
    {
        return collect($this->contentLocales())
            ->mapWithKeys(fn (string $locale): array => [$locale => $this->languageName($locale, app()->getLocale())])
            ->all();
    }

    /**
     * @param  array<string, string>  $sources  attribute => locale it was written in
     */
    public function translate(Model $model, array $sources): void
    {
        $settings = $this->settings();

        // Re-checked here: the settings may have changed while the job waited.
        if (! $model instanceof TranslatableContract || ! $settings?->enabled || ! $this->aiIsConfigured($settings)) {
            return;
        }

        $model->load('translations');
        $tracked = $this->trackedValues($model);
        $work = [];

        foreach ($sources as $attribute => $sourceLocale) {
            $attribute = (string) $attribute;
            $sourceLocale = (string) $sourceLocale;
            $row = $tracked[$sourceLocale][$attribute] ?? null;

            // The AI had written this value and a person has now changed it,
            // so it is theirs from here on.
            if ($row && $row->value_hash !== sha1((string) $this->valueOf($model, $sourceLocale, $attribute))) {
                $this->forget($model, $sourceLocale, $attribute);
                unset($tracked[$sourceLocale][$attribute]);
            }

            $item = $this->pendingWork($model, $tracked, $settings, $attribute, $sourceLocale);

            if ($item !== null) {
                $work[$attribute] = $item;
            }
        }

        if ($work === []) {
            return;
        }

        $locales = collect($this->targetLocales($settings))
            ->merge(array_column($work, 'source_locale'))
            ->unique()
            ->values()
            ->all();

        $fields = $this->aiTranslations($model, $work, $locales, $settings);
        $writes = [];

        foreach ($work as $attribute => $item) {
            foreach ($this->resolveWrites($item, $fields[$attribute] ?? null, $settings) as $locale => $write) {
                $writes[$locale][$attribute] = $write;
            }
        }

        foreach ($writes as $locale => $values) {
            $this->write($model, $locale, $values);
        }
    }

    /**
     * What has to happen for one attribute, or null when nothing does.
     *
     * "stale" locales get a (new) translation. "open" locales may instead
     * receive the original text when it turns out to be written in their
     * language — they hold nothing, or only what the AI wrote.
     */
    private function pendingWork(Model $model, array $tracked, AutoTranslationSettings $settings, string $attribute, string $sourceLocale): ?array
    {
        $text = $this->valueOf($model, $sourceLocale, $attribute);

        if (! $this->isTranslatableText($text) || mb_strlen($text) > (int) config('ai.auto_translation.max_characters', 10000)) {
            return null;
        }

        $sourceHash = sha1($text);
        $sourceRow = $tracked[$sourceLocale][$attribute] ?? null;

        // Still exactly what the AI wrote there: nothing new to translate from.
        if ($sourceRow && $sourceRow->value_hash === $sourceHash) {
            return null;
        }

        $stale = [];
        $open = [];

        foreach ($this->targetLocales($settings) as $locale) {
            if ($locale === $sourceLocale) {
                continue;
            }

            $current = $this->valueOf($model, $locale, $attribute);
            $row = $tracked[$locale][$attribute] ?? null;

            if (blank($current)) {
                $stale[] = $locale;
                $open[$locale] = false;
            } elseif ($row && $row->value_hash === sha1($current)) {
                $fromThisText = $row->source_hash === $sourceHash;
                $open[$locale] = $fromThisText;

                if (! $fromThisText && $settings->update_machine_translations) {
                    $stale[] = $locale;
                }
            }
        }

        // A locale already translated from this exact text means its language
        // was detected before, so it is not worth an AI call on its own.
        $needsDetection = $settings->detect_source_language && in_array(false, $open, true);

        if ($stale === [] && ! $needsDetection) {
            return null;
        }

        return [
            'text' => $text,
            'source_locale' => $sourceLocale,
            'source_hash' => $sourceHash,
            'stale' => $stale,
            'open' => array_keys($open),
        ];
    }

    /**
     * @return array<string, array{value: string, machine: bool, source_locale: string, source_hash: string}>
     */
    private function resolveWrites(array $item, mixed $field, AutoTranslationSettings $settings): array
    {
        if (! is_array($field)) {
            return [];
        }

        $translations = collect((array) ($field['translations'] ?? []))
            ->map(fn ($value): string => is_string($value) ? trim($value) : '')
            ->filter(fn (string $value): bool => $value !== '');

        $sourceLocale = $item['source_locale'];
        $detected = (string) ($field['source_locale'] ?? '');
        $writes = [];

        // Typed in another language than the screen's (English on the Arabic
        // screen): keep it under its real language and translate the screen's.
        if ($settings->detect_source_language
            && $detected !== $sourceLocale
            && in_array($detected, $item['open'], true)
            && $translations->has($sourceLocale)) {
            $writes[$detected] = ['value' => $item['text'], 'machine' => false];
            $writes[$sourceLocale] = ['value' => $translations->get($sourceLocale), 'machine' => true];
            $sourceLocale = $detected;
        }

        foreach ($item['stale'] as $locale) {
            if (! isset($writes[$locale]) && $translations->has($locale)) {
                $writes[$locale] = ['value' => $translations->get($locale), 'machine' => true];
            }
        }

        return collect($writes)
            ->map(fn (array $write): array => $write + [
                'source_locale' => $sourceLocale,
                'source_hash' => $item['source_hash'],
            ])
            ->all();
    }

    private function aiTranslations(Model $model, array $work, array $locales, AutoTranslationSettings $settings): array
    {
        $response = AutoTranslationAgent::make(locales: $locales, attributes: array_keys($work))->prompt(
            $this->prompt($model, $work, $locales),
            provider: $this->configuredProvider($settings),
            model: $this->configuredModel($settings),
            timeout: $this->configuredTimeout(),
        );

        $result = method_exists($response, 'toArray') ? $response->toArray() : [];

        return (array) ($result['fields'] ?? []);
    }

    private function prompt(Model $model, array $work, array $locales): string
    {
        return json_encode([
            'task' => 'ترجم كل حقل إلى جميع اللغات المطلوبة وحدّد لغته الأصلية حسب schema فقط.',
            'record_type' => Str::headline(class_basename($model)),
            'languages' => collect($locales)
                ->mapWithKeys(fn (string $locale): array => [$locale => $this->languageName($locale)])
                ->all(),
            'fields' => collect($work)
                ->map(fn (array $item): array => [
                    'text' => $item['text'],
                    'saved_under_locale' => $item['source_locale'],
                ])
                ->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, array{value: string, machine: bool, source_locale: string, source_hash: string}>  $values
     */
    private function write(Model $model, string $locale, array $values): void
    {
        try {
            DB::transaction(function () use ($model, $locale, $values): void {
                $translation = $model->getTranslation($locale, false) ?? $model->getNewTranslation($locale);

                foreach ($values as $attribute => $write) {
                    $translation->setAttribute($attribute, $write['value']);
                }

                // Saved on the translation row, not the record, so the record's
                // saved event does not fire and loop back into this service.
                $translation->save();

                foreach ($values as $attribute => $write) {
                    $write['machine']
                        ? $this->remember($model, $locale, $attribute, $write)
                        : $this->forget($model, $locale, $attribute);
                }
            });
        } catch (Throwable $exception) {
            Log::warning('Auto translation could not be saved.', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'locale' => $locale,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function remember(Model $model, string $locale, string $attribute, array $write): void
    {
        DB::table(self::TABLE)->upsert([
            [
                'translatable_type' => $model->getMorphClass(),
                'translatable_id' => $model->getKey(),
                'locale' => $locale,
                'attribute' => $attribute,
                'source_locale' => $write['source_locale'],
                'source_hash' => $write['source_hash'],
                'value_hash' => sha1($write['value']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['translatable_type', 'translatable_id', 'locale', 'attribute'], ['source_locale', 'source_hash', 'value_hash', 'updated_at']);
    }

    private function forget(Model $model, string $locale, string $attribute): void
    {
        DB::table(self::TABLE)
            ->where('translatable_type', $model->getMorphClass())
            ->where('translatable_id', $model->getKey())
            ->where('locale', $locale)
            ->where('attribute', $attribute)
            ->delete();
    }

    /**
     * @return array<string, array<string, object>> locale => attribute => row
     */
    private function trackedValues(Model $model): array
    {
        return DB::table(self::TABLE)
            ->where('translatable_type', $model->getMorphClass())
            ->where('translatable_id', $model->getKey())
            ->get()
            ->groupBy('locale')
            ->map(fn ($rows): array => $rows->keyBy('attribute')->all())
            ->all();
    }

    private function valueOf(Model $model, string $locale, string $attribute): ?string
    {
        $value = $model->getTranslation($locale, false)?->getAttribute($attribute);

        return is_string($value) ? $value : null;
    }

    /** Needs a letter: numbers, codes and empty HTML are left as they are. */
    private function isTranslatableText(mixed $value): bool
    {
        return is_string($value) && preg_match('/\p{L}/u', strip_tags($value)) === 1;
    }

    private function targetLocales(AutoTranslationSettings $settings): array
    {
        return array_values(array_intersect($this->contentLocales(), (array) $settings->target_locales));
    }

    private function contentLocales(): array
    {
        return app(Locales::class)->all();
    }

    private function languageName(string $locale, string $displayLocale = 'en'): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayLanguage($locale, $displayLocale) : '';

        return $name !== '' && $name !== $locale ? $name : strtoupper($locale);
    }

    private function settings(): ?AutoTranslationSettings
    {
        try {
            $settings = app(AutoTranslationSettings::class);

            // Loads every value now, so a settings migration that has not run
            // yet disables the feature instead of failing someone's save.
            $settings->toArray();

            return $settings;
        } catch (Throwable) {
            return null;
        }
    }

    private function aiIsConfigured(AutoTranslationSettings $settings): bool
    {
        return $this->providerIsConfigured($this->configuredProvider($settings) ?? config('ai.default'));
    }

    private function providerIsConfigured(Lab|array|string|null $provider): bool
    {
        if ($provider instanceof Lab) {
            $provider = $provider->value;
        }

        if (is_array($provider)) {
            return collect($provider)
                ->map(fn ($model, $providerName) => is_int($providerName) ? $model : $providerName)
                ->contains(fn ($providerName) => $this->providerHasCredentials((string) $providerName));
        }

        return $this->providerHasCredentials((string) $provider);
    }

    private function providerHasCredentials(string $provider): bool
    {
        if ($provider === 'ollama') {
            return filled(config('ai.providers.ollama.url'));
        }

        return filled(config("ai.providers.{$provider}.key"));
    }

    private function configuredProvider(AutoTranslationSettings $settings): Lab|array|string|null
    {
        $provider = $settings->valueOr('provider', config('ai.auto_translation.provider'));

        return blank($provider) ? null : $provider;
    }

    private function configuredModel(AutoTranslationSettings $settings): ?string
    {
        $model = $settings->valueOr('model', config('ai.auto_translation.model'));

        return blank($model) ? null : $model;
    }

    private function configuredTimeout(): int
    {
        return (int) config('ai.auto_translation.timeout', 60);
    }
}
