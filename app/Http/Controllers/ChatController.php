<?php

namespace App\Http\Controllers;

use App\Models\chat;
use App\Models\User;
use App\Models\User_Notification;
use Illuminate\Http\Request;
use App\Events\chat1;
use App\Events\NotificatinEvent;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{

    public function getUserNotifications()
    {
        $user = Auth::user();

        // الحصول على إشعارات المستخدم + الإشعارات العامة (is_admin = true)
        $notifications = User_Notification::where('user_id', $user->id)
            ->orWhere(function($query) {
                $query->where('is_admin', true);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'notifications' => $notifications
        ]);
    }




    public function markAllAsRead()
    {
        $user = Auth::user();

        User_Notification::where('user_id', $user->id)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث جميع إشعاراتك إلى مقروء'
        ]);
    }


    public function sendAdminNotification(Request $request)
    {


        $validatedData = $request->validate([
            'message' => 'required|string',
        ]);

        // الحصول على جميع المستخدمين
        $user = Auth::user();


        User_Notification::create([
                'user_id' => $user->id,
                'is_admin' => true,
                'message' => $validatedData['message'],
                'is_read' => false,
            ]);

        event(new NotificatinEvent($validatedData['message']));

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الإشعار لجميع المستخدمين بنجاح'
        ]);
    }

}
