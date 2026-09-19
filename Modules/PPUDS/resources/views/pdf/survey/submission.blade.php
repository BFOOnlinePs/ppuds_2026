@extends('core::pdf.app')

@section('content')

    <div class="header-title">
        <h2>{{ $survey->title }}</h2>
        <p>{{ __('Submitted By') }}: {{ $user->name }}</p>

        @if($studentCompany)
            <p>{{ __('Evaluated Student') }}: {{ $studentCompany->student?->name }}</p>

            @if($studentCompany->student?->studentProfile?->student_number)
                <p>{{ __('Student Number') }}: {{ $studentCompany->student->studentProfile->student_number }}</p>
            @endif
        @endif

        @if($user->studentProfile?->student_number)
            <p>{{ __('Student Number') }}: {{ $user->studentProfile->student_number }}</p>
        @endif

        <p>{{ __('Submitted At') }}: {{ $submittedAt }}</p>
        <p>{{ __('Printed At') }}: {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    <table dir="rtl">
        <thead>
            <tr>
                <th style="width: 55%">{{ __('Question') }}</th>
                <th style="width: 45%">{{ __('Answer') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['question'] }}</td>
                    <td>{{ $row['answer'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" style="text-align: center; padding: 20px;">
                        {{ __('No records found.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
