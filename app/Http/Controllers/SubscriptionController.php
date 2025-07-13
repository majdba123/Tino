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
        $perPage = $request->input('per_page', 15); // 15 عنصراً في الصفحة افتراضياً

        $query = User_Subscription::with(['user', 'subscription','payment'])
            ->where('is_active', true)
            ->latest();

        $paginatedSubscriptions = $query->paginate($perPage);

        // تنسيق البيانات مع الحفاظ على الهيكل الأصلي
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
                            'is_active' => $subscription->is_active
                        ];
                    })
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'meta' => [
                'current_page' => $paginatedSubscriptions->currentPage(),
                'last_page' => $paginatedSubscriptions->lastPage(),
                'per_page' => $paginatedSubscriptions->perPage(),
                'total' => $paginatedSubscriptions->total(),
            ],
            'message' => 'تم جلب المستخدمين المشتركين بنجاح'
        ]);
    }
}
