<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * جلب معلومات المستخدم الحالي
     */
    public function getProfile(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل الدخول'
                ], 401);
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'payment_methods' => $user->payment_methods,
                    'type' => $user->type,
                    'location' => [
                        'lat' => $user->lat,
                        'lang' => $user->lang
                    ]
                ]
            ];

            if ($user->image) {
                $response['data']['image'] = Storage::url($user->image);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب بيانات المستخدم: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث معلومات المستخدم
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل الدخول'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,'.$user->id,
                'phone' => 'sometimes|string|max:20',
                'lat' => 'sometimes|numeric',
                'lang' => 'sometimes|numeric',
                'password' => 'sometimes|string|min:8|confirmed',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
                'payment_methods' =>  'sometimes|in:stripe,paypal'

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->only(['name', 'email', 'phone', 'lat', 'lang','payment_methods']);

            if ($request->has('password')) {
                $data['password'] = bcrypt($request->password);
            }

            if ($request->hasFile('image')) {
                // حذف الصورة القديمة إذا كانت موجودة
                if ($user->image) {
                    $oldImagePath = str_replace(url('api/storage/'), '', $user->image);
                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }

                // حفظ الصورة الجديدة بنفس الطريقة المستخدمة في دالة store
                $imageFile = $request->file('image');
                $imageName = Str::random(32).'.'.$imageFile->getClientOriginalExtension();
                $imagePath = 'users/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                $data['image'] = url('api/storage/' . $imagePath);
            }

            $user->update($data);

            $response = [
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'payment_methods' => $user->payment_methods,
                    'location' => [
                        'lat' => $user->lat,
                        'lang' => $user->lang
                    ],
                    'image' => $user->image
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في تحديث البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب بيانات عيادة المستخدم
     */
    public function getClinicProfile(): JsonResponse
    {
        try {
            $user = Auth::user();
            $clinic = $user->clinic ?? null;

            if (!$clinic) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل الدخول أو ليس لديه عيادة'
                ], 401);
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $clinic->id,
                    'name' => $clinic->user->name,
                    'email' => $clinic->user->email,
                    'address' => $clinic->address,
                    'phone' => $clinic->phone,
                    'location' => [
                        'latitude' => $clinic->latitude,
                        'longitude' => $clinic->longitude
                    ],
                    'working_hours' => [
                        'opening_time' => $clinic->opening_time,
                        'closing_time' => $clinic->closing_time
                    ],
                    'type' => $clinic->type,
                    'status' => $clinic->status,
                    'user_id' => $clinic->user_id
                ]
            ];

            if ($user->image) {
                $response['data']['image'] = Storage::url($user->image);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب بيانات العيادة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث بيانات العيادة
     */
    public function updateClinicProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $clinic = $user->clinic ?? null;

            if (!$clinic) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل الدخول أو ليس لديه عيادة'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'address' => 'sometimes|string',
                'phone' => 'sometimes|string|max:20',
                'latitude' => 'sometimes|numeric',
                'longitude' => 'sometimes|numeric',
                'opening_time' => 'sometimes|date_format:H:i',
                'closing_time' => 'sometimes|date_format:H:i|after:opening_time',
                'user_email' => 'sometimes|email|unique:users,email,'.$user->id,
                'user_password' => 'sometimes|string|min:8|confirmed',
                'user_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
            ], [
                'closing_time.after' => 'يجب أن يكون وقت الإغلاق بعد وقت الفتح',
                'user_email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
                'user_password.min' => 'كلمة المرور يجب أن تكون على الأقل 8 أحرف',
                'user_image.max' => 'حجم الصورة يجب أن لا يتجاوز 2MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $clinic->update($request->only([
                'address', 'phone', 'latitude', 'longitude',
                'opening_time', 'closing_time'
            ]));

            $userData = [];
            if ($request->has('name')) $userData['name'] = $request->name;
            if ($request->has('user_email')) $userData['email'] = $request->user_email;
            if ($request->has('user_password')) $userData['password'] = bcrypt($request->user_password);
            if ($request->has('user_phone')) $userData['phone'] = $request->user_phone;

            if ($request->hasFile('user_image')) {
                if ($user->image && Storage::exists($user->image)) {
                    Storage::delete($user->image);
                }
                $userData['image'] = $request->file('user_image')->store('public/users/images');
            }

            if (!empty($userData)) {
                $user->update($userData);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
                'data' => [
                    'clinic' => $clinic->fresh(),
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'image' => $user->image ? Storage::url($user->image) : null
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في تحديث البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب جميع المستخدمين مع التقسيم والترتيب
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        try {
            $query = User::query()->latest();

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('name')) {
                $query->where('name', 'like', "%$request->name%");
            }

            if ($request->has('email')) {
                $query->where('email', 'like', "%$request->email%");
            }

            if ($request->has('phone')) {
                $query->where('phone', 'like', "%$request->phone%");
            }

            $users = $query->paginate($request->per_page ?? 15);

            $users->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type,
                    'image' => $user->image ? Storage::url($user->image) : null,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'last_page' => $users->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب المستخدمين: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب مستخدم معين بالتفاصيل
     */
    public function getUserById($id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 404);
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type,
                    'location' => [
                        'lat' => $user->lat,
                        'lang' => $user->lang
                    ],
                    'image' => $user->image ? Storage::url($user->image) : null,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $user->updated_at->format('Y-m-d H:i:s')
                ]
            ];

            if ($user->type === '3' && $user->clinic) {
                $response['data']['clinic'] = [
                    'address' => $user->clinic->address,
                    'working_hours' => [
                        'opening_time' => $user->clinic->opening_time,
                        'closing_time' => $user->clinic->closing_time
                    ],
                    'status' => $user->clinic->status
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب بيانات المستخدم: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف مستخدم (للمسؤول فقط)
     */
    public function deleteUser($id): JsonResponse
    {
        try {
            if (Auth::user()->type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح بالوصول'
                ], 403);
            }

            if (Auth::id() == $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكنك حذف حسابك الخاص'
                ], 400);
            }

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 404);
            }

            if ($user->image && Storage::exists($user->image)) {
                Storage::delete($user->image);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المستخدم بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في حذف المستخدم: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث حالة المستخدم (active/banned)
     */
    public function updateUserStatus(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:active,banned'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 404);
            }

            $user->status = $request->status;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة المستخدم بنجاح',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في تحديث حالة المستخدم: ' . $e->getMessage()
            ], 500);
        }
    }
}
