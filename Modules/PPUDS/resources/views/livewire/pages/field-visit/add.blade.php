<div>
    {{ $this->form }}

    <x-core::button.primary wire:click="save">
        {{ __('Save') }}
    </x-core::button.primary>

    <x-filament-actions::modals />
</div>
