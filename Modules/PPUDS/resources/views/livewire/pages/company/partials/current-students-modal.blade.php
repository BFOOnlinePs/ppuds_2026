<div class="space-y-4">
    @if ($students->isEmpty())
        <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            {{ __('No current students found') }}
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="max-h-[28rem] overflow-auto">
                <table class="w-full min-w-[760px] divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="sticky top-0 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('Student Name') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Student Number') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Email') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Course') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Branch') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Department') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Year') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('Training Record') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-950">
                    @foreach ($students as $student)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">
                                @if ($student['student_url'])
                                    <a href="{{ $student['student_url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $student['student_name'] }}
                                    </a>
                                @else
                                    {{ $student['student_name'] }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $student['student_number'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $student['email'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $student['course'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $student['branch'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $student['department'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ $student['semester'] }} / {{ $student['year'] }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($student['record_url'])
                                    <a href="{{ $student['record_url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">
                                        {{ __('Open Record') }}
                                    </a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
