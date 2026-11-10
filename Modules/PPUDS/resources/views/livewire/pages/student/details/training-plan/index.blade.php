<div class="space-y-6">
    @forelse ($this->plans as $plan)
        <x-filament::section :heading="$plan->course?->name" :description="$plan->major?->name">
            <div class="space-y-6">
                @foreach (['objectives' => __('Objectives'), 'tasks' => __('Tasks'), 'outcomes' => __('Outcomes'), 'subjects' => __('Subjects')] as $field => $label)
                    {{-- المواد اختيارية، فلا نعرض عنوانها إذا كانت فارغة --}}
                    @continue(empty($plan->{$field}))
                    <div>
                        <h4 class="font-semibold mb-2">{{ $label }}</h4>
                        <ol class="space-y-1">
                            @foreach ($plan->{$field} ?? [] as $item)
                                <li class="flex gap-2">
                                    <span>{{ $loop->iteration }}.</span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach

                {{-- المواد الجامعية التي درسها الطالب لهذا المساق (من تبويب التسجيل) --}}
                @if ($group = $this->registrationSubjects->get($plan->course_id))
                    <div>
                        <h4 class="font-semibold mb-2">{{ __('University Subjects') }}</h4>
                        <ol class="space-y-1">
                            @foreach ($group['subjects'] as $subject)
                                <li class="flex gap-2">
                                    <span>{{ $loop->iteration }}.</span>
                                    <span>{{ $subject->name }}@if (filled($subject->code)) ({{ $subject->code }})@endif</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p>{{ __('No training plan has been added for this major and course yet.') }}</p>
        </x-filament::section>
    @endforelse

    {{-- مواد الطالب في مساقات لا توجد لها خطة تدريب، حتى لا تختفي من التبويب --}}
    @foreach ($this->registrationSubjects->except($this->plans->pluck('course_id')->all()) as $group)
        <x-filament::section :heading="$group['course']?->name">
            <div>
                <h4 class="font-semibold mb-2">{{ __('University Subjects') }}</h4>
                <ol class="space-y-1">
                    @foreach ($group['subjects'] as $subject)
                        <li class="flex gap-2">
                            <span>{{ $loop->iteration }}.</span>
                            <span>{{ $subject->name }}@if (filled($subject->code)) ({{ $subject->code }})@endif</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </x-filament::section>
    @endforeach
</div>
