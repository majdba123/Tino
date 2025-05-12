<?php

namespace App\Http\Controllers;

use App\Models\Anwer_Cons;
use App\Models\Order_Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderClinicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function getClinicOrders(Request $request)
    {
        $clinic = auth()->user()->clinic;

        if (!$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'عيادة غير مسجلة'
            ], 404);
        }

        // تجهيز الاستعلام مع ترتيب الطلبات حسب الأحدث
        $query = Order_Clinic::with([
            'consultation:id,user_id,pet_id,description,status',
            'consultation.pet:id,name,type,gender',
            'consultation.user:id,name,email',
            'consultation.pet.medicalRecords:id,details,date,pet_id'
        ])->where('clinic_id', $clinic->id);

        // تطبيق الفلاتر
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // ترتيب الطلبات حسب الأحدث وتطبيق `pagination`
        $orders = $query->latest()->paginate(10);

        // تحضير البيانات للإرجاع
        return response()->json([
            'success' => true,
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'consultation' => [
                        'description' => $order->consultation->description,
                        'status' => $order->consultation->status,
                        'admin_notes' => $order->consultation->admin_notes,

                        'pet' => [
                            'name' => $order->consultation->pet->name,
                            'type' => $order->consultation->pet->type,
                            'gender' => $order->consultation->pet->gender,
                            'medical_records' => $order->consultation->pet->medicalRecords
                                ? $order->consultation->pet->medicalRecords->map(fn ($record) => [
                                    'date' => $record->date,
                                    'details' => $record->details
                                ])
                                : []
                        ],
                        'user' => [
                            'name' => $order->consultation->user->name,
                            'email' => $order->consultation->user->email
                        ]
                    ]
                ];
            })
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function updateOrderStatus(Request $request, $orderId)
    {
        // جلب العيادة الخاصة بالمستخدم المسجل
        $clinic = auth()->user()->clinic;

        if (!$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'العيادة غير مسجلة'
            ], 403);
        }

        // البحث عن الطلب
        $order = Order_Clinic::with('consultation')->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        // التحقق من أن العيادة المالكة للطلب هي نفس العيادة التي تقوم بالطلب
        if ($order->clinic_id !== $clinic->id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتحديث هذا الطلب'
            ], 403);
        }

        // التحقق من أن الحالة المطلوبة صحيحة
        if (!in_array($request->status, ['approved', 'disapproved'])) {
            return response()->json([
                'success' => false,
                'message' => 'حالة غير صحيحة، يجب أن تكون approved أو disapproved'
            ], 400);
        }

        // إذا كانت الحالة approved، التحقق من وجود تاريخ ووقت الزيارة
        if ($request->status === 'approved') {
            $validator = Validator::make($request->all(), [
                'visit_date' => 'required|date|after_or_equal:today',
                'visit_time' => 'required|date_format:H:i'
            ], [
                'visit_date.required' => 'تاريخ الزيارة مطلوب',
                'visit_date.date' => 'يجب أن يكون تاريخاً صالحاً',
                'visit_date.after_or_equal' => 'يجب أن يكون التاريخ اليوم أو ما بعده',
                'visit_time.required' => 'وقت الزيارة مطلوب',
                'visit_time.date_format' => 'يجب أن يكون الوقت بتنسيق ساعة:دقيقة (24 ساعة)'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
        }

        // تحديث حالة الطلب
        $order->status = $request->status;

        // إذا كانت الحالة approved، حفظ موعد الزيارة
        $order->save();

        // تحديث حالة الاستشارة بناءً على حالة الطلب
        if ($order->consultation) {
            if ($request->status === 'approved') {
                $order->consultation->status = 'complete';

                // حفظ الرد الخاص بالاستشارة في `Anwer_Cons` مع معلومات العيادة كـ JSON
                $clinicInfo = [
                    'id' => $clinic->id,
                    'name' => $clinic->user->name,
                    'address' => $clinic->address,
                    'phone' => $clinic->phone,
                    'latitude' => $clinic->latitude,
                    'longitude' => $clinic->longitude,
                    'opening_time' => $clinic->opening_time,
                    'closing_time' => $clinic->closing_time,
                    'type' => $clinic->type,
                    'status' => $clinic->status,
                    'visit_date' => $request->visit_date,
                    'visit_time' => $request->visit_time
                ];

                Anwer_Cons::updateOrCreate(
                    ['consultation_id' => $order->consultation->id],
                    [
                        'clinic_info' => json_encode($clinicInfo),
                        'operation' => $order->consultation->operation
                    ]
                );
            } elseif ($request->status === 'disapproved') {
                $order->consultation->status = 'disapproved';
            }

            $order->consultation->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الطلب بنجاح',
            'order_status' => $order->status,
            'consultation_status' => $order->consultation ? $order->consultation->status : null,
            'visit_details' => $request->status === 'approved' ? [
                'date' => $request->visit_date,
                'time' => $request->visit_time,
                'clinic_info' => $clinicInfo ?? null
            ] : null
        ]);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Order_Clinic $order_Clinic)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order_Clinic $order_Clinic)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order_Clinic $order_Clinic)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order_Clinic $order_Clinic)
    {
        //
    }
}
