<div>
    @unless ($this->hasRegistration())
        <div class="mb-4 rounded-md bg-warning p-4 text-sm text-white">
            {{ __('You do not have a registration in the current semester.') }}
        </div>
    @endunless

    @if ($this->isLocked())
        <div class="mb-4 rounded-md bg-success p-4 text-sm text-white">
            {{ __('The final report has already been submitted and can no longer be edited.') }}
        </div>
    @endif

    {{ $this->form }}

    @if ($this->hasRegistration() && ! $this->isLocked())
        <div class="flex flex-wrap items-center gap-3">
            <x-core::button.primary wire:click="save" wire:loading.attr="disabled">
                {{ __('Save Draft') }}
            </x-core::button.primary>

            <x-core::button.primary
                wire:click="submit"
                wire:loading.attr="disabled"
                wire:confirm="{{ __('Once submitted, the final report can no longer be edited. Do you want to continue?') }}"
            >
                {{ __('Submit Final Report') }}
            </x-core::button.primary>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
