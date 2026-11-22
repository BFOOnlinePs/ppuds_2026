# تسجيل الدخول في تطبيق الجوال — دليل مبرمج Flutter

هذا الدليل يشرح كل ما يحتاجه التطبيق لتسجيل الدخول عبر **الدخول الداخلي للنظام** (ليس دخول الجامعة Keycloak).

الفكرة الأساسية: **طريقة دخول التطبيق تُحدَّد من لوحة التحكم**، والتطبيق يقرأها من السيرفر ويعرض الشاشة المناسبة. لا تكتب طريقة الدخول ثابتة في التطبيق.

> **ملفات جاهزة بجانب هذا الدليل:**
> - [`flutter/ppuds_auth_api.dart`](flutter/ppuds_auth_api.dart): طبقة الـ API كاملة بـ Dart (Models و`PpudsAuthApi` ومعالجة الأخطاء). انسخه إلى المشروع.
> - [`flutter/AI_TASK_mobile_sign_in.md`](flutter/AI_TASK_mobile_sign_in.md): وصف المهمة جاهز لتعطيه لمساعد الذكاء الاصطناعي (بالإنجليزية)، مع منطق الشاشات وقائمة القبول.

---

## 1. الصورة العامة

من لوحة التحكم: **الإعدادات ← تسجيل الدخول والتحقق ← الدخول من تطبيق الجوال**. يختار المسؤول واحدة من أربع طرق:

| `mode` | ماذا يفعل المستخدم |
|---|---|
| `password` | البريد + كلمة المرور فقط (كما يعمل التطبيق الآن) |
| `code` | البريد، ثم رمز يصله على البريد أو SMS. **لا توجد كلمة مرور** |
| `password_or_code` | المستخدم يختار: كلمة المرور **أو** الرمز |
| `password_then_code` | البريد + كلمة المرور، **ثم** رمز للتأكيد (تحقق بخطوتين) |

وبشكل مستقل عن الطرق الأربع، يوجد نوعان من رموز QR، ولكل منهما مفتاح مستقل:

| النوع | المفتاح | ماذا يحدث عند المسح |
|---|---|---|
| **رمز QR شخصي** (لكل مشرف رمزه) | `personal_qr_login` | **يدخل مباشرة**، بدون بريد وبدون رمز تأكيد |
| **رمز QR الشركة** (ملصق مطبوع) | `company_qr_login` | يحدد الشركة فقط، ثم يدخل المستخدم بريده والرمز الذي يصله |

التطبيق لا يحتاج أن يعرف مسبقاً نوع الرمز الذي مسحه: يرسله إلى `POST /api/v1/auth/qr/scan`، والسيرفر يعيد النوع (القسم 8).

> لوحة التحكم (الويب) لها إعداد منفصل خاص بها، ولا يؤثر على التطبيق.

### أي نظام دخول أصلاً؟ الجامعة أم الداخلي؟

هذا الأمر لم يتغير، والتطبيق يعرفه من:

```
GET /api/v1/ppuds/settings   →   data.login_method
```

- `login_method = 2` (PPU): الدخول عبر الجامعة كما هو اليوم (`/api/v1/auth/university-login`)، **وهذا الدليل لا ينطبق**.
- `login_method = 1` (SYSTEM): الدخول الداخلي، **اتبع هذا الدليل**.

---

## 2. قبل أي شيء: اقرأ خيارات الدخول

```
GET /api/v1/auth/options
```

نفس البيانات موجودة أيضاً في `GET /api/v1/config` داخل `data.auth`. استخدم أيهما. اقرأها **في كل مرة تفتح فيها شاشة الدخول**، لأن المسؤول قد يغيّرها في أي وقت.

### مثال للاستجابة

```json
{
  "status": true,
  "message": "تم جلب خيارات تسجيل الدخول بنجاح.",
  "data": {
    "mode": "password_or_code",
    "per_user": false,
    "password_login": true,
    "code_login": true,
    "two_step": false,
    "company_qr_login": true,
    "personal_qr_login": true,
    "channels": ["mail"],
    "default_channel": "mail",
    "channel_choice": false,
    "code_length": 6
  }
}
```

### معنى الحقول

