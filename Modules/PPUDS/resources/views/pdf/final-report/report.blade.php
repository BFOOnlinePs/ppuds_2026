{{--
    النموذج الرسمي للتقرير النهائي للتدريب الميداني. الحقول التي لا يخزّنها
    النظام محذوفة من النموذج بالكامل، فلا تُطبع خانات لا يملؤها شيء.
--}}
<!doctype html>
<html lang="ar">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 20mm 18mm;
        }

        body {
            font-family: 'tajawal', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 12px;
            margin: 0;
        }

        .cover {
            text-align: center;
        }

        .cover p {
            margin: 0 0 14px;
        }

        .cover .spacer {
            margin-bottom: 34px;
        }

        .cover .title {
            font-size: 15px;
            font-weight: 700;
        }

        .page-break {
            page-break-before: always;
        }

        .bullet {
            font-weight: 700;
            margin: 0 0 8px;
        }

        .note {
            margin: 0 0 14px;
            text-align: justify;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            direction: rtl;
            margin-bottom: 16px;
        }

        th, td {
            border: 1px solid #000;
            padding: 5px 7px;
            font-size: 12px;
            text-align: right;
            vertical-align: top;
        }

        th {
            font-weight: 700;
        }

        .form-table .label {
            width: 34%;
            font-weight: 700;
        }

        .form-table .value {
            width: 66%;
        }

        .data-table th {
            text-align: center;
        }

        .data-table td {
            text-align: center;
        }

        .data-table td.text {
            text-align: right;
        }

        .row-index {
            width: 8%;
        }

        .numbered {
            margin: 0 0 14px;
            padding: 0;
        }

        .numbered li {
            margin-bottom: 6px;
        }

        .signature {
            margin-top: 6mm;
            font-weight: 700;
        }

        .signature td {
            border: none;
            padding: 0;
        }
    </style>
</head>
<body>

{{-- ============================ الغلاف ============================ --}}
<div class="cover">
    <p>بسم الله الرحمن الرحيم</p>
    <p>جامعة بوليتكنك فلسطين</p>
    <p class="spacer">كلية الدراسات الثنائية</p>

    <p class="title{{ filled($registration?->course?->name) ? '' : ' spacer' }}">التقرير النهائي للتدريب الميداني</p>

    @if (filled($registration?->course?->name))
        <p class="spacer">اسم المرحلة التدريبية ( {{ $registration->course->name }} )</p>
    @endif

    <p class="title">إعداد الطالب/ة:</p>
    <p>اسم الطالب الرباعي: {{ $student?->name }}</p>
    <p>الرقم الجامعي: {{ $student?->studentProfile?->student_number }}</p>
    <p class="spacer">التخصص: {{ $student?->studentProfile?->major?->name }}</p>

    <p class="title">جهة التدريب:</p>
    <p class="spacer">{{ $company?->name }}</p>

    <p class="title">فترة التدريب:</p>
    <p class="spacer">{{ $trainingPeriod }}</p>

    <p class="title">العام الدراسي</p>
    <p>{{ $academicYear }}</p>
</div>

{{-- ====================== المعلومات الأساسية ====================== --}}
<div class="page-break"></div>

<p class="bullet">- المعلومات الأساسية</p>

<table class="form-table">
    <tr>
        <th colspan="2">معلومات جهة التدريب</th>
    </tr>
    <tr>
        <td class="label">اسم جهة التدريب (عربي)</td>
        <td class="value">{{ $companyNameAr }}</td>
    </tr>
    <tr>
        <td class="label">اسم جهة التدريب (انجليزي)</td>
        <td class="value">{{ $companyNameEn }}</td>
    </tr>
    <tr>
        <td class="label">مجال العمل</td>
        <td class="value">{{ $company?->category?->name }}</td>
    </tr>
    <tr>
        <td class="label">اسم مسؤول جهة التدريب</td>
        <td class="value">{{ $company?->contact_person }}</td>
    </tr>
    <tr>
        <td class="label">عنوان جهة التدريب</td>
        <td class="value">{{ $branch?->address }}</td>
    </tr>
    <tr>
        <td class="label">الموقع الإلكتروني</td>
        <td class="value">{{ $company?->website }}</td>
    </tr>
    <tr>
        <td class="label">البريد الإلكتروني</td>
        <td class="value">{{ $branch?->email }}</td>
    </tr>
    <tr>
        <td class="label">رقم التواصل</td>
        <td class="value">{{ $company?->contact_info ?: $branch?->phone }}</td>
    </tr>
