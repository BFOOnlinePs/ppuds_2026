<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

/**
 * وصف الـ API يُكتب هنا كـ attribute لا كـ docblock، لأن الوصف نص طويل متعدد
 * الأسطر و Swagger UI يعرضه Markdown في أعلى الصفحة. الـ docblock لا يحتمل
 * أسطراً حقيقية، والـ nowdoc يحتملها كما هي.
 */
#[OA\PathItem(path: '/api')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\Info(
    title: 'Be Found Online APis',
    version: '1.0.0',
    description: <<<'MARKDOWN'
دليل مختصر يسبق النقاط أدناه. تفاصيل كل حقل داخل النقطة نفسها.

---

## المصادقة

كل المسارات تتطلب توكن، عدا التسجيل والدخول. الصق التوكن في زر **Authorize** أعلى هذه الصفحة لتجرّب النقاط مباشرة.

```
Authorization: Bearer <token>
Accept: application/json
```

انتهاء التوكن يعيد `401` برسالة `Unauthenticated.` — أخرج المستخدم، ولا تُعِد المحاولة.

---

## شكل الاستجابة

كل استجابة تمرّ عبر غلاف واحد. اقرأ `status` أولاً.

**مورد مفرد** — البيانات داخل `data`:

```json
{ "status": true, "message": "Note created successfully", "data": { "id": 12 } }
```

**قائمة مُصفَّحة** — حقول Laravel تُدمج في الجذر، لا داخل `data`:

```json
{
  "status": true,
  "message": "Notes retrieved successfully",
  "data":  [ { "id": 12 } ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta":  { "current_page": 1, "last_page": 7, "per_page": 15, "total": 98 }
}
```

اكتب دالة استخراج واحدة تتعامل مع الشكلين.

---

## الأخطاء

نفس الغلاف مع `status: false`، وكلها من معالج واحد.

| الرمز | المعنى | `data` | ما تفعله الواجهة |
|---|---|---|---|
| 401 | غير مُصادَق | null | أخرج المستخدم |
| 403 | ممنوع | null | اعرض الرسالة |
| 404 | غير موجود | null | حالة فارغة |
| 405 | فعل خاطئ | null | راجع «رفع الملفات» أدناه |
| 422 | فشل تحقق | كائن الأخطاء | وزّعها على الحقول |
| 500 | خطأ خادم | null | رسالة عامة |

في `422` تحمل `message` **أول خطأ فقط** جاهزاً للعرض، و`data` تحمل الخريطة الكاملة:

```json
{
  "status": false,
  "message": "The query field is required.",
  "data": { "query": ["The query field is required."] }
}
```

---

## اللغة

```
Accept-Language: ar
```

المقبول `ar` و `en` **فقط**. الصيغ الطويلة مثل `ar-PS` أو `en-US` تُتجاهَل بصمت ويعود الخادم للغة الافتراضية — أرسل الرمز المجرّد.

---

## معاملات القوائم

| المعامل | مثال | ملاحظة |
|---|---|---|
| `per_page` | `?per_page=25` | الافتراضي 15، الأعلى 100 |
| `page` | `?page=3` | |
| `filter[…]` | `?filter[status]=1` | المسموح فقط، وإلا رُفض الطلب كله |
| `sort` | `?sort=-created_at` | السالب للتنازلي |
| `include` | `?include=student,company` | مفصولة بفواصل |
| `fields[…]` | `?fields[notes]=id,title` | لتقليل حجم الاستجابة |

معامل غير مسموح **يُرفض الطلب كله**، ولا يُتجاهَل.

---

## ⚠ رفع الملفات مع التعديل

PHP لا يقرأ `multipart/form-data` من طلبات `PATCH`، فيصل الجسم فارغاً. لذلك أي تعديل يرفع ملفاً يُرسَل كـ `POST` مع تزوير الفعل:

```
POST /api/v1/ppuds/companies/3
Content-Type: multipart/form-data

_method=PATCH        ← إلزامي
name=شركة جديدة
logo=@file.png
```

تعديل **بلا** ملف ← `PATCH` عادي بـ JSON. تعديل **بملف** ← `POST` + `_method=PATCH`. نسيان `_method` يعطي `405`.

