{{-- <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow-md flex items-center justify-between"> --}}
<div>
    {{-- <x-filament::card>
        <livewire:core::filament.widget.stats-overview />
    </x-filament::card> --}}

    {{ $this->infolist }}
</div>

{{--    <div class="text-center"> --}}
{{--      <h2 id="greeting" class="text-2xl font-semibold text-gray-800 dark:text-white">مرحباً!</h2> --}}
{{--      <p class="text-gray-500 dark:text-gray-400">نتمنى لك يوماً سعيداً في لوحتك.</p> --}}
{{--    </div> --}}

{{-- </div> --}}

<script>
    const greetingEl = document.getElementById('greeting');
    const now = new Date();
    const hour = now.getHours();

    let greeting = '';

    if (hour >= 5 && hour < 12) {
        greeting = 'صباح الخير ☀️';
    } else if (hour >= 12 && hour < 18) {
        greeting = 'مساء الخير 🌤️';
    } else {
        greeting = 'مساء الخير 🌙';
    }

    greetingEl.textContent = greeting;
</script>