| الحقل | النوع | المعنى في الشاشة |
|---|---|---|
| `mode` | string | الطريقة العامة (أحد القيم الأربع أعلاه) |
| `per_user` | bool | **مهم:** إذا `true` فلبعض المستخدمين أو الأدوار طريقة خاصة بهم. اطلب البريد أولاً ثم استدعِ `/auth/identify` (القسم 2.1) |
| `password_login` | bool | اعرض حقل كلمة المرور / زر "الدخول بكلمة المرور" |
| `code_login` | bool | اعرض "الدخول برمز" (بريد فقط ثم رمز) |
| `two_step` | bool | بعد كلمة المرور قد يُطلب رمز (انظر القسم 6) |
| `company_qr_login` | bool | رمز QR الشركة مفعّل |
| `personal_qr_login` | bool | رموز QR الشخصية مفعّلة |

> اعرض زر **"مسح رمز QR"** إذا كان `company_qr_login` **أو** `personal_qr_login` يساوي `true`.
| `channels` | string[] | القنوات المتاحة: `mail`, `sms` |
| `default_channel` | string\|null | القناة الافتراضية |
| `channel_choice` | bool | إذا `true` اعرض اختيار "إرسال عبر البريد / SMS" وأرسل `channel` |
| `code_length` | int\|null | عدد خانات الرمز، لبناء حقل الإدخال |

> إذا تعذّر إرسال الرموز لأي سبب (مثلاً البريد غير مضبوط)، يعيد السيرفر تلقائياً `mode = password` حتى لا يُحرم أحد من الدخول. لذلك اعتمد دائماً على الاستجابة لا على افتراضات.

---

### 2.1 طريقة دخول خاصة لكل مستخدم (`per_user = true`)

يمكن للمسؤول تحديد طريقة الدخول حسب الدور أو لكل مستخدم بعينه، مثلاً: الطلاب بالرمز عبر SMS، والمشرفون بكلمة المرور. ترتيب الأولوية:

1. طريقة المستخدم نفسه، من صفحة المستخدم في لوحة التحكم.
2. قاعدة دوره، من الإعدادات.
3. الطريقة العامة للتطبيق (`mode`).

في هذه الحالة **لا يعرف التطبيق الشاشة المناسبة قبل معرفة المستخدم**، فاعرض الشاشة على خطوتين:

```
شاشة البريد ──► POST /api/v1/auth/identify {email}
                 │
                 ├─ mode = password            → اطلب كلمة المرور    → القسم 4
                 ├─ mode = code                → أرسل الرمز مباشرة   → القسم 5
                 ├─ mode = password_or_code    → اعرض الخيارين       → القسم 7
                 └─ mode = password_then_code  → اطلب كلمة المرور    → القسم 6
```

```
POST /api/v1/auth/identify
```

```json
{ "email": "student@example.com" }
```

```json
{
  "status": true,
  "message": "تم جلب خيارات تسجيل الدخول بنجاح.",
  "data": {
    "mode": "code",
    "password_login": false,
    "code_login": true,
    "two_step": false,
    "channels": ["sms"],
    "default_channel": "sms",
    "channel_choice": false,
    "code_length": 6
  }
}
```

ملاحظات:

- البريد **غير المسجّل** يعيد الطريقة العامة، تماماً مثل أي حساب ليس له طريقة خاصة. لذلك اعرض الشاشة حسب الاستجابة دائماً، دون رسالة "الحساب غير موجود".
- إذا حدد المسؤول قناة للمستخدم أو لدوره (مثلاً SMS)، تأتي في `default_channel` ويكون `channel_choice = false`.
- السيرفر يفرض الطريقة بنفسه. إذا حاول حساب "رمز فقط" الدخول بكلمة المرور الصحيحة، يرد `/auth/login` بـ `403` ورسالة توضيحية.
- إذا كان `per_user = false`، تجاهل هذا القسم واعتمد على `mode` كما في بقية الدليل.

---

## 3. قواعد عامة لكل الطلبات

### الـ Headers

```
Accept: application/json
Content-Type: application/json
Accept-Language: ar        // أو en — لغة رسائل الأخطاء والإيميل/SMS المرسل
```

> `Accept-Language` تؤثر على مسارات `options` و`otp/*` و`company-qr/*`. أما المسار الحالي `/api/v1/auth/login` فيرد بالعربية دائماً، كما كان قبل هذه الإضافة.

### شكل الاستجابة الموحّد

كل الطلبات تعيد نفس الغلاف:

```json
{ "status": true | false, "message": "نص جاهز للعرض للمستخدم", "data": { } | null }
```

اعرض `message` للمستخدم مباشرة، فهو مترجم حسب `Accept-Language`.

### رموز الحالة (HTTP status)

