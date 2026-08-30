<?php

namespace Modules\PPUDS\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Entities\User;
use Modules\PPUDS\Auth\KeycloakUserProvider;
use Modules\PPUDS\Enums\LoginMethod;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Settings\KeycloakSettings;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class PPUDSServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'PPUDS';

    protected string $nameLower = 'ppuds';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        // This for settings
        $this->loadMigrationsFrom(module_path('PPUDS', 'database/Settings'));

        Event::listen(
            SocialiteWasCalled::class,
            [KeycloakExtendSocialite::class, 'handle']
        );

        if (! $this->app->runningInConsole()) {
            try {
                $this->applyKeycloakSettings();

                $settings = app(GeneralSettings::class);

                if ($settings->login_method === LoginMethod::PPU) {
                    Config::set('auth.providers.keycloak_users', [
                        'driver' => 'keycloak_users',
                        'model' => config('auth.providers.users.model', User::class),
                    ]);
                    Config::set('auth.guards.api.driver', 'keycloak');
                    Config::set('auth.guards.api.provider', 'keycloak_users');
                    Config::set('keycloak.user_provider_custom_retrieve_method', 'retrieveByKeycloakToken');
                    Config::set('keycloak.user_provider_credential', 'username');
                    Config::set('keycloak.token_principal_attribute', 'preferred_username');
                }
            } catch (\Exception $e) {
                Log::error('Failed to load PPUDS general settings: '.$e->getMessage());
            }
        }
    }

    /**
     * Lets the settings screen override the realm's .env configuration.
     *
     * Anything left blank on that screen keeps its .env value, so an install
     * that has never opened the screen behaves exactly as before. Failures
     * are swallowed on purpose: a missing settings table must not take the
     * whole application down before the migration has run.
     */
    private function applyKeycloakSettings(): void
    {
        try {
            $keycloak = app(KeycloakSettings::class);
        } catch (\Throwable $e) {
            Log::warning('Keycloak settings unavailable, falling back to .env: '.$e->getMessage());

            return;
        }

        Config::set('services.keycloak.base_url', $keycloak->valueOr('base_url', config('services.keycloak.base_url')));
        Config::set('services.keycloak.realms', $keycloak->valueOr('realm', config('services.keycloak.realms')));
        Config::set('services.keycloak.client_id', $keycloak->valueOr('client_id', config('services.keycloak.client_id')));
        Config::set('services.keycloak.client_secret', $keycloak->valueOr('client_secret', config('services.keycloak.client_secret')));
        Config::set('services.keycloak.redirect', $keycloak->valueOr('redirect_uri', config('services.keycloak.redirect')));
        Config::set('services.keycloak.mobile_client_id', $keycloak->valueOr('mobile_client_id', config('services.keycloak.mobile_client_id')));
        Config::set('services.keycloak.mobile_client_secret', $keycloak->valueOr('mobile_client_secret', config('services.keycloak.mobile_client_secret')));
        Config::set('services.keycloak.api_client_id', $keycloak->valueOr('api_client_id', config('services.keycloak.api_client_id')));
        Config::set('services.keycloak.password_grant_scope', $keycloak->valueOr('password_grant_scope', config('services.keycloak.password_grant_scope')));

        // Derived from base_url + realm rather than stored, so they can never
        // drift out of step with the realm they describe.
        Config::set('services.keycloak.issuer', $keycloak->issuer() ?? config('services.keycloak.issuer'));
        Config::set('services.keycloak.jwks_url', $keycloak->jwksUrl() ?? config('services.keycloak.jwks_url'));

        // The token guard reads from its own config file.
        Config::set('keycloak.realm_public_key', $keycloak->valueOr('realm_public_key', config('keycloak.realm_public_key')));
        Config::set('keycloak.allowed_resources', $keycloak->valueOr('allowed_resources', config('keycloak.allowed_resources')));
    }

    /**
     * Registration the service provider.
     */
    public function register(): void
    {
        $this->registerKeycloakUserProvider();

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(SidebarServiceProvider::class);
    }

    /**
     * Registration commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Registration command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Registration translations.
     */
    public function registerTranslations(): void
    {
        $langPath = module_path($this->name, 'Lang');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, ''), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, ''));
        }
    }

    /**
     * Registration config.
     */
    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $config = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $config_key = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
                    $segments = explode('.', $this->nameLower.'.'.$config_key);

                    // Remove duplicated adjacent segments
                    $normalized = [];
                    foreach ($segments as $segment) {
                        if (end($normalized) !== $segment) {
                            $normalized[] = $segment;
                        }
                    }

                    $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

                    $this->publishes([$file->getPathname() => config_path($config)], 'config');
                    $this->merge_config_from($file->getPathname(), $key);
                }
            }
        }
    }

    /**
     * Merge config from the given path recursively.
     */
    protected function merge_config_from(string $path, string $key): void
    {
        $existing = config($key, []);
        $module_config = require $path;

        config([$key => array_replace_recursive($existing, $module_config)]);
    }

    /**
     * Registration views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        Blade::componentNamespace(config('modules.namespace').'\\'.$this->name.'\\View\\Components', $this->nameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function registerKeycloakUserProvider(): void
    {
        Auth::provider('keycloak_users', function (Application $app, array $config) {
            return new KeycloakUserProvider($app['hash'], $config['model']);
        });
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }
}
