<?php

namespace App\Http\Controllers;

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

        $subscription = $this->subscriptionService->subscribeUser(
            $request->user(),
            $request->subscription_id,
            $request->discount_code,
            $request->payment_method ?? 'stripe' // القيمة الافتراضية stripe
        );

        return response()->json([
            'success' => $subscription['success'],
            'payment_url' => $subscription['payment_url'] ?? null,
            'message' => $subscription['message'],
        ], $subscription['success'] ? 201 : 400);
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

        $subscriptions = User_Subscription::with('user','payment','subscription')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }
}
