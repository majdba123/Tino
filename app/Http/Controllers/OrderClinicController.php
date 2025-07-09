<?php

namespace App\Http\Controllers;

use App\Models\Anwer_Cons;
use App\Models\Clinic;
use App\Models\MedicalRecord;
use App\Models\Order_Clinic;
use App\Models\Pill;
use App\Models\User_Notification;
use App\Events\chat1;
use App\Models\User_Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderClinicController extends Controller
{
    /**
     * عرض جميع طلبات العيادة مع التقسيم
     */
    public function getClinicOrders(Request $request)
    {
        $user = auth()->user();
        $perPage = $request->input('per_page', 10); // 10 عناصر في الصفحة افتراضياً

        $query = Order_Clinic::with([
                'consultation:id,user_id,pet_id,description,status,admin_notes,operation',
                'consultation.pet:id,name,type,gender',
                'consultation.user:id,name,email',
                'consultation.pet.medicalRecords:id,details,date,pet_id',
                'consultation.anwer_cons:id,consultation_id,clinic_info,operation'
            ])
            ->latest(); // الترتيب حسب الأحدث

        if ($user->type != "admin") {
            $clinic = $user->clinic;

            if (!$clinic) {
                return response()->json([
                    'success' => false,
                    'message' => 'عيادة غير مسجلة'
                ], 404);
            }

            $query->where('clinic_id', $clinic->id);
        }

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

        $orders = $query->paginate($perPage);

        $formattedOrders = $orders->map(function ($order) {
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
        });

        return response()->json([
            'success' => true,
            'data' => $formattedOrders,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    /**
     * تحديث حالة الطلب
     */
    public function updateOrderStatus(Request $request, $orderId)
    {
        $clinic = auth()->user()->clinic;

        if (!$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'العيادة غير مسجلة'
            ], 403);
        }

        $order = Order_Clinic::with('consultation')->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        if ($order->clinic_id !== $clinic->id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتحديث هذا الطلب'
            ], 403);
        }

        if (!in_array($request->status, ['approved', 'disapproved'])) {
            return response()->json([
                'success' => false,
                'message' => 'حالة غير صحيحة'
            ], 400);
        }

        if ($request->status === 'approved') {
            $validator = Validator::make($request->all(), [
                'visit_date' => 'sometimes|date|after_or_equal:today',
                'visit_time' => 'sometimes|date_format:H:i'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
        }

        DB::transaction(function () use ($order, $request, $clinic) {
            $order->status = $request->status;
            $order->save();

            $consultationId = $order->consultation->id;
            $userName = $order->consultation->user->name;
            $clinicName = $clinic->user->name;

            if ($request->status === 'approved') {
                $order->consultation->status = 'complete';

                $clinicInfo = [
                    'clinic_id' => $clinic->id,
                    'Order_id' => $order->id,
                    'name' => $clinicName,
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

                $userMessage = "تم قبول طلب الاستشارة رقم $consultationId من قبل عيادة $clinicName";
                $clinicMessage = "لقد قمت بقبول طلب الاستشارة رقم $consultationId للمستخدم $userName";
                $adminMessage = "تم قبول طلب الاستشارة رقم $consultationId من قبل عيادة $clinicName للمستخدم $userName";
            } else {
                $order->consultation->status = 'disapproved';
                $userMessage = "نأسف لإبلاغك بأن طلب الاستشارة رقم $consultationId قد تم رفضه من قبل عيادة $clinicName";
                $clinicMessage = "لقد قمت برفض طلب الاستشارة رقم $consultationId للمستخدم $userName";
                $adminMessage = "تم رفض طلب الاستشارة رقم $consultationId من قبل عيادة $clinicName للمستخدم $userName";
            }

            $order->consultation->save();

            // إنشاء الإشعارات
            $notifications = [
                User_Notification::create([
                    'user_id' => $order->consultation->user_id,
                    'message' => $userMessage,
                    'is_read' => false
                ]),
                User_Notification::create([
                    'user_id' => $clinic->user_id,
                    'message' => $clinicMessage,
                    'is_read' => false
                ]),
                User_Notification::create([
                    'user_id' => 1, // ID الإدمن
                    'message' => $adminMessage,
                    'is_read' => false,
                    'is_admin' => true
                ])
            ];

            // بث الإشعارات
            foreach ($notifications as $notification) {
                broadcast(new chat1([
                    'user_id' => $notification->user_id,
                    'message' => $notification->message,
                    'consultation_id' => $consultationId,
                    'notification_id' => $notification->id,
                    'is_admin' => $notification->is_admin ?? false
                ]))->toOthers();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'order_status' => $order->status,
            'consultation_status' => $order->consultation->status
        ]);
    }

    /**
     * تغيير حالة الطلب إلى "في العيادة"
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

        $order = Order_Clinic::with([
                'consultation:id,user_id,pet_id,description,admin_notes,operation,status',
                'consultation.pet:id,name,type,gender',
                'consultation.user:id,name,email',
                'consultation.anwer_cons:id,consultation_id,clinic_info,operation',
                'clinic.user:id,name'
            ])
            ->where('id', $request->order_id)
            ->where('consultation_id', $request->consultation_id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود أو لا يتطابق مع الاستشارة'
            ], 404);
        }

        if ($order->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تغيير الحالة إلا للطلبات المقبولة',
                'current_status' => $order->status
            ], 400);
        }

        DB::transaction(function () use ($order) {
            $order->status = 'in_clinic';
            $order->save();

            $userMessage = "تم تغيير حالة طلبك رقم {$order->id} إلى 'في العيادة'";
            $clinicMessage = "تم تغيير حالة الطلب رقم {$order->id} إلى 'في العيادة'";
            $adminMessage = "تم تغيير حالة الطلب رقم {$order->id} إلى 'في العيادة'";

            $notifications = [
                User_Notification::create([
                    'user_id' => $order->consultation->user_id,
                    'message' => $userMessage,
                    'is_read' => false
                ]),
                User_Notification::create([
                    'user_id' => $order->clinic->user_id,
                    'message' => $clinicMessage,
                    'is_read' => false
                ]),
                User_Notification::create([
                    'user_id' => 1,
                    'message' => $adminMessage,
                    'is_read' => false,
                    'is_admin' => true
                ])
            ];

            foreach ($notifications as $notification) {
                broadcast(new chat1([
                    'user_id' => $notification->user_id,
                    'message' => $notification->message,
                    'consultation_id' => $order->consultation_id,
                    'notification_id' => $notification->id,
                    'is_admin' => $notification->is_admin ?? false
                ]))->toOthers();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الطلب إلى "في العيادة" بنجاح'
        ]);
    }

    /**
     * إكمال الطلب وإنشاء الفاتورة والسجل الطبي
     */
    public function completeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:order__clinics,id',
            'have_discount' => 'required|boolean',
            'discount_percent' => 'required_if:have_discount,true|numeric|min:0|max:100',
            'invoice_services' => 'required|array|min:1',
            'invoice_services.*.name' => 'required|string|max:255',
            'invoice_services.*.price' => 'required|numeric|min:0',
            'medical_services' => 'required|array|min:1',
            'medical_services.*.procedure' => 'required|string|max:255',
            'medical_services.*.description' => 'required|string',
            'clinic_note' => 'required|string',
            'tax_percent' => 'required|numeric|min:0|max:100',
            'service_date' => 'required|date'
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

        $order = Order_Clinic::with(['consultation.pet.user', 'clinic'])
            ->where('id', $request->order_id)
            ->where('clinic_id', $clinic->id)
            ->firstOrFail();

        if ($order->status !== 'in_clinic') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إكمال الطلب إلا إذا كان بحالة "في العيادة"'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $subtotal = collect($request->invoice_services)->sum('price');
            $taxAmount = ($subtotal * $request->tax_percent) / 100;
            $discountAmount = $request->have_discount ? ($subtotal * $request->discount_percent) / 100 : 0;
            $finalPrice = ($subtotal - $discountAmount) + $taxAmount;

            $order->update([
                'status' => 'complete',
                'price_order' => $subtotal,
                'have_discount' => $request->have_discount,
                'discount_percent' => $request->discount_percent,
                'discount_amount' => $discountAmount,
                'final_price' => $finalPrice,
                'clinic_note' => $request->clinic_note
            ]);

            $pill = $order->pill()->create([
                'invoice_number' => 'INV-' . date('Y') . '-' . str_pad(Pill::count() + 1, 4, '0', STR_PAD_LEFT),
                'invoice_services' => collect($request->invoice_services)->map(function ($service) {
                    return [
                        'name' => $service['name'],
                        'price' => (float) $service['price'],
                        'quantity' => $service['quantity'] ?? 1,
                        'total' => (float) ($service['price'] * ($service['quantity'] ?? 1))
                    ];
                }),
                'tax_percent' => $request->tax_percent,
                'tax_amount' => $taxAmount,
                'discount_percent' => $request->have_discount ? $request->discount_percent : 0,
                'discount_amount' => $discountAmount,
                'total_amount' => $finalPrice,
                'service_date' => $request->service_date,
                'insurance_info' => $request->insurance_info,
                'payment_notes' => $request->payment_notes
            ]);

            if ($order->consultation?->pet) {
                MedicalRecord::create([
                    'pet_id' => $order->consultation->pet->id,
                    'details' => $request->clinic_note,
                    'treatment' => $request->payment_notes,
                    'services' => $request->medical_services,
                    'date' => now(),
                    'order_id' => $order->id
                ]);
            }

            User_Review::create([
                'user_id' => $order->consultation->pet->user->id,
                'clinic_id' => $clinic->id,
                'status' => 'pending'
            ]);

            $user = $order->consultation->pet->user;
            $petName = $order->consultation->pet->name;

            $userMessage = "تم إكمال الخدمة لحيوانك الأليف {$petName} في عيادة {$clinic->user->name}";
            $clinicMessage = "تم إكمال الطلب رقم {$order->id} للحيوان الأليف {$petName}";
            $adminMessage = "تم إكمال الطلب رقم {$order->id} في عيادة {$clinic->user->name}";

            $notifications = [
                User_Notification::create([
                    'user_id' => $user->id,
                    'message' => $userMessage,
                    'is_read' => false
                ]),
                User_Notification::create([
                    'user_id' => $clinic->user_id,
                    'message' => $clinicMessage,
                    'is_read' => false
                ]),
                User_Notification::create([
                    'user_id' => 1,
                    'message' => $adminMessage,
                    'is_read' => false,
                    'is_admin' => true
                ])
            ];

            foreach ($notifications as $notification) {
                broadcast(new chat1([
                    'user_id' => $notification->user_id,
                    'message' => $notification->message,
                    'consultation_id' => $order->consultation_id,
                    'notification_id' => $notification->id,
                    'is_admin' => $notification->is_admin ?? false
                ]))->toOthers();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إكمال الطلب بنجاح',
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'status' => $order->status,
                        'final_price' => $order->final_price
                    ],
                    'invoice' => [
                        'number' => $pill->invoice_number,
                        'total' => $pill->total_amount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'فشل في إتمام العملية: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل طلب معين
     */
    public function showOrder($orderId)
    {
        $user = auth()->user();
        $clinic = $user->clinic;

        if ($user->type != "admin" && !$clinic) {
            return response()->json([
                'success' => false,
                'message' => 'عيادة غير مسجلة'
            ], 404);
        }

        $query = Order_Clinic::with([
            'consultation:id,user_id,pet_id,description,status,admin_notes,operation',
            'consultation.pet:id,name,type,gender',
            'consultation.user:id,name,email',
            'consultation.pet.medicalRecords:id,details,date,pet_id',
            'consultation.anwer_cons:id,consultation_id,clinic_info,operation'
        ]);

        if ($user->type != "admin") {
            $query->where('clinic_id', $clinic->id);
        }

        $order = $query->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير موجود أو لا ينتمي إلى هذه العيادة'
            ], 404);
        }

        $clinicInfo = $order->consultation->anwer_cons
            ? json_decode($order->consultation->anwer_cons->clinic_info, true)
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'price_order' => $order->price_order,
                'discount_amount' => $order->discount_amount,
                'final_price' => $order->final_price,
                'clinic_note' => $order->clinic_note,
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
            ]
        ]);
    }
}
