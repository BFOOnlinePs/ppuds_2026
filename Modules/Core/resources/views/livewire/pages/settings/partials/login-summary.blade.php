@php
    $badge = fn (\Modules\PPUDS\Enums\InternalLoginMode $mode): string => match ($mode) {
        \Modules\PPUDS\Enums\InternalLoginMode::PASSWORD => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        \Modules\PPUDS\Enums\InternalLoginMode::CODE => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        \Modules\PPUDS\Enums\InternalLoginMode::PASSWORD_OR_CODE => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
        \Modules\PPUDS\Enums\InternalLoginMode::PASSWORD_THEN_CODE => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    };
@endphp

<div class="space-y-3">
    @unless ($codesPossible)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            {{ __('No delivery channel is on, so every method that needs a code falls back to the password. Turn on email or SMS under "Sending Codes".') }}
        </div>
    @endunless

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/40">
                <tr>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Role') }}</th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Dashboard (Website)') }}</th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Mobile App') }}</th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Code Sent By') }}</th>
                    <th class="px-3 py-2 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('QR Code') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                            {{ $row['label'] }}
                            @if ($row['fromRule'])
                                <span class="ms-1 rounded bg-primary-50 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">{{ __('exception') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge($row['web']) }}">{{ $row['web']->getShortLabel() }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge($row['mobile']) }}">{{ $row['mobile']->getShortLabel() }}</span>
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                            {{ $row['channel']?->getLabel() ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                            @if ($row['personalQr'])
                                <div class="font-semibold text-green-700 dark:text-green-300">{{ __('Personal code: straight in') }}</div>
                            @endif
                            @if ($row['companyQr'])
                                <div>{{ __('Company poster + code') }}</div>
                            @endif
                            @unless ($row['personalQr'] || $row['companyQr'])
                                —
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Someone with several roles follows the first exception that matches them.') }}
        @if ($ownMethodCount > 0)
            {{ trans_choice('{1} One user also has their own method, set on their user page — it comes before this table.|[2,*] :count users also have their own method, set on their user pages — it comes before this table.', $ownMethodCount, ['count' => $ownMethodCount]) }}
        @endif
    </p>
</div>
