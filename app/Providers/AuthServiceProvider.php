<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('eliminar-datos', fn (User $user): bool => $user->role === User::ROLE_ADMIN);
        Gate::define('configurar-reportes', fn (User $user): bool => in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OPERADOR], true));
    }
}
