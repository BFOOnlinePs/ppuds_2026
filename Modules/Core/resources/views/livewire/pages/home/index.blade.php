<div>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('Announcements') }}
        </x-slot>
        <div class="px-6 py-10 mx-auto">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($this->getAnnouncements() as $announcement)
                    <div class="flex flex-col h-full group">

                        <div
                            class="relative flex items-center justify-center w-full overflow-hidden border border-gray-100 rounded-lg aspect-video bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                            <img class="object-contain w-full h-full p-2 transition-transform duration-300 group-hover:scale-105"
                                src="{{ $announcement->getImageAttribute() }}" alt="{{ $announcement->name }}">
                        </div>

                        <div class="flex flex-col flex-grow mt-4">
                            {{-- <span class="text-sm font-medium tracking-wider uppercase text-primary-600 dark:text-primary-400">category</span> --}}

                            <h1
                                class="text-xl font-semibold text-gray-800 transition-colors dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                <a href="{{ route('announcements.details', $announcement->id) }}">
                                    {{ $announcement->name }}
                                </a>
                            </h1>

                            <div class="flex items-center justify-between pt-4 mt-auto">
                                <div>
                                    <a href="#"
                                        class="text-lg font-medium text-gray-700 transition-colors dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                        {{ $announcement->createdBy->name ?? 'Admin' }}
                                    </a>

                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $announcement->created_at->format('F j, Y') }}
                                    </p>
                                </div>

                                <a href="{{ route('announcements.details', $announcement->id) }}"
                                    class="inline-block font-medium text-primary-600 dark:text-primary-400 transition-colors hover:text-primary-500 dark:hover:text-primary-300">
                                    {{ __('Read more') }} &rarr;
                                </a>
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</div>
