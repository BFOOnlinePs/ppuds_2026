@php
    $toolLabels = [
        'SearchStudents' => __('Student search'),
        'GetStudentDetails' => __('Student details'),
        'GetStudentFiles' => __('Student files'),
        'SearchCompanies' => __('Company search'),
        'SuggestCompaniesForStudent' => __('Company suggestions'),
        'SuggestNearestCompanies' => __('Nearest companies'),
        'SetStudentResidence' => __('Residence city saved'),
        'LinkStudentToCompany' => __('Student registered in company'),
        'UnlinkStudentFromCompany' => __('Company link removed'),
    ];

    $examples = [
        __('Show me the information of student 202012345'),
        __('Show the files of student Ahmad'),
        __('Suggest the nearest company to the student based on where he lives'),
        __('Register the student in the Hebron Municipality'),
    ];

    $isThinking = $pendingPrompt !== null;
@endphp

<div
    x-data="{ sidebarOpen: false }"
    class="relative flex h-[calc(100vh-12rem)] min-h-[560px] overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950"
>
    {{-- قائمة المحادثات --}}
    <aside
        class="absolute inset-y-0 start-0 z-20 w-72 shrink-0 flex-col border-e border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900 lg:static lg:flex"
        :class="sidebarOpen ? 'flex' : 'hidden'"
    >
        <div class="flex items-center gap-2 p-3">
            <button
                type="button"
                wire:click="newChat"
                x-on:click="sidebarOpen = false"
                class="inline-flex h-10 flex-1 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-800 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800"
            >
                @svg('heroicon-o-pencil-square', 'h-4 w-4')
                <span>{{ __('New chat') }}</span>
            </button>

            <button
                type="button"
                x-on:click="sidebarOpen = false"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 lg:hidden"
            >
                @svg('heroicon-o-x-mark', 'h-5 w-5')
            </button>
        </div>

        <div class="flex-1 space-y-1 overflow-y-auto px-2 pb-3">
            <p class="px-2 pb-1 pt-2 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Previous conversations') }}</p>

            @forelse ($this->conversations as $conversation)
                <div
                    wire:key="conversation-{{ $conversation->id }}"
                    @class([
                        'group flex items-center gap-1 rounded-lg transition',
                        'bg-gray-200/70 dark:bg-gray-800' => $conversation->id === $conversationId,
                        'hover:bg-gray-100 dark:hover:bg-gray-800/60' => $conversation->id !== $conversationId,
                    ])
                >
                    <button
                        type="button"
                        wire:click="openConversation('{{ $conversation->id }}')"
                        x-on:click="sidebarOpen = false"
                        class="min-w-0 flex-1 px-3 py-2 text-start"
                    >
                        <span class="block truncate text-sm text-gray-800 dark:text-gray-100">{{ $conversation->title }}</span>
                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ \Illuminate\Support\Carbon::parse($conversation->updated_at)->diffForHumans() }}
                        </span>
                    </button>

                    <button
                        type="button"
                        wire:click="deleteConversation('{{ $conversation->id }}')"
                        wire:confirm="{{ __('Are you sure you want to delete this conversation?') }}"
                        class="me-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-400 opacity-0 transition hover:bg-gray-200 hover:text-danger-600 group-hover:opacity-100 dark:hover:bg-gray-700"
                        title="{{ __('Delete') }}"
                    >
                        @svg('heroicon-o-trash', 'h-4 w-4')
                    </button>
                </div>
            @empty
                <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No conversations yet') }}</p>
            @endforelse
        </div>
    </aside>

    {{-- منطقة المحادثة --}}
    <section class="relative flex min-w-0 flex-1 flex-col">
        <header class="flex items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <button
                type="button"
                x-on:click="sidebarOpen = true"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 lg:hidden"
            >
                @svg('heroicon-o-bars-3', 'h-5 w-5')
            </button>

            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-950 text-white dark:bg-white dark:text-gray-950">
                @svg('heroicon-o-sparkles', 'h-5 w-5')
            </span>
            <div class="min-w-0">
                <h2 class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ __('AI Assistant') }}</h2>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ __('Ask about students, their files and training companies') }}</p>
            </div>
        </header>

        @unless ($this->configured)
            <div class="border-b border-warning-200 bg-warning-50 px-4 py-2 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                {{ __('AI provider is not configured. Please add the API key in the environment settings.') }}
            </div>
        @endunless

        <div
            x-data
            x-init="
                const scroll = () => $el.scrollTop = $el.scrollHeight;
                scroll();
                new MutationObserver(scroll).observe($el, { childList: true, subtree: true });
            "
            class="flex-1 overflow-y-auto px-4 py-6 sm:px-6"
        >
            @if ($messages === [] && ! $isThinking)
                <div class="mx-auto flex h-full max-w-2xl flex-col items-center justify-center text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-950 text-white dark:bg-white dark:text-gray-950">
                        @svg('heroicon-o-sparkles', 'h-6 w-6')
                    </span>
                    <h3 class="mt-4 text-xl font-semibold text-gray-950 dark:text-white">{{ __('How can I help you today?') }}</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('I can show student information and files, suggest the nearest companies, and register students in companies.') }}
                    </p>

                    <div class="mt-6 grid w-full grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($examples as $example)
                            <button
                                type="button"
                                wire:click="$set('prompt', @js($example))"
                                x-on:click="$nextTick(() => document.getElementById('ai-assistant-prompt')?.focus())"
                                class="rounded-lg border border-gray-200 px-4 py-3 text-start text-sm text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:border-gray-500 dark:hover:bg-gray-900"
                            >
                                {{ $example }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mx-auto flex max-w-3xl flex-col gap-6">
                    @foreach ($messages as $index => $message)
                        @if ($message['role'] === 'user')
                            <article wire:key="ai-message-{{ $index }}" class="flex justify-end">
                                <div class="max-w-[85%] whitespace-pre-line break-words rounded-2xl bg-gray-100 px-4 py-3 text-sm leading-7 text-gray-900 dark:bg-gray-800 dark:text-gray-100">{{ $message['text'] }}</div>
                            </article>
                        @elseif ($message['role'] === 'error')
                            <article wire:key="ai-message-{{ $index }}" class="flex gap-3">
                                <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-danger-50 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400">
                                    @svg('heroicon-o-exclamation-triangle', 'h-4 w-4')
                                </span>
                                <div class="min-w-0 flex-1 rounded-lg border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300">
                                    {{ $message['text'] }}
                                </div>
                            </article>
                        @else
                            <article wire:key="ai-message-{{ $index }}" class="flex gap-3">
                                <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-950 text-white dark:bg-white dark:text-gray-950">
                                    @svg('heroicon-o-sparkles', 'h-4 w-4')
                                </span>
                                <div class="min-w-0 flex-1">
                                    @if (! empty($message['tools']))
                                        <div class="mb-2 flex flex-wrap gap-1.5">
                                            @foreach ($message['tools'] as $tool)
                                                <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                    @svg('heroicon-o-bolt', 'h-3 w-3')
                                                    {{ $toolLabels[$tool] ?? $tool }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div
                                        x-init="$el.querySelectorAll('a').forEach(a => { a.target = '_blank'; a.rel = 'noopener noreferrer'; })"
                                        class="prose prose-sm max-w-none break-words leading-7 text-gray-800 dark:prose-invert dark:text-gray-100 prose-a:text-primary-600 prose-table:text-xs"
                                    >
                                        {!! \Illuminate\Support\Str::markdown($message['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                    </div>
                                </div>
                            </article>
                        @endif
                    @endforeach

                    @if ($isThinking)
                        <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                                @svg('heroicon-o-sparkles', 'h-4 w-4')
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gray-400"></span>
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gray-400 [animation-delay:120ms]"></span>
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gray-400 [animation-delay:240ms]"></span>
                            </span>
                            <span>{{ __('Thinking...') }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <form wire:submit="send" class="border-t border-gray-200 p-3 dark:border-gray-800 sm:p-4">
            <div class="mx-auto flex max-w-3xl items-end gap-2 rounded-2xl border border-gray-300 bg-white p-2 shadow-sm focus-within:border-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:focus-within:border-gray-500">
                <textarea
                    id="ai-assistant-prompt"
                    rows="1"
                    wire:model="prompt"
                    x-data
                    x-init="
                        const resize = () => { $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px'; };
                        $el.addEventListener('input', resize);
                        $wire.$watch('prompt', () => $nextTick(resize));
                    "
                    x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send(); }"
                    @disabled($isThinking)
                    class="max-h-52 min-h-11 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm leading-6 text-gray-950 shadow-none outline-none focus:ring-0 disabled:opacity-60 dark:text-white"
                    placeholder="{{ __('Message the AI assistant...') }}"
                ></textarea>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="send,generate"
                    @disabled($isThinking)
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-950 text-white transition hover:bg-gray-800 disabled:cursor-wait disabled:opacity-60 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                    title="{{ __('Send') }}"
                >
                    @svg('heroicon-o-paper-airplane', 'h-4 w-4 rtl:-scale-x-100')
                    <span class="sr-only">{{ __('Send') }}</span>
                </button>
            </div>

            @error('prompt')
                <p class="mx-auto mt-2 max-w-3xl text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror

            <p class="mx-auto mt-2 max-w-3xl text-center text-xs text-gray-400 dark:text-gray-500">
                {{ __('The assistant can make mistakes. Review important actions before relying on them.') }}
            </p>
        </form>
    </section>
</div>
