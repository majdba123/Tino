<?php
namespace App\Console\Commands;

use App\Models\User_Subscription;
use App\Models\Payment;
use App\Models\Subscription;
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
                if ($this->canAutoRenew($subscription)) {
                    $this->renewSubscription($stripe, $subscription, $user, $now);
                }
            } catch (\Exception $e) {
                Log::error("خطأ أثناء معالجة الاشتراك {$subscription->id}: " . $e->getMessage(), ['exception' => $e]);
            }
        }

        Log::info('تم الانتهاء من عملية الفحص');
    }

    protected function canAutoRenew($subscription): bool
    {
        if (!$subscription->auto_renew) {
            Log::info("التجديد التلقائي غير مفعل للاشتراك: {$subscription->id}");
            return false;
        }

        if (!$subscription->stripe_customer_id || !$subscription->stripe_payment_method_id) {
            Log::warning("بيانات Stripe ناقصة للاشتراك: {$subscription->id}", [
                'stripe_customer_id' => $subscription->stripe_customer_id,
                'stripe_payment_method_id' => $subscription->stripe_payment_method_id
            ]);
            return false;
        }

        // التحقق من أن الباقة لا تزال متاحة
        if (!$subscription->subscription || !$subscription->subscription->is_active) {
            Log::warning("الباقة غير متاحة للاشتراك: {$subscription->id}");
            return false;
        }

        return true;
    }

    protected function renewSubscription($stripe, $subscription, $user, $now)
    {
        try {
            Log::info("محاولة تجديد الاشتراك للمستخدم: {$user->id}, الاشتراك: {$subscription->id}");

            // إنشاء عملية دفع جديدة باستخدام PaymentMethod الموجود
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => (int)($subscription->price_paid * 100),
                'currency' => 'usd',
                'customer' => $subscription->stripe_customer_id,
                'payment_method' => $subscription->stripe_payment_method_id,
                'off_session' => true,
                'confirm' => true,
                'description' => 'تجديد تلقائي للاشتراك',
                'metadata' => [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->subscription_id,
                    'previous_subscription_id' => $subscription->id,
                    'pet_id' => $subscription->pet_id
                ]
            ]);

            // التحقق من نجاح الدفع
            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception("حالة الدفع غير ناجحة: {$paymentIntent->status}");
            }

            // الحصول على عدد الزيارات والمكالمات المسموح بها من الباقة
            $subscriptionPlan = $subscription->subscription;
            $remainingCalls = $subscriptionPlan->calls_limit ?? 0;
            $remainingVisits = $subscriptionPlan->visits_limit ?? 0;

            // إنشاء اشتراك جديد بنفس بيانات الاشتراك القديم
            $newSubscription = User_Subscription::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->subscription_id,
                'price_paid' => $subscription->price_paid,
                'start_date' => $now,
                'end_date' => $now->copy()->addMonths($subscriptionPlan->duration_months),
                'remaining_calls' => $remainingCalls,
                'remaining_visits' => $remainingVisits,
                'is_active' => User_Subscription::STATUS_ACTIVE,
                'payment_method' => 'stripe',
                'payment_status' => 'paid',
                'payment_session_id' => $paymentIntent->id,
                'payment_details' => $paymentIntent->toArray(),
                'pet_id' => $subscription->pet_id,
                'auto_renew' => true,
                'stripe_customer_id' => $subscription->stripe_customer_id,
                'stripe_payment_method_id' => $subscription->stripe_payment_method_id
            ]);

            // تسجيل الدفع
            Payment::create([
                'user_id' => $user->id,
                'user_subscription_id' => $newSubscription->id,
                'payment_id' => $paymentIntent->id,
                'amount' => $subscription->price_paid,
                'currency' => 'usd',
                'payment_method' => 'stripe',
                'status' => 'paid',
                'details' => $paymentIntent->toArray()
            ]);

            Log::info("تم التجديد بنجاح - اشتراك جديد: {$newSubscription->id}");

            // إرسال إشعار للمستخدم

        } catch (\Stripe\Exception\CardException $e) {
            Log::error("فشل في الدفع للاشتراك {$subscription->id}: " . $e->getMessage(), ['exception' => $e]);
            $subscription->update(['auto_renew' => false]);
        } catch (\Exception $e) {
            Log::error("خطأ غير متوقع أثناء تجديد الاشتراك {$subscription->id}: " . $e->getMessage(), ['exception' => $e]);
            $subscription->update(['auto_renew' => false]);
        }
    }


}
