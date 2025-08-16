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


    public function get_my_all(Request $request)
    {
        $user = Auth::user();

        $query = User_Subscription::with(['user', 'subscription', 'pet', 'payment'])
            ->where('user_id', $user->id);

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        $subscriptions = $query->orderBy('created_at', 'desc')->get();

        // حساب الإحصائيات
        $stats = $this->calculateUserStats($user->id, $request);

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
            'statistics' => $stats,
            'filters_applied' => $request->all()
        ]);
    }

    // دالة مساعدة لتطبيق الفلاتر
    protected function applyFilters($query, $request)
    {
        $query->when($request->start_date, function($q) use ($request) {
            return $q->whereDate('start_date', '>=', $request->start_date);
        })
        ->when($request->end_date, function($q) use ($request) {
            return $q->whereDate('end_date', '<=', $request->end_date);
        })
        ->when($request->has('is_active'), function($q) use ($request) {
            return $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        })
        ->when($request->stop_at, function($q) use ($request) {
            return $q->whereDate('stop_at', $request->stop_at);
        })
        ->when($request->has('auto_renew'), function($q) use ($request) {
            return $q->where('auto_renew', filter_var($request->auto_renew, FILTER_VALIDATE_BOOLEAN));
        })
        ->when($request->payment_method, function($q) use ($request) {
            return $q->where('payment_method', $request->payment_method);
        })
        ->when($request->payment_status, function($q) use ($request) {
            return $q->where('payment_status', $request->payment_status);
        })
        ->when($request->pet_id, function($q) use ($request) {
            return $q->where('pet_id', $request->pet_id);
        });
    }

    // دالة مساعدة لحساب الإحصائيات
    protected function calculateUserStats($userId, $request)
    {
        $statsQuery = User_Subscription::where('user_id', $userId);
        $this->applyFilters($statsQuery, $request);

        return [
            'total_subscriptions' => $statsQuery->count(),
            'active_subscriptions' => $statsQuery->clone()->where('is_active', true)->count(),
            'expired_subscriptions' => $statsQuery->clone()->where('end_date', '<', now())->count(),
            'subscription_types' => $statsQuery->clone()
                ->select('subscription_id')
                ->with('subscription')
                ->selectRaw('count(*) as count')
                ->groupBy('subscription_id')
                ->get()
                ->map(function($item) {
                    return [
                        'subscription_id' => $item->subscription_id,
                        'name' => $item->subscription->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                }),
            'payment_methods' => $statsQuery->clone()
                ->select('payment_method')
                ->selectRaw('count(*) as count')
                ->groupBy('payment_method')
                ->get(),
            'pets_with_subscriptions' => $statsQuery->clone()
                ->select('pet_id')
                ->with('pet')
                ->selectRaw('count(*) as count')
                ->groupBy('pet_id')
                ->get()
                ->map(function($item) {
                    return [
                        'pet_id' => $item->pet_id,
                        'name' => $item->pet->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                }),
            'auto_renew_stats' => [
                'enabled' => $statsQuery->clone()->where('auto_renew', true)->count(),
                'disabled' => $statsQuery->clone()->where('auto_renew', false)->count()
            ]
        ];
    }
}
