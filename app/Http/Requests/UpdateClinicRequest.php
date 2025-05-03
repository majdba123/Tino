<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'address' => 'sometimes|required|string|max:500',
            'phone' => 'sometimes|required|string|max:20|regex:/^[0-9]+$/',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'opening_time' => 'sometimes|required|date_format:H:i',
            'closing_time' => 'sometimes|required|date_format:H:i|after:opening_time',
            'type' => 'sometimes|required|in:integrated,external',
            'status' => 'sometimes|required|in:active,inactive',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'حقل اسم العيادة مطلوب',
            'name.max' => 'اسم العيادة يجب ألا يتجاوز 255 حرفاً',

            'address.required' => 'حقل العنوان مطلوب',
            'address.max' => 'العنوان يجب ألا يتجاوز 500 حرف',

            'phone.required' => 'حقل الهاتف مطلوب',
            'phone.max' => 'رقم الهاتف يجب ألا يتجاوز 20 رقماً',
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط',

            'latitude.required' => 'حقل خط العرض مطلوب',
            'latitude.numeric' => 'خط العرض يجب أن يكون رقماً',
            'latitude.between' => 'خط العرض يجب أن يكون بين -90 و 90',

            'longitude.required' => 'حقل خط الطول مطلوب',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقماً',
            'longitude.between' => 'خط الطول يجب أن يكون بين -180 و 180',

            'opening_time.required' => 'حقل وقت الفتح مطلوب',
            'opening_time.date_format' => 'تنسيق وقت الفتح غير صحيح',

            'closing_time.required' => 'حقل وقت الإغلاق مطلوب',
            'closing_time.date_format' => 'تنسيق وقت الإغلاق غير صحيح',
            'closing_time.after' => 'وقت الإغلاق يجب أن يكون بعد وقت الفتح',

            'type.required' => 'حقل نوع العيادة مطلوب',
            'type.in' => 'نوع العيادة يجب أن يكون إما متكاملة أو خارجية',

            'status.required' => 'حقل الحالة مطلوب',
            'status.in' => 'الحالة يجب أن تكون إما نشطة أو غير نشطة',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'اسم العيادة',
            'address' => 'العنوان',
            'phone' => 'رقم الهاتف',
            'latitude' => 'خط العرض',
            'longitude' => 'خط الطول',
            'opening_time' => 'وقت الفتح',
            'closing_time' => 'وقت الإغلاق',
            'type' => 'نوع العيادة',
            'status' => 'الحالة',
        ];
    }
}
