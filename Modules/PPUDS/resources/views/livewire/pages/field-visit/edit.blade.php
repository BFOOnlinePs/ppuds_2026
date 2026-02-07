<div>
    {{ $this->form }}

    <x-core::button.primary wire:click="save">
        {{ __('Update') }}
    </x-core::button.primary>

    <x-filament-actions::modals />
</div>
