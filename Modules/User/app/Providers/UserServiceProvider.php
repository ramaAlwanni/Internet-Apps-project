<?php

namespace Modules\User\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;


class UserServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'User';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'user';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }

     /**
     * Bootstrap services for the User Module.
     */
    public function boot(): void
    {
        parent::boot();
        $this->loadRoutesFrom(module_path('User', 'routes/api.php'));
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiters for user authentication.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input('email')));
            $ip = $request->ip();

            return [
                Limit::perMinutes(15, 5)
                    ->by('login:'.$email.'|'.$ip)
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Too many login attempts. Please try again later.',
                        ], 429, $headers);
                    }),

                Limit::perMinute(30)
                    ->by('login-ip:'.$ip),

                Limit::perHour(40)
                    ->by('login-account:'.$email),
            ];
        });
    }
}
