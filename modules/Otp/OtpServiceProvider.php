<?php

namespace Modules\Otp;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Modules\Identity\Events\AccountDeleting;
use Modules\Otp\Actions\PruneChallenges;
use Modules\Otp\Channels\LogOtpSender;
use Modules\Otp\Channels\WhatsAppOtpSender;
use Modules\Otp\Contracts\OtpSender;
use Modules\Otp\Listeners\ForgetChallenges;
use Modules\Shared\Providers\ModuleServiceProvider;
use RuntimeException;

class OtpServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Otp';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(OtpSender::class, fn () => match (config('otp.channel')) {
            'whatsapp' => new WhatsAppOtpSender((array) config('otp.whatsapp')),
            // Fail when resolved, so a misconfigured production send is a visible
            // error on the request rather than a silent failure.
            'log' => $this->app->isProduction()
                ? throw new RuntimeException('OTP_CHANNEL=log is not allowed in production')
                : new LogOtpSender,
            default => throw new InvalidArgumentException('Unknown OTP_CHANNEL: '.config('otp.channel')),
        });
    }

    public function boot(): void
    {
        parent::boot();

        // Per phone and global caps live in SendOtp, after validation, so junk
        // requests cannot burn them. Here: per client IP only.
        RateLimiter::for('otp-ip', fn (Request $request) => Limit::perHour((int) config('otp.limits.per_ip_per_hour'))
            ->by('ip:'.$request->ip()));
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(10, (int) config('otp.limits.verify_per_ip_per_10_min'))
            ->by('ip:'.$request->ip()));
        RateLimiter::for('otp-attach', fn (Request $request) => Limit::perHour((int) config('otp.limits.per_user_attach_per_hour'))
            ->by('user:'.$request->user()?->getAuthIdentifier()));

        Event::listen(AccountDeleting::class, ForgetChallenges::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn () => app(PruneChallenges::class)())->hourly()->name('otp:prune')->withoutOverlapping();
        });
    }
}
