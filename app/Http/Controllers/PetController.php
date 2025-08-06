<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\DiscountCoupon;
use App\Http\Requests\PetRequest;
use App\Http\Requests\Petupdate;

use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

        // حفظ الصورة بنفس الطريقة المستخدمة سابقاً
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $imageName = Str::random(32).'.'.$imageFile->getClientOriginalExtension();
            $imagePath = 'pets/' . $imageName;
            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
            $data['image'] = url('api/storage/' . $imagePath);
        }

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
    public function updatePet(Petupdate $request, $id)
    {
        $user = Auth::user();

        // البحث عن الحيوان والتحقق من ملكيته للمستخدم
        $pet = Pet::where('user_id', $user->id)->find($id);

        if (!$pet) {
            return response()->json([
                'success' => false,
                'message' => 'الحيوان الأليف غير موجود أو لا تملك صلاحية التعديل عليه'
            ], 404);
        }

        // تحديث البيانات مع التحقق من الحقول المطلوبة
        $data = $request->only([
            'name',
            'type',
            'breed',
            'name_cheap',
            'birth_date',
            'gender',
            'health_status',
            'status'
        ]);

        // معالجة صورة الحيوان الأليف
        if ($request->hasFile('image')) {
            // حذف الصورة القديمة إذا كانت موجودة
            if ($pet->image) {
                $oldImagePath = str_replace(url('api/storage/'), '', $pet->image);
                if (Storage::disk('public')->exists($oldImagePath)) {
                    Storage::disk('public')->delete($oldImagePath);
                }
            }

            // حفظ الصورة الجديدة بنفس الطريقة المستخدمة سابقاً
            $imageFile = $request->file('image');
            $imageName = Str::random(32).'.'.$imageFile->getClientOriginalExtension();
            $imagePath = 'pets/' . $imageName;
            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
            $data['image'] = url('api/storage/' . $imagePath);
        }

        // التحقق من وجود بيانات للتحديث
        if (empty($data) && !$request->hasFile('image')) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تقديم أي بيانات للتحديث'
            ], 400);
        }

        // تنفيذ التحديث
        $pet->update($data);

        // إرجاع النتيجة مع بيانات محدثة
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث معلومات الحيوان الأليف بنجاح',
            'data' => $pet->fresh()
        ]);
    }
}
