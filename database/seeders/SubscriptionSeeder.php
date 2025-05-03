<?php

namespace Database\Seeders;

use App\Models\Subscription;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run()
    {
        // مسح البيانات السابقة إذا وجدت
        Subscription::query()->delete();

        // إنشاء الاشتراكات الأساسية
        $subscriptions = [
            [
                'name' => 'الاشتراك الأساسي',
                'slug' => 'basic-subscription',
                'description' => 'استشارة هاتفية فقط',
                'price' => 100.00,
                'type' => "basic",
                'duration_months' => 2,
                'is_active' => true
            ],
            [
                'name' => 'الاشتراك المميز',
                'slug' => 'premium-subscription',
                'description' => 'استشارة هاتفية + زيارة عيادة (يشمل خصمًا على الزيارات)',
                'price' => 250.00,
                'type' => "premium",
                'duration_months' => 2,
                'is_active' => true
            ]
        ];

        foreach ($subscriptions as $subscription) {
            Subscription::create($subscription);
        }

        $this->command->info('تم إنشاء اشتراكات العيادة بنجاح!');
    }
}
