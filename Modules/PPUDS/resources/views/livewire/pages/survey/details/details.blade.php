<div>
    <div class="mb-6 rounded-xl border border-gray-100 bg-white p-6 shadow">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 md:text-3xl">
                    {{ $this->survey->title }}
                </h1>

                <p class="mt-2 text-lg font-semibold text-primary-600">
                    {{ __('Target Group') }}:
                    {{ $this->survey->serve_group ? \Modules\Core\Enums\UserRole::tryFrom($this->survey->serve_group)?->getLabel() ?? $this->survey->serve_group : '-' }}
                </p>
            </div>
        </div>
    </div>

    {{ $this->infolist }}
</div>
