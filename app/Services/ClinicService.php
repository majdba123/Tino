<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ClinicService
{
    public function createClinic(array $data)
    {
        // إنشاء المستخدم أولاً
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'type' => 3,
        ]);

        // إنشاء العيادة
        $clinic = Clinic::create([
            'address' => $data['address'],
            'phone' => $data['phone'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'opening_time' => $data['opening_time'],
            'closing_time' => $data['closing_time'],
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
            'status'
        ];

        $updateData = array_intersect_key($data, array_flip($updatableFields));

        $clinic->update($updateData);

        return $clinic;
    }
}
