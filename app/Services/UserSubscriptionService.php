<?php

namespace App\Services;

use App\Models\Coupon;
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

/*public function subscribeUser($user, $subscriptionId, $discountCode = null, $paymentMethod = 'stripe', $pet_id)
{
    try {
        return DB::transaction(function () use ($user, $subscriptionId, $discountCode, $paymentMethod,$pet_id) {
            $subscription = Subscription::findOrFail($subscriptionId);

            if (!$subscription->is_active) {
                throw new \Exception('هذه الباقة غير متاحة حالياً');
            }

            // حساب السعر والخصم (يبقى كما هو)
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
                    $finalPrice = max(50, $originalPrice - $discountAmount);
                    $discountApplied = [
                        'code' => $discountCoupon->code,
                        'percent' => $discountCoupon->discount_percent,
                        'amount' => $discountAmount
                    ];
                    $discountCoupon->update(['is_used' => true]);
                }
            }

            // إنشاء اشتراك مؤقت (يبقى كما هو)
            $userSubscription = User_Subscription::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'price_paid' => $finalPrice,
                'start_date' => now(),
                'end_date' => now()->addMonths($subscription->duration_months),
                'remaining_calls' => 0,
                'remaining_visits' => 0,
                'is_active' => User_Subscription::STATUS_PENDING,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'pet_id' => $pet_id

            ]);

            if ($paymentMethod == 'stripe') {
                // تكوين Stripe (الكود الحالي)
                $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

                $session = $stripe->checkout->sessions->create([
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

                $paymentUrl = $session->url;
                $paymentSessionId = $session->id;
                $paymentDetails = [
                    'session_id' => $session->id,
                    'payment_intent' => $session->payment_intent,
                    'payment_status' => $session->payment_status
                ];
            } elseif ($paymentMethod === 'paypal') {
                // تكوين PayPal
                $apiContext = new \PayPal\Rest\ApiContext(
                    new \PayPal\Auth\OAuthTokenCredential(
                        env('PAYPAL_MODE') == 'sandbox' ? env('PAYPAL_SANDBOX_CLIENT_ID') : env('PAYPAL_LIVE_CLIENT_ID'),
                        env('PAYPAL_MODE') == 'sandbox' ? env('PAYPAL_SANDBOX_CLIENT_SECRET') : env('PAYPAL_LIVE_CLIENT_SECRET')
                    )
                );
                $apiContext->setConfig(['mode' => env('PAYPAL_MODE', 'sandbox')]);

                $payer = new \PayPal\Api\Payer();
                $payer->setPaymentMethod('paypal');

                $item = new \PayPal\Api\Item();
                $item->setName($subscription->name)
                    ->setCurrency('USD')
                    ->setQuantity(1)
                    ->setPrice($finalPrice);

                $itemList = new \PayPal\Api\ItemList();
                $itemList->setItems([$item]);

                $amount = new \PayPal\Api\Amount();
                $amount->setCurrency('USD')
                    ->setTotal($finalPrice);

                $transaction = new \PayPal\Api\Transaction();
                $transaction->setAmount($amount)
                    ->setItemList($itemList)
                    ->setDescription($subscription->description)
                    ->setInvoiceNumber($userSubscription->id);

                $redirectUrls = new \PayPal\Api\RedirectUrls();
                $redirectUrls->setReturnUrl(url("/api/payment/success/{$userSubscription->id}"))
                    ->setCancelUrl(url("/api/payment/cancel/{$userSubscription->id}"));

                $payment = new \PayPal\Api\Payment();
                $payment->setIntent('sale')
                    ->setPayer($payer)
                    ->setTransactions([$transaction])
                    ->setRedirectUrls($redirectUrls);

                try {
                    $payment->create($apiContext);
                    $paymentUrl = $payment->getApprovalLink();
                    $paymentSessionId = $payment->getId();
                    $paymentDetails = [
                        'payment_id' => $payment->getId(),
                        'state' => $payment->getState(),
                        'links' => array_map(function($link) {
                            return ['href' => $link->getHref(), 'rel' => $link->getRel(), 'method' => $link->getMethod()];
                        }, $payment->getLinks())
                    ];
                } catch (\Exception $ex) {
                    throw new \Exception('PayPal Error: ' . $ex->getMessage());
                }
            } else {
                throw new \Exception('طريقة الدفع غير مدعومة');
            }

            // تحديث بيانات الاشتراك (تعديل بسيط ليعمل مع كلا الطريقتين)
            $userSubscription->update([
                'payment_session_id' => $paymentSessionId,
                'payment_details' => $paymentDetails
            ]);

            // إنشاء سجل الدفع (تعديل بسيط)
            Payment::create([
                'user_id' => $user->id,
                'user_subscription_id' => $userSubscription->id,
                'payment_id' => $paymentSessionId,
                'amount' => $finalPrice,
                'currency' => 'usd',
                'payment_method' => $paymentMethod,
                'status' => 'pending',
                'details' => $paymentDetails
            ]);

            return [
                'success' => true,
                'payment_url' => $paymentUrl,
                'subscription' => $userSubscription,
                'discount' => $discountApplied,
                'message' => 'تم إنشاء جلسة الدفع بنجاح'
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
    } catch (\PayPal\Exception\PayPalConnectionException $e) {
        Log::error('PayPal Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'حدث خطأ في نظام الدفع PayPal',
            'error' => json_decode($e->getData(), true)
        ];
    } catch (\Exception $e) {
        Log::error('General Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'حدث خطأ غير متوقع',
            'error' => $e->getMessage()
        ];
    }
}*/



 /*   public function subscribeUser($user, $subscriptionId, $discountCode = null, $paymentMethod, $pet_id)
    {
        try {
            return DB::transaction(function () use ($user, $subscriptionId, $discountCode, $paymentMethod, $pet_id) {
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
                        $finalPrice = max(50, $originalPrice - $discountAmount);
                        $discountApplied = [
                            'code' => $discountCoupon->code,
                            'percent' => $discountCoupon->discount_percent,
                            'amount' => $discountAmount
                        ];
                        $discountCoupon->update(['is_used' => true]);
                    } else {
                        $coupon = Coupon::where('code', $discountCode)
                            ->where('is_used', false)
                            ->first();

                        if ($coupon) {
                            $discountAmount = $originalPrice * ($coupon->discount_percent / 100);
                            $finalPrice = max(50, $originalPrice - $discountAmount);
                            $discountApplied = [
                                'code' => $coupon->code,
                                'percent' => $coupon->discount_percent,
                                'amount' => $discountAmount
                            ];
                            $coupon->update(['is_used' => true]);
                        }
                    }
                }

                // إنشاء الاشتراك المؤقت
                $userSubscription = User_Subscription::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'price_paid' => $finalPrice,
                    'start_date' => now(),
                    'end_date' => now()->addMonths($subscription->duration_months),
                    'remaining_calls' => 0,
                    'remaining_visits' => 0,
                    'is_active' => User_Subscription::STATUS_PENDING,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'pending',
                    'pet_id' => $pet_id,
                    'auto_renew' => request()->boolean('auto_renew', false)
                ]);

                if ($paymentMethod == 'stripe') {
                    $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

                    // إنشاء العميل في Stripe إذا لم يكن موجودًا
                    if (!$user->stripe_customer_id) {
                        $customer = $stripe->customers->create([
                            'email' => $user->email,
                            'name' => $user->name,
                        ]);
                        $user->update(['stripe_customer_id' => $customer->id]);
                    }

                    $session = $stripe->checkout->sessions->create([
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
                        'customer' => $user->stripe_customer_id,
                        'metadata' => [
                            'user_id' => $user->id,
                            'subscription_id' => $subscription->id,
                            'user_subscription_id' => $userSubscription->id
                        ]
                    ]);

                    $paymentUrl = $session->url;
                    $paymentSessionId = $session->id;
                    $paymentDetails = [
                        'session_id' => $session->id,
                        'payment_intent' => $session->payment_intent,
                        'payment_status' => $session->payment_status
                    ];
                } elseif ($paymentMethod === 'paypal') {
                    $apiContext = new \PayPal\Rest\ApiContext(
                        new \PayPal\Auth\OAuthTokenCredential(
                            env('PAYPAL_MODE') == 'sandbox' ? env('PAYPAL_SANDBOX_CLIENT_ID') : env('PAYPAL_LIVE_CLIENT_ID'),
                            env('PAYPAL_MODE') == 'sandbox' ? env('PAYPAL_SANDBOX_CLIENT_SECRET') : env('PAYPAL_LIVE_CLIENT_SECRET')
                        )
                    );
                    $apiContext->setConfig(['mode' => env('PAYPAL_MODE', 'sandbox')]);

                    $payer = new \PayPal\Api\Payer();
                    $payer->setPaymentMethod('paypal');

                    $item = new \PayPal\Api\Item();
                    $item->setName($subscription->name)
                        ->setCurrency('USD')
                        ->setQuantity(1)
                        ->setPrice($finalPrice);

                    $itemList = new \PayPal\Api\ItemList();
                    $itemList->setItems([$item]);

                    $amount = new \PayPal\Api\Amount();
                    $amount->setCurrency('USD')
                        ->setTotal($finalPrice);

                    $transaction = new \PayPal\Api\Transaction();
                    $transaction->setAmount($amount)
                        ->setItemList($itemList)
                        ->setDescription($subscription->description)
                        ->setInvoiceNumber($userSubscription->id);

                    $redirectUrls = new \PayPal\Api\RedirectUrls();
                    $redirectUrls->setReturnUrl(url("/api/payment/success/{$userSubscription->id}"))
                        ->setCancelUrl(url("/api/payment/cancel/{$userSubscription->id}"));

                    $payment = new \PayPal\Api\Payment();
                    $payment->setIntent('sale')
                        ->setPayer($payer)
                        ->setTransactions([$transaction])
                        ->setRedirectUrls($redirectUrls);

                    try {
                        $payment->create($apiContext);
                        $paymentUrl = $payment->getApprovalLink();
                        $paymentSessionId = $payment->getId();
                        $paymentDetails = [
                            'payment_id' => $payment->getId(),
                            'state' => $payment->getState(),
                            'links' => array_map(function($link) {
                                return ['href' => $link->getHref(), 'rel' => $link->getRel(), 'method' => $link->getMethod()];
                            }, $payment->getLinks())
                        ];
                    } catch (\Exception $ex) {
                        throw new \Exception('PayPal Error: ' . $ex->getMessage());
                    }
                } else {
                    throw new \Exception('طريقة الدفع غير مدعومة');
                }

                // تحديث بيانات الاشتراك
                $userSubscription->update([
                    'payment_session_id' => $paymentSessionId,
                    'payment_details' => $paymentDetails
                ]);

                // إنشاء سجل الدفع
                Payment::create([
                    'user_id' => $user->id,
                    'user_subscription_id' => $userSubscription->id,
                    'payment_id' => $paymentSessionId,
                    'amount' => $finalPrice,
                    'currency' => 'usd',
                    'payment_method' => $paymentMethod,
                    'status' => 'pending',
                    'details' => $paymentDetails
                ]);

                return [
                    'success' => true,
                    'payment_url' => $paymentUrl,
                    'subscription' => $userSubscription,
                    'discount' => $discountApplied,
                    'message' => 'تم إنشاء جلسة الدفع بنجاح'
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
                'message' => 'حدث خطأ في نظام الدفع Stripe',
                'error' => $e->getMessage()
            ];
        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            Log::error('PayPal Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ في نظام الدفع PayPal',
                'error' => json_decode($e->getData(), true)
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
*/
public function subscribeUser($user, $subscriptionId, $discountCode = null, $paymentMethod, $pet_id)
{
    try {
        return DB::transaction(function () use ($user, $subscriptionId, $discountCode, $paymentMethod, $pet_id) {
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
                    $finalPrice = max(50, $originalPrice - $discountAmount);
                    $discountApplied = [
                        'code' => $discountCoupon->code,
                        'percent' => $discountCoupon->discount_percent,
                        'amount' => $discountAmount
                    ];
                    $discountCoupon->update(['is_used' => true]);
                } else {
                    $coupon = Coupon::where('code', $discountCode)
                        ->where('is_used', false)
                        ->first();

                    if ($coupon) {
                        $discountAmount = $originalPrice * ($coupon->discount_percent / 100);
                        $finalPrice = max(50, $originalPrice - $discountAmount);
                        $discountApplied = [
                            'code' => $coupon->code,
                            'percent' => $coupon->discount_percent,
                            'amount' => $discountAmount
                        ];
                        $coupon->update(['is_used' => true]);
                    }
                }
            }

            // إنشاء الاشتراك المؤقت مع ضبط auto_renew
            $autoRenew = request()->has('auto_renew') ? request()->boolean('auto_renew') : false;

            $userSubscription = User_Subscription::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'price_paid' => $finalPrice,
                'start_date' => now(),
                'end_date' => now()->addMonths($subscription->duration_months),
                'remaining_calls' => 0,
                'remaining_visits' => 0,
                'is_active' => User_Subscription::STATUS_PENDING,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'pet_id' => $pet_id,
                'auto_renew' => $autoRenew
            ]);

            if ($paymentMethod == 'stripe') {
                $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

                if (!$user->stripe_customer_id) {
                    $customer = $stripe->customers->create([
                        'email' => $user->email,
                        'name' => $user->name,
                    ]);
                    $user->update(['stripe_customer_id' => $customer->id]);
                }

                $session = $stripe->checkout->sessions->create([
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
                    'customer' => $user->stripe_customer_id,
                    'metadata' => [
                        'user_id' => $user->id,
                        'subscription_id' => $subscription->id,
                        'user_subscription_id' => $userSubscription->id,
                        'auto_renew' => $autoRenew
                    ]
                ]);

                $paymentUrl = $session->url;
                $paymentSessionId = $session->id;
                $paymentDetails = [
                    'session_id' => $session->id,
                    'payment_intent' => $session->payment_intent,
                    'payment_status' => $session->payment_status
                ];
            } elseif ($paymentMethod === 'paypal') {
                // ... (كود PayPal الحالي يبقى كما هو)
            }

            $userSubscription->update([
                'payment_session_id' => $paymentSessionId,
                'payment_details' => $paymentDetails
            ]);

            Payment::create([
                'user_id' => $user->id,
                'user_subscription_id' => $userSubscription->id,
                'payment_id' => $paymentSessionId,
                'amount' => $finalPrice,
                'currency' => 'usd',
                'payment_method' => $paymentMethod,
                'status' => 'pending',
                'details' => $paymentDetails
            ]);

            return [
                'success' => true,
                'payment_url' => $paymentUrl,
                'subscription' => $userSubscription,
                'discount' => $discountApplied,
                'message' => 'تم إنشاء جلسة الدفع بنجاح'
            ];
        });
    } catch (\Exception $e) {
        Log::error('Error in subscribeUser: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'حدث خطأ أثناء إنشاء الاشتراك',
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
