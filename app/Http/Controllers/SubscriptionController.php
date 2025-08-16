<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionStoreRequest;
use App\Http\Requests\SubscriptionUpdateRequest;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User_Subscription;

class SubscriptionController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|nullable',
            'price_min' => 'sometimes|numeric|min:0|nullable',
            'price_max' => 'sometimes|numeric|min:0|nullable',
            'is_active' => 'sometimes|string|in:true,false,all|nullable',
            'type' => 'sometimes|string|in:basic,premium|nullable',
            'per_page' => 'sometimes|integer|min:1|max:100' // إضافة اختيار عدد العناصر في الصفحة
        ]);

        $filters = array_filter($validated, function($value, $key) {
            return $value !== null && $value !== '' && $key !== 'per_page';
        }, ARRAY_FILTER_USE_BOTH);

        $perPage = $request->input('per_page', 15); // 15 عنصراً في الصفحة افتراضياً

        $subscriptions = $this->subscriptionService->getSubscriptions($filters)
            ->latest() // الترتيب حسب الأحدث
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $subscriptions->items(),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
            'filters' => $filters
        ]);
    }

    public function store(SubscriptionStoreRequest $request): JsonResponse
    {
        $subscription = $this->subscriptionService->createSubscription($request->validated());

        return response()->json([
            'success' => true,
            'data' => $subscription,
            'message' => 'تم إنشاء الاشتراك بنجاح'
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $subscription
        ]);
    }

    public function update(SubscriptionUpdateRequest $request, $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $updatedSubscription = $this->subscriptionService->updateSubscription($subscription, $request->validated());

        return response()->json([
            'success' => true,
            'data' => $updatedSubscription,
            'message' => 'تم تحديث الاشتراك بنجاح'
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $this->subscriptionService->deleteSubscription($subscription);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الاشتراك بنجاح'
        ]);
    }

    public function activate($id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $activatedSubscription = $this->subscriptionService->activateSubscription($subscription);

        return response()->json([
            'success' => true,
            'data' => $activatedSubscription,
            'message' => 'تم تفعيل الاشتراك بنجاح'
        ]);
    }

    public function deactivate($id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $deactivatedSubscription = $this->subscriptionService->deactivateSubscription($subscription);

        return response()->json([
            'success' => true,
            'data' => $deactivatedSubscription,
            'message' => 'تم تعطيل الاشتراك بنجاح'
        ]);
    }

    public function get_all_user_subscriped(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $userId = $request->input('user_id');

        $query = User_Subscription::with(['user', 'subscription', 'payment', 'pet'])
            ->when($userId, function ($query) use ($userId) {
                return $query->where('user_id', $userId);
            })
            ->when($request->input('is_active'), function ($query) use ($request) {
                return $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->input('subscription_id'), function ($query) use ($request) {
                return $query->where('subscription_id', $request->subscription_id);
            })
            ->when($request->input('payment_method'), function ($query) use ($request) {
                return $query->where('payment_method', $request->payment_method);
            })
            ->when($request->input('payment_status'), function ($query) use ($request) {
                return $query->where('payment_status', $request->payment_status);
            })
            ->when($request->input('start_date'), function ($query) use ($request) {
                return $query->whereDate('start_date', '>=', $request->start_date);
            })
            ->when($request->input('end_date'), function ($query) use ($request) {
                return $query->whereDate('end_date', '<=', $request->end_date);
            })
            ->latest();

        $paginatedSubscriptions = $query->paginate($perPage);

        // تنسيق البيانات
        $formattedData = $paginatedSubscriptions->getCollection()
            ->groupBy('user_id')
            ->map(function ($subscriptions) {
                $user = $subscriptions->first()->user;
                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'subscriptions' => $subscriptions->map(function ($subscription) {
                        return [
                            'subscription_id' => $subscription->subscription_id,
                            'subscription_name' => $subscription->subscription->name,
                            'start_date' => $subscription->start_date,
                            'end_date' => $subscription->end_date,
                            'remaining_calls' => $subscription->remaining_calls,
                            'remaining_visits' => $subscription->remaining_visits,
                            'is_active' => $subscription->is_active,
                            'payment_method' => $subscription->payment_method,
                            'payment_status' => $subscription->payment_status
                        ];
                    })
                ];
            })
            ->values();

        // حساب الإحصائيات
        $stats = [
            'total_active_subscriptions' => User_Subscription::where('is_active', true)->count(),
            'total_users_with_subscriptions' => User_Subscription::distinct('user_id')->count('user_id'),
            'subscription_distribution' => User_Subscription::selectRaw('subscription_id, count(*) as count')
                ->groupBy('subscription_id')
                ->with('subscription')
                ->get()
                ->map(function ($item) {
                    return [
                        'subscription_id' => $item->subscription_id,
                        'subscription_name' => $item->subscription->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                }),
            'payment_methods_distribution' => User_Subscription::selectRaw('payment_method, count(*) as count')
                ->groupBy('payment_method')
                ->get(),
            'status_distribution' => [
                'active' => User_Subscription::where('is_active', true)->count(),
                'expired' => User_Subscription::where('end_date', '<', now())->count(),
                'pending' => User_Subscription::where('payment_status', User_Subscription::STATUS_PENDING)->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'statistics' => $stats,
            'meta' => [
                'current_page' => $paginatedSubscriptions->currentPage(),
                'last_page' => $paginatedSubscriptions->lastPage(),
                'per_page' => $paginatedSubscriptions->perPage(),
                'total' => $paginatedSubscriptions->total(),
                'filters' => $request->except('per_page')
            ],
            'message' => 'تم جلب المستخدمين المشتركين بنجاح'
        ]);
    }
}
