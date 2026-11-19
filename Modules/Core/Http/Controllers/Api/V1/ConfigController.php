<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Entities\AppVersion;
use Modules\Core\Traits\ApiResponse;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Services\LoginOtpService;

class ConfigController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     * path="/api/v1/config",
     * summary="Get App Configuration",
     * description="Retrieve app versions, maintenance mode, and general settings for Android and iOS",
     * tags={"Config"},
     * @OA\Response(
     * response=200,
     * description="Config retrieved successfully",
     * @OA\JsonContent(
     * type="object",
     * @OA\Property(property="status", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Config retrieved successfully"),
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(
     * property="android",
     * type="object",
     * @OA\Property(property="min_version", type="string", example="1.0.0"),
     * @OA\Property(property="latest_version", type="string", example="1.2.0"),
     * @OA\Property(property="store_url", type="string", example="https://play.google.com/..."),
     * @OA\Property(property="maintenance_mode", type="boolean", example=false),
     * @OA\Property(property="message", type="string", example="Please update app")
     * ),
     * @OA\Property(
     * property="ios",
     * type="object",
     * @OA\Property(property="min_version", type="string", example="1.0.0"),
     * @OA\Property(property="latest_version", type="string", example="1.3.0"),
     * @OA\Property(property="store_url", type="string", example="https://apps.apple.com/..."),
     * @OA\Property(property="maintenance_mode", type="boolean", example=false),
     * @OA\Property(property="message", type="string", example="")
     * ),
     * @OA\Property(
     * property="contact",
     * type="object",
     * @OA\Property(property="support_email", type="string", example="support@domain.com"),
     * @OA\Property(property="whatsapp", type="string", example="+970599123456")
     * ),
     * @OA\Property(
     * property="auth",
     * type="object",
     * description="How the app signs in, as chosen in the dashboard — the same object as GET /api/v1/auth/options. Reads as password only while the university SSO is the login method.",
     * @OA\Property(property="mode", type="string", enum={"password","code","password_or_code","password_then_code"}, example="password"),
     * @OA\Property(property="password_login", type="boolean", example=true),
     * @OA\Property(property="code_login", type="boolean", example=false),
     * @OA\Property(property="two_step", type="boolean", example=false),
     * @OA\Property(property="company_qr_login", type="boolean", example=false),
     * @OA\Property(property="channels", type="array", @OA\Items(type="string", enum={"mail","sms"})),
     * @OA\Property(property="default_channel", type="string", nullable=true, example="mail"),
     * @OA\Property(property="channel_choice", type="boolean", example=false),
     * @OA\Property(property="code_length", type="integer", nullable=true, example=6)
     * )
     * )
     * )
     * )
     * )
     */
    public function index()
    {
        $config = Cache::rememberForever('app_bootstrap_config', function () {

            $versions = AppVersion::get()->keyBy('platform');

            return [
                'android' => $versions['android'] ?? null,
                'ios'     => $versions['ios'] ?? null,
            ];
        });

        // Read on every call rather than cached with the versions, so turning a
        // sign-in option on or off in the settings reaches the app at once.
        $config['auth'] = app(LoginOtpService::class)->availability(LoginClient::MOBILE);

        return $this->successResponse(
            $config,
            __('Config retrieved successfully')
        );
    }
}
