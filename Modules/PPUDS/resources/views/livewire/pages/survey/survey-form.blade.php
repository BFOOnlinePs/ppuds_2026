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
                        $hasSubmitted = $user ? \Modules\PPUDS\Entities\SurveyAnswer::where('survey_id', $survey->id)->where('submitted_by', $user->id)->exists() : false;
                    @endphp

                    @if(!$user)
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <x-heroicon-o-lock-closed class="w-16 h-16 text-gray-300 mb-4" />
                            <h3 class="text-2xl font-bold">{{ __('Authentication Required') }}</h3>
                        </div>
                    @elseif($hasSubmitted)
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="w-20 h-20 mb-6 rounded-full bg-green-50 flex items-center justify-center">
                                <x-heroicon-o-check-circle class="w-12 h-12 text-green-500" />
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900">{{ __('Thank You!') }}</h2>
                        </div>
                    @else
                        <form wire:submit="submit" class="w-full space-y-6">

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
