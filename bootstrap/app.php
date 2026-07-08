<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        \App\Console\Commands\FillBranchLocationsFromStudentAttendance::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(
            fn (Request $request): string => app(\Modules\PPUDS\Settings\GeneralSettings::class)->login_method === \Modules\PPUDS\Enums\LoginMethod::PPU
                ? route('keycloak.redirect')
                : route('login')
        );

        $middleware->alias([
            /**** OTHER MIDDLEWARE ALIASES ****/
            'localize'              => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localizationRedirect'  => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localeCookieRedirect'  => \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            'localeViewPath'        => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,

            'api.localize'          => \Modules\Core\Http\Middleware\ApiLocalization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! (($request->expectsJson() || $request->is('api/*')) && ! $request->routeIs('l5-swagger.*'))) {
                return null;
            }

            $options = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

            if ($e instanceof \KeycloakGuard\Exceptions\TokenException ||
                $e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Unauthenticated.',
                    'data'    => null
                ], 401, [], $options);
            }

            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException ||
                $e instanceof AccessDeniedHttpException) {
                return response()->json([
                    'status'  => false,
                    'message' => 'This action is unauthorized.',
                    'data'    => null
                ], 403, [], $options);
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Validation Error',
                    'data'    => $e->errors()
                ], 422, [], $options);
            }

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ||
                $e instanceof NotFoundHttpException) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Resource not found.',
                    'data'    => null
                ], 404, [], $options);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'status'  => false,
                    'message' => config('app.debug') ? $e->getMessage() : 'Method not allowed.',
                    'data'    => null
                ], 405, $e->getHeaders(), $options);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'status'  => false,
                    'message' => config('app.debug') ? $e->getMessage() : 'HTTP Error.',
                    'data'    => null
                ], $e->getStatusCode(), $e->getHeaders(), $options);
            }

            return response()->json([
                'status'  => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Server Error.',
                'data'    => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500, [], $options);
        });
    })->create();
