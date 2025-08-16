<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'subject' => 'required|string|max:255',
        ]);


        // Create the contact
        $contact = Contact::create([
            'subject' => $request->subject,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);



        return response()->json([
            'success' => true,
            'message' => 'Contact created successfully',
            'data' => [
                'contact' => $contact,
            ]
        ], 201);
    }



    public function myContacts()
    {
        $user = Auth::user();

        // Get contacts with replies for the authenticated user, ordered by latest first
        $contacts = $user->contact()
            ->with(['replies', 'user' => function($query) {
                $query->select('id', 'name', 'email');
            }])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contacts->map(function($contact) {
                return [
                    'id' => $contact->id,
                    'subject' => $contact->subject,
                    'status' => $contact->status,
                    'created_at' => $contact->created_at,
                    'updated_at' => $contact->updated_at,
                    'replies' => $contact->replies,
                    'user' => $contact->user
                ];
            }),
            'user_id' => $user->id
        ]);
    }

    public function storeReply(Request $request, $contact_id)
    {
        try {
            $user = Auth::user(); // الحصول على المستخدم الحالي

            // التحقق من صحة البيانات
            $validated = $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            // البحث عن الشكوى مع المستخدم المرتبط بها
            $contact = Contact::with('user')->find($contact_id);
            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'الشكوى غير موجودة',
                    'errors' => ['contact_id' => ['لم يتم العثور على الشكوى المحددة']]
                ], 404);
            }

            // إنشاء الرد
            $reply = $contact->replies()->create([
                'message' => $validated['message'],
                'user_id' => $user->id,
            ]);

            // إنشاء إشعار للمستخدم
            $notificationMessage = "تم الرد على اتصالك (ID: {$contact->id})";
            $notification = User_Notification::create([
                'user_id' => $contact->user_id, // إرسال الإشعار لصاحب الشكوى
                'message' => $notificationMessage,
                'is_read' => false,
                'is_admin' => false,
            ]);

            // بث الحدث للصفحة الأمامية
            broadcast(new chat1([
                'user_id' => $contact->user_id,
                'message' => $notificationMessage,
                'contact_id' => $contact->id,
                'notification_id' => $notification->id,
                'is_admin' => false
            ]))->toOthers();

            // تحديث حالة الشكوى
            $contact->update(['status' => 'replied']);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الرد بنجاح',
                'data' => [
                    'reply' => $reply,
                    'notification' => $notification,
                    'contact' => [
                        'id' => $contact->id,
                        'status' => 'replied',
                        'updated_at' => $contact->fresh()->updated_at
                    ]
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في التحقق من صحة البيانات',
                'errors' => $e->validator->errors()->toArray()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ ما',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function allContacts(Request $request)
    {
        try {
            // Validate filter parameters
            $validated = $request->validate([
                'status' => 'nullable|in:pending,replied,resolved,closed',
                'user_id' => 'nullable|integer|exists:users,id',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Base query with user relationship
            $query = Contact::with(['replies', 'user' => function($query) {
                $query->select('id', 'name', 'email');
            }])->latest();

            if ($request->has('user_id')) {
                $query->where('user_id', $validated['user_id']);
            }

            // Apply status filter if provided
            if ($request->has('status')) {
                $query->where('status', $validated['status']);
            }

            // Pagination
            $perPage = $validated['per_page'] ?? 15;
            $contacts = $query->paginate($perPage);

            // Format the response
            $response = [
                'success' => true,
                'data' => $contacts->map(function($contact) {
                    return [
                        'id' => $contact->id,
                        'subject' => $contact->subject,
                        'status' => $contact->status,
                        'created_at' => $contact->created_at,
                        'updated_at' => $contact->updated_at,
                        'replies' => $contact->replies,
                        'user' => $contact->user
                    ];
                }),
                'meta' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                ],
                'filters' => [
                    'status' => $request->status,
                    'user_id' => $request->user_id,
                    'per_page' => $perPage
                ]
            ];

            return response()->json($response);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في التحقق من الصحة',
                'errors' => $e->validator->errors()->toArray()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ ما',
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function destroy($contact_id)
    {
        try {
            $user = Auth::user();
            $contact = Contact::find($contact_id);

            // Check if contact exists
            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                    'errors' => ['contact_id' => ['The specified contact does not exist']]
                ], 404);
            }

            // Delete the contact and its replies (using cascading delete if set up in database)
            $contact->replies()->delete();
            $contact->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contact and its replies deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting contact',
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }
}
