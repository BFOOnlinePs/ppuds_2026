<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'keycloak' => [
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realms' => env('KEYCLOAK_REALM'),

        // --- الإضافات الجديدة المطلوبة للفلاتر والـ API ---

        // رابط الـ JWKS لجلب مفاتيح التحقق من التوكن تلقائياً
        'jwks_url' => env('KEYCLOAK_JWKS_URL'),

        // الـ Client ID الخاص بالـ API لضمان أن التوكن موجه لنظامك (Audience)
        'api_client_id' => env('KEYCLOAK_API_CLIENT_ID'),
        'issuer'        => env('KEYCLOAK_ISSUER'),

        // --- تسجيل دخول تطبيق الهاتف عبر الجامعة (password grant) ---

        // عميل تطبيق Flutter، وهو غير عميل الويب أعلاه وله سرّه الخاص
        'mobile_client_id' => env('KEYCLOAK_MOBILE_CLIENT_ID', 'dualstudies-flutter-app'),
        'mobile_client_secret' => env('KEYCLOAK_MOBILE_CLIENT_SECRET'),

        // offline_access مطلوب ليعيد الـ realm توكن تحديث للتطبيق
        'password_grant_scope' => env('KEYCLOAK_PASSWORD_GRANT_SCOPE', 'openid profile offline_access'),

        'timeout' => env('KEYCLOAK_TIMEOUT', 20),
    ],

    'ppu_api' => [
        'base_url' => env('PPU_API_BASE_URL', 'https://api-core.ppu.edu'),
        'access_token' => env('PPU_API_ACCESS_TOKEN'),
    ],

    /*
     * خوادم الخرائط المستضافة ذاتياً. الخوادم الثلاثة تعمل على HTTP فقط (لا شهادة
     * TLS على الـ IP)، والتطبيق يُقدَّم عبر HTTPS، فيحجب المتصفح أي طلب إليها
     * مباشرةً باعتباره mixed content وتظهر الخريطة فارغة. لذلك تُطلب البلاطات من
     * مسار التطبيق نفسه (tiles_url) وهو يمررها إلى tile_server من جهة الخادم.
     * إذا صار للخادم اسم نطاق وشهادة TLS، يكفي ضبط MAP_TILES_URL ليشير إليه مباشرة.
     */
    'map' => [
        'tile_server' => env('MAP_TILE_SERVER', 'http://31.97.217.130:8080'),
        'nominatim_url' => env('MAP_NOMINATIM_URL', 'http://31.97.217.130:8081'),
        'osrm_url' => env('MAP_OSRM_URL', 'http://31.97.217.130:5000'),

        // العنوان الذي تستهلكه Leaflet: نسبي ⇒ نفس أصل الصفحة ⇒ لا mixed content
        'tiles_url' => env('MAP_TILES_URL', '/map/tile/{z}/{x}/{y}.png'),

        /*
         * يُستخدم فقط عند فشل البلاطة (tileerror) في حقل اختيار الموقع، وهو معطّل
         * افتراضياً: كان يشير إلى basemaps.cartocdn.com، وCARTO صارت تطبع عبارة
         * "API KEY REQUIRED" على كل بلاطة لمن يستهلكها بلا مفتاح، فكان خطأ بلاطة
         * واحدة عابر يقلب الخريطة كلها إلى بلاطات مختومة بهذه العبارة. الفارغ هنا
         * يجعل الخريطة تبقى على خادم البلاطات الذاتي وتعيد المحاولة كالمعتاد.
         * أي بديل يوضع هنا يجب ألا يتطلب مفتاحاً وأن يُضبط معه نص الإسناد.
         */
        'fallback_tiles_url' => env('MAP_FALLBACK_TILES_URL', ''),

        'timeout' => env('MAP_TILE_TIMEOUT', 10),
        'cache_ttl' => env('MAP_TILE_CACHE_TTL', 604800),
    ],

];
