<?php

namespace App\Services;

use App\Models\DiscountCoupon;
use App\Models\Subscription;
use App\Models\User_Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserSubscriptionService
{
    public function subscribeUser($user, $subscriptionId, $discountCode = null)
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
    }

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
