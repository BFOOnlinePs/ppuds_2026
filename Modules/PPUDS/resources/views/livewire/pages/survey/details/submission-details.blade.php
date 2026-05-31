<div class="w-full max-w-full py-8 px-4 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start w-full">
        <div class="lg:col-span-1 lg:sticky lg:top-8 space-y-6 text-right min-w-0">
            <div class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold rounded-full text-primary-700 bg-primary-50 dark:bg-primary-900/30 dark:text-primary-400">
                <x-heroicon-s-document-text class="w-5 h-5" />
                <span>{{ __('Submission Details') }}</span>
            </div>

            <h1 class="text-4xl lg:text-5xl font-black tracking-tight text-gray-900 dark:text-white leading-tight break-words">
                {{ $survey->title }}
            </h1>

            <div class="space-y-2 text-base text-gray-600 dark:text-gray-400">
                <p class="font-bold text-gray-900 dark:text-white">
                    {{ $user->name }}
                </p>

                @if($studentCompany)
                    <p>{{ __('Evaluated Student') }}: {{ $studentCompany->student?->name }}</p>

                    @if($studentCompany->student?->studentProfile?->student_number)
                        <p>{{ __('Student Number') }}: {{ $studentCompany->student->studentProfile->student_number }}</p>
                    @endif
                @endif

                @if($user->studentProfile?->student_number)
                    <p>{{ __('Student Number') }}: {{ $user->studentProfile->student_number }}</p>
                @endif

                <p>{{ __('Submitted At') }}: {{ $this->submittedAt() }}</p>
            </div>

            @if($survey->description)
                <div class="text-lg leading-relaxed text-gray-600 dark:text-gray-400 border-r-4 border-primary-500 pr-4 prose dark:prose-invert [&_img]:max-w-full [&_img]:h-auto [&_img]:rounded-lg [&_img]:my-4 overflow-hidden break-words">
                    {!! $survey->description !!}
                </div>
            @endif
        </div>

        <div class="lg:col-span-2 w-full min-w-0">
            <div class="w-full relative overflow-hidden bg-white/50 shadow-sm sm:rounded-2xl dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800">
                <div class="absolute top-0 left-0 w-full h-1.5 bg-primary-500"></div>

                <div class="p-6 sm:p-8 w-full">
                    <div class="w-full space-y-6 [&_.fi-fo]:!max-w-none [&_.fi-section]:!max-w-none [&>div]:!max-w-none">
                        {{ $this->form }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
