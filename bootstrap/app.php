<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Core\Traits\ApiResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\CheckCompanySupervisorsUniversityRegistration::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(
            fn (\Illuminate\Http\Request $request): string => app(\Modules\PPUDS\Settings\GeneralSettings::class)->login_method === \Modules\PPUDS\Enums\LoginMethod::PPU
                ? route('keycloak.redirect')
                : route('login')
        );

        $middleware->alias([
            /**** OTHER MIDDLEWARE ALIASES ****/
            'localize'                => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localizationRedirect'    => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect'   => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localeCookieRedirect'    => \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            'localeViewPath'          => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,

            'api.localize'            =>\Modules\Core\Http\Middleware\ApiLocalization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {

            // تطبيق هذه الهيكلية فقط على مسارات الـ API لعدم كسر واجهات الويب (Blade/Livewire)
            if (($request->expectsJson() || $request->is('api/*')) && ! $request->routeIs('l5-swagger.*')) {

                $options = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

                // 1. أخطاء المصادقة (Authentication - 401)
                if ($e instanceof \KeycloakGuard\Exceptions\TokenException ||
                    $e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Unauthenticated.', // أو 'انتهت الجلسة'
                        'data'    => null
                    ], 401, [], $options);
                }

                // 2. أخطاء الصلاحيات (Authorization/Permissions - 403)
                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException ||
                    $e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'This action is unauthorized.',
                        'data'    => null
                    ], 403, [], $options);
                }

                // 3. أخطاء التحقق من البيانات (Validation - 422)
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Validation Error',
                        'data'    => $e->errors() // إرجاع مصفوفة الأخطاء هنا
                    ], 422, [], $options);
                }

                // 4. أخطاء عدم وجود البيانات أو الروابط (Not Found - 404)
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ||
                    $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Resource not found.',
                        'data'    => null
                    ], 404, [], $options);
                }

                // 5. أي أخطاء أخرى بالسيرفر (Server Error - 500)
                // في بيئة التطوير (local) نعرض تفاصيل الخطأ الحقيقي، وفي الإنتاج (production) نعرض رسالة عامة
                return response()->json([
                    'status'  => false,
                    'message' => config('app.debug') ? $e->getMessage() : 'Server Error.',
                    'data'    => config('app.debug') ? [
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ] : null
                ], 500, [], $options);
            }

        });
    })->create();