| الكود | المعنى | ماذا يفعل التطبيق |
|---|---|---|
| `200` | نجاح | — |
| `401` | بيانات دخول خاطئة، أو `login_ticket` منتهي/غير صالح | اعرض `message`. مع التذكرة: ارجع لشاشة كلمة المرور |
| `403` | هذه الطريقة معطّلة من لوحة التحكم | أعد قراءة `/auth/options` واعرض الشاشة الصحيحة |
| `404` | رمز QR غير صالح/ملغى/منتهي، أو الشركة غير فعّالة | اعرض `message` واطلب مسح QR الحالي |
| `422` | خطأ تحقق (حقل ناقص، أو الرمز خاطئ/منتهي) | اعرض `message`. تفاصيل الحقول في `data` |
| `429` | طلبات كثيرة | انتظر `data.retry_after` ثانية (موجودة أيضاً في الـ header `Retry-After`) |

مثال `422` لرمز خاطئ:

```json
{
  "status": false,
  "message": "الرمز غير صحيح أو منتهي الصلاحية.",
  "data": { "code": ["الرمز غير صحيح أو منتهي الصلاحية."] }
}
```

مثال `429`:

```json
{
  "status": false,
  "message": "طلبات كثيرة جداً. يرجى المحاولة مجدداً بعد 42 ثانية.",
  "data": { "retry_after": 42 }
}
```

### استجابة الدخول الناجح (موحّدة لكل الطرق)

كل طرق الدخول تنتهي بنفس الاستجابة الحالية لـ `/api/v1/auth/login`، فلا يتغير شيء في بقية التطبيق:

```json
{
  "status": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "token": "15|Qm3x....",
    "token_type": "Bearer",
    "user": {
      "id": 3,
      "name": "مشرف تجريبي",
      "email": "supervisor@example.com",
      "phone": "0599000000",
      "image": "https://...",
      "...": "..."
    }
  }
}
```

بعدها استخدم التوكن كالمعتاد:

```
Authorization: Bearer 15|Qm3x....
```

وتسجيل الخروج كما هو: `POST /api/v1/auth/logout`.

---

## 4. الطريقة `password` — كلمة المرور فقط

لا شيء جديد، الطلب الحالي نفسه:

```
POST /api/v1/auth/login
```

```json
{
  "email": "supervisor@example.com",
  "password": "********",
  "device_name": "Samsung S23",
  "fcm_token": "fcm-token-value"
}
```

الاستجابة: استجابة الدخول الناجح (القسم 3).

---

## 5. الطريقة `code` — البريد ثم الرمز (بدون كلمة مرور)

```
شاشة البريد ──► POST /auth/otp/request ──► شاشة الرمز ──► POST /auth/otp/verify ──► توكن
                                          ▲
                                          └── "إعادة الإرسال" بعد resend_after ثانية
```

### الخطوة 1: طلب الرمز

```
POST /api/v1/auth/otp/request
```

```json
{
  "email": "supervisor@example.com",
  "channel": "mail"
}
```

- `channel` اختياري، ولا يُحترم إلا إذا كان `channel_choice = true`.

الاستجابة:

```json
{
  "status": true,
  "message": "إذا كان الحساب يستطيع الدخول بهذه الطريقة، فقد أُرسل إليه رمز.",
  "data": {
    "channel": "mail",
    "expires_in": 600,
    "resend_after": 60,
    "code_length": 6
  }
}
```

> **مهم أمنياً:** الاستجابة واحدة سواء كان البريد مسجّلاً أم لا، حتى لا يُستغل الـ API لمعرفة الحسابات الموجودة. لذلك لا تكتب في التطبيق "تم الإرسال إلى بريدك" بشكل قاطع. استخدم `message` كما هو، أو صياغة مشابهة: "إذا كان الحساب موجوداً ستصلك رسالة".

| الحقل | الاستخدام |
|---|---|
| `expires_in` | صلاحية الرمز بالثواني (اعرض "صالح لمدة 10 دقائق") |
| `resend_after` | عطّل زر "إعادة الإرسال" هذا العدد من الثواني (عداد تنازلي) |
| `code_length` | عدد خانات حقل الرمز |

### الخطوة 2: التحقق من الرمز

```
POST /api/v1/auth/otp/verify
```

```json
{
  "email": "supervisor@example.com",
  "code": "482913",
  "device_name": "Samsung S23",
  "fcm_token": "fcm-token-value"
}
```

الاستجابة: استجابة الدخول الناجح (القسم 3).

