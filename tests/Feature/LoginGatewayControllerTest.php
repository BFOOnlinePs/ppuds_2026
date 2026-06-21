<?php

namespace Tests\Feature;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Http\Controllers\LoginGatewayController;
use Modules\PPUDS\Settings\GeneralSettings;
use Tests\TestCase;

class LoginGatewayControllerTest extends TestCase
{
    public function test_ppu_login_gateway_displays_flashed_errors_instead_of_redirecting_to_keycloak(): void
    {
        $this->bindPpuLoginSettings();

        $request = Request::create('/login', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'auth' => ['لا يمكنك الدخول لحسابك مؤقتا يرجى مراجعة مركز الحاسوب رمز الخطا role-19'],
        ])));

        $response = app(LoginGatewayController::class)($request);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('auth.login', $response->name());
    }

    public function test_ppu_login_gateway_redirects_to_keycloak_when_there_are_no_errors(): void
    {
        $this->bindPpuLoginSettings();

        $request = Request::create('/login', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        $response = app(LoginGatewayController::class)($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('keycloak.redirect'), $response->getTargetUrl());
    }

    private function bindPpuLoginSettings(): void
    {
        $this->app->instance(GeneralSettings::class, (object) [
            'login_method' => LoginMethod::PPU,
        ]);
    }
}
