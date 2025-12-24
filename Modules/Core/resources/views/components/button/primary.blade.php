<div class="mt-4">

    @if ($attributes->has('href'))
        <a {{ $attributes->merge([
            'class' => 'inline-flex items-center justify-center gap-2 rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:bg-primary-dark dark:hover:bg-primary'
        ]) }}>
            {{ $slot }}
        </a>
    @else
        <button {{ $attributes->merge([
            'class' => 'inline-flex items-center justify-center gap-2 rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:bg-primary-light focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:bg-primary-dark dark:hover:bg-primary']) }}>
            {{ $slot }}
        </button>
    @endif
</div>
