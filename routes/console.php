<?php

use Illuminate\Support\Facades\Schedule;
use Modules\Centres\Models\Centre;
use Modules\Spots\Models\Spot;
use Modules\Trails\Models\Trail;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('backup:database')->dailyAt('03:30')->withoutOverlapping(120);
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(10);
Schedule::command('model:prune', ['--model' => [Spot::class, Trail::class, Centre::class]])->dailyAt('04:00');
