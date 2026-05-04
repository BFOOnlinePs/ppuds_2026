# Laravel Excel Guide

هذا الملف يشرح طريقة بناء ملفات Excel في المشروع، ولماذا فصلنا الاستدعاء عن منطق بناء الملف، وكيف نختار بين `FromQuery` و `FromGenerator`.

الهدف ليس فقط أن يعمل التصدير، بل أن يكون الكود واضحاً، موحداً، وقابلاً للتوسع في كل الموديولات.

## الفكرة العامة

في المشروع عندنا ثلاث طبقات:

1. `ExcelServiceInterface`
   هو طريقة الاستدعاء الموحدة في كل المشروع.

2. `ExcelService`
   هو الغلاف العام فوق Laravel Excel. لا يعرف ما هي بيانات العملاء أو الاستبيانات. فقط يعرف كيف يعمل `download`, `store`, `queue`, `raw`, `import`.

3. `Export Class`
   هو الملف المسؤول عن شكل البيانات نفسها: الأعمدة، الصفوف، ترتيب البيانات، وتحويل القيم.

بهذا الشكل أي مكان في المشروع يستدعي Excel بنفس الطريقة، لكن كل ملف Export يبقى مسؤولاً عن بياناته فقط.

## الملفات الأساسية

الخدمة العامة موجودة هنا:

```text
Modules/Core/Interfaces/ExcelServiceInterface.php
Modules/Core/Services/ExcelService.php
Modules/Core/Exports/IterableExport.php
```

وتسجيل الخدمة موجود داخل:

```text
Modules/Core/Providers/CoreServiceProvider.php
```

أي موديول يريد تصدير ملف Excel لا يستدعي `Excel::download()` مباشرة، بل يستدعي:

```php
app(ExcelServiceInterface::class)->download(...)
```

هذا يجعل طريقة الاستدعاء موحدة في كل المشروع.

## لماذا لا نستخدم Facade مباشرة؟

يمكن استخدام:

```php
Excel::download(new CustomerInfoExport(), 'customers.xlsx');
```

لكن الأفضل في مشروعنا:

```php
app(ExcelServiceInterface::class)->download(
    new CustomerInfoExport(),
    'customers.xlsx',
    WriterType::XLSX
);
```

السبب:

- يسهل تغيير طريقة التصدير لاحقاً من مكان واحد.
- يسهل الاختبار والحقن.
- يمنع انتشار `Excel::download()` في كل الموديولات.
- يخلي كل الفريق يستخدم نفس الطريقة.

## القاعدة الذهبية

وحد طريقة الاستدعاء، وليس بالضرورة طريقة بناء كل Export.

يعني دائماً استدع Excel عبر `ExcelServiceInterface`.

لكن داخل Export class اختَر الأسلوب المناسب للبيانات:

- `FromQuery + WithMapping`: للجداول العادية من قاعدة البيانات.
- `FromGenerator`: للبيانات المركبة أو التي تحتاج بناء يدوي خاص.
- `downloadRows`: للتجارب السريعة أو ملفات صغيرة جداً.

## الطريقة الأولى: FromQuery + WithMapping

هذه هي الطريقة المفضلة للبيانات العادية من Eloquent، مثل العملاء، الشركات، الطلاب، المدفوعات، التقارير البسيطة.

مثال:

```php
<?php

namespace Modules\Customer\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Modules\Customer\Entities\Customer;

class CustomerInfoExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithCustomChunkSize
{
    public function query()
    {
        return Customer::query()
            ->with('user')
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            __('Name'),
            __('Email'),
            __('Phone'),
            __('Gender'),
            __('Status'),
        ];
    }

    public function map($customer): array
    {
        return [
            $customer->user?->name,
            $customer->user?->email,
            $customer->user?->phone,
            $customer->gender?->getLabel(),
            $customer->status?->getLabel(),
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
```

### لماذا هذه الطريقة جيدة؟

`FromQuery` يعطي Laravel Excel الاستعلام نفسه، والحزمة تتعامل معه بطريقة مناسبة للتصدير.

`WithMapping` يفصل شكل الصف عن الاستعلام. الاستعلام يجلب البيانات، و `map()` يحدد ما الذي يظهر في Excel.

`WithCustomChunkSize` يعطيك تحكم بحجم الدفعات حتى لا يتم تحميل كل البيانات مرة واحدة.

## استدعاء Export من زر Filament

مثال زر داخل Table action:

```php
use Filament\Tables\Actions\Action;
use Maatwebsite\Excel\Excel as WriterType;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\Customer\Exports\CustomerInfoExport;

Action::make('export_customers')
    ->label(__('Export Customers'))
    ->icon('heroicon-m-arrow-down-tray')
    ->color('success')
    ->action(fn () => app(ExcelServiceInterface::class)->download(
        new CustomerInfoExport(),
        'customers.xlsx',
        WriterType::XLSX
    ));
```

هذه هي طريقة الاستدعاء الموحدة التي نريدها في المشروع.

## الطريقة الثانية: FromGenerator

استخدم `FromGenerator` عندما تكون البيانات ليست مجرد جدول عادي.

