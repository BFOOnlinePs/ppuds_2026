@extends('core::pdf.app')

@section('content')

    <div class="header-title">
        <h2>{{ $title }}</h2>
        <p>{{ __('Printed At') }}: {{ now()->format('Y-m-d H:i') }}</p>
        <p>{{ __('Total Records') }}: {{ $rows->count() }}</p>
    </div>

    <table dir="rtl">
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($headings), 1) }}" style="text-align: center; padding: 20px;">
                        {{ __('No records found.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
