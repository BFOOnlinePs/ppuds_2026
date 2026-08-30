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

];
