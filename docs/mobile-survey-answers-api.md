# API إجابات الاستبيانات (تطبيق الموبايل)

**الخادم (تست):** `https://ppuds.bfohost.com`

**الهيدرز المطلوبة في كل الطلبات:**

```
Authorization: Bearer {token}
Accept: application/json
Accept-Language: ar        (اختياري، للغة رسائل الأخطاء)
```

---

## 1) جلب الاستبيان وأسئلته

```
GET /api/v1/ppuds/surveys/{survey_id}
```

لكل سؤال `id` و `type` و `is_required` و `options`. قيمة `type` تحدد شكل الإجابة:

| type | النوع | ما يُرسل في `value` |
|---|---|---|
| 1, 2 | نص قصير / طويل | نص |
| 3, 5 | خيار واحد (Radio / Select) | `id` الخيار |
| 4, 6 | عدة خيارات | مصفوفة من `id` الخيارات |
| 7 | تاريخ | نص بصيغة `2026-11-25` |
| **8** | **رفع ملف** | **الملف نفسه (انظر القسم 2-ب)** |
| 9 | تقييم | رقم من 1 إلى 5 |
| 10 | رقم | رقم ≥ 0 |

---

## 2) إرسال الإجابات

```
POST /api/v1/ppuds/survey-answers
```

| الحقل | إلزامي | ملاحظات |
|---|---|---|
| `survey_id` | نعم | |
| `student_company_id` | حسب الحالة | إلزامي عندما يقيّم مشرف الشركة طالبًا، أو يقيّم الطالب شركة |
| `answers[i][question_id]` | نعم | يجب أن يكون السؤال من نفس الاستبيان |
| `answers[i][value]` | | القيمة حسب جدول الأنواع |
| `answers[i][file]` | لأسئلة الملف | الملف نفسه |

### أ) استبيان بلا ملفات: JSON عادي

```json
{
  "survey_id": 1,
  "answers": [
    { "question_id": 10, "value": "نص الإجابة" },
    { "question_id": 11, "value": 4 },
    { "question_id": 12, "value": [31, 32] }
  ]
}
```

### ب) استبيان فيه سؤال ملف: يجب أن يكون الطلب كله `multipart/form-data`

> **مهم:** لم يعد الخادم يقبل مسار الملف على الجهاز، مثل `/data/user/...` أو `file://...`.
> يجب رفع الملف نفسه، وإلا يرجع خطأ **422**.

```bash
curl -X POST https://ppuds.bfohost.com/api/v1/ppuds/survey-answers \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json" \
  -F "survey_id=1" \
  -F "answers[0][question_id]=10" \
  -F "answers[0][value]=نص الإجابة" \
  -F "answers[1][question_id]=12" \
  -F "answers[1][value][]=31" \
  -F "answers[1][value][]=32" \
  -F "answers[2][question_id]=15" \
  -F "answers[2][file]=@/path/to/report.pdf"
```

مثال Flutter باستخدام Dio:

```dart
final form = FormData();
form.fields.add(MapEntry('survey_id', '$surveyId'));
if (studentCompanyId != null) {
  form.fields.add(MapEntry('student_company_id', '$studentCompanyId'));
}

for (var i = 0; i < answers.length; i++) {
  final a = answers[i];
  form.fields.add(MapEntry('answers[$i][question_id]', '${a.questionId}'));

  if (a.type == 8) {
    // الملف نفسه، لا مساره
    form.files.add(MapEntry(
      'answers[$i][file]',
      await MultipartFile.fromFile(a.filePath, filename: a.filePath.split('/').last),
    ));
  } else if (a.value is List) {
    for (final v in a.value) {
      form.fields.add(MapEntry('answers[$i][value][]', '$v'));
    }
  } else if (a.value != null) {
    form.fields.add(MapEntry('answers[$i][value]', '${a.value}'));
  }
}

// بدون Content-Type: application/json في هذا الطلب
await dio.post('/api/v1/ppuds/survey-answers', data: form);
```

ملاحظات على الـ multipart:

- لا تضع `Content-Type` يدويًا، فالمكتبة تضعه مع الـ boundary.
- المصفوفات (عدة خيارات) تُرسل هكذا: `answers[i][value][]` مكررة لكل خيار.
- يمكن وضع الملف في `answers[i][file]` أو في `answers[i][value]`، والاثنان مقبولان.

**قيود الملف:**

- ملف واحد لكل سؤال.
- أقصى حجم: **20MB**.
- الأنواع المسموحة: `pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, jpg, jpeg, png, heic, webp, zip`.
- إذا كان سؤال الملف إلزاميًا (`is_required`) فيجب إرسال الملف.

---

## 3) الردود

**نجاح، 201:**

```json
{ "status": true, "message": "تم إرسال الإجابات بنجاح", "data": null }
```

**أخطاء:** كلها بالشكل `{ "status": false, "message": "..." }`

| الكود | السبب |
|---|---|
| 422 | سؤال لا يتبع هذا الاستبيان، أو أُرسل مسار بدل الملف، أو أكثر من ملف لنفس السؤال، أو نوع أو حجم غير مسموح، أو سؤال ملف إلزامي بلا ملف، أو قيمة غير رقمية لسؤال رقم، أو `student_company_id` ناقص |
| 403 | الاستبيان مُرسل مسبقًا، أو الطالب/الشركة غير متاح للتقييم أو تم تقييمه |
| 401 | التوكن غير صالح أو منتهٍ |

---

## 4) جلب الإجابات المرسلة

```
GET /api/v1/ppuds/survey-answers?filter[survey_id]=1&include=question
```

- الفلاتر المتاحة: `survey_id` و `student_company_id` و `evaluated_student_id` و `submitted_by`.
- المستخدم العادي يرى إجاباته فقط. الأدمن والمشرفون يرون كل الإجابات.
- في سؤال الملف يحتوي `text_answer` على مسار الملف على الخادم، مثل `surveys/{uuid}/report.pdf`.
  الملفات محفوظة في مكان خاص، وتُفتح من لوحة التحكم في الويب فقط بعد التحقق من الصلاحية، ولا يوجد رابط عام لها.
