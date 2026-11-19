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
</div>
