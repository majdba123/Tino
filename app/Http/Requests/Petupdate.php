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
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',

            // الحقول الجديدة
            'color' => 'sometimes|string|max:50',
            'weight' => 'sometimes|numeric|min:0|max:200',
            'is_spayed' => 'sometimes|boolean',
            'allergies' => 'sometimes|array',
            'allergies.*' => 'string|max:255',
            'previous_surgeries' => 'sometimes|array',
            'previous_surgeries.*' => 'string|max:500',
            'chronic_conditions' => 'sometimes|array',
            'chronic_conditions.*' => 'string|max:500',
            'vaccination_history' => 'sometimes|array',
            'vaccination_history.*.name' => 'required_with:vaccination_history|string|max:255',
            'vaccination_history.*.date' => 'required_with:vaccination_history.*.name|date',
            'vaccination_history.*.next_due' => 'sometimes|date',
            'last_veterinary_visit' => 'sometimes|date|before_or_equal:today',
            'current_veterinary' => 'sometimes|string|max:255',
            'insurance_company' => 'sometimes|string|max:255',
            'policy_number' => 'sometimes|string|max:100',
            'coverage_details' => 'sometimes|string|max:1000'
        ];
    }

    public function messages()
    {
        return [
            'birth_date.before_or_equal' => 'تاريخ الميلاد يجب أن يكون تاريخاً صحيحاً ولا يتجاوز تاريخ اليوم',
            'gender.in' => 'الجنس يجب أن يكون إما ذكر أو أنثى',
            'health_status.in' => 'الحالة الصحية يجب أن تكون واحدة من: ممتازة، جيدة، متوسطة، ضعيفة',
            'weight.numeric' => 'الوزن يجب أن يكون رقماً',
            'weight.min' => 'الوزن يجب أن يكون على الأقل 0',
            'weight.max' => 'الوزن لا يمكن أن يتجاوز 200 كجم',
            'allergies.*.string' => 'كل عنصر في الحساسية يجب أن يكون نصاً',
            'previous_surgeries.*.string' => 'كل عنصر في العمليات الجراحية السابقة يجب أن يكون نصاً',
            'chronic_conditions.*.string' => 'كل عنصر في الأمراض المزمنة يجب أن يكون نصاً',
            'vaccination_history.*.name.required_with' => 'اسم اللقاح مطلوب عند إضافة تاريخ تطعيم',
            'vaccination_history.*.date.required_with' => 'تاريخ اللقاح مطلوب عند إضافة اسم تطعيم',
            'vaccination_history.*.date.date' => 'تاريخ اللقاح يجب أن يكون تاريخاً صحيحاً',
            'vaccination_history.*.next_due.date' => 'موعد الجرعة التالية يجب أن يكون تاريخاً صحيحاً',
            'last_veterinary_visit.before_or_equal' => 'آخر زيارة للطبيب البيطري يجب أن تكون تاريخاً صحيحاً ولا تتجاوز تاريخ اليوم'
        ];
    }

    /**
     * تحضير البيانات للتحقق (لتحويل JSON strings إلى arrays)
     */
    protected function prepareForValidation()
    {
        // تحويل الحقول JSON إلى arrays إذا كانت مرسلة ك strings
        $jsonFields = ['allergies', 'previous_surgeries', 'chronic_conditions', 'vaccination_history'];

        foreach ($jsonFields as $field) {
            if ($this->has($field) && is_string($this->$field)) {
                try {
                    $this->merge([
                        $field => json_decode($this->$field, true)
                    ]);
                } catch (\Exception $e) {
                    // إذا فشل تحويل JSON، نترك القيمة كما هي وسيفشل التحقق لاحقاً
                }
            }
        }

        // تحويل القيم المنطقية
        if ($this->has('is_spayed') && is_string($this->is_spayed)) {
            $this->merge([
                'is_spayed' => filter_var($this->is_spayed, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }
}
