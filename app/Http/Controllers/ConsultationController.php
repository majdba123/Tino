<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Anwer_Cons;
use App\Models\Clinic;
use App\Models\Consultation;
use App\Models\Order_Clinic;
use App\Models\Pet;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User_Subscription;
use Illuminate\Support\Facades\Validator;

class ConsultationController extends Controller
{
    // إنشاء استشارة جديدة
    public function store(Request $request)
    {
        // التحقق من وجود اشتراك فعال أولاً
        $subscriptionCheck = $this->checkActiveSubscription(Auth::id());
        if (!$subscriptionCheck['success']) {
            return response()->json([
                'success' => false,
                'message' => $subscriptionCheck['message']
            ], 403);
        }

        $request->validate([
            'pet_id' => 'required|exists:pets,id,user_id,'.Auth::id(),
            'description' => 'required|string|max:1000',
        ]);

        $consultation = Consultation::create([
            'user_id' => Auth::id(),
            'pet_id' => $request->pet_id,
            'operation' => "none",
            'description' => $request->description,
            'status' => Consultation::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consultation created successfully',
            'data' => $consultation
        ], 201);
    }

    // عرض جميع استشارات المستخدم
    public function index(Request $request)
    {
        // التحقق من وجود معامل الفلترة في الطلب
        $operationStatus = $request->query('operation_status');
        $status = $request->query('status');
        $pet_id = $request->query('pet_id');
        $user_id = $request->query('user_id');

        // بناء الاستعلام الأساسي
        $query = Consultation::query()->with('pet','anwer_cons')->latest(); // الترتيب حسب الأحدث (حقل created_at)

        // إذا لم يكن المستخدم مدير (admin)، نضيف شرط user_id
        if (Auth::user()->type !== 'admin') {
            $query->where('user_id', Auth::id());
        }

        // تطبيق الفلترة إذا تم تحديد operation_status
        if ($operationStatus) {
            $query->where('operation', $operationStatus);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($pet_id) {
            $query->where('pet_id', $pet_id);
        }
        if ($user_id) {
            $query->where('user_id', $user_id);
        }


        // تطبيق التقسيم إلى صفحات مع 10 عناصر لكل صفحة (يمكن تغيير الرقم حسب الحاجة)
        $consultations = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $consultations
        ]);
    }
    // عرض استشارة معينة
    public function show($id)
    {
        $consultation = Consultation::with('pet')->find($id);

        // إذا لم يكن المستخدم مديراً (admin) ولم يكن صاحب الاستشارة
        if (Auth::user()->type !== 'admin' && $consultation->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $consultation
        ]);
    }


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
        ], [
            'operation.required' => 'حالة العملية مطلوبة',
            'operation.in' => 'حالة العملية يجب أن تكون call أو inside أو outside',
            'admin_notes.required_if' => 'ملاحظات المدير مطلوبة في حالة call أو outside أو inside',
            'admin_notes.string' => 'ملاحظات المدير يجب أن تكون نصية',
            'clinic_id.required_if' => 'معرف العيادة مطلوب عندما تكون العملية داخلية أو خارجية',
            'clinic_id.exists' => 'العيادة المحددة غير موجودة'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $operation = request('operation');
        $consultation->operation = $operation;
        $consultation->admin_notes = request('admin_notes');
        $responseData = [];

        if ($operation === 'call') {
            $consultation->status = "complete";
            $responseData['message'] = 'تم تحويل الاستشارة إلى مكالمة بنجاح';
        }
        elseif (in_array($operation, ['outside', 'inside'])) {
            $clinic = Clinic::find(request('clinic_id'));

            // التحقق من أن العيادة نشطة
            if (!$clinic || $clinic->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب اختيار عيادة نشطة'
                ], 422);
            }

            if ($operation === 'outside') {
                if ($clinic->type !== 'external') {
                    return response()->json([
                        'success' => false,
                        'message' => 'يجب اختيار عيادة خارجية للعملية الخارجية'
                    ], 422);
                }

                // إنشاء سجل Anwer_Cons مع معلومات العيادة
                Anwer_Cons::updateOrCreate(
                    ['consultation_id' => $consultation->id],
                    [
                        'clinic_info' => json_encode([
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


                        ]),
                        'operation' => $operation
                    ]
                );

                $consultation->status = "complete";
                $responseData['message'] = 'تم تحويل الاستشارة إلى عيادة خارجية بنجاح';
                $responseData['clinic'] = $clinic;
            }
            else { // حالة inside
                if ($clinic->type !== 'integrated') {
                    return response()->json([
                        'success' => false,
                        'message' => 'يجب اختيار عيادة داخلية للعملية الداخلية'
                    ], 422);
                }

                $consultation->status = "reviewed";
                $responseData['message'] = 'تم تحويل الاستشارة إلى عيادة داخلية بنجاح ويجب مراجعتها';

                // إنشاء سجل Order_Clinic في حالة inside
                Order_Clinic::create([
                    'consultation_id' => $consultation->id,
                    'clinic_id' => $clinic->id,
                    'status' => 'pending',
                    'operation' => $operation
                ]);
            }
        }

        $consultation->save();

        return response()->json([
            'success' => true,
            'data' => array_merge($consultation->toArray(), $responseData)
        ]);
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

        // يمكنك إضافة المزيد من الشروط هنا إذا لزم الأمر
        // مثل التحقق من نوع الاشتراك أو عدد الاستشارات المتبقية

        return [
            'success' => true,
            'message' => 'User has active subscription',
            'subscription' => $activeSubscription
        ];
    }



    /**
 * إعادة تحويل الاستشارة إلى عيادة أخرى
 */
public function reassignToClinic($consultationId)
{
    $consultation = Consultation::find($consultationId);

    // التحقق من وجود الاستشارة
    if (!$consultation) {
        return response()->json([
            'success' => false,
            'message' => 'الاستشارة غير موجودة'
        ], 404);
    }

    // التحقق من أن حالة الاستشارة مرفوضة
    if ($consultation->status !== 'disapproved') {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن إعادة التحويل إلا للاستشارات المرفوضة'
        ], 400);
    }

    $validator = Validator::make(request()->all(), [
        'clinic_id' => 'required|exists:clinics,id',
    ], [
        'clinic_id.required' => 'معرف العيادة مطلوب',
        'clinic_id.exists' => 'العيادة المحددة غير موجودة',

    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $clinic = Clinic::find(request('clinic_id'));

    // التحقق من أن العيادة نشطة
    if (!$clinic || $clinic->status !== 'active') {
        return response()->json([
            'success' => false,
            'message' => 'يجب اختيار عيادة نشطة'
        ], 422);
    }

    DB::beginTransaction();
    try {
        // تحديث حالة الاستشارة
        $consultation->update([
            'status' => 'reviewed',
        ]);

        // إنشاء سجل جديد في Order_Clinic
        $order = Order_Clinic::create([
            'consultation_id' => $consultation->id,
            'clinic_id' => $clinic->id,
            'status' => 'pending',
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة تحويل الاستشارة إلى العيادة الجديدة بنجاح',
            'data' => [
                'consultation' => $consultation,
                'new_clinic' => $clinic,
                'order' => $order
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء إعادة التحويل',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
