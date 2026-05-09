<div>
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <label class="inline-flex items-center gap-2 pt-5 text-sm font-medium text-gray-700 dark:text-gray-300">
            <input type="checkbox" wire:model.live="useUniversitySettings"
                   class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
            <span>{{ __('Use University Settings') }}</span>
        </label>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Academic Year') }}</label>
            <input type="text" wire:model="academicYear" @disabled($useUniversitySettings)
                   class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Semester') }}</label>
            <input type="text" wire:model="semester" @disabled($useUniversitySettings)
                   class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:text-sm">
        </div>
        <div class="flex flex-wrap gap-3 pt-5">
            <button wire:click="syncCourses" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg wire:loading.remove wire:target="syncCourses" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <svg wire:loading wire:target="syncCourses" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span wire:loading.remove wire:target="syncCourses">{{ __('Sync Courses') }}</span>
                <span wire:loading wire:target="syncCourses">{{ __('Syncing...') }}</span>
            </button>

            <button wire:click="startSync" wire:loading.attr="disabled" @disabled($courseSyncing || ! $showCourseStatusReview || $courses->isEmpty())
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg wire:loading.remove wire:target="startSync" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg wire:loading wire:target="startSync" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span wire:loading.remove wire:target="startSync">{{ __('Continue Sync') }}</span>
                <span wire:loading wire:target="startSync">{{ __('Syncing...') }}</span>
            </button>
        </div>
    </div>

    @if ($showCourseStatusReview)
        <div class="mb-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Courses Status') }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Review course status before continuing the system sync.') }}</p>
                </div>
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('Courses') }}: {{ $courses->count() }}
                </div>
            </div>

            <div class="max-h-96 overflow-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('Course Code') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-gray-700 dark:text-gray-200">{{ __('Status') }}</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($courses as $course)
                        @php($isActive = $courseStatuses[$course->id] ?? false)
                        <tr wire:key="sync-course-status-{{ $course->id }}">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-gray-900 dark:text-gray-100">{{ $course->course_code }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $course->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" wire:model.live="courseStatuses.{{ $course->id }}"
                                           class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                                    <span class="text-xs font-medium {{ $isActive ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $isActive ? __('Active') : __('Inactive') }}
                                    </span>
                                </label>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No courses found yet.') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div wire:poll.1s="refreshLogs" id="sync-terminal"
         class="h-[600px] overflow-y-auto rounded-lg border border-gray-600 bg-gray-950 p-4 font-mono text-sm leading-relaxed text-green-400"
         style="background-color: #0a0a0a; box-shadow: inset 0 0 30px rgba(0,0,0,0.8);">
        @forelse ($logs as $log)
            <div class="whitespace-pre-wrap">
                <span class="text-gray-500">[{{ $log['time'] }}]</span>
                <span>{{ $log['message'] }}</span>
            </div>
        @empty
            <div class="text-gray-600">{{ __('No logs yet. Click "Start Sync" to begin.') }}</div>
        @endforelse
    </div>

    <div id="terminal-bottom"></div>

    @push('scripts')
        <script>
            document.addEventListener('livewire:init', function () {
                const scrollToBottom = function () {
                    const terminal = document.getElementById('sync-terminal');
                    if (terminal) {
                        terminal.scrollTop = terminal.scrollHeight;
                    }
                };

                Livewire.hook('commit', ({ component, respond, succeed, fail }) => {
                    succeed(() => {
                        setTimeout(scrollToBottom, 50);
                    });
                });

                scrollToBottom();
            });
        </script>
    @endpush
</div>
