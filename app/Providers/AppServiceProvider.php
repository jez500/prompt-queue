<?php

namespace App\Providers;

use App\Enums\SsoProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Authelia\Provider as AutheliaProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        $this->configureDefaults();
        $this->configureProxies();
        $this->configureSocialite();
    }

    /**
     * Trust the reverse proxy that terminates TLS.
     *
     * `TrustProxies` is already in the global middleware stack; it just has no
     * proxy list until something sets one. Without it the original scheme in
     * X-Forwarded-Proto is ignored, every generated URL comes out as http on a
     * page served over https, and the browser blocks the assets as mixed
     * content.
     *
     * This belongs here rather than in `bootstrap/app.php`: `config` is not
     * bound when the middleware closure runs there, and reading `env()`
     * instead would return null once the entrypoint has run `config:cache`.
     */
    protected function configureProxies(): void
    {
        TrustProxies::at(config('app.trusted_proxies'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register the community Socialite drivers.
     *
     * Unlike Socialite's built-in providers these are not auto-discovered —
     * without this listener `Socialite::driver('authelia')` throws.
     */
    protected function configureSocialite(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite(SsoProvider::Authelia->value, AutheliaProvider::class);
        });
    }
}
