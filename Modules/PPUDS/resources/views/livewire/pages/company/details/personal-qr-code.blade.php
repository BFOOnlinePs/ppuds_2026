{{-- A personal code signs its holder straight in: shown only to those who manage QR codes. --}}
<div class="space-y-4">
    @if ($code)
        <div class="mx-auto w-56 rounded-lg border border-gray-200 bg-white p-2 [&>svg]:h-auto [&>svg]:w-full" aria-label="{{ __('Personal sign-in code') }}">
            {!! $svg !!}
        </div>

        <p class="rounded-md bg-red-50 px-3 py-2 text-center text-xs text-red-800">
            {{ __('Anyone who scans this code signs in as :name. Hand it over privately and never post it publicly.', ['name' => $user->name]) }}
        </p>

        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Valid Until') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $code->expires_at?->format('Y-m-d') ?? __('Until revoked') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Last Used') }}</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $code->last_used_at?->diffForHumans() ?? __('Never') }}</dd>
            </div>
        </dl>

        <div class="flex flex-wrap justify-center gap-2">
            <a href="{{ route('companies.qr-code.personal.download', [$company, $user, 'png']) }}"
               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                {{ __('Download PNG') }}
            </a>
            <a href="{{ route('companies.qr-code.personal.print', [$company, $user]) }}" target="_blank" rel="noopener"
               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                {{ __('Print') }}
            </a>
        </div>
    @else
        <p class="text-center text-sm text-gray-600 dark:text-gray-300">{{ __('This person has no active personal sign-in code.') }}</p>
    @endif
</div>
