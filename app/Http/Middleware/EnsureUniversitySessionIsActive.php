<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\PPUDS\Services\PpuApiService;
use Symfony\Component\HttpFoundation\Response;

class EnsureUniversitySessionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->session()->get('keycloak_access_token');

        if (! $accessToken || ! Auth::check()) {
            return $next($request);
        }

        $ppuApi = app(PpuApiService::class);

        if (! $ppuApi->isTokenExpired($accessToken)) {
            return $next($request);
        }

        try {
            // يجدد التوكن ما دامت جلسة الجامعة قائمة، ويحفظ الزوج الجديد في الجلسة.
            $ppuApi->refreshAccessToken($request->session()->get('keycloak_refresh_token'), Auth::id());
        } catch (ConnectionException) {
            // تعذر الوصول إلى نظام الجامعة لا يعني انتهاء الجلسة.
            return $next($request);
        } catch (\Exception) {
            // انتهت جلسة الجامعة، فتنتهي جلسة النظام معها.
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // طلبات Livewire تتم عبر fetch فلا تستطيع اتباع التحويل إلى صفحة دخول الجامعة،
            // و419 يجعل Livewire يعرض رسالة انتهاء الصفحة ثم يعيد تحميلها.
            if ($request->hasHeader('X-Livewire')) {
                abort(419);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
