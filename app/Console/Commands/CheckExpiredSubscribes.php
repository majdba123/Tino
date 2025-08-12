<?php

namespace App\Console\Commands;

use App\Models\User_Subscription;
use App\Models\Payment;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class CheckExpiredSubscribes extends Command
{
    protected $signature = 'subscribes:check-expired';
    protected $description = 'Update status of expired subscriptions and renew if auto_renew is enabled';

    public function handle()
    {
        Log::info('بدء عملية فحص الاشتراكات المنتهية');

        $now = Carbon::now();
        $stripe = new StripeClient(env('STRIPE_SECRET'));

        // البحث عن الاشتراكات النشطة المنتهية
        $expiredSubscriptions = User_Subscription::where('is_active', User_Subscription::STATUS_ACTIVE)
            ->where('end_date', '<=', $now)
            ->with(['user', 'subscription'])
            ->get();

        if ($expiredSubscriptions->isEmpty()) {
            Log::info('لا توجد اشتراكات منتهية الصلاحية');
            return;
        }

        foreach ($expiredSubscriptions as $subscription) {
            try {
                $user = $subscription->user;
                Log::info("معالجة اشتراك منتهي - المستخدم: {$user->id}, الاشتراك: {$subscription->id}");

                // تعطيل الاشتراك الحالي
                $subscription->update(['is_active' => false]);
                Log::info("تم تعطيل الاشتراك: {$subscription->id}");

                // التحقق من إمكانية التجديد التلقائي
                if ($this->canAutoRenew($subscription, $user)) {
                    $this->renewSubscription($stripe, $subscription, $user, $now);
                }
            } catch (\Exception $e) {
                Log::error("خطأ أثناء معالجة الاشتراك {$subscription->id}: " . $e->getMessage());
            }
        }

        Log::info('تم الانتهاء من عملية الفحص');
    }

    protected function canAutoRenew($subscription, $user): bool
    {
        if (!$subscription->auto_renew) {
            Log::info("التجديد التلقائي غير مفعل للاشتراك: {$subscription->id}");
            return false;
        }

        if (!$user->stripe_customer_id || !$user->stripe_payment_method_id) {
            Log::warning("بيانات Stripe ناقصة للمستخدم: {$user->id}");
            return false;
        }

        return true;
    }

    protected function renewSubscription($stripe, $subscription, $user, $now)
    {
        try {
            Log::info("محاولة تجديد الاشتراك للمستخدم: {$user->id}");

            // التحقق من طريقة الدفع
            $paymentMethod = $stripe->paymentMethods->retrieve($user->stripe_payment_method_id);

            // إنشاء عملية دفع جديدة
            $intent = $stripe->paymentIntents->create([
                'amount' => (int)($subscription->price_paid * 100),
                'currency' => 'usd',
                'customer' => $user->stripe_customer_id,
                'payment_method' => $user->stripe_payment_method_id,
                'off_session' => true,
                'confirm' => true,
                'description' => 'تجديد تلقائي للاشتراك',
                'metadata' => [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->subscription_id,
                    'previous_subscription_id' => $subscription->id
                ]
            ]);

            // إنشاء اشتراك جديد
            $newSubscription = User_Subscription::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->subscription_id,
                'price_paid' => $subscription->price_paid,
                'start_date' => $now,
                'end_date' => $now->copy()->addMonths($subscription->subscription->duration_months),
                'is_active' => User_Subscription::STATUS_ACTIVE,
                'payment_method' => 'stripe',
                'payment_status' => 'paid',
                'payment_session_id' => $intent->id,
                'payment_details' => $intent->toArray(),
                'pet_id' => $subscription->pet_id,
                'auto_renew' => true
            ]);

            // تسجيل الدفع
            Payment::create([
                'user_id' => $user->id,
                'user_subscription_id' => $newSubscription->id,
                'payment_id' => $intent->id,
                'amount' => $subscription->price_paid,
                'currency' => 'usd',
                'payment_method' => 'stripe',
                'status' => 'paid',
                'details' => $intent->toArray()
            ]);

            Log::info("تم التجديد بنجاح - اشتراك جديد: {$newSubscription->id}");

        } catch (\Stripe\Exception\CardException $e) {
            Log::error("فشل في الدفع: " . $e->getMessage());
            $subscription->update(['auto_renew' => false]);
        } catch (\Exception $e) {
            Log::error("خطأ غير متوقع: " . $e->getMessage());
            $subscription->update(['auto_renew' => false]);
        }
    }
}
