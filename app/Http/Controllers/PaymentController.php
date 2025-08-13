<?php

namespace App\Http\Controllers;

use App\Models\User_Subscription;
use App\Models\Payment;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Checkout\Session;
use Illuminate\Support\Facades\Log;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\PaymentExecution;

class PaymentController extends Controller
{
    protected $paypalContext;

  /*  public function __construct()
    {
        // تهيئة سياق PayPal
        $this->paypalContext = new ApiContext(
            new OAuthTokenCredential(
                env('PAYPAL_MODE') === 'sandbox' ? env('PAYPAL_SANDBOX_CLIENT_ID') : env('PAYPAL_LIVE_CLIENT_ID'),
                env('PAYPAL_MODE') === 'sandbox' ? env('PAYPAL_SANDBOX_CLIENT_SECRET') : env('PAYPAL_LIVE_CLIENT_SECRET')
            )
        );
        $this->paypalContext->setConfig(['mode' => env('PAYPAL_MODE', 'sandbox')]);
    }*/

   /* public function success($subscriptionId)
    {
        try {
            $subscription = User_Subscription::with('subscription')->findOrFail($subscriptionId);

            // إذا كان الاشتراك مفعلاً مسبقاً
            if ($subscription->is_active === User_Subscription::STATUS_ACTIVE) {
                return response()->json([
                    'success' => true,
                    'message' => 'الاشتراك مفعّل مسبقاً'
                ])->header('Content-Type', 'application/json');
            }

            // معالجة الدفع حسب طريقة الدفع
            if ($subscription->payment_method == 'stripe') {
                Stripe::setApiKey(env('STRIPE_SECRET'));
                $session = Session::retrieve($subscription->payment_session_id);

                if ($session->payment_status == 'paid') {
                    $this->activateSubscription($subscription, $session);
                    return $this->successResponse($subscription, 'تم تفعيل الاشتراك بنجاح');
                }
            } elseif ($subscription->payment_method == 'paypal') {
                if (request('paymentId') && request('PayerID')) {
                    $payment = \PayPal\Api\Payment::get(request('paymentId'), $this->paypalContext);
                    $execution = new PaymentExecution();
                    $execution->setPayerId(request('PayerID'));

                    $result = $payment->execute($execution, $this->paypalContext);

                    if ($result->getState() === 'approved') {
                        $this->activateSubscription($subscription, [
                            'id' => $payment->getId(),
                            'state' => 'approved',
                            'payer' => $payment->getPayer()
                        ]);
                        return $this->successResponse($subscription, 'تم تفعيل الاشتراك بنجاح عبر PayPal');
                    }
                }
            }

            Payment::where('user_subscription_id', $subscription->id)
            ->update(['status' => 'failed']);

            User_Subscription::where('id', $subscription->id)
            ->update(['payment_status' => 'failed']);
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تأكيد الدفع بعد',
                'data' => ['status' => 'processing']
            ]);

        } catch (\Exception $e) {
            Log::error('Payment processing error: ' . $e->getMessage());
            Payment::where('user_subscription_id', $subscription->id)
            ->update(['status' => 'failed']);

            User_Subscription::where('id', $subscription->id)
            ->update(['payment_status' => 'failed']);
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة الدفع',
                'error' => $e->getMessage()
            ], 500);
        }
    }*/