أمثلة مناسبة:

- الاستبيانات: الأعمدة تتغير حسب الأسئلة.
- تقرير يجمع بيانات من أكثر من مصدر.
- صفوف تحتاج منطق خاص جداً.
- ملف فيه ترتيب غير مرتبط مباشرة باستعلام واحد.

مثال مبسط:

```php
<?php

namespace Modules\Customer\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Modules\Customer\Entities\Customer;

class CustomerInfoExport implements FromGenerator, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            __('Name'),
            __('Email'),
            __('Phone'),
            __('Gender'),
            __('Status'),
        ];
    }

    public function generator(): Generator
    {
        foreach (Customer::query()->with('user')->lazyById(500) as $customer) {
            yield [
                $customer->user?->name,
                $customer->user?->email,
                $customer->user?->phone,
                $customer->gender?->getLabel(),
                $customer->status?->getLabel(),
            ];
        }
    }
}
```

### ملاحظة مهمة عن cursor

تجنب هذا الشكل مع العلاقات:

```php
Customer::query()->with('user')->cursor();
```

لأن `cursor()` غالباً لا يستفيد من eager loading مثل ما تتوقع، وقد يسبب مشكلة `N+1 queries`.

إذا كنت تريد Generator مع علاقات، استخدم:

```php
lazyById(500)
```

أو استخدم `FromQuery + WithMapping` إذا كانت البيانات جدولية عادية.

## الفرق بين FromQuery و FromGenerator

استخدم `FromQuery` عندما:

- عندك Eloquent query واضح.
- كل صف في Excel يقابل record من قاعدة البيانات.
- تحتاج mapping بسيط للحقول.
- تريد eager loading للعلاقات مثل `with('user')`.

استخدم `FromGenerator` عندما:

- الصف لا يقابل record واحد بشكل مباشر.
- الأعمدة ديناميكية.
- تحتاج تجميع أو ترتيب خاص.
- تحتاج تبني الصفوف بنفسك باستخدام `yield`.

في مشروعنا:

- `CustomerInfoExport`: الأفضل `FromQuery + WithMapping`.
- `SurveySubmissionsExport`: الأفضل `FromGenerator` لأن الأعمدة هي أسئلة الاستبيان، وليست حقول ثابتة في جدول واحد.

## مثال الاستبيانات ولماذا استخدمنا Generator

ملف الاستبيانات موجود هنا:

```text
Modules/PPUDS/Exports/SurveySubmissionsExport.php
```

الفكرة:

- أول عمود: الشخص الذي سلم الاستبيان.
- باقي الأعمدة: أسئلة الاستبيان.
- كل صف: مستخدم قام بالتسليم.
- كل خلية: إجابة هذا المستخدم على السؤال.

هذه البيانات ديناميكية لأن كل استبيان له أسئلة مختلفة. لذلك `FromGenerator` مناسب أكثر.

المنطق داخل export يعمل تقريباً هكذا:

1. تحميل الاستبيان مع الأسئلة والخيارات.
2. بناء headings من الأسئلة.
3. جلب المستخدمين الذين سلموا.
4. جلب إجاباتهم.
5. بناء صف لكل مستخدم.
6. قراءة الإجابة حسب نوع السؤال.

مهم جداً: لا نعامل كل أنواع الأسئلة بنفس الطريقة.

- النص يأخذ `text_answer`.
- الراديو والقائمة يأخذان خياراً واحداً.
- checkbox و multi-select يجمعان عدة خيارات.
- الملف قد يحتوي أكثر من قيمة نصية.

هذا يمنع ظهور إجابات زائدة في Excel.

## downloadRows للتجربة السريعة

عندنا helper سريع داخل `ExcelService` اسمه:

```php
downloadRows()
```

استخدمه فقط للتجارب أو الملفات الصغيرة.

مثال:

```php
use Maatwebsite\Excel\Excel as WriterType;
use Modules\Core\Interfaces\ExcelServiceInterface;

$rows = [
    ['name' => 'Ahmad', 'email' => 'ahmad@example.com'],
    ['name' => 'Mona', 'email' => 'mona@example.com'],
];

return app(ExcelServiceInterface::class)->downloadRows(
    rows: $rows,
    filename: 'test.xlsx',
    headings: [__('Name'), __('Email')],
    writerType: WriterType::XLSX
);
```

لا تستخدم `downloadRows()` لتقارير كبيرة أو ملفات ستبقى في النظام. اعمل Export class واضح.

## كيف تضيف Export جديد؟

اتبع هذه الخطوات:

1. حدد الموديول.

مثلاً:

```text
Modules/Customer
```

2. أنشئ مجلد:

```text
Modules/Customer/Exports
```

3. أنشئ ملف:

```text
Modules/Customer/Exports/CustomerInfoExport.php
```

4. اختر نوع Export:

- `FromQuery + WithMapping` للبيانات العادية.
- `FromGenerator` للبيانات المركبة.

5. استدع الملف من زر أو Controller عبر:

```php
app(ExcelServiceInterface::class)->download(...)
```

