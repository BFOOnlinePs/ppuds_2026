{{-- Full size preview of one library file, shown inside the table's view modal. --}}
<div class="space-y-6 p-4">

    @if ($isImage)
        {{-- Bounded by the viewport, never by a conversion: this is the original file. --}}
        <img src="{{ $media->getUrl() }}"
             alt="{{ $media->getCustomProperty('alt_text') }}"
             class="w-full rounded-lg"
             style="max-height: 70vh; object-fit: contain;">
    @else
        <div class="rounded-lg border border-gray-200 p-4 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            {{ $media->file_name }}
        </div>
    @endif

    <dl class="space-y-2 text-sm">
        @foreach ([
            __('File Name') => $media->file_name,
            __('Alt Text') => $media->getCustomProperty('alt_text'),
            __('Dimensions') => $dimensions,
            __('Size') => \Illuminate\Support\Number::fileSize((int) $media->size, precision: 1),
            __('File Type') => $media->mime_type,
            __('Source') => $source,
            __('Created At') => $media->created_at?->format('Y-m-d'),
        ] as $label => $value)
            <div class="flex items-center justify-between gap-3">
                <dt class="min-w-0 truncate text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="shrink-0 font-semibold text-gray-900 dark:text-gray-100">
                    {{ filled($value) ? $value : '—' }}
                </dd>
            </div>
        @endforeach
    </dl>

    <a href="{{ $media->getUrl() }}"
       target="_blank"
       rel="noopener"
       class="text-sm font-semibold"
       style="color: #0ea5e9;">
        {{ __('Open Original') }}
    </a>
</div>
