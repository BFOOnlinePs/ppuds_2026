@php
    use Modules\PPUDS\Enums\FinalReportItemType;

    $contributions = $report->items->where('type', FinalReportItemType::CONTRIBUTION)->values();
    $difficulties = $report->items->where('type', FinalReportItemType::DIFFICULTY)->values();
@endphp

<div class="space-y-4 text-sm">
    <div>
        <div class="mb-2 font-semibold">{{ __('Training Tasks') }}</div>

        @if ($report->tasks->isEmpty())
            <div class="text-gray-500">---</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-start">
                    <thead>
                        <tr>
                            <th class="border px-3 py-2 text-start text-xs">#</th>
                            <th class="border px-3 py-2 text-start text-xs">{{ __('Training Task') }}</th>
                            <th class="border px-3 py-2 text-start text-xs">{{ __('Task Details') }}</th>
                            <th class="border px-3 py-2 text-start text-xs">{{ __('Work Duration In Days And Hours') }}</th>
                            <th class="border px-3 py-2 text-start text-xs">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->tasks as $task)
                            <tr>
                                <td class="border px-3 py-2">{{ $loop->iteration }}</td>
                                <td class="border px-3 py-2">{{ $task->task_name }}</td>
                                <td class="border px-3 py-2">{{ $task->task_details ?: '---' }}</td>
                                <td class="border px-3 py-2">{{ $task->work_duration ?: '---' }}</td>
                                <td class="border px-3 py-2">{{ $task->notes ?: '---' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>
        <div class="mb-2 font-semibold">{{ __('Skills Gained From Training') }}</div>

        @if ($report->skills->isEmpty())
            <div class="text-gray-500">---</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-start">
                    <thead>
                        <tr>
                            <th class="border px-3 py-2 text-start text-xs">#</th>
                            <th class="border px-3 py-2 text-start text-xs">{{ __('Skill') }}</th>
                            <th class="border px-3 py-2 text-start text-xs">{{ __('Mastery Percentage') }}</th>
                            <th class="border px-3 py-2 text-start text-xs">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->skills as $skill)
                            <tr>
                                <td class="border px-3 py-2">{{ $loop->iteration }}</td>
                                <td class="border px-3 py-2">{{ $skill->skill }}</td>
                                <td class="border px-3 py-2">{{ $skill->mastery_percentage !== null ? $skill->mastery_percentage . '%' : '---' }}</td>
                                <td class="border px-3 py-2">{{ $skill->notes ?: '---' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @foreach ([__('Key Contributions') => $contributions, __('Training Difficulties') => $difficulties] as $title => $items)
        <div>
            <div class="mb-2 font-semibold">{{ $title }}</div>

            @if ($items->isEmpty())
                <div class="text-gray-500">---</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-start">
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td class="border px-3 py-2">{{ $loop->iteration }}</td>
                                    <td class="border px-3 py-2">{{ $item->content }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
</div>
