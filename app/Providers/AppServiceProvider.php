<?php

namespace App\Providers;

use App\Actions\GrantStaffAccess;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Authentik\Provider as AuthentikProvider;
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
        $this->configureSocialite();
        $this->configureStaffAccess();
        $this->configureSecureUrls();
    }

    protected function configureSecureUrls(): void
    {
        // Determine if HTTPS should be enforced
        $enforceHttps = $this->app->environment(['production', 'staging'])
            && ! $this->app->runningUnitTests();

        // Force HTTPS for all generated URLs
        URL::forceHttps($enforceHttps);

        // Ensure proper server variable is set
        if ($enforceHttps) {
            request()->server->set('HTTPS', 'on');
        }
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

        Password::defaults(
            fn (): ?Password => app()->isProduction()
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
     * Take an account into the house the moment its address is proven to be
     * one of the theatre's own — however it came to be proven. This is the only
     * place the grant hangs off for accounts that sign up by hand: an address
     * nobody has answered mail at is not evidence of anything.
     */
    private function configureStaffAccess(): void
    {
        Event::listen(function (Verified $event) {
            // The event only promises somebody with a verifiable address; the
            // grant needs the application's own user.
            if ($event->user instanceof User) {
                app(GrantStaffAccess::class)->handle($event->user);
            }
        });
    }

    /**
     * Register the community Socialite provider for Authentik SSO.
     */
    private function configureSocialite(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('authentik', AuthentikProvider::class);
        });
    }
}
