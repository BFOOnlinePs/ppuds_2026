<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai_translation.enabled', true);
        $this->migrator->add('ai_translation.target_locales', array_values((array) config('translatable.locales', [])));
        $this->migrator->add('ai_translation.detect_source_language', true);
        $this->migrator->add('ai_translation.update_machine_translations', true);
        $this->migrator->add('ai_translation.translate_background_saves', false);

        // Blank keeps config('ai.auto_translation') / the AI_AUTO_TRANSLATION_* env values.
        $this->migrator->add('ai_translation.provider', '');
        $this->migrator->add('ai_translation.model', '');
    }

    public function down(): void
    {
        foreach ([
            'enabled', 'target_locales', 'detect_source_language',
            'update_machine_translations', 'translate_background_saves',
            'provider', 'model',
        ] as $property) {
            $this->migrator->delete("ai_translation.{$property}");
        }
    }
};
