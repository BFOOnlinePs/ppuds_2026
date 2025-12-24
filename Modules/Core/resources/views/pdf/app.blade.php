@php
    $settings = \Modules\Core\Entities\Settings::first();
    $generalSettings = app(\Modules\Core\Settings\GeneralSettings::class);
@endphp
    <!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        /* ربط الهيدر والفوتر كمصادر mPDF */
        @page {
            header: page-header;
            footer: page-footer;
            /* حجز المساحة العلوية حسب ارتفاع الهيدر (قيمة تجريبية - عدّلها) */
            margin-top: 40mm;
            margin-bottom: 12mm;
            margin-left: 10mm;
            margin-right: 10mm;
        }

        /* ستايل الهيدر المعروض داخل المنطقة المحجوزة */
        .pdf-header {
            width:100%;
            box-sizing:border-box;
            border-bottom:1px solid #e0e0e0;
            padding-bottom:8px;
            text-align: center;
            direction: rtl;
        }
        .pdf-header .logo {
            display:inline-block;
            vertical-align: middle;
        }
        .pdf-header .logo img {
            width:60px;
            height:60px;
        }
        .pdf-header .company {
            display:inline-block;
            vertical-align: middle;
            text-align: center;
        }
        .pdf-header .company .name {
            font-size:18px;
            font-weight:700;
            margin:0;
        }
        .pdf-header .company .tag {
            font-size:11px;
            color:#666;
            margin:0;
        }

        /* جسم الصفحة */
        body {
            font-family: 'tajawal', sans-serif;
            direction: rtl;
            text-align: right;
            margin: 0;
            font-size: 12px;
        }

        /* جدول مثال */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 6mm;
            direction: rtl;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 12px;
            text-align: right;
        }

        /* الفوتر */
        .pdf-footer {
            font-size:11px;
            text-align:center;
            direction: rtl;
        }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

{{-- تعريف الهيدر والفوتر (مقروءان من @page header/footer) --}}
<htmlpageheader name="page-header">
    <div class="pdf-header">
        <div class="logo">
            <img style="width:60px; height:60px;" src="{{ $settings->getLogoUrl() }}" alt="logo">
        </div>
        <div class="company">
            <p class="name">{{ $generalSettings->site_name }}</p>
            <p class="tag">تقارير نظام الإدارة</p>
        </div>
    </div>
</htmlpageheader>

<htmlpagefooter name="page-footer">
    <div class="pdf-footer">
        <span>هاتف: 0569162687 &nbsp; | &nbsp; بريد: info@example.com</span>
        <br/>
        <span>صفحة {PAGENO} من {nb}</span>
    </div>
</htmlpagefooter>

{{-- تأكيد تفعيل الهيدر والفوتر (اختياري) --}}
<!--mpdf
        <sethtmlpageheader name="page-header" value="on" show-this-page="1" />
        <sethtmlpagefooter name="page-footer" value="on" />
    mpdf-->

{{-- البدء بمحتوى التقرير --}}
<div class="content">
    @yield('content')
</div>
</body>
</html>
