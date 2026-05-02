<div class="space-y-6">
    <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid grid-cols-1 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="aspect-[16/7] w-full bg-gray-50 dark:bg-gray-800">
                    <img
                        src="{{ $announcement->getImageAttribute() }}"
                        alt="{{ $announcement->name }}"
                        class="h-full w-full object-cover"
                    >
                </div>

                <div class="space-y-5 p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($announcement->is_pinned)
                            <span class="inline-flex items-center gap-1 rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300 dark:ring-warning-400/20">
                                @svg('heroicon-o-bookmark', 'h-4 w-4')
                                {{ __('Pinned') }}
                            </span>
                        @endif

                        <span @class([
                            'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1',
                            'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20' => $this->isActive(),
                            'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700' => ! $this->isActive(),
                        ])>
                            @svg($this->isActive() ? 'heroicon-o-check-circle' : 'heroicon-o-clock', 'h-4 w-4')
                            {{ $this->isActive() ? __('Active') : __('Inactive') }}
                        </span>
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ $announcement->name }}
                        </h1>
                    </div>

                    <div class="prose max-w-none text-gray-700 dark:prose-invert dark:text-gray-300">
                        {!! $announcement->content !!}
                    </div>
                </div>
            </div>

            <aside class="border-t border-gray-200 bg-gray-50 p-6 dark:border-gray-800 dark:bg-gray-900/60 lg:border-s lg:border-t-0">
                <h2 class="mb-4 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('Announcement Information') }}
                </h2>

                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Publisher') }}</dt>
                        <dd class="mt-1 font-medium text-gray-950 dark:text-white">
                            {{ $announcement->createdBy?->name ?? __('System') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Published At') }}</dt>
                        <dd class="mt-1 font-medium text-gray-950 dark:text-white">
                            {{ $announcement->published_at?->translatedFormat('Y-m-d H:i') ?? '-' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Expiry Date') }}</dt>
                        <dd class="mt-1 font-medium text-gray-950 dark:text-white">
                            {{ $announcement->expires_at?->translatedFormat('Y-m-d H:i') ?? __('No Expiry') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Target Roles') }}</dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            @forelse ($this->targetRoleLabels() as $role)
                                <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20">
                                    {{ $role }}
                                </span>
                            @empty
                                <span class="text-gray-500 dark:text-gray-400">-</span>
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </aside>
        </div>
    </section>
</div>
