<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{

    public function index()
    {
        $coupons = Coupon::orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons|max:50',
            'discount_percent' => 'required|numeric|min:1|max:100',
        ]);

        $coupon = Coupon::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => $coupon
        ], 201);
    }


    public function update(Request $request, $coupon_id)
    {
        $coupon = Coupon::find($coupon_id);

        $validated = $request->validate([
            'code' => 'sometimes|string|unique:coupons,code,'.$coupon->id.'|max:50',
            'discount_percent' => 'sometimes|numeric|min:1|max:100',
            'is_used' => 'sometimes|boolean'
        ]);

        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => $coupon
        ]);
    }



    public function destroy($coupon_id)
    {
        $coupon = Coupon::find($coupon_id);

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully'
        ]);
    }
}
