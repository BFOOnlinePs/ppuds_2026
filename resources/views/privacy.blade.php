<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> PPU Dual Study </title>
    <!-- استدعاء Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap');
        body {
            font-family: 'Tajawal', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

    <div class="max-w-4xl mx-auto px-4 py-12 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                سياسة الخصوصية
            </h1>
            <p class="mt-4 text-lg text-gray-500">
                آخر تحديث: 6 مايو 2026
            </p>
        </div>

        <!-- Content -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg p-8">

            <div class="prose prose-blue max-w-none">

                <h2 class="text-2xl font-bold text-gray-900 mb-4 border-b pb-2">1. مقدمة</h2>
                <p class="mb-6 text-gray-700 leading-relaxed">
                    نرحب بك في تطبيق <strong>[اسم التطبيق]</strong>. توضح "سياسة الخصوصية" هذه كيف نقوم بجمع، استخدام، وحماية معلوماتك الشخصية عند استخدامك لتطبيقنا المتاح على الهواتف الذكية. باستخدامك للتطبيق، فإنك توافق على ممارسات جمع البيانات الموضحة في هذا المستند.
                </p>

                <h2 class="text-2xl font-bold text-gray-900 mb-4 border-b pb-2">2. المعلومات التي نجمعها</h2>
                <p class="mb-6 text-gray-700 leading-relaxed">
                    قد نقوم بجمع أنواع معينة من المعلومات لتوفير وتحسين خدماتنا، بما في ذلك:
                </p>
                <ul class="list-disc list-inside mb-6 text-gray-700">
                    <li><strong>المعلومات الشخصية:</strong> مثل الاسم، وعنوان البريد الإلكتروني (إذا قمت بالتسجيل في التطبيق).</li>
                    <li><strong>بيانات الاستخدام:</strong> معلومات حول كيفية تفاعلك مع التطبيق (مثل وقت الجلسات والأعطال) لتحسين تجربة المستخدم.</li>
                    <li><strong>معلومات الجهاز:</strong> مثل نوع الجهاز، ونظام التشغيل، والمعرفات الفريدة للجهاز.</li>
                </ul>

                <h2 class="text-2xl font-bold text-gray-900 mb-4 border-b pb-2">3. كيف نستخدم معلوماتك</h2>
                <p class="mb-6 text-gray-700 leading-relaxed">
                    نستخدم المعلومات التي نجمعها للأغراض التالية:
                </p>
                <ul class="list-disc list-inside mb-6 text-gray-700">
                    <li>توفير وصيانة خدمات التطبيق.</li>
                    <li>تحسين وتخصيص تجربة المستخدم.</li>
                    <li>التواصل معك (على سبيل المثال، لإرسال التحديثات والإشعارات الهامة).</li>
                    <li>اكتشاف ومنع المشاكل التقنية أو الأمنية.</li>
                </ul>

                <h2 class="text-2xl font-bold text-gray-900 mb-4 border-b pb-2">4. مشاركة المعلومات مع أطراف ثالثة</h2>
                <p class="mb-6 text-gray-700 leading-relaxed">
                    نحن لا نقوم ببيع معلوماتك الشخصية لأي طرف ثالث. قد نشارك بعض البيانات مع مزودي خدمات موثوقين (مثل خدمات تحليلات Google أو Firebase) فقط لمساعدتنا في تحليل وتحسين أداء التطبيق، وتخضع هذه الأطراف لسياسات الخصوصية الخاصة بها.
                </p>

                <h2 class="text-2xl font-bold text-gray-900 mb-4 border-b pb-2">5. أمن البيانات</h2>
                <p class="mb-6 text-gray-700 leading-relaxed">
                    نحن نتخذ إجراءات تقنية وتنظيمية مناسبة لحماية معلوماتك من الوصول غير المصرح به أو التعديل أو الإفصاح. ومع ذلك، يرجى ملاحظة أنه لا توجد وسيلة نقل عبر الإنترنت أو طريقة تخزين إلكتروني آمنة بنسبة 100%.
                </p>

                <h2 class="text-2xl font-bold text-gray-900 mb-4 border-b pb-2">6. التغييرات على سياسة الخصوصية</h2>
                <p class="mb-6 text-gray-700 leading-relaxed">
                    قد نقوم بتحديث سياسة الخصوصية هذه من وقت لآخر. سنقوم بإعلامك بأي تغييرات جوهرية عن طريق نشر السياسة الجديدة على هذه الصفحة وتحديث "تاريخ آخر تحديث" في الأعلى. يُنصح بمراجعة هذه الصفحة بشكل دوري.
                </p>

                <h2 class="text-2xl font-bold text-gray-900 mb-4 border-b pb-2">7. اتصل بنا</h2>
                <p class="mb-6 text-gray-700 leading-relaxed">
                    إذا كان لديك أي أسئلة أو استفسارات حول سياسة الخصوصية هذه، يرجى التواصل معنا عبر:
                </p>
                {{-- <ul class="list-none mb-6 text-gray-700">
                    <li><strong>البريد الإلكتروني:</strong> <a href="mailto:support@yourdomain.com" class="text-blue-600 hover:underline">support@yourdomain.com</a></li>
                    <li><strong>الموقع الإلكتروني:</strong> <a href="https://yourdomain.com" class="text-blue-600 hover:underline">https://yourdomain.com</a></li>
                </ul> --}}

            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-gray-400 text-sm">
            &copy; {{ date('Y') }} [اسم شركتك أو مطور التطبيق]. جميع الحقوق محفوظة.
        </div>
    </div>

</body>
</html>
