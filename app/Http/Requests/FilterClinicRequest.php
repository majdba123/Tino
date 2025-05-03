<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class FilterClinicRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'phone' => 'sometimes|string|max:20',
            'opening_time' => 'sometimes|date_format:H:i',
            'closing_time' => 'sometimes|date_format:H:i',
            'type' => 'sometimes|in:integrated,external',
            'status' => 'sometimes|in:active,inactive',
        ];
    }

    public function messages()
    {
        return [
            'name.string' => 'يجب أن يكون اسم العيادة نصياً',
            'name.max' => 'يجب ألا يتجاوز اسم العيادة 255 حرفاً',

            'address.string' => 'يجب أن يكون العنوان نصياً',
            'address.max' => 'يجب ألا يتجاوز العنوان 500 حرف',

            'phone.string' => 'يجب أن يكون رقم الهاتف نصياً',
            'phone.max' => 'يجب ألا يتجاوز رقم الهاتف 20 حرفاً',

            'opening_time.date_format' => 'صيغة وقت الفتح غير صحيحة، يجب أن تكون بالساعة:الدقيقة (مثال: 08:00)',

            'closing_time.date_format' => 'صيغة وقت الإغلاق غير صحيحة، يجب أن تكون بالساعة:الدقيقة (مثال: 17:00)',

            'type.in' => 'نوع العيادة غير صحيح، يجب أن يكون إما integrated أو external',

            'status.in' => 'حالة العيادة غير صحيحة، يجب أن تكون إما active أو inactive',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'اسم العيادة',
            'address' => 'العنوان',
            'phone' => 'رقم الهاتف',
            'opening_time' => 'وقت الفتح',
            'closing_time' => 'وقت الإغلاق',
            'type' => 'نوع العيادة',
            'status' => 'حالة العيادة',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        // Customize the response for validation errors
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }


    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('type') == 0 && $this->input('price') > 100) {
                $validator->errors()->add('price', 'The price for a product type category may not be greater than 100.');
            }
        });
    }
}
