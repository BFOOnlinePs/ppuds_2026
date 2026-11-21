@extends('core::pdf.app')

@section('content')
    <div style="text-align: center; direction: rtl;">
        @if ($holder ?? null)
            {{-- A personal code: it signs this person straight in, so it is handed to them privately. --}}
            <h1 style="font-size: 26px; margin: 0 0 6px;">{{ $holder->name }}</h1>
            <p style="font-size: 15px; color: #555; margin: 0 0 18px;">
                {{ __('Your personal sign-in code for :app — :company', ['app' => config('app.name'), 'company' => $company->name]) }}
            </p>
        @else
            <h1 style="font-size: 26px; margin: 0 0 6px;">{{ $company->name }}</h1>
            <p style="font-size: 15px; color: #555; margin: 0 0 18px;">{{ __('Sign in to :app with the company code', ['app' => config('app.name')]) }}</p>
        @endif

        <img src="{{ $qrImage }}" alt="QR" style="width: 110mm; height: 110mm;">

        <table style="width: 85%; margin: 18px auto 0; font-size: 14px; border-collapse: collapse;" dir="rtl">
            <tr><td style="padding: 4px 0;">1. {{ __('Open the :app app.', ['app' => config('app.name')]) }}</td></tr>
            @if ($holder ?? null)
                <tr><td style="padding: 4px 0;">2. {{ __('Choose "Scan QR code" and scan this code — you are signed in straight away.') }}</td></tr>
            @else
                <tr><td style="padding: 4px 0;">2. {{ __('Choose "Sign in with the company code" and scan this code.') }}</td></tr>
                <tr><td style="padding: 4px 0;">3. {{ __('Enter your email, then the code we send you.') }}</td></tr>
            @endif
        </table>

        <p style="font-size: 12px; color: #777; margin-top: 16px;">
            @if ($holder ?? null)
                <strong style="color: #b91c1c;">{{ __('Keep this code private: anyone who scans it signs in as you. If it is lost, ask the administration to revoke it.') }}</strong>
            @else
                {{ __('Only people who belong to this company can sign in with this code.') }}
            @endif
            @if ($expiresAt)
                <br>{{ __('Valid until :date', ['date' => $expiresAt->format('Y-m-d')]) }}
            @endif
        </p>
    </div>
@endsection
