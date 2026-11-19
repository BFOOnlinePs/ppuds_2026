<x-guest-layout>
    <x-slot name="title">
        {{ __('Company sign-in code') }}
    </x-slot>

    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="space-y-4 text-center">
            @if ($company)
                <h1 class="text-lg font-semibold text-gray-900">{{ $company->name }}</h1>
                <p class="text-sm text-gray-600">
                    {{ __('This is the sign-in code of :company. Open the :app app, choose to sign in with the company code, and scan it from there.', ['company' => $company->name, 'app' => config('app.name')]) }}
                </p>
            @else
                <h1 class="text-lg font-semibold text-gray-900">{{ __('This code is no longer valid') }}</h1>
                <p class="text-sm text-gray-600">
                    {{ __('Ask the company for its current sign-in code.') }}
                </p>
            @endif

            @if ($storeLinks)
                <div class="flex flex-wrap justify-center gap-3 pt-2">
                    @foreach ($storeLinks as $platform => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-dark">
                            {{ __('Get the app') }} ({{ $platform }})
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-authentication-card>
</x-guest-layout>
