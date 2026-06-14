<div>
    <div class="mb-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Import Placements') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Upload an Excel file with the same placement columns to import companies, company supervisors, branches, and student links.') }}</p>
        </div>

        <div class="grid gap-4 px-4 py-4 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Placements Excel') }}</label>
                <input type="file" wire:model="placementImportFile" accept=".xlsx,.xls"
                       class="mt-1 block w-full text-sm text-gray-700 file:me-4 file:rounded-md file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500 dark:text-gray-200">
                @error('placementImportFile')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Academic Year') }}</label>
                <input type="text" wire:model="academicYear"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:text-sm">
                @error('academicYear')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Semester') }}</label>
                <select wire:model="semester"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:text-sm">
                    @foreach ($semesterOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('semester')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 px-4 py-4 dark:border-gray-700">
            <div class="flex flex-wrap gap-4">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="previewOnly"
                           class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                    <span>{{ __('Preview Only') }}</span>
                </label>

                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model="updateExisting"
                           class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                    <span>{{ __('Update Existing') }}</span>
                </label>

                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model="useLatestRegistration"
                           class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                    <span>{{ __('Use Latest Registration') }}</span>
                </label>
            </div>

            <button wire:click="importPlacements" wire:loading.attr="disabled" wire:target="placementImportFile,importPlacements"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-50">
                <svg wire:loading.remove wire:target="placementImportFile,importPlacements" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12-4-4m4 4 4-4M4 20h16" />
                </svg>
                <svg wire:loading wire:target="placementImportFile,importPlacements" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span wire:loading.remove wire:target="placementImportFile,importPlacements">{{ $previewOnly ? __('Preview Import') : __('Run Import') }}</span>
                <span wire:loading wire:target="placementImportFile,importPlacements">{{ __('Importing...') }}</span>
            </button>
        </div>
    </div>

    @if (! empty($result))
        @php($stats = $result['stats'] ?? [])
        @php($issues = $result['issues'] ?? [])

        <div class="mb-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Import Result') }}</h3>
                <span class="rounded-md px-2 py-1 text-xs font-medium {{ $resultWasPreview ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' }}">
                    {{ $resultWasPreview ? __('Preview Only') : __('Imported') }}
                </span>
            </div>

            @if (($stats['missing_students'] ?? 0) > 0 || ($stats['missing_registrations'] ?? 0) > 0 || ($stats['missing_university_supervisors'] ?? 0) > 0)
                <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ __('The file was read successfully, but student links and supervisors require matching records for the selected year and semester.') }}
                </div>
            @endif

            <div class="grid gap-3 px-4 py-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    'rows_seen' => __('Rows'),
                    'companies_created' => __('Companies'),
                    'supervisors_created' => __('Supervisors'),
                    'branches_created' => __('Branches'),
                    'student_company_created' => __('New Links'),
                    'student_company_updated' => __('Updated Links'),
                    'student_company_skipped_existing' => __('Skipped Links'),
                    'registration_supervisors_updated' => __('University Supervisors'),
                    'registration_supervisors_skipped_existing' => __('Skipped Supervisors'),
                    'missing_students' => __('Missing Students'),
                    'missing_registrations' => __('Missing Registrations'),
                    'missing_university_supervisors' => __('Missing Supervisors'),
                    'errors' => __('Errors'),
                ] as $metric => $label)
                    <div class="rounded-md border border-gray-200 px-3 py-2 dark:border-gray-700">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $stats[$metric] ?? 0 }}</div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700">
                <div class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Warnings') }}</div>
                <div class="max-h-72 overflow-auto divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse (array_slice($issues, 0, 50) as $issue)
                        <div class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                            <span class="font-medium">{{ $issue['sheet'] }} #{{ $issue['row'] }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $issue['message'] }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">{{ __('No warnings') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