### إعادة الإرسال

نفس طلب الخطوة 1 مرة أخرى، بعد انتهاء العداد. الرمز الجديد يُلغي القديم تلقائياً.

### قواعد الرمز (من السيرفر، للعلم)

- يُستخدم **مرة واحدة فقط**.
- صالح لمدة `expires_in`.
- له عدد محاولات خاطئة محدود (افتراضياً 5). بعدها يُحرق، ويجب طلب رمز جديد.
- كثرة المحاولات الخاطئة تقفل البريد مؤقتاً، ويعيد السيرفر `429` مع `retry_after`.

---

## 6. الطريقة `password_then_code` — تحقق بخطوتين

```
البريد + كلمة المرور ──► POST /auth/login
        │
        ├─ data.token موجود        → دخل مباشرة (لا يحدث في هذا الوضع، لكن تعامل معه)
        └─ data.requires_code=true → احفظ login_ticket ──► شاشة الرمز
                                                          │
                       POST /auth/otp/verify {login_ticket, code} ──► توكن
                       إعادة الإرسال: POST /auth/otp/request {login_ticket}
```

### الخطوة 1: كلمة المرور

نفس الطلب الحالي:

```
POST /api/v1/auth/login
```

```json
{
  "email": "supervisor@example.com",
  "password": "********",
  "channel": "mail"
}
```

إذا كانت كلمة المرور خاطئة: `401` كالمعتاد.

إذا كانت صحيحة، **لا يأتي توكن**، بل:

```json
{
  "status": true,
  "message": "أدخل الرمز الذي أرسلناه إليك لإتمام تسجيل الدخول.",
  "data": {
    "requires_code": true,
    "login_ticket": "eyJpdiI6IkV2...",
    "channel": "mail",
    "expires_in": 600,
    "resend_after": 60,
    "code_length": 6
  }
}
```

> **قاعدة بسيطة للتطبيق:** بعد `/auth/login` افحص الاستجابة. إذا وُجد `data.token` فقد تم الدخول، وإذا كان `data.requires_code == true` فانتقل لشاشة الرمز. بهذا يعمل نفس الكود مع كل الطرق.

`login_ticket`:

- دليل على أن كلمة المرور صحيحة، وصالح بنفس مدة الرمز.
- احفظه في الذاكرة فقط (state) أثناء شاشة الرمز، ولا تخزّنه بشكل دائم.
- لا يحتاج التطبيق لفهم محتواه، فهو مشفّر.

### الخطوة 2: الرمز

```
POST /api/v1/auth/otp/verify
```

```json
{
  "login_ticket": "eyJpdiI6IkV2...",
  "code": "482913",
  "device_name": "Samsung S23",
  "fcm_token": "fcm-token-value"
}
```

الاستجابة: استجابة الدخول الناجح (القسم 3).

- إذا انتهت صلاحية التذكرة أو كانت غير صالحة: `401`. أرجع المستخدم لشاشة كلمة المرور.

### إعادة الإرسال

```
POST /api/v1/auth/otp/request
```

```json
{ "login_ticket": "eyJpdiI6IkV2..." }
```

> في هذا الوضع **لا يعمل** `otp/request` و`otp/verify` بالبريد وحده (يعيد `403`). الرمز لا يُطلب إلا بعد كلمة مرور صحيحة.

---

## 7. الطريقة `password_or_code`

اعرض الخيارين في شاشة الدخول:

- "الدخول بكلمة المرور" → القسم 4.
- "الدخول برمز يصلك على البريد" → القسم 5.

---

## 8. الدخول عبر رمز QR (شخصي أو للشركة)

يظهر زر "مسح رمز QR" إذا كان `personal_qr_login` أو `company_qr_login` يساوي `true`. كل الرموز تُنشأ وتُطبع من صفحة الشركة في لوحة التحكم.

```
مسح QR ──► POST /auth/qr/scan {qr_token, device_name, fcm_token}
              │
              ├─ data.type = "personal" → دخل مباشرة (فيها token و user) ✔
              │
              └─ data.type = "company"  → (اعرض اسم وشعار الشركة)
                     ──► البريد ──► POST /auth/otp/request {email, qr_token}
                     ──► الرمز  ──► POST /auth/otp/verify  {email, code, qr_token} ──► توكن
```

### محتوى الـ QR

الـ QR يحتوي رابطاً بهذا الشكل (للنوعين):

