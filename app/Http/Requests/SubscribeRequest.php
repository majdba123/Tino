<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends FormRequest
{
    public function authorize()
    {
        return true; // سيتم التحكم في الصلاحيات عبر middleware
    }

    public function rules()
    {
        return [
            'subscription_id' => [
                'required',
                'exists:subscriptions,id',
                Rule::exists('subscriptions', 'id')->where('is_active', true)
            ],
            'discount_code' => 'nullable|string|exists:discount_coupons,code',
            'pet_id' => 'required|string|exists:pets,id'


        ];
    }

    public function messages()
    {
        return [
            'subscription_id.required' => 'يجب اختيار اشتراك',
            'subscription_id.exists' => 'الاشتراك المحدد غير متوفر',
           'discount_code.exists' => 'كود الخصم غير صحيح'
        ];
    }
}
