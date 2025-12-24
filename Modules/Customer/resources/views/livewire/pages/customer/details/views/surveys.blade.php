<!-- Merged: Your structure + my pro styling (RTL, Tailwind only) -->
<div>
    @if(isset($surveys) && !empty($surveys))
        <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50">
            <!-- Decorative blobs -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-32 -end-32 h-72 w-72 rounded-full bg-indigo-200/30 blur-3xl"></div>
                <div class="absolute -bottom-24 -start-24 h-80 w-80 rounded-full bg-blue-200/30 blur-3xl"></div>
            </div>

            <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                @if(isset($surveys))
                    <!-- Header card -->
                    <div class="bg-white/90 backdrop-blur rounded-2xl border border-gray-200/70 shadow-sm p-6 sm:p-8 mb-8">
                        <div class="flex items-start gap-4">
                            <div class="shrink-0 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6">
                                    <path d="M5 3a2 2 0 0 0-2 2v1h18V5a2 2 0 0 0-2-2H5Zm16 5H3v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8ZM7 12h7a1 1 0 1 1 0 2H7a1 1 0 1 1 0-2Zm0 4h10a1 1 0 1 1 0 2H7a1 1 0 1 1 0-2Z"/>
                                </svg>
                            </div>
                            <div class="grow">
                                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                                    <a href="{{ route('surveys.index') }}">{{ $surveys->name }}</a>
                                </h1>
                                @if(!empty($surveys->description))
                                    <p class="mt-2 text-gray-600 leading-relaxed">{{ $surveys->description }}</p>
                                @endif
                                @if(is_iterable($surveys->questions))
                                    <div class="mt-3 text-sm text-gray-500">
                                        <span class="font-medium text-gray-700">عدد الأسئلة:</span>
                                        {{ $surveys->questions->count() }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if(is_iterable($surveys->questions) && !$surveys->questions->isEmpty())
                        <form wire:submit.prevent="save" class="space-y-6">
                            @csrf
                            @foreach($surveys->questions as $index => $question)
                                @if($question)
                                    <section class="group rounded-2xl border border-gray-200 bg-white shadow-sm transition-all duration-300 hover:shadow-md">
                                        <div class="p-6 sm:p-8">
                                            <!-- Question header -->
                                            <div class="mb-5 flex items-start justify-between gap-4">
                                                <div>
                                                    <h3 class="text-lg sm:text-xl font-bold text-gray-900">
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-indigo-50 px-2 text-sm font-semibold text-indigo-700 border border-indigo-100">{{ $index + 1 }}</span>
                                                    <span>{{ $question->name }}</span>
                                                </span>
                                                        @if($question->is_required ?? false)
                                                            <span class="align-middle ms-2 rounded-md bg-red-50 px-2 py-0.5 text-[12px] font-semibold text-red-600 border border-red-100">إلزامي</span>
                                                        @endif
                                                    </h3>
                                                    @if(!empty($question->hint))
                                                        <p class="mt-2 text-sm text-gray-500">{{ $question->hint }}</p>
                                                    @endif
                                                </div>
                                            </div>

                                            @switch($question->type)

                                                {{-- RADIO: chip cards --}}
                                                @case(\Modules\Clinic\Enums\QuestionType::RADIO)
                                                    @if(is_iterable($question->options))
                                                        <div class="grid gap-3 sm:grid-cols-2">
                                                            @foreach($question->options as $option)
                                                                @php $val = $option['value'] ?? ''; @endphp
                                                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 p-4 cursor-pointer hover:border-indigo-300 transition-colors">
                                                                    <input type="radio"
                                                                           wire:model="answers.question-{{ $question->id }}"
                                                                           value="{{ $val }}"
                                                                           class="h-5 w-5 accent-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                                        @required($question->is_required ?? false)>
                                                                    <span class="text-gray-800 font-medium">{{ $val !== '' ? $val : 'خيار غير متوفر' }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>

                                                        @if(isset($answers['question-'.$question->id]) && $answers['question-'.$question->id] === 'other')
                                                            <textarea
                                                                rows="5"
                                                                class="w-full mt-4 rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/50 p-4 placeholder:text-gray-400"
                                                                placeholder="اكتب وصفاً هنا..."
                                                                wire:model="answers.question-{{ $question->id }}-description"
                                                            ></textarea>
                                                        @endif
                                                    @endif
                                                    @break

                                                    {{-- CHECKBOX: chip cards --}}
                                                @case(\Modules\Clinic\Enums\QuestionType::CHECKBOX)
                                                    @if(is_iterable($question->options))
                                                        <div class="grid gap-3 sm:grid-cols-2">
                                                            @foreach($question->options as $option)
                                                                @php $val = $option['value'] ?? ''; @endphp
                                                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 p-4 cursor-pointer hover:border-indigo-300 transition-colors">
                                                                    <input type="checkbox"
                                                                           wire:model="answers.question-{{ $question->id }}"
                                                                           value="{{ $val }}"
                                                                           class="h-5 w-5 rounded-md accent-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                                    <span class="text-gray-800 font-medium">{{ $val !== '' ? $val : 'خيار غير متوفر' }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>

                                                        @if(isset($answers['question-'.$question->id]) && is_array($answers['question-'.$question->id]) && in_array('other', $answers['question-'.$question->id]))
                                                            <textarea
                                                                rows="5"
                                                                class="w-full mt-4 rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/50 p-4 placeholder:text-gray-400"
                                                                placeholder="اكتب وصفاً هنا..."
                                                                wire:model="answers.question-{{ $question->id }}-description"
                                                            ></textarea>
                                                        @endif
                                                    @endif
                                                    @break

                                                    {{-- TEXT --}}
                                                @case(\Modules\Clinic\Enums\QuestionType::TEXT)
                                                    <input type="text"
                                                           wire:model="answers.question-{{ $question->id }}"
                                                           class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/50 p-4 placeholder:text-gray-400"
                                                           placeholder="اكتب إجابتك هنا..."
                                                        @required($question->is_required ?? false)>
                                                    @break

                                                    {{-- TEXTAREA --}}
                                                @case(\Modules\Clinic\Enums\QuestionType::TEXTAREA)
                                                    <textarea
                                                        wire:model="answers.question-{{ $question->id }}"
                                                        rows="5"
                                                        class="w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/50 p-4 placeholder:text-gray-400"
                                                        placeholder="اكتب إجابتك هنا..."
                                                @required($question->is_required ?? false)
                                            ></textarea>
                                                    @break

                                                @default
                                                    <p class="text-red-600">نوع السؤال غير معروف</p>
                                            @endswitch

                                            @error('question-'.$question->id)
                                            <p class="mt-3 text-sm font-medium text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </section>
                                @endif
                            @endforeach

                            <!-- Sticky submit bar with Livewire states -->
                            <div class="sticky bottom-4 z-10">
                                <div class="mx-auto rounded-2xl border border-indigo-200/60 bg-indigo-600 text-white shadow-lg shadow-indigo-300/30">
                                    <button
                                        wire:click="save"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-75 cursor-not-allowed"
                                        wire:target="save"
                                        type="submit"
                                        class="w-full px-6 py-4 text-lg font-bold tracking-wide hover:bg-indigo-700 active:bg-indigo-800 rounded-2xl focus:outline-none focus:ring-4 focus:ring-white/40"
                                    >
                                        <span wire:loading.remove wire:target="save">إرسال الإجابات</span>
                                        <span wire:loading wire:target="save">جاري الإرسال...</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    @else
        <div class="flex items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-blue-50 p-10">
            <div class="bg-white shadow-lg rounded-2xl p-10 text-center max-w-md w-full" style="padding: 20px">
                <h1 class="text-2xl font-bold text-gray-800 mb-4">
                    {{ __('Survey') }}
                </h1>
                <p class="text-gray-600 mb-8">
                    {{ __('Create and manage your surveys easily from here.') }}
                </p>

                <x-core::button.primary href="{{ route('surveys.index') }}" class="px-6 py-3 text-lg rounded-xl">
                    {{ __('Add Survey') }}
                </x-core::button.primary>
            </div>
        </div>
    @endif
</div>
