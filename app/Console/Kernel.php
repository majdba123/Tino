<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {

        $schedule->command('subscribes:check-expired')
                 ->everyTwelveHours()
                 ->appendOutputTo(storage_path('logs/expired_discounts.log'));

    }

    protected $commands = [
        \App\Console\Commands\CheckExpiredSubscribes::class,
    ];
}



/**php artisan coupons:check-expired
php artisan subscribes:check-expired
php artisan discounts:check-expired
php artisan products:check-expired */
