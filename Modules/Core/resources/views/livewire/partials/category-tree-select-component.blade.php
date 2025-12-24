@php
    $isSelected = $multiple ? in_array($category['id'], $selected ?? []) : $selected == $category['id'];
    $hasChildren = isset($category['children']) && count($category['children']) > 0;
    $isExpanded = $isExpanded ?? false;
@endphp

<div class="category-item" data-category-id="{{ $category['id'] }}">
    <!-- Category Row -->
    <div class="flex items-center px-4 py-3 hover:bg-gray-50 transition-colors duration-150 border-b border-gray-100 last:border-b-0"
         style="padding-right: {{ ($level * 28) + 16 }}px;">

        <!-- Expand/Collapse Button -->
        @if($hasChildren)
            <button type="button"
                    wire:click="toggleExpand({{ $category['id'] }})"
                    class="flex-shrink-0 w-6 h-6 mr-3 flex items-center justify-center text-gray-400 hover:text-gray-600 focus:outline-none rounded transition-colors duration-200">
                <svg class="w-4 h-4 transform transition-transform duration-200 {{ $isExpanded ? 'rotate-90 text-blue-500' : 'rotate-0' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        @else
            <div class="w-6 h-6 mr-3 flex items-center justify-center">
                <div class="w-1.5 h-1.5 bg-gray-300 rounded-full"></div>
            </div>
        @endif

        <!-- Checkbox/Radio with Click Handler -->
        <div class="flex-shrink-0 mr-3">
            <button type="button"
                    wire:click="toggleCategory({{ $category['id'] }})"
                    class="flex items-center focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 rounded p-1">

                @if($multiple)
                    <!-- Checkbox -->
                    <div class="relative w-5 h-5 checkbox-animation">
                        <div class="w-5 h-5 border-2 rounded transition-all duration-200
                                    {{ $isSelected
                                        ? 'bg-blue-600 border-blue-600'
                                        : 'bg-white border-gray-300 hover:border-blue-400' }}">
                        </div>

                        @if($isSelected)
                            <div class="absolute inset-0 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Radio -->
                    <div class="relative w-5 h-5 checkbox-animation">
                        <div class="w-5 h-5 border-2 border-gray-300 rounded-full transition-all duration-200
                                    {{ $isSelected ? 'border-blue-600' : 'hover:border-blue-400' }}">
                        </div>

                        @if($isSelected)
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="w-2.5 h-2.5 bg-blue-600 rounded-full"></div>
                            </div>
                        @endif
                    </div>
                @endif
            </button>
        </div>

        <!-- Category Name -->
        <div class="flex-1 min-w-0 mr-3">
            <button type="button"
                    wire:click="toggleCategory({{ $category['id'] }})"
                    class="w-full text-right focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 rounded px-2 py-1 hover:bg-gray-100 transition-colors duration-200">
                <span class="text-sm font-medium truncate block {{ $isSelected ? 'text-blue-800' : 'text-gray-700' }}">
                    {{ $category['title'] ?? $category['name'] }}
                </span>
            </button>
        </div>

        <!-- Selected Indicator & Children Count -->
        <div class="flex items-center gap-2">
            @if($isSelected)
                <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
            @endif

            @if($hasChildren)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                    {{ count($category['children']) }}
                </span>
            @endif
        </div>
    </div>

    <!-- Children Categories -->
    @if($hasChildren && $isExpanded)
        <div class="children-container bg-gray-25">
            @foreach($category['children'] as $child)
                @include('core::livewire.partials.category-tree-select-component', [
                    'category' => $child,
                    'level' => $level + 1,
                    'isExpanded' => in_array($child['id'], $expandedCategories ?? [])
                ])
            @endforeach
        </div>
    @endif
</div>
