<div>
    {{ $this->form }}

    <x-core::button.primary wire:click="save" wire:loading.attr="disabled" wire:target="save" class="disabled:opacity-60 disabled:cursor-wait">
        <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        {{ __('Save') }}
    </x-core::button.primary>
</div>