```
https://<domain>/c/9fQxK2v...Lm7   (43 حرفاً بعد /c/)
```

أرسل **النص الممسوح كما هو** (الرابط كاملاً) في `qr_token`، والسيرفر يستخرج منه الرمز ويحدد نوعه. لو فتح أحد الرابط بالمتصفح بدل التطبيق، تظهر له صفحة تطلب منه فتح التطبيق، ولا يحدث أي دخول من المتصفح. صفحة الرمز الشخصي لا تعرض اسم صاحبه.

### الخطوة 1: المسح

```
POST /api/v1/auth/qr/scan
```

```json
{
  "qr_token": "https://ds.ppu.edu/c/9fQxK2v...Lm7",
  "device_name": "Samsung S23",
  "fcm_token": "fcm-token-value"
}
```

`device_name` و`fcm_token` تُستخدم فقط إذا كان الرمز شخصياً (لأنه يدخل مباشرة). أرسلها دائماً.

**إذا كان الرمز شخصياً**، يتم الدخول فوراً، والاستجابة هي استجابة الدخول الناجح نفسها مع `type`:

```json
{
  "status": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "type": "personal",
    "token": "15|Qm3x....",
    "token_type": "Bearer",
    "user": { "id": 3, "name": "مشرف تجريبي", "email": "supervisor@example.com", "...": "..." }
  }
}
```

**إذا كان رمز الشركة**:

```json
{
  "status": true,
  "message": "تم العثور على الشركة.",
  "data": {
    "type": "company",
    "company": {
      "id": 12,
      "name": "شركة التدريب",
      "logo_url": "https://..."
    }
  }
}
```

الأخطاء:

- `404`:
  - الرمز غير صالح، أو أُلغي (المسؤول ولّد رمزاً جديداً)، أو انتهت صلاحيته.
  - الشركة غير فعّالة.
  - للرمز الشخصي: صاحبه لم يعد تابعاً للشركة أو حُذف حسابه.
  - نوع الرمز معطّل من الإعدادات.
- `403`: الدخول عبر QR بنوعيه معطّل.

> `POST /api/v1/auth/company-qr/resolve` ما زال يعمل لرمز الشركة فقط (يعيد `404` للرمز الشخصي)، لكن استخدم `qr/scan` لأنه يغطي النوعين.

### رمز الشركة: الخطوة 2 و3

مثل الطريقة `code` تماماً، مع إضافة `qr_token` (نفس النص الممسوح) في **الطلبين**:

```json
// POST /api/v1/auth/otp/request
{ "email": "supervisor@example.com", "qr_token": "https://ds.ppu.edu/c/9fQx..." }

// POST /api/v1/auth/otp/verify
{ "email": "supervisor@example.com", "code": "482913", "qr_token": "https://ds.ppu.edu/c/9fQx...", "device_name": "Samsung S23", "fcm_token": "..." }
```

ملاحظات:

- **لا يدخل إلا من يتبع لهذه الشركة** (مثلاً مشرف الشركة على أحد فروعها)، وبحسب الأدوار المسموحة في لوحة التحكم. غير ذلك لن يصله رمز، والاستجابة نفسها لا تكشف ذلك.
- الرمز المطلوب عبر QR يعمل لهذه الشركة فقط، ولا يصلح للدخول العادي، والعكس صحيح.
- دخول QR بنوعيه مستقل عن `mode`، ويحكمه `company_qr_login` و`personal_qr_login` فقط.

### عن الرمز الشخصي

- الرمز الشخصي **بمثابة كلمة مرور**: أي شخص يمسحه يدخل باسم صاحبه. لا تحفظ صورته في التطبيق ولا تعرضه.
- إذا فُقد، يلغيه المسؤول من لوحة التحكم أو يولّد بديلاً، فيتوقف القديم فوراً.
- الأجهزة التي دخلت مسبقاً تبقى مسجّلة لأن التوكن صالح. للخروج منها: `POST /api/v1/auth/logout`.

---

## 9. ملخص كل الـ Endpoints

