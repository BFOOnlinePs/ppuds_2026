{{-- عند أي خطأ في الحفظ نصعد لأعلى الصفحة حيث ملخص الأخطاء، لأن الحقل قد يكون في خطوة أو تبويب مخفي --}}
<div
    x-data
    x-on:form-validation-error.window="
        if ($event.detail.livewireId !== @js($this->getId())) return
        setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 300)
    "
>
    @if ($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-danger-50 dark:bg-danger-500/10 border border-danger-200 dark:border-danger-500/20 text-danger-600 dark:text-danger-400">
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

    {{ $this->form }}

    <x-filament-actions::modals />
</div>
