# Laravel AI Student Company Assistant

هذا الملف يشرح كيف تم بناء مساعد الذكاء الاصطناعي في الصفحة الرئيسية لاقتراح الشركات وربطها بالطالب، ولماذا تم تقسيم الكود بهذه الطريقة.

الهدف من المساعد:

- يكتب المستخدم اسم الطالب أو الرقم الجامعي.
- النظام يحدد الطالب وسجل التسجيل المناسب.
- Laravel AI يقترح شركات مناسبة من الشركات الموجودة في النظام فقط.
- المستخدم يستطيع ربط شركة واحدة أو كل الاقتراحات بالطالب.
- بعد اختيار الطالب، يستطيع المستخدم كتابة أمر مثل: `اربطه مع بلدية الخليل` فيقوم النظام بالبحث عن الشركة وربطها.

## الحزمة المستخدمة

تم استخدام حزمة Laravel AI:

```bash
composer require laravel/ai
```

الحزمة أضافت إمكانية بناء `Agent` داخل Laravel واستدعائه عبر:

```php
StudentCompanySuggestionAgent::make()->prompt(...)
```

نحن لم نستخدم تخزين محادثات Laravel AI في قاعدة البيانات، لذلك لا نحتاج migration الخاص بجداول:

```text
agent_conversations
agent_conversation_messages
```

المساعد الحالي يحتفظ بحالة المحادثة داخل Livewire فقط أثناء استخدام الصفحة.

## الإعدادات

إعدادات Laravel AI موجودة في:

```text
config/ai.php
```

وأضفنا إعدادًا خاصًا بمساعد الشركات:

```php
'student_company_suggestions' => [
    'provider' => env('AI_STUDENT_COMPANY_PROVIDER'),
    'model' => env('AI_STUDENT_COMPANY_MODEL'),
    'timeout' => (int) env('AI_STUDENT_COMPANY_TIMEOUT', 60),
],
```

يمكن تشغيله مثلًا عبر OpenAI:

```env
OPENAI_API_KEY=...
AI_STUDENT_COMPANY_PROVIDER=openai
AI_STUDENT_COMPANY_MODEL=gpt-4o-mini
AI_STUDENT_COMPANY_TIMEOUT=60
```

إذا لم يتم ضبط API key، لا يتوقف المساعد. يرجع إلى اقتراحات محلية مبنية على بيانات الشركات والطالب.

## الملفات الأساسية

الواجهة Livewire:

```text
Modules/Core/Livewire/Pages/Home/StudentCompanyAssistant.php
Modules/Core/resources/views/livewire/pages/home/student-company-assistant.blade.php
```

ربط المساعد بالصفحة الرئيسية:

```text
Modules/Core/resources/views/livewire/pages/home/index.blade.php
```

الـ Agent الخاص بـ Laravel AI:

```text
Modules/Core/Ai/StudentCompanySuggestionAgent.php
```

خدمة تجهيز بيانات الاقتراحات واستدعاء الـ Agent:

```text
Modules/Core/Services/StudentCompanySuggestionService.php
```

الـ Actions المسؤولة عن العمليات:

```text
Modules/Core/Actions/StudentCompanyAssistant/FindStudentsForCompanyAssistant.php
Modules/Core/Actions/StudentCompanyAssistant/FindCompaniesForCompanyAssistant.php
Modules/Core/Actions/StudentCompanyAssistant/ResolveStudentCompanyRegistration.php
Modules/Core/Actions/StudentCompanyAssistant/ResolveCompanyPlacement.php
Modules/Core/Actions/StudentCompanyAssistant/LinkSuggestedCompanyToStudent.php
```

## لماذا هذا التقسيم؟

حتى يكون الكود أقرب إلى Laravel way:

- Livewire Component مسؤول عن حالة الشاشة والرسائل فقط.
- Actions مسؤولة عن عمليات واضحة وصغيرة.
- Service مسؤولة عن تنسيق بيانات الاقتراحات والفشل الآمن.
- Agent مسؤول فقط عن تعليمات الذكاء الاصطناعي و structured output schema.

هذا يجعل الكود أسهل في الاختبار، وأسهل في التعديل، ولا يجعل Livewire يحتوي منطق business كبير.

## كيف يظهر المساعد؟

في الصفحة الرئيسية:

```php
@can('StudentCompany Create')
    @livewire(\Modules\Core\Livewire\Pages\Home\StudentCompanyAssistant::class)
@endcan
```

يعني المساعد يظهر فقط للمستخدم الذي يملك صلاحية:

```text
StudentCompany Create
```

وهذا مهم لأن المساعد يستطيع إنشاء ربط بين طالب وشركة.

## تدفق العمل

### 1. المستخدم يكتب اسم الطالب

