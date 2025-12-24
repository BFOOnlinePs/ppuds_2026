@php
    //  app() an instanc of the service container
    //  \Modules\Core\Services\SidebarService::class is the class name of the service to be resolved
    //  all() is a method call on the resolved service instance
    //  The result is stored in the $sidebar variable
    $sidebar = app(\Modules\Core\Services\SidebarService::class)->all();

    //  app() an instance of the service container
    //  \Modules\Core\Entities\Settings::class is the class name of the service to be resolved
    //  first() is a method call on the resolved service instance
    //  The result is stored in the $settings variable
    $settingsModel = app(\Modules\Core\Entities\Settings::class)->first();

    //  Initialize $activeGroupKey to null, which is the default value indicating no active group
    $activeGroupKey = null;
@endphp

{{--  Use Blade's @foreach to access the $loop variable and find the active group --}}
@foreach($sidebar as $item)
    @if ($item['type'] === 'group' && isset($item['children']))
        @foreach ($item['children'] as $subitem)
            @if (isset($subitem['route']) && request()->routeIs($subitem['route']))
                @php
                    //  If an active child is found, assign the parent's key to $activeGroupKey
                    //  This is done inside a @php block where $loop->parent->index is accessible
                    $activeGroupKey = $item['key'] ?? 'key'.$loop->parent->index;
                @endphp
                {{--  Exit both loops since the active group has been found --}}
                @break(2)
            @endif
        @endforeach
    @endif
@endforeach


<div :class="{'dark text-white-dark' : $store.app.semidark}">
    {{--  The main navigation bar for the sidebar --}}
    <nav
        x-data="sidebar"
        class="sidebar fixed top-0 bottom-0 z-50 h-full min-h-screen w-[260px] shadow-[5px_0_25px_0_rgba(94,92,154,0.1)] transition-all duration-300"
    >
        <div class="h-full bg-white dark:bg-[#0e1726]">
            {{--  Sidebar header with logo and collapse button --}}
            <div class="flex items-center justify-between px-4 py-3">
                <a href="index.html" class="main-logo flex shrink-0 items-center">
                    {{--  Display the site logo --}}
                    <img class="ml-[5px] w-8 flex-none" src="{{ $settingsModel?->getLogoUrl() }}" alt="image" />
                    {{--  Display the site name --}}
                    <span class="align-middle text-lg font-semibold ltr:ml-1.5 rtl:mr-1.5 dark:text-white-light lg:inline">{{ $settings->site_name }}</span>
                </a>
                {{--  Sidebar toggle button --}}
                <a
                    href="javascript:;"
                    class="collapse-icon flex h-8 w-8 items-center rounded-full transition duration-300 hover:bg-gray-500/10 rtl:rotate-180 dark:text-white-light dark:hover:bg-dark-light/10"
                    @click="$store.app.toggleSidebar()"
                >
                    <svg class="m-auto h-5 w-5" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path
                            opacity="0.5"
                            d="M16.9998 19L10.9998 12L16.9998 5"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </a>
            </div>
            {{--  Sidebar menu list --}}
            {{--  The activeDropdown is initialized with the value of $activeGroupKey --}}
            <ul
                class="perfect-scrollbar relative h-[calc(100vh-80px)] space-y-0.5 overflow-y-auto overflow-x-hidden p-4 py-0 font-semibold"
                x-data="{ activeDropdown: '{{ $activeGroupKey }}' }"
            >
                {{--  Iterate over each item in the $sidebar array --}}
                @foreach($sidebar as $item)
                    {{--  Check if the current item is a group --}}
                    @if($item['type'] === 'group')
                        <li class="menu nav-item">
                            {{--  Button to toggle the dropdown menu --}}
                            {{--  The active class is applied if activeDropdown matches the item's key --}}
                            <button
                                type="button"
                                class="nav-link group"
                                :class="{'active' : activeDropdown === '{{ $item['key'] ?? 'key'.$loop->index }}'}"
                                @click="activeDropdown === '{{ $item['key'] ?? 'key'.$loop->index }}' ? activeDropdown = null : activeDropdown = '{{ $item['key'] ?? 'key'.$loop->index }}'"
                            >
                                <div class="flex items-center">
                                    {{--  Display the item's icon, either as an image or an SVG --}}
                                    @if(Str::startsWith($item['icon'], 'http') || Str::contains($item['icon'], '/'))
                                        <img src="{{ asset($item['icon']) }}" alt="img" class="h-5 w-5" />
                                    @else
                                        @svg($item['icon'])
                                    @endif
                                    {{--  Display the item's title --}}
                                    <span class="text-black ltr:pl-3 rtl:pr-3 dark:text-[#506690] dark:group-hover:text-white-dark">
                                        {{ __($item['title']) }}
                                    </span>
                                </div>
                                {{--  Arrow icon to indicate dropdown state --}}
                                {{--  The rotation is toggled based on activeDropdown --}}
                                <div class="rtl:rotate-180" :class="{'!rotate-90' : activeDropdown === '{{ $item['key'] ?? 'key'.$loop->index }}'}">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </div>
                            </button>
                            {{--  Sub-menu for the group --}}
                            {{--  The menu is shown if activeDropdown matches the item's key --}}
                            <ul x-cloak x-show="activeDropdown === '{{ $item['key'] ?? 'key'.$loop->index }}'" x-collapse class="sub-menu text-gray-500">
                                {{--  Iterate over each child of the group --}}
                                @foreach($item['children'] as $subitem)
                                    <li>
                                        {{--  Link to the subitem's route --}}
                                        {{--  The active class is applied if the subitem's route is the current route --}}
                                        <a href="{{ route($subitem['route']) }}" class="{{ request()->routeIs($subitem['route']) ? 'active' : '' }}">
                                            {{--  Display the subitem's icon if it exists --}}
                                            @if(isset($subitem['icon']))
                                                @if(Str::startsWith($subitem['icon'], 'http') || Str::contains($subitem['icon'], '/'))
                                                    <img src="{{ asset($subitem['icon']) }}" alt="img" class="h-4 w-4" />
                                                @else
                                                    {{--  Placeholder for SVG icon if needed --}}
                                                @endif
                                            @endif
                                            {{--  Display the subitem's title --}}
                                            <span>{{ __($subitem['title']) }}</span>
                                            {{--  Display a badge if it exists --}}
                                            @if(!empty($subitem['badge']))
                                                <span class="badge">{{ $subitem['badge'] }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        {{--  This is a single menu item (not a group) --}}
                        <li class="nav-item">
                            {{--  Link to the item's route --}}
                            {{--  The active class is applied if the item's route is the current route --}}
                            <a href="{{ route($item['route']) }}" class="group {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                                <div class="flex items-center">
                                    {{--  Display the item's icon, either as an image or an SVG --}}
                                    @if(Str::startsWith($item['icon'], 'http') || Str::contains($item['icon'], '/'))
                                        <img src="{{ asset($item['icon']) }}" alt="img" class="h-5 w-5" />
                                    @else
                                        @svg($item['icon'])
                                    @endif
                                    {{--  Display the item's title --}}
                                    <span class="text-black ltr:pl-3 rtl:pr-3 dark:text-[#506690] dark:group-hover:text-white-dark">
                                        {{ __($item['title']) }}
                                    </span>
                                    {{--  Display a badge if it exists --}}
                                    @if(!empty($item['badge']))
                                        <span class="badge">{{ $item['badge'] }}</span>
                                    @endif
                                </div>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </nav>
</div>
