<?php

namespace App\Http\Controllers;

use App\Models\User_Subscription;
use App\Models\Payment;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Checkout\Session;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function success($subscriptionId)
    {
        //dd(request()->all());
        try {
            $subscription = User_Subscription::with('subscription')->findOrFail($subscriptionId);

            // التحقق من أن الاشتراك مملوك للمستخدم الحالي
      /*      if ($subscription->user_id != auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح بالوصول إلى هذا الاشتراك'
                ], 403)->header('Content-Type', 'application/json');
            }*/

            // إذا كان الاشتراك مفعلاً مسبقاً
            if ($subscription->is_active === User_Subscription::STATUS_ACTIVE) {
                return response()->json([
                    'success' => true,
                    'message' => 'الاشتراك مفعّل مسبقاً'
                ])->header('Content-Type', 'application/json');
            }

            Stripe::setApiKey(env('STRIPE_SECRET'));

            $session = Session::retrieve($subscription->payment_session_id);

            if ($session->payment_status == 'paid') {
                $this->activateSubscription($subscription, $session);

                return response()->json([
                    'success' => true,
                    'message' => 'تم تفعيل الاشتراك بنجاح',
                    'data' => [
                        'subscription' => $subscription,
                        'payment_status' => 'paid',
                        'start_date' => $subscription->start_date,
                        'end_date' => $subscription->end_date
                    ]
                ])->header('Content-Type', 'application/json');
            }

            return response()->json([
                'success' => false,
                'message' => 'لم يتم تأكيد الدفع بعد',
                'data' => ['status' => 'processing']
            ]);

        } catch (\Exception $e) {
            Log::error('Payment processing error: ' . $e->getMessage());
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

            // التحقق من أن الاشتراك مملوك للمستخدم الحالي
            if ($subscription->user_id != auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح بالوصول إلى هذا الاشتراك'
                ], 403);
            }

            $subscription->update([
                'is_active' => User_Subscription::STATUS_FAILED,
                'payment_status' => 'canceled',
                'payment_details' => array_merge(
                    (array)$subscription->payment_details,
                    ['cancelled_at' => now()]
                )
            ]);

            Payment::where('payment_id', $subscription->payment_session_id)->update([
                'status' => Payment::STATUS_FAILED,
                'details' => ['reason' => 'user_cancelled']
            ]);

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

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $subscription->is_active,
                    'payment_status' => $subscription->payment_status,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'can_retry' => $subscription->is_active == User_Subscription::STATUS_FAILED
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
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch (\Exception $e) {
            Log::error('Webhook signature verification failed: ' . $e->getMessage());
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

    protected function activateSubscription(User_Subscription $subscription, $session)
    {
        $subscription->update([
            'is_active' => User_Subscription::STATUS_ACTIVE,
            'payment_status' => 'paid',
            'start_date' => now(),
            'end_date' => now()->addMonths($subscription->subscription->duration_months),
            'payment_details' => $session->toArray()
        ]);

        Payment::updateOrCreate(
            ['payment_id' => $session->id],
            [
                'status' => Payment::STATUS_PAID,
                'details' => $session->toArray()
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
}
