<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SubscriptionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subscriptions')->ignore($this->subscription)
            ],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_months' => 'required|integer|min:1',
            'type' => 'required|string|in:basic,premium',
            'is_active' => 'sometimes|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'حقل اسم الاشتراك مطلوب.',
            'name.string' => 'يجب أن يكون اسم الاشتراك نصًا.',
            'name.max' => 'يجب ألا يتجاوز اسم الاشتراك 255 حرفًا.',
            'name.unique' => 'اسم الاشتراك مستخدم مسبقًا.',

            'description.string' => 'يجب أن يكون الوصف نصًا.',

            'price.required' => 'حقل السعر مطلوب.',
            'price.numeric' => 'يجب أن يكون السعر رقمًا.',
            'price.min' => 'يجب أن يكون السعر أكبر من أو يساوي الصفر.',

            'duration_months.required' => 'حقل المدة بالشهور مطلوب.',
            'duration_months.integer' => 'يجب أن تكون المدة عددًا صحيحًا.',
            'duration_months.min' => 'يجب أن تكون المدة شهرًا على الأقل.',

            'type.required' => 'حقل نوع الاشتراك مطلوب.',
            'type.string' => 'يجب أن يكون نوع الاشتراك نصًا.',
            'type.in' => 'نوع الاشتراك يجب أن يكون إما basic أو premium.',

            'is_active.boolean' => 'يجب أن تكون حالة التنشيط قيمة منطقية (نعم/لا).'
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'تحقق من الأخطاء في النموذج.',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'type' => strtolower($this->input('type', 'basic'))
        ]);
    }
}
