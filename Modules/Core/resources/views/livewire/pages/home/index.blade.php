<div>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('Announcements') }}
        </x-slot>
        <div class="px-6 py-10 mx-auto">
            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($this->getAnnouncements() as $announcement)
                    <div class="flex flex-col h-full">

                        <div
                            class="relative w-full aspect-video bg-gray-50 border border-gray-100 rounded-lg overflow-hidden flex items-center justify-center">
                            <img class="object-contain w-full h-full p-2" src="{{ $announcement->getImageAttribute() }}"
                                alt="{{ $announcement->name }}">
                        </div>

                        <div class="mt-4 flex flex-col flex-grow">
                            {{-- <span class="text-blue-500 uppercase">category</span> --}}

                            <h1 class="text-xl font-semibold text-gray-800 dark:text-white">
                                {{ $announcement->name }}
                            </h1>

                            {{-- <p class="mt-2 text-gray-500 dark:text-gray-400">
                                Lorem ipsum dolor sit amet...
                            </p> --}}

                            <div class="flex items-center justify-between mt-auto pt-4">
                                <div>
                                    <a href="#"
                                        class="text-lg font-medium text-gray-700 dark:text-gray-300 hover:underline hover:text-gray-500">
                                        {{ $announcement->createdBy->name ?? 'Admin' }}
                                    </a>

                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $announcement->created_at->format('F j, Y') }}
                                    </p>
                                </div>

                                <a href="{{ route('announcements.details', $announcement->id) }}"
                                    class="inline-block text-blue-500 underline hover:text-blue-400">
                                    {{ __('Read more') }}
                                </a>
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</div>
