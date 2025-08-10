<?php

namespace App\Http\Controllers;

use App\Models\Refound;
use Illuminate\Http\Request;
use App\Models\User_Subscription;
use Illuminate\Support\Facades\Auth;

class RefoundController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'user_subscription_id' => 'required|exists:user__subscriptions,id',
        ]);

        $user = Auth::user();
        $subscription = User_Subscription::findOrFail($request->user_subscription_id);

        // التحقق من الشروط
        if ($subscription->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($subscription->is_active || is_null($subscription->stop_at)) {
            return response()->json([
                'message' => 'Refund request cannot be created for active subscriptions or without stop date'
            ], 422);
        }

        // إنشاء طلب الاسترجاع
        $refund = Refound::create([
            'user_id' => $user->id,
            'user_subscription_id' => $subscription->id,

        ]);

        return response()->json([
            'message' => 'Refund request created successfully',
            'data' => $refund
        ], 201);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Refound::with(['User_Subscription', 'user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // فلترة حسب الاشتراك
        if ($request->has('user_subscription_id')) {
            $query->where('user_subscription_id', $request->user_subscription_id);
        }

        // فلترة حسب الحالة
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // إضافة pagination
        $refunds = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $refunds
        ]);
    }


    public function index_admin(Request $request)
    {
        $user = Auth::user();
        $query = Refound::with(['user', 'User_Subscription'])
        ->orderBy('created_at', 'desc');
        // فلترة حسب الاشتراك
        if ($request->has('user_subscription_id')) {
            $query->where('user_subscription_id', $request->user_subscription_id);
        }

        // فلترة حسب الحالة
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // إضافة pagination
        $refunds = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $refunds
        ]);
    }


    public function updateStatus(Request $request, $refundId)
    {


        $request->validate([
            'status' => 'required|in:rejected,completed',
        ]);

        $refund = Refound::findOrFail($refundId);

        // يمكنك إضافة شروط إضافية هنا مثلاً:
        // - لا يمكن تغيير الحالة إذا كانت already completed
        if ($refund->status === 'completed') {
            return response()->json([
                'message' => 'Cannot change status of already completed refund'
            ], 422);
        }

        // تحديث الحالة
        $refund->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
            'processed_at' => now() // تسجيل وقت المعالجة
        ]);

        // إرسال إشعار للمستخدم (اختياري)
        // $refund->user->notify(new RefundStatusUpdated($refund));

        return response()->json([
            'success' => true,
            'message' => 'Refund status updated successfully',
            'data' => $refund
        ]);
    }
}
