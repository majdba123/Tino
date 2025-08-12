<?php

namespace App\Http\Controllers;
use App\Models\DiscountCoupon;
use Carbon\Carbon;
use Illuminate\Support\Str;

use App\Models\User_Subscription;
use Illuminate\Http\Request;
use App\Http\Requests\SubscribeRequest;
use App\Services\UserSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserSubscriptionController extends Controller
{
    protected $subscriptionService;

    public function __construct(UserSubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
        $this->middleware('auth:sanctum');
    }



    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        // ✅ تحقق من وجود طريقة دفع
        if (is_null($user->payment_methods)) {
            return response()->json([
                'success' => false,
                'message' => 'يجب أن تختار طريقة دفع قبل الاشتراك.',
            ], 400);
        }

        $subscription = $this->subscriptionService->subscribeUser(
            $request->user(),
            $request->subscription_id,
            $request->discount_code,
            $user->payment_methods,
            $request->pet_id,
        );

        // 🎁 توليد كوبون الخصم
        $coupon = DiscountCoupon::create([
            'code' => 'PET25-' . Str::upper(Str::random(6)),
            'discount_percent' => 25,
            'expires_at' => Carbon::now()->addMonths(6),
            'user_id' => auth()->id(),
            'is_used' => false
        ]);

        return response()->json([
            'success' => $subscription['success'],
            'payment_url' => $subscription['payment_url'] ?? null,
            'message' => $subscription['message'],
        ], $subscription['success'] ? 201 : 400);
    }






    public function stopedsubscribe($id): JsonResponse
    {
        try {
            $user=Auth::user()->id;
            // Find the user subscription
            $subscription = User_Subscription::where('id',$id)->where('user_id' ,$user )->first();

            // Check if subscription exists
            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Update the stop_at field with current timestamp
            $subscription->update([
                'stop_at' => now(),
                'is_active' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription stopped successfully',
                'data' => $subscription
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }























    public function activeSubscription($user_id): JsonResponse
    {
        $subscription = $this->subscriptionService->getUserActiveSubscription(
            $user_id
        );

        return response()->json([
            'success' => true,
            'data' => $subscription
        ]);
    }

    public function get_my_all()
    {
        $user = Auth::user();

        $subscriptions = User_Subscription::with('user','payment','subscription','pet')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }
}