| Method | Path | Auth | الوصف |
|---|---|---|---|
| GET | `/api/v1/auth/options` | — | خيارات الدخول (أو `data.auth` في `/api/v1/config`) |
| POST | `/api/v1/auth/identify` | — | طريقة دخول حساب معيّن (عند `per_user = true`): `{email}` |
| POST | `/api/v1/auth/login` | — | كلمة المرور. قد تعيد `requires_code` في وضع الخطوتين، أو `403` لحساب "رمز فقط" |
| POST | `/api/v1/auth/otp/request` | — | طلب/إعادة إرسال رمز: `{email}` أو `{email, qr_token}` أو `{login_ticket}` |
| POST | `/api/v1/auth/otp/verify` | — | التحقق وإصدار التوكن: `{email, code}` أو `{email, code, qr_token}` أو `{login_ticket, code}` |
| POST | `/api/v1/auth/qr/scan` | — | مسح أي QR: الشخصي يدخل مباشرة، والشركة تعيد بياناتها: `{qr_token, device_name, fcm_token}` |
| POST | `/api/v1/auth/company-qr/resolve` | — | (قديم، لرمز الشركة فقط) التحقق من QR الشركة: `{qr_token}` |
| POST | `/api/v1/auth/logout` | Bearer | تسجيل الخروج (بدون تغيير) |

حقول اختيارية مشتركة في `otp/verify`: `device_name` و`fcm_token` (نفس `/auth/login`).

حد عام للطلبات: 20 طلباً في الدقيقة لكل IP على مسارات الرمز و QR، وبعدها `429`.

---

## 10. نماذج Dart جاهزة

> مثال باستخدام `dio`. عدّل حسب طبقة الشبكة الموجودة في التطبيق.

### Model: AuthOptions

```dart
enum LoginMode { password, code, passwordOrCode, passwordThenCode }

LoginMode _parseMode(String? v) => switch (v) {
      'code' => LoginMode.code,
      'password_or_code' => LoginMode.passwordOrCode,
      'password_then_code' => LoginMode.passwordThenCode,
      _ => LoginMode.password,
    };

class AuthOptions {
  final LoginMode mode;
  final bool passwordLogin;
  final bool codeLogin;
  final bool twoStep;
  final bool companyQrLogin;
  final bool personalQrLogin;
  final bool perUser;
  final List<String> channels;
  final String? defaultChannel;
  final bool channelChoice;
  final int codeLength;

  AuthOptions.fromJson(Map<String, dynamic> j)
      : mode = _parseMode(j['mode'] as String?),
        passwordLogin = j['password_login'] == true,
        codeLogin = j['code_login'] == true,
        twoStep = j['two_step'] == true,
        companyQrLogin = j['company_qr_login'] == true,
        personalQrLogin = j['personal_qr_login'] == true,
        perUser = j['per_user'] == true,
        channels = List<String>.from(j['channels'] ?? const []),
        defaultChannel = j['default_channel'] as String?,
        channelChoice = j['channel_choice'] == true,
        codeLength = (j['code_length'] as int?) ?? 6;
}
```

### Model: CodeChallenge

```dart
class CodeChallenge {
  final String channel;     // mail | sms
  final int expiresIn;      // ثواني
  final int resendAfter;    // ثواني
  final int codeLength;
  final String? loginTicket; // في وضع الخطوتين فقط

  CodeChallenge.fromJson(Map<String, dynamic> j)
      : channel = j['channel'] as String,
        expiresIn = j['expires_in'] as int,
        resendAfter = j['resend_after'] as int,
        codeLength = j['code_length'] as int,
        loginTicket = j['login_ticket'] as String?;
}
```

### نتيجة كلمة المرور (توكن أو خطوة ثانية)

```dart
sealed class PasswordLoginResult {}

class LoggedIn extends PasswordLoginResult {
  final String token;
  final Map<String, dynamic> user;
  LoggedIn(this.token, this.user);
}

class CodeRequired extends PasswordLoginResult {
  final CodeChallenge challenge; // فيه loginTicket
  CodeRequired(this.challenge);
}
```

### نتيجة مسح QR (دخول مباشر أو شركة)

```dart
sealed class QrScanResult {}

/// رمز شخصي: تم الدخول مباشرة.
class QrSignedIn extends QrScanResult {
  final String token;
  final Map<String, dynamic> user;
  QrSignedIn(this.token, this.user);
}

/// رمز الشركة: أكمل بالبريد ثم الرمز، مع نفس qrToken.
class QrCompany extends QrScanResult {
  final Map<String, dynamic> company; // id, name, logo_url
  final String qrToken;
  QrCompany(this.company, this.qrToken);
}
```

### Service

