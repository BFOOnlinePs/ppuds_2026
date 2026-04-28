<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    App\Providers\Wirechat\ChatsPanelProvider::class,
    KeycloakGuard\KeycloakGuardServiceProvider::class,
];