## شكل أسماء الملفات

يفضل أن تكون أسماء الملفات واضحة:

```php
customers-2026-05-04-151000.xlsx
survey-submissions-training-evaluation-2026-05-04-151000.xlsx
payments-report-2026-05.xlsx
```

مثال:

```php
protected function exportFilename(): string
{
    return 'customers-'.now()->format('Y-m-d-His').'.xlsx';
}
```

لو الاسم مبني على عنوان:

```php
use Illuminate\Support\Str;

$slug = Str::slug((string) $survey->title);

return 'survey-submissions-'.($slug ?: $survey->id).'-'.now()->format('Y-m-d-His').'.xlsx';
```

## الصلاحيات

لا تجعل زر التصدير يظهر لكل شخص.

مثال:

```php
->visible(fn () => auth()->user()->can('Customer Export'))
```

أو مؤقتاً:

```php
->visible(fn () => auth()->user()->can('Customer View'))
```

الأفضل مستقبلاً إضافة صلاحيات واضحة:

```text
Customer Export
Survey Export
Payment Export
```

## مشاكل شائعة

### 1. N+1 Queries

المشكلة:

```php
Customer::query()->cursor();

$customer->user?->name;
```

هذا قد يعمل استعلام لكل user.

الحل:

```php
Customer::query()->with('user')
```

واستخدم `FromQuery` أو `lazyById()`.

### 2. ظهور إجابات زائدة في الاستبيانات

السبب غالباً أن التصدير يجمع كل answer records لنفس السؤال بدون فهم نوع السؤال.

الحل:

- السؤال المفرد يأخذ أول إجابة صحيحة.
- السؤال متعدد الاختيار يجمع الخيارات الصحيحة فقط.
- تأكد أن الخيار تابع لنفس السؤال.

### 3. تحميل بيانات كثيرة في الذاكرة

تجنب:

```php
Customer::query()->get()
```

مع ملفات كبيرة.

استخدم:

```php
FromQuery + WithCustomChunkSize
```

أو:

```php
lazyById(500)
```

### 4. تكرار استدعاء Excel في كل مكان

تجنب:

```php
Excel::download(...)
```

في كل موديول.

استخدم:

```php
app(ExcelServiceInterface::class)->download(...)
```

## متى أستخدم Queue؟

إذا الملف قد يحتوي آلاف كثيرة من الصفوف، الأفضل استخدام queue.

السيناريوهات المناسبة:

- تصدير ضخم.
- تقرير يحتاج وقت.
- ملف فيه علاقات كثيرة.
- المستخدم لا يحتاج التحميل فوراً.

حالياً عندنا `ExcelService` يدعم:

```php
queue()
```

لكن تحتاج تضيف تجربة مستخدم مناسبة:

- زر يبدأ التصدير.
- job يحفظ الملف.
- notification للمستخدم عند الانتهاء.
- رابط تحميل.

لذلك لا تستخدم queue عشوائياً بدون تصميم تجربة التحميل.

## Checklist قبل اعتماد أي Export

قبل ما تعتمد ملف Excel جديد، راجع التالي:

- هل استدعيت Excel عبر `ExcelServiceInterface`؟
- هل Export class داخل الموديول الصحيح؟
- هل اخترت `FromQuery` للبيانات العادية؟
- هل استخدمت `FromGenerator` فقط عندما تحتاج بناء خاص؟
- هل أضفت headings واضحة؟
- هل استخدمت eager loading للعلاقات؟
- هل تجنبت `get()` مع بيانات كبيرة؟
- هل اسم الملف واضح؟
- هل هناك صلاحية للزر؟
- هل شغلت `php -l` و `pint`؟
- هل جربت الملف على بيانات حقيقية؟

## القالب المفضل لملفات Eloquent العادية

استخدم هذا القالب غالباً:

```php
<?php

namespace Modules\SomeModule\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SomeModelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithCustomChunkSize
{
    public function query()
    {
        return SomeModel::query()
            ->with(['relation'])
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            __('Column 1'),
            __('Column 2'),
        ];
    }

    public function map($record): array
    {
        return [
            $record->field,
            $record->relation?->name,
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
```

## القالب المفضل للبيانات المركبة

استخدم هذا القالب عندما تحتاج تحكم كامل بالصفوف:

```php
<?php

namespace Modules\SomeModule\Exports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ComplexReportExport implements FromGenerator, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            __('Column 1'),
            __('Column 2'),
        ];
    }

    public function generator(): Generator
    {
        foreach ($this->records() as $record) {
            yield [
                $record['value_1'],
                $record['value_2'],
            ];
        }
    }
}
```

## الخلاصة

طريقة المشروع المعتمدة:

```text
Button / Controller / Livewire
        |
        v
ExcelServiceInterface
        |
        v
Export Class
        |
        v
Laravel Excel
```

لا تضع منطق بناء Excel داخل الزر نفسه إلا للتجارب الصغيرة.

خلي الزر فقط يستدعي الخدمة.

خلي Export class يبني الملف.

خلي `ExcelService` هو بوابة Excel الموحدة في كل المشروع.
