<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\SendOtpMail;
use App\Models\Subscribe;
use App\Models\User;

class checkActiveSubscription
{
    public static function checkActive($providerId)
    {
        $subscription = Subscribe::where('provider__service_id', $providerId)
                                    ->where('status', 'active')
                                    ->first();

            return $subscription ? true : false;
    }
}
