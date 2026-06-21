<?php

namespace Tests\Unit;

use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as KeycloakUserContract;
use Modules\PPUDS\Actions\AuthenticateViaKeycloakAction;
use Tests\TestCase;

class AuthenticateViaKeycloakActionTest extends TestCase
{
    public function test_users_with_role_19_are_blocked_from_keycloak_login(): void
    {
        $keycloakUser = new class($this->jwt(['preferred_username' => '12345', 'realm_access' => ['roles' => ['offline_access', 'role-19']]])) implements KeycloakUserContract
        {
            public string $token;

            public ?string $refreshToken = null;

            public function __construct(string $token)
            {
                $this->token = $token;
            }

            public function getId()
            {
                return '12345';
            }

            public function getNickname()
            {
                return '12345';
            }

            public function getName()
            {
                return 'Test User';
            }

            public function getEmail()
            {
                return null;
            }

            public function getAvatar()
            {
                return null;
            }
        };

        try {
            (new AuthenticateViaKeycloakAction)->execute($keycloakUser);
            $this->fail('Expected users with role-19 to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'auth' => ['لا يمكنك الدخول لحسابك مؤقتا يرجى مراجعة مركز الحاسوب رمز الخطا role-19'],
            ], $exception->errors());
        }
    }

    private function jwt(array $payload): string
    {
        return implode('.', [
            $this->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT'])),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE)),
            '',
        ]);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