```dart
class AuthApi {
  AuthApi(this._dio);
  final Dio _dio;

  Future<AuthOptions> options() async {
    final r = await _dio.get('/api/v1/auth/options');
    return AuthOptions.fromJson(r.data['data']);
  }

  /// طريقة دخول حساب معيّن — عند options.perUser == true.
  /// نفس شكل AuthOptions (بدون حقول QR و per_user).
  Future<AuthOptions> identify(String email) async {
    final r = await _dio.post('/api/v1/auth/identify', data: {'email': email});
    return AuthOptions.fromJson(r.data['data']);
  }

  /// كلمة المرور — تعيد توكن، أو تطلب رمزاً في وضع الخطوتين.
  Future<PasswordLoginResult> loginWithPassword({
    required String email,
    required String password,
    String? channel,
    String? deviceName,
    String? fcmToken,
  }) async {
    final r = await _dio.post('/api/v1/auth/login', data: {
      'email': email,
      'password': password,
      if (channel != null) 'channel': channel,
      if (deviceName != null) 'device_name': deviceName,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });
    final data = r.data['data'] as Map<String, dynamic>;
    if (data['requires_code'] == true) {
      return CodeRequired(CodeChallenge.fromJson(data));
    }
    return LoggedIn(data['token'] as String, data['user'] as Map<String, dynamic>);
  }

  /// طلب رمز: بالبريد (وضع code / QR) أو بالتذكرة (إعادة إرسال في وضع الخطوتين).
  Future<CodeChallenge> requestCode({
    String? email,
    String? loginTicket,
    String? qrToken,
    String? channel,
  }) async {
    final r = await _dio.post('/api/v1/auth/otp/request', data: {
      if (email != null) 'email': email,
      if (loginTicket != null) 'login_ticket': loginTicket,
      if (qrToken != null) 'qr_token': qrToken,
      if (channel != null) 'channel': channel,
    });
    return CodeChallenge.fromJson(r.data['data']);
  }

  /// التحقق من الرمز وإصدار التوكن.
  Future<LoggedIn> verifyCode({
    required String code,
    String? email,
    String? loginTicket,
    String? qrToken,
    String? deviceName,
    String? fcmToken,
  }) async {
    final r = await _dio.post('/api/v1/auth/otp/verify', data: {
      'code': code,
      if (email != null) 'email': email,
      if (loginTicket != null) 'login_ticket': loginTicket,
      if (qrToken != null) 'qr_token': qrToken,
      if (deviceName != null) 'device_name': deviceName,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });
    final data = r.data['data'] as Map<String, dynamic>;
    return LoggedIn(data['token'] as String, data['user'] as Map<String, dynamic>);
  }

  /// أي QR ممسوح: الشخصي يدخل مباشرة، ورمز الشركة يعيد بياناتها.
  Future<QrScanResult> scanQr(String scanned, {String? deviceName, String? fcmToken}) async {
    final r = await _dio.post('/api/v1/auth/qr/scan', data: {
      'qr_token': scanned,
      if (deviceName != null) 'device_name': deviceName,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });
    final data = r.data['data'] as Map<String, dynamic>;
    if (data['type'] == 'personal') {
      return QrSignedIn(data['token'] as String, data['user'] as Map<String, dynamic>);
    }
    return QrCompany(data['company'] as Map<String, dynamic>, scanned);
  }
}
```

### قراءة الأخطاء

```dart
String errorMessage(DioException e) =>
    (e.response?.data is Map ? e.response?.data['message'] : null) ?? 'حدث خطأ، حاول مجدداً';

int? retryAfter(DioException e) {
  final d = e.response?.data;
  return (d is Map && d['data'] is Map) ? d['data']['retry_after'] as int? : null;
}
```

### بناء شاشة الدخول من الخيارات

```dart
final o = await authApi.options();

void showScreenFor(AuthOptions m) {
  switch (m.mode) {
    case LoginMode.password:
    case LoginMode.passwordThenCode:
      showPasswordStep();                  // بعد الإرسال: افحص LoggedIn / CodeRequired
    case LoginMode.code:
      sendCodeAndShowCodeStep();           // otp/request ثم شاشة الرمز
    case LoginMode.passwordOrCode:
      showPasswordStep(withCodeOption: true);
  }
}

if (o.perUser) {
  // لكل مستخدم طريقته: البريد أولاً، ثم طريقته هو.
  final email = await askForEmail();
  showScreenFor(await authApi.identify(email));
} else {
  showScreenFor(o);                        // الطريقة العامة للجميع
}

if (o.companyQrLogin || o.personalQrLogin) showScanQrButton();

// عند المسح:
final result = await authApi.scanQr(scannedText, deviceName: deviceName, fcmToken: fcmToken);
switch (result) {
  case QrSignedIn(:final token, :final user):
    saveSessionAndGoHome(token, user);        // رمز شخصي: دخل مباشرة
  case QrCompany(:final company, :final qrToken):
    showEmailFormFor(company, qrToken);       // رمز الشركة: بريد ثم رمز
}
```

