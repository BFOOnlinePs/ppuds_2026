<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class ApiLocalization
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language');
        $supportedLocales = array_keys(LaravelLocalization::getSupportedLocales());

        if ($locale && in_array($locale, $supportedLocales)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
