<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\DiscountCoupon;
use App\Http\Requests\PetRequest;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PetController extends Controller
{

    public function store(PetRequest $request)
    {

        $activeSubscription = auth()->user()->User_Subscription()
        ->active()
        ->exists();

        if (!$activeSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'يجب أن يكون لديك اشتراك فعال لإضافة حيوان أليف'
            ], 403);
        }

        $data = $request->validated();
        $data['user_id'] = auth()->id();

        // إضافة القيمة الافتراضية للحالة إذا لم يتم إرسالها
        $data['status'] = $data['status'] ?? 'active';

        // إنشاء الحيوان الأليف
        $pet = Pet::create($data);

        // توليد كوبون الخصم
        $coupon = DiscountCoupon::create([
            'code' => 'PET25-' . Str::upper(Str::random(6)),
            'discount_percent' => 25,
            'expires_at' => Carbon::now()->addMonths(6),
            'user_id' => auth()->id(),
            'is_used' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الحيوان الأليف بنجاح!',
            'pet' => $pet->load('user'),
            'coupon' => [
                'code' => $coupon->code,
                'discount' => '25%',
                'expires_at' => $coupon->expires_at->format('Y-m-d')
            ]
        ], 201);
    }

    public function show($id)
    {
        $pet = Pet::with(['user', 'medicalRecords'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $pet,
            'age' => $pet->age // إرجاع العمر المحسوب
        ]);
    }
    public function index()
    {
        $pets = auth()->user()->pets()->with('medicalRecords')->get();

        return response()->json([
            'success' => true,
            'data' => $pets
        ]);
    }
}
