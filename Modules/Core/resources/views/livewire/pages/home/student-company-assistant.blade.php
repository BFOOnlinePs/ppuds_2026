<x-filament-widgets::widget>
    <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-sparkles', 'h-5 w-5 text-primary-600 dark:text-primary-400')
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    مساعد اقتراح الشركات
                </h2>
            </div>

            @if ($suggestions !== [])
                @php($allLinked = collect($suggestions)->every(fn ($suggestion) => $suggestion['linked'] ?? false))

                <button
                    type="button"
                    wire:click="linkAllSuggestions"
                    wire:loading.attr="disabled"
                    @disabled($allLinked)
                    class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600 dark:disabled:bg-gray-700 dark:disabled:text-gray-400"
                >
                    @svg('heroicon-o-link', 'h-4 w-4')
                    <span>ربط كل الاقتراحات</span>
                </button>
            @endif
        </div>

        @if ($selectedStudentName)
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="inline-flex rounded-md bg-primary-50 px-2.5 py-1 font-medium text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">
                    {{ $selectedStudentName }}
                </span>
                @if ($selectedRegistrationLabel)
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ $selectedRegistrationLabel }}
                    </span>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="flex min-h-[340px] flex-col rounded-lg border border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-950/40">
                <div class="flex-1 space-y-3 overflow-y-auto p-4">
                    @foreach ($messages as $message)
                        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div
                                class="max-w-[88%] rounded-lg px-3 py-2 text-sm leading-6 {{ $message['role'] === 'user' ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10' }}"
                            >
                                {{ $message['text'] }}
                            </div>
                        </div>
                    @endforeach

                    <div wire:loading wire:target="send,selectStudent,linkCompany,linkAllSuggestions" class="text-sm text-gray-500 dark:text-gray-400">
                        جار المعالجة...
                    </div>
                </div>

                <form wire:submit.prevent="send" class="border-t border-gray-200 p-3 dark:border-gray-800">
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            type="text"
                            wire:model.defer="studentName"
                            class="min-h-11 flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            placeholder="اسم الطالب أو الرقم الجامعي"
                        >

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-500 disabled:cursor-wait disabled:opacity-70"
                        >
                            @svg('heroicon-o-paper-airplane', 'h-4 w-4')
                            <span>إرسال</span>
                        </button>
                    </div>

                    @error('studentName')
                        <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </form>
            </div>

            <div class="space-y-3">
                @if ($studentMatches !== [])
                    <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-3 text-sm font-medium text-gray-950 dark:text-white">نتائج الطلاب</div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($studentMatches as $student)
                                <button
                                    type="button"
                                    wire:click="selectStudent({{ $student['id'] }})"
                                    class="rounded-lg border border-gray-200 bg-white p-3 text-start text-sm transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                                >
                                    <span class="block font-medium text-gray-950 dark:text-white">{{ $student['name'] }}</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $student['student_number'] ?? '-' }} · {{ $student['major'] ?? $student['email'] }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @forelse ($suggestions as $suggestion)
                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $suggestion['company_name'] }}
                                </h3>
                                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                    {{ $suggestion['reason'] }}
                                </p>
                            </div>

                            <span class="shrink-0 rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20">
                                {{ $suggestion['fit_score'] }}%
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-1 gap-3 text-sm text-gray-600 dark:text-gray-400 sm:grid-cols-3">
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500 dark:text-gray-500">الفرع</dt>
                                <dd class="truncate font-medium text-gray-800 dark:text-gray-200">{{ $suggestion['branch_name'] ?? '-' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500 dark:text-gray-500">القسم</dt>
                                <dd class="truncate font-medium text-gray-800 dark:text-gray-200">{{ $suggestion['department_name'] ?? '-' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500 dark:text-gray-500">الطلاب الحاليون</dt>
                                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $suggestion['current_students_count'] }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="button"
                                wire:click="linkCompany({{ $suggestion['company_id'] }})"
                                wire:loading.attr="disabled"
                                @disabled($suggestion['linked'] ?? false)
                                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-700 transition hover:border-primary-300 hover:bg-primary-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-500 dark:border-primary-400/20 dark:bg-primary-400/10 dark:text-primary-300 dark:hover:bg-primary-400/20 dark:disabled:border-gray-700 dark:disabled:bg-gray-800 dark:disabled:text-gray-500"
                            >
                                @svg(($suggestion['linked'] ?? false) ? 'heroicon-o-check' : 'heroicon-o-link', 'h-4 w-4')
                                <span>{{ ($suggestion['linked'] ?? false) ? 'تم الربط' : 'ربط الشركة' }}</span>
                            </button>
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
                        لا توجد اقتراحات بعد.
                    </div>
                @endforelse
            </div>
        </div>
    </section>
</x-filament-widgets::widget>
