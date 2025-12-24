<div>
    {{ $this->form }}

    <form wire:submit.prevent='triggerEvent' action="">
        <input type="submit">
    </form>

    <x-core::button.primary wire:click="save">
        {{ __('Save') }}
    </x-core::button.primary>
</div>
