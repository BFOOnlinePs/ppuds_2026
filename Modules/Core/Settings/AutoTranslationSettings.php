<?php

namespace Modules\Core\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * AI auto translation of translatable records, editable from the settings
 * screen. The provider and model fall back to config('ai.auto_translation')
 * while they are left blank.
 *
 * NOTE: these properties carry no docblocks on purpose — see KeycloakSettings.
 */
class AutoTranslationSettings extends Settings
{
    public bool $enabled;

    // Locales from config('translatable.locales') the AI fills in.
    public array $target_locales;

    // Store text typed in another language (English on the Arabic screen)
    // under its real language, and translate it into the screen's language.
    public bool $detect_source_language;

    // Re-translate AI-written values when the original text changes. Values
    // a person typed are never overwritten either way.
    public bool $update_machine_translations;

    // Also translate records saved by queue jobs and console commands, such
    // as the university sync. Off by default: one sync can save thousands.
    public bool $translate_background_saves;

    public string $provider;

    public string $model;

    public static function group(): string
    {
        return 'ai_translation';
    }

    /** Blank means "keep using config", so callers need the fallback everywhere. */
    public function valueOr(string $property, mixed $fallback): mixed
    {
        $value = trim((string) ($this->{$property} ?? ''));

        return $value !== '' ? $value : $fallback;
    }
}