ينطبق على `/companies/{id}` و `/notes/{id}` و `/student-attachments/{id}` و `/payments/{id}`.

---

## التقييد التلقائي حسب الدور

القوائم مُقيَّدة **في الخادم**. نفس المسار يعيد نتائج مختلفة لكل مستخدم دون أن ترسل الواجهة أي فلتر.

| الدور | ما يعيده `GET /ppuds/reports` |
|---|---|
| الطالب | تقاريره هو |
| مشرف الشركة | طلاب الأقسام المرتبط بها هو |
| المشرف الجامعي | الطلاب المسنَدون إليه |
| المدير | الكل |

**لا تُرشِّح في الواجهة حسب الدور**، ولا ترسل `student_id` ظنّاً أنك تحمي البيانات. وقائمة فارغة عند مشرف شركة تعني غالباً أنه غير مربوط بأي قسم — حالة إعداد لا خطأ برمجي.

---

## علامة الشركة

تُحسب آلياً لحظة تسليم مشرف الشركة للاستبيان عبر `POST /ppuds/survey-answers`. لا نقطة تحسبها يدوياً.

تدخل الحساب أسئلة **التقييم فقط** بمقياس 1–5:

```
company_score = ( Σ إجابات التقييم ÷ (عدد الأسئلة × 5) ) × company_max_grade
```

مثال: `5,4,3,4` = 16 من 20 ← `0.8 × 40 = 32`.

اقرأ السقف من `max_company_score` في الاستجابة ولا تُثبّته في الكود، فهو قابل للتغيير من الإعدادات. و`total_score` يعود `null` إن لم تُرصد أي علامة — لا تعرض صفراً مكانه.

---

## المزامنة التفاضلية

للتطبيقات التي تحتفظ بنسخة محلية. أرسل `since` واحفظ `meta.timestamp` العائد للمرة القادمة:

```
GET /api/v1/sync/users?since=2026-09-01 12:00:00

{ "data": [ … ], "meta": { "timestamp": "2026-09-08 10:30:00" } }
```

أول مزامنة: اترك `since` فارغاً. الصيغة `Y-m-d H:i:s`.

---

## ⚠ تغييرات تكسر التوافق

كان التوثيق يصف نقاطاً بأفعال أو مسارات خاطئة. صُحّح كله. إن كنت قد برمجت على التوثيق القديم فراجع هذه المواضع:

| النقطة | كان التوثيق يقول | الصواب | الأثر |
|---|---|---|---|
| التسجيل | `POST /auth/registration` | `POST /auth/register` | كان 404 |
| سجل النشاط | `GET /activities` | `GET /activity-logs` | كان 404 |
| الزيارات الميدانية | `POST /field-visits/{id}` | `PATCH /field-visits/{id}` | كان 405 |
| طلبات الإجازة | `POST /leave-requests/{id}` | `PATCH /leave-requests/{id}` | كان 405 |
| الدفعات | `POST /payments/{id}` | `PATCH /payments/{id}` | كان 405 |
| الإعدادات | `PUT /ppuds/settings` | `PATCH /ppuds/settings` | كان 405 |
| مرفقات الطالب | `POST /student-attachments/{id}` | `PATCH /student-attachments/{id}` | كان 405 |
| الحضور | لم تظهر إطلاقاً | `PATCH /attendances/{id}` | كانت مخفية |
| تعديل ملاحظة | موثّقة بلا مسار | `PATCH /ppuds/notes/{id}` | سُجِّل المسار الناقص |

**نقاط جديدة في التوثيق:** مساعد ربط الشركات (خمس نقاط)، و `GET /ppuds/chats/contacts`، و `GET /ppuds/attendances/reports`، و `GET /ppuds/supervisors/{id}/statistics`، ونقاط المزامنة الثلاث.

**نقاط اختفت:** كان التوثيق يعرض نحو 40 نقطة تخص وحدات معطّلة في هذا النظام (متجر، كوبونات، توصيل، تسويق) وكلها تعيد `404`. صار Swagger يقرأ حالة الوحدات ولا يعرض إلا المفعَّلة.
MARKDOWN
)]
abstract class Controller
{
    //
}
