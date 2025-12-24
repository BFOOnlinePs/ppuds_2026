{{-- Modules/Core/Resources/views/components/activity-log-modal.blade.php --}}
<div class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium">{{ __('Activity Log') }}</h3>
        <span class="text-sm text-gray-500">{{ __('Activities Count') }} {{ $activities->count() }} </span>
    </div>

    @if($activities->count() > 0)
        <div class="max-h-96 overflow-y-auto space-y-3">
            @foreach($activities as $activity)
                <div class="border rounded-lg p-4 bg-gray-50">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 space-x-reverse">
                                <span class="font-medium text-gray-900">
                                    {{ __($activity->description) }} {{ $activity->value }}
                                </span>
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                    {{ __(class_basename($activity->subject_type)) }}
                                </span>
                            </div>

                            @if($activity->causer)
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ __('By') }}: {{ $activity->causer->name }}
                                </p>
                            @endif

                            @if($activity->properties && count($activity->properties) > 0)
                                <details class="mt-2">
                                    <summary class="text-sm text-blue-600 cursor-pointer">{{ __('Show Details') }}</summary>
                                    <div class="mt-2 p-3 bg-white rounded border text-xs">
                                        @if(isset($activity->properties['old']) && isset($activity->properties['attributes']))
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <strong>{{ __('Old Values') }}:</strong>
                                                    <pre class="mt-1 bg-red-50 p-2 rounded text-xs overflow-auto max-h-32">{{ json_encode($activity->properties['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                </div>
                                                <div>
                                                    <strong>{{ __('New Values') }}:</strong>
                                                    <pre class="mt-1 bg-green-50 p-2 rounded text-xs overflow-auto max-h-32">{{ json_encode($activity->properties['attributes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                </div>
                                            </div>
                                        @else
                                            <pre class="overflow-auto max-h-32">{{ json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </div>

                        <div class="text-left">
                            <time class="text-sm text-gray-500">
                                {{ $activity->created_at->diffForHumans() }}
                            </time>
                            <br>
                            <span class="text-xs text-gray-400">
                                {{ $activity->created_at->format('d/m/Y H:i') }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8 text-gray-500">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="mt-2">{{ __('No activities recorded') }}</p>
        </div>
    @endif
</div>
