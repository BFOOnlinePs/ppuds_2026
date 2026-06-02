@php($sections = $this->sections())

<x-filament-widgets::widget>
    @if (count($sections))
        <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-chart-bar-square', 'h-5 w-5 text-primary-600 dark:text-primary-400')
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('Dashboard Statistics Verification') }}
                    </h2>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach ($sections as $section)
                    <article class="rounded-lg border border-gray-200 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-primary-600 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-primary-400 dark:ring-gray-700">
                                    @svg($section['icon'], 'h-5 w-5')
                                </span>

                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $section['title'] }}
                                    </h3>
                                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                                        {{ number_format($section['count']) }}
                                    </p>
                                </div>
                            </div>

                            @if ($section['url'])
                                <a
                                    href="{{ $section['url'] }}"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-primary-700 transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-primary-300 dark:hover:bg-primary-500/10"
                                >
                                    {{ __('View All') }}
                                    @svg('heroicon-o-arrow-top-right-on-square', 'h-4 w-4')
                                </a>
                            @endif
                        </div>

                        @if (count($section['rows']))
                            <div class="divide-y divide-gray-200 overflow-hidden rounded-md border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-900">
                                @foreach ($section['rows'] as $row)
                                    @if ($row['url'])
                                        <a
                                            href="{{ $row['url'] }}"
                                            class="group flex min-h-[64px] items-center justify-between gap-3 px-3 py-2 transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 dark:hover:bg-primary-500/10"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $row['title'] }}
                                                </span>
                                                <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $row['subtitle'] ?: '-' }}
                                                </span>
                                                @if ($row['meta'])
                                                    <span class="mt-1 block truncate text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $row['meta'] }}
                                                    </span>
                                                @endif
                                            </span>

                                            @svg('heroicon-o-chevron-left', 'h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-primary-600 dark:group-hover:text-primary-300 rtl:rotate-180')
                                        </a>
                                    @else
                                        <div class="flex min-h-[64px] items-center gap-3 px-3 py-2">
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $row['title'] }}
                                                </span>
                                                <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $row['subtitle'] ?: '-' }}
                                                </span>
                                                @if ($row['meta'])
                                                    <span class="mt-1 block truncate text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $row['meta'] }}
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-md border border-dashed border-gray-300 bg-white px-3 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                {{ $section['empty'] }}
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</x-filament-widgets::widget>
