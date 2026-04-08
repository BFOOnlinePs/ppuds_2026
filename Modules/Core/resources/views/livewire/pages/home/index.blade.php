<div>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('Announcements') }}
        </x-slot>
        <div class="px-6 py-10 mx-auto">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($this->getAnnouncements() as $announcement)
                    <div
                        class="flex flex-col h-full overflow-hidden transition-all duration-300 bg-white border border-gray-200 rounded-2xl shadow-sm group hover:shadow-lg hover:border-primary-500 dark:bg-gray-900 dark:border-gray-800 dark:hover:border-primary-500">

                        <div class="relative overflow-hidden aspect-[4/3] sm:aspect-[3/2] lg:aspect-video">
                            <img class="object-cover object-center w-full h-full transition-transform duration-500 group-hover:scale-105"
                                src="{{ $announcement->getImageAttribute() }}" alt="{{ $announcement->name }}">
                        </div>

                        <div class="flex flex-col flex-grow p-6">
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

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $announcement->created_at->format('F j, Y') }}
                                    </p>
                                </div>

                                <a href="{{ route('announcements.details', $announcement->id) }}"
                                    class="inline-flex items-center gap-1 font-medium text-primary-600 dark:text-primary-400 transition-colors hover:text-primary-500 dark:hover:text-primary-300">
                                    {{ __('Read more') }} <span aria-hidden="true">&rarr;</span>
                                </a>
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</div>
