<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PetRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'breed' => 'required|string|max:255',
            'name_cheap' => 'required|string|max:255',
            'birth_date' => 'required|date|before_or_equal:today',
            'gender' => 'required|in:male,female',
            'health_status' => 'required|in:excellent,good,fair,poor',
            'status' => 'sometimes|in:active,inactive,deceased'
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
