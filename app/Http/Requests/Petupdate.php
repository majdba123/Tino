<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Petupdate extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:255',
            'breed' => 'sometimes|string|max:255',
            'name_cheap' => 'sometimes|string|max:255',
            'birth_date' => 'sometimes|date|before_or_equal:today',
            'gender' => 'sometimes|in:male,female',
            'health_status' => 'sometimes|in:excellent,good,fair,poor',
            'status' => 'sometimes|in:active,inactive,deceased',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
        ];
    }

    public function messages()
    {
        return [
            'birth_date.before_or_equal' => 'تاريخ الميلاد يجب أن يكون تاريخاً صحيحاً ولا يتجاوز تاريخ اليوم',
            'gender.in' => 'الجنس يجب أن يكون إما ذكر أو أنثى',
            'health_status.in' => 'الحالة الصحية يجب أن تكون واحدة من: ممتازة، جيدة، متوسطة، ضعيفة'
        ];
    }
}
