<?php

namespace Database\Seeders;

use App\Models\User_Subscription;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class UserSubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        User_Subscription::create([
            'user_id' => 2, // ID المستخدم الإداري
            'subscription_id' => 1, // افترضنا أن لديك باقة اشتراك بـ ID 1
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addYear(), // اشتراك لمدة سنة
            'remaining_calls' => 0, // عدد المكالمات المتبقية
            'remaining_visits' => 0, // عدد الزيارات المتبقية
            'price_paid' => 999.99, // السعر المدفوع
            'is_active' => true,
            'payment_method' => 'stripe', // اشتراك لمدة سنة
            'payment_status' => 'paid', // عدد المكالمات المتبقية
            'payment_session_id' => "wqdqdqw", // عدد الزيارات المتبقية
        ]);
    }
}
