<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\AccessTokens;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->configureJwtGuard();
        $this->configureRateLimiting();
    }

    /**
     * The `api` guard authenticates `Authorization: Bearer <jwt>` and remembers the token's device.
     */
    private function configureJwtGuard(): void
    {
        Auth::viaRequest('jwt', function (Request $request) {
            $token = $request->bearerToken();
            $claims = $token ? app(AccessTokens::class)->decode($token) : null;

            if ($claims === null) {
                return null;
            }

            $request->attributes->set('token_device_id', $claims['device_id']);

            return User::find($claims['sub']);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $email = $request->user('api')?->email ?? $request->input('email');

            return Limit::perMinute(5)->by($request->ip().'|'.strtolower(is_string($email) ? $email : ''));
        });

        RateLimiter::for('refresh', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(60)->by((string) $request->user('api')?->getKey()));
    }
}
