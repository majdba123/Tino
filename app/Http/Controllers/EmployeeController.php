<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class EmployeeController extends Controller
{
    // إنشاء موظف جديد
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Rules\Password::defaults()],

        ]);

        // إنشاء المستخدم
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'otp' => '1',
            'type' => '2' // يمكنك إضافة نوع المستخدم
        ]);

        // إنشاء الموظف
        $employee = Employee::create([
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Employee created successfully',
            'employee' => $employee->load('user')
        ], 201);
    }

    // حذف الموظف
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        // حذف المستخدم المرتبط
        User::where('id', $employee->user_id)->delete();

        // سيتم حذف الموظف تلقائياً بسبب onDelete('cascade')

        return response()->json(['message' => 'Employee deleted successfully']);
    }

    // قائمة الموظفين
    public function index(Request $request)
    {
        $query = Employee::with(['user']);

        // Filter by employee name (search in users table)
        if ($request->has('name')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', '%'.$request->name.'%');
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Get paginated results (you can adjust the per-page number)
        $employees = $query->paginate(10);

        return response()->json([
            'data' => $employees->items(),
            'pagination' => [
                'total' => $employees->total(),
                'per_page' => $employees->perPage(),
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem()
            ]
        ]);
    }

public function update(Request $request, $id)
{
    $employee = Employee::findOrFail($id);
    $user = $employee->user;

    $request->validate([
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
        'password' => ['sometimes', Rules\Password::defaults()],
        'status' => 'sometimes|in:active,inactive' // التحقق من القيم المسموحة
    ]);

    // تحديث بيانات المستخدم
    if ($request->has('name')) {
        $user->name = $request->name;
    }

    if ($request->has('email')) {
        $user->email = $request->email;
    }

    if ($request->has('password')) {
        $user->password = Hash::make($request->password);
    }

    $user->save();

    // تحديث حالة الموظف
    if ($request->has('status')) {
        $employee->status = $request->status;
        $employee->save();
    }

    return response()->json([
        'message' => 'Employee updated successfully',
        'employee' => $employee->load('user')
    ]);
}

}
