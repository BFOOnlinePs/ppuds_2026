<?php

namespace Modules\PPUDS\Actions;

use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as KeycloakUser;
use Modules\Core\Entities\User;

class AuthenticateViaKeycloakAction
{
    public function handle() {}

    public function execute(KeycloakUser $keycloakUser) : User
    {
        return DB::transaction(function () use ($keycloakUser) {
            $user = User::updateOrCreate(

            );
        });
    }
}
