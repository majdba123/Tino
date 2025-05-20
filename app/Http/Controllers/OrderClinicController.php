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

        // Eager load relationships with specific columns
        $query = Order_Clinic::with([
            'consultation:id,user_id,pet_id,description,status,admin_notes,operation',
            'consultation.pet:id,name,type,gender',
            'consultation.user:id,name,email',
            'consultation.pet.medicalRecords:id,details,date,pet_id',
            'consultation.anwer_cons:id,consultation_id,clinic_info,operation'
        ])->where('clinic_id', $clinic->id);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get paginated results
        $orders = $query->latest()->paginate(10);

        // Prepare response data
        return response()->json([
            'success' => true,
            'data' => $orders->map(function ($order) {
                $clinicInfo = $order->consultation->anwer_cons
                    ? json_decode($order->consultation->anwer_cons->clinic_info, true)
                    : null;

                return [
                    'id' => $order->id,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'price_order' => $order->price_order,
                    'discount_amount' => $order->discount_amount,
                    'final_price' => $order->final_price,
                    'consultation' => [
                        'id' => $order->consultation->id,
                        'description' => $order->consultation->description,
                        'status' => $order->consultation->status,
                        'admin_notes' => $order->consultation->admin_notes,
                        'operation' => $order->consultation->operation,
                        'answer' => $order->consultation->anwer_cons ? [
                            'clinic_info' => $clinicInfo,
                            'operation' => $order->consultation->anwer_cons->operation
                        ] : null,
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
            }),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem()
            ]
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
                    'clinic_id' => $clinic->id,
                    'Order_id' => $order->id,
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
/**
 * Check order status and update to "in_clinic" if approved
 */
    public function checkAndUpdateOrderStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:order__clinics,id',
            'consultation_id' => 'required|exists:consultations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the order with matching consultation_id and load all needed relationships
        $order = Order_Clinic::with([
            'consultation:id,user_id,pet_id,description,admin_notes,operation,status',
            'consultation.pet:id,name,type,gender',
            'consultation.user:id,name,email',
            'consultation.anwer_cons:id,consultation_id,clinic_info,operation'
        ])
        ->where('id', $request->order_id)
        ->where('consultation_id', $request->consultation_id)
        ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or consultation mismatch'
            ], 404);
        }

        // Check if order is approved
        if ($order->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Order is not approved',
                'current_status' => $order->status
            ], 400);
        }

        // Update status to "in_clinic"
        $order->status = 'in_clinic';

        $order->save();

        // Prepare the response data
        $response = [
            'success' => true,
            'message' => 'Order status updated to in_clinic',
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'clinic_note' => $order->clinic_note,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ],
            'consultation' => [
                'id' => $order->consultation->id,
                'description' => $order->consultation->description,
                'admin_notes' => $order->consultation->admin_notes,
                'operation' => $order->consultation->operation,
                'status' => $order->consultation->status,
                'pet' => [
                    'id' => $order->consultation->pet->id,
                    'name' => $order->consultation->pet->name,
                    'type' => $order->consultation->pet->type,
                    'gender' => $order->consultation->pet->gender,
                ],
                'user' => [
                    'id' => $order->consultation->user->id,
                    'name' => $order->consultation->user->name,
                    'email' => $order->consultation->user->email,
                ],
                'answer' => $order->consultation->anwer_cons ? [
                    'clinic_info' => json_decode($order->consultation->anwer_cons->clinic_info, true),
                    'operation' => $order->consultation->anwer_cons->operation,
                ] : null
            ]
        ];

        return response()->json($response);
    }

    /**
     * Display the specified resource.
     */
/**
 * Complete order and create medical records
 */
    public function completeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:order__clinics,id',
            'price_order' => 'required|numeric|min:0',
            'have_discount' => 'required|boolean',
            'discount_percent' => 'required_if:have_discount,true|numeric|min:0|max:100|nullable',
            'medical_details' => 'required|string',
            'clinic_note' => 'required|string'
        ], [
            // ... your existing validation messages ...
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $clinic = auth()->user()->clinic;
        if (!$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'عيادة غير مسجلة'
            ], 404);
        }

        $order = Order_Clinic::with(['consultation.pet'])
            ->where('id', $request->order_id)
            ->where('clinic_id', $clinic->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود أو لا ينتمي إلى هذه العيادة'
            ], 404);
        }

        if ($order->status !== 'in_clinic') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إكمال الطلب إلا إذا كان بحالة "في العيادة"',
                'current_status' => $order->status
            ], 400);
        }

        // Calculate prices
        $discountAmount = 0;
        $finalPrice = $request->price_order;

        if ($request->have_discount && $request->discount_percent) {
            $discountAmount = ($request->price_order * $request->discount_percent) / 100;
            $finalPrice = $request->price_order - $discountAmount;
        }

        // Update order
        $order->update([
            'status' => 'complete',
            'price_order' => $request->price_order,
            'have_discount' => $request->have_discount,
            'discount_percent' => $request->have_discount ? $request->discount_percent : null,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'clinic_note' => $request->clinic_note
        ]);

        // Create pill
        $pill = $order->pill()->create([
            'medical_details' => $request->medical_details,
            'clinic_note' => $request->clinic_note,
            'price_order' => $request->price_order,
            'have_discount' => $request->have_discount,
            'discount_percent' => $request->have_discount ? $request->discount_percent : null,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'issued_at' => now()
        ]);

        // Create medical record
        if ($order->consultation && $order->consultation->pet) {
            $medicalRecord = $clinic->medicalRecords()->create([
                'details' => $request->medical_details,
                'date' => now(),
                'pet_id' => $order->consultation->pet->id
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إكمال الطلب بنجاح وإنشاء الفاتورة والسجل الطبي',
            'order' => $order->fresh(),
            'pill' => $pill,
            'medical_record' => $medicalRecord ?? null
        ]);
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
