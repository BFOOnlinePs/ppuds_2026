<?php

namespace Modules\PPUDS\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Http;
use Exception;

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
            $payload = (array) $decoded;

            // 3. التحقق من الـ Audience (aud)
            // نتأكد أن التوكن موجه فعلاً لمشروعنا (dualstudies-laravel-api)
            $expectedAud = config('services.keycloak.api_client_id');
            if (!isset($payload['aud']) || !in_array($expectedAud, (array)$payload['aud'])) {
                throw new Exception("Token is not intended for this API audience.");
            }

            // 4. التحقق من الـ Issuer (iss)
            if ($payload['iss'] !== config('services.keycloak.issuer')) {
                throw new Exception("Invalid Token Issuer.");
            }

            return $payload;

        } catch (Exception $e) {
            throw new Exception("JWT Validation Failed: " . $e->getMessage());
        }
    }
}
