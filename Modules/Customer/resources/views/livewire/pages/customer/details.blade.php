<div>
    <div class="mb-3">
        {{ $this->infolist }}
    </div>
    <div>
        {{ $this->form }}
    </div>

{{--    @if($this->selectedTab === 2)--}}
{{--        <div class="p-4 rounded-lg bg-white dark:bg-gray-800/50 mt-6 border border-gray-200 dark:border-gray-700">--}}
{{--            @include('customer::tables.customer-readings-stats', ['stats' => $this->statistics])--}}
{{--            @include('customer::tables.customer-readings-history', ['readings' => $this->customer->readings()->latest()->get()])--}}
{{--        </div>--}}
{{--    @endif--}}

{{--    <x-core::button.primary wire:click="save">--}}
{{--        {{ __('Save') }}--}}
{{--    </x-core::button.primary>--}}
</div>
