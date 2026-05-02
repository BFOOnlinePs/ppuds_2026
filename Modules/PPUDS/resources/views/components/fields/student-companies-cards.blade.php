<div>

    @if ($studentCompanies->isNotEmpty())
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach ($studentCompanies as $studentCompany)
                @php
                    $statusColor = $studentCompany->status?->getColor() ?? 'gray';
                    $statusClasses = match ($statusColor) {
                        'success' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/20',
                        'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300 dark:ring-danger-400/20',
                        default => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20',
                    };
                @endphp

                <a
                    href="{{ route('student-companies.details', $studentCompany) }}"
                    class="group flex min-h-[168px] overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:bg-primary-50/50 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-primary-500/10"
                >
                    <div class="flex w-32 shrink-0 items-center justify-center bg-gray-50 p-4 dark:bg-gray-800 sm:w-40">
                        <img
                            src="{{ $studentCompany->company?->getImageAttribute() }}"
                            alt="{{ $studentCompany->company?->name }}"
                            class="h-24 w-24 rounded-lg object-contain"
                        >
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col p-4">
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 {{ $statusClasses }}">
                                {{ $studentCompany->status?->getLabel() ?? __('Status') }}
                            </span>
                        </div>

                        <h3 class="truncate text-base font-semibold text-gray-950 transition group-hover:text-primary-700 dark:text-white dark:group-hover:text-primary-300">
                            {{ $studentCompany->company?->name ?? __('Company Name') }}
                        </h3>

                        <dl class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500 dark:text-gray-500">{{ __('Branch Name') }}</dt>
                                <dd class="truncate font-medium text-gray-800 dark:text-gray-200">
                                    {{ $studentCompany->branch?->name ?? '-' }}
                                </dd>
                            </div>

                            <div class="min-w-0">
                                <dt class="text-xs text-gray-500 dark:text-gray-500">{{ __('Department') }}</dt>
                                <dd class="truncate font-medium text-gray-800 dark:text-gray-200">
                                    {{ $studentCompany->department?->name ?? '-' }}
                                </dd>
                            </div>
                        </dl>

                        <span class="mt-auto inline-flex items-center gap-1 pt-4 text-sm font-medium text-primary-600 dark:text-primary-400">
                            {{ __('View Training Details') }}
                            @svg('heroicon-o-arrow-left', 'h-4 w-4')
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
            {{ __('No Company Registered') }}
        </div>
    @endif
</div>
