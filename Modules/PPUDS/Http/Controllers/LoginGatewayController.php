<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PPUDS\Enums\LoginMethod;
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

        return view('auth.login');
    }
}
