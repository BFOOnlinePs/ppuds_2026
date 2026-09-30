{{--
    قائمة اختيار بسيطة بخانة بحث، مربوطة بخاصية Livewire عبر wire:model.
    الخيارات مصفوفة (القيمة => النص). البحث يتم في المتصفح دون طلب للخادم.

    <x-core::forms.search-select wire:model.live="selected.1" :options="$options" :placeholder="__('Select')" />
--}}
@props([
    'options' => [],
    'placeholder' => null,
])

@php
    $model = $attributes->wire('model');
    $items = collect($options)
        ->map(fn ($label, $value): array => ['value' => (string) $value, 'label' => (string) $label])
        ->values();
@endphp

<div
    x-data="{
        open: false,
        search: '',
        value: $wire.entangle(@js($model->value()), @js($model->hasModifier('live'))),
        options: @js($items),
        get filtered() {
            const term = this.search.trim().toLowerCase()

            const matches = term === ''
                ? this.options
                : this.options.filter((option) => option.label.toLowerCase().includes(term))

            // قائمة الشركات قد تكون طويلة؛ البحث يضيّقها
            return matches.slice(0, 100)
        },
        get selectedLabel() {
            const current = String(this.value ?? '')

            return this.options.find((option) => option.value === current)?.label ?? ''
        },
        toggle() {
            this.open = ! this.open

            if (this.open) {
                this.$nextTick(() => this.$refs.search.focus())
            }
        },
        choose(option) {
            this.value = option.value
            this.open = false
            this.search = ''
        },
    }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.stop="open = false"
    {{ $attributes->whereDoesntStartWith('wire:model')->class(['relative']) }}
>
    <button
        type="button"
        x-on:click="toggle()"
        class="flex w-full items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-start text-sm shadow-sm ring-1 ring-gray-950/10 transition focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/20"
    >
        <span
            class="truncate"
            x-text="selectedLabel || @js($placeholder)"
            x-bind:class="selectedLabel ? 'text-gray-950 dark:text-white' : 'text-gray-400 dark:text-gray-500'"
        ></span>

        @svg('heroicon-m-chevron-down', 'h-4 w-4 shrink-0 text-gray-400')
    </button>

    <div
        x-show="open"
        x-transition.opacity
        style="display: none"
        class="absolute inset-x-0 z-30 mt-1 overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
    >
        <div class="border-b border-gray-100 p-2 dark:border-white/10">
            <input
                x-ref="search"
                x-model="search"
                type="search"
                placeholder="{{ __('Search...') }}"
                class="w-full rounded-md border-0 bg-gray-50 px-3 py-1.5 text-sm text-gray-950 ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
            >
        </div>

        <ul class="max-h-60 overflow-y-auto py-1">
            <template x-for="option in filtered" x-bind:key="option.value">
                <li>
                    <button
                        type="button"
                        x-on:click="choose(option)"
                        x-text="option.label"
                        class="block w-full px-3 py-2 text-start text-sm text-gray-700 transition hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5"
                        x-bind:class="option.value === String(value ?? '') && 'bg-primary-50 font-semibold text-primary-700 dark:bg-white/5 dark:text-primary-400'"
                    ></button>
                </li>
            </template>

            <li x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('No results found') }}
            </li>
        </ul>
    </div>
</div>
