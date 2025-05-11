<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Consultation;
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
        $query = Consultation::query()->with('pet')->latest(); // الترتيب حسب الأحدث (حقل created_at)

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
    // البحث عن الاستشارة
    $consultation = Consultation::with('pet')->find($id);

    // التحقق من وجود الاستشارة
    if (!$consultation) {
        return response()->json([
            'success' => false,
            'message' => 'الاستشارة غير موجودة'
        ], 404);
    }

    // التحقق من البيانات المدخلة
    $validator = Validator::make(request()->all(), [
        'operation' => 'required|in:call,chat,visit,inside,outside',
        'admin_notes' => 'required_if:operation,call,outside|string',
        'clinic_id' => 'required_if:operation,outside|exists:clinics,id'
    ], [
        'operation.required' => 'حالة العملية مطلوبة',
        'operation.in' => 'حالة العملية يجب أن تكون call, chat, visit, inside أو outside',
        'admin_notes.required_if' => 'ملاحظات المدير مطلوبة في حالة call أو outside',
        'admin_notes.string' => 'ملاحظات المدير يجب أن تكون نصية',
        'clinic_id.required_if' => 'معرف العيادة مطلوب عندما تكون العملية خارجية',
        'clinic_id.exists' => 'العيادة المحددة غير موجودة'
    ]);

    // في حالة فشل التحقق
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // إذا كانت العملية outside، التحقق من نوع العيادة
    if (request('operation') === 'outside') {
        $clinic = Clinic::find(request('clinic_id'));

        if (!$clinic || $clinic->type !== 'external') {
            return response()->json([
                'success' => false,
                'message' => 'يجب اختيار عيادة خارجية للعملية الخارجية'
            ], 422);
        }
    }

    // تحديث بيانات الاستشارة
    $consultation->operation = request('operation');
    $consultation->admin_notes = request('admin_notes');

    // إذا كانت العملية اتصال (call)
    if (request('operation') === 'call') {
        $consultation->status = 'complete';
    }
    // إذا كانت العملية خارجية (outside)
    elseif (request('operation') === 'outside') {
        $consultation->status = 'complete';
    }

    $consultation->save();

    return response()->json([
        'success' => true,
        'message' => 'تم تحديث حالة الاستشارة بنجاح',
        'data' => $consultation
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
}
