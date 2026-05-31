<div class="w-full max-w-full py-8 px-4 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start w-full">

        <div class="lg:col-span-1 lg:sticky lg:top-8 space-y-6 text-right min-w-0">
            <div class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold rounded-full text-primary-700 bg-primary-50 dark:bg-primary-900/30 dark:text-primary-400">
                <x-heroicon-s-document-text class="w-5 h-5" />
                <span>{{ __('Survey Form') }}</span>
            </div>

            <h1 class="text-4xl lg:text-5xl font-black tracking-tight text-gray-900 dark:text-white leading-tight break-words">
                {{ $survey->title }}
            </h1>

            @if($survey->description)
                <div class="text-lg leading-relaxed text-gray-600 dark:text-gray-400 border-r-4 border-primary-500 pr-4 prose dark:prose-invert [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg [&_img]:my-4 overflow-hidden break-words">
                    {!! $survey->description !!}
                </div>
            @endif

            {{-- <div class="pt-8 hidden lg:block">
                <p class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-shield-check class="w-6 h-6 text-green-500" />
                    {{ __('Your responses are strictly confidential.') }}
                </p>
            </div> --}}
        </div>

        <div class="lg:col-span-2 w-full min-w-0">
            <div class="w-full relative overflow-hidden bg-white/50 shadow-sm sm:rounded-2xl dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800">

                <div class="absolute top-0 left-0 w-full h-1.5 bg-primary-500"></div>

                <div class="p-6 sm:p-8 w-full">
                    @php
                        $user = auth()->user();
                        $isCompanyEvaluation = $this->isCompanySupervisorEvaluation();
                        $totalCompanyStudents = $isCompanyEvaluation ? $this->totalCompanyStudentsCount() : 0;
                        $pendingCompanyStudents = $isCompanyEvaluation ? $this->pendingCompanyStudentsCount() : 0;
                        $evaluatedCompanyStudents = $isCompanyEvaluation ? $this->evaluatedCompanyStudentsCount() : 0;
                        $hasSubmitted = $this->hasSubmitted();
                    @endphp

                    @if(!$user)
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <x-heroicon-o-lock-closed class="w-16 h-16 text-gray-300 mb-4" />
                            <h3 class="text-2xl font-bold">{{ __('Authentication Required') }}</h3>
                        </div>
                    @elseif($isCompanyEvaluation && $totalCompanyStudents === 0)
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="w-20 h-20 mb-6 rounded-full bg-gray-50 flex items-center justify-center">
                                <x-heroicon-o-user-group class="w-12 h-12 text-gray-400" />
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900">{{ __('No current students found') }}</h2>
                        </div>
                    @elseif($hasSubmitted)
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="w-20 h-20 mb-6 rounded-full bg-green-50 flex items-center justify-center">
                                <x-heroicon-o-check-circle class="w-12 h-12 text-green-500" />
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900">
                                {{ $isCompanyEvaluation ? __('All current students have been evaluated') : __('Thank You!') }}
                            </h2>
                        </div>
                    @else
                        <form wire:submit="submit" class="w-full space-y-6">
                            @if($isCompanyEvaluation)
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Current Semester Students') }}</p>
                                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-white">{{ $totalCompanyStudents }}</p>
                                    </div>

                                    <div class="rounded-xl border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950/30 p-4">
                                        <p class="text-sm text-green-700 dark:text-green-300">{{ __('Evaluated Students') }}</p>
                                        <p class="mt-1 text-2xl font-black text-green-700 dark:text-green-300">{{ $evaluatedCompanyStudents }}</p>
                                    </div>

                                    <div class="rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-4">
                                        <p class="text-sm text-amber-700 dark:text-amber-300">{{ __('Pending Evaluation') }}</p>
                                        <p class="mt-1 text-2xl font-black text-amber-700 dark:text-amber-300">{{ $pendingCompanyStudents }}</p>
                                    </div>
                                </div>
                            @endif

                            <div class="w-full [&_.fi-fo]:!max-w-none [&_.fi-section]:!max-w-none [&>div]:!max-w-none">
                                {{ $this->form }}
                            </div>

                            <div class="flex justify-end pt-8 mt-8 border-t border-gray-200 dark:border-gray-700">
                                <x-filament::button type="submit" color="warning" size="lg" icon="heroicon-m-paper-airplane" class="px-12 py-3 text-lg w-full sm:w-auto">
                                    {{ __('Submit Survey') }}
                                </x-filament::button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>
