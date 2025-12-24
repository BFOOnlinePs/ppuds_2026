@extends('core::pdf.app')

@section('content')

    <style>
        /* ======= قواعد عامة ======= */
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'tajawal', sans-serif; direction: rtl; }

        .content-wrapper { direction: rtl; margin: 0; padding: 4mm 0; }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 6px 0;
            color: #000;
        }
        .section-sub {
            font-size: 11px;
            color: #666;
            margin-bottom: 8px;
        }

        /* أرقام لاتينية داخل الجداول */
        .ltr-num {
            direction: ltr;
            display: inline-block;
            font-family: sans-serif;
            font-weight: bold;
        }

        .page-break { page-break-before: always; }

        /* ======= جداول المعلومات والقراءات ======= */
        table { border-collapse: collapse; table-layout: fixed; width: 100%; word-wrap: break-word; }

        .info-table {
            width: 100%;
            margin-top: 4mm;
            background: #fff;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 8px 10px;
            vertical-align: middle;
            border: 1px solid #e6e6e6;
            font-size: 13px;
            overflow-wrap: break-word;
        }
        .info-table .label {
            width: 25%;
            background: #f7f7f7;
            color: #333;
            font-weight: 700;
            text-align: right;
        }
        .info-table .value {
            width: 25%;
            text-align: right;
            color: #111;
            font-weight: 600;
        }

        .readings-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        .readings-table th, .readings-table td {
            border: 1px solid #999;
            padding: 8px 5px;
            word-wrap: break-word;
        }
        .readings-table thead th {
            background-color: #e6e6e6;
            font-weight: bold;
        }
        .readings-table .col-desc {
            background-color: #f4f4f4;
            font-weight: bold;
            width: 15%;
        }

        /* ======= جدول اليوم (الجدول الكبير) ======= */
        .program-section { width: 100%; direction: rtl; }

        .day-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* مهم */
            margin-bottom: 0;
            border: 1px solid #444;
            word-wrap: break-word;
        }

        .day-header th {
            background-color: #333;
            color: #fff;
            padding: 10px;
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            border: 1px solid #333;
        }

        .meal-type-cell {
            width: 20%;
            min-width: 80px;
            background-color: #f2f2f2;
            color: #333;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #999;
            font-size: 13px;
            padding: 5px;
            overflow-wrap: break-word;
            white-space: normal;
        }

        .meal-content-cell {
            width: 80%;
            background-color: #fff;
            vertical-align: top;
            border: 1px solid #999;
            padding: 6px;
            word-wrap: break-word;
            min-width: 150px;
        }

        /* ======= جدول الأصناف الداخلي ======= */
        .items-inner-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* مهم أيضاً */
        }
        .items-inner-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
            font-size: 12px;
            vertical-align: middle;
            overflow-wrap: break-word;
        }
        .items-inner-table tr:last-child td { border-bottom: none; }

        .item-name {
            text-align: right;
            color: #000;
            font-weight: 500;
            width: 150px;
        }
        .item-desc {
            display: block;
            color: #777;
            font-size: 10px;
            margin-top: 3px;
            font-weight: normal;
            overflow-wrap: break-word;
        }
        .item-description {
            text-align: left;
            color: #0044cc;
            font-weight: bold;
            width: 150px;
            white-space: nowrap;
        }
        .item-qty {
            font-weight: bold;
            width: 30px;
            white-space: nowrap;
        }

        /* تلميحات عامة لتفادي كسر التخطيط */
        img { max-width: 100%; height: auto; display: block; }
        .nowrap { white-space: nowrap; }
    </style>

    <div class="content-wrapper">
        {{-- القسم الأول: بيانات العميل --}}
        <div>
            <h3 class="section-title">تفاصيل برنامج العميل: {{ $customerProgram->customer->name }}</h3>
            <div class="section-sub">معلومات أساسية للعميل</div>
        </div>

        <table class="info-table" role="presentation">
            <tr>
                <td class="label">الاسم</td>
                <td class="value">{{ $customerProgram->customer->name }}</td>
                <td class="label">رقم الهاتف</td>
                <td class="value"><span class="ltr-num">{{ $customerProgram->customer->phone ?? '-' }}</span></td>
            </tr>
            <tr>
                <td class="label">العمر</td>
                <td class="value">{{ \Carbon\Carbon::parse($customerProgram->customer->date_of_birth)->age }}</td>
                <td class="label">المدينة</td>
                <td class="value">{{ $customerProgram->customer->city_name }}</td>
            </tr>
            <tr>
                <td class="label">البريد الإلكتروني</td>
                <td class="value">{{ $customerProgram->customer->email }}</td>
                <td class="label">اسم البرنامج</td>
                <td class="value">{{ $customerProgram->program->name }}</td>
            </tr>
            <tr>
                <td class="label">تاريخ التسجيل</td>
                <td class="value"><span class="ltr-num">{{ $customerProgram->customer->created_at->format('Y-m-d') }}</span></td>
                <td class="label">اسم الاخصائية</td>
                <td class="value">{{ auth()->user()->name ?? '-' }}</td>
            </tr>
        </table>

        {{-- القسم الثاني: جدول القراءات --}}
        @if($currentReading)
            <div style="margin-top: 8mm;">
                <h3 class="section-title">ملخص القراءات والتقدم</h3>

                <table class="readings-table" role="presentation">
                    <thead>
                    <tr>
                        <th>الوصف</th>
                        <th>الزيارة الحالية<br><span style="font-size:10px; font-weight:normal" class="ltr-num">{{ $currentReading->created_at->format('Y-m-d') }}</span></th>
                        <th>الزيارة السابقة<br><span style="font-size:10px; font-weight:normal" class="ltr-num">{{ $previousReading ? $previousReading->created_at->format('Y-m-d') : '-' }}</span></th>
                        <th>الزيارة الأولى<br><span style="font-size:10px; font-weight:normal" class="ltr-num">{{ $firstReading ? $firstReading->created_at->format('Y-m-d') : '-' }}</span></th>
                        <th>التقدم عن<br>آخر زيارة</th>
                        <th>التقدم<br>التراكمي</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($metrics as $key => $label)
                        @php
                            $currVal = $currentReading->{$key} ?? 0;
                            $prevVal = $previousReading->{$key} ?? 0;
                            $firstVal = $firstReading->{$key} ?? 0;

                            $diffLast = $previousReading ? ($currVal - $prevVal) : 0;
                            $diffTotal = $firstReading ? ($currVal - $firstVal) : 0;
                        @endphp
                        <tr>
                            <td class="col-desc">{{ $label }}</td>
                            <td class="ltr-num">{{ number_format($currVal, 1) }}</td>
                            <td class="ltr-num">{{ $previousReading ? number_format($prevVal, 1) : '-' }}</td>
                            <td class="ltr-num">{{ $firstReading ? number_format($firstVal, 1) : '-' }}</td>

                            <td class="ltr-num" style="color: {{ $diffLast > 0 ? '#d9534f' : ($diffLast < 0 ? '#5cb85c' : '#000') }}">
                                {{ number_format($diffLast, 2) }}
                            </td>

                            <td class="ltr-num" style="color: {{ $diffTotal > 0 ? '#d9534f' : ($diffTotal < 0 ? '#5cb85c' : '#000') }}">
                                {{ number_format($diffTotal, 2) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="margin-top: 20px; text-align: center; color: #777;">
                لا توجد قراءات مسجلة لهذا العميل حتى الآن.
            </div>
        @endif
    </div>

    {{-- تعليمات البرنامج صفحة جديدة --}}
    <div class="page-break"></div>

    <div style="margin-bottom: 20px;">
        <h3 class="section-title">تعليمات البرنامج</h3>
        <div style="font-size: 13px; line-height: 1.6;">
            {!! $customerProgram->program->instruction->description !!}
        </div>
    </div>

    {{-- الجدول الغذائي --}}
    <div class="page-break"></div>

    <div class="program-section">
        <h3 class="section-title">الجدول الغذائي الأسبوعي</h3>
        <div class="section-sub">تفاصيل الوجبات والكميات لكل يوم</div>
        <br>

        @if($customerProgram->days && $customerProgram->days->count() > 0)

            @foreach($customerProgram->days as $index => $day)

                @if($index > 0)
                    <div class="page-break"></div>
                @endif

                {{-- جدول اليوم مع colgroup لتثبيت العرض --}}
                <table class="day-table" role="presentation">
                    <colgroup>
                        <col style="width:20%">
                        <col style="width:80%">
                    </colgroup>
                    <thead>
                    <tr class="day-header">
                        <th colspan="2">اليوم {{ $day->day_number }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if($day->dayMeals && $day->dayMeals->count() > 0)
                        @foreach($day->dayMeals as $meal)
                            <tr>
                                <td class="meal-type-cell">
                                    {{ $meal->mealType->name ?? 'وجبة' }}
                                </td>

                                <td class="meal-content-cell">
                                    @if($meal->mealItems && $meal->mealItems->count() > 0)
                                        <table class="items-inner-table" role="presentation">
                                            <colgroup>
                                                <col style="width:75%">
                                                <col style="width:25%">
                                            </colgroup>
                                            @foreach($meal->mealItems as $item)
                                                <tr>
                                                    <td class="item-name">
                                                        {{ $item->foodItem->name ?? '-' }}
                                                        @if(!empty($item->description))
                                                            <span class="item-desc">{{ $item->description }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        {{ $item->quantity }}
                                                    </td>
                                                    <td class="item-description">
                                                        <span class="">
                                                            {{ $item->servingSize->name ?? '' }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    @else
                                        <div style="padding: 10px; color: #999; text-align: center; font-size: 11px;">
                                            لا توجد أصناف
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="2" style="padding: 20px; text-align: center; color: #777;">
                                لا توجد وجبات مسجلة لهذا اليوم
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            @endforeach

        @else
            <div style="text-align: center; padding: 30px; border: 1px dashed #ccc; background: #f9f9f9; margin-top: 10px;">
                لا يوجد جدول غذائي مضاف حالياً.
            </div>
        @endif
    </div>

@endsection
