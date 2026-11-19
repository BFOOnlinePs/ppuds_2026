<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Services\LoginOtpService;
use Modules\PPUDS\Settings\GeneralSettings;

class LoginGatewayController extends Controller
{
    public function __invoke(Request $request)
    {
        $generalSettings = app(GeneralSettings::class);

        // عند وجود خطأ من محاولة دخول الجامعة تُعرض صفحة الدخول برسالته، بدل
        // إعادة التحويل إلى الجامعة بصمت.
        if ($generalSettings->login_method === LoginMethod::PPU && ! $request->session()->has('errors')) {
            return redirect()->route('keycloak.redirect');
        }

        // حين تكون طريقة دخول لوحة التحكم "الرمز فقط" لا تُعرض كلمة المرور إطلاقاً: البريد ثم الرمز.
        if (! app(LoginOtpService::class)->passwordLoginAllowed(LoginClient::WEB)) {
            return redirect()->route('login.otp');
        }

        return view('auth.login');
    }
}
