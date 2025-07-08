<?php

namespace App\Services;

use App\Models\DiscountCoupon;
use App\Models\Subscription;
use App\Models\User_Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
class UserSubscriptionService
{

public function subscribeUser($user, $subscriptionId, $discountCode = null)
{
    try {


        return DB::transaction(function () use ($user, $subscriptionId, $discountCode) {
            $subscription = Subscription::findOrFail($subscriptionId);

            if (!$subscription->is_active) {
                throw new \Exception('هذه الباقة غير متاحة حالياً');
            }

            // حساب السعر والخصم
            $originalPrice = $subscription->price;
            $finalPrice = $originalPrice;
            $discountApplied = null;

            if ($discountCode) {
                $discountCoupon = DiscountCoupon::where('code', $discountCode)
                    ->where('user_id', $user->id)
                    ->where('is_used', false)
                    ->where('expires_at', '>', now())
                    ->first();

                if ($discountCoupon) {
                    $discountAmount = $originalPrice * ($discountCoupon->discount_percent / 100);
                    $finalPrice = max(50, $originalPrice - $discountAmount); // الحد الأدنى 0.50 دولار
                    $discountApplied = [
                        'code' => $discountCoupon->code,
                        'percent' => $discountCoupon->discount_percent,
                        'amount' => $discountAmount
                    ];
                    $discountCoupon->update(['is_used' => true]);
                }
            }

            // إنشاء اشتراك مؤقت
            $userSubscription = User_Subscription::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'price_paid' => $finalPrice,
                'start_date' => now(),
                'end_date' => now()->addMonths($subscription->duration_months),
                'remaining_calls' => 0,
                'remaining_visits' => 0,
                'is_active' => User_Subscription::STATUS_PENDING,
                'payment_method' => 'stripe',
                'payment_status' => 'pending'
            ]);

            // تكوين Stripe
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

            // إنشاء جلسة الدفع
            $session =  $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $subscription->name,
                            'description' => $subscription->description,
                        ],
                        'unit_amount' => (int)($finalPrice * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => url("/api/payment/success/{$userSubscription->id}"),
                'cancel_url' => url("/api/payment/cancel/{$userSubscription->id}"),
                'customer_email' => $user->email,
                'metadata' => [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'user_subscription_id' => $userSubscription->id
                ]
            ]);

            // تحديث بيانات الاشتراك
            $userSubscription->update([
                'payment_session_id' => $session->id,
                'payment_details' => [
                    'session_id' => $session->id,
                    'payment_intent' => $session->payment_intent,
                    'payment_status' => $session->payment_status
                ]
            ]);

            // إنشاء سجل الدفع
            Payment::create([
                'user_id' => $user->id,
                'user_subscription_id' => $userSubscription->id,
                'payment_id' => $session->id,
                'amount' => $finalPrice,
                'currency' => 'usd',
                'payment_method' => 'stripe',
                'status' => 'pending',
                'details' => $session->toArray()
            ]);

            return [
                'success' => true,
                'payment_url' => $session->url,
                'subscription' => $userSubscription,
                'discount' => $discountApplied,
                'message' => 'ssss'

            ];

        });
    } catch (\Illuminate\Database\QueryException $e) {
        Log::error('Database Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'حدث خطأ في قاعدة البيانات',
            'error' => $e->getMessage()
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        Log::error('Stripe Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'حدث خطأ في نظام الدفع',
            'error' => $e->getMessage()
        ];
    } catch (\Exception $e) {
        Log::error('General Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'حدث خطأ غير متوقع',
            'error' => $e->getMessage()
        ];
    }
}


























































































   /* public function subscribeUser($user, $subscriptionId, $discountCode = null)
    {
        return DB::transaction(function () use ($user, $subscriptionId, $discountCode) {
            $subscription = Subscription::findOrFail($subscriptionId);

            // التحقق من أن الباقة نشطة
            if (!$subscription->is_active) {
                return [
                    'success' => false,
                    'message' => 'هذه الباقة غير متاحة حالياً'
                ];
            }

            // التحقق من وجود اشتراك فعال للمستخدم
            $activeSubscription = $this->getUserActiveSubscription($user->id);
            if ($activeSubscription) {
                return [
                    'success' => false,
                    'message' => 'لديك اشتراك فعال بالفعل'
                ];
            }

            $originalPrice = $subscription->price;
            $finalPrice = $originalPrice;
            $discountApplied = null;

            // إذا تم إرسال كود خصم
            if (!empty($discountCode)) {
                $discountCoupon = DiscountCoupon::where('code', $discountCode)
                    ->where('user_id', $user->id)
                    ->where('is_used', false)
                    ->where('expires_at', '>', now())
                    ->first();

                if ($discountCoupon) {
                    $discountAmount = $originalPrice * ($discountCoupon->discount_percent / 100);
                    $finalPrice = $originalPrice - $discountAmount;

                    // تحديث حالة الكوبون إلى مستخدم
                    $discountCoupon->update(['is_used' => true]);

                    $discountApplied = [
                        'coupon_code' => $discountCoupon->code,
                        'discount_percent' => $discountCoupon->discount_percent,
                        'discount_amount' => $discountAmount,
                        'original_price' => $originalPrice,
                        'final_price' => $finalPrice
                    ];
                }
                // إذا كان الكود غير صالح، نستمر بدون خصم ولا نعيد خطأ
            }

            // حساب تاريخ الانتهاء بناءً على مدة الباقة
            $startDate = Carbon::now();
            $endDate = $startDate->copy()->addMonths($subscription->duration_months);

            // إنشاء اشتراك جديد
            $userSubscription = User_Subscription::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'remaining_calls' => 0,
                'remaining_visits' => 0,
                'is_active' => true,
                'price_paid' => $finalPrice
            ]);

            $response = [
                'success' => true,
                'message' => 'تم تفعيل الاشتراك بنجاح',
                'data' => [
                    'subscription' => $userSubscription,
                    'original_price' => $originalPrice,
                    'final_price' => $finalPrice,
                ]
            ];

            if ($discountApplied) {
                $response['data']['discount_applied'] = $discountApplied;
                $response['message'] = 'تم تفعيل الاشتراك بنجاح مع تطبيق الخصم';
            }

            return $response;
        });
    }*/

    protected function deactivatePreviousSubscriptions($user)
    {
        User_Subscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }


    public function getUserActiveSubscription($userId)
    {
        return User_Subscription::with('subscription')
            ->where('user_id', $userId)
            ->active()
            ->first();
    }
}
