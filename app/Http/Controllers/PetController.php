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
    try {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        // إضافة القيمة الافتراضية للحالة إذا لم يتم إرسالها
        $data['status'] = $data['status'] ?? 'active';

        // معالجة الحقول المصفوفة (تحويلها إلى JSON)
        $jsonFields = ['allergies', 'previous_surgeries', 'chronic_conditions', 'vaccination_history'];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            } else {
                $data[$field] = null; // أو json_encode([]) إذا كنت تريد مصفوفة فارغة افتراضياً
            }
        }

        // تحويل القيم المنطقية
        if (isset($data['is_spayed'])) {
            $data['is_spayed'] = filter_var($data['is_spayed'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $data['is_spayed'] = false; // قيمة افتراضية
        }

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

        // تحميل العلاقات مع البيانات المحولة
        $pet->load('user');

        // تحويل الحقول JSON back إلى arrays للاستجابة
        $petData = $pet->toArray();
        foreach ($jsonFields as $field) {
            if ($pet->$field) {
                $petData[$field] = json_decode($pet->$field, true);
            } else {
                $petData[$field] = [];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الحيوان الأليف بنجاح!',
            'pet' => $petData,
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'فشل في إضافة الحيوان الأليف: ' . $e->getMessage()
        ], 500);
    }
}
    public function show($id)
    {
        $pet = Pet::with(['user', 'medicalRecords','user_subscriptionn'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $pet,
            'age' => $pet->age // إرجاع العمر المحسوب
        ]);
    }
    public function index()
    {
        $pets = auth()->user()->pets()->with('medicalRecords','user_subscriptionn')->get();

        return response()->json([
            'success' => true,
            'data' => $pets
        ]);
    }
   public function updatePet(Petupdate $request, $id)
{
    try {
        $user = Auth::user();

        // البحث عن الحيوان والتحقق من ملكيته للمستخدم
        $pet = Pet::where('user_id', $user->id)->find($id);

        if (!$pet) {
            return response()->json([
                'success' => false,
                'message' => 'الحيوان الأليف غير موجود أو لا تملك صلاحية التعديل عليه'
            ], 404);
        }

        // الحصول على جميع البيانات المصرح بها
        $data = $request->validated();

        // معالجة الحقول المصفوفة (تحويلها إلى JSON)
        $jsonFields = ['allergies', 'previous_surgeries', 'chronic_conditions', 'vaccination_history'];

        foreach ($jsonFields as $field) {
            if (isset($data[$field])) {
                if (is_array($data[$field])) {
                    // إذا تم إرسال مصفوفة جديدة، استبدال القديمة بها
                    $data[$field] = !empty($data[$field]) ? json_encode($data[$field]) : null;
                } else {
                    // إذا لم يتم إرسالها أو تم إرسال قيمة غير صحيحة، احذفها من البيانات
                    unset($data[$field]);
                }
            }
        }

        // تحويل القيم المنطقية
        if (isset($data['is_spayed'])) {
            $data['is_spayed'] = filter_var($data['is_spayed'], FILTER_VALIDATE_BOOLEAN);
        }

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

        // تحميل البيانات المحدثة مع العلاقات
        $pet->refresh();

        // تحويل الحقول JSON back إلى arrays للاستجابة
        $petData = $pet->toArray();
        foreach ($jsonFields as $field) {
            if ($pet->$field) {
                $petData[$field] = json_decode($pet->$field, true);
            } else {
                $petData[$field] = [];
            }
        }

        // إرجاع النتيجة مع بيانات محدثة
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث معلومات الحيوان الأليف بنجاح',
            'data' => $petData
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'فشل في تحديث الحيوان الأليف: ' . $e->getMessage()
        ], 500);
    }
}
}
