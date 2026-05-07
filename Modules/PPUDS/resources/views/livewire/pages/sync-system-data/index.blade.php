<div>
    <div class="mb-4 flex items-center gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Academic Year') }}</label>
            <input type="text" wire:model="academicYear"
                   class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Semester') }}</label>
            <input type="text" wire:model="semester"
                   class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:text-sm">
        </div>
        <div class="pt-5">
            <button wire:click="startSync" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg wire:loading.remove wire:target="startSync" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <svg wire:loading wire:target="startSync" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span wire:loading.remove wire:target="startSync">{{ __('Start Sync') }}</span>
                <span wire:loading wire:target="startSync">{{ __('Syncing...') }}</span>
            </button>
        </div>
    </div>

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