---

## 11. توصيات الواجهة (UX)

- **حقل الرمز:**
  - طوله `code_length`، ولوحة مفاتيح أرقام.
  - فعّل `autofillHints: [AutofillHints.oneTimeCode]` حتى يقترح iOS/Android الرمز من الرسالة.
  - أرسل الرمز تلقائياً عند اكتمال الخانات.
- **زر إعادة الإرسال:** عداد تنازلي بقيمة `resend_after`. عند `429` استخدم `retry_after`.
- **رسالة بعد طلب الرمز:** استخدم `message` من السيرفر، ولا تؤكد أن البريد موجود (انظر القسم 5).
- **"تغيير البريد"** في شاشة الرمز يرجع لشاشة البريد. في وضع الخطوتين يرجع لشاشة كلمة المرور ويحذف `login_ticket`.
- **عند `403`:** أعد تحميل `/auth/options`. الغالب أن المسؤول غيّر طريقة الدخول.
- **عند `401` مع التذكرة:** "انتهت محاولة الدخول، أدخل كلمة المرور مجدداً".
- لا تخزّن الرمز ولا `login_ticket` في التخزين الدائم ولا في الـ logs.

---

## 12. سيناريوهات اختبار قبل التسليم

اضبط كل وضع من لوحة التحكم وجرّب:

1. **`password`:** الدخول بكلمة المرور يعمل كما كان. `otp/request` يعيد `403`.
2. **`code`:**
   - طلب رمز ← التحقق ← دخول.
   - `/auth/login` بكلمة المرور يعيد `403`.
   - رمز خاطئ يعيد `422`، و5 أخطاء تحرق الرمز.
   - إعادة الإرسال قبل انتهاء العداد تعيد `429`.
3. **`password_or_code`:** الطريقتان تعملان.
4. **`password_then_code`:**
   - كلمة مرور خاطئة تعيد `401`.
   - كلمة مرور صحيحة تعيد `requires_code` ← التحقق بالتذكرة ← دخول.
   - إعادة الإرسال بالتذكرة.
   - تذكرة منتهية (بعد 10 دقائق) تعيد `401`.
5. **QR الشركة:**
   - مسح رمز الشركة ← `type = company` ← اسم الشركة ← بريد مشرف الشركة ← رمز ← دخول.
   - QR ملغى (ولّد المسؤول رمزاً جديداً) يعيد `404`.
   - بريد شخص لا يتبع للشركة: نفس الرسالة، لكن لا يصله رمز.
6. **QR الشخصي:**
   - مسح رمز شخصي ← `type = personal` ← دخول مباشر بدون أي إدخال.
   - رمز ملغى أو صاحبه أُزيل من الشركة يعيد `404`.
   - مع تعطيل "رموز QR الشخصية" من الإعدادات يعيد `404`، أو `403` إذا كان النوعان معطّلين.
7. **طريقة لكل مستخدم:** ضع قاعدة "الطلاب: رمز عبر SMS" في الإعدادات:
   - `options.per_user = true`.
   - `identify` لطالب يعيد `mode = code` مع `default_channel = sms`.
   - `identify` لبريد غير موجود يعيد الطريقة العامة.
   - كلمة مرور طالب صحيحة على `/auth/login` تعيد `403`.
8. **اللغة:** `Accept-Language: en` يعيد رسائل مسارات الرمز وQR بالإنجليزية.

---

## 13. للمراجعة في السيرفر

- التوثيق التفاعلي (Swagger) يحتوي كل المسارات أعلاه تحت تصنيف **Auth**.
- الإعدادات: لوحة التحكم ← الإعدادات ← **تسجيل الدخول والتحقق** (ومنها قسم **طريقة الدخول حسب الدور**).
- طريقة مستخدم بعينه: لوحة التحكم ← المستخدمين ← تعديل ← قسم **طريقة تسجيل الدخول**.
- رمز QR الشركة والرموز الشخصية: لوحة التحكم ← الشركات ← تفاصيل الشركة ← تبويب **رمز QR للدخول** (الرموز الشخصية في قسم "رموز الدخول الشخصية" أسفل التبويب).
