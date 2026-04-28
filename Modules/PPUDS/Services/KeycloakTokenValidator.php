<?php

namespace Modules\PPUDS\Services;

use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

class KeycloakTokenValidator
{
    public function validate(string $token): array
    {
        try {
            // 1. جلب مفاتيح التشفير العامة من الرابط الموجود في الـ .env
            $jwksUrl = config('services.keycloak.jwks_url');

            // نستخدم الـ Cache لتقليل الضغط على سيرفر الجامعة وسرعة الطلبات
            $jwks = cache()->remember('ppu_jwks_keys', 3600, function () use ($jwksUrl) {
                return Http::get($jwksUrl)->json();
            });

            // 2. فك التشفير والتحقق من التوقيع (Signature)
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
            $payload = json_decode(json_encode($decoded), true);

            // 3. التحقق من الـ Audience (aud)
            // نتأكد أن التوكن موجه فعلاً لمشروعنا (dualstudies-laravel-api)
            $expectedAud = config('services.keycloak.api_client_id');
            if (! isset($payload['aud']) || ! in_array($expectedAud, (array) $payload['aud'])) {
                throw new Exception('Token is not intended for this API audience.');
            }

            // 4. التحقق من الـ Issuer (iss)
            if ($payload['iss'] !== config('services.keycloak.issuer')) {
                throw new Exception('Invalid Issuer. Expected: '.config('services.keycloak.issuer').' but got: '.$payload['iss']);
            }

            return $payload;

        } catch (Exception $e) {
            throw new Exception('JWT Validation Failed: '.$e->getMessage());
        }
    }
}
