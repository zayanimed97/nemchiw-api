<?php

namespace Modules\Chat;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Chat\Contracts\ConversationDirectory;
use Modules\Chat\Listeners\CloseFinishedTrip;
use Modules\Chat\Listeners\FollowRequest;
use Modules\Chat\Listeners\NoteTripEdit;
use Modules\Chat\Listeners\OpenConversation;
use Modules\Chat\Realtime\NullRealtime;
use Modules\Chat\Realtime\PusherRealtime;
use Modules\Chat\Realtime\Realtime;
use Modules\Chat\Services\EloquentConversationDirectory;
use Modules\Shared\Events\RequestChanged;
use Modules\Shared\Events\TripEdited;
use Modules\Shared\Events\TripFinished;
use Modules\Shared\Providers\ModuleServiceProvider;
use Pusher\Pusher;

class ChatServiceProvider extends ModuleServiceProvider
{
    protected function module(): string
    {
        return 'Chat';
    }

    public function register(): void
    {
        parent::register();
        $this->app->singleton(ConversationDirectory::class, EloquentConversationDirectory::class);
        $this->app->singleton(Realtime::class, function () {
            $config = (array) config('chat.realtime');
            if (empty($config['key']) || empty($config['secret']) || empty($config['app_id'])) {
                return new NullRealtime;
            }

            return new PusherRealtime(new Pusher(
                (string) $config['key'], (string) $config['secret'], (string) $config['app_id'],
                ['host' => (string) $config['host'], 'port' => 443, 'scheme' => 'https', 'useTLS' => true],
            ));
        });
    }

    public function boot(): void
    {
        parent::boot();
        // Order matters: the conversation exists before the system line and before Push reads it.
        Event::listen(RequestChanged::class, OpenConversation::class);
        Event::listen(RequestChanged::class, FollowRequest::class);
        Event::listen(TripEdited::class, NoteTripEdit::class);
        Event::listen(TripFinished::class, CloseFinishedTrip::class);

        RateLimiter::for('chat-messages', fn (Request $request) => Limit::perMinute((int) config('chat.messages_per_minute'))
            ->by('user:'.$request->user()?->getAuthIdentifier()));
    }
}
