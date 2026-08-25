<?php

namespace App\Providers;

use App\Telegram\BotUsernameResolver;
use App\Telegram\ResilientPolling;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SergiX44\Nutgram\Nutgram;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->beforeResolving(Nutgram::class, function (): void {
            BotUsernameResolver::resolve();
        });

        $this->app->afterResolving(Nutgram::class, function (Nutgram $bot): void {
            if (app()->runningUnitTests() || ! app()->runningInConsole()) {
                return;
            }

            $bot->setRunningMode(ResilientPolling::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
}
