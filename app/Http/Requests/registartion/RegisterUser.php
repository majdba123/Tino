<?php

namespace App\Http\Requests\registartion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class RegisterUser extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return True;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users', // Make email nullable
            'phone' => 'nullable|string|max:20|unique:users', // Add phone field
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'Email has already been taken.',
            'phone.unique' => 'Phone has already been taken.',
            'password.required' => 'Password is required.',
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
            if (!$this->has('email') && !$this->has('phone')) {
                $validator->errors()->add('email_or_phone', 'You must provide either an email address or a phone number.');
            }elseif($this->has('email') && $this->has('phone'))
            {
                $validator->errors()->add('email_or_phone', 'You must provide either an email address or a phone number.');

            }
        });
    }



}