    public function success($subscriptionId)
    {
        try {
            $subscription = User_Subscription::with('subscription', 'user')->findOrFail($subscriptionId);
            $user = $subscription->user;

            Log::info("Processing success for subscription {$subscriptionId}", [
                'user_id' => $user->id,
                'payment_method' => $subscription->payment_method,
                'payment_session_id' => $subscription->payment_session_id
            ]);

            // إذا كان الاشتراك مفعلاً مسبقاً
            if ($subscription->is_active === User_Subscription::STATUS_ACTIVE) {
                Log::info("الاشتراك {$subscriptionId} مفعل مسبقاً");
                return response()->json([
                    'success' => true,
                    'message' => 'الاشتراك مفعّل مسبقاً'
                ]);
            }

            // Stripe
            if ($subscription->payment_method === 'stripe') {
                $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

                // Verify payment_session_id exists
                if (empty($subscription->payment_session_id)) {
                    Log::error("No payment_session_id found for subscription {$subscriptionId}");
                    Payment::where('user_subscription_id', $subscription->id)
                        ->update(['status' => 'failed']);
                    $subscription->update([
                        'payment_status' => 'failed',
                        'auto_renew' => false
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'معرف جلسة الدفع غير موجود',
                        'data' => ['status' => 'missing_session_id']
                    ]);
                }

                // Retrieve the checkout session
                try {
                    $session = $stripe->checkout->sessions->retrieve(
                        $subscription->payment_session_id,
                        ['expand' => ['payment_intent', 'setup_intent']]
                    );
                } catch (\Exception $e) {
                    Log::error("Failed to retrieve checkout session for subscription {$subscriptionId}: " . $e->getMessage(), ['exception' => $e]);
                    Payment::where('user_subscription_id', $subscription->id)
                        ->update(['status' => 'failed']);
                    $subscription->update([
                        'payment_status' => 'failed',
                        'auto_renew' => false
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'فشل في استرجاع جلسة الدفع',
                        'error' => $e->getMessage()
                    ], 500);
                }

                // Log session details
                Log::info("Checkout session details for subscription {$subscriptionId}", [
                    'payment_status' => $session->payment_status,
                    'payment_intent' => $session->payment_intent ?? 'null',
                    'setup_intent' => $session->setup_intent ?? 'null'
                ]);

                // Check payment status
                if ($session->payment_status !== 'paid') {
                    Log::warning("حالة الدفع للاشتراك {$subscriptionId} ليست 'paid': {$session->payment_status}");
                    Payment::where('user_subscription_id', $subscription->id)
                        ->update(['status' => 'failed']);
                    $subscription->update([
                        'payment_status' => 'failed',
                        'auto_renew' => false
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'لم يتم تأكيد الدفع بعد',
                        'data' => ['status' => $session->payment_status]
                    ]);
                }

                // Retrieve payment method from different sources
                $paymentMethodId = null;

                // 1. Try to get from payment intent
                if (!empty($session->payment_intent)) {
                    try {
                        $paymentIntent = $session->payment_intent;
                        Log::info("Payment intent details for subscription {$subscriptionId}", [
                            'payment_intent_id' => $paymentIntent->id,
                            'payment_method' => $paymentIntent->payment_method ?? 'null'
                        ]);

                        if (!empty($paymentIntent->payment_method)) {
                            $paymentMethodId = $paymentIntent->payment_method;
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to process payment intent for subscription {$subscriptionId}: " . $e->getMessage());
                    }
                }

                // 2. Try to get from setup intent (for subscriptions)
                if (empty($paymentMethodId) && !empty($session->setup_intent)) {
                    try {
                        $setupIntent = $session->setup_intent;
                        Log::info("Setup intent details for subscription {$subscriptionId}", [
                            'setup_intent_id' => $setupIntent->id,
                            'payment_method' => $setupIntent->payment_method ?? 'null'
                        ]);

                        if (!empty($setupIntent->payment_method)) {
                            $paymentMethodId = $setupIntent->payment_method;
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to process setup intent for subscription {$subscriptionId}: " . $e->getMessage());
                    }
                }

                // 3. Fallback to payment method types from session
                if (empty($paymentMethodId) && !empty($session->payment_method_types)) {
                    $paymentMethodId = $session->payment_method_types[0] ?? null;
                    Log::info("Using fallback payment method type for subscription {$subscriptionId}", [
                        'payment_method_type' => $paymentMethodId
                    ]);
                }

                // تفعيل الاشتراك
                $subscription->update([
                    'is_active' => User_Subscription::STATUS_ACTIVE,
                    'payment_status' => 'paid',
                    'start_date' => now(),
                    'end_date' => now()->addMonths($subscription->subscription->duration_months),
                    'stripe_payment_method_id' => $paymentMethodId,
                    'auto_renew' => $subscription->auto_renew ?? request()->boolean('auto_renew', false)
                ]);

                // حفظ وربط وسيلة الدفع للاشتراك إذا وجدت
                if ($paymentMethodId) {
                    try {
                        $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId);
                        Log::info("Payment method retrieved for subscription {$subscriptionId}", [
                            'payment_method_id' => $paymentMethodId,
                            'customer' => $paymentMethod->customer ?? 'null'
                        ]);

                        // إذا لم تكن مرتبطة بـ Customer، قم بربطها
                        if ($paymentMethod->customer !== $subscription->stripe_customer_id) {
                            $stripe->paymentMethods->attach($paymentMethodId, [
                                'customer' => $subscription->stripe_customer_id
                            ]);
                            Log::info("Attached payment method {$paymentMethodId} to customer {$subscription->stripe_customer_id}");
                        }

                        // تسجيل الدفع الناجح
                        Payment::where('user_subscription_id', $subscription->id)
                            ->update([
                                'status' => 'paid',
                                'details' => array_merge(
                                    $session->toArray(),
                                    ['payment_method_id' => $paymentMethodId]
                                )
                            ]);

                        Log::info("Successfully updated subscription {$subscriptionId} with stripe_payment_method_id and payment status");
                    } catch (\Exception $e) {
                        Log::error("فشل في حفظ طريقة الدفع للاشتراك {$subscription->id}: " . $e->getMessage(), ['exception' => $e]);
                    }
                } else {
                    Log::warning("لم يتم العثور على طريقة دفع للاشتراك {$subscription->id}", [
                        'payment_intent' => $session->payment_intent ?? 'غير موجود',
                        'setup_intent' => $session->setup_intent ?? 'غير موجود',
                        'payment_method_types' => $session->payment_method_types ?? []
                    ]);

                    // مع ذلك، قم بتسجيل الدفع الناجح بدون payment_method_id
                    Payment::where('user_subscription_id', $subscription->id)
                        ->update([
                            'status' => 'paid',
                            'details' => $session->toArray()
                        ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'تم تفعيل الاشتراك بنجاح عبر Stripe',
                    'data' => [
                        'subscription' => $subscription,
                        'auto_renew' => $subscription->auto_renew
                    ]
                ]);
            }

            // PayPal
            elseif ($subscription->payment_method === 'paypal') {
                if (request('paymentId') && request('PayerID')) {
                    $payment = \PayPal\Api\Payment::get(request('paymentId'), $this->paypalContext);
                    $execution = new \PayPal\Api\PaymentExecution();
                    $execution->setPayerId(request('PayerID'));

                    $result = $payment->execute($execution, $this->paypalContext);

                    if ($result->getState() === 'approved') {
                        $subscription->update([
                            'is_active' => User_Subscription::STATUS_ACTIVE,
                            'payment_status' => 'paid',
                            'start_date' => now(),
                            'end_date' => now()->addMonths($subscription->subscription->duration_months),
                            'auto_renew' => false // PayPal لا يدعم التجديد التلقائي
                        ]);

                        Payment::where('user_subscription_id', $subscription->id)
                            ->update([
                                'status' => 'paid',
                                'details' => $result->toArray()
                            ]);

                        Log::info("Successfully processed PayPal payment for subscription {$subscriptionId}");
                        return response()->json([
                            'success' => true,
                            'message' => 'تم تفعيل الاشتراك بنجاح عبر PayPal',
                            'data' => [
                                'subscription' => $subscription,
                                'auto_renew' => false
                            ]
                        ]);
                    }
                }
            }

            // فشل الدفع
            Log::warning("فشل الدفع للاشتراك {$subscription->id}: طريقة الدفع غير مدعومة أو بيانات غير كاملة");
            Payment::where('user_subscription_id', $subscription->id)
                ->update(['status' => 'failed']);

            $subscription->update([
                'payment_status' => 'failed',
                'auto_renew' => false
            ]);

            return response()->json([
                'success' => false,
                'message' => 'لم يتم تأكيد الدفع بعد',
                'data' => ['status' => 'processing']
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في معالجة الدفع: ' . $e->getMessage(), [
                'subscription_id' => $subscriptionId,
                'exception' => $e
            ]);

            Payment::where('user_subscription_id', $subscriptionId)
                ->update(['status' => 'failed']);

            User_Subscription::where('id', $subscriptionId)
                ->update([
                    'payment_status' => 'failed',
                    'auto_renew' => false
                ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء معالجة الدفع',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel($subscriptionId)
    {
        try {
            $subscription = User_Subscription::findOrFail($subscriptionId);



            $updateData = [
                'is_active' => User_Subscription::STATUS_FAILED,
                'payment_status' => 'canceled',
                'payment_details' => array_merge(
                    (array)$subscription->payment_details,
                    ['cancelled_at' => now()]
                )
            ];

            // إذا كان PayPal، نضيف معلومات إضافية
            if ($subscription->payment_method === 'paypal') {
                $updateData['payment_details']['paypal_cancel'] = true;
            }

            $subscription->update($updateData);

            Payment::where('payment_id', $subscription->payment_session_id)->update([
                'status' => Payment::STATUS_FAILED,
                'details' => ['reason' => 'user_cancelled']
            ]);
            Payment::where('user_subscription_id', $subscription->id)
            ->update(['status' => 'failed']);

            User_Subscription::where('id', $subscription->id)
            ->update(['payment_status' => 'failed']);
            return response()->json([
                'success' => false,
                'message' => 'تم إلغاء عملية الدفع',
                'data' => [
                    'status' => 'canceled',
                    'subscription_id' => $subscription->id,
                    'can_retry' => true
                ]
            ]);

        } catch (\Exception $e) {
            Payment::where('user_subscription_id', $subscription->id)
            ->update(['status' => 'failed']);

            User_Subscription::where('id', $subscription->id)
            ->update(['payment_status' => 'failed']);
            Log::error('Cancel subscription error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء الاشتراك',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkStatus($subscriptionId)
    {
        try {
            $subscription = User_Subscription::with('subscription')->findOrFail($subscriptionId);

            // التحقق من أن الاشتراك مملوك للمستخدم الحالي
            if ($subscription->user_id != auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح بالوصول إلى هذا الاشتراك'
                ], 403);
            }

            // إذا كان PayPal ولم يتم التحقق بعد، يمكننا التحقق من حالة الدفع
            if ($subscription->payment_method === 'paypal' && $subscription->is_active === User_Subscription::STATUS_PENDING) {
                try {
                    $payment = \PayPal\Api\Payment::get($subscription->payment_session_id, $this->paypalContext);
                    if ($payment->getState() === 'approved') {
                        $this->activateSubscription($subscription, [
                            'id' => $payment->getId(),
                            'state' => 'approved',
                            'payer' => $payment->getPayer()
                        ]);
                        $subscription->refresh();
                    }
                } catch (\Exception $e) {
                    Log::error('PayPal status check error: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $subscription->is_active,
                    'payment_status' => $subscription->payment_status,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'can_retry' => $subscription->is_active == User_Subscription::STATUS_FAILED,
                    'payment_method' => $subscription->payment_method
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Check status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق من حالة الاشتراك',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        // تحديد نوع الويب هوك (Stripe أو PayPal)
        if ($request->has('event_type') && $request->has('resource')) {
            // معالجة ويب هوك PayPal
            return $this->handlePayPalWebhook($request);
        } else {
            // معالجة ويب هوك Stripe
            return $this->handleStripeWebhook($request);
        }
    }

    protected function handleStripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature',
                'error' => $e->getMessage()
            ], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $this->handlePaymentSuccess($session);
                break;

            case 'checkout.session.expired':
                $session = $event->data->object;
                $this->handlePaymentExpired($session);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;
        }

        return response()->json(['success' => true]);
    }

    protected function handlePayPalWebhook(Request $request)
    {
        $eventType = $request->input('event_type');
        $resource = $request->input('resource');

        if (!$eventType || !$resource) {
            return response()->json(['success' => false, 'message' => 'Invalid PayPal webhook data'], 400);
        }

        $paymentId = $resource['id'] ?? null;
        if (!$paymentId) return response()->json(['success' => false, 'message' => 'No payment ID'], 400);

        $subscription = User_Subscription::where('payment_session_id', $paymentId)->first();
        if (!$subscription) return response()->json(['success' => false, 'message' => 'Subscription not found'], 404);

        switch ($eventType) {
            case 'PAYMENT.SALE.COMPLETED':
                if ($subscription->is_active == User_Subscription::STATUS_PENDING) {
                    $this->activateSubscription($subscription, $resource);
                }
                break;

            case 'PAYMENT.SALE.DENIED':
            case 'PAYMENT.SALE.FAILED':
                $subscription->update([
                    'is_active' => User_Subscription::STATUS_FAILED,
                    'payment_status' => 'failed',
                    'payment_details' => array_merge(
                        (array)$subscription->payment_details,
                        ['failed_at' => now(), 'reason' => $request->input('summary', 'unknown')]
                    )
                ]);

                Payment::where('payment_id', $paymentId)->update([
                    'status' => Payment::STATUS_FAILED,
                    'details' => ['reason' => 'payment_failed']
                ]);
                break;

            case 'PAYMENT.SALE.REFUNDED':
                $subscription->update([
                    'is_active' => User_Subscription::STATUS_FAILED,
                    'payment_status' => 'refunded'
                ]);
                break;
        }

        return response()->json(['success' => true]);
    }

    protected function activateSubscription(User_Subscription $subscription, $paymentData)
    {
        $updateData = [
            'is_active' => User_Subscription::STATUS_ACTIVE,
            'payment_status' => 'paid',
            'start_date' => now(),
            'end_date' => now()->addMonths($subscription->subscription->duration_months),
            'payment_details' => is_array($paymentData) ? $paymentData : $paymentData->toArray()
        ];

        $subscription->update($updateData);

        Payment::updateOrCreate(
            ['payment_id' => $subscription->payment_session_id],
            [
                'status' => Payment::STATUS_PAID,
                'details' => $updateData['payment_details']
            ]
        );
    }

    protected function handlePaymentSuccess($session)
    {
        $subscription = User_Subscription::where('payment_session_id', $session->id)->first();

        if ($subscription && $subscription->is_active == User_Subscription::STATUS_PENDING) {
            $this->activateSubscription($subscription, $session);
            // يمكنك هنا إرسال إشعار للمستخدم
        }
    }

    protected function handlePaymentExpired($session)
    {
        $subscription = User_Subscription::where('payment_session_id', $session->id)->first();

        if ($subscription && $subscription->is_active == User_Subscription::STATUS_PENDING) {
            $subscription->update([
                'is_active' => User_Subscription::STATUS_FAILED,
                'payment_status' => 'expired',
                'payment_details' => [
                    'expired_at' => now(),
                    'reason' => 'session_expired'
                ]
            ]);

            Payment::where('payment_id', $session->id)->update([
                'status' => Payment::STATUS_FAILED,
                'details' => ['reason' => 'session_expired']
            ]);
        }
    }

    protected function handlePaymentFailed($paymentIntent)
    {
        $subscription = User_Subscription::where('payment_session_id', $paymentIntent->id)->first();

        if ($subscription && $subscription->is_active == User_Subscription::STATUS_PENDING) {
            $subscription->update([
                'is_active' => User_Subscription::STATUS_FAILED,
                'payment_status' => 'failed',
                'payment_details' => [
                    'failed_at' => now(),
                    'reason' => $paymentIntent->last_payment_error->message ?? 'unknown'
                ]
            ]);

            Payment::where('payment_id', $paymentIntent->id)->update([
                'status' => Payment::STATUS_FAILED,
                'details' => [
                    'reason' => 'payment_failed',
                    'error' => $paymentIntent->last_payment_error
                ]
            ]);
        }
    }

    protected function successResponse($subscription, $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'subscription' => $subscription,
                'payment_status' => 'paid',
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'payment_method' => $subscription->payment_method
            ]
        ])->header('Content-Type', 'application/json');
    }
}
