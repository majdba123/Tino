<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Anwer_Cons;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Order_Clinic;
use App\Models\Pet;
use App\Models\User_Notification;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User_Subscription;
use Illuminate\Support\Facades\Validator;
use App\Events\chat1;

class ConsultationController extends Controller
{
    /**
     * إنشاء استشارة جديدة
     */
    public function store(Request $request)
    {
        $subscriptionCheck = $this->checkActiveSubscription(Auth::id());
        if (!$subscriptionCheck['success']) {
            return response()->json([
                'success' => false,
                'message' => $subscriptionCheck['message']
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'pet_id' => 'required|exists:pets,id,user_id,'.Auth::id(),
            'description' => 'required|string|max:1000',
            'level_urgency' => 'required|in:low,medium,high', // قيم محددة: منخفض، متوسط، عالي
            'contact' => 'required|in:phone,video_call', // قيم محددة: هاتف أو مكالمة فيديو
            'data_available' => 'required|string|max:1000',
            'type_con' => 'required|in:normal,emergency',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $consultation = Consultation::create([
            'user_id' => Auth::id(),
            'pet_id' => $request->pet_id,
            'operation' => "none",
            'description' => $request->description,
            'level_urgency' => $request->level_urgency, // إضافة مستوى الطوارئ
            'contact_method' => $request->contact, // إضافة طريقة التواصل
            'data_available' => $request->data_available,
            'type_con' => $request->type_con,
            'status' => Consultation::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الاستشارة بنجاح',
            'data' => $consultation
        ], 201);
    }

    /**
     * عرض جميع استشارات المستخدم مع التقسيم
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10); // 10 عناصر في الصفحة افتراضياً

        $query = Consultation::with(['pet', 'anwer_cons'])
            ->latest();

        if (Auth::user()->type !== 'admin' && Auth::user()->type !== '2') {
            $query->where('user_id', Auth::id());
        }

        // تطبيق الفلاتر
        if ($request->filled('operation_status')) {
            $query->where('operation', $request->operation_status);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('pet_id')) {
            $query->where('pet_id', $request->pet_id);
        }

        if ($request->filled('user_id') && (Auth::user()->type === 'admin' || Auth::user()->type === '2')) {
            $query->where('user_id', $request->user_id);
        }

        $consultations = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $consultations->items(),
            'meta' => [
                'current_page' => $consultations->currentPage(),
                'last_page' => $consultations->lastPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
            ]
        ]);
    }

    /**
     * عرض استشارة معينة
     */
    public function show($id)
    {
        $consultation = Consultation::with('pet')->find($id);

        if (!$consultation) {
            return response()->json([
                'success' => false,
                'message' => 'الاستشارة غير موجودة'
            ], 404);
        }

        if (Auth::user()->type !== 'admin' && $consultation->user_id !== Auth::id() && Auth::user()->type !== '2') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالوصول'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $consultation
        ]);
    }
    public function cancell_con($id)
    {
        $consultation = Consultation::with('pet')->find($id);

        if (!$consultation) {
            return response()->json([
                'success' => false,
                'message' => 'الاستشارة غير موجودة'
            ], 404);
        }

        // التحقق من الصلاحية: إما أن يكون المستخدم admin أو صاحب الاستشارة أو نوعه 2
        if (Auth::user()->type !== 'admin' && $consultation->user_id !== Auth::id() && Auth::user()->type !== '2') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالوصول'
            ], 403);
        }

        // التحقق مما إذا كانت الاستشارة قابلة للإلغاء (ليست مكتملة أو ملغاة مسبقاً)
        if ($consultation->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء استشارة مكتملة'
            ], 400);
        }

        if ($consultation->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'الاستشارة ملغاة بالفعل'
            ], 400);
        }

        // تحديث حالة الاستشارة إلى "ملغاة"
        $consultation->update([
            'status' => 'cancelled',
            'cancelled_at' => now() // يمكنك إضافة حقل cancelled_at لتسجيل وقت الإلغاء
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الاستشارة بنجاح',
            'data' => $consultation->fresh()
        ]);
    }

    /**
     * تغيير نوع العملية للاستشارة
     */
    public function change_operation($id)
    {
        $consultation = Consultation::find($id);

        if (!$consultation) {
            return response()->json([
                'success' => false,
                'message' => 'الاستشارة غير موجودة'
            ], 404);
        }

        $validator = Validator::make(request()->all(), [
            'operation' => 'required|in:call,inside,outside',
            'admin_notes' => 'required_if:operation,call,outside,inside|string',
            'clinic_id' => 'required_if:operation,outside,inside|exists:clinics,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::transaction(function () use ($consultation) {
            $operation = request('operation');
            $consultation->operation = $operation;
            $consultation->admin_notes = request('admin_notes');
            $responseData = [];

            if ($operation === 'call') {
                $consultation->status = "complete";
                $responseData['message'] = 'تم تحويل الاستشارة إلى مكالمة بنجاح';

                $this->sendNotification(
                    $consultation->user_id,
                    "تم تحويل استشارتك رقم {$consultation->id} إلى مكالمة وسيتم التواصل معك قريباً",
                    $consultation->id
                );

                $this->sendNotification(
                    1,
                    "تم تحويل الاستشارة رقم {$consultation->id} إلى مكالمة",
                    $consultation->id,
                    true
                );

            } elseif (in_array($operation, ['outside', 'inside'])) {
                $clinic = Clinic::find(request('clinic_id'));

                if (!$clinic || $clinic->status !== 'active') {
                    throw new \Exception('يجب اختيار عيادة نشطة');
                }

                if ($operation === 'outside') {
                    if ($clinic->type !== 'external') {
                        throw new \Exception('يجب اختيار عيادة خارجية للعملية الخارجية');
                    }

                    Anwer_Cons::updateOrCreate(
                        ['consultation_id' => $consultation->id],
                        [
                            'clinic_info' => json_encode([]),
                            'operation' => $operation
                        ]
                    );

                    $consultation->status = "complete";
                    $responseData['message'] = 'تم تحويل الاستشارة إلى عيادة خارجية بنجاح';
                    $responseData['clinic'] = $clinic;

                    $this->sendNotification(
                        $consultation->user_id,
                        "تم تحويل استشارتك رقم {$consultation->id} إلى عيادة {$clinic->user->name} الخارجية",
                        $consultation->id
                    );

                    $this->sendNotification(
                        $clinic->user_id,
                        "لديك استشارة جديدة رقم {$consultation->id} محولة إليك",
                        $consultation->id
                    );

                    $this->sendNotification(
                        1,
                        "تم تحويل الاستشارة رقم {$consultation->id} إلى العيادة الخارجية {$clinic->user->name}",
                        $consultation->id,
                        true
                    );

                } else {
                    if ($clinic->type !== 'integrated') {
                        throw new \Exception('يجب اختيار عيادة داخلية للعملية الداخلية');
                    }

                    $consultation->status = "reviewed";
                    $responseData['message'] = 'تم تحويل الاستشارة إلى عيادة داخلية بنجاح ويجب مراجعتها';

                    $this->sendNotification(
                        $consultation->user_id,
                        "تم تحويل استشارتك رقم {$consultation->id} إلى عيادة {$clinic->user->name} الداخلية",
                        $consultation->id
                    );

                    $this->sendNotification(
                        $clinic->user_id,
                        "لديك استشارة جديدة رقم {$consultation->id} تحتاج للمراجعة",
                        $consultation->id
                    );

                    $this->sendNotification(
                        1,
                        "تم تحويل الاستشارة رقم {$consultation->id} إلى العيادة الداخلية {$clinic->user->name}",
                        $consultation->id,
                        true
                    );

                    Order_Clinic::create([
                        'consultation_id' => $consultation->id,
                        'clinic_id' => $clinic->id,
                        'status' => 'pending',
                        'operation' => $operation
                    ]);
                }
            }

            $consultation->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث نوع العملية بنجاح',
            'data' => $consultation
        ]);
    }

    /**
     * إعادة تحويل الاستشارة إلى عيادة أخرى
     */
    public function reassignToClinic($consultationId)
    {
        $consultation = Consultation::find($consultationId);

        if (!$consultation) {
            return response()->json([
                'success' => false,
                'message' => 'الاستشارة غير موجودة'
            ], 404);
        }

        if ($consultation->status !== 'disapproved') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إعادة التحويل إلا للاستشارات المرفوضة'
            ], 400);
        }

        $validator = Validator::make(request()->all(), [
            'clinic_id' => 'required|exists:clinics,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $clinic = Clinic::find(request('clinic_id'));

        if (!$clinic || $clinic->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'يجب اختيار عيادة نشطة'
            ], 422);
        }

        DB::transaction(function () use ($consultation, $clinic) {
            $consultation->update(['status' => 'reviewed']);

            Order_Clinic::create([
                'consultation_id' => $consultation->id,
                'clinic_id' => $clinic->id,
                'status' => 'pending'
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة تحويل الاستشارة بنجاح',
            'data' => [
                'consultation' => $consultation,
                'new_clinic' => $clinic
            ]
        ]);
    }

    /**
     * دالة مساعدة لإرسال الإشعارات
     */
    private function sendNotification($userId, $message, $consultationId, $isAdmin = false)
    {
        $notification = User_Notification::create([
            'user_id' => $userId,
            'message' => $message,
            'is_read' => false,
            'is_admin' => $isAdmin
        ]);

        broadcast(new chat1([
            'user_id' => $userId,
            'message' => $message,
            'consultation_id' => $consultationId,
            'notification_id' => $notification->id,
            'is_admin' => $isAdmin
        ]))->toOthers();
    }

    /**
     * التحقق من وجود اشتراك فعال للمستخدم
     */
    private function checkActiveSubscription($userId)
    {
        $activeSubscription = User_Subscription::where('user_id', $userId)
            ->where('end_date', '>=', now())
            ->where('is_active', true)
            ->first();

        if (!$activeSubscription) {
            return [
                'success' => false,
                'message' => 'يجب أن يكون لديك اشتراك فعال لإنشاء استشارة جديدة'
            ];
        }

        return [
            'success' => true,
            'message' => 'User has active subscription',
            'subscription' => $activeSubscription
        ];
    }
}
