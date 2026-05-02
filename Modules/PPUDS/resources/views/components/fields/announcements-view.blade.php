<div>
    @php($announcements = $this->getAnnouncements())

    @if ($announcements->isNotEmpty())
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($announcements as $announcement)
                <a
                    href="{{ route('announcements.details', $announcement) }}"
                    class="group flex h-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:bg-primary-50/50 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                >
                    <div class="h-28 w-32 shrink-0 bg-gray-50 dark:bg-gray-800">
                        <img
                            src="{{ $announcement->getImageAttribute() }}"
                            alt="{{ $announcement->name }}"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                        >
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col p-4">
                        <div class="mb-2 flex items-center gap-2">
                            @if ($announcement->is_pinned)
                                <span class="inline-flex items-center rounded-md bg-warning-50 px-1.5 py-0.5 text-xs font-medium text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/20">
                                    {{ __('Pinned') }}
                                </span>
                            @endif

                            <span class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $announcement->published_at?->translatedFormat('Y-m-d') ?? $announcement->created_at?->translatedFormat('Y-m-d') }}
                            </span>
                        </div>

                        <h3 class="line-clamp-2 text-sm font-semibold text-gray-950 transition group-hover:text-primary-700 dark:text-white dark:group-hover:text-primary-300">
                            {{ $announcement->name }}
                        </h3>

                        <p class="mt-2 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ str($announcement->content)->stripTags()->limit(110) }}
                        </p>

                        <span class="mt-auto inline-flex items-center gap-1 pt-3 text-sm font-medium text-primary-600 dark:text-primary-400">
                            {{ __('Read more') }}
                            @svg('heroicon-o-arrow-left', 'h-4 w-4')
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
            {{ __('No announcements available') }}
        </div>
    @endif
</div>
