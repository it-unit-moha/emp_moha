<?php

namespace App\Providers;

use App\Models\Designation;
use App\Models\Division;
use App\Models\Unit;
use App\Models\User;
use App\Policies\MasterDataPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        foreach ([Division::class, Unit::class, Designation::class] as $model) {
            Gate::policy($model, MasterDataPolicy::class);
        }
        Gate::define('manage-users', fn (User $user) => $user->role->canManageUsers());
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
