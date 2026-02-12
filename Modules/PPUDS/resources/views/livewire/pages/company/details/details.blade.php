<div class="w-full bg-white rounded-xl shadow overflow-hidden border border-gray-100">

    <!-- Cover -->
    <div class="relative w-full bg-cover bg-center"
         style="height:260px; background-image:url('{{ $this->company->getFirstMediaUrl('cover_photo') }}');">

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

        <x-core::button.primary wire:click="save">
            {{ __('Save') }}
        </x-core::button.primary>
    </div>

    <!-- Form -->
    <div class="px-6 py-8 border-t border-gray-100 bg-gray-50/50">
        {{ $this->form }}
    </div>

    <x-filament-actions::modals />
</div>
