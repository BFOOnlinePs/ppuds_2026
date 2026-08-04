@extends('core::pdf.app')

@section('content')

    <div class="header-title">
        <h2>{{ __('Student Attendance Report') }}</h2>
        <p>
            @if($from || $until)
                {{ __('Period') }}: {{ $from ?: '---' }} &nbsp;-&nbsp; {{ $until ?: '---' }}
            @endif
        </p>
        <p>{{ __('Printed At') }}: {{ now()->format('Y-m-d H:i') }}</p>
        <p>{{ __('Total Records') }}: {{ $rows->count() }}</p>
    </div>

    <table dir="rtl">
        <thead>
            <tr>
                <th style="width: 10%">{{ __('Student Number') }}</th>
                <th style="width: 16%">{{ __('Student Name') }}</th>
                <th style="width: 14%">{{ __('Company Name') }}</th>
                <th style="width: 12%">{{ __('Branch') }}</th>
                <th style="width: 10%">{{ __('Date') }}</th>
                <th style="width: 8%">{{ __('Check In') }}</th>
                <th style="width: 8%">{{ __('Check Out') }}</th>
                <th style="width: 10%">{{ __('Status') }}</th>
                <th style="width: 12%">{{ __('Notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['student_number'] }}</td>
                    <td>{{ $row['student_name'] }}</td>
                    <td>{{ $row['company_name'] }}</td>
                    <td>{{ $row['branch_name'] }}</td>
                    <td>{{ $row['attendance_date'] ?: '---' }}</td>
                    <td>{{ $row['check_in'] ?: '---' }}</td>
                    <td>{{ $row['check_out'] ?: '---' }}</td>
                    <td>{{ $row['status_label'] }}</td>
                    <td>{{ $row['description'] ?: '---' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; padding: 20px;">
                        {{ __('No records found.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
