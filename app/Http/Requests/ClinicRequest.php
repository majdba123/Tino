<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClinicRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:20',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'opening_time' => 'required|date_format:H:i',
            'closing_time' => 'required|date_format:H:i|after:opening_time',
            'type' => 'required|in:integrated,external',
        ];
    }

    public function messages()
    {
        return [
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.min' => 'كلمة المرور يجب أن تكون على الأقل 8 أحرف',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'closing_time.after' => 'وقت الإغلاق يجب أن يكون بعد وقت الفتح',
        ];
    }
}
