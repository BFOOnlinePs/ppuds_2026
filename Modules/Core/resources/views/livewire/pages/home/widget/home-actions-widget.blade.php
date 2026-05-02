@php($links = $this->links())

<x-filament-widgets::widget>
    @if (count($links))
        <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-4 flex items-center gap-2">
                @svg('heroicon-o-squares-2x2', 'h-5 w-5 text-primary-600 dark:text-primary-400')
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('Quick Access') }}
                </h2>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($links as $link)
                    @if ($link['disabled'])
                        <span
                            aria-disabled="true"
                            title="{{ __('Coming Soon') }}"
                            class="inline-flex min-h-[64px] cursor-not-allowed items-center gap-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-400 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-500"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                                @svg($link['icon'], 'h-5 w-5')
                            </span>

                            <span class="min-w-0 flex-1 leading-5">
                                {{ __($link['label']) }}
                            </span>
                        </span>
                    @else
                        <a
                            href="{{ $link['url'] }}"
                            class="group inline-flex min-h-[64px] items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:border-primary-500 dark:hover:bg-primary-500/10 dark:hover:text-primary-300 dark:focus:ring-offset-gray-900"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-primary-600 ring-1 ring-gray-200 transition group-hover:bg-white dark:bg-gray-800 dark:text-primary-400 dark:ring-gray-700 dark:group-hover:bg-gray-900">
                                @svg($link['icon'], 'h-5 w-5')
                            </span>

                            <span class="min-w-0 flex-1 leading-5">
                                {{ __($link['label']) }}
                            </span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
</x-filament-widgets::widget>
