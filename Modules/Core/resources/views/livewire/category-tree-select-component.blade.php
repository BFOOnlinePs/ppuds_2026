@php
    $isSelected = $multiple ? in_array($category['id'], $selected ?? []) : $selected == $category['id'];
    $hasChildren = isset($category['children']) && count($category['children']) > 0;
    $isExpanded = $isExpanded ?? false;
@endphp

<div class="category-item transition-all duration-200">
    <!-- Category Row -->
    <div class="flex items-center px-4 py-3 hover:bg-gradient-to-r hover:from-gray-50 hover:to-blue-50 transition-all duration-200 border-b border-gray-50 last:border-b-0 group"
         style="padding-right: {{ ($level * 28) + 16 }}px;">

        <!-- Expand/Collapse Button -->
        @if($hasChildren)
            <button type="button"
                    wire:click="toggleExpand({{ $category['id'] }})"
                    class="flex-shrink-0 w-7 h-7 mr-3 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-white hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 rounded-lg transition-all duration-200 group-hover:shadow-sm">
                <svg class="w-4 h-4 transform transition-transform duration-300 {{ $isExpanded ? 'rotate-90 text-blue-500' : 'rotate-0' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        @else
            <div class="w-7 h-7 mr-3 flex items-center justify-center">
                <div class="w-1.5 h-1.5 bg-gray-300 rounded-full group-hover:bg-blue-400 transition-colors duration-200"></div>
            </div>
        @endif

        <!-- Custom Checkbox/Radio -->
        <div class="flex-shrink-0 mr-3" wire:click.stop="toggleCategory({{ $category['id'] }})">
            <label class="inline-flex items-center cursor-pointer group/checkbox">
                @if($multiple)
                    <!-- Custom Checkbox -->
                    <div class="relative">
                        <input type="checkbox"
                               class="sr-only"
                               {{ $isSelected ? 'checked' : '' }}
                               wire:model.live="selected"
                               value="{{ $category['id'] }}"
                               onclick="event.stopPropagation();">

                        <div class="w-5 h-5 border-2 rounded-md transition-all duration-200 cursor-pointer
                                    {{ $isSelected
                                        ? 'bg-blue-600 border-blue-600 shadow-md'
                                        : 'bg-white border-gray-300 hover:border-blue-400 group-hover/checkbox:border-blue-500' }}">

                            @if($isSelected)
                                <svg class="w-5 h-5 text-white pointer-events-none" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            @endif
                        </div>
                    </div>
                @else
                    <!-- Custom Radio -->
                    <div class="relative">
                        <input type="radio"
                               class="sr-only"
                               name="category_radio"
                               {{ $isSelected ? 'checked' : '' }}
                               wire:click="toggleCategory({{ $category['id'] }})"
                               onclick="event.stopPropagation();">

                        <div class="w-5 h-5 border-2 border-gray-300 rounded-full transition-all duration-200 cursor-pointer
                                    {{ $isSelected
                                        ? 'border-blue-600 shadow-md bg-white'
                                        : 'bg-white hover:border-blue-400 group-hover/checkbox:border-blue-500' }}">

                            @if($isSelected)
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="w-2.5 h-2.5 bg-blue-600 rounded-full"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </label>
        </div>

        <!-- Category Name -->
        <div class="flex-1 min-w-0 mr-3">
            <button type="button"
                    wire:click="toggleCategory({{ $category['id'] }})"
                    class="w-full text-right focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 rounded-lg px-2 py-1 transition-all duration-200 hover:bg-white hover:shadow-sm">
                <span class="text-sm font-medium transition-colors duration-200 truncate block
                           {{ $isSelected
                               ? 'text-blue-800 font-semibold'
                               : 'text-gray-700 group-hover:text-gray-900' }}">
                    {{ $category['title'] ?? $category['name'] }}
                </span>
            </button>
        </div>

        <!-- Indicators Row -->
        <div class="flex items-center gap-2">
            <!-- Selected Indicator -->
            @if($isSelected)
                <div class="flex-shrink-0">
                    <div class="w-2 h-2 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full shadow-sm animate-pulse"></div>
                </div>
            @endif

            <!-- Children Count Badge -->
            @if($hasChildren)
                <div class="flex-shrink-0">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                               {{ $isSelected || $isExpanded
                                   ? 'bg-blue-100 text-blue-700'
                                   : 'bg-gray-100 text-gray-600 group-hover:bg-blue-50 group-hover:text-blue-600' }}
                               transition-colors duration-200">
                        {{ count($category['children']) }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    <!-- Children Categories -->
    @if($hasChildren)
        <div class="children-container transition-all duration-300 ease-in-out overflow-hidden
                    {{ $isExpanded ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0' }}">
            <div class="bg-gradient-to-r from-gray-25 to-blue-25" style="background: linear-gradient(90deg, #fafafa 0%, #f8faff 100%);">
                @foreach($category['children'] as $child)
                    @include('core::livewire.partials.category-tree-select-component', [
                        'category' => $child,
                        'level' => $level + 1,
                        'isExpanded' => in_array($child['id'], $expandedCategories ?? [])
                    ])
                @endforeach
            </div>
        </div>
    @endif
</div>

<style>
    /* Smooth expansion animation */
    .children-container {
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                    opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Enhanced hover effects */
    .category-item:hover .children-container:not(.max-h-0) {
        background: linear-gradient(90deg, #f8fafc 0%, #f1f5f9 100%);
    }

    /* Selected state animations */
    @keyframes pulseBlue {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.7;
        }
    }

    .animate-pulse {
        animation: pulseBlue 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Focus enhancements */
    .category-item button:focus-visible,
    .category-item input:focus-visible {
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
        outline: none;
    }

    /* Level indicators */
    .category-item[data-level="0"] {
        background: linear-gradient(90deg, transparent 0%, rgba(59, 130, 246, 0.02) 100%);
    }

    .category-item[data-level="1"] {
        background: linear-gradient(90deg, transparent 0%, rgba(59, 130, 246, 0.03) 100%);
    }

    .category-item[data-level="2"] {
        background: linear-gradient(90deg, transparent 0%, rgba(59, 130, 246, 0.04) 100%);
    }
</style>
