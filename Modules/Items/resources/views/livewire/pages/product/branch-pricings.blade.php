<div>
    <x-filament::card class="p-4">
        @php
            // اتجاه الصفحة يعتمد على اللغة الحالية
            $isRtl = in_array(app()->getLocale(), ['ar', 'he', 'fa']);
        @endphp

        <div class="overflow-x-auto" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
            <table class="min-w-full divide-y divide-gray-200 table-auto">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 {{ $isRtl ? 'text-right' : 'text-left' }} text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Product / Variation') }}
                    </th>
                    @foreach($branches as $branch)
                        <th class="px-4 py-3 {{ $isRtl ? 'text-right' : 'text-left' }} text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ $branch->name }}
                        </th>
                    @endforeach
                </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-100">
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900 {{ $isRtl ? 'text-right' : 'text-left' }}">
                        {{ $product->name }}
                    </td>
                    @foreach($branches as $branch)
                        <td class="px-4 py-2">
                            <input
                                type="number"
                                step="0.01"
                                inputmode="decimal"
                                wire:model.defer="prices.{{ $product->id }}.{{ $branch->id }}"
                                class="block w-full rounded-md border border-gray-200 bg-white py-1 px-2 text-sm shadow-sm
                                       focus:outline-none focus:ring-1 focus:ring-inset focus:ring-primary-600 text-{{ $isRtl ? 'right' : 'left' }}"
                                placeholder="{{ __('Price') }}"
                            />
                        </td>
                    @endforeach
                </tr>

                @foreach($product->variations ?? [] as $variation)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-700 {{ $isRtl ? 'text-right' : 'text-left' }}">
                            {{ $variation->name }}
                        </td>
                        @foreach($branches as $branch)
                            <td class="px-4 py-2">
                                <input
                                    type="number"
                                    step="0.01"
                                    inputmode="decimal"
                                    wire:model.defer="prices.{{ $variation->id }}.{{ $branch->id }}"
                                    class="block w-full rounded-md border border-gray-200 bg-white py-1 px-2 text-sm shadow-sm
                                           focus:outline-none focus:ring-1 focus:ring-inset focus:ring-primary-600 text-{{ $isRtl ? 'right' : 'left' }}"
g
                                />
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-end">
            <x-filament::button wire:click="save" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-filament::button>
        </div>
    </x-filament::card>

</div>
