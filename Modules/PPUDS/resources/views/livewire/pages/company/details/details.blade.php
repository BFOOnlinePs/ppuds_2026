{{-- عند أي خطأ في الحفظ نصعد لأعلى الصفحة حيث ملخص الأخطاء، لأن الحقل قد يكون في تبويب أو فرع مطوي --}}
<div
    class="w-full bg-white rounded-xl shadow overflow-hidden border border-gray-100"
    x-data
    x-on:form-validation-error.window="
        if ($event.detail.livewireId !== @js($this->getId())) return
        setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 300)
    "
>
    @if ($errors->any())
        <div class="m-4 p-4 rounded-lg bg-danger-50 dark:bg-danger-500/10 border border-danger-200 dark:border-danger-500/20 text-danger-600 dark:text-danger-400">
            <div class="flex items-center gap-2 text-sm font-medium mb-2">
                <x-icon name="solar-danger-triangle-bold" class="w-5 h-5" />
                {{ __('Please fix the following errors') }}
            </div>
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <style>
        .fi-modal.fi-modal-open .fi-modal-close-overlay {
            z-index: 19990 !important;
        }

        .fi-modal.fi-modal-open .fi-modal-close-overlay + .fixed.inset-0 {
            z-index: 20000 !important;
        }
    </style>

    <!-- Cover -->
    <div class="relative w-full h-[340px] md:h-[420px] bg-cover bg-center"
         style="background-image:url('{{ $this->company->getFirstMediaUrl('cover_photo') }}');">

        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>

        <!-- Avatar -->
        <div class="absolute bottom-0 right-6 sm:right-10 translate-y-1/2 z-20">
            <img src="{{ $this->company->getImageAttribute() }}"
                 alt="User Avatar"
                 class="h-32 w-32 md:h-40 md:w-40 rounded-full object-cover
                border-4 border-white shadow-xl bg-white">
        </div>
    </div>

    <!-- Info -->
    <div class="relative px-6 pt-20 pb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

        <div class="text-center sm:text-right">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                {{ $this->company?->name ?? 'اسم المستخدم' }}
            </h1>
        </div>

        @can('Company Update')
            <x-core::button.primary wire:click="save">
                {{ __('Save') }}
            </x-core::button.primary>
        @endcan
    </div>

    <!-- Form -->
    <div class="px-6 py-8 border-t border-gray-100 bg-gray-50/50">
        {{ $this->form }}
    </div>

    <x-filament-actions::modals />
</div>