في `StudentCompanyAssistant::send()` يتم التحقق من الصلاحية:

```php
$this->authorize('StudentCompany Create');
```

ثم يتم البحث عن الطالب عبر:

```php
FindStudentsForCompanyAssistant
```

البحث يتم حسب:

- الاسم العربي
- الاسم الإنجليزي
- البريد
- الرقم الجامعي داخل `studentProfile`

إذا وجد أكثر من طالب، يعرض نتائج لاختيار الطالب الصحيح.

### 2. اختيار سجل التسجيل المناسب

بعد تحديد الطالب، يتم اختيار تسجيله عبر:

```php
ResolveStudentCompanyRegistration
```

الأولوية تكون لسجل الفصل والسنة الحاليين من إعدادات PPUDS.

إذا لم يوجد تسجيل للفصل الحالي، يستخدم آخر تسجيل متاح للطالب، ويعرض رسالة تنبيه للمستخدم.

### 3. تجهيز بيانات الشركات المرشحة

الخدمة:

```php
StudentCompanySuggestionService
```

تجلب الشركات النشطة أولًا:

```php
CompanyStatus::ACTIVE
```

وتحمّل العلاقات المطلوبة:

```text
category
branches
departments
currentStudentCompanies count
```

ثم تحوّل كل شركة إلى payload بسيط يرسل للـ AI، مثل:

```php
[
    'company_id' => 10,
    'company_name' => 'بلدية الخليل',
    'category' => '...',
    'description' => '...',
    'current_students_count' => 2,
    'branches' => [...],
]
```

الفكرة المهمة: الذكاء الاصطناعي لا يخترع شركات من عنده. هو يختار فقط من قائمة الشركات الموجودة في النظام.

## Laravel AI Agent

الـ Agent موجود في:

```text
Modules/Core/Ai/StudentCompanySuggestionAgent.php
```

الكلاس يطبق:

```php
Agent
Conversational
HasStructuredOutput
HasTools
```

ويستخدم:

```php
use Promptable;
```

### التعليمات

داخل `instructions()` وضعنا قواعد واضحة:

- اختر من قائمة الشركات المحددة فقط.
- لا تغيّر معرفات الشركات.
- فضّل الشركة المناسبة لتخصص الطالب ومقرره.
- وازن بين الملاءمة وعدد الطلاب الحاليين في الشركة.
- اكتب السبب بالعربية.

### Structured Output

الـ Agent لا يرجع نصًا حرًا فقط، بل يرجع JSON منظمًا حسب schema:

```php
[
    'summary' => $schema->string()->required(),
    'suggestions' => $schema->array()
        ->min(1)
        ->max($this->limit)
        ->items($schema->object([
            'company_id' => $schema->integer()->required(),
            'branch_id' => $schema->integer()->nullable()->required(),
            'department_id' => $schema->integer()->nullable()->required(),
            'reason' => $schema->string()->max(280)->required(),
            'fit_score' => $schema->integer()->min(1)->max(100)->required(),
        ])->required())
        ->required(),
]
```

هذا أفضل من تحليل نص حر، لأن النتيجة تصبح قابلة للاستخدام مباشرة في الكود.

## استدعاء Laravel AI

الاستدعاء موجود داخل:

```php
StudentCompanySuggestionService::aiSuggestions()
```

ويتم بهذا الشكل:

```php
$response = StudentCompanySuggestionAgent::make(limit: $limit)->prompt(
    $this->prompt($student, $registration, $candidates, $limit),
    provider: $this->configuredProvider(),
    model: $this->configuredModel(),
    timeout: $this->configuredTimeout(),
);
```

ثم يتم تحويل الرد إلى array:

```php
return method_exists($response, 'toArray') ? $response->toArray() : [];
```

## الفشل الآمن fallback

إذا لم يكن هناك API key، أو فشل الاتصال بالـ provider، لا يتعطل النظام.

الخدمة ترجع اقتراحات محلية عبر:

```php
fallbackSuggestions()
```

هذه الاقتراحات تعتمد على:

- كلمات من تخصص الطالب والمقرر.
- اسم الشركة وتصنيفها ووصفها.
- عدد الطلاب الحاليين في الشركة.

هذا يجعل المساعد مفيدًا حتى قبل ضبط الذكاء الاصطناعي.

## ربط الشركة بالطالب

الربط يتم عبر:

```text
Modules/Core/Actions/StudentCompanyAssistant/LinkSuggestedCompanyToStudent.php
```

هذا الـ Action:

1. يحدد الفرع والقسم عبر `ResolveCompanyPlacement`.
2. يبحث هل يوجد ربط سابق لنفس `registration_id` و `company_id`.
3. إذا كان موجودًا ومحذوفًا soft deleted، يعمل له restore.
4. إذا كان موجودًا، يحدث بياناته.
5. إذا لم يكن موجودًا، ينشئ سجلًا جديدًا في `StudentCompany`.

