<div>
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-danger-50 p-4 text-danger-700 ring-1 ring-danger-600/20 dark:text-danger-400">
            <div class="mb-2 flex items-center gap-2 text-sm font-medium">
                <x-icon name="solar-danger-triangle-bold" class="w-5 h-5" />
                {{ __('Please fix the following errors') }}
            </div>
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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

    @if ($this->hasRegistration())
        <div class="flex flex-wrap items-center gap-3">
            @unless ($this->isLocked())
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
            @endunless

            @if ($this->report)
                <x-core::button.primary wire:click="printPdf" wire:loading.attr="disabled">
                    {{ __('Print PDF') }}
                </x-core::button.primary>
            @endif
        </div>
    @endif

    <x-filament-actions::modals />
</div>
