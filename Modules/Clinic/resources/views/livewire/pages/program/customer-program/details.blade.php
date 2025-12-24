<div>

    <div class="mb-3">
        {{ $this->infolist }}

    </div>

    <div>
        {{ $this->form }}
    </div>

    <x-core::button.primary wire:click="save">
        {{ __('Save') }}
    </x-core::button.primary>
</div>
