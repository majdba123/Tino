<?php

namespace App\Http\Controllers;

use App\Models\DiscountCoupon;

class DiscountCouponController extends Controller
{
    public function index()
    {
        $coupons = auth()->user()->discountCoupons()
                        ->where('is_used', false)
                        ->where('expires_at', '>', now())
                        ->get();

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }
}
