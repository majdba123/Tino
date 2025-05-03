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
        }]);

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
}
