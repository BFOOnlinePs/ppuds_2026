<div>
    @if ($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-danger-50 dark:bg-danger-500/10 border border-danger-200 dark:border-danger-500/20 text-danger-600 dark:text-danger-400">
            <div class="flex items-center gap-2 text-sm font-medium mb-2">
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

    {{ $this->form }}

{{--    <x-core::button.primary wire:click="save">--}}
{{--        {{ __('Save') }}--}}
{{--    </x-core::button.primary>--}}

    <x-filament-actions::modals />
</div>
