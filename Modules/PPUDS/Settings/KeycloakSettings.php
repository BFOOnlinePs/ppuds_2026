<?php

namespace Modules\PPUDS\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * The university's Keycloak realm, editable from the settings screen instead
 * of .env, so the realm can be repointed without a redeploy.
 *
 * Every value falls back to its .env counterpart while it is left blank, so
 * an installation that has not filled the screen in keeps working exactly as
 * it did before.
 *
 * NOTE: these properties carry no docblocks on purpose. Spatie's
 * PropertyReflector stops reading the native type once a property has one,
 * and returns no type unless that docblock has a @var line — which leaves the
 * property uncast. See GeneralSettings for the same warning.
 */
class KeycloakSettings extends Settings
{
    // e.g. https://midad.ppu.edu
    public string $base_url;

    // e.g. PPU
    public string $realm;

    // Browser sign-in (Socialite) client.
    public string $client_id;

    public string $client_secret;

    public string $redirect_uri;

    // The Flutter app's own realm client, used for the password grant.
    public string $mobile_client_id;

    // That client's own secret — not the web client's.
    public string $mobile_client_secret;

    // Audience check: the client the token must be issued for.
    public string $api_client_id;

    // RS256 public key from Realm Settings -> Keys. Without it the guard
    // cannot verify tokens, so every authenticated API call fails.
    public string $realm_public_key;

    public string $allowed_resources;

    public string $password_grant_scope;

    public static function group(): string
    {
        return 'ppuds_keycloak';
    }

    /** Blank means "keep using .env", so callers need the fallback everywhere. */
    public function valueOr(string $property, mixed $fallback): mixed
    {
        $value = trim((string) ($this->{$property} ?? ''));

        return $value !== '' ? $value : $fallback;
    }

    /**
     * Always `{base_url}/realms/{realm}` — deriving it removes a field that
     * could silently disagree with the other two.
     */
    public function issuer(): ?string
    {
        $baseUrl = rtrim((string) $this->valueOr('base_url', config('services.keycloak.base_url')), '/');
        $realm = $this->valueOr('realm', config('services.keycloak.realms'));

        if ($baseUrl === '' || blank($realm)) {
            return null;
        }

        return "{$baseUrl}/realms/{$realm}";
    }

    public function jwksUrl(): ?string
    {
        $issuer = $this->issuer();

        return $issuer ? "{$issuer}/protocol/openid-connect/certs" : null;
    }
}
