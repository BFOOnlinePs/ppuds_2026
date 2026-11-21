@php
    /** @var \Modules\PPUDS\Entities\CompanyQrCode|null $qrCode */
    $qrCode = $qrCode ?? null;
@endphp

<div class="space-y-4">
    @unless ($qrLoginEnabled)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ __('Company QR sign-in is switched off, so scanning this code does not sign anyone in yet.') }}
            @can('Setting View List')
                <a href="{{ route('settings') }}" class="font-semibold underline">{{ __('Open the settings') }}</a>
            @endcan
        </div>
    @endunless

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Sign-in QR Code') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('People who belong to this company scan it in the app, then confirm who they are with a one-time code. The code itself is not a password.') }}
                </p>
            </div>

            @if ($qrCode)
                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">{{ __('Active') }}</span>
            @else
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">{{ __('No active code') }}</span>
            @endif
        </div>

        <div class="flex flex-col gap-6 p-4 md:flex-row md:items-start">
            @if ($qrCode)
                <div class="mx-auto w-48 shrink-0 rounded-lg border border-gray-200 bg-white p-2 md:mx-0 [&>svg]:h-auto [&>svg]:w-full" aria-label="{{ __('Sign-in QR Code') }}">
                    {!! $qrSvg !!}
                </div>

                <dl class="grid flex-1 grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Generated At') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $qrCode->created_at?->format('Y-m-d H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Valid Until') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $qrCode->expires_at?->format('Y-m-d') ?? __('Until revoked') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Sign-ins Through This Code') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $qrCode->use_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Last Used') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $qrCode->last_used_at?->diffForHumans() ?? __('Never') }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('This company has no sign-in QR code yet.') }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-700">
            @if ($qrCode)
                <a href="{{ route('companies.qr-code.download', [$company, 'png']) }}"
                   class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    {{ __('Download PNG') }}
                </a>
                <a href="{{ route('companies.qr-code.download', [$company, 'svg']) }}"
                   class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    {{ __('Download SVG') }}
                </a>
                <a href="{{ route('companies.qr-code.print', $company) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    {{ __('Print') }}
                </a>
            @endif

            @can('Company QR Manage')
                <div class="flex flex-wrap gap-2 ms-auto">
                    <button type="button" wire:click="mountAction('generateQrCode')" wire:loading.attr="disabled"
                            class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-60">
                        {{ $qrCode ? __('Generate A New Code') : __('Generate Code') }}
                    </button>

                    @if ($qrCode)
                        <button type="button" wire:click="mountAction('revokeQrCode')" wire:loading.attr="disabled"
                                class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-60">
                            {{ __('Revoke Code') }}
                        </button>
                    @endif
                </div>
            @endcan
        </div>
    </div>

    {{-- Personal codes: each signs its holder straight in, so only QR managers see this. --}}
    @if ($canManagePersonalCodes)
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Personal Sign-in Codes') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Each supervisor can get their own QR code. Scanning it in the app signs them straight in, without any code — so hand it to them privately.') }}
                </p>
            </div>

            @unless ($personalQrLoginEnabled)
                <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-900">
                    {{ __('Personal QR sign-in is switched off, so these codes do not sign anyone in yet.') }}
                    @can('Setting View List')
                        <a href="{{ route('settings') }}" class="font-semibold underline">{{ __('Open the settings') }}</a>
                    @endcan
                </div>
            @endunless

            @if ($personalCodeRows->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ __('This company has no supervisors yet. Assign supervisors to its branches first.') }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-start dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Supervisor') }}</th>
                                <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Status') }}</th>
                                <th class="px-4 py-2 text-start text-xs font-semibold text-gray-600 dark:text-gray-300">{{ __('Last Used') }}</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($personalCodeRows as $row)
                                @php($personalUser = $row['user'])
                                @php($personalCode = $row['code'])
                                <tr wire:key="personal-qr-{{ $personalUser->id }}">
                                    <td class="px-4 py-2 text-sm">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $personalUser->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $personalUser->email }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        @if ($personalCode)
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">{{ __('Active') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">{{ __('No active code') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $personalCode?->last_used_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @if ($personalCode)
                                                <button type="button" wire:click="mountAction('showPersonalQrCode', { userId: {{ $personalUser->id }} })"
                                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                                                    {{ __('Show Code') }}
                                                </button>
                                            @endif

                                            <button type="button" wire:click="mountAction('generatePersonalQrCode', { userId: {{ $personalUser->id }} })" wire:loading.attr="disabled"
                                                    class="inline-flex items-center rounded-md bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-60">
                                                {{ $personalCode ? __('Generate A New Code') : __('Generate Code') }}
                                            </button>

                                            @if ($personalCode)
                                                <button type="button" wire:click="mountAction('revokePersonalQrCode', { userId: {{ $personalUser->id }} })" wire:loading.attr="disabled"
                                                        class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:opacity-60">
                                                    {{ __('Revoke Code') }}
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
