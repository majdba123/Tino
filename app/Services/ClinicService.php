<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ClinicService
{
   public function createClinic(array $data)
    {
        // التحقق من وجود مستخدم بنفس البريد الإلكتروني
        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser) {
            // إذا كان المستخدم موجودًا بالفعل، تحقق مما إذا كان لديه عيادة
            $existingClinic = Clinic::where('user_id', $existingUser->id)->first();

            if ($existingClinic) {
                throw new \Exception('المستخدم لديه عيادة مسجلة بالفعل');
            }

            // إذا لم يكن لديه عيادة، استخدم المستخدم الموجود
            $user = $existingUser;
        } else {
            // إنشاء مستخدم جديد إذا لم يكن موجودًا
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'user',
                'otp' => '1',
                'type' => 3,
            ]);
        }

        // إنشاء العيادة
        $clinic = Clinic::create([
            'address' => $data['address'],
            'phone' => $data['phone'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'opening_time' => $data['opening_time'],
            'closing_time' => $data['closing_time'],
            'bank_account_info' => $data['bank_account_info'],
            'tax_number' => $data['tax_number'],
            'payment_terms' => $data['payment_terms'],
            'type' => $data['type'],
            'user_id' => $user->id,
        ]);

        return $clinic;
    }

    // في App\Services\ClinicService.php
    public function updateClinic($clinicId, array $data)
    {
        $clinic = Clinic::findOrFail($clinicId);

        $updatableFields = [
            'name',
            'address',
            'phone',
            'latitude',
            'longitude',
            'opening_time',
            'closing_time',
            'type',
            'status',
            'tax_number',
            'bank_account_info',
            'payment_terms'

        ];

        $updateData = array_intersect_key($data, array_flip($updatableFields));

        $clinic->update($updateData);

        return $clinic;
    }
}
