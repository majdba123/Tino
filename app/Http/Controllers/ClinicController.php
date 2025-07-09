<?php

namespace App\Http\Controllers;

use App\Models\clinic;
use App\Http\Requests\ClinicRequest;
use App\Http\Requests\UpdateClinicRequest;
use App\Http\Requests\FilterClinicRequest;
use Symfony\Component\HttpFoundation\Response;

use App\Services\ClinicService;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    protected $clinicService;

    public function __construct(ClinicService $clinicService)
    {
        $this->clinicService = $clinicService;
    }

    public function store(ClinicRequest $request)
    {
        $clinic = $this->clinicService->createClinic($request->validated());
        return response()->json($clinic, 201);
    }

// في App\Http\Controllers\ClinicController.php
    public function update(UpdateClinicRequest $request, $id)
    {
        $validated = $request->validated();

        // إزالة الحقول التي لا نريد تحديثها في العيادة
        unset($validated['email'], $validated['password']);

        $clinic = $this->clinicService->updateClinic($id, $validated);
        return response()->json($clinic);
    }


    public function filter(FilterClinicRequest $request)
    {
        $validated = $request->validated();

        $query = Clinic::with(['user' => function($query) {
            $query->select('id', 'name');
        }])->latest(); // إضافة الترتيب حسب الأحدث هنا

        // فلترة حسب الحقول الأساسية
        $this->applyFilters($query, $validated, [
            'address' => 'like',
            'phone' => 'like',
            'opening_time' => '=',
            'closing_time' => '=',
            'type' => '=',
            'status' => '='
        ]);

        // فلترة حسب اسم المستخدم
        if (isset($validated['name'])) {
            $query->whereHas('user', function($q) use ($validated) {
                $q->where('name', 'like', '%' . $validated['name'] . '%');
            });
        }

        // تطبيق التقسيم (Pagination)
        $perPage = $request->get('per_page', 15); // 15 عنصر في الصفحة افتراضياً
        $clinics = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'تم تصفية العيادات بنجاح',
            'data' => $clinics->items(),
            'meta' => [
                'current_page' => $clinics->currentPage(),
                'last_page' => $clinics->lastPage(),
                'per_page' => $clinics->perPage(),
                'total' => $clinics->total(),
            ]
        ]);
    }
    // دالة مساعدة لتطبيق الفلاتر
    protected function applyFilters($query, $filters, $filterMap)
    {
        foreach ($filterMap as $field => $operator) {
            if (isset($filters[$field])) {
                $value = $filters[$field];

                if ($operator === 'like') {
                    $query->where($field, 'like', '%' . $value . '%');
                } else {
                    $query->where($field, $operator, $value);
                }
            }
        }
    }


    public function show($id)
    {
        $clinic = Clinic::with(['user' => function($query) {
            $query->select('id', 'name', 'email');
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $clinic
        ]);
    }



    public function destroy($id)
    {
        $clinic = Clinic::findOrFail($id);

        // حذف المستخدم المرتبط إذا كان متكاملاً مع النظام
        if ($clinic->type === 'integrated') {
            $clinic->user()->delete();
        }

        $clinic->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العيادة بنجاح'
        ], Response::HTTP_OK);
    }

    // Add this method to your ClinicController
    public function getClinicStatistics($id = null)
    {
        // If no ID provided, get clinic from authenticated user
        if ($id === null) {
            $user = auth()->user();
            if (!$user->clinic) {
                return response()->json([
                    'success' => false,
                    'message' => 'No clinic associated with this user'
                ], 404);
            }
            $clinic = $user->clinic;
        } else {
            $clinic = Clinic::find($id);
            if (!$clinic) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clinic not found'
                ], 404);
            }
        }

        // Get all orders for this clinic
        $orders = $clinic->Order_Clinic;

        // Calculate statistics
        $totalOrders = $orders->count();
        $approvedOrders = $orders->where('status', 'approved')->count();
        $disapprovedOrders = $orders->where('status', 'disapproved')->count();
        $completedOrders = $orders->where('status', 'complete')->count();
        $pendingOrders = $orders->where('status', 'pending')->count();

        // Calculate financial statistics
        $totalRevenue = $orders->where('status', 'complete')->sum('final_price');
        $totalDiscounts = $orders->where('status', 'complete')->sum('discount_amount');
        $averageOrderValue = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0;

        // Get orders with discount
        $discountedOrders = $orders->where('have_discount', true)->count();
        $discountPercentage = $totalOrders > 0 ? ($discountedOrders / $totalOrders) * 100 : 0;

        // Group by status for more detailed analysis
        $statusDistribution = $orders->groupBy('status')->map->count();

        return response()->json([
            'success' => true,
            'data' => [
                'clinic_id' => $clinic->id,
                'clinic_name' => $clinic->user->name,
                'total_orders' => $totalOrders,
                'order_status' => [
                    'approved' => $approvedOrders,
                    'disapproved' => $disapprovedOrders,
                    'completed' => $completedOrders,
                    'pending' => $pendingOrders,
                    'status_distribution' => $statusDistribution,
                ],
                'financial_statistics' => [
                    'total_revenue' => $totalRevenue,
                    'total_discounts' => $totalDiscounts,
                    'average_order_value' => round($averageOrderValue, 2),
                    'discounted_orders_count' => $discountedOrders,
                    'discount_percentage' => round($discountPercentage, 2),
                ],
            ],
            'message' => 'Clinic statistics retrieved successfully'
        ]);
    }
}
