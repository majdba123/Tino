<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalRecordRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'details' => 'required|string',
            'date' => 'required|date',
            'pet_id' => 'required|exists:pets,id',
        ];
    }
}
