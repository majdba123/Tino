<?php

namespace App\Console\Commands;

use App\Models\Subscribe;
use App\Models\User_Subscription;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckExpiredSubscribes extends Command
{
    protected $signature = 'subscribes:check-expired';
    protected $description = 'Update status of expired subscribes';

    public function handle()
    {
        $now = Carbon::now();

        // تحديث الاشتراكات التي انتهت صلاحيتها
        User_Subscription::where('is_active', '1')
                ->where('end_date', '<=', $now)
                ->update(['is_active' => 0]);

        $this->info('Expired subscribes have been updated successfully.');
    }
}