القيم الأساسية التي يحفظها:

```php
[
    'registration_id' => $registrationId,
    'student_id' => $studentId,
    'company_id' => $suggestion['company_id'],
    'branch_id' => $branchId,
    'department_id' => $departmentId,
    'status' => TrainingStatus::AVAILABLE,
    'created_by' => $createdBy,
]
```

## أوامر الشات بعد اختيار الطالب

بعد اختيار الطالب، لا يعود الإدخال التالي بحثًا عن طالب فقط.

إذا كتب المستخدم:

```text
اربطه مع بلدية الخليل
```

يتم التعامل مع الرسالة كطلب شركة عبر:

```text
FindCompaniesForCompanyAssistant
```

هذا الـ Action ينظف النص من كلمات مثل:

```text
اربطه، اربط، مع، في، الشركة، شركة، الطالب
```

ثم يبحث في الشركات حسب:

- اسم الشركة في translations
- الموقع `website`

إذا وجد شركة واحدة، يربطها مباشرة.

إذا وجد أكثر من شركة، يعرض قائمة ليختار المستخدم الشركة الصحيحة.

## الواجهة

الواجهة موجودة في:

```text
Modules/Core/resources/views/livewire/pages/home/student-company-assistant.blade.php
```

وتعرض:

- رسائل الشات.
- نتائج الطلاب إذا كان البحث غير محدد.
- نتائج الشركات إذا كان اسم الشركة غير محدد.
- بطاقات الاقتراحات مع زر ربط.
- زر ربط كل الاقتراحات.

لم نستخدم:

```blade
<x-filament-widgets::widget>
```

لأن المساعد Livewire Component عادي، وليس Filament Widget. استخدام هذا الغلاف يطلب methods مثل `getColumnSpan()`، وهذا سبب الخطأ السابق.

## ملاحظات مهمة

- الذكاء الاصطناعي لا ينشئ شركات جديدة.
- الربط يتم فقط مع شركات موجودة في قاعدة البيانات.
- الربط محمي بصلاحية `StudentCompany Create`.
- إذا لم يتم ضبط AI provider، يعمل fallback محلي.
- لا يوجد تخزين دائم لمحادثات الـ AI حاليًا.
- كل عملية business مهمة موجودة في Action مستقل.

## خطوات تجربة سريعة

1. افتح الصفحة الرئيسية بحساب يملك `StudentCompany Create`.
2. اكتب اسم طالب أو رقمه الجامعي.
3. إذا ظهرت أكثر من نتيجة، اختر الطالب.
4. راجع الاقتراحات واضغط `ربط الشركة`.
5. أو اكتب بعد اختيار الطالب:

```text
اربطه مع بلدية الخليل
```

6. إذا ظهرت أكثر من شركة، اختر الشركة المطلوبة.

## لماذا هذه الطريقة أفضل؟

هذه الطريقة أفضل لأنها:

- تفصل الواجهة عن المنطق.
- تستخدم Laravel AI Agent حقيقي بدل anonymous closure في كل مكان.
- تستخدم structured output بدل parsing نصي.
- تحافظ على fallback إذا فشل الذكاء الاصطناعي.
- تجعل الربط transaction آمن ومركزي.
- تسمح بإضافة اختبارات لاحقًا لكل Action بدون تشغيل Livewire أو AI فعلي.

## API المساعد

تمت إضافة API للمساعد داخل موديول PPUDS تحت:

```text
POST /api/v1/ppuds/student-company-assistant/students/search
POST /api/v1/ppuds/student-company-assistant/companies/suggest
POST /api/v1/ppuds/student-company-assistant/companies/search
POST /api/v1/ppuds/student-company-assistant/link
POST /api/v1/ppuds/student-company-assistant/link-all
```

كل المسارات محمية بـ:

```text
auth:api,sanctum
api.localize
StudentCompany Create
```

ملف المسارات:

```text
Modules/PPUDS/routes/api.php
```

الـ Controller:

```text
Modules/PPUDS/Http/Controllers/Api/V1/StudentCompanyAssistantController.php
```

طلبات التحقق والصلاحيات:

```text
Modules/PPUDS/Http/Requests/StudentCompanyAssistant
```

مخرجات JSON الخاصة بالمساعد:

```text
Modules/PPUDS/Transformers/V1/StudentCompanyAssistant
```

المهم هنا أن الـ API لا يحتوي منطقًا جديدًا منفصلًا عن الواجهة. هو يعيد استخدام نفس الـ Actions ونفس `StudentCompanySuggestionService`، لذلك الواجهة والـ API يعملان على نفس قواعد البحث والاقتراح والربط.
