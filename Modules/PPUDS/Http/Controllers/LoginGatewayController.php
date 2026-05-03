<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PPUDS\Settings\GeneralSettings;

class LoginGatewayController extends Controller
{
    public function __invoke()
    {
        $generalSettings = app(GeneralSettings::class);
        if ($generalSettings->login_method === \Modules\PPUDS\Enums\LoginMethod::PPU->value) {
            return redirect()->route('keycloak.redirect');
        }

        return view('auth.login');
    }
}
