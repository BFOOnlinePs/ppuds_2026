<div>
    {{-- Statistics Cards --}}

    {{-- Quick Actions & Sections --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6">
        @foreach($sections as $section)
            <div class="bg-white rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100">
                {{-- Section Header --}}
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <div class="bg-white p-2 rounded-lg shadow-sm">
                            <svg class="w-6 h-6 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800">{{ $section['title'] }}</h2>
                    </div>
                </div>

                {{-- Section Items --}}
                <div class="p-4 space-y-2">
                    @foreach($section['items'] as $item)
                        <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                           class="group block bg-gray-50 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 rounded-xl p-4 transition-all duration-300 border border-transparent hover:border-blue-200">
                            <div class="flex items-start gap-3">
                                <div class="bg-white group-hover:bg-blue-500 p-2 rounded-lg shadow-sm transition-all duration-300">
                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-white transition-colors" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-800 group-hover:text-blue-600 transition-colors mb-1">
                                        {{ $item['label'] }}
                                    </h3>
                                    <p class="text-sm text-gray-500 leading-relaxed">{{ $item['desc'] }}</p>
                                </div>
                                <div class="text-gray-400 group-hover:text-blue-500 transition-colors">
                                    {{-- Note: In LTR, this icon usually points right (>). The rotate-180 combined with the path might need adjustment depending on the base icon. --}}
                                    <svg class="w-5 h-5 transform rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Recent Activity Section --}}
    <div class="mt-8 bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                </svg>
                Recent Activity
            </h2>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @for($i = 0; $i < 5; $i++)
                    <div class="flex items-center gap-4 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                        <div class="bg-blue-100 p-2 rounded-full">
                            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-gray-800 font-medium">New user added</p>
                            <p class="text-sm text-gray-500">{{ $i + 1 }} hours ago</p>
                        </div>
                        <span class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-medium">Completed</span>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <style>
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-in {
            animation: slideIn 0.5s ease-out forwards;
        }
    </style>
</div>
