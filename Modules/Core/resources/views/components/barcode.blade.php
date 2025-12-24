@php
    $pdfService = app(Modules\Core\Services\PdfService::class);
@endphp
<div class="flex flex-col gap-2">
    <div class="text-sm font-medium">
        {{ $product->name }}
    </div>

    <div>
        {!! $pdfService->generateBarcode($product->barcode) !!}
    </div>
</div>
