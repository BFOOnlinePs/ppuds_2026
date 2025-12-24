<div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    @php $current = LaravelLocalization::getCurrentLocale(); @endphp

    {{-- Language switcher --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div class="flex justify-center">
            <ul class="inline-flex gap-2 bg-white/70 dark:bg-slate-900/60 backdrop-blur-md p-2 rounded-2xl shadow-lg ring-1 ring-black/5 dark:ring-white/10">
                @foreach(LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                    <li>
                        <a rel="alternate" hreflang="{{ $localeCode }}"
                           href="{{ LaravelLocalization::getLocalizedURL($localeCode, null, [], true) }}"
                           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-sm font-medium transition-all
                                  focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/60
                                  {{ $localeCode === $current
                                      ? 'bg-indigo-600 text-white shadow-sm'
                                      : 'text-slate-700 hover:text-indigo-700 hover:bg-slate-100/70 dark:text-slate-200 dark:hover:text-white dark:hover:bg-slate-800/70' }}">
                            <img class="w-5 h-5 rounded-sm ring-1 ring-black/10 dark:ring-white/10"
                                 src="{{ asset('assets/dashboard/admin-theme/images/flags/' . $localeCode . '.png') }}"
                                 alt="{{ $properties['native'] }} flag">
                            <span>{{ $properties['native'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Header --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <h1 class="text-3xl font-semibold text-slate-900 dark:text-white">وحدات العميل</h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400 text-sm">اختر الوحدة التي ترغب بإدارتها أو استعراضها.</p>
    </div>

    {{-- Modules grid --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
            @foreach($this->getAvailableFeatures() as $module => $feature)
                <button type="button"
                        wire:click="selectFeature('{{ e($feature['name']) }}')"
                        class="group relative w-full h-full overflow-hidden rounded-2xl
                               border border-slate-200/70 dark:border-slate-800/60
                               bg-white/90 dark:bg-slate-900/80 backdrop-blur
                               shadow-sm transition-all duration-300
                               hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    {{-- glow bar on hover --}}
                    <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-fuchsia-500 to-sky-500
                                 opacity-0 group-hover:opacity-100 transition-opacity"></span>

                    {{-- badge --}}
                    @if(!empty($module))
                        <span class="absolute top-3 end-3 rounded-full bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200
                                     dark:bg-indigo-500/15 dark:text-indigo-200 dark:ring-indigo-500/30
                                     px-2.5 py-1 text-[11px] font-medium">
                            {{ Str::upper($module) }}
                        </span>
                    @endif

                    <div class="p-6 flex flex-col items-center text-center gap-3">
                        {{-- Icon --}}
                        <div class="grid place-items-center size-14 rounded-xl
                                    bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100
                                    group-hover:scale-105 transition">
                            @svg($feature['icon'], 'w-7 h-7')
                        </div>

                        {{-- Title --}}
                        <h3 class="mt-1 text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                            {{ __($feature['display_name']) ?? 'بدون اسم' }}
                        </h3>

                        {{-- Description (two lines max) --}}
                        <p class="text-sm text-slate-600 dark:text-slate-400 line-clamp-2">
                            {{ __($feature['description']) ?? 'بدون وصف' }}
                        </p>

                        {{-- CTA --}}
                        <div class="mt-2 inline-flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
                            <span class="text-sm font-medium">فتح الوحدة</span>
                            <svg class="w-4 h-4 rtl:rotate-180 transition-transform group-hover:translate-x-0.5"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    </div>
</div>
