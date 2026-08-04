@extends('core::pdf.app')

@section('content')

    <div class="header-title">
        <h2>{{ __('Student Companies') }}</h2>
        <p>{{ __('Printed At') }}: {{ now()->format('Y-m-d H:i') }}</p>
        <p>{{ __('Total Records') }}: {{ $rows->count() }}</p>
    </div>

    <table dir="rtl">
        <thead>
            <tr>
                <th style="width: 10%">{{ __('Student Number') }}</th>
                <th style="width: 16%">{{ __('Student') }}</th>
                <th style="width: 14%">{{ __('Company') }}</th>
                <th style="width: 12%">{{ __('Branch') }}</th>
                <th style="width: 10%">{{ __('Status') }}</th>
                <th style="width: 12%">{{ __('Course') }}</th>
                <th style="width: 8%">{{ __('Year') }}</th>
                <th style="width: 8%">{{ __('Semester') }}</th>
                <th style="width: 10%">{{ __('Supervisor Visit Days') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['student_number'] }}</td>
                    <td>{{ $row['student_name'] }}</td>
                    <td>{{ $row['company_name'] }}</td>
                    <td>{{ $row['branch_name'] }}</td>
                    <td>{{ $row['status_label'] }}</td>
                    <td>{{ $row['course_name'] }}</td>
                    <td>{{ $row['year'] }}</td>
                    <td>{{ $row['semester'] }}</td>
                    <td>{{ $row['field_visit_days'] }}</td>
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
