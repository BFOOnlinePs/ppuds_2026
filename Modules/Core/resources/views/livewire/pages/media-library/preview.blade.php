{{-- Full size preview of one library file, shown inside the table's view modal. --}}
<div class="space-y-6 p-4">

    @if ($asset->is_image)
        {{-- Bounded by the viewport, never by a conversion: this is the original file. --}}
        <img src="{{ $asset->url }}"
             alt="{{ $asset->alt_text }}"
             class="w-full rounded-lg"
             style="max-height: 70vh; object-fit: contain;">
    @else
        <div class="rounded-lg border border-gray-200 p-4 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            {{ $asset->file?->file_name }}
        </div>
    @endif

    <dl class="space-y-2 text-sm">
        @foreach ([
            __('File Name') => $asset->file?->file_name,
            __('Alt Text') => $asset->alt_text,
            __('Dimensions') => $asset->dimensions,
            __('Size') => $asset->file ? \Illuminate\Support\Number::fileSize($asset->file->size, precision: 1) : null,
            __('File Type') => $asset->file?->mime_type,
            __('Created By') => $asset->createdBy?->name,
            __('Created At') => $asset->created_at?->format('Y-m-d'),
        ] as $label => $value)
            <div class="flex items-center justify-between gap-3">
                <dt class="min-w-0 truncate text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="shrink-0 font-semibold text-gray-900 dark:text-gray-100">
                    {{ filled($value) ? $value : '—' }}
                </dd>
            </div>
        @endforeach
    </dl>

    @if ($asset->url)
        <a href="{{ $asset->url }}"
           target="_blank"
           rel="noopener"
           class="text-sm font-semibold"
           style="color: #0ea5e9;">
            {{ __('Open Original') }}
        </a>
    @endif
</div>