</table>

<table class="form-table">
    <tr>
        <th colspan="2">معلومات مشرف التدريب (شركة)</th>
    </tr>
    <tr>
        <td class="label">اسم مشرف التدريب</td>
        <td class="value">{{ $branch?->manager_name }}</td>
    </tr>
    <tr>
        <td class="label">رقم الاتصال</td>
        <td class="value">{{ $branch?->manager_phone }}</td>
    </tr>
    <tr>
        <td class="label">البريد الإلكتروني</td>
        <td class="value">{{ $branch?->email }}</td>
    </tr>
</table>

<table class="form-table">
    <tr>
        <th colspan="2">إحصائية التدريب للطالب</th>
    </tr>
    <tr>
        <td class="label">عدد أيام التدريب</td>
        <td class="value">{{ $stats['days'] }}</td>
    </tr>
    <tr>
        <td class="label">عدد ساعات التدريب</td>
        <td class="value">{{ $stats['hours'] }}</td>
    </tr>
    <tr>
        <td class="label">عدد أيام الإجازات</td>
        <td class="value">{{ $stats['leaves'] }}</td>
    </tr>
</table>

<table class="signature">
    <tr>
        <td style="width: 55%">توقيع مشرف جهة التدريب: {{ str_repeat('.', 20) }}</td>
        <td style="width: 45%">ختم جهة التدريب: {{ str_repeat('.', 24) }}</td>
    </tr>
</table>

{{-- ======================= تفاصيل التدريب ======================= --}}
<div class="page-break"></div>

@if (filled(strip_tags((string) $report->role_description)))
    <p class="bullet">- مهام التدريب</p>

    <div class="note">{!! str($report->role_description)->sanitizeHtml() !!}</div>
@endif

@if ($report->tasks->isNotEmpty())
    <p class="bullet">- المهام التدريبية:</p>

    <table class="data-table">
        <thead>
            <tr>
                <th class="row-index">الرقم</th>
                <th style="width: 22%">المهمة التدريبية</th>
                <th style="width: 33%">تفاصيل المهمة</th>
                <th style="width: 20%">مدة العمل فيها بالأيام والساعات</th>
                <th style="width: 17%">ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report->tasks as $task)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="text">{{ $task->task_name }}</td>
                    <td class="text">{{ $task->task_details }}</td>
                    <td class="text">{{ $task->work_duration }}</td>
                    <td class="text">{{ $task->notes }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($report->skills->isNotEmpty())
    <p class="bullet">- المهارات المكتسبة من التدريب:</p>

    <table class="data-table">
        <thead>
            <tr>
                <th class="row-index">الرقم</th>
                <th style="width: 47%">المهارة</th>
                <th style="width: 20%">نسبة اتقانها %</th>
                <th style="width: 25%">ملاحظات</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report->skills as $skill)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="text">{{ $skill->skill }}</td>
                    <td>{{ $skill->mastery_percentage !== null ? $skill->mastery_percentage.'%' : '' }}</td>
                    <td class="text">{{ $skill->notes }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($contributions->isNotEmpty())
    <p class="bullet">- أهم المساهمات:</p>

    <ol class="numbered">
        @foreach ($contributions as $item)
            <li>{{ $item->content }}</li>
        @endforeach
    </ol>
@endif

@if ($difficulties->isNotEmpty())
    <p class="bullet">- صعوبات التدريب:</p>

    <ol class="numbered">
        @foreach ($difficulties as $item)
            <li>{{ $item->content }}</li>
        @endforeach
    </ol>
@endif

@if (filled(strip_tags((string) $report->summary)))
    <p class="bullet">- الملخص:</p>

    <div class="note">{!! str($report->summary)->sanitizeHtml() !!}</div>
@endif

</body>
</html>
