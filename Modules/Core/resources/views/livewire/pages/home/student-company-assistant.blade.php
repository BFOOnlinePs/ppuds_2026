<section
    class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950"
    dir="rtl"
>
    <div class="grid min-h-[680px] grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex min-h-[620px] flex-col bg-white dark:bg-gray-950">
            <header class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800 sm:px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-950 text-white dark:bg-white dark:text-gray-950">
                        @svg('heroicon-o-sparkles', 'h-5 w-5')
                    </span>
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                            مساعد التدريب الذكي
                        </h2>
                        @if ($selectedStudentName)
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $selectedStudentName }}{{ $selectedRegistrationLabel ? ' · '.$selectedRegistrationLabel : '' }}
                            </p>
                        @else
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                PPUDS Assistant
                            </p>
                        @endif
                    </div>
                </div>

                @if ($suggestions !== [])
                    @php($allLinked = collect($suggestions)->every(fn ($suggestion) => $suggestion['linked'] ?? false))

                    <button
                        type="button"
                        wire:click="linkAllSuggestions"
                        wire:confirm="هل تريد ربط كل الاقتراحات بالطالب المحدد؟"
                        wire:loading.attr="disabled"
                        @disabled($allLinked)
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-300 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900 dark:disabled:text-gray-600"
                        title="ربط كل الاقتراحات"
                    >
                        @svg('heroicon-o-link', 'h-4 w-4')
                        <span class="sr-only">ربط كل الاقتراحات</span>
                    </button>
                @endif
            </header>

            <div class="flex-1 overflow-y-auto bg-gray-50/70 px-4 py-5 dark:bg-gray-950 sm:px-6">
                <div class="mx-auto flex max-w-3xl flex-col gap-5">
                    @foreach ($messages as $index => $message)
                        @php($isUser = $message['role'] === 'user')

                        <article
                            wire:key="assistant-message-{{ $index }}"
                            class="flex gap-3 {{ $isUser ? 'justify-end' : 'justify-start' }}"
                        >
                            @unless ($isUser)
                                <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950">
                                    @svg('heroicon-o-sparkles', 'h-4 w-4')
                                </span>
                            @endunless

                            <div class="{{ $isUser ? 'max-w-[82%] rounded-lg bg-gray-900 px-4 py-3 text-sm leading-7 text-white dark:bg-gray-100 dark:text-gray-950' : 'min-w-0 flex-1 text-sm leading-7 text-gray-800 dark:text-gray-100' }}">
                                <div class="whitespace-pre-line break-words">{{ $message['text'] }}</div>
                            </div>
                        </article>
                    @endforeach

                    <div
                        wire:loading
                        wire:target="send,selectStudent,selectCompany,linkCompany,linkAllSuggestions"
                        class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400"
                    >
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                            @svg('heroicon-o-sparkles', 'h-4 w-4')
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gray-400"></span>
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gray-400 [animation-delay:120ms]"></span>
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gray-400 [animation-delay:240ms]"></span>
                        </span>
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="send" class="border-t border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-950 sm:p-4">
                <div class="mx-auto flex max-w-3xl items-end gap-2 rounded-lg border border-gray-300 bg-white p-2 shadow-sm focus-within:border-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:focus-within:border-gray-500">
                    <textarea
                        rows="1"
                        wire:model.defer="studentName"
                        wire:keydown.enter.prevent="send"
                        class="max-h-32 min-h-11 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm leading-6 text-gray-950 shadow-none outline-none focus:ring-0 dark:text-white"
                        placeholder="{{ $selectedStudentName ? 'اسأل عن التدريب أو اكتب اسم شركة للربط' : 'اسأل عن طالب أو اكتب رقمه الجامعي' }}"
                    ></textarea>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-950 text-white transition hover:bg-gray-800 disabled:cursor-wait disabled:opacity-60 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                        title="إرسال"
                    >
                        @svg('heroicon-o-paper-airplane', 'h-4 w-4')
                        <span class="sr-only">إرسال</span>
                    </button>
                </div>

                @error('studentName')
                    <p class="mx-auto mt-2 max-w-3xl text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror
            </form>
        </div>

        <aside class="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/60 xl:border-r xl:border-t-0">
            <div class="space-y-4">
                @if ($studentContext !== [])
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                            @svg('heroicon-o-user-circle', 'h-4 w-4 text-gray-500')
                            <span>سياق الطالب</span>
                        </div>

                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">الطالب</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ data_get($studentContext, 'student_name', '-') }}</dd>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">الرقم</dt>
                                    <dd class="mt-1 truncate text-gray-800 dark:text-gray-200">{{ data_get($studentContext, 'student_number', '-') ?: '-' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">التخصص</dt>
                                    <dd class="mt-1 truncate text-gray-800 dark:text-gray-200">{{ data_get($studentContext, 'major', '-') ?: '-' }}</dd>
                                </div>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 dark:text-gray-400">التسجيل</dt>
                                <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ data_get($studentContext, 'registration', '-') ?: '-' }}</dd>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">الشركة</dt>
                                    <dd class="mt-1 truncate text-gray-800 dark:text-gray-200">{{ data_get($studentContext, 'company', '-') ?: '-' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">الحالة</dt>
                                    <dd class="mt-1 truncate text-gray-800 dark:text-gray-200">{{ data_get($studentContext, 'status', '-') ?: '-' }}</dd>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">أيام الحضور</dt>
                                    <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ data_get($studentContext, 'attendance_days', '-') ?? '-' }}</dd>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">طلبات الغياب</dt>
                                    <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ data_get($studentContext, 'leave_requests', '-') ?? '-' }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>
                @endif

                @if ($studentMatches !== [])
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                            @svg('heroicon-o-users', 'h-4 w-4 text-gray-500')
                            <span>نتائج الطلاب</span>
                        </div>

                        <div class="space-y-2">
                            @foreach ($studentMatches as $student)
                                <button
                                    type="button"
                                    wire:click="selectStudent({{ $student['id'] }})"
                                    class="w-full rounded-lg border border-gray-200 bg-white p-3 text-start text-sm transition hover:border-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-gray-500 dark:hover:bg-gray-900"
                                >
                                    <span class="block truncate font-medium text-gray-950 dark:text-white">{{ $student['name'] }}</span>
                                    <span class="mt-1 block truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $student['student_number'] ?? '-' }} · {{ $student['major'] ?? $student['email'] }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($companyMatches !== [])
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                            @svg('heroicon-o-building-office-2', 'h-4 w-4 text-gray-500')
                            <span>نتائج الشركات</span>
                        </div>

                        <div class="space-y-2">
                            @foreach ($companyMatches as $company)
                                <button
                                    type="button"
                                    wire:click="selectCompany({{ $company['id'] }})"
                                    wire:confirm="هل تريد ربط هذه الشركة بالطالب المحدد؟"
                                    class="w-full rounded-lg border border-gray-200 bg-white p-3 text-start text-sm transition hover:border-gray-400 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-gray-500 dark:hover:bg-gray-900"
                                >
                                    <span class="block truncate font-medium text-gray-950 dark:text-white">{{ $company['name'] }}</span>
                                    <span class="mt-1 block truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $company['category'] ?? '-' }} · {{ $company['branch'] ?? '-' }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($suggestions !== [])
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                            @svg('heroicon-o-sparkles', 'h-4 w-4 text-gray-500')
                            <span>اقتراحات الشركات</span>
                        </div>

                        <div class="space-y-3">
                            @foreach ($suggestions as $suggestion)
                                <article class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                                {{ $suggestion['company_name'] }}
                                            </h3>
                                            <p class="mt-2 line-clamp-3 text-xs leading-5 text-gray-600 dark:text-gray-300">
                                                {{ $suggestion['reason'] }}
                                            </p>
                                        </div>
                                        <span class="shrink-0 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                            {{ $suggestion['fit_score'] }}%
                                        </span>
                                    </div>

                                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <div class="min-w-0">
                                            <dt>الفرع</dt>
                                            <dd class="truncate font-medium text-gray-800 dark:text-gray-200">{{ $suggestion['branch_name'] ?? '-' }}</dd>
                                        </div>
                                        <div class="min-w-0">
                                            <dt>القسم</dt>
                                            <dd class="truncate font-medium text-gray-800 dark:text-gray-200">{{ $suggestion['department_name'] ?? '-' }}</dd>
                                        </div>
                                    </dl>

                                    <div class="mt-3 flex justify-end">
                                        <button
                                            type="button"
                                            wire:click="linkCompany({{ $suggestion['company_id'] }})"
                                            wire:confirm="هل تريد ربط هذه الشركة بالطالب المحدد؟"
                                            wire:loading.attr="disabled"
                                            @disabled($suggestion['linked'] ?? false)
                                            class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-300 px-3 text-xs font-medium text-gray-800 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 dark:border-gray-700 dark:text-gray-100 dark:hover:bg-gray-900 dark:disabled:text-gray-600"
                                        >
                                            @svg(($suggestion['linked'] ?? false) ? 'heroicon-o-check' : 'heroicon-o-link', 'h-4 w-4')
                                            <span>{{ ($suggestion['linked'] ?? false) ? 'تم الربط' : 'ربط' }}</span>
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @elseif ($studentContext === [] && $studentMatches === [] && $companyMatches === [])
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-400">
                        اسأل عن طالب، أو اطلب اقتراح شركات لطالب محدد.
                    </div>
                @endif
            </div>
        </aside>
    </div>
</section>
