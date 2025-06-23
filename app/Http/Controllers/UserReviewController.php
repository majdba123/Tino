<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\User_Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserReviewController extends Controller
{


    // الحصول على تقييمات المستخدم
    public function getUserRatings(Request $request)
    {
        $query = User_Review::where('user_id', Auth::id())
            ->with('clinic');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        $ratings = $query->get();

        return response()->json($ratings);
    }

    // تحديث التقييم
    public function update(Request $request, $id)
    {
        $rating = User_Review::find($id);

        if (!$rating) {
            return response()->json(['message' => 'Rating not found'], 404);
        }

        if ($rating->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'review' => 'sometimes|nullable|string|max:1000',
        ]);

        // التحقق مما إذا كان هناك تغيير في rating أو review
        if ($request->has('rating') || $request->has('review')) {
            $rating->update([
                'rating' => $request->input('rating', $rating->rating),
                'review' => $request->input('review', $rating->review),
                'status' => 'completed'
            ]);
        }

        return response()->json($rating);
    }

    // حذف التقييم
    public function destroy($id)
    {
        $rating = User_Review::find($id);

        if (!$rating) {
            return response()->json(['message' => 'Rating not found'], 404);
        }

        if ($rating->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rating->delete();

        return response()->json(['message' => 'Rating deleted successfully']);
    }

    // الحصول على تقييمات العيادة
    public function getClinicRatings(Request $request)
    {
        $user=Auth::user();
        $clinic =$user->clinic;

        if (!$clinic) {
            return response()->json(['message' => 'Clinic not found'], 404);
        }

        $query = $clinic->user_review()->where('status', 'completed')
            ->with('user');

        if ($request->has('sort')) {
            $query->orderBy('rating', $request->sort);
        } else {
            $query->latest();
        }

        $ratings = $query->get();

        return response()->json($ratings);
    }

    // الحصول على جميع التقييمات (للمسؤول)
    public function getAllRatings(Request $request)
    {
        $query = User_Review::with(['user', 'clinic']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        $ratings = $query->get();

        return response()->json($ratings);
    }

    // حذف التقييم (للمسؤول)
    public function adminDestroy($id)
    {
        $rating = User_Review::find($id);

        if (!$rating) {
            return response()->json(['message' => 'Rating not found'], 404);
        }

        $rating->delete();

        return response()->json(['message' => 'Rating deleted successfully by admin']);
    }
}
