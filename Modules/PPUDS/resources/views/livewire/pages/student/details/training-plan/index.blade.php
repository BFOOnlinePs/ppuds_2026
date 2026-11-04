<div class="space-y-6">
    @forelse ($this->plans as $plan)
        <x-filament::section :heading="$plan->course?->name" :description="$plan->major?->name">
            <div class="space-y-6">
                @foreach (['objectives' => __('Objectives'), 'tasks' => __('Tasks'), 'outcomes' => __('Outcomes')] as $field => $label)
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
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p>{{ __('No training plan has been added for this major and course yet.') }}</p>
        </x-filament::section>
    @endforelse
</div>
