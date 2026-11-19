<x-guest-layout>
    <x-slot name="title">
        {{ __('Sign in with a one-time code') }}
    </x-slot>

    {{-- The guest layout does not load the dashboard CSS that defines this. --}}
    <style>[x-cloak] { display: none !important; }</style>

    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        @if (! $pending)
            <form method="POST" action="{{ route('login.otp.send') }}" x-data="{ busy: false }" x-on:submit="busy = true">
                @csrf

                <p class="mb-4 text-sm text-gray-600">
                    {{ __('Enter your email and we will send you a one-time code to sign in.') }}
                </p>

                <div>
                    <x-label for="email" value="{{ __('Email') }}" />
                    <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error for="email" class="mt-2" />
                </div>

                @if ($channelChoice)
                    <fieldset class="mt-4">
                        <legend class="text-sm font-medium text-gray-700">{{ __('Send the code by') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach ($channelOptions as $key => $label)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="radio" name="channel" value="{{ $key }}" @checked(old('channel', $defaultChannel) === $key)
                                           class="border-gray-300 text-primary focus:ring-indigo-500">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-4 mt-6">
                    @if ($passwordLogin)
                        <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                            {{ __('Sign in with a password') }}
                        </a>
                    @endif

                    <x-button class="ms-auto" x-bind:disabled="busy">
                        <span x-show="! busy">{{ __('Send code') }}</span>
                        <span x-show="busy" x-cloak>{{ __('Sending…') }}</span>
                    </x-button>
                </div>
            </form>
        @else
            <div class="mb-4 text-sm text-gray-600" role="status">
                @if ($pending['after_password'] ?? null)
                    {{-- Two-step: the password was accepted, so the person is known. --}}
                    {{ $pending['channel'] === 'sms'
                        ? __('To finish signing in, enter the code we sent by SMS to the phone number on your account.')
                        : __('To finish signing in, enter the code we sent to :email.', ['email' => $pending['email']]) }}
                @elseif ($pending['channel'] === 'sms')
                    {{ __('If :email can sign in this way, a code has been sent by SMS to the phone number on that account.', ['email' => $pending['email']]) }}
                @else
                    {{ __('If :email can sign in this way, a code has been sent to it.', ['email' => $pending['email']]) }}
                @endif
                {{ __('The code expires in :minutes minutes.', ['minutes' => intdiv($pending['expires_in'], 60)]) }}
            </div>

            <form method="POST" action="{{ route('login.otp.verify') }}" x-data="{ busy: false }" x-on:submit="busy = true">
                @csrf

                <div>
                    <x-label for="code" value="{{ __('Code') }}" />
                    <x-input id="code" class="block mt-1 w-full text-center text-lg tracking-[0.5em]" type="text" name="code"
                             inputmode="numeric" pattern="[0-9]*" maxlength="{{ $pending['code_length'] }}"
                             autocomplete="one-time-code" dir="ltr" required autofocus />
                    <x-input-error for="code" class="mt-2" />
                </div>

                <div class="flex justify-end mt-6">
                    <x-button x-bind:disabled="busy">
                        <span x-show="! busy">{{ __('Sign in') }}</span>
                        <span x-show="busy" x-cloak>{{ __('Checking…') }}</span>
                    </x-button>
                </div>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-4 mt-6 text-sm">
                <form method="POST" action="{{ route('login.otp.reset') }}">
                    @csrf
                    <button type="submit" class="underline text-gray-600 hover:text-gray-900">
                        {{ ($pending['after_password'] ?? null) ? __('Back to sign in') : __('Use a different email') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('login.otp.send') }}"
                      x-data="{ wait: {{ (int) $resendAfter }} }"
                      x-init="let t = setInterval(() => { wait > 0 ? wait-- : clearInterval(t) }, 1000)">
                    @csrf
                    <input type="hidden" name="email" value="{{ $pending['email'] }}">
                    <input type="hidden" name="channel" value="{{ $pending['channel'] }}">

                    <button type="submit" x-bind:disabled="wait > 0"
                            class="underline text-gray-600 hover:text-gray-900 disabled:no-underline disabled:text-gray-400 disabled:cursor-not-allowed">
                        <span x-show="wait === 0">{{ __('Resend code') }}</span>
                        <span x-show="wait > 0" x-cloak>{{ __('Resend code in') }} <span x-text="wait"></span> {{ __('seconds') }}</span>
                    </button>
                </form>
            </div>

            <x-input-error for="email" class="mt-4" />
        @endif
    </x-authentication-card>
</x-guest-layout>
