<?php

namespace Modules\Core\Listeners;

use Illuminate\Auth\Events\Login;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Starts every web sign-in in the default language (config app.locale).
 *
 * The locale lives in the session and in the URL prefix, so both are reset:
 * otherwise an old `/en/...` intended URL, or a locale left in the session,
 * would carry the previous language into the new sign-in.
 */
class SetDefaultLocaleOnLogin
{
    public function handle(Login $event): void
    {
        $request = request();

        // API sign-ins have no session; their language comes from Accept-Language.
        if (! $request->hasSession()) {
            return;
        }

        $locale = config('app.locale');
        $session = $request->session();

        $session->put('locale', $locale);

        $intended = $session->get('url.intended');

        if (! is_string($intended)) {
            return;
        }

        $firstSegment = explode('/', trim((string) parse_url($intended, PHP_URL_PATH), '/'))[0];

        if ($firstSegment !== $locale && LaravelLocalization::checkLocaleInSupportedLocales($firstSegment)) {
            $session->put('url.intended', LaravelLocalization::getLocalizedURL($locale, $intended, [], true));
        }
    }
}
