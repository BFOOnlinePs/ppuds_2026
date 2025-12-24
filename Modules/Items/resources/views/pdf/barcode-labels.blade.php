<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            direction: rtl;
        }

        @page {
            size: 8.5cm 3cm landscape;
            margin: 0;
        }

        body {
            width: 100%;
            height: 100%;
        }

        .barcode-container {
            width: 100%;
            height: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .product-name {
            font-size: 14px;
            font-weight: bold;
            color: #000;
            max-width: 90%;
            word-wrap: break-word;
        }

        .barcode-img {
            width: 100%;
            height: 50%;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>
<body>
@foreach ($barcodes as $item)
    {{-- Repeat the label based on the quantity --}}
    @for ($i = 0; $i < $item['quantity']; $i++)
        <div class="barcode-container">
            <div class="barcode-img">
                {{--  ✅ استخدم الباركود الجاهز من الـ Service مباشرة  --}}
                {!! $item['barcode_html'] !!}
            </div>

            @if (isset($item['product']))
                <div class="product-name">
                    {{ $item['product']->name }}
                </div>
            @endif
        </div>

        {{-- Add a page break after each label, EXCEPT the very last one --}}
        @if (!($loop->last && $i === $item['quantity'] - 1))
            <div style="page-break-after: always;"></div>
        @endif
    @endfor
@endforeach
</body>
</html>
